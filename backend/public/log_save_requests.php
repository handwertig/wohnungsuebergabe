<?php
/**
 * Einfacher Logging-Test für alle eingehenden Requests
 */

// Log alle Requests zu /protocols/save
$logFile = __DIR__ . '/../logs/save_requests.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'NONE',
    'post_data' => $_POST,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'NONE'
];

file_put_contents($logFile, date('Y-m-d H:i:s') . " SAVE REQUEST: " . json_encode($logData) . PHP_EOL, FILE_APPEND);

echo "Log written to: $logFile\n";
echo "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . "\n";
echo "URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN') . "\n";
echo "POST data: " . json_encode($_POST) . "\n";
?>