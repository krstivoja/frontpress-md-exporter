<?php
/**
 * Standalone test file to verify AJAX response delivery
 *
 * Test with curl:
 * curl -X POST https://docs.dplugins.com/wp-content/plugins/frontpress-md-exporter/test-ajax.php
 *
 * Or from browser console:
 * fetch('/wp-content/plugins/frontpress-md-exporter/test-ajax.php', {method: 'POST'}).then(r => r.text()).then(console.log)
 */

error_log('TEST-AJAX: Request received');

// Load WordPress
require_once('../../../wp-load.php');

error_log('TEST-AJAX: WordPress loaded');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Use POST method']);
    exit;
}

error_log('TEST-AJAX: Creating mock response (similar size to real export)');

// Create a response similar in size to the real export response (~691 bytes)
$mockData = [
    'run_id' => 'test123456789012',
    'total' => 281,
    'subsites' => array_fill(0, 14, [
        'id' => 1,
        'slug' => 'subsite',
        'count' => 20,
    ]),
    'warnings' => null,
];

$json = json_encode(['success' => true, 'data' => $mockData]);
error_log('TEST-AJAX: JSON size: ' . strlen($json) . ' bytes');

// Use the exact same headers as AjaxHandler
header('Content-Type: application/json; charset=UTF-8');
header('Content-Length: ' . strlen($json));
echo $json;

error_log('TEST-AJAX: Response echoed');
exit;
