import sys
import json
import base64
import os
import traceback
import httpx
from openai import OpenAI

# Forzar flush en todos los prints para Railway
import functools
print = functools.partial(print, flush=True)

# ID DE VERSIÓN ÚNICO PARA TRACKING DE DESPLIEGUE
BUILD_ID = "2026-01-09-v7-LOGGING-DIAGNOSTIC-STABLE"

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
    mode = "CORROBORACIÓN" if context else "EXTRACCIÓN DIRECTA"
    print(f"HTTP Logs | OCR Process | Starting | Mode: {mode} | File: {os.path.basename(file_path)}")
    
    try:
        client = OpenAI(
            api_key=api_key,
            timeout=600.0,
            max_retries=3
        )
        
        # Determinar si es PDF o imagen
        ext = file_path.lower().split('.')[-1]
        base64_images = []
        
        if ext == 'pdf':
            print(f"DEBUG: Detectado PDF. Abriendo con PyMuPDF...")
            import fitz
            doc = fitz.open(file_path)
            num_pages = len(doc)
            print(f"DEBUG: El PDF tiene {num_pages} páginas.")
            max_pages = min(num_pages, 15)
            
            for page_num in range(max_pages):
                print(f"DEBUG: Procesando página {page_num+1}/{max_pages}...")
                page = doc.load_page(page_num)
                pix = page.get_pixmap(dpi=130)
                temp_image = f"{file_path}_p{page_num}.png"
                pix.save(temp_image)
                base64_images.append(encode_image(temp_image))
                os.remove(temp_image)
            doc.close()
            print(f"DEBUG: Conversión de PDF a imágenes completada.")
        else:
            print(f"DEBUG: Detectada imagen ({ext}).")
            base64_images.append(encode_image(file_path))
        
        image_contents = []
        for i, b64 in enumerate(base64_images):
            image_contents.append({
                "type": "image_url",
                "image_url": {
                    "url": f"data:image/jpeg;base64,{b64}",
                    "detail": "high"
                }
            })
        
        print(f"HTTP Logs | OCR Process | Prepared {len(image_contents)} images for OpenAI Vision")
        
        if context:
            print(f"DEBUG: Usando modo CORROBORACIÓN con contexto.")
            system_prompt = """Eres un motor de OCR y validación de datos de alta precisión para facturas aduaneras. 
            Tu tarea es CORROBORAR si los datos del JSON proporcionado coinciden EXACTAMENTE con las imágenes adjuntas. 
            Corrige cualquier discrepancia basándote EXCLUSIVAMENTE en lo que ves en las imágenes.
            
            Datos actuales para corroborar: """ + json.dumps(context, ensure_ascii=False)
            user_prompt = "Compara los datos proporcionados con las imágenes y devuelve el JSON corregido siguiendo exactamente el mismo esquema."
        else:
            print(f"DEBUG: Usando modo EXTRACCIÓN DIRECTA.")
            system_prompt = """Eres un motor de OCR de grado industrial especializado en facturas comerciales y documentos de transporte.
            Tu objetivo es extraer información estructurada con precisión del 100%.
            
            ESQUEMA DE RESPUESTA (JSON):
            {
              "facturas": [
                {
                  "texto_completo": "Todo el texto detectado...",
                  "datos_estructurados": {
                    "numero_factura": "ID literal",
                    "fecha": "YYYY-MM-DD",
                    "proveedor": "Nombre Empresa Vendedora",
                    "moneda": "USD, EUR, etc.",
                    "pais_origen": "País de salida/fabricación",
                    "remitente": { "nombre": "", "direccion": "", "contacto": "", "telefono": "" },
                    "consignatario": { "nombre": "", "direccion": "", "contacto": "", "pais": "" },
                    "items": [
                      {
                        "numero_linea": 1,
                        "numero_serie_parte": "SKU o Part Number",
                        "descripcion": "Descripción detallada de la mercancía",
                        "cantidad": 00.00,
                        "unidad_medida": "PCS, KG, SETS",
                        "precio_unitario": 00.00,
                        "precio_total": 00.00,
                        "caracteristicas": "HS Code, materiales, etc.",
                        "datos_importantes": "Cualquier otra info técnica"
                      }
                    ],
                    "totales": {
                      "subtotal": 0.0,
                      "descuento": 0.0,
                      "impuesto_monto": 0.0,
                      "impuesto_porcentaje": 0.0,
                      "total_final": 0.0
                    }
                  }
                }
              ]
            }

            REGLAS CRÍTICAS:
            1. Si hay múltiples facturas en el mismo archivo, genera un objeto por cada una en el array "facturas".
            2. Extrae TODOS los ítems de la tabla. No omitas ninguno.
            3. Si un dato no existe, usa null o cadena vacía, pero mantén la estructura.
            4. Los números deben ser numéricos (no strings si es posible) o strings limpios de símbolos.
            """
            user_prompt = "Analiza detalladamente las imágenes y extrae toda la información en el formato JSON solicitado."

        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": [{"type": "text", "text": user_prompt}, *image_contents]}
            ],
            response_format={"type": "json_object"},
            max_tokens=8000
        )
        
        content = response.choices[0].message.content
        print(f"HTTP Logs | OCR Process | OpenAI Response Received | Size: {len(content)} characters")
        
        try:
            result = json.loads(content)
        except:
            print(f"DEBUG: El JSON de OpenAI parecía truncado, intentando reparar...")
            result = json.loads(repair_truncated_json(content))
            print(f"DEBUG: JSON reparado exitosamente.")
        
        if "facturas" in result:
            facturas_list = result["facturas"]
        elif isinstance(result, list):
            facturas_list = result
        else:
            facturas_list = [result]

        print(f"DEBUG: Se detectaron {len(facturas_list)} facturas en la respuesta.")

        for i, factura in enumerate(facturas_list):
            print(f"DEBUG: Corrigiendo datos lógicos de factura {i+1}...")
            correct_invoice_data(factura)

        final_result = {
            'success': True,
            'build_id': BUILD_ID,
            'facturas': sanitize_for_json(facturas_list),
            'metodo': 'intelligent-ocr-engine' if not context else 'intelligent-ocr-verified'
        }
        
        print(f"HTTP Logs | OCR Process | Success | Invoices detected: {len(facturas_list)}")
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
