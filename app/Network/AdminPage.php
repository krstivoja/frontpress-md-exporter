<?php

declare(strict_types=1);

namespace FrontPressMdExp\Network;

use FrontPressMdExp\Plugin;

final class AdminPage
{
    public const MENU_SLUG = 'fps-mdexp-network';

    public function register(): void
    {
        add_action('network_admin_menu', [$this, 'addMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        add_menu_page(
            __('MD Export (Network)', 'frontpress-md-exporter'),
            __('MD Export', 'frontpress-md-exporter'),
            'manage_network_options',
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-download',
            81
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::MENU_SLUG . '-network') {
            return;
        }

        wp_enqueue_script(
            'fps-mdexp-network',
            FPS_MDEXP_URL . 'dist/network.js',
            [],
            FPS_MDEXP_VERSION,
            true
        );

        if (file_exists(FPS_MDEXP_PATH . 'dist/network.css')) {
            wp_enqueue_style(
                'fps-mdexp-network',
                FPS_MDEXP_URL . 'dist/network.css',
                [],
                FPS_MDEXP_VERSION
            );
        }

        wp_localize_script('fps-mdexp-network', 'fpsExporter', [
            'restRoot' => esc_url_raw(rest_url(Plugin::REST_NAMESPACE . '/')),
            'nonce'    => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('FrontPress MD Network Exporter', 'frontpress-md-exporter') . '</h1>';
        echo '<div id="fps-mdexp-network-root"></div></div>';
    }
}
