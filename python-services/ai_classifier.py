# Microservicio de Clasificación IA - Flask
# Clasificación de códigos arancelarios usando OpenAI GPT-4o

from flask import Flask, request, jsonify
from flask_cors import CORS
import openai
import os
import logging
import time
import json

# Configuración de logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)

# Configurar API key de OpenAI
openai.api_key = os.getenv('OPENAI_API_KEY', '')

# Prompt base para clasificación arancelaria
CLASSIFICATION_PROMPT = """Eres un EXPERTO clasificador arancelario aduanero con 20 años de experiencia.
Tu tarea es analizar descripciones de productos y asignar el código arancelario (HS Code) más preciso.

Para cada descripción, devuelve un objeto JSON con:
1. "codigo_arancelario": Ddígitos (ej: "8471.30.00")
2. "descripcion_codigo": Texto oficial del arancel
3. "explicacion": Justificación técnica de por qué aplica esta partida, mencionando Reglas Generales de Interpretación si aplica.
4. "confianza": Valor numérico 0-100
5. "alternativas": Lista de posibles códigos alternativos si hay ambigüedad

Responde SOLAMENTE en formato JSON válido.
"""

@app.route('/classify', methods=['POST'])
def classify_items():
    start_time = time.time()
    
    try:
        data = request.get_json()
        if not data or 'descripciones' not in data:
            return jsonify({'success': False, 'error': 'Faltan descripciones'}), 400
        
        descripciones = data['descripciones']
        
        if not openai.api_key:
            return jsonify({'success': False, 'error': 'API Key no configurada'}), 500
        
        clasificaciones = []
        
        # Procesar en lote para ahorrar llamadas si son muchas, o una por una para precisión
        # Vision Multi es rápido, haremos una por una para máxima calidad de contexto
        
        for desc in descripciones:
            response = openai.ChatCompletion.create(
                model="gpt-4o",
                messages=[
                    {"role": "system", "content": CLASSIFICATION_PROMPT},
                    {"role": "user", "content": f"Clasificar producto: {desc}"}
                ],
                response_format={"type": "json_object"},
                temperature=0.2
            )
            
            content = response.choices[0].message.content
            item_result = json.loads(content)
            item_result['modelo'] = 'gpt-4o'
            clasificaciones.append(item_result)
            
        processing_time = int((time.time() - start_time) * 1000)
        
        return jsonify({
            'success': True,
            'clasificaciones': clasificaciones,
            'tiempo_procesamiento_ms': processing_time
        }), 200
        
    except Exception as e:
        logger.error(f"Error en clasificación: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'status': 'ok',
        'service': 'AI Classifier (GPT-4o)',
        'api_key_configured': bool(openai.api_key)
    }), 200

if __name__ == '__main__':
    logger.info("Iniciando servicio Clasificación IA en puerto 5001")
    app.run(host='0.0.0.0', port=5001)
