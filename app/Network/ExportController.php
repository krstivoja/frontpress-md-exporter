<?php

declare(strict_types=1);

namespace FrontPressMdExp\Network;

use FrontPressMdExp\Plugin;
use FrontPressMdExp\Rest\Auth;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ExportController
{
    public function register(): void
    {
        $runArg = [
            'run_id' => [
                'required'          => true,
                'sanitize_callback' => static fn($v) => preg_replace('/[^A-Za-z0-9]/', '', (string) $v),
            ],
        ];

        register_rest_route(Plugin::REST_NAMESPACE, '/network/test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'test'],
            'permission_callback' => [Auth::class, 'canManageNetwork'],
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/network/sites', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'sites'],
            'permission_callback' => [Auth::class, 'canManageNetwork'],
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/network/start', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'start'],
            'permission_callback' => [Auth::class, 'canManageNetwork'],
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/network/tick', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'tick'],
            'permission_callback' => [Auth::class, 'canManageNetwork'],
            'args'                => $runArg + [
                'batch' => [
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                    'default'           => 20,
                ],
            ],
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/network/finalize', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'finalize'],
            'permission_callback' => [Auth::class, 'canManageNetwork'],
            'args'                => $runArg,
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/network/download', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'download'],
            'permission_callback' => [Auth::class, 'canManageNetwork'],
            'args'                => $runArg + [
                'token' => [
                    'required'          => true,
                    'sanitize_callback' => static fn($v) => preg_replace('/[^A-Za-z0-9]/', '', (string) $v),
                ],
            ],
        ]);
    }

    public function sites(WP_REST_Request $req): WP_REST_Response
    {
        if (!is_multisite()) {
            return new WP_REST_Response(['multisite' => false, 'sites' => []]);
        }
        $sites = [];
        foreach (get_sites(['number' => 0]) as $s) {
            switch_to_blog((int) $s->blog_id);
            $sites[] = [
                'id'    => (int) $s->blog_id,
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
                'path'  => (string) $s->path,
                'count' => $this->postCount(),
            ];
            restore_current_blog();
        }
        return new WP_REST_Response(['multisite' => true, 'sites' => $sites]);
    }

    public function start(WP_REST_Request $req): WP_REST_Response
    {
        // Start output buffering to catch any stray output
        ob_start();

        try {
            @set_time_limit(0);

            // Log that we received the request
            error_log('Network export start called');

            // Force error output for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_reporting(E_ALL);
                ini_set('display_errors', '1');
            }

            $body  = $req->get_json_params();
            error_log('Request body: ' . print_r($body, true));

            if (!is_array($body) || empty($body)) {
                ob_end_clean();
                return new WP_REST_Response([
                    'error' => 'invalid_request',
                    'message' => 'Invalid request body.',
                ], 400);
            }

            $ids = is_array($body['site_ids'] ?? null) ? array_map('intval', $body['site_ids']) : [];
            error_log('Site IDs to export: ' . implode(', ', $ids));

            if (empty($ids)) {
                ob_end_clean();
                return new WP_REST_Response([
                    'error' => 'no_sites_selected',
                    'message' => 'Please select at least one site to export.',
                ], 400);
            }

            // Validate site IDs exist before attempting export
            foreach ($ids as $id) {
                $site = get_site($id);
                if (!$site) {
                    ob_end_clean();
                    return new WP_REST_Response([
                        'error' => 'invalid_site',
                        'message' => "Site ID {$id} does not exist.",
                    ], 400);
                }
            }

            error_log('Starting export...');
            $result = Exporter::start($ids);
            error_log('Export started successfully');
            error_log('Result: ' . print_r($result, true));

            $json = wp_json_encode($result);
            error_log('Result JSON size: ' . strlen($json) . ' bytes');

            if (isset($result['error'])) {
                ob_end_clean();
                return new WP_REST_Response($result, 400);
            }

            // Check for any buffered output
            $buffered = ob_get_clean();
            if ($buffered !== '' && $buffered !== false) {
                error_log('WARNING: Buffered output detected: ' . substr($buffered, 0, 200));
            } else {
                error_log('No buffered output detected');
            }

            error_log('Creating WP_REST_Response object');

            // WORKAROUND: Send response immediately to bypass server buffering
            status_header(200);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Debug-Size: ' . strlen($json));
            header('Content-Length: ' . strlen($json));

            error_log('Sending JSON directly: ' . strlen($json) . ' bytes');
            echo $json;
            flush();

            if (function_exists('fastcgi_finish_request')) {
                error_log('Calling fastcgi_finish_request()');
                fastcgi_finish_request();
            }

            error_log('Response sent, exiting');
            exit;
        } catch (\Throwable $e) {
            // Discard any buffered output
            ob_end_clean();

            // Log the error for debugging
            if (function_exists('error_log')) {
                error_log('Network export start failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }

            return new WP_REST_Response([
                'error' => 'export_failed',
                'message' => $e->getMessage(),
                'file' => WP_DEBUG ? $e->getFile() : null,
                'line' => WP_DEBUG ? $e->getLine() : null,
                'trace' => WP_DEBUG ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    public function tick(WP_REST_Request $req): WP_REST_Response
    {
        @set_time_limit(0);
        $exp = new Exporter((string) $req->get_param('run_id'));
        return new WP_REST_Response($exp->tick((int) $req->get_param('batch')));
    }

    public function finalize(WP_REST_Request $req): WP_REST_Response
    {
        @set_time_limit(0);
        $exp = new Exporter((string) $req->get_param('run_id'));
        return new WP_REST_Response($exp->finalize());
    }

    public function download(WP_REST_Request $req): void
    {
        $exp = new Exporter((string) $req->get_param('run_id'));
        $exp->streamDownload((string) $req->get_param('token'));
        exit;
    }

    private function postCount(): int
    {
        $total = 0;
        foreach (get_post_types(['public' => true], 'names') as $pt) {
            if ($pt === 'attachment') {
                continue;
            }
            $counts = wp_count_posts($pt);
            foreach (['publish', 'draft', 'private', 'pending', 'future'] as $st) {
                $total += isset($counts->$st) ? (int) $counts->$st : 0;
            }
        }
        return $total;
    }
}
