<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class WeeklyScheduleWidget extends \WP_Widget {
    public function __construct() {
        parent::__construct(
            'bh_weekly_schedule',
            __('Bubba Hub – Weekly Schedule', 'bubba-hub'),
            [
                'description' => __('Displays the current Directorist listing weekly schedule.', 'bubba-hub'),
            ]
        );
    }

    public function widget($args, $instance): void {
        $post_id = get_queried_object_id();

        if (!$post_id || !Listing::is_listing((int) $post_id)) {
            return;
        }

        $schedule = Schedule::summary((int) $post_id);

        if ($schedule === '') {
            return;
        }

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html__('Weekly Schedule', 'bubba-hub') . $args['after_title'];
        echo $schedule;
        echo $args['after_widget'];
    }

    public function form($instance): void {
        echo '<p>' . esc_html__('This widget automatically displays the weekly schedule for the current Directorist listing.', 'bubba-hub') . '</p>';
    }

    public function update($new_instance, $old_instance): array {
        return [];
    }
}
