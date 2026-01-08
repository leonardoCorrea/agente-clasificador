import sys
import json
import base64
import os
import traceback
import httpx
from openai import OpenAI

# ID DE VERSIÓN ÚNICO PARA TRACKING DE DESPLIEGUE
BUILD_ID = "2026-01-07-v6-TIMEOUT-AUTH-STABLE"

# SOLUCIÓN RADICAL PARA ERROR 'proxies':
for env_var in ['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy']:
    if env_var in os.environ:
        del os.environ[env_var]

def encode_image(image_path):
    """Codificar imagen a base64"""
    with open(image_path, "rb") as image_file:
        return base64.b64encode(image_file.read()).decode('utf-8')

def sanitize_for_json(obj):
    """
    Recursively sanitize all string values in a dict/list to ensure JSON compatibility.
    """
    if isinstance(obj, dict):
        return {k: sanitize_for_json(v) for k, v in obj.items()}
    elif isinstance(obj, list):
        return [sanitize_for_json(item) for item in obj]
    elif isinstance(obj, str):
        sanitized = ''.join(char for char in obj if ord(char) >= 32 or char in '\n\r\t')
        if len(sanitized) > 50000:
            sanitized = sanitized[:50000] + "... [truncado]"
        return sanitized
    else:
        return obj

def repair_truncated_json(json_str):
    """
    Intenta reparar JSON truncado cerrando strings, arrays y objetos abiertos.
    """
    quote_count = json_str.count('"') - json_str.count('\\"')
    if quote_count % 2 != 0:
        json_str += '"'
    open_braces = json_str.count('{') - json_str.count('}')
    open_brackets = json_str.count('[') - json_str.count(']')
    json_str += ']' * open_brackets
    json_str += '}' * open_braces
    return json_str

def process_ocr(file_path, api_key, context=None):
    """
    Procesar OCR o Corroboración usando Vision Multi.
    Soporta múltiples páginas y múltiples facturas por archivo.
    """
    try:
        client = OpenAI(
            api_key=api_key,
            timeout=300.0,
            max_retries=3
        )
        
        # Determinar si es PDF o imagen
        ext = file_path.lower().split('.')[-1]
        base64_images = []
        
        if ext == 'pdf':
            import fitz
            doc = fitz.open(file_path)
            max_pages = min(len(doc), 15)
            for page_num in range(max_pages):
                page = doc.load_page(page_num)
                pix = page.get_pixmap(dpi=130)
                temp_image = f"{file_path}_p{page_num}.png"
                pix.save(temp_image)
                base64_images.append(encode_image(temp_image))
                os.remove(temp_image)
            doc.close()
        else:
            base64_images.append(encode_image(file_path))
        
        image_contents = []
        for b64 in base64_images:
            image_contents.append({
                "type": "image_url",
                "image_url": {
                    "url": f"data:image/jpeg;base64,{b64}",
                    "detail": "high"
                }
            })

        if context:
            system_prompt = """Eres un motor de OCR y validación de datos de alta precisión. 
            Tu tarea es CORROBORAR si los datos del JSON coinciden EXACTAMENTE con la imagen. Corrígelos si es necesario.
            Datos actuales: """ + json.dumps(context, ensure_ascii=False)
            user_prompt = "Devuelve el JSON corregido y completo."
        else:
            system_prompt = """Eres un motor de OCR especializado en facturas. Extrae todos los datos con precisión.
            REGLAS:
            1. EXTRACCIÓN COMPLETA DE ÍTEMS.
            2. NÚMERO DE FACTURA LITERAL.
            3. DESC. CONCISAS.
            4. TOTAL_FINAL VERIFICADO.
            """
            user_prompt = "Analiza las imágenes y extrae la información en JSON."

        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": [{"type": "text", "text": user_prompt}, *image_contents]}
            ],
            response_format={"type": "json_object"},
            max_tokens=4000
        )
        
        content = response.choices[0].message.content
        try:
            result = json.loads(content)
        except:
            result = json.loads(repair_truncated_json(content))
        
        if "facturas" in result:
            facturas_list = result["facturas"]
        elif isinstance(result, list):
            facturas_list = result
        else:
            facturas_list = [result]

        for factura in facturas_list:
            correct_invoice_data(factura)

        final_result = {
            'success': True,
            'build_id': BUILD_ID,
            'facturas': sanitize_for_json(facturas_list),
            'metodo': 'intelligent-ocr-engine' if not context else 'intelligent-ocr-verified'
        }
        
        return final_result

    except Exception as e:
        error_trace = traceback.format_exc()
        return {
            'success': False,
            'build_id': BUILD_ID,
            'error': f"{str(e)}\n\n--- PYTHON STACK TRACE ---\n{error_trace}"
        }

def clean_amount(value):
    if not value: return 0.0
    if isinstance(value, (int, float)): return float(value)
    clean = str(value).replace('$', '').replace('USD', '').replace(',', '').strip()
    try: return float(clean)
    except: return 0.0

def correct_invoice_data(factura):
    datos = factura.get('datos_estructurados', {})
    items = datos.get('items', [])
    totales = datos.get('totales', {})
    calc_subtotal = 0.0
    for item in items:
        cant = clean_amount(item.get('cantidad', 0))
        unit = clean_amount(item.get('precio_unitario', 0))
        total = clean_amount(item.get('precio_total', 0))
        if cant > 0 and unit > 0:
            calc = cant * unit
            if abs(calc - total) > 0.05:
                item['precio_total'] = round(calc, 2)
                calc_subtotal += calc
            else: calc_subtotal += total
        else: calc_subtotal += total
    
    ext_total = clean_amount(totales.get('total_final', 0))
    imp = clean_amount(totales.get('impuesto_monto', 0))
    expected = calc_subtotal + imp - clean_amount(totales.get('descuento', 0))
    if abs(ext_total - expected) > 1.0:
        totales['total_final'] = round(expected, 2)
