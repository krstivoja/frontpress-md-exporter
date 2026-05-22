<?php

declare(strict_types=1);

namespace FrontPressMdExp\Rest;

use FrontPressMdExp\Export\Exporter;
use FrontPressMdExp\Plugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ExportController
{
    public function register(): void
    {
        $args = [
            'run_id' => [
                'required'          => true,
                'sanitize_callback' => static fn($v) => preg_replace('/[^A-Za-z0-9]/', '', (string) $v),
            ],
        ];

        register_rest_route(Plugin::REST_NAMESPACE, '/export/start', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'start'],
            'permission_callback' => [Auth::class, 'canManage'],
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/export/tick', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'tick'],
            'permission_callback' => [Auth::class, 'canManage'],
            'args'                => $args + [
                'batch' => [
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                    'default'           => 20,
                ],
            ],
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/export/finalize', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'finalize'],
            'permission_callback' => [Auth::class, 'canManage'],
            'args'                => $args,
        ]);

        register_rest_route(Plugin::REST_NAMESPACE, '/export/download', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'download'],
            'permission_callback' => [Auth::class, 'canManage'],
            'args'                => $args + [
                'token' => [
                    'required'          => true,
                    'sanitize_callback' => static fn($v) => preg_replace('/[^A-Za-z0-9]/', '', (string) $v),
                ],
            ],
        ]);
    }

    public function start(WP_REST_Request $req): WP_REST_Response
    {
        @set_time_limit(0);
        return new WP_REST_Response(Exporter::start());
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
}
