<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Safe bridge between Bubba Hub and Directorist listings.
 *
 * This class never registers a second listing post type and never modifies
 * Directorist core. It only provides a stable way for Bubba Hub modules to
 * identify Directorist listings and read optional ACF data attached to them.
 */
final class Listing {
    public const POST_TYPE = 'at_biz_dir';

    public static function is_available(): bool {
        return post_type_exists(self::POST_TYPE);
    }

    public static function is_listing(int $post_id): bool {
        return $post_id > 0 && get_post_type($post_id) === self::POST_TYPE;
    }

    /**
     * Read an ACF value without making ACF a hard dependency.
     */
    public static function get_field(string $field_name, int $post_id = 0, mixed $default = null): mixed {
        if (!function_exists('get_field')) {
            return $default;
        }

        $value = get_field($field_name, $post_id ?: false);
        return ($value === null || $value === false || $value === '') ? $default : $value;
    }

    /**
     * Read the current Bubba Hub timetable field, supporting the existing
     * field-name variants during migration.
     */
    public static function get_schedule(int $post_id): array {
        if (!self::is_listing($post_id)) {
            return [];
        }

        $schedule = self::get_field('weekly_schedule', $post_id, null);

        if (!is_array($schedule)) {
            $schedule = self::get_field('group_business_hours_repeater', $post_id, []);
        }

        return is_array($schedule) ? $schedule : [];
    }

    /**
     * Common field aliases from the original directory configuration.
     */
    public static function field_aliases(): array {
        return [
            'age_range'      => ['age_range'],
            'region'         => ['region'],
            'town'           => ['town', 'location'],
            'category'       => ['category'],
            'day'            => ['day', 'day_name'],
            'price'          => ['price'],
            'session_length' => ['session_length'],
            'latitude'       => ['lat', 'latitude'],
            'longitude'      => ['long', 'longitude'],
        ];
    }
}
