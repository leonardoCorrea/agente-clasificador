from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from app.services.ocr_processor import process_ocr
from app.config import settings
import tempfile
import os
import json

router = APIRouter()


@router.post("/process")
async def process_invoice_ocr(
    file: UploadFile = File(..., description="Archivo PDF o imagen de la factura"),
    api_key: str = Form(..., description="OpenAI API Key"),
    context: str = Form(None, description="Contexto JSON opcional para corroboración")
):
    """
    Procesar factura con OCR usando OpenAI Vision
    
    Args:
        file: Archivo PDF o imagen (JPG, PNG)
        api_key: API Key de OpenAI
        context: JSON opcional con datos para corroborar
    
    Returns:
        JSON con resultados del OCR
    """
    temp_path = None
    
    try:
        # Validar tamaño de archivo
        file_content = await file.read()
        file_size = len(file_content)
        
        if file_size > settings.MAX_FILE_SIZE:
            raise HTTPException(
                status_code=413,
                detail=f"Archivo demasiado grande. Máximo permitido: {settings.MAX_FILE_SIZE / 1024 / 1024}MB"
            )
        
        # Validar extensión
        file_ext = os.path.splitext(file.filename)[1].lower()
        allowed_extensions = ['.pdf', '.jpg', '.jpeg', '.png']
        
        if file_ext not in allowed_extensions:
            raise HTTPException(
                status_code=400,
                detail=f"Tipo de archivo no permitido. Extensiones permitidas: {', '.join(allowed_extensions)}"
            )
        
        # Guardar archivo temporal
        with tempfile.NamedTemporaryFile(delete=False, suffix=file_ext) as tmp:
            tmp.write(file_content)
            temp_path = tmp.name
        
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
        
        # Procesar OCR
        result = process_ocr(temp_path, api_key, context_data)
        
        return result
        
    except HTTPException:
        # Re-lanzar excepciones HTTP
        raise
        
    except Exception as e:
        # Capturar cualquier otro error
        raise HTTPException(
            status_code=500,
            detail=f"Error procesando OCR: {str(e)}"
        )
        
    finally:
        # Limpiar archivo temporal
        if temp_path and os.path.exists(temp_path):
            try:
                os.unlink(temp_path)
            except:
                pass


@router.get("/status")
async def get_status():
    """Obtener estado del servicio OCR"""
    return {
        "status": "operational",
        "max_file_size_mb": settings.MAX_FILE_SIZE / 1024 / 1024,
        "supported_formats": ["PDF", "JPG", "JPEG", "PNG"]
    }
