<?php
require_once 'config/config.php';

echo "<h1>Diagnóstico de Conectividad y Permisos</h1>";

// 1. Test de Escritura
$logDir = BASE_PATH . '/logs';
$testFile = $logDir . '/write_test.txt';

echo "<h2>1. Permisos de Escritura</h2>";
echo "Ruta de logs: " . $logDir . "<br>";
echo "¿Existe el directorio?: " . (is_dir($logDir) ? "SÍ" : "NO") . "<br>";

if (!is_dir($logDir)) {
    echo "Intentando crear directorio...<br>";
    mkdir($logDir, 0777, true);
}

$written = @file_put_contents($testFile, "Prueba de escritura: " . date('Y-m-d H:i:s'));
if ($written !== false) {
    echo "<span style='color:green'>ÉXITO: Se pudo escribir en $testFile</span><br>";
} else {
    echo "<span style='color:red'>ERROR: No se pudo escribir en el directorio de logs. Revisa los permisos (CHMOD 755 o 777).</span><br>";
}

// 2. Test de Conectividad a Railway
echo "<h2>2. Conectividad a Railway</h2>";
$url = OCR_SERVICE_URL . '/api/health'; // Asumiendo que hay un /api/health o root
echo "URL de prueba: " . $url . "<br>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // A veces necesario en CPanel para testear
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "<span style='color:red'>ERROR CURL: $curlError</span><br>";
} else {
    echo "<span style='color:green'>RESPUESTA RAILWAY: Código $httpCode</span><br>";
    echo "Contenido: " . htmlspecialchars(substr($response, 0, 200)) . "<br>";
}

// 3. Test de DNS
echo "<h2>3. Resolución DNS</h2>";
$host = parse_url(OCR_SERVICE_URL, PHP_URL_HOST);
$ip = gethostbyname($host);
echo "Host: $host -> IP: $ip<br>";
if ($ip === $host) {
    echo "<span style='color:red'>ERROR: No se puede resolver el host. Revisar DNS del servidor.</span><br>";
} else {
    echo "<span style='color:green'>DNS OK: El host resuelve correctamente.</span><br>";
}
