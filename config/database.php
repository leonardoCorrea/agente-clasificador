<?php
/**
 * Configuración de Base de Datos
 * Plataforma de Clasificación de Facturas Aduaneras
 * 
 * Este archivo maneja la detección automática de entorno y configura
 * las credenciales de base de datos apropiadas según el entorno detectado.
 */

// ============================================================================
// FUNCIÓN PARA CARGAR VARIABLES DE ENTORNO DESDE .ENV
// ============================================================================

/**
 * Carga variables de entorno desde archivo .env
 * @param string $path Ruta al archivo .env
 * @return bool True si se cargó correctamente, false en caso contrario
 */
function loadEnv($path)
{
    if (!file_exists($path)) {
        error_log("Advertencia: Archivo .env no encontrado en: $path");
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios y líneas vacías
        if (strpos(trim($line), '#') === 0 || trim($line) === '') {
            continue;
        }

        // Parsear línea KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // No sobrescribir variables ya definidas en el entorno
            if (!array_key_exists($key, $_ENV) && !getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    return true;
}

// Cargar variables de entorno
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

// ============================================================================
// DETECCIÓN AUTOMÁTICA DE ENTORNO
// ============================================================================

/**
 * Detecta si la aplicación está corriendo en localhost o en producción
 * @return bool True si es producción, False si es desarrollo (localhost)
 */
function isProduction()
{
    // Primero verificar por HTTP_HOST (más confiable)
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        // Es localhost si contiene 'localhost' o '127.0.0.1' o empieza con '192.168.'
        $isLocalhost = (
            strpos($host, 'localhost') !== false ||
            strpos($host, '127.0.0.1') !== false ||
            strpos($host, '192.168.') === 0
        );

        // Si NO es localhost, es producción
        if (!$isLocalhost) {
            return true;
        }
    }

    // Si es localhost o no hay HTTP_HOST, verificar variable de entorno APP_ENV
    $appEnv = getenv('APP_ENV');
    if ($appEnv === 'production') {
        return true;
    }

    // Por defecto, asumir desarrollo
    return false;
}

// Detectar entorno
$produccion = isProduction();

// ============================================================================
// CONFIGURACIÓN DE BASE DE DATOS SEGÚN ENTORNO
// ============================================================================

if ($produccion) {
    // CONFIGURACIÓN DE PRODUCCIÓN
    $servername = getenv('DB_PROD_HOST') ?: 'localhost';
    $username = getenv('DB_PROD_USER') ?: 'lcorrea_adminFM';
    $password = getenv('DB_PROD_PASS') ?: '';
    $dbname = getenv('DB_PROD_NAME') ?: 'lcorrea_facturacion_aduanera';
    $serverUploadPath = getenv('UPLOAD_PATH_PROD') ?: '/home/lcorrea/ticosoftcr.com/agenteAduana/uploads/';
} else {
    // CONFIGURACIÓN DE DESARROLLO
    $servername = getenv('DB_DEV_HOST') ?: '192.168.100.103';
    $username = getenv('DB_DEV_USER') ?: 'admin';
    $password = getenv('DB_DEV_PASS') ?: '123456';
    $dbname = getenv('DB_DEV_NAME') ?: 'agenteBD';
    $serverUploadPath = getenv('UPLOAD_PATH_DEV') ?: 'C:\\WEBSERVER\\htdocs\\agenteClasificador\\uploads\\';
}

// ============================================================================
// VALIDACIÓN DE CONFIGURACIÓN
// ============================================================================

/**
 * Valida que las variables de configuración críticas estén definidas
 * @throws Exception Si falta alguna configuración crítica
 */
function validateDatabaseConfig()
{
    global $servername, $username, $dbname;

    $errors = [];

    if (empty($servername)) {
        $errors[] = "DB_HOST no está configurado";
    }
    if (empty($username)) {
        $errors[] = "DB_USER no está configurado";
    }
    if (empty($dbname)) {
        $errors[] = "DB_NAME no está configurado";
    }

    if (!empty($errors)) {
        $errorMsg = "Error de configuración de base de datos:\n" . implode("\n", $errors);
        error_log($errorMsg);
        throw new Exception("Error de configuración. Por favor, verifique el archivo .env");
    }
}

// Validar configuración
try {
    validateDatabaseConfig();
} catch (Exception $e) {
    // En desarrollo, mostrar el error
    if (!$produccion) {
        die($e->getMessage());
    }
    // En producción, solo registrar en log
    error_log($e->getMessage());
    die("Error de configuración del sistema. Contacte al administrador.");
}

// ============================================================================
// DEFINIR CONSTANTES DE BASE DE DATOS
// ============================================================================

define('DB_HOST', $servername);
define('DB_NAME', $dbname);
define('DB_USER', $username);
define('DB_PASS', $password);
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// DEFINIR CONSTANTES DE ENTORNO
// ============================================================================

define('IS_PRODUCTION', $produccion);
define('IS_DEVELOPMENT', !$produccion);
define('UPLOAD_PATH', $serverUploadPath);

// ============================================================================
// LOGGING DE CONFIGURACIÓN (SOLO EN DESARROLLO)
// ============================================================================

if (IS_DEVELOPMENT) {
    error_log("=== Configuración de Base de Datos ===");
    error_log("Entorno: " . (IS_PRODUCTION ? "PRODUCCIÓN" : "DESARROLLO"));
    error_log("Host: " . DB_HOST);
    error_log("Base de datos: " . DB_NAME);
    error_log("Usuario: " . DB_USER);
    error_log("Ruta de uploads: " . UPLOAD_PATH);
    error_log("=====================================");
}

// ============================================================================
// FUNCIONES HELPER DE CONFIGURACIÓN
// ============================================================================

/**
 * Obtiene una variable de entorno con valor por defecto
 * @param string $key Nombre de la variable
 * @param mixed $default Valor por defecto si no existe
 * @return mixed Valor de la variable o el valor por defecto
 */
function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

/**
 * Verifica si estamos en modo debug
 * @return bool
 */
function isDebugMode()
{
    $debug = env('APP_DEBUG', 'false');
    return in_array(strtolower($debug), ['true', '1', 'yes']);
}
