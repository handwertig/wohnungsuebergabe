<?php
/**
 * Debug Handler - zeigt was beim Save-Request passiert
 */

declare(strict_types=1);

// Log Request Details
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
    'post_data' => $_POST,
    'get_data' => $_GET,
    'session_data' => $_SESSION ?? [],
    'headers' => getallheaders() ?: [],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN'
];

error_log('SAVE REQUEST DEBUG: ' . json_encode($logData, JSON_UNESCAPED_UNICODE));

// Return JSON response für AJAX calls
header('Content-Type: application/json');
echo json_encode([
    'debug' => true,
    'message' => 'Save request received and logged',
    'data' => $logData,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>