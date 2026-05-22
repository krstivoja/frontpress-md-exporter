<?php

declare(strict_types=1);

namespace FrontPressMdExp\Admin;

use FrontPressMdExp\Plugin;

final class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        add_menu_page(
            __('MD Export', 'frontpress-md-exporter'),
            __('MD Export', 'frontpress-md-exporter'),
            'manage_options',
            Plugin::MENU_SLUG,
            [$this, 'render'],
            'dashicons-download',
            81
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . Plugin::MENU_SLUG) {
            return;
        }

        wp_enqueue_script(
            'fps-mdexp-admin',
            FPS_MDEXP_URL . 'dist/index.js',
            [],
            FPS_MDEXP_VERSION,
            true
        );

        if (file_exists(FPS_MDEXP_PATH . 'dist/index.css')) {
            wp_enqueue_style(
                'fps-mdexp-admin',
                FPS_MDEXP_URL . 'dist/index.css',
                [],
                FPS_MDEXP_VERSION
            );
        }

        wp_localize_script('fps-mdexp-admin', 'fpsExporter', [
            'restRoot' => esc_url_raw(rest_url(Plugin::REST_NAMESPACE . '/')),
            'nonce'    => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('FrontPress MD Exporter', 'frontpress-md-exporter') . '</h1>';
        echo '<div id="fps-mdexp-root"></div></div>';
    }
}
