from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.config import settings
from app.routers import ocr

# Crear aplicación FastAPI
app = FastAPI(
    title=settings.APP_TITLE,
    description=settings.APP_DESCRIPTION,
    version=settings.APP_VERSION,
)

# Middleware de Logging para "HTTP Logs"
@app.middleware("http")
async def log_requests(request, call_next):
    import time
    start_time = time.time()
    response = await call_next(request)
    duration = time.time() - start_time
    
    # Imprimir en el formato solicitado para Railway
    print(f"HTTP Logs | {request.method} {request.url.path} | Status: {response.status_code} | Duration: {duration:.2f}s")
    
    return response

# Configurar CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Incluir routers
app.include_router(ocr.router, prefix="/api/ocr", tags=["OCR"])


from app.services.ocr_processor import BUILD_ID

@app.get("/")
async def root():
    """Endpoint raíz"""
    return {
        "service": settings.APP_TITLE,
        "version": settings.APP_VERSION,
        "build_id": BUILD_ID,
        "status": "running",
        "endpoints": {
            "health": "/health",
            "docs": "/docs",
            "ocr_process": "/api/ocr/process"
        }
    }


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": settings.APP_TITLE,
        "build_id": BUILD_ID,
        "version": settings.APP_VERSION
    }


# Startup event para logging
@app.on_event("startup")
async def startup_event():
    import os
    port = os.getenv("PORT", "8000")
    print(f"🚀 Application starting on port {port}")
    print(f"📝 Allowed origins: {settings.allowed_origins_list}")
