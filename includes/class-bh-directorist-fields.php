<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Read-only access to listing data owned by Directorist.
 *
 * Bubba Hub does not create a second source of truth for these fields.
 */
final class DirectoristFields {
    private const PRICE_META = '_price';
    private const AGE_RANGE_META = 'age_range';
    private const TERM_TIME_META = 'term_time';

    public static function get(int $post_id, string $field, mixed $default = null): mixed {
        if (!Listing::is_listing($post_id)) {
            return $default;
        }

        $meta_key = self::meta_key($field);
        if ($meta_key === '') {
            return $default;
        }

        $value = get_post_meta($post_id, $meta_key, true);

        return ($value === '' || $value === false || $value === null) ? $default : $value;
    }

    public static function meta_key(string $field): string {
        return match ($field) {
            'price' => self::PRICE_META,
            'age_range' => self::AGE_RANGE_META,
            'term_time' => self::TERM_TIME_META,
            default => '',
        };
    }

    public static function price_value(mixed $value): ?float {
        if ($value === '' || $value === null || is_array($value)) {
            return null;
        }

        $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public static function term_time_values(): array {
        return ['1', 'true', 'yes', 'on', 'term', 'term-time', 'term_time'];
    }
}
