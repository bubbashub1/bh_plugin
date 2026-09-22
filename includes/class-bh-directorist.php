<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class Directorist {
    public static function is_available(): bool {
        return defined('ATBDP_VERSION');
    }

    public static function version(): string {
        return self::is_available() ? (string) ATBDP_VERSION : '';
    }
}
