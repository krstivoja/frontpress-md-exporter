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
        @set_time_limit(0);
        $body  = $req->get_json_params();
        $ids   = is_array($body['site_ids'] ?? null) ? array_map('intval', $body['site_ids']) : [];
        return new WP_REST_Response(Exporter::start($ids));
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
