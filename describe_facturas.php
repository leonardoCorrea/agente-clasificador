<?php
require_once 'config/config.php';
$db = Database::getInstance();
$res = $db->select('DESCRIBE facturas');
print_r($res);
