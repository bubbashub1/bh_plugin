<?php
namespace BubbaHub;
defined('ABSPATH') || exit;
final class DirectoryCard {
    public static function meta(int $post_id): array {
        $category = wp_get_post_terms($post_id, 'at_biz_dir-category', ['fields' => 'names']);
        $location = wp_get_post_terms($post_id, 'at_biz_dir-location', ['fields' => 'names']);
        return [
            'category' => !is_wp_error($category) ? (string) reset($category) : '',
            'location' => !is_wp_error($location) ? (string) reset($location) : '',
            'age_range' => (string) DirectoryFields::get($post_id, 'age_range', ''),
            'price' => (string) DirectoryFields::get($post_id, 'price', ''),
        ];
    }
    public static function age_label(string $value): string {
        return $value === 'all' ? 'All ages' : $value;
    }
}
