<?php
namespace BubbaHub;
defined('ABSPATH') || exit;
final class DirectoryCard {
    public static function meta(int $post_id): array {
        $category = wp_get_post_terms($post_id, 'at_biz_dir-category', ['fields' => 'names']);
        return [
            'category' => !is_wp_error($category) ? (string) reset($category) : '',
            'location' => self::location_names($post_id),
            'age_range' => (string) DirectoryFields::get($post_id, 'age_range', ''),
            'price' => (string) DirectoryFields::get($post_id, 'price', ''),
        ];
    }

    private static function location_names(int $post_id): string {
        $terms = wp_get_post_terms($post_id, 'at_biz_dir-location', ['orderby' => 'parent', 'order' => 'ASC']);
        if (!is_wp_error($terms) && $terms) {
            $names = [];
            foreach ($terms as $term) {
                $name = trim((string) $term->name);
                if ($name !== '' && !in_array($name, $names, true)) $names[] = $name;
            }
            if ($names) return implode(', ', $names);
        }
        foreach (['_address', 'address'] as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (is_string($value) && trim($value) !== '') return trim($value);
        }
        return '';
    }

    public static function age_label(string $value): string {
        return $value === 'all' ? 'All ages' : $value;
    }
}