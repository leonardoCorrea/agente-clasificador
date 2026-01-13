<?php
require_once 'config/config.php';
echo "PYTHON_PATH: " . (defined('PYTHON_PATH') ? PYTHON_PATH : "UNDEFINED") . "\n";
echo "OPENAI_API_KEY: " . (defined('OPENAI_API_KEY') ? "DEFINED" : "UNDEFINED") . "\n";
echo "IS_DEVELOPMENT: " . (IS_DEVELOPMENT ? "TRUE" : "FALSE") . "\n";
