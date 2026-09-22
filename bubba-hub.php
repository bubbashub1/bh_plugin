<?php
/**
 * Plugin Name: Bubba Hub
 * Description: Family platform layer for Bubba Hub. Uses free Directorist for directory listings, search and listing management.
 * Version: 1.1.0
 * Author: Bubba Hub
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: bubba-hub
 */
defined('ABSPATH') || exit;

if (!defined('BH_PLUGIN_VERSION')) {
    define('BH_PLUGIN_VERSION', '1.1.0');
}
if (!defined('BH_PLUGIN_FILE')) {
    define('BH_PLUGIN_FILE', __FILE__);
}
if (!defined('BH_PLUGIN_DIR')) {
    define('BH_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('BH_PLUGIN_URL')) {
    define('BH_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once BH_PLUGIN_DIR . 'includes/class-bh-directorist.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-listing.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-directory-fields.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-directory-query.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-directory.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-directory-search.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-directorist-search-config.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-schedule.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-directory-card.php';
require_once BH_PLUGIN_DIR . 'includes/class-bh-plugin.php';

add_action('plugins_loaded', static function (): void {
    \BubbaHub\Plugin::boot();
    \BubbaHub\Directory::register();
    \BubbaHub\DirectorySearch::register();
}, 20);
