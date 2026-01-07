# OCR Microservice

Microservicio Python con FastAPI para procesamiento OCR de facturas aduaneras.

## Estructura

```
python-service/
├── app/
│   ├── __init__.py
│   ├── main.py              # FastAPI app principal
│   ├── config.py            # Configuración
│   ├── routers/
│   │   ├── __init__.py
│   │   └── ocr.py           # Endpoints de OCR
│   └── services/
│       ├── __init__.py
│       └── ocr_processor.py # Lógica de OCR
├── requirements.txt
├── Dockerfile
├── .env.example
├── .gitignore
└── README.md
```

## Desarrollo Local

### 1. Instalar dependencias

```bash
cd python-service
pip install -r requirements.txt
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
# Editar .env con tus valores
```

### 3. Ejecutar servidor

```bash
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

El servidor estará disponible en `http://localhost:8000`

## Endpoints

### Health Check
```
GET /health
```

### Procesar OCR
```
POST /api/ocr/process
Content-Type: multipart/form-data

Parámetros:
- file: archivo PDF o imagen
- api_key: OpenAI API key
- context: (opcional) JSON con contexto para corroboración
```

## Deployment en Railway

Ver instrucciones en el archivo `RAILWAY_DEPLOYMENT.md`

## Variables de Entorno

- `ALLOWED_ORIGINS`: Orígenes permitidos para CORS (separados por coma)
- `MAX_FILE_SIZE`: Tamaño máximo de archivo en bytes (default: 10485760)

## Tecnologías

- FastAPI
- OpenAI GPT-4 Vision
- PyMuPDF (para PDFs)
- Pillow (para imágenes)
