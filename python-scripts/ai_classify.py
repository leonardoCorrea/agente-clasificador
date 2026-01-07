# Script Python para Clasificación IA con Vision Multi
# Ejecutado directamente desde PHP, NO usa Flask
# Recibe: JSON con descripciones como argumento
# Retorna: JSON a stdout

import sys
import json
from openai import OpenAI

# Forzar salida en UTF-8 para evitar problemas de codificación en Windows
if sys.stdout.encoding != 'utf-8':
    try:
        import io
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    except:
        pass

CLASSIFICATION_PROMPT = """Eres un Agente Aduanal EXPERTO de Costa Rica, con profundo conocimiento en el Sistema Arancelario Centroamericano (SAC) y el TICA.

Tu misión es clasificar mercancías con precisión quirúrgica, evitando confusiones comunes entre sectores (ej: no confundir autopartes con electrónica).

REGLAS CRÍTICAS DE CLASIFICACIÓN:
1. **CONTEXTO DE FACTURA:** Utiliza la información del proveedor, contacto y empresa para determinar el SECTOR de la mercancía.
   - Si el proveedor es de repuestos (ej: "Gonher", "Autopartes"), prioriza el Capítulo 87 (Vehículos y sus partes).
   - Si hay referencias a sitios web o emails de industria automotriz, ÚSALOS como evidencia de sector.
2. **CÓDIGOS Y PART NUMBERS:** Muchos productos tienen códigos OEM o Part Numbers (ej: "G1093", "12636838"). Estos códigos son fundamentales para identificar repuestos.
3. **FORMATO SAC:** El código DEBE tener 10 dígitos (ej: 8471.30.00.00). Si el código es de 8 dígitos, añade ".00" al final.
4. **REGLAS DE INTERPRETACIÓN:** Aplica las Reglas Generales de Interpretación del SA.
5. **JUSTIFICACIÓN:** En 'explicacion', cita partidas, subpartidas y por qué la información del proveedor/contexto confirma esta clasificación.

Responde ÚNICAMENTE en formato JSON:
{
    "codigo_arancelario": "1234.56.78.90",
    "descripcion_codigo": "Descripción oficial del SAC de Costa Rica",
    "explicacion": "Justificación técnica detallada citando reglas y contexto del proveedor",
    "confianza": 95.0,
    "alternativas": [
        {
            "codigo_arancelario": "1234.56.00.00",
            "descripcion_codigo": "...",
            "explicacion": "..."
        }
    ]
}"""

def classify_item(descripcion, api_key, context=None):
    """Clasificar un ítem usando Vision Multi con contexto opcional"""
    try:
        client = OpenAI(api_key=api_key)
        
        user_msg = f"Clasificar producto: {descripcion}"
        if context:
            user_msg += f"\n\nCONTEXTO DE FACTURA (Proveedor/Importador):\n{json.dumps(context, ensure_ascii=False)}"
        
        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": CLASSIFICATION_PROMPT},
                {"role": "user", "content": user_msg}
            ],
            response_format={"type": "json_object"},
            temperature=0.2
        )
        
        result = json.loads(response.choices[0].message.content)
        result['modelo'] = 'gpt-4o'
        return result
        
    except Exception as e:
        return {
            'codigo_arancelario': '0000.00.00',
            'descripcion_codigo': 'Error en clasificación',
            'explicacion': str(e),
            'confianza': 0,
            'modelo': 'error'
        }

if __name__ == '__main__':
    # Argumentos: python ai_classify.py <json_descripciones> <api_key>
    if len(sys.argv) < 3:
        print(json.dumps({'success': False, 'error': 'Faltan argumentos'}))
        sys.exit(1)
    
    try:
        descripciones_json = sys.argv[1]
        api_key = sys.argv[2]
        context = None
        
        if len(sys.argv) > 3:
            try:
                context = json.loads(sys.argv[3])
            except:
                context = None
        
        descripciones = json.loads(descripciones_json)
        
        if not isinstance(descripciones, list):
            descripciones = [descripciones]
        
        clasificaciones = []
        for desc in descripciones:
            result = classify_item(desc, api_key, context)
            clasificaciones.append(result)
        
        print(json.dumps({
            'success': True,
            'clasificaciones': clasificaciones
        }, ensure_ascii=False))
        
    except Exception as e:
        print(json.dumps({'success': False, 'error': str(e)}))
