<?php
require 'config/config.php';
$db = Database::getInstance();
$f = $db->query('SELECT id, estado, observaciones FROM facturas ORDER BY id DESC LIMIT 5');
print_r($f);
