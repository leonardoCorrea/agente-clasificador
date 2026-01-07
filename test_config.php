<?php
/**
 * Script de Prueba de Configuración
 * Verifica que la configuración de base de datos y entorno funcione correctamente
 */

// Cargar configuración
require_once __DIR__ . '/config/config.php';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Configuración -
        <?= APP_NAME ?>
    </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }

        h2 {
            color: #555;
            margin-top: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }

        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #4CAF50;
            color: white;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }

        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔧 Prueba de Configuración del Sistema</h1>
        <p><strong>Aplicación:</strong>
            <?= APP_NAME ?> v
            <?= APP_VERSION ?>
        </p>
        <p><strong>Fecha:</strong>
            <?= date('d/m/Y H:i:s') ?>
        </p>

        <h2>1. Detección de Entorno</h2>
        <?php if (IS_DEVELOPMENT): ?>
            <div class="info">
                <strong>✓ Entorno Detectado:</strong> <span class="badge badge-warning">DESARROLLO</span><br>
                <strong>Host:</strong>
                <?= $_SERVER['HTTP_HOST'] ?? 'N/A' ?><br>
                <strong>Debug Mode:</strong>
                <?= isDebugMode() ? 'Activado' : 'Desactivado' ?>
            </div>
        <?php else: ?>
            <div class="info">
                <strong>✓ Entorno Detectado:</strong> <span class="badge badge-success">PRODUCCIÓN</span><br>
                <strong>Host:</strong>
                <?= $_SERVER['HTTP_HOST'] ?? 'N/A' ?><br>
                <strong>Debug Mode:</strong>
                <?= isDebugMode() ? 'Activado' : 'Desactivado' ?>
            </div>
        <?php endif; ?>

        <h2>2. Configuración de Base de Datos</h2>
        <table>
            <tr>
                <th>Parámetro</th>
                <th>Valor</th>
            </tr>
            <tr>
                <td>Host</td>
                <td><code><?= DB_HOST ?></code></td>
            </tr>
            <tr>
                <td>Base de Datos</td>
                <td><code><?= DB_NAME ?></code></td>
            </tr>
            <tr>
                <td>Usuario</td>
                <td><code><?= DB_USER ?></code></td>
            </tr>
            <tr>
                <td>Contraseña</td>
                <td><code><?= str_repeat('*', strlen(DB_PASS)) ?></code> (
                    <?= strlen(DB_PASS) ?> caracteres)
                </td>
            </tr>
            <tr>
                <td>Charset</td>
                <td><code><?= DB_CHARSET ?></code></td>
            </tr>
        </table>

        <h2>3. Prueba de Conexión a Base de Datos</h2>
        <?php
        try {
            $db = Database::getInstance();
            $connection = $db->getConnection();

            // Probar conexión
            $result = $db->queryOne("SELECT VERSION() as version, DATABASE() as db_name");

            echo '<div class="success">';
            echo '<strong>✓ Conexión Exitosa</strong><br>';
            echo '<strong>MySQL Version:</strong> ' . $result['version'] . '<br>';
            echo '<strong>Base de Datos Actual:</strong> ' . $result['db_name'];
            echo '</div>';

            // Verificar estado de conexión
            if ($db->isConnected()) {
                echo '<div class="success">';
                echo '<strong>✓ Estado de Conexión:</strong> Activa';
                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ Error de Conexión:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>

        <h2>4. Configuración de Rutas</h2>
        <table>
            <tr>
                <th>Ruta</th>
                <th>Valor</th>
                <th>Existe</th>
            </tr>
            <tr>
                <td>BASE_PATH</td>
                <td><code><?= BASE_PATH ?></code></td>
                <td>
                    <?= file_exists(BASE_PATH) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>' ?>
                </td>
            </tr>
            <tr>
                <td>UPLOAD_PATH</td>
                <td><code><?= UPLOAD_PATH ?></code></td>
                <td>
                    <?= file_exists(UPLOAD_PATH) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>' ?>
                </td>
            </tr>
            <tr>
                <td>PYTHON_SCRIPTS_DIR</td>
                <td><code><?= PYTHON_SCRIPTS_DIR ?></code></td>
                <td>
                    <?= file_exists(PYTHON_SCRIPTS_DIR) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>' ?>
                </td>
            </tr>
        </table>

        <h2>5. Otras Configuraciones</h2>
        <table>
            <tr>
                <th>Configuración</th>
                <th>Valor</th>
            </tr>
            <tr>
                <td>MAX_FILE_SIZE</td>
                <td><code><?= number_format(MAX_FILE_SIZE / 1024 / 1024, 2) ?> MB</code></td>
            </tr>
            <tr>
                <td>SESSION_LIFETIME</td>
                <td><code><?= SESSION_LIFETIME ?> segundos (<?= SESSION_LIFETIME / 60 ?> minutos)</code></td>
            </tr>
            <tr>
                <td>PASSWORD_MIN_LENGTH</td>
                <td><code><?= PASSWORD_MIN_LENGTH ?> caracteres</code></td>
            </tr>
            <tr>
                <td>OPENAI_API_KEY</td>
                <td><code><?= !empty(OPENAI_API_KEY) ? 'Configurada (' . strlen(OPENAI_API_KEY) . ' caracteres)' : 'No configurada' ?></code>
                </td>
            </tr>
            <tr>
                <td>SMTP_HOST</td>
                <td><code><?= SMTP_HOST ?>:<?= SMTP_PORT ?></code></td>
            </tr>
        </table>

        <h2>6. Extensiones de Archivo Permitidas</h2>
        <div class="info">
            <?php foreach (ALLOWED_EXTENSIONS as $ext): ?>
                <span class="badge badge-success">
                    <?= strtoupper($ext) ?>
                </span>
            <?php endforeach; ?>
        </div>

        <h2>7. Variables de Entorno (.env)</h2>
        <?php
        $envFile = BASE_PATH . '/.env';
        if (file_exists($envFile)) {
            echo '<div class="success">';
            echo '<strong>✓ Archivo .env encontrado</strong><br>';
            echo '<strong>Ubicación:</strong> <code>' . $envFile . '</code>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>✗ Archivo .env no encontrado</strong><br>';
            echo 'Por favor, copie .env.example a .env y configure las variables.';
            echo '</div>';
        }
        ?>

        <h2>8. Resumen de Pruebas</h2>
        <?php
        $tests = [
            'Detección de entorno' => true,
            'Configuración de BD cargada' => defined('DB_HOST') && defined('DB_NAME'),
            'Conexión a BD' => isset($db) && $db->isConnected(),
            'Directorio de uploads existe' => file_exists(UPLOAD_PATH),
            'Archivo .env existe' => file_exists($envFile),
            'OpenAI API Key configurada' => !empty(OPENAI_API_KEY)
        ];

        $passed = array_filter($tests);
        $total = count($tests);
        $passedCount = count($passed);

        if ($passedCount === $total) {
            echo '<div class="success">';
            echo '<strong>✓ Todas las pruebas pasaron (' . $passedCount . '/' . $total . ')</strong>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>✗ Algunas pruebas fallaron (' . $passedCount . '/' . $total . ' pasaron)</strong>';
            echo '</div>';
        }
        ?>

        <table>
            <tr>
                <th>Prueba</th>
                <th>Resultado</th>
            </tr>
            <?php foreach ($tests as $test => $result): ?>
                <tr>
                    <td>
                        <?= $test ?>
                    </td>
                    <td>
                        <?php if ($result): ?>
                            <span class="badge badge-success">✓ PASS</span>
                        <?php else: ?>
                            <span class="badge badge-danger">✗ FAIL</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-radius: 4px;">
            <strong>📝 Nota:</strong> Este archivo es solo para pruebas. Elimínelo o restrinja el acceso en producción.
        </div>
    </div>
</body>

</html>