<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class ListingInfoWidget extends \WP_Widget {
    public function __construct() {
        parent::__construct(
            'bh_listing_info',
            __('Bubba Hub – Listing Information', 'bubba-hub'),
            ['description' => __('Displays key Directorist information for the current listing.', 'bubba-hub')]
        );
    }

    public function widget($args, $instance): void {
        $post_id = get_queried_object_id();
        if (!$post_id || !Listing::is_listing((int) $post_id)) {
            return;
        }

        $meta = DirectoryCard::meta((int) $post_id);
        $items = [];

        if ($meta['location'] !== '') $items[] = ['label' => __('Location', 'bubba-hub'), 'value' => $meta['location']];
        if ($meta['age_range'] !== '') $items[] = ['label' => __('Age range', 'bubba-hub'), 'value' => DirectoryCard::age_label($meta['age_range'])];
        if ($meta['price'] !== '') $items[] = ['label' => __('Price', 'bubba-hub'), 'value' => $meta['price']];

        if (!$items) return;

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html__('Activity Information', 'bubba-hub') . $args['after_title'];
        echo '<div class="bh-listing-info-widget">';

        foreach ($items as $item) {
            echo '<div class="bh-listing-info-widget__item">';
            echo '<span class="bh-listing-info-widget__label">' . esc_html($item['label']) . '</span>';
            echo '<span class="bh-listing-info-widget__value">' . esc_html($item['value']) . '</span>';
            echo '</div>';
        }

        echo '</div>';
        echo $args['after_widget'];
    }

    public function form($instance): void {
        echo '<p>' . esc_html__('This widget automatically displays key information from Directorist.', 'bubba-hub') . '</p>';
    }

    public function update($new_instance, $old_instance): array {
        return [];
    }
}
