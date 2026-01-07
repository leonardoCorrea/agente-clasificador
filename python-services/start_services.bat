@echo off
REM Script de inicio para Windows
REM Inicia los microservicios Python con la API key configurada

echo ============================================================
echo INICIANDO MICROSERVICIOS DE CLASIFICACION DE FACTURAS
echo ============================================================
echo.

REM Configurar la API key de OpenAI
set OPENAI_API_KEY=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

echo [OK] Clave API de OpenAI configurada
echo.
echo Iniciando servicios en segundo plano...
echo   - Servicio OCR en puerto 5000
echo   - Servicio IA en puerto 5001
echo.
echo Presione Ctrl+C para detener los servicios
echo ============================================================
echo.

REM Iniciar servicio OCR en segundo plano
start "Servicio OCR" cmd /k "python ocr_service.py"

REM Esperar 2 segundos
timeout /t 2 /nobreak >nul

REM Iniciar servicio IA en segundo plano
start "Servicio IA" cmd /k "python ai_classifier.py"

echo.
echo [OK] Servicios iniciados correctamente
echo.
echo Para detener los servicios, cierre las ventanas de comandos
echo o presione Ctrl+C en cada una.
echo.
pause
