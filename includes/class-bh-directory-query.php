<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Read-only directory query helpers.
 *
 * Directorist remains the single source of truth for listings and location.
 * Location filtering uses Directorist's hierarchical location taxonomy.
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

        if (!empty($filters['category'])) {
            $tax_query[] = [
                'taxonomy' => 'at_biz_dir-category',
                'field' => 'slug',
                'terms' => array_map('sanitize_title', (array) $filters['category']),
            ];
        }

        if (!empty($filters['region'])) {
            $tax_query[] = [
                'taxonomy' => 'at_biz_dir-location',
                'field' => 'slug',
                'terms' => array_map('sanitize_title', (array) $filters['region']),
                'include_children' => true,
            ];
        }

        if (!empty($filters['town'])) {
            $tax_query[] = [
                'taxonomy' => 'at_biz_dir-location',
                'field' => 'slug',
                'terms' => array_map('sanitize_title', (array) $filters['town']),
                'include_children' => false,
            ];
        }

        $schedule_ids = self::schedule_matches($filters);
        if ($schedule_ids !== null) {
            $args['post__in'] = $schedule_ids ?: [0];
        }

        foreach (['age_range', 'price'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $meta_query[] = [
                    'key' => sanitize_key($key),
                    'value' => sanitize_text_field((string) $filters[$key]),
                    'compare' => '=',
                ];
            }
        }

        if ($tax_query) {
            $args['tax_query'] = ['relation' => 'AND', ...$tax_query];
        }
        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    private static function schedule_matches(array $filters): ?array {
        $day = sanitize_text_field((string) ($filters['day'] ?? ''));
        $term = sanitize_key((string) ($filters['term_time'] ?? ''));
        if ($day === '' && $term === '') return null;
        if (!in_array($term, ['', 'term'], true)) return null;
        $ids = get_posts([
            'post_type' => Listing::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        $matches = [];
        foreach ($ids as $id) {
            if (Schedule::matches((int) $id, $day, $term)) $matches[] = (int) $id;
        }
        return $matches;
    }

    public static function run(array $filters = []): \WP_Query {
        if (!Listing::is_available()) {
            return new \WP_Query(['post__in' => [0]]);
        }

        return new \WP_Query(self::args($filters));
    }
}
