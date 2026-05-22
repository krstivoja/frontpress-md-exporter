<?php
/**
 * Plugin Name: FrontPress MD Exporter
 * Description: Export this WordPress site to mdframework-compatible Markdown files (with front matter, taxonomies, and media) as a downloadable zip.
 * Version: 0.2.2
 * Author: Marko Krstić
 * License: GPL-2.0-or-later
 * Text Domain: frontpress-md-exporter
 * Requires at least: 5.8
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FPS_MDEXP_FILE', __FILE__);
define('FPS_MDEXP_PATH', plugin_dir_path(__FILE__));
define('FPS_MDEXP_URL', plugin_dir_url(__FILE__));
define('FPS_MDEXP_VERSION', '0.2.2');

require_once __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', static function (): void {
    \FrontPressMdExp\Plugin::boot();
});
