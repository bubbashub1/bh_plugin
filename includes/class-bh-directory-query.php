<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Read-only directory query helpers.
 *
 * This class intentionally delegates actual searching to WordPress/Directorist
 * rather than registering another directory engine.
 */
final class DirectoryQuery {
    public static function args(array $filters = []): array {
        $args = [
            'post_type' => Listing::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => isset($filters['posts_per_page']) ? max(1, min(100, (int) $filters['posts_per_page'])) : 20,
            'paged' => isset($filters['paged']) ? max(1, (int) $filters['paged']) : 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $tax_query = [];
        $meta_query = [];

        foreach (['category','region','town','day','term_time'] as $key) {
            if (!empty($filters[$key])) {
                $taxonomy = self::taxonomy_for($key);
                if ($taxonomy) {
                    $tax_query[] = [
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => array_map('sanitize_title', (array) $filters[$key]),
                    ];
                }
            }
        }

        foreach (['age_range','price'] as $key) {
            if ($filters[$key] ?? null !== null && $filters[$key] !== '') {
                $meta_query[] = [
                    'key' => sanitize_key($key),
                    'value' => sanitize_text_field((string) $filters[$key]),
                    'compare' => '=',
                ];
            }
        }

        if ($tax_query) $args['tax_query'] = $tax_query;
        if ($meta_query) $args['meta_query'] = $meta_query;

        return $args;
    }

    private static function taxonomy_for(string $key): ?string {
        $map = [
            'category' => 'at_biz_dir-category',
            'region' => 'at_biz_dir-location',
        ];
        return $map[$key] ?? null;
    }

    public static function run(array $filters = []): \WP_Query {
        if (!Listing::is_available()) {
            return new \WP_Query(['post__in' => [0]]);
        }
        return new \WP_Query(self::args($filters));
    }
}
