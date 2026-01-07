# Script Python para OCR con Vision Multi
# Ejecutado directamente desde PHP, NO usa Flask
# Recibe: ruta de archivo como argumento
# Retorna: JSON a stdout

import sys
import json
import base64
import os
from openai import OpenAI

# Forzar salida en UTF-8 para evitar problemas de codificación en Windows
if sys.stdout.encoding != 'utf-8':
    try:
        import io
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    except:
        pass

def encode_image(image_path):
    """Codificar imagen a base64"""
    with open(image_path, "rb") as image_file:
        return base64.b64encode(image_file.read()).decode('utf-8')

def sanitize_for_json(obj):
    """
    Recursively sanitize all string values in a dict/list to ensure JSON compatibility.
    Removes problematic control characters but lets json.dumps handle escaping.
    """
    if isinstance(obj, dict):
        return {k: sanitize_for_json(v) for k, v in obj.items()}
    elif isinstance(obj, list):
        return [sanitize_for_json(item) for item in obj]
    elif isinstance(obj, str):
        # Eliminar SOLO caracteres de control problemáticos (< 32) excepto \n, \r, \t
        # NO escapar comillas manualmente - json.dumps lo hará correctamente
        sanitized = ''.join(
            char for char in obj 
            if ord(char) >= 32 or char in '\n\r\t'
        )
        
        # Limitar longitud para evitar problemas de memoria/tamaño
        if len(sanitized) > 50000:  # 50KB por campo
            sanitized = sanitized[:50000] + "... [truncado]"
        
        return sanitized
    else:
        return obj

def repair_truncated_json(json_str):
    """
    Intenta reparar JSON truncado cerrando strings, arrays y objetos abiertos.
    """
    # Contar comillas para ver si hay una string sin cerrar
    quote_count = json_str.count('"') - json_str.count('\\"')
    
    # Si hay número impar de comillas, cerrar la string
    if quote_count % 2 != 0:
        json_str += '"'
    
    # Contar llaves y corchetes abiertos
    open_braces = json_str.count('{') - json_str.count('}')
    open_brackets = json_str.count('[') - json_str.count(']')
    
    # Cerrar arrays abiertos
    json_str += ']' * open_brackets
    
    # Cerrar objetos abiertos
    json_str += '}' * open_braces
    
    return json_str

def process_ocr(file_path, api_key, context=None):
    """
    Procesar OCR o Corroboración usando Vision Multi.
    Soporta múltiples páginas y múltiples facturas por archivo.
    """
    try:
        client = OpenAI(api_key=api_key)
        
        # Determinar si es PDF o imagen
        ext = file_path.lower().split('.')[-1]
        base64_images = []
        
        if ext == 'pdf':
            # Para PDF, convertir páginas a imágenes usando PyMuPDF (fitz)
            import fitz  # PyMuPDF
            doc = fitz.open(file_path)
            
            # Limitar a las primeras 15 páginas para evitar exceder límites de tokens/imágenes de Vision Multi
            max_pages = min(len(doc), 15)
            
            for page_num in range(max_pages):
                page = doc.load_page(page_num)
                pix = page.get_pixmap(dpi=130) # DPI optimizado para velocidad vs precisión
                temp_image = f"{file_path}_p{page_num}.png"
                pix.save(temp_image)
                base64_images.append(encode_image(temp_image))
                os.remove(temp_image)
            
            doc.close()
        else:
            # Imagen directa
            base64_images.append(encode_image(file_path))
        
        # Preparar contenido de imágenes para el mensaje
        image_contents = []
        for b64 in base64_images:
            image_contents.append({
                "type": "image_url",
                "image_url": {
                    "url": f"data:image/jpeg;base64,{b64}",
                    "detail": "high"
                }
            })

        # Definir Prompts según modo
        if context:
            system_prompt = """Eres un motor de OCR y validación de datos de alta precisión. 
            Tu tarea es CORROBORAR si los datos del JSON coinciden EXACTAMENTE con la imagen.
            Si hay errores, corrígelos. Si faltan datos, agrégalos. 
            
            IMPORTANTE: Si el documento tiene múltiples facturas, devuelve un ARRAY de objetos JSON de facturas.
            Prioriza los DATOS ESTRUCTURADOS. El campo "texto_completo" debe ser BREVE (máximo 500 caracteres).

            Datos actuales para corroborar: """ + json.dumps(context, ensure_ascii=False)
            user_prompt = "Compara estos datos con las imágenes de la factura y devuelve el JSON corregido y completo (como un array de facturas)."
        else:
            system_prompt = """Eres un motor de OCR especializado en facturas complejas.
            Tu misión es extraer TODOS los datos posibles con extrema precisión, SIN RESUMIR.
            
            ⚠️ LÍMITE DE TOKENS: Tienes un límite estricto de respuesta. Debes ser EXTREMADAMENTE CONCISO en todos los campos de texto.
            
            REGLAS DE EXTRACCIÓN CRÍTICAS:
            1. **EXTRACCIÓN COMPLETA DE ÍTEMS:** Si la factura tiene 20 líneas, DEBES devolver 20 objetos en el array 'items'. Si tiene 100, devuelve 100. NO RESUMAS. NO AGRUPES. Extrae línea por línea tal cual aparece.
            2. **NÚMERO DE FACTURA:** Busca "Factura", "Invoice", "Bill", "Ref", o símbolos como "#". Extrae el valor LITERALMENTE (ej: "Invoice #12345").
            3. **DESCRIPCIONES ULTRA CONCISAS:** 
               - 'descripcion': MÁXIMO 80 caracteres (solo nombre esencial del producto)
               - 'caracteristicas': MÁXIMO 50 caracteres (solo info crítica)
               - 'datos_importantes': MÁXIMO 50 caracteres (solo códigos)
               - 'numero_serie_parte': MÁXIMO 30 caracteres
            4. **MONEDA Y MONTOS:** Ten CUIDADO con símbolos confusos ('$' vs '5'). Verifica matemáticamente (cantidad * precio = total).
            5. **TEXTO_COMPLETO MUY BREVE:** El campo "texto_completo" debe ser ULTRA CONCISO (máximo 150 caracteres): solo número factura, proveedor, fecha, total. NADA MÁS.
            6. **ESTRUCTURA EXACTA:** Devuelve SOLO el JSON válido. NO agregues explicaciones ni texto adicional.
            
            ESTRUCTURA JSON REQUERIDA:
            {
                "facturas": [
                    {
                        "texto_completo": "Breve resumen (max 150 chars)",
                        "datos_estructurados": {
                            "numero_factura": "String",
                            "fecha": "YYYY-MM-DD",
                            "proveedor": "String (max 100 chars)",
                            "moneda": "USD",
                            "remitente": {
                                "nombre": "String (max 100 chars)",
                                "direccion": "String (max 150 chars)",
                                "contacto": "String (max 80 chars)",
                                "telefono": "String (max 30 chars)"
                            },
                            "consignatario": {
                                "nombre": "String (max 100 chars)",
                                "direccion": "String (max 150 chars)",
                                "contacto": "String (max 80 chars)",
                                "pais": "String (max 50 chars)"
                            },
                            "pais_origen": "String (max 50 chars)",
                            "totales": {
                                "subtotal": 0.00,
                                "descuento": 0.00,
                                "impuesto_monto": 0.00,
                                "impuesto_porcentaje": 0.00,
                                "total_final": 0.00
                            },
                            "items": [
                                {
                                    "numero_linea": 1,
                                    "numero_serie_parte": "String (max 30 chars)",
                                    "descripcion": "String (max 80 chars)",
                                    "caracteristicas": "String (max 50 chars)",
                                    "datos_importantes": "String (max 50 chars)",
                                    "unidad_medida": "String",
                                    "cantidad": 0.00,
                                    "precio_unitario": 0.00,
                                    "precio_total": 0.00
                                }
                            ]
                        }
                    }
                ]
            }"""
            user_prompt = "Analiza las imágenes y extrae la información completa de la factura siguiendo estrictamente el esquema JSON proporcionado. ⚠️ CRÍTICO: Mantén TODOS los campos de texto EXTREMADAMENTE BREVES (respeta los límites de caracteres). Si la factura tiene muchos items, sé aún más conciso en las descripciones para evitar truncamiento."

        # Llamar a Vision Multi con max_tokens optimizado
        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {
                    "role": "system",
                    "content": system_prompt
                },
                {
                    "role": "user",
                    "content": [
                        {"type": "text", "text": user_prompt},
                        *image_contents
                    ]
                }
            ],
            response_format={"type": "json_object"},
            max_tokens=8000  # Reducido para evitar truncamiento en facturas grandes
        )
        
        content = response.choices[0].message.content
        finish_reason = response.choices[0].finish_reason
        
        # Detectar si la respuesta fue truncada por límite de tokens
        if finish_reason == 'length':
            import sys
            print(f"WARNING: Response was truncated due to token limit. Invoice may have too many items.", file=sys.stderr)
            print(f"Attempting to repair truncated JSON...", file=sys.stderr)
        
        # Intentar parsear el JSON con manejo robusto de errores
        try:
            result = json.loads(content)
        except json.JSONDecodeError as e:
            # Si falla el parsing, intentar varias estrategias de reparación
            import sys
            print(f"Warning: JSON decode error: {str(e)}", file=sys.stderr)
            print(f"Problematic content (first 500 chars): {content[:500]}", file=sys.stderr)
            
            # Estrategia 1: Limpiar caracteres de control
            try:
                cleaned_content = ''.join(
                    char for char in content 
                    if ord(char) >= 32 or char in '\n\r\t'
                )
                result = json.loads(cleaned_content)
                print("Successfully parsed after cleaning control characters", file=sys.stderr)
            except json.JSONDecodeError:
                # Estrategia 2: Reparar JSON truncado
                try:
                    repaired_content = repair_truncated_json(content)
                    result = json.loads(repaired_content)
                    print("Successfully parsed after repairing truncated JSON", file=sys.stderr)
                except json.JSONDecodeError:
                    # Estrategia 3: Combinar limpieza + reparación
                    try:
                        cleaned_content = ''.join(
                            char for char in content 
                            if ord(char) >= 32 or char in '\n\r\t'
                        )
                        repaired_content = repair_truncated_json(cleaned_content)
                        result = json.loads(repaired_content)
                        print("Successfully parsed after cleaning + repairing", file=sys.stderr)
                    except json.JSONDecodeError as e2:
                        # Si aún falla, retornar error con detalles
                        error_msg = f"OpenAI returned invalid JSON. Error: {str(e)}"
                        
                        # Incluir información sobre truncamiento si está disponible
                        if 'finish_reason' in locals() and finish_reason == 'length':
                            error_msg += "\n\n⚠️ CAUSA: La respuesta fue TRUNCADA por OpenAI debido al límite de tokens."
                            error_msg += "\n\n💡 SOLUCIONES:"
                            error_msg += "\n   1. La factura tiene demasiados items (>40). Considera dividirla en múltiples archivos."
                            error_msg += "\n   2. Intenta con una factura más simple o con menos items."
                            error_msg += "\n   3. Si es una factura crítica, contacta al administrador para ajustar el procesamiento por lotes."
                        else:
                            error_msg += "\n\n💡 SUGERENCIA: La respuesta parece estar truncada o contiene caracteres inválidos."
                        
                        error_msg += f"\n\nFirst 1000 characters of response:\n{content[:1000]}"
                        error_msg += f"\n\nLast 500 characters:\n{content[-500:]}"
                        
                        return {
                            'success': False,
                            'error': error_msg
                        }
        
        # Normalizar respuesta (asegurar que es un array de facturas)
        if "facturas" in result:
            facturas_list = result["facturas"]
        elif isinstance(result, list):
            facturas_list = result
        else:
            facturas_list = [result]

        # Post-procesamiento: truncar campos largos AGRESIVAMENTE para evitar JSON gigantes
        for factura in facturas_list:
            # Truncar texto_completo agresivamente
            if 'texto_completo' in factura and len(factura['texto_completo']) > 150:
                factura['texto_completo'] = factura['texto_completo'][:150] + "..."
            
            datos = factura.get('datos_estructurados', {})
            
            # Truncar campos de header
            if 'proveedor' in datos and len(str(datos['proveedor'])) > 100:
                datos['proveedor'] = str(datos['proveedor'])[:100] + "..."
            
            # Truncar remitente
            remitente = datos.get('remitente', {})
            if 'nombre' in remitente and len(str(remitente['nombre'])) > 100:
                remitente['nombre'] = str(remitente['nombre'])[:100] + "..."
            if 'direccion' in remitente and len(str(remitente['direccion'])) > 150:
                remitente['direccion'] = str(remitente['direccion'])[:150] + "..."
            if 'contacto' in remitente and len(str(remitente['contacto'])) > 80:
                remitente['contacto'] = str(remitente['contacto'])[:80] + "..."
            
            # Truncar consignatario
            consignatario = datos.get('consignatario', {})
            if 'nombre' in consignatario and len(str(consignatario['nombre'])) > 100:
                consignatario['nombre'] = str(consignatario['nombre'])[:100] + "..."
            if 'direccion' in consignatario and len(str(consignatario['direccion'])) > 150:
                consignatario['direccion'] = str(consignatario['direccion'])[:150] + "..."
            if 'contacto' in consignatario and len(str(consignatario['contacto'])) > 80:
                consignatario['contacto'] = str(consignatario['contacto'])[:80] + "..."
            
            items = datos.get('items', [])
            
            # Truncar descripciones y características de items MUY AGRESIVAMENTE
            for item in items:
                if 'numero_serie_parte' in item and len(str(item['numero_serie_parte'])) > 30:
                    item['numero_serie_parte'] = str(item['numero_serie_parte'])[:30]
                
                if 'descripcion' in item and len(str(item['descripcion'])) > 80:
                    item['descripcion'] = str(item['descripcion'])[:80]
                
                if 'caracteristicas' in item and len(str(item['caracteristicas'])) > 50:
                    item['caracteristicas'] = str(item['caracteristicas'])[:50]
                
                if 'datos_importantes' in item and len(str(item['datos_importantes'])) > 50:
                    item['datos_importantes'] = str(item['datos_importantes'])[:50]
        
        # Corrección de totales
        for factura in facturas_list:
            correct_invoice_data(factura)

        # IMPORTANTE: Sanitizar todo el resultado antes de retornar
        final_result = {
            'success': True,
            'facturas': sanitize_for_json(facturas_list),
            'metodo': 'intelligent-ocr-engine' if not context else 'intelligent-ocr-verified'
        }
        
        return final_result

    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

def clean_amount(value):
    """Convierte string de moneda a float, manejando errores comunes"""
    if not value:
        return 0.0
    if isinstance(value, (int, float)):
        return float(value)
    
    # Eliminar símbolos de moneda y separadores de miles
    clean = str(value).replace('$', '').replace('USD', '').replace(',', '').strip()
    try:
        return float(clean)
    except ValueError:
        return 0.0

def correct_invoice_data(factura):
    """
    Verifica y corrige datos de la factura basados en reglas matemáticas.
    Específicamente maneja el error de OCR donde '$' se lee como '5'.
    """
    datos = factura.get('datos_estructurados', {})
    items = datos.get('items', [])
    totales = datos.get('totales', {})
    
    # 1. Calcular suma de items
    calculated_subtotal = 0.0
    
    for item in items:
        cant = clean_amount(item.get('cantidad', 0))
        unit = clean_amount(item.get('precio_unitario', 0))
        total_item = clean_amount(item.get('precio_total', 0))
        
        # Corregir total del item si difiere de cant * unit
        if cant > 0 and unit > 0:
            calc_total = cant * unit
            # Si hay discrepancia mayor a 0.05
            if abs(calc_total - total_item) > 0.05:
                # Si el total del item es enorme, probablemente tenga el error del '5'
                if total_item > calc_total * 5 and str(item.get('precio_total', '')).startswith('5'):
                     # Intentar quitar el primer dígito
                     pass # Asumimos que cant * unit es la verdad
                
                # Actualizamos al calculado que es más confiable
                item['precio_total'] = round(calc_total, 2)
                calculated_subtotal += calc_total
            else:
                 calculated_subtotal += total_item
        else:
            calculated_subtotal += total_item
            
    # 2. Verificar Total General
    extracted_total = clean_amount(totales.get('total_final', 0))
    extracted_subtotal = clean_amount(totales.get('subtotal', 0))
    
    # Si no hay items, poco podemos hacer, pero si hay:
    if calculated_subtotal > 0:
        # Umbral de error (propina, pequeños impuestos no detectados, redondeo)
        diff = abs(extracted_total - calculated_subtotal)
        
        # Caso específico: Error '$' -> '5'
        # Ejemplo: Real 1500.00, OCR lee 51500.00
        # El valor leído es aprox (Real + 50000) o (Real * 10 + 5...) dependiendo de la posición
        
        if diff > calculated_subtotal: 
            # Si el total extrañamente alto empieza con 5
            total_str = str(totales.get('total_final', ''))
            clean_str = total_str.replace(',', '').replace('$', '').strip()
            
            if clean_str.startswith('5'):
                # Hipótesis 1: El 5 es un $ extra. Intentamos quitar el primer caracter
                corrected_str = clean_str[1:]
                try:
                    corrected_val = float(corrected_str)
                    if abs(corrected_val - calculated_subtotal) < (calculated_subtotal * 0.1): # 10% margen
                        totales['total_final'] = corrected_val
                        return # Corregido
                except:
                    pass
        
        # Si la suma de items es consistente y el total header está mal, confiamos en la suma de items
        # (mas impuestos si los hay)
        
        impuestos = clean_amount(totales.get('impuesto_monto', 0))
        expected_total = calculated_subtotal + impuestos - clean_amount(totales.get('descuento', 0))
        
        # Si el total extraído difiere significativamente del esperado, usamos el calculado
        if abs(extracted_total - expected_total) > 1.0:
            # Solo si el calculado no es cero
             totales['total_final'] = round(expected_total, 2)
             
            # Si el subtotal también estaba mal, lo arreglamos
             if abs(extracted_subtotal - calculated_subtotal) > 1.0:
                 totales['subtotal'] = round(calculated_subtotal, 2)

if __name__ == '__main__':
    # Argumentos: python ocr_process.py <archivo> <api_key> [contexto_json]
    try:
        if len(sys.argv) < 3:
            result = {'success': False, 'error': 'Faltan argumentos'}
            print(json.dumps(result, ensure_ascii=False, indent=None))
            sys.exit(1)
        
        file_path = sys.argv[1]
        api_key = sys.argv[2]
        context = None
        
        if len(sys.argv) > 3:
            try:
                context = json.loads(sys.argv[3])
            except Exception as ctx_err:
                # Si el contexto falla, continuar sin él
                pass
        
        if not os.path.exists(file_path):
            result = {'success': False, 'error': 'Archivo no encontrado'}
            print(json.dumps(result, ensure_ascii=False, indent=None))
            sys.exit(1)
        
        result = process_ocr(file_path, api_key, context)
        
        # Asegurar que el JSON sea válido antes de imprimirlo
        try:
            # Serializar con ensure_ascii=False para mantener caracteres UTF-8
            # pero sin indent para evitar problemas con saltos de línea
            json_output = json.dumps(result, ensure_ascii=False, indent=None)
            print(json_output)
        except (TypeError, ValueError) as json_err:
            # Si hay error al serializar, intentar con ensure_ascii=True
            try:
                json_output = json.dumps(result, ensure_ascii=True, indent=None)
                print(json_output)
            except Exception as fallback_err:
                # Último recurso: mensaje de error simple
                error_result = {
                    'success': False, 
                    'error': f'Error al serializar JSON: {str(json_err)}'
                }
                print(json.dumps(error_result, ensure_ascii=True))
                
    except Exception as e:
        # Capturar cualquier excepción y retornar JSON válido
        error_result = {
            'success': False, 
            'error': str(e),
            'error_type': type(e).__name__
        }
        try:
            print(json.dumps(error_result, ensure_ascii=False, indent=None))
        except:
            # Si incluso esto falla, usar ASCII puro
            print(json.dumps(error_result, ensure_ascii=True))
