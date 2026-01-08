<?php
/**
 * OCR Debugging & Test Screen
 * Use this to diagnose connectivity and processing issues in production.
 */
require_once 'config/config.php';
requireAuth();

// Prevenir timeouts de PHP y avisar al servidor (Litespeed/Nginx)
set_time_limit(0);
header('X-Accel-Buffering: no'); // Para Nginx
@ini_set('max_execution_time', 0);
@ini_set('memory_limit', '512M');

$testFile = '/home/lcorrea/ticosoftcr.com/agenteClasificador/uploads/factura_694abdf31a5fa.pdf';
$remoteUrl = 'https://agente-clasificador-production.up.railway.app/api/ocr/status';
$processUrl = 'https://agente-clasificador-production.up.railway.app/api/ocr/process';

$results = [];
$logs = [];

function addLog($msg, $type = 'info')
{
    global $logs;
    $logs[] = ['msg' => $msg, 'type' => $type, 'time' => date('H:i:s')];
}

// Action: Check Version
if (isset($_POST['action']) && $_POST['action'] === 'check_version') {
    $target = 'https://agente-clasificador-production.up.railway.app/api/ocr/status';
    addLog("Checking Version at: $target");

    // Check local API Key Integrity
    $key = OPENAI_API_KEY;
    $len = strlen($key);
    $masked = $len > 10 ? substr($key, 0, 8) . '...' . substr($key, -4) : 'TOO SHORT';
    addLog("Local API Key Check: Length=$len, Format=$masked");

    $ch = curl_init($target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    addLog("Raw Status Response: " . $response);
    $decoded = json_decode($response, true);
    if (isset($decoded['build_id'])) {
        addLog("✅ DETECTED BUILD ID: " . $decoded['build_id'], "success");
    } else {
        addLog("❌ NO BUILD ID DETECTED (Running old version)", "danger");
    }
}

// 1. Check Local Environment
addLog("Verifying local environment...");
addLog("IS_PRODUCTION: " . (IS_PRODUCTION ? 'Yes' : 'No'));
addLog("OCR_SERVICE_URL: " . OCR_SERVICE_URL);

// 2. Check Test File
if (file_exists($testFile)) {
    addLog("Test file found: $testFile", "success");
    addLog("Size: " . filesize($testFile) . " bytes");
} else {
    addLog("Test file NOT FOUND at: $testFile", "danger");
}

// Action: Test Health
if (isset($_POST['action']) && $_POST['action'] === 'test_health') {
    addLog("Testing Railway Health endpoint...");
    $ch = curl_init($remoteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        addLog("Connection Error: $err", "danger");
    } else {
        addLog("HTTP Code: " . $info['http_code'], $info['http_code'] == 200 ? "success" : "warning");
        addLog("Raw Response: " . $response);
        $decoded = json_decode($response, true);
        if (isset($decoded['build_id'])) {
            addLog("Service Build ID: " . $decoded['build_id'], "success");
        }
    }
}

// Action: Process Test File
if (isset($_POST['action']) && $_POST['action'] === 'process_test') {
    addLog("Sending file to OCR service: $processUrl");

    if (!file_exists($testFile)) {
        addLog("Cannot process: file not found", "danger");
    } else {
        $ch = curl_init($processUrl);
        $postFields = [
            'file' => new CURLFile($testFile),
            'api_key' => OPENAI_API_KEY
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            addLog("Processing Error (cURL): $err", "danger");
        } else {
            addLog("HTTP Code: " . $info['http_code']);
            $decoded = json_decode($response, true);
            if (isset($decoded['build_id'])) {
                addLog("Response Build ID: " . $decoded['build_id'], "info");
            }
            if (json_last_error() === JSON_ERROR_NONE) {
                addLog("Response Status: " . ($decoded['success'] ? "SUCCESS" : "FAILED"), $decoded['success'] ? "success" : "danger");
                if (!$decoded['success']) {
                    addLog("Internal Error Message: " . ($decoded['error'] ?? 'No message'), "danger");
                }
            } else {
                addLog("Invalid JSON Response Received", "danger");
                addLog("Raw Body Tip: " . substr($response, 0, 1000));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>OCR Debug Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .console {
            background: #212529;
            color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', Courier, monospace;
            min-height: 400px;
            max-height: 600px;
            overflow-y: auto;
        }

        .log-time {
            color: #6c757d;
            font-size: 0.8em;
            margin-right: 10px;
        }

        .log-info {
            color: #0dcaf0;
        }

        .log-success {
            color: #198754;
            font-weight: bold;
        }

        .log-warning {
            color: #ffc107;
        }

        .log-danger {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>

<body class="p-4">
    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">OCR Debug Console</h3>
                <div>
                    <a href="private/ocr-upload.php" class="btn btn-outline-light btn-sm">Volver al Upload</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>Acciones de Diagnóstico</h5>
                        <form method="POST" class="d-flex gap-2">
                            <button name="action" value="check_version" class="btn btn-warning">1. Verificar Versión en
                                Producción</button>
                            <button name="action" value="test_health" class="btn btn-info text-white">2. Probar Conexión
                                (Health)</button>
                            <button name="action" value="process_test" class="btn btn-success">3. Procesar Factura
                                Específica</button>
                        </form>
                    </div>
                </div>

                <div class="console mt-3">
                    <?php if (empty($logs)): ?>
                        <div class="text-muted">Esperando acciones... Haz clic en "Verificar Versión en Producción" para
                            empezar.</div>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="mb-1">
                            <span class="log-time">[<?php echo $log['time']; ?>]</span>
                            <span
                                class="log-<?php echo $log['type']; ?>"><?php echo htmlspecialchars($log['msg']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>