<?php
/**
 * Plugin Name: Bubba Hub
 * Description: Family platform layer for Bubba Hub. Uses free Directorist for directory listings, search and listing management.
 * Version: 1.0.0
 * Author: Bubba Hub
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: bubba-hub
 */
defined('ABSPATH') || exit;

define('BH_PLUGIN_VERSION', '1.0.0');
define('BH_PLUGIN_FILE', __FILE__);
define('BH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once BH_PLUGIN_DIR . 'includes/class-bh-plugin.php';

add_action('plugins_loaded', static function () {
    \BubbaHub\Plugin::boot();
});
