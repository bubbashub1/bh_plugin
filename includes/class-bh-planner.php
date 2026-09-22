<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class Planner {
    private const VIEWS = ['daily', 'weekly', 'monthly'];
    private const DAY_NAMES = [
        'sunday' => 0, 'sun' => 0,
        'monday' => 1, 'mon' => 1,
        'tuesday' => 2, 'tue' => 2, 'tues' => 2,
        'wednesday' => 3, 'wed' => 3,
        'thursday' => 4, 'thu' => 4, 'thur' => 4, 'thurs' => 4,
        'friday' => 5, 'fri' => 5,
        'saturday' => 6, 'sat' => 6,
    ];

    public static function view(): string {
        $view = isset($_GET['bh_view']) ? sanitize_key(wp_unslash($_GET['bh_view'])) : 'grid';
        return in_array($view, ['grid', 'list', 'map', ...self::VIEWS], true) ? $view : 'grid';
    }

    public static function is_calendar_view(string $view): bool {
        return in_array($view, self::VIEWS, true);
    }

    public static function date(): \DateTimeImmutable {
        $raw = isset($_GET['bh_date']) ? sanitize_text_field(wp_unslash($_GET['bh_date'])) : '';
        $timezone = wp_timezone();

        if ($raw !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $timezone);
            if ($date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $raw) {
                return $date;
            }
        }

        return current_datetime()->setTime(0, 0);
    }

    public static function range(string $view, ?\DateTimeImmutable $date = null): array {
        $date = $date ?: self::date();

        if ($view === 'daily') {
            return [$date->setTime(0, 0), $date->setTime(0, 0)];
        }

        if ($view === 'monthly') {
            $first = $date->modify('first day of this month')->setTime(0, 0);
            $last = $date->modify('last day of this month')->setTime(0, 0);
            $start = $first->modify('monday this week')->setTime(0, 0);
            $end = $last->modify('sunday this week')->setTime(0, 0);
            return [$start, $end];
        }

        $start = $date->modify('monday this week')->setTime(0, 0);
        return [$start, $start->modify('+6 days')->setTime(0, 0)];
    }

    public static function title(string $view, \DateTimeImmutable $date): string {
        if ($view === 'daily') {
            return wp_date('l, j F Y', $date->getTimestamp(), wp_timezone());
        }

        if ($view === 'monthly') {
            return wp_date('F Y', $date->getTimestamp(), wp_timezone());
        }

        [$start, $end] = self::range('weekly', $date);
        return wp_date('j M', $start->getTimestamp(), wp_timezone()) . ' – ' .
            wp_date('j M Y', $end->getTimestamp(), wp_timezone());
    }

    public static function navigation_url(string $view, \DateTimeImmutable $date, int $offset = 0): string {
        $target = $date;
        if ($offset !== 0) {
            $target = match ($view) {
                'daily' => $date->modify(($offset > 0 ? '+' : '') . $offset . ' days'),
                'monthly' => $date->modify(($offset > 0 ? '+' : '') . $offset . ' months')->modify('first day of this month'),
                default => $date->modify(($offset > 0 ? '+' : '') . ($offset * 7) . ' days'),
            };
        }

        $args = [
            'bh_view' => $view,
            'bh_date' => $target->format('Y-m-d'),
            'bh_page' => false,
        ];

        return add_query_arg($args);
    }

    public static function view_url(string $view): string {
        return add_query_arg([
            'bh_view' => $view,
            'bh_date' => false,
            'bh_page' => false,
        ]);
    }

    public static function events(\WP_Query $query, \DateTimeImmutable $start, \DateTimeImmutable $end): array {
        $events = [];

        foreach ($query->posts as $post) {
            $post_id = (int) $post->ID;
            $rows = Listing::get_schedule($post_id);
            if (!$rows) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row) || !empty($row['is_closed'])) {
                    continue;
                }

                $day = self::day_number($row['day_name'] ?? $row['day'] ?? '');
                if ($day === null) {
                    continue;
                }

                $recurrence_start = self::parse_date($row['recurrence_start_date'] ?? '');
                $recurrence_end = self::parse_date($row['recurrence_end_date'] ?? '');
                $frequency = strtolower(trim((string) ($row['frequency'] ?? 'weekly')));
                $sessions = isset($row['sessions']) && is_array($row['sessions']) ? $row['sessions'] : [];

                for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
                    if ((int) $date->format('w') !== $day) {
                        continue;
                    }

                    if ($recurrence_start && $date < $recurrence_start) {
                        continue;
                    }
                    if ($recurrence_end && $date > $recurrence_end) {
                        continue;
                    }

                    if (!self::matches_frequency($date, $recurrence_start, $frequency)) {
                        continue;
                    }

                    foreach ($sessions as $session) {
                        if (!is_array($session)) {
                            continue;
                        }

                        $start_minutes = self::minutes($session['start_time'] ?? $session['start'] ?? '');
                        $end_minutes = self::minutes($session['end_time'] ?? $session['end'] ?? '');
                        if ($start_minutes === null) {
                            continue;
                        }
                        if ($end_minutes === null || $end_minutes <= $start_minutes) {
                            $end_minutes = min(1439, $start_minutes + 60);
                        }

                        $label = sanitize_text_field((string) ($session['session_label'] ?? $session['label'] ?? ''));
                        $events[] = [
                            'post_id' => $post_id,
                            'date' => $date->format('Y-m-d'),
                            'start' => $start_minutes,
                            'end' => $end_minutes,
                            'time' => self::format_minutes($start_minutes) . '–' . self::format_minutes($end_minutes),
                            'title' => get_the_title($post_id),
                            'label' => $label,
                            'url' => get_permalink($post_id),
                        ];
                    }
                }
            }
        }

        usort($events, static function (array $a, array $b): int {
            return [$a['date'], $a['start'], $a['title']] <=> [$b['date'], $b['start'], $b['title']];
        });

        return $events;
    }

    public static function render(string $view, \WP_Query $query): string {
        if (!self::is_calendar_view($view)) {
            return '';
        }

        $date = self::date();
        [$range_start, $range_end] = self::range($view, $date);
        $events = self::events($query, $range_start, $range_end);

        if ($view === 'monthly') {
            return self::render_month($range_start, $range_end, $events, $date);
        }

        return self::render_time_grid($view, $range_start, $range_end, $events);
    }

    private static function render_time_grid(string $view, \DateTimeImmutable $start, \DateTimeImmutable $end, array $events): string {
        $days = [];
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $days[] = $date;
        }

        $day_count = count($days);
        $start_hour = 6;
        $end_hour = 22;
        $slot_minutes = 30;
        $slots = (($end_hour - $start_hour) * 60) / $slot_minutes;

        $by_day = [];
        foreach ($events as $event) {
            $by_day[$event['date']][] = $event;
        }

        ob_start();
        ?>
        <div class="bh-planner bh-planner--<?php echo esc_attr($view); ?>">
            <div class="bh-planner__week-head" style="--bh-columns:<?php echo esc_attr((string) $day_count); ?>">
                <div class="bh-planner__time-head"></div>
                <?php foreach ($days as $day) : ?>
                    <div class="bh-planner__day-head<?php echo $day->format('Y-m-d') === current_datetime()->format('Y-m-d') ? ' is-today' : ''; ?>">
                        <span><?php echo esc_html(wp_date('D', $day->getTimestamp(), wp_timezone())); ?></span>
                        <strong><?php echo esc_html(wp_date('j', $day->getTimestamp(), wp_timezone())); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bh-planner__time-grid" style="--bh-columns:<?php echo esc_attr((string) $day_count); ?>;--bh-slots:<?php echo esc_attr((string) $slots); ?>">
                <div class="bh-planner__time-axis">
                    <?php for ($hour = $start_hour; $hour <= $end_hour; $hour++) : ?>
                        <?php if ($hour < $end_hour) : ?>
                            <span style="--bh-time-position:<?php echo esc_attr((string) (($hour - $start_hour) * 2)); ?>"><?php echo esc_html(wp_date('g a', mktime($hour, 0, 0))); ?></span>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php foreach ($days as $day) : ?>
                    <?php $date_key = $day->format('Y-m-d'); ?>
                    <div class="bh-planner__day-column<?php echo $date_key === current_datetime()->format('Y-m-d') ? ' is-today' : ''; ?>">
                        <?php for ($slot = 0; $slot < $slots; $slot++) : ?>
                            <span class="bh-planner__slot-line<?php echo $slot % 2 === 0 ? ' is-hour' : ''; ?>" style="--bh-slot:<?php echo esc_attr((string) $slot); ?>"></span>
                        <?php endfor; ?>
                        <?php foreach (($by_day[$date_key] ?? []) as $event) : ?>
                            <?php
                            $top = (($event['start'] - ($start_hour * 60)) / 30) * (100 / $slots);
                            $height = max(3, (($event['end'] - $event['start']) / 30) * (100 / $slots));
                            $top = max(0, min(100, $top));
                            $height = max(2.5, min(100 - $top, $height));
                            ?>
                            <a class="bh-planner__event" href="<?php echo esc_url($event['url']); ?>" style="--bh-event-top:<?php echo esc_attr((string) $top); ?>%;--bh-event-height:<?php echo esc_attr((string) $height); ?>%;">
                                <strong><?php echo esc_html($event['title']); ?></strong>
                                <span><?php echo esc_html($event['time']); ?></span>
                                <?php if ($event['label']) : ?><small><?php echo esc_html($event['label']); ?></small><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_month(\DateTimeImmutable $start, \DateTimeImmutable $end, array $events, \DateTimeImmutable $selected): string {
        $by_day = [];
        foreach ($events as $event) {
            $by_day[$event['date']][] = $event;
        }

        ob_start();
        ?>
        <div class="bh-planner bh-planner--monthly">
            <div class="bh-planner__month-head">
                <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $label) : ?>
                    <span><?php echo esc_html($label); ?></span>
                <?php endforeach; ?>
            </div>
            <div class="bh-planner__month-grid">
                <?php for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) : ?>
                    <?php $date_key = $date->format('Y-m-d'); $outside = $date->format('m') !== $selected->format('m'); ?>
                    <div class="bh-planner__month-day<?php echo $outside ? ' is-outside' : ''; ?><?php echo $date_key === current_datetime()->format('Y-m-d') ? ' is-today' : ''; ?>">
                        <span class="bh-planner__month-number"><?php echo esc_html($date->format('j')); ?></span>
                        <div class="bh-planner__month-events">
                            <?php foreach (array_slice($by_day[$date_key] ?? [], 0, 4) as $event) : ?>
                                <a href="<?php echo esc_url($event['url']); ?>" class="bh-planner__month-event">
                                    <strong><?php echo esc_html($event['title']); ?></strong>
                                    <span><?php echo esc_html($event['time']); ?></span>
                                </a>
                            <?php endforeach; ?>
                            <?php if (count($by_day[$date_key] ?? []) > 4) : ?>
                                <span class="bh-planner__more">+<?php echo esc_html((string) (count($by_day[$date_key]) - 4)); ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function day_number(mixed $day): ?int {
        $key = sanitize_key((string) $day);
        return array_key_exists($key, self::DAY_NAMES) ? self::DAY_NAMES[$key] : null;
    }

    private static function parse_date(mixed $value): ?\DateTimeImmutable {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        foreach (['Y-m-d', 'Ymd', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }

    private static function matches_frequency(\DateTimeImmutable $date, ?\DateTimeImmutable $start, string $frequency): bool {
        if (!$start || $start > $date) {
            return true;
        }

        if (str_contains($frequency, 'biweek') || str_contains($frequency, 'fortnight')) {
            $days = (int) $start->diff($date)->format('%a');
            return ($days % 14) === 0;
        }

        return true;
    }

    private static function minutes(mixed $value): ?int {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})\s*([ap]m)?$/i', $value, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $ampm = strtolower($m[3] ?? '');
            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }
            return ($hour >= 0 && $hour <= 23 && $minute <= 59) ? ($hour * 60 + $minute) : null;
        }

        return null;
    }

    private static function format_minutes(int $minutes): string {
        $hour = intdiv($minutes, 60);
        $minute = $minutes % 60;
        $suffix = $hour >= 12 ? 'pm' : 'am';
        $display_hour = $hour % 12 ?: 12;
        return sprintf('%d:%02d%s', $display_hour, $minute, $suffix);
    }
}
