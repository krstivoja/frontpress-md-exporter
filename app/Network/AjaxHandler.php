<?php

declare(strict_types=1);

namespace FrontPressMdExp\Network;

use FrontPressMdExp\Settings\Mapping;

/**
 * Alternative AJAX handler that bypasses REST API
 * Workaround for CloudPanel/ModSecurity blocking REST API responses
 */
final class AjaxHandler
{
    public function register(): void
    {
        add_action('wp_ajax_fps_network_start', [$this, 'start']);
        add_action('wp_ajax_fps_network_tick', [$this, 'tick']);
        add_action('wp_ajax_fps_network_finalize', [$this, 'finalize']);
    }

    /**
     * Send JSON response directly (bypass WordPress buffering)
     * Clear all output buffers before sending
     */
    private function sendJson(array $data, int $statusCode = 200): void
    {
        $json = wp_json_encode($data);
        if (!is_string($json)) {
            $json = '{"success":false,"data":{"message":"Could not encode export response as JSON."}}';
            $statusCode = 500;
        }

        error_log('AJAX: Sending JSON (' . strlen($json) . ' bytes)');

        // Clear ALL output buffers that WordPress might have created
        $levels = ob_get_level();
        error_log('AJAX: Clearing ' . $levels . ' output buffer levels');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        status_header($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Length: ' . strlen($json));

        echo $json;

        // Flush output to browser
        if (function_exists('fastcgi_finish_request')) {
            error_log('AJAX: Calling fastcgi_finish_request()');
            fastcgi_finish_request();
        } else {
            error_log('AJAX: Calling flush()');
            flush();
        }

        error_log('AJAX: Response sent, exiting');
        exit;
    }

    public function start(): void
    {
        error_log('AJAX: Network export start called');

        // Security check
        if (!is_multisite() || !current_user_can('manage_network')) {
            error_log('AJAX: Permission denied');
            $this->sendJson(['success' => false, 'data' => ['message' => 'Permission denied']], 403);
        }

        if (!check_ajax_referer('fps-mdexp-ajax', 'nonce', false)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Invalid or expired export session. Refresh the page and try again.']], 403);
        }

        $ids = isset($_POST['site_ids']) && is_array($_POST['site_ids'])
            ? array_map('intval', $_POST['site_ids'])
            : [];

        error_log('AJAX: Site IDs: ' . implode(', ', $ids));

        if (empty($ids)) {
            error_log('AJAX: No sites selected');
            $this->sendJson(['success' => false, 'data' => ['message' => 'No sites selected']], 400);
        }

        try {
            $result = Exporter::start($ids);
            error_log('AJAX: Export started, result: ' . print_r($result, true));
        } catch (\Throwable $e) {
            error_log('AJAX: Export start failed: ' . $e->getMessage());
            $this->sendJson(['success' => false, 'data' => ['message' => $e->getMessage()]], 500);
        }

        if (isset($result['error'])) {
            error_log('AJAX: Export returned error');
            $this->sendJson(['success' => false, 'data' => $result], 400);
        }

        error_log('AJAX: Sending success response');
        $this->sendJson(['success' => true, 'data' => $result]);
    }

    public function tick(): void
    {
        error_log('AJAX: tick() called');

        if (!is_multisite() || !current_user_can('manage_network')) {
            error_log('AJAX: tick() permission denied');
            $this->sendJson(['success' => false, 'data' => ['message' => 'Permission denied']], 403);
        }

        if (!check_ajax_referer('fps-mdexp-ajax', 'nonce', false)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Invalid or expired export session. Refresh the page and try again.']], 403);
        }

        $runId = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';
        $batch = isset($_POST['batch']) ? (int) $_POST['batch'] : 20;

        if (empty($runId)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Missing run_id']], 400);
        }

        try {
            $exp = new Exporter($runId);
            $result = $exp->tick($batch);
        } catch (\Throwable $e) {
            error_log('AJAX: tick() failed: ' . $e->getMessage());
            $this->sendJson(['success' => false, 'data' => ['message' => $e->getMessage()]], 500);
        }

        if (isset($result['error'])) {
            $this->sendJson(['success' => false, 'data' => $result], 400);
        }

        $this->sendJson(['success' => true, 'data' => $result]);
    }

    public function finalize(): void
    {
        if (!is_multisite() || !current_user_can('manage_network')) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Permission denied']], 403);
        }

        if (!check_ajax_referer('fps-mdexp-ajax', 'nonce', false)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Invalid or expired export session. Refresh the page and try again.']], 403);
        }

        $runId = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';

        if (empty($runId)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Missing run_id']], 400);
        }

        try {
            $exp = new Exporter($runId);
            $result = $exp->finalize();
        } catch (\Throwable $e) {
            error_log('AJAX: finalize() failed: ' . $e->getMessage());
            $this->sendJson(['success' => false, 'data' => ['message' => $e->getMessage()]], 500);
        }

        if (isset($result['error'])) {
            $this->sendJson(['success' => false, 'data' => $result], 400);
        }

        $this->sendJson(['success' => true, 'data' => $result]);
    }
}
