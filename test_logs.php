<?php
require_once 'config/config.php';

echo "<h1>Diagnóstico de Logs Definitivo</h1>";
echo "BASE_PATH: " . BASE_PATH . "<br>";
echo "Ruta Logs deseada: " . BASE_PATH . "/logs<br>";

$logDir = BASE_PATH . '/logs';
if (!is_dir($logDir)) {
    echo "Intentando crear directorio con mkdir($logDir, 0777, true)...<br>";
    $res = @mkdir($logDir, 0777, true);
    echo "Resultado de mkdir: " . ($res ? "ÉXITO" : "FALLO") . "<br>";
} else {
    echo "El directorio ya existe.<br>";
}

echo "Permisos actuales del directorio: " . substr(sprintf('%o', fileperms($logDir)), -4) . "<br>";

$testFile = $logDir . '/test_verify.log';
$content = "[" . date('Y-m-d H:i:s') . "] Prueba de escritura.\n";
$wrote = @file_put_contents($testFile, $content, FILE_APPEND);

if ($wrote !== false) {
    echo "<span style='color:green'>ÉXITO: Se escribió en $testFile</span><br>";
    echo "Contenido del archivo:<br><pre>" . htmlspecialchars(file_get_contents($testFile)) . "</pre>";
} else {
    echo "<span style='color:red'>ERROR: No se pudo escribir. Error error_get_last(): </span>";
    print_r(error_get_last());
}

echo "<h2>Exploración de archivos en logs/</h2>";
$files = scandir($logDir);
print_r($files);
