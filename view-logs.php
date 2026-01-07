<?php
/**
 * Script para ver los últimos logs de error
 * Ejecutar desde: https://ticosoftcr.com/agenteClasificador/view-logs.php
 */

echo "<h1>Últimos Logs de Error</h1>";

// Buscar archivo de error log
$possibleLogFiles = [
    __DIR__ . '/error_log',
    __DIR__ . '/../error_log',
    ini_get('error_log'),
    '/home/lcorrea/ticosoftcr.com/error_log',
    '/home/lcorrea/ticosoftcr.com/agenteClasificador/error_log'
];

echo "<h2>Buscando archivos de log...</h2>";
$logFile = null;
foreach ($possibleLogFiles as $file) {
    if ($file && file_exists($file)) {
        echo "<p>✅ Encontrado: $file</p>";
        $logFile = $file;
        break;
    } else {
        echo "<p>❌ No existe: $file</p>";
    }
}

if (!$logFile) {
    echo "<h2>Logs de PHP (últimas 50 líneas desde php://stderr)</h2>";
    echo "<p>No se encontró archivo de log. Mostrando configuración de PHP:</p>";
    echo "<pre>";
    echo "error_log setting: " . ini_get('error_log') . "\n";
    echo "log_errors: " . ini_get('log_errors') . "\n";
    echo "display_errors: " . ini_get('display_errors') . "\n";
    echo "</pre>";
} else {
    echo "<h2>Últimas 100 líneas del log: $logFile</h2>";

    // Leer últimas líneas
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);

    echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
    foreach ($lastLines as $line) {
        // Resaltar líneas con "OCR" o "Error"
        if (stripos($line, 'OCR') !== false || stripos($line, 'Error') !== false) {
            echo "<strong style='color: red;'>" . htmlspecialchars($line) . "</strong>";
        } else {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";

    // Filtrar solo líneas de OCR
    echo "<h2>Solo líneas relacionadas con OCR (últimas 50):</h2>";
    $ocrLines = array_filter($lines, function ($line) {
        return stripos($line, 'OCR') !== false || stripos($line, 'Microservice') !== false;
    });
    $lastOcrLines = array_slice($ocrLines, -50);

    echo "<pre style='background: #ffe; padding: 10px; overflow-x: auto;'>";
    foreach ($lastOcrLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='diagnostic.php'>← Volver al diagnóstico</a></p>";
