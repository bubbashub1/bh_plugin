<?php
namespace BubbaHub;
defined('ABSPATH') || exit;

final class DirectoryCard {
    public static function meta(int $post_id): array {
        $category = wp_get_post_terms(
            $post_id,
            defined('ATBDP_CATEGORY') ? ATBDP_CATEGORY : 'at_biz_dir-category',
            ['fields' => 'names']
        );

        return [
            'category' => !is_wp_error($category) ? (string) reset($category) : '',
            'location' => self::location_names($post_id),
            'age_range' => (string) DirectoryFields::get($post_id, 'age_range', ''),
            'price' => (string) DirectoryFields::get($post_id, 'price', ''),
        ];
    }

    /**
     * Read the listing's assigned locations directly from Directorist.
     *
     * Directorist defines its listing location taxonomy as ATBDP_LOCATION.
     * This keeps the Bubba Hub card aligned with Directorist's own location
     * source instead of maintaining a separate location field or mapping.
     */
    private static function location_names(int $post_id): string {
        $taxonomy = defined('ATBDP_LOCATION') ? ATBDP_LOCATION : 'at_biz_dir-location';
        $locations = get_the_terms($post_id, $taxonomy);

        if (is_wp_error($locations) || empty($locations)) {
            return '';
        }

        $names = [];

        foreach ($locations as $location) {
            if (!($location instanceof \WP_Term)) {
                continue;
            }

            $name = trim((string) $location->name);

            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    public static function age_label(string $value): string {
        return $value === 'all' ? 'All ages' : $value;
    }
}
