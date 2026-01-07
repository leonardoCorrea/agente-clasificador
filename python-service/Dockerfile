FROM python:3.11-slim

WORKDIR /app

# Instalar dependencias del sistema necesarias para PyMuPDF y Pillow
RUN apt-get update && apt-get install -y \
    libglib2.0-0 \
    libgomp1 \
    && rm -rf /var/lib/apt/lists/*

# Copiar requirements e instalar dependencias Python
# Nota: El contexto de build es la raíz del repo, así que copiamos desde python-service/
COPY python-service/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copiar código de la aplicación
COPY python-service/app/ ./app/

# Exponer puerto
EXPOSE 8000

# Variables de entorno por defecto
ENV ALLOWED_ORIGINS="*"
ENV MAX_FILE_SIZE=10485760

# Comando para ejecutar la aplicación
# Railway mapeará automáticamente el puerto 8000 al puerto público
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
