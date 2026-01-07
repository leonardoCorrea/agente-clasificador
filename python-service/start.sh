#!/bin/sh
# Startup script para Railway
# Lee el puerto de la variable de entorno PORT

PORT=${PORT:-8000}
echo "Starting uvicorn on port $PORT"
exec uvicorn app.main:app --host 0.0.0.0 --port $PORT
