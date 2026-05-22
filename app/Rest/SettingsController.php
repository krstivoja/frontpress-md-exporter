<?php

declare(strict_types=1);

namespace FrontPressMdExp\Rest;

use FrontPressMdExp\Plugin;
use FrontPressMdExp\Settings\Mapping;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SettingsController
{
    public function register(): void
    {
        register_rest_route(Plugin::REST_NAMESPACE, '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get'],
                'permission_callback' => [Auth::class, 'canManage'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save'],
                'permission_callback' => [Auth::class, 'canManage'],
            ],
        ]);
    }

    public function get(WP_REST_Request $req): WP_REST_Response
    {
        return new WP_REST_Response(Mapping::get());
    }

    public function save(WP_REST_Request $req): WP_REST_Response
    {
        $body = $req->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        return new WP_REST_Response(Mapping::save($body));
    }
}
