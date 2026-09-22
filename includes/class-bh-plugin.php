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
        add_action('admin_notices', [$this, 'dependency_notice']);
    }

    public function dependency_notice(): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        if (!Directorist::is_available()) {
            echo '<div class="notice notice-warning"><p><strong>Bubba Hub:</strong> Free Directorist is required for the directory foundation. Please activate Directorist before using Bubba Hub directory features.</p></div>';
        }
    }
}
