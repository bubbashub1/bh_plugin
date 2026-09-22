<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class Schedule {
    public static function summary(int $post_id): string {
        $rows = Listing::get_schedule($post_id);
        if (!$rows) {
            return '';
        }

        $days = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $day = isset($row['day_name']) ? sanitize_text_field((string) $row['day_name']) : '';
            if ($day === '') {
                $day = isset($row['day']) ? sanitize_text_field((string) $row['day']) : '';
            }
            if ($day === '') {
                continue;
            }

            $closed = !empty($row['is_closed']);
            $sessions = isset($row['sessions']) && is_array($row['sessions']) ? $row['sessions'] : [];

            if ($closed) {
                $days[] = [$day, 'Closed'];
                continue;
            }

            $times = [];
            foreach ($sessions as $session) {
                if (!is_array($session)) {
                    continue;
                }
                $start = self::time($session['start'] ?? '');
                $end = self::time($session['end'] ?? '');
                if ($start && $end) {
                    $times[] = $start . '–' . $end;
                } elseif ($start) {
                    $times[] = $start;
                }
            }

            if ($times) {
                $days[] = [$day, implode(', ', $times)];
            }
        }

        if (!$days) {
            return '';
        }

        $items = '';
        foreach ($days as [$day, $times]) {
            $items .= '<li><span class="bh-directory-card__day">' . esc_html($day) . '</span><span class="bh-directory-card__times">' . esc_html($times) . '</span></li>';
        }

        return '<div class="bh-directory-card__schedule"><strong>Sessions</strong><ul>' . $items . '</ul></div>';
    }

    private static function time(mixed $value): string {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\\d{1,2}):(\\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return sanitize_text_field($value);
    }
}
