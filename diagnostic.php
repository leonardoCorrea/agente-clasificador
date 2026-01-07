<?php
/**
 * Script de diagnóstico para verificar configuración de OCR
 * Ejecutar desde: https://ticosoftcr.com/agenteClasificador/diagnostic.php
 */

echo "<h1>Diagnóstico de Configuración OCR</h1>";

// Cargar configuración
require_once __DIR__ . '/config/config.php';

echo "<h2>1. Constantes Definidas</h2>";
echo "<pre>";
echo "OCR_SERVICE_URL: " . (defined('OCR_SERVICE_URL') ? OCR_SERVICE_URL : 'NO DEFINIDA') . "\n";
echo "IS_PRODUCTION: " . (defined('IS_PRODUCTION') ? (IS_PRODUCTION ? 'true' : 'false') : 'NO DEFINIDA') . "\n";
echo "IS_DEVELOPMENT: " . (defined('IS_DEVELOPMENT') ? (IS_DEVELOPMENT ? 'true' : 'false') : 'NO DEFINIDA') . "\n";
echo "</pre>";

echo "<h2>2. Archivo OCRService.php</h2>";
$ocrServicePath = __DIR__ . '/classes/OCRService.php';
if (file_exists($ocrServicePath)) {
    echo "<p>✅ Archivo existe</p>";
    
    // Leer primeras líneas para verificar versión
    $content = file_get_contents($ocrServicePath);
    
    if (strpos($content, 'callOCRService') !== false) {
        echo "<p>✅ Versión NUEVA (usa HTTP microservicio)</p>";
    } else {
        echo "<p>❌ Versión ANTIGUA (ejecuta Python localmente)</p>";
    }
    
    if (strpos($content, '$pythonPath') !== false) {
        echo "<p>⚠️ PROBLEMA: Todavía tiene código de Python local</p>";
    }
    
    if (strpos($content, '$ocrServiceUrl') !== false) {
        echo "<p>✅ Tiene variable ocrServiceUrl</p>";
    }
    
    // Mostrar primeras 50 líneas
    $lines = explode("\n", $content);
    echo "<h3>Primeras 30 líneas del archivo:</h3>";
    echo "<pre>";
    for ($i = 0; $i < min(30, count($lines)); $i++) {
        echo ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "\n";
    }
    echo "</pre>";
    
} else {
    echo "<p>❌ Archivo NO existe</p>";
}

echo "<h2>3. Test de Conexión al Microservicio</h2>";
if (defined('OCR_SERVICE_URL')) {
    $healthUrl = OCR_SERVICE_URL . '/health';
    echo "<p>Probando: <a href='$healthUrl' target='_blank'>$healthUrl</a></p>";
    
    $ch = curl_init($healthUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p>❌ Error de conexión: $error</p>";
    } elseif ($httpCode === 200) {
        echo "<p>✅ Microservicio responde correctamente</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<p>⚠️ HTTP Code: $httpCode</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
} else {
    echo "<p>❌ OCR_SERVICE_URL no está definida</p>";
}

echo "<h2>4. Información del Servidor</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . __FILE__ . "\n";
echo "</pre>";

echo "<h2>5. Extensiones PHP</h2>";
echo "<pre>";
echo "cURL: " . (extension_loaded('curl') ? '✅ Instalada' : '❌ NO instalada') . "\n";
echo "JSON: " . (extension_loaded('json') ? '✅ Instalada' : '❌ NO instalada') . "\n";
echo "</pre>";
