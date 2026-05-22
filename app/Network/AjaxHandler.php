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

    public function start(): void
    {
        error_log('AJAX: Network export start called');

        // Security check
        if (!is_multisite() || !current_user_can('manage_network')) {
            error_log('AJAX: Permission denied');
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        check_ajax_referer('fps-mdexp-ajax', 'nonce');

        $ids = isset($_POST['site_ids']) && is_array($_POST['site_ids'])
            ? array_map('intval', $_POST['site_ids'])
            : [];

        error_log('AJAX: Site IDs: ' . implode(', ', $ids));

        if (empty($ids)) {
            error_log('AJAX: No sites selected');
            wp_send_json_error(['message' => 'No sites selected'], 400);
        }

        $result = Exporter::start($ids);
        error_log('AJAX: Export started, result: ' . print_r($result, true));

        if (isset($result['error'])) {
            error_log('AJAX: Export returned error');
            wp_send_json_error($result, 400);
        }

        error_log('AJAX: Sending success response');
        wp_send_json_success($result);
    }

    public function tick(): void
    {
        if (!is_multisite() || !current_user_can('manage_network')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        check_ajax_referer('fps-mdexp-ajax', 'nonce');

        $runId = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';
        $batch = isset($_POST['batch']) ? (int) $_POST['batch'] : 20;

        if (empty($runId)) {
            wp_send_json_error(['message' => 'Missing run_id'], 400);
        }

        $exp = new Exporter($runId);
        $result = $exp->tick($batch);

        wp_send_json_success($result);
    }

    public function finalize(): void
    {
        if (!is_multisite() || !current_user_can('manage_network')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        check_ajax_referer('fps-mdexp-ajax', 'nonce');

        $runId = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';

        if (empty($runId)) {
            wp_send_json_error(['message' => 'Missing run_id'], 400);
        }

        $exp = new Exporter($runId);
        $result = $exp->finalize();

        if (isset($result['error'])) {
            wp_send_json_error($result, 400);
        }

        wp_send_json_success($result);
    }
}
