<?php
/**
 * Standalone test file to verify AJAX is working
 *
 * Access this at: https://yourdomain.com/wp-content/plugins/frontpress-md-exporter/test-ajax.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Use POST method']);
    exit;
}

// Simple test response
header('Content-Type: application/json');
header('Cache-Control: no-cache');
echo json_encode([
    'success' => true,
    'message' => 'AJAX test successful',
    'data' => [
        'timestamp' => time(),
        'post_data' => $_POST,
    ]
]);
