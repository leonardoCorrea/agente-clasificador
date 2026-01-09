<?php
/**
 * Test de Capacidad Background / No-Abort
 * Verifica si el servidor permite que un script siga corriendo tras cerrar la conexión.
 */
require_once 'config/config.php';

$logFile = __DIR__ . '/background_test.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Iniciando test...\n");

// 1. Enviar respuesta rápida
ob_start();
echo "<h1>Test de Background Iniciado</h1>";
echo "<p>Este script intentará seguir corriendo 30 segundos más tras cerrar esta página.</p>";
echo "<p>Revisa el archivo <code>background_test.log</code> en unos momentos.</p>";
$size = ob_get_length();
header("Content-Length: $size");
header("Connection: close");
ob_end_flush();
@ob_flush();
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 2. Trabajo en "background"
ignore_user_abort(true);
set_time_limit(60);

for ($i = 1; $i <= 6; $i++) {
    sleep(5);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Sigo vivo... Paso $i/6\n", FILE_APPEND);
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Test completado exitosamente.\n", FILE_APPEND);
