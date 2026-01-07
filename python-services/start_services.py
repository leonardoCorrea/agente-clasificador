# Script de inicio para microservicios Python
# Ejecutar este archivo para iniciar ambos servicios

import os
import sys
import subprocess
import time

# Configurar la clave API de OpenAI desde el archivo .env
OPENAI_API_KEY = "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"

# Establecer variable de entorno
os.environ['OPENAI_API_KEY'] = OPENAI_API_KEY

print("=" * 60)
print("INICIANDO MICROSERVICIOS DE CLASIFICACIÓN DE FACTURAS")
print("=" * 60)
print()
print("✓ Clave API de OpenAI configurada")
print()
print("Iniciando servicios:")
print("  - Servicio OCR en puerto 5000")
print("  - Servicio IA en puerto 5001")
print()
print("Presione Ctrl+C para detener los servicios")
print("=" * 60)
print()

try:
    # Iniciar servicio OCR
    ocr_process = subprocess.Popen(
        [sys.executable, 'ocr_service.py'],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        bufsize=1
    )
    
    time.sleep(2)
    
    # Iniciar servicio IA
    ai_process = subprocess.Popen(
        [sys.executable, 'ai_classifier.py'],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        bufsize=1
    )
    
    print("✓ Servicios iniciados correctamente")
    print()
    print("Logs de servicios:")
    print("-" * 60)
    
    # Mantener los procesos corriendo y mostrar logs
    while True:
        time.sleep(1)
        
except KeyboardInterrupt:
    print("\n\nDeteniendo servicios...")
    ocr_process.terminate()
    ai_process.terminate()
    print("✓ Servicios detenidos")
    
except Exception as e:
    print(f"\n❌ Error: {str(e)}")
    sys.exit(1)
