# Microservicio OCR - Flask
# Extracción de texto de facturas usando OpenAI Vision Multi
# Este servicio reemplaza a Tesseract OCR para una mayor precisión

from flask import Flask, request, jsonify
from flask_cors import CORS
import openai
import base64
import os
import tempfile
import logging
import time
import json
from pdf2image import convert_from_path

# Configuración de logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)

# Configurar API key desde variable de entorno
openai.api_key = os.getenv('OPENAI_API_KEY', '')

def encode_image(image_path):
    """Codificar imagen a base64"""
    with open(image_path, "rb") as image_file:
        return base64.b64encode(image_file.read()).decode('utf-8')

@app.route('/ocr', methods=['POST'])
def process_ocr():
    """
    Endpoint para procesar OCR de facturas con Vision Multi
    Acepta: archivos PDF o imágenes
    Retorna: JSON con texto extraído y datos estructurados
    """
    start_time = time.time()
    
    try:
        if not openai.api_key:
            return jsonify({
                'success': False,
                'error': 'API Key de OpenAI no configurada'
            }), 500

        # Verificar archivo
        if 'file' not in request.files:
            return jsonify({'success': False, 'error': 'No se recibió archivo'}), 400
        
        file = request.files['file']
        if file.filename == '':
            return jsonify({'success': False, 'error': 'Nombre de archivo vacío'}), 400
        
        # Guardar archivo temporalmente
        with tempfile.TemporaryDirectory() as temp_dir:
            file_path = os.path.join(temp_dir, file.filename)
            file.save(file_path)
            
            # Obtener extensión
            ext = file.filename.rsplit('.', 1)[1].lower()
            image_paths = []
            
            # Convertir PDF a imágenes o usar imagen directa
            if ext == 'pdf':
                logger.info("Convirtiendo PDF a imágenes...")
                try:
                    images = convert_from_path(file_path, dpi=200, fmt='jpeg')
                    # Procesar solo la primera página para ahorrar tokens/tiempo por ahora
                    # O limitar a 3 páginas máximo
                    max_pages = min(len(images), 3)
                    for i in range(max_pages):
                        img_path = os.path.join(temp_dir, f'page_{i}.jpg')
                        images[i].save(img_path, 'JPEG')
                        image_paths.append(img_path)
                except Exception as e:
                    logger.error(f"Error convirtiendo PDF: {e}")
                    return jsonify({'success': False, 'error': f'Error procesando PDF: {str(e)}'}), 500
            elif ext in ['jpg', 'jpeg', 'png']:
                image_paths.append(file_path)
            else:
                return jsonify({'success': False, 'error': 'Formato no soportado'}), 400
            
            # Procesar con Vision Multi
            full_text = ""
            structured_data = {}
            
            for img_path in image_paths:
                base64_image = encode_image(img_path)
                
                logger.info(f"Enviando imagen a GPT-4o...")
                response = openai.ChatCompletion.create(
                    model="gpt-4o",
                    messages=[
                        {
                            "role": "system",
                            "content": """Eres un experto en extracción de datos de facturas.
                            Tu tarea es extraer TODO el texto visible de la imagen y además identificar datos clave.
                            
                            Responde SIEMPRE en formato JSON válido con esta estructura:
                            {
                                "texto_completo": "Todo el texto extraído línea por línea...",
                                "datos_estructurados": {
                                    "numero_factura": "XXX",
                                    "fecha": "DD/MM/YYYY",
                                    "proveedor": "Nombre Proveedor",
                                    "total": "0.00",
                                    "moneda": "USD",
                                    "items": [
                                        {"descripcion": "Item 1", "cantidad": 1, "precio_unitario": 0.00, "total": 0.00}
                                    ]
                                }
                            }
                            """
                        },
                        {
                            "role": "user",
                            "content": [
                                {"type": "text", "text": "Extrae la información de esta factura."},
                                {
                                    "type": "image_url",
                                    "image_url": {
                                        "url": f"data:image/jpeg;base64,{base64_image}"
                                    }
                                }
                            ]
                        }
                    ],
                    response_format={"type": "json_object"},
                    max_tokens=4096
                )
                

def clean_json_response(response_text):
    """
    Limpia la respuesta para asegurar que sea JSON válido.
    Elimina bloques de código markdown y espacios extra.
    """
    text = response_text.strip()
    
    # Eliminar bloques de código markdown ```json ... ```
    if "```" in text:
        import re
        # Buscar contenido entre ```json y ``` (o solo ```)
        match = re.search(r"```(?:json)?(.*?)```", text, re.DOTALL)
        if match:
            text = match.group(1).strip()
            
    return text

def balance_json(json_str):
    """
    Intenta reparar JSON truncado cerrando llaves y corchetes.
    """
    stack = []
    # Contar estructuras abiertas
    for char in json_str:
        if char == '{':
            stack.append('}')
        elif char == '[':
            stack.append(']')
        elif char == '}' or char == ']':
            if stack:
                last = stack[-1]
                if char == last:
                    stack.pop()
                    
    # Cerrar estructuras pendientes en orden inverso
    while stack:
        json_str += stack.pop()
        
    return json_str

# ... (inside process_ocr loop) ...
                
                result_content = response.choices[0].message.content
                logger.info(f"Respuesta recibida. Longitud: {len(result_content)}")
                
                try:
                    # 1. Limpieza básica
                    cleaned_content = clean_json_response(result_content)
                    
                    # 2. Intento de parseo directo
                    try:
                        json_response = json.loads(cleaned_content)
                    except json.JSONDecodeError:
                        # 3. Si falla, intentar reparar (balancear llaves)
                        logger.warning("JSON inválido, intentando reparar...")
                        repaired_content = balance_json(cleaned_content)
                        json_response = json.loads(repaired_content)

                    page_text = json_response.get('texto_completo', '')
                    page_data = json_response.get('datos_estructurados', {})
                    
                    full_text += page_text + "\n\n--- SIGUIENTE PAGINA ---\n\n"
                    
                    if not structured_data:
                        structured_data = page_data
                    elif 'items' in page_data:
                        if 'items' not in structured_data:
                            structured_data['items'] = []
                        structured_data['items'].extend(page_data['items'])
                        
                except Exception as e:
                    logger.error(f"Error fatal parseando JSON: {str(e)}")
                    # Fallback: Usar el texto crudo si falla todo
                    full_text += result_content
            
            processing_time = int((time.time() - start_time) * 1000)
            
            return jsonify({
                'success': True,
                'texto': full_text,
                'datos_estructurados': structured_data,
                'confianza': 100,
                'metodo': 'gpt-4o',
                'tiempo_procesamiento_ms': processing_time
            }), 200
            
    except Exception as e:
        logger.error(f"Error general: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'status': 'ok',
        'service': 'OCR Service (Vision Multi)',
        'api_key_configured': bool(openai.api_key)
    }), 200

if __name__ == '__main__':
    logger.info("Iniciando servicio OCR Vision Multi en puerto 5000")
    app.run(host='0.0.0.0', port=5000)
