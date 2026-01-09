<?php
require_once '../config/config.php';
requireAuth();

$id = $_GET['id'] ?? null;

if ($id) {
    $db = Database::getInstance();
    $db->update('facturas', ['estado' => 'pendiente', 'observaciones' => 'Reinicio manual de estado'], ['id' => $id]);

    // Registrar en auditoría
    AuditLog::log($_SESSION['user_id'], 'reset_ocr_status', 'facturas', $id);

    header("Location: ocr-upload.php?message=Estado reiniciado con éxito&type=info");
    exit;
}

header("Location: ocr-upload.php");
exit;
