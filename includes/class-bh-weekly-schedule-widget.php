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
        $current = current_datetime();
        $current_day = strtolower($current->format('l'));
        $current_minutes = ((int) $current->format('G') * 60) + (int) $current->format('i');

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
            $day_slug = strtolower(sanitize_title($day));
            $is_today = ($day_slug === $current_day);
            $is_open_now = false;

            if ($is_today && !$closed) {
                foreach ($sessions as $session_check) {
                    if (!is_array($session_check)) {
                        continue;
                    }
                    $check_start = self::minutes($session_check['start_time'] ?? $session_check['start'] ?? '');
                    $check_end = self::minutes($session_check['end_time'] ?? $session_check['end'] ?? '');
                    if ($check_start !== null && $check_end !== null && $current_minutes >= $check_start && $current_minutes < $check_end) {
                        $is_open_now = true;
                        break;
                    }
                }
            }

            echo '<details class="bh-weekly-schedule-widget__day' . ($is_today ? ' is-today' : '') . '" open>';
            echo '<summary class="bh-weekly-schedule-widget__day-heading">';
            echo '<span class="bh-weekly-schedule-widget__day-name">' . esc_html($day) . '</span>';
            if ($is_today) {
                echo '<span class="bh-weekly-schedule-widget__status ' . ($is_open_now ? 'is-open' : 'is-closed') . '">';
                echo '<span class="bh-weekly-schedule-widget__status-dot" aria-hidden="true"></span>';
                echo esc_html($is_open_now ? __('Open now', 'bubba-hub') : __('Closed now', 'bubba-hub'));
                echo '</span>';
            }
            echo '</summary>';

            echo '<div class="bh-weekly-schedule-widget__content">';
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
            echo '</details>';
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

    private static function minutes(mixed $value): ?int {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
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
