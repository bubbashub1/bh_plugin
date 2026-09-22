<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Read-only directory query helpers.
 *
 * Directorist remains the single source of truth for listings, location,
 * category, age range, term time and price. ACF is used only for timetable
 * day matching and timetable presentation.
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

        $search = trim(sanitize_text_field((string) ($filters['search'] ?? '')));
        $post_in = null;

        if ($search !== '') {
            $post_in = self::search_listing_ids($search);
        }

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

        $day_ids = self::schedule_day_matches($filters);
        if ($day_ids !== null) {
            $post_in = $post_in === null
                ? $day_ids
                : array_values(array_intersect($post_in, $day_ids));
        }

        if ($post_in !== null) {
            $args['post__in'] = $post_in ?: [0];
        }

        $age_range = sanitize_text_field((string) ($filters['age_range'] ?? ''));
        if ($age_range !== '') {
            $meta_query[] = [
                'key' => DirectoristFields::meta_key('age_range'),
                'value' => $age_range,
                'compare' => '=',
            ];
        }

        $price = DirectoristFields::price_value($filters['price'] ?? '');
        if ($price !== null) {
            $meta_query[] = [
                'key' => DirectoristFields::meta_key('price'),
                'value' => (string) $price,
                'compare' => '=',
                'type' => 'DECIMAL',
            ];
        }

        $term_time = sanitize_key((string) ($filters['term_time'] ?? ''));
        if ($term_time !== '') {
            $meta_query[] = [
                'key' => DirectoristFields::meta_key('term_time'),
                'value' => DirectoristFields::term_time_values(),
                'compare' => 'IN',
            ];
        }

        if ($tax_query) {
            $args['tax_query'] = ['relation' => 'AND', ...$tax_query];
        }
        if ($meta_query) {
            $args['meta_query'] = ['relation' => 'AND', ...$meta_query];
        }

        return $args;
    }

    private static function search_listing_ids(string $search): array {
        $ids = get_posts([
            'post_type' => Listing::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            's' => $search,
            'no_found_rows' => true,
        ]);

        foreach (['at_biz_dir-category', 'at_biz_dir-location'] as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'search' => $search,
                'number' => 50,
            ]);

            if (is_wp_error($terms) || !$terms) {
                continue;
            }

            $term_ids = array_map('intval', wp_list_pluck($terms, 'term_id'));
            if (!$term_ids) {
                continue;
            }

            $term_ids_posts = get_posts([
                'post_type' => Listing::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'tax_query' => [[
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_ids,
                    'include_children' => true,
                ]],
            ]);

            $ids = array_merge($ids, $term_ids_posts);
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private static function schedule_day_matches(array $filters): ?array {
        $day = sanitize_text_field((string) ($filters['day'] ?? ''));
        if ($day === '') {
            return null;
        }

        $ids = get_posts([
            'post_type' => Listing::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $matches = [];
        foreach ($ids as $id) {
            if (Schedule::matches_day((int) $id, $day)) {
                $matches[] = (int) $id;
            }
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
