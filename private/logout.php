<?php
require_once '../config/config.php';
requireAuth();

$auth = new Auth();
$auth->logout();
redirect('/agenteClasificador/public/login.php');
