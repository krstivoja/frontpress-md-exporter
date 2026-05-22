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

        // Clear ALL output buffers that WordPress might have created
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        status_header($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Length: ' . strlen($json));

        echo $json;

        // Flush output to browser
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }

        exit;
    }

    public function start(): void
    {
        // Security check
        if (!is_multisite() || !current_user_can('manage_network')) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Permission denied']], 403);
        }

        if (!check_ajax_referer('fps-mdexp-ajax', 'nonce', false)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Invalid or expired export session. Refresh the page and try again.']], 403);
        }

        $ids = isset($_POST['site_ids']) && is_array($_POST['site_ids'])
            ? array_map('intval', $_POST['site_ids'])
            : [];

        if (empty($ids)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'No sites selected']], 400);
        }

        try {
            $result = Exporter::start($ids);
        } catch (\Throwable $e) {
            $this->sendJson(['success' => false, 'data' => ['message' => $e->getMessage()]], 500);
        }

        if (isset($result['error'])) {
            $this->sendJson(['success' => false, 'data' => $result], 400);
        }

        $this->sendJson(['success' => true, 'data' => $result]);
    }

    public function tick(): void
    {
        if (!is_multisite() || !current_user_can('manage_network')) {
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
            $this->sendJson(['success' => false, 'data' => ['message' => $e->getMessage()]], 500);
        }

        if (isset($result['error'])) {
            $this->sendJson(['success' => false, 'data' => $result], 400);
        }

        $this->sendJson(['success' => true, 'data' => $result]);
    }
}
