<?php

declare(strict_types=1);

namespace FrontPressMdExp;

use FrontPressMdExp\Admin\SettingsPage;
use FrontPressMdExp\Network\AdminPage as NetworkAdminPage;
use FrontPressMdExp\Network\AjaxHandler;
use FrontPressMdExp\Network\ExportController as NetworkExportController;
use FrontPressMdExp\Rest\ExportController;
use FrontPressMdExp\Rest\InventoryController;
use FrontPressMdExp\Rest\SettingsController;

final class Plugin
{
    public const REST_NAMESPACE = 'fps-mdexp/v1';
    public const OPTION_KEY     = 'fps_mdexp_settings';
    public const MENU_SLUG      = 'fps-mdexp';

    public static function boot(): void
    {
        (new SettingsPage())->register();

        if (is_multisite()) {
            (new NetworkAdminPage())->register();
        }

        // Debug REST API responses for network export
        add_filter('rest_pre_echo_response', static function ($result, $server, $request) {
            $route = $request->get_route();
            if ($route && strpos($route, 'network/start') !== false) {
                error_log('REST API about to send response for ' . $route);
                error_log('Response type: ' . gettype($result));
                error_log('Response size: ' . strlen(json_encode($result)) . ' bytes');
                error_log('Response preview: ' . substr(json_encode($result), 0, 500));
            }
            return $result;
        }, 10, 3);

        // Debug after WordPress prepares the response
        add_filter('rest_post_dispatch', static function ($response, $server, $request) {
            $route = $request->get_route();
            if ($route && strpos($route, 'network/start') !== false) {
                error_log('REST post_dispatch - Response status: ' . $response->get_status());
                error_log('REST post_dispatch - Response headers: ' . json_encode($response->get_headers()));
                $data = $response->get_data();
                error_log('REST post_dispatch - Response data size: ' . strlen(json_encode($data)) . ' bytes');
                error_log('REST post_dispatch - Response data preview: ' . substr(json_encode($data), 0, 200));
            }
            return $response;
        }, 10, 3);

        // Catch any shutdown errors during network export
        register_shutdown_function(static function () {
            $error = error_get_last();
            if ($error !== null &&
                ($error['type'] === E_ERROR ||
                 $error['type'] === E_PARSE ||
                 $error['type'] === E_CORE_ERROR ||
                 $error['type'] === E_COMPILE_ERROR)) {

                // Check if this was during a network export request
                if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'network/start') !== false) {
                    error_log('FATAL ERROR during network export: ' . print_r($error, true));
                }
            }
        });

        add_action('rest_api_init', static function (): void {
            (new InventoryController())->register();
            (new SettingsController())->register();
            (new ExportController())->register();
            if (is_multisite()) {
                (new NetworkExportController())->register();
                (new AjaxHandler())->register();
            }
        });

        // Plugin action links for single site or subsite admin
        add_filter('plugin_action_links_' . plugin_basename(FPS_MDEXP_FILE), static function (array $links): array {
            $settingsLink = sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)),
                esc_html__('Settings', 'frontpress-md-exporter')
            );
            array_unshift($links, $settingsLink);
            return $links;
        });

        // Plugin action links for network admin (multisite)
        if (is_multisite()) {
            add_filter('network_admin_plugin_action_links_' . plugin_basename(FPS_MDEXP_FILE), static function (array $links): array {
                $networkLink = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(network_admin_url('admin.php?page=' . NetworkAdminPage::MENU_SLUG)),
                    esc_html__('Network Export', 'frontpress-md-exporter')
                );
                array_unshift($links, $networkLink);
                return $links;
            });
        }
    }
}
