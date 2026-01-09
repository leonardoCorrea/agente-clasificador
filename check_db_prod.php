<?php
define('BOOTSTRAP_FORCE_PROD', true); // Custom flag to force prod in this script
require 'config/database.php';

// Mocking isProduction logic to force true
$produccion = true;
$servername = getenv('DB_PROD_HOST') ?: 'localhost';
$username = getenv('DB_PROD_USER') ?: 'lcorrea_adminFM';
$password = getenv('DB_PROD_PASS') ?: '';
$dbname = getenv('DB_PROD_NAME') ?: 'lcorrea_facturacion_aduanera';

$dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query('SELECT id, estado, observaciones FROM facturas ORDER BY id DESC LIMIT 5');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
