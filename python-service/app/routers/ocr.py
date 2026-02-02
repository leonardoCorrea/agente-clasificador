from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from app.services.ocr_processor import process_ocr, BUILD_ID
from app.config import settings
import tempfile
import os
import json

router = APIRouter()


@router.post("/process")
async def process_invoice_ocr(
    file: UploadFile = File(None, description="Archivo PDF o imagen de la factura"),
    api_key: str = Form(..., description="OpenAI API Key"),
    context: str = Form(None, description="Contexto JSON opcional para corroboración"),
    page_number: int = Form(None, description="Número de página específico a procesar (1-indexed)"),
    session_id: str = Form(None, description="ID de sesión para usar un archivo ya subido")
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
    context_data = None
    
    try:
        # Parsear contexto si existe
        if context:
            try:
                context_data = json.loads(context)
            except json.JSONDecodeError:
                raise HTTPException(
                    status_code=400,
                    detail="El contexto proporcionado no es un JSON válido"
                )

        # Validar tamaño y extensión solo si se sube un archivo nuevo
        if file:
            file_content = await file.read()
            file_size = len(file_content)
            
            if file_size > settings.MAX_FILE_SIZE:
                raise HTTPException(
                    status_code=413,
                    detail=f"Archivo demasiado grande. Máximo permitido: {settings.MAX_FILE_SIZE / 1024 / 1024}MB"
                )
            
            file_ext = os.path.splitext(file.filename)[1].lower()
            allowed_extensions = {'.pdf', '.jpg', '.jpeg', '.png'}
            if file_ext not in allowed_extensions:
                raise HTTPException(
                    status_code=400,
                    detail=f"Tipo de archivo no permitido. Extensiones permitidas: {', '.join(allowed_extensions)}"
                )
        
        # Si hay session_id, buscar el archivo en /tmp
        if session_id:
            safe_session_id = "".join(c for c in session_id if c.isalnum() or c in "-_")
            session_dir = os.path.join(tempfile.gettempdir(), f"ocr_session_{safe_session_id}")
            # Buscar cualquier archivo en ese directorio
            if os.path.exists(session_dir):
                files = os.listdir(session_dir)
                if files:
                    temp_path = os.path.join(session_dir, files[0])
                    print(f"DEBUG: Usando archivo de sesión: {temp_path}")
            
            if not temp_path:
                raise HTTPException(status_code=404, detail="Sesión no encontrada o expirada")
        elif file:
            # Comportamiento anterior: Guardar archivo temporal de la subida actual
            file_content = await file.read()
            file_ext = os.path.splitext(file.filename)[1].lower()
            with tempfile.NamedTemporaryFile(delete=False, suffix=file_ext) as tmp:
                tmp.write(file_content)
                temp_path = tmp.name
        else:
            raise HTTPException(status_code=400, detail="Debe proporcionar un archivo o un session_id")
        
        # Procesar OCR
        result = process_ocr(temp_path, api_key, context_data, page_number)
        
        # Si NO es sesión, limpiar el temporal (si es sesión, se queda para la siguiente página)
        if not session_id and temp_path and os.path.exists(temp_path):
            try:
                os.unlink(temp_path)
            except:
                pass
            
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


@router.post("/prepare")
async def prepare_ocr_session(
    file: UploadFile = File(..., description="Archivo PDF para iniciar sesión")
):
    """Subir archivo y preparar sesión para procesamiento por páginas"""
    session_dir = None
    try:
        import uuid
        session_id = str(uuid.uuid4())
        safe_session_id = "".join(c for c in session_id if c.isalnum() or c in "-_")
        session_dir = os.path.join(tempfile.gettempdir(), f"ocr_session_{safe_session_id}")
        os.makedirs(session_dir, exist_ok=True)
        
        file_ext = os.path.splitext(file.filename)[1].lower()
        temp_path = os.path.join(session_dir, f"invoice{file_ext}")
        
        file_content = await file.read()
        with open(temp_path, "wb") as f:
            f.write(file_content)
            
        pages = 1
        if file_ext == '.pdf':
            import fitz
            doc = fitz.open(temp_path)
            pages = len(doc)
            doc.close()
            
        return {
            "success": True, 
            "session_id": session_id, 
            "pages": pages, 
            "format": file_ext
        }
    except Exception as e:
        if session_dir and os.path.exists(session_dir):
            import shutil
            shutil.rmtree(session_dir)
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/info")
async def get_pdf_info(
    file: UploadFile = File(..., description="Archivo PDF")
):
    """Obtener información básica del PDF (número de páginas)"""
    temp_path = None
    try:
        file_content = await file.read()
        file_ext = os.path.splitext(file.filename)[1].lower()
        
        if file_ext != '.pdf':
            return {"success": True, "pages": 1, "format": file_ext}
            
        with tempfile.NamedTemporaryFile(delete=False, suffix='.pdf') as tmp:
            tmp.write(file_content)
            temp_path = tmp.name
            
        import fitz
        doc = fitz.open(temp_path)
        pages = len(doc)
        doc.close()
        
        return {"success": True, "pages": pages, "format": "pdf"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if temp_path and os.path.exists(temp_path):
            os.unlink(temp_path)

@router.get("/status")
async def get_status():
    """Obtener estado del servicio OCR"""
    return {
        "status": "operational",
        "build_id": BUILD_ID,
        "max_file_size_mb": settings.MAX_FILE_SIZE / 1024 / 1024,
        "supported_formats": ["PDF", "JPG", "JPEG", "PNG"]
    }
