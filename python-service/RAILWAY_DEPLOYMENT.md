# Deployment en Railway - Guía Paso a Paso

## Requisitos Previos

- Cuenta en GitHub
- Cuenta en Railway (gratis): https://railway.app
- Código del microservicio en un repositorio Git

---

## Paso 1: Preparar el Repositorio

### 1.1 Inicializar Git (si no lo has hecho)

```bash
cd c:\WEBSERVER\htdocs\agenteClasificador
git init
git add .
git commit -m "Initial commit with OCR microservice"
```

### 1.2 Crear Repositorio en GitHub

1. Ve a https://github.com/new
2. Nombre del repositorio: `agente-clasificador` (o el que prefieras)
3. **NO** inicialices con README, .gitignore ni licencia
4. Click en "Create repository"

### 1.3 Conectar y Subir

```bash
git remote add origin https://github.com/leonardoCorrea/agente-clasificador.git
git branch -M main
git push -u origin main
```

---

## Paso 2: Crear Proyecto en Railway

### 2.1 Registrarse en Railway

1. Ve a https://railway.app
2. Click en "Start a New Project"
3. Inicia sesión con GitHub (recomendado)

### 2.2 Crear Nuevo Proyecto

1. Click en "New Project"
2. Selecciona "Deploy from GitHub repo"
3. Autoriza Railway para acceder a tus repositorios
4. Selecciona el repositorio `agente-clasificador`

### 2.3 Configurar el Servicio

Railway detectará automáticamente el `Dockerfile` en `python-service/`

1. Railway preguntará por el directorio raíz
2. **IMPORTANTE**: Cambia el directorio raíz a `python-service`
   - Click en "Settings" → "Service Settings"
   - En "Root Directory" escribe: `python-service`
   - Click en "Save"

---

## Paso 3: Configurar Variables de Entorno

### 3.1 Agregar Variables

1. En tu proyecto de Railway, click en el servicio
2. Ve a la pestaña "Variables"
3. Click en "New Variable"
4. Agrega las siguientes variables:

```
ALLOWED_ORIGINS=https://ticosoftcr.com,https://www.ticosoftcr.com,http://localhost
MAX_FILE_SIZE=10485760
```

**Nota**: Puedes agregar más orígenes según necesites

### 3.2 Guardar Cambios

Railway redesplegará automáticamente con las nuevas variables

---

## Paso 4: Obtener la URL del Servicio

### 4.1 Generar Dominio Público

1. En tu servicio, ve a "Settings"
2. Busca la sección "Networking"
3. Click en "Generate Domain"
4. Railway te dará una URL como: `https://your-service.up.railway.app`

### 4.2 Copiar la URL

Copia esta URL, la necesitarás para configurar tu aplicación PHP

---

## Paso 5: Actualizar Configuración PHP

### 5.1 Actualizar `.env` en Producción

En tu servidor de producción, edita el archivo `.env`:

```bash
# Microservicio OCR - Producción
OCR_SERVICE_URL_PROD=https://your-service.up.railway.app
```

Reemplaza `your-service.up.railway.app` con tu URL real de Railway

### 5.2 Verificar Configuración Local

En tu `.env` local (desarrollo):

```bash
# Microservicio OCR - Desarrollo
OCR_SERVICE_URL_DEV=http://localhost:8000
```

---

## Paso 6: Probar el Microservicio

### 6.1 Health Check

Abre en tu navegador:
```
https://your-service.up.railway.app/health
```

Deberías ver:
```json
{
  "status": "healthy",
  "service": "OCR Microservice",
  "version": "1.0.0"
}
```

### 6.2 Documentación API

Abre:
```
https://your-service.up.railway.app/docs
```

Verás la documentación interactiva de FastAPI (Swagger UI)

---

## Paso 7: Probar desde PHP

### 7.1 En Desarrollo (localhost)

1. Inicia el microservicio localmente:
   ```bash
   cd python-service
   pip install -r requirements.txt
   uvicorn app.main:app --reload
   ```

2. Prueba subir una factura desde tu aplicación PHP local

### 7.2 En Producción

1. Sube los archivos PHP actualizados a tu servidor
2. Asegúrate que `.env` tiene la URL correcta de Railway
3. Prueba subir una factura desde producción

---

## Troubleshooting

### El servicio no inicia

**Problema**: Railway muestra error al desplegar

**Solución**:
1. Verifica que el `Dockerfile` esté en `python-service/`
2. Verifica que el "Root Directory" esté configurado como `python-service`
3. Revisa los logs en Railway → "Deployments" → Click en el deployment → "View Logs"

### Error 413 (Payload Too Large)

**Problema**: Archivos muy grandes

**Solución**:
1. Aumenta `MAX_FILE_SIZE` en las variables de Railway
2. Verifica el límite de Railway (generalmente 100MB)

### Error de CORS

**Problema**: "Access-Control-Allow-Origin" error

**Solución**:
1. Agrega tu dominio a `ALLOWED_ORIGINS` en Railway
2. Formato: `https://tudominio.com,https://www.tudominio.com`
3. Separa múltiples dominios con comas (sin espacios)

### Timeout en requests

**Problema**: La petición tarda mucho y falla

**Solución**:
1. Aumenta el timeout en PHP (ya está en 300 segundos)
2. Railway tiene un límite de 100 segundos para el plan gratuito
3. Considera actualizar a plan Pro si necesitas más tiempo

---

## Monitoreo

### Ver Logs en Tiempo Real

1. En Railway, ve a tu servicio
2. Click en "Deployments"
3. Click en el deployment activo
4. Click en "View Logs"

### Métricas

Railway muestra automáticamente:
- CPU usage
- Memory usage
- Network traffic
- Request count

---

## Costos

### Plan Gratuito

- **Horas**: 500 horas/mes (suficiente para desarrollo)
- **Memoria**: 512MB RAM
- **Almacenamiento**: 1GB
- **Ancho de banda**: 100GB/mes

### Plan Pro (si lo necesitas)

- **Costo**: ~$5/mes por servicio
- **Horas**: Ilimitadas
- **Memoria**: Hasta 8GB RAM
- **Sin límite de tiempo de ejecución**

---

## Comandos Útiles

### Ver logs localmente

```bash
cd python-service
uvicorn app.main:app --log-level debug
```

### Probar endpoint localmente

```bash
curl http://localhost:8000/health
```

### Probar endpoint en Railway

```bash
curl https://your-service.up.railway.app/health
```

---

## Próximos Pasos

1. ✅ Desplegar microservicio en Railway
2. ✅ Obtener URL del servicio
3. ✅ Actualizar `.env` en producción
4. ✅ Probar OCR desde producción
5. 📊 Monitorear uso y performance
6. 🔄 Configurar CI/CD para deploys automáticos (opcional)

---

## Soporte

- **Railway Docs**: https://docs.railway.app
- **Railway Discord**: https://discord.gg/railway
- **FastAPI Docs**: https://fastapi.tiangolo.com

---

## Notas Importantes

⚠️ **Seguridad**:
- NO subas tu `.env` con credenciales al repositorio
- Usa variables de entorno en Railway para datos sensibles
- El `OPENAI_API_KEY` se envía desde PHP, no se almacena en Railway

✅ **Best Practices**:
- Mantén el microservicio actualizado
- Monitorea los logs regularmente
- Configura alertas en Railway para errores
- Haz backups de tu configuración

🚀 **Performance**:
- Railway usa contenedores Docker optimizados
- El servicio se "duerme" después de 15 min de inactividad (plan gratuito)
- Primera request después de dormir puede tardar ~10 segundos
- Considera plan Pro para producción con tráfico constante
