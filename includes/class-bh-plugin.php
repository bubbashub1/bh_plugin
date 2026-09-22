<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class Plugin {
    private static ?self $instance = null;

    public static function boot(): void {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self();
        self::$instance->register();
    }

    private function register(): void {
        add_action('init', [$this, 'register_platform_types'], 20);
        add_action('admin_notices', [$this, 'dependency_notice']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_platform_types(): void {
        register_post_type('bh_venue', [
            'labels' => [
                'name' => __('Venues', 'bubba-hub'),
                'singular_name' => __('Venue', 'bubba-hub'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
        ]);

        register_post_type('bh_session', [
            'labels' => [
                'name' => __('Sessions', 'bubba-hub'),
                'singular_name' => __('Session', 'bubba-hub'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title'],
            'show_in_rest' => true,
        ]);
    }

    public function dependency_notice(): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        if (!defined('ATBDP_VERSION')) {
            echo '<div class="notice notice-warning"><p><strong>Bubba Hub:</strong> Directorist is required for the directory foundation. Please activate the free Directorist plugin.</p></div>';
        }
    }

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'bh-platform',
            BH_PLUGIN_URL . 'assets/css/bh-platform.css',
            [],
            BH_PLUGIN_VERSION
        );
    }
}
