<?php
/**
 * Archivo de Configuración Principal
 * Plataforma de Clasificación de Facturas Aduaneras
 */

// ============================================================================
// CARGAR CONFIGURACIÓN DE BASE DE DATOS Y ENTORNO
// ============================================================================
require_once __DIR__ . '/database.php';

// ============================================================================
// CONFIGURACIÓN DE ZONA HORARIA
// ============================================================================
date_default_timezone_set('America/Mexico_City');

// ============================================================================
// CONFIGURACIÓN DE ERRORES SEGÚN ENTORNO
// ============================================================================
if (IS_DEVELOPMENT) {
    // En desarrollo: mostrar todos los errores
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // En producción: ocultar errores del usuario, solo registrar en logs
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log');
}

// ============================================================================
// CONFIGURACIÓN DE SESIÓN
// ============================================================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', IS_PRODUCTION ? 1 : 0); // HTTPS solo en producción
session_start();

// ============================================================================
// CONSTANTES DE LA APLICACIÓN
// ============================================================================
define('APP_NAME', 'Plataforma de Clasificación de Facturas Aduaneras');
define('APP_VERSION', '1.0.0');

// ============================================================================
// CONFIGURACIÓN DE RUTAS
// ============================================================================
// BASE_PATH ya está definido en database.php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('MAX_FILE_SIZE', env('MAX_FILE_SIZE', 10485760)); // 10MB por defecto

// ============================================================================
// CONFIGURACIÓN DE MICROSERVICIO OCR
// ============================================================================
// URL del microservicio OCR (FastAPI)
if (IS_PRODUCTION) {
    define('OCR_SERVICE_URL', env('OCR_SERVICE_URL_PROD', 'https://your-service.railway.app'));
} else {
    define('OCR_SERVICE_URL', env('OCR_SERVICE_URL_DEV', 'http://localhost:8000'));
}

// Mantener por compatibilidad (ya no se usa directamente)
define('PYTHON_SCRIPTS_DIR', __DIR__ . '/../python-scripts');

// ============================================================================
// OPENAI API
// ============================================================================
define('OPENAI_API_KEY', env('OPENAI_API_KEY', ''));

// ============================================================================
// CONFIGURACIÓN DE SEGURIDAD
// ============================================================================
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', env('PASSWORD_MIN_LENGTH', 8));
define('SESSION_LIFETIME', env('SESSION_LIFETIME', 3600)); // 1 hora

// ============================================================================
// CONFIGURACIÓN DE EMAIL (SMTP)
// ============================================================================
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_FROM', env('SMTP_FROM', 'noreply@facturacion.com'));
define('SMTP_FROM_NAME', APP_NAME);

// ============================================================================
// EXTENSIONES DE ARCHIVO PERMITIDAS
// ============================================================================
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);

// ============================================================================
// CREAR DIRECTORIO DE UPLOADS SI NO EXISTE
// ============================================================================
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// ============================================================================
// AUTOLOADER SIMPLE PARA CLASES
// ============================================================================
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ============================================================================
// FUNCIONES HELPER
// ============================================================================

/**
 * Generar token CSRF
 */
function generateCSRFToken()
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitizar entrada
 */
function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redireccionar
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * Respuesta JSON
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verificar si el usuario está autenticado
 */
function isAuthenticated()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

/**
 * Verificar rol de usuario
 */
function hasRole($role)
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Requerir autenticación
 */
function requireAuth()
{
    if (!isAuthenticated()) {
        redirect('/agenteClasificador/public/login.php');
    }
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = 'd/m/Y')
{
    if (empty($date))
        return '-';
    return date($format, strtotime($date));
}

/**
 * Formatear moneda
 */
function formatCurrency($amount, $currency = 'USD')
{
    return $currency . ' ' . number_format($amount, 2, '.', ',');
}
