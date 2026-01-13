from fastapi import APIRouter, Form, HTTPException
from app.services.ai_classifier import process_classification
import json

router = APIRouter()

@router.post("/process")
async def classify_items(
    descripciones: str = Form(..., description="JSON con lista de descripciones de productos"),
    api_key: str = Form(..., description="OpenAI API Key"),
    context: str = Form(None, description="JSON opcional con contexto de la factura")
):
    """
    Clasificar productos con IA usando OpenAI Vision via Railway
    
    Args:
        descripciones: JSON con lista de strings (descripciones)
        api_key: API Key de OpenAI
        context: JSON opcional con datos del proveedor/consignatario
    
    Returns:
        JSON con sugerencias de clasificación
    """
    try:
        # Parsear descripciones
        try:
            desc_list = json.loads(descripciones)
        except json.JSONDecodeError:
            raise HTTPException(
                status_code=400,
                detail="El campo 'descripciones' no es un JSON válido"
            )
        
        # Parsear contexto si existe
        context_data = None
        if context:
            try:
                context_data = json.loads(context)
            except json.JSONDecodeError:
                raise HTTPException(
                    status_code=400,
                    detail="El contexto proporcionado no es un JSON válido"
                )
        
        # Procesar clasificación
        result = process_classification(desc_list, api_key, context_data)
        
        return result
        
    except HTTPException:
        raise
        
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"Error en clasificación Railway: {str(e)}"
        )
