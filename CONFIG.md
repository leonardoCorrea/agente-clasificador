# Configuración del Sistema

## Descripción General

Este documento describe cómo funciona el sistema de configuración de la Plataforma de Clasificación de Facturas Aduaneras, incluyendo la detección automática de entorno y la configuración de base de datos.

## Detección Automática de Entorno

El sistema detecta automáticamente si está corriendo en **desarrollo** (localhost) o **producción** (servidor remoto) usando el siguiente orden de prioridad:

### 1. Detección por HTTP_HOST (Prioridad Alta)
El sistema primero verifica el dominio/host de la solicitud:
- **Desarrollo**: Si el host contiene `localhost`, `127.0.0.1`, o empieza con `192.168.`
- **Producción**: Cualquier otro host (ej: `ticosoftcr.com`, `example.com`, etc.)

### 2. Variable de Entorno `APP_ENV` (Prioridad Baja)
Solo se usa si el host es localhost o no está disponible:
```bash
# En .env
APP_ENV=development  # o 'production'
```

> **Nota Importante:** La detección por HTTP_HOST tiene prioridad sobre `APP_ENV`. Esto significa que si accedes desde `ticosoftcr.com`, **siempre** se detectará como producción, incluso si `APP_ENV=development` en el `.env`.

## Archivo .env

El archivo `.env` contiene todas las variables de configuración sensibles y específicas del entorno.

### Ubicación
```
c:\WEBSERVER\htdocs\agenteClasificador\.env
```

### Estructura

```bash
# ============================================================================
# ENTORNO
# ============================================================================
APP_ENV=development          # development o production
APP_DEBUG=true              # true o false

# ============================================================================
# BASE DE DATOS - DESARROLLO
# ============================================================================
DB_DEV_HOST=192.168.100.103
DB_DEV_NAME=agenteBD
DB_DEV_USER=admin
DB_DEV_PASS=123456

# ============================================================================
# BASE DE DATOS - PRODUCCIÓN
# ============================================================================
DB_PROD_HOST=localhost
DB_PROD_NAME=lcorrea_facturacion_aduanera
DB_PROD_USER=lcorrea_adminFM
DB_PROD_PASS=TuContraseñaSegura

# ============================================================================
# RUTAS DE ARCHIVOS
# ============================================================================
UPLOAD_PATH_DEV=C:\\WEBSERVER\\htdocs\\agenteClasificador\\uploads\\
UPLOAD_PATH_PROD=/home/lcorrea/ticosoftcr.com/agenteAduana/uploads/

# ... más configuraciones
```

### ⚠️ Seguridad Importante

1. **NUNCA** subir el archivo `.env` al repositorio git
2. Verificar que `.env` esté en `.gitignore`
3. Usar `.env.example` como plantilla para nuevos entornos
4. Cambiar las contraseñas de producción regularmente

## Variables de Configuración Disponibles

### Entorno
| Variable | Descripción | Valores | Por Defecto |
|----------|-------------|---------|-------------|
| `APP_ENV` | Entorno de ejecución | development, production | development |
| `APP_DEBUG` | Modo debug | true, false | false |

### Base de Datos - Desarrollo
| Variable | Descripción | Por Defecto |
|----------|-------------|-------------|
| `DB_DEV_HOST` | Host de BD | 192.168.100.103 |
| `DB_DEV_NAME` | Nombre de BD | agenteBD |
| `DB_DEV_USER` | Usuario de BD | admin |
| `DB_DEV_PASS` | Contraseña de BD | 123456 |

### Base de Datos - Producción
| Variable | Descripción | Por Defecto |
|----------|-------------|-------------|
| `DB_PROD_HOST` | Host de BD | localhost |
| `DB_PROD_NAME` | Nombre de BD | lcorrea_facturacion_aduanera |
| `DB_PROD_USER` | Usuario de BD | lcorrea_adminFM |
| `DB_PROD_PASS` | Contraseña de BD | (vacío) |

### Rutas
| Variable | Descripción |
|----------|-------------|
| `UPLOAD_PATH_DEV` | Ruta de uploads en desarrollo |
| `UPLOAD_PATH_PROD` | Ruta de uploads en producción |
| `MAX_FILE_SIZE` | Tamaño máximo de archivo en bytes (default: 10485760 = 10MB) |

### OpenAI
| Variable | Descripción |
|----------|-------------|
| `OPENAI_API_KEY` | API Key para clasificación con IA |

### Email (SMTP)
| Variable | Descripción | Por Defecto |
|----------|-------------|-------------|
| `SMTP_HOST` | Servidor SMTP | smtp.gmail.com |
| `SMTP_PORT` | Puerto SMTP | 587 |
| `SMTP_USER` | Usuario SMTP | (vacío) |
| `SMTP_PASS` | Contraseña SMTP | (vacío) |
| `SMTP_FROM` | Email remitente | noreply@facturacion.com |

### Seguridad
| Variable | Descripción | Por Defecto |
|----------|-------------|-------------|
| `SESSION_LIFETIME` | Tiempo de vida de sesión en segundos | 3600 (1 hora) |
| `PASSWORD_MIN_LENGTH` | Longitud mínima de contraseña | 8 |

## Archivos de Configuración

### 1. `config/database.php`
Maneja la detección de entorno y configuración de base de datos.

**Funciones principales:**
- `loadEnv($path)`: Carga variables desde archivo .env
- `isProduction()`: Detecta si es entorno de producción
- `validateDatabaseConfig()`: Valida configuración requerida
- `env($key, $default)`: Obtiene variable de entorno con valor por defecto
- `isDebugMode()`: Verifica si está en modo debug

**Constantes definidas:**
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- `IS_PRODUCTION`, `IS_DEVELOPMENT`
- `UPLOAD_PATH`

### 2. `config/config.php`
Configuración principal de la aplicación.

**Incluye:**
- Configuración de zona horaria
- Configuración de errores según entorno
- Configuración de sesión
- Constantes de la aplicación
- Configuración de Python y OpenAI
- Funciones helper (CSRF, sanitización, autenticación, etc.)

### 3. `classes/Database.php`
Clase singleton para manejo de conexión a base de datos.

**Métodos principales:**
- `getInstance()`: Obtiene instancia única
- `getConnection()`: Obtiene conexión PDO
- `isConnected()`: Verifica si la conexión está activa
- `reconnect()`: Reconecta a la base de datos
- `query()`, `queryOne()`, `execute()`: Métodos de consulta
- `insert()`, `update()`, `delete()`: Helpers para operaciones CRUD

## Cómo Usar la Configuración

### Obtener Variables de Entorno

```php
// Usando la función helper env()
$apiKey = env('OPENAI_API_KEY', 'default-value');
$maxSize = env('MAX_FILE_SIZE', 10485760);

// Usando constantes definidas
$dbHost = DB_HOST;
$uploadPath = UPLOAD_PATH;
$isProduction = IS_PRODUCTION;
```

### Verificar Entorno

```php
if (IS_DEVELOPMENT) {
    // Código solo para desarrollo
    error_log("Debug info...");
}

if (IS_PRODUCTION) {
    // Código solo para producción
    // Ocultar errores, etc.
}
```

### Usar la Base de Datos

```php
$db = Database::getInstance();

// SELECT
$users = $db->query("SELECT * FROM users WHERE active = ?", [1]);

// SELECT ONE
$user = $db->queryOne("SELECT * FROM users WHERE id = ?", [$userId]);

// INSERT
$userId = $db->insert('users', [
    'name' => 'Juan',
    'email' => 'juan@example.com'
]);

// UPDATE
$db->update('users', 
    ['name' => 'Juan Pérez'], 
    ['id' => $userId]
);

// DELETE
$db->delete('users', ['id' => $userId]);
```

## Configuración para Nuevo Entorno

1. **Copiar archivo de ejemplo:**
   ```bash
   copy .env.example .env
   ```

2. **Editar `.env` con tus credenciales:**
   - Configurar `APP_ENV` (development o production)
   - Configurar credenciales de base de datos
   - Configurar rutas de upload
   - Configurar API keys

3. **Verificar permisos:**
   - Asegurar que el directorio de uploads existe y tiene permisos de escritura
   - Asegurar que el directorio de logs existe y tiene permisos de escritura

4. **Probar la configuración:**
   - Navegar a la aplicación en el navegador
   - Verificar que no hay errores de conexión
   - Revisar los logs para confirmar la configuración

## Troubleshooting

### Error: "Archivo .env no encontrado"
**Solución:** Copiar `.env.example` a `.env` y configurar las variables.

### Error: "Error de configuración de base de datos"
**Solución:** Verificar que todas las variables de BD estén configuradas en `.env`:
- `DB_DEV_HOST`, `DB_DEV_NAME`, `DB_DEV_USER`, `DB_DEV_PASS` (para desarrollo)
- `DB_PROD_HOST`, `DB_PROD_NAME`, `DB_PROD_USER`, `DB_PROD_PASS` (para producción)

### Error: "Error de conexión a la base de datos"
**Soluciones:**
1. Verificar que el servidor de BD está corriendo
2. Verificar credenciales en `.env`
3. Verificar que el usuario tiene permisos en la base de datos
4. En desarrollo, revisar el mensaje de error detallado en pantalla
5. Revisar los logs de error de PHP

### La aplicación usa las credenciales incorrectas
**Solución:** Verificar la detección de entorno:
1. Revisar el valor de `APP_ENV` en `.env`
2. Verificar que el `HTTP_HOST` se detecta correctamente
3. Revisar los logs para ver qué entorno se detectó

### Cambios en .env no se reflejan
**Solución:** 
1. Reiniciar el servidor web (Apache/Nginx)
2. Limpiar caché de PHP si está habilitado
3. Verificar que no hay errores de sintaxis en `.env`

## Mejores Prácticas

1. **Nunca hardcodear credenciales** en el código
2. **Usar siempre `env()`** para obtener valores de configuración
3. **Mantener `.env.example` actualizado** con todas las variables necesarias
4. **Documentar nuevas variables** en este archivo
5. **Usar contraseñas seguras** en producción
6. **Rotar credenciales** regularmente
7. **Revisar logs** regularmente para detectar problemas
8. **Probar en desarrollo** antes de desplegar a producción
