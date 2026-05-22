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
     * Same approach as the working test-ajax.php file
     */
    private function sendJson(array $data, int $statusCode = 200): void
    {
        $json = wp_json_encode($data);
        error_log('AJAX: Sending JSON (' . strlen($json) . ' bytes)');

        status_header($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Length: ' . strlen($json));
        echo $json;

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

        check_ajax_referer('fps-mdexp-ajax', 'nonce');

        $ids = isset($_POST['site_ids']) && is_array($_POST['site_ids'])
            ? array_map('intval', $_POST['site_ids'])
            : [];

        error_log('AJAX: Site IDs: ' . implode(', ', $ids));

        if (empty($ids)) {
            error_log('AJAX: No sites selected');
            $this->sendJson(['success' => false, 'data' => ['message' => 'No sites selected']], 400);
        }

        $result = Exporter::start($ids);
        error_log('AJAX: Export started, result: ' . print_r($result, true));

        if (isset($result['error'])) {
            error_log('AJAX: Export returned error');
            $this->sendJson(['success' => false, 'data' => $result], 400);
        }

        error_log('AJAX: Sending success response');
        $this->sendJson(['success' => true, 'data' => $result]);
    }

    public function tick(): void
    {
        if (!is_multisite() || !current_user_can('manage_network')) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Permission denied']], 403);
        }

        check_ajax_referer('fps-mdexp-ajax', 'nonce');

        $runId = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';
        $batch = isset($_POST['batch']) ? (int) $_POST['batch'] : 20;

        if (empty($runId)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Missing run_id']], 400);
        }

        $exp = new Exporter($runId);
        $result = $exp->tick($batch);

        $this->sendJson(['success' => true, 'data' => $result]);
    }

    public function finalize(): void
    {
        if (!is_multisite() || !current_user_can('manage_network')) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Permission denied']], 403);
        }

        check_ajax_referer('fps-mdexp-ajax', 'nonce');

        $runId = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';

        if (empty($runId)) {
            $this->sendJson(['success' => false, 'data' => ['message' => 'Missing run_id']], 400);
        }

        $exp = new Exporter($runId);
        $result = $exp->finalize();

        if (isset($result['error'])) {
            $this->sendJson(['success' => false, 'data' => $result], 400);
        }

        $this->sendJson(['success' => true, 'data' => $result]);
    }
}
