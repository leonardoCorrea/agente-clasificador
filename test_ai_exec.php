<?php
require_once 'config/config.php';

echo "Testing AIClassificationService...\n";

$aiService = new AIClassificationService();

// Mocking some data for a test item that should exist in the DB (or just testing the execution)
// We'll test the private method executeClassification directly if we can, or just mock a call.
// Since it's private, we'll test a public one that calls it.

echo "Executing classification test with dummy data...\n";

// We need an item ID from the database for a real test, 
// but let's try to reflectively call the private method or just use a known ID if available.
// Alternatively, let's just test if we can run command directly from PHP as the service does.

$pythonPath = PYTHON_PATH;
$scriptPath = 'python-scripts/ai_classify.py';
$jsonInput = json_encode(["TEST ITEM DESCR"], JSON_UNESCAPED_UNICODE);
$apiKey = OPENAI_API_KEY;
$jsonContext = "[]";

$command = sprintf(
    '"%s" "%s" %s "%s" %s 2>&1',
    $pythonPath,
    $scriptPath,
    escapeshellarg($jsonInput),
    $apiKey,
    escapeshellarg($jsonContext)
);

echo "Command: $command\n";
$output = shell_exec($command);
echo "Output: $output\n";

$result = json_decode($output, true);
if ($result && isset($result['success'])) {
    echo "SUCCESS: Python script executed and returned valid JSON.\n";
} else {
    echo "FAILURE: Could not parse JSON or script failed.\n";
}
