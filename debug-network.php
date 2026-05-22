<?php
/**
 * Debug script for FrontPress MD Exporter Network Admin
 *
 * Add this at the top of wp-config.php to enable:
 * define('FPS_MDEXP_DEBUG', true);
 */

if (!defined('FPS_MDEXP_DEBUG') || !FPS_MDEXP_DEBUG) {
    return;
}

add_action('admin_notices', function() {
    if (!is_network_admin()) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'fps-mdexp') === false) {
        return;
    }

    echo '<div class="notice notice-warning">';
    echo '<h3>FrontPress MD Exporter Debug Info</h3>';

    // Check constants
    echo '<p><strong>Constants:</strong></p>';
    echo '<ul>';
    echo '<li>FPS_MDEXP_FILE: ' . (defined('FPS_MDEXP_FILE') ? '✅ ' . FPS_MDEXP_FILE : '❌ Not defined') . '</li>';
    echo '<li>FPS_MDEXP_PATH: ' . (defined('FPS_MDEXP_PATH') ? '✅ ' . FPS_MDEXP_PATH : '❌ Not defined') . '</li>';
    echo '<li>FPS_MDEXP_URL: ' . (defined('FPS_MDEXP_URL') ? '✅ ' . FPS_MDEXP_URL : '❌ Not defined') . '</li>';
    echo '<li>FPS_MDEXP_VERSION: ' . (defined('FPS_MDEXP_VERSION') ? '✅ ' . FPS_MDEXP_VERSION : '❌ Not defined') . '</li>';
    echo '</ul>';

    // Check if files exist
    echo '<p><strong>Asset Files:</strong></p>';
    echo '<ul>';
    $jsPath = FPS_MDEXP_PATH . 'dist/network.js';
    $cssPath = FPS_MDEXP_PATH . 'dist/network.css';
    echo '<li>network.js: ' . (file_exists($jsPath) ? '✅ Exists (' . size_format(filesize($jsPath)) . ')' : '❌ Missing') . '</li>';
    echo '<li>network.css: ' . (file_exists($cssPath) ? '✅ Exists (' . size_format(filesize($cssPath)) . ')' : '❌ Missing') . '</li>';
    echo '</ul>';

    // Check enqueued scripts
    global $wp_scripts;
    echo '<p><strong>Enqueued Scripts:</strong></p>';
    echo '<ul>';
    echo '<li>fps-mdexp-network: ' . (isset($wp_scripts->registered['fps-mdexp-network']) ? '✅ Registered' : '❌ Not registered') . '</li>';
    if (isset($wp_scripts->registered['fps-mdexp-network'])) {
        $script = $wp_scripts->registered['fps-mdexp-network'];
        echo '<li>Script src: ' . esc_html($script->src) . '</li>';
        if (!empty($script->extra['data'])) {
            echo '<li>Localized data: <pre>' . esc_html($script->extra['data']) . '</pre></li>';
        }
    }
    echo '</ul>';

    // Check current screen
    echo '<p><strong>Current Screen:</strong></p>';
    echo '<ul>';
    echo '<li>Screen ID: ' . esc_html($screen->id) . '</li>';
    echo '<li>Base: ' . esc_html($screen->base) . '</li>';
    echo '<li>Is Network Admin: ' . (is_network_admin() ? '✅ Yes' : '❌ No') . '</li>';
    echo '</ul>';

    // Check if is multisite
    echo '<p><strong>Multisite:</strong></p>';
    echo '<ul>';
    echo '<li>is_multisite(): ' . (is_multisite() ? '✅ Yes' : '❌ No') . '</li>';
    echo '<li>Network ID: ' . get_current_network_id() . '</li>';
    echo '<li>Site ID: ' . get_current_blog_id() . '</li>';
    echo '</ul>';

    echo '</div>';
});
