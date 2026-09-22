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

        if (!$post_id || !Listing::is_listing((int) $post_id) || !Listing::get_schedule((int) $post_id)) {
            return;
        }

        $rows = Listing::get_schedule((int) $post_id);

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html__('Weekly Schedule', 'bubba-hub') . $args['after_title'];
        echo '<div class="bh-weekly-schedule-widget">';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $day = (string) ($row['day_name'] ?? $row['day'] ?? '');
            if ($day === '') {
                continue;
            }

            $day = ucwords(strtolower(sanitize_text_field($day)));
            $closed = !empty($row['is_closed']);
            $sessions = isset($row['sessions']) && is_array($row['sessions']) ? $row['sessions'] : [];

            echo '<div class="bh-weekly-schedule-widget__day">';
            echo '<div class="bh-weekly-schedule-widget__day-name">' . esc_html($day) . '</div>';

            if ($closed) {
                echo '<div class="bh-weekly-schedule-widget__closed">' . esc_html__('Closed', 'bubba-hub') . '</div>';
            } elseif ($sessions) {
                echo '<div class="bh-weekly-schedule-widget__sessions">';

                foreach ($sessions as $session) {
                    if (!is_array($session)) {
                        continue;
                    }

                    $start = self::format_time($session['start_time'] ?? $session['start'] ?? '');
                    $end = self::format_time($session['end_time'] ?? $session['end'] ?? '');
                    $label = sanitize_text_field((string) ($session['session_label'] ?? ''));

                    if (!$start && !$end && !$label) {
                        continue;
                    }

                    echo '<div class="bh-weekly-schedule-widget__session">';
                    echo '<span class="bh-weekly-schedule-widget__label">' . esc_html($label !== '' ? $label : __('Session', 'bubba-hub')) . '</span>';
                    if ($start || $end) {
                        echo '<span class="bh-weekly-schedule-widget__time">' . esc_html($start && $end ? $start . '–' . $end : ($start ?: $end)) . '</span>';
                    }
                    echo '</div>';
                }

                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>';
        echo $args['after_widget'];
    }

    public function form($instance): void {
        echo '<p>' . esc_html__('This widget automatically displays the weekly schedule for the current Directorist listing.', 'bubba-hub') . '</p>';
    }

    public function update($new_instance, $old_instance): array {
        return [];
    }

    private static function format_time(mixed $value): string {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return sanitize_text_field($value);
    }
}
