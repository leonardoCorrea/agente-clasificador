<?php
require_once 'config/config.php';
$db = Database::getInstance();
$res = $db->select("SELECT id, archivo_original, estado, observaciones FROM facturas WHERE estado = 'error' OR estado = 'procesando' ORDER BY id DESC LIMIT 5");
print_r($res);
