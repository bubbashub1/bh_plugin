<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class DirectorySearch {
    private const LOCATION_TAXONOMY = 'at_biz_dir-location';

    public static function register(): void {
        add_shortcode('bh_directory_search', [self::class, 'shortcode']);
    }

    public static function shortcode(): string {
        $values = [
            'search' => isset($_GET['bh_search']) ? sanitize_text_field(wp_unslash($_GET['bh_search'])) : '',
            'category' => isset($_GET['bh_category']) ? sanitize_title(wp_unslash($_GET['bh_category'])) : '',
            'age_range' => isset($_GET['bh_age_range']) ? sanitize_text_field(wp_unslash($_GET['bh_age_range'])) : '',
            'location' => isset($_GET['bh_location']) ? sanitize_title(wp_unslash($_GET['bh_location'])) : '',
            'day' => isset($_GET['bh_day']) ? sanitize_title(wp_unslash($_GET['bh_day'])) : '',
            'price' => isset($_GET['bh_price']) ? sanitize_text_field(wp_unslash($_GET['bh_price'])) : '',
            'free_activity' => isset($_GET['bh_free_activity']) ? sanitize_text_field(wp_unslash($_GET['bh_free_activity'])) : '',
        ];

        $args = [
            'posts_per_page' => 12,
            'paged' => max(1, (int) ($_GET['bh_page'] ?? 1)),
        ];

        foreach ($values as $key => $value) {
            if ($value !== '') $args[$key] = $value;
        }

        $query = DirectoryQuery::run($args);
        $categories = self::categories();
        $locations = self::locations();
        $age_options = DirectoristFields::options('age_range');
        $free_activity_option = DirectoristFields::free_activity_option();

        ob_start();
        ?>
        <section class="bh-directory-search" aria-label="Find family activities">
            <form class="bh-directory-search__form" method="get">
                <div class="bh-directory-search__field bh-directory-search__field--search">
                    <label for="bh-search">Search</label>
                    <input id="bh-search" name="bh_search" type="search" value="<?php echo esc_attr($values['search']); ?>" placeholder="Search activities">
                </div>

                <div class="bh-directory-search__field">
                    <label for="bh-category">Category</label>
                    <select id="bh-category" name="bh_category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($values['category'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bh-directory-search__field">
                    <label for="bh-region-main">Region</label>
                    <select id="bh-region-main" name="bh_region">
                        <option value="">All regions</option>
                        <?php foreach ($regions as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($values['region'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bh-directory-search__field">
                    <label for="bh-town-main">Town</label>
                    <select id="bh-town-main" name="bh_town" <?php disabled($values['region'], ''); ?>>
                        <option value=""><?php echo $values['region'] === '' ? 'Select a region first' : 'All towns'; ?></option>
                        <?php foreach ($towns as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($values['town'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($free_activity_option) : ?>
                    <div class="bh-directory-search__field bh-directory-search__field--checkbox">
                        <label for="bh-free-activity">
                            <input id="bh-free-activity" name="bh_free_activity" type="checkbox" value="<?php echo esc_attr($free_activity_option['value']); ?>" <?php checked($values['free_activity'], $free_activity_option['value']); ?>>
                            Free Activity
                        </label>
                    </div>
                <?php endif; ?>

                <details class="bh-directory-search__advanced" <?php echo ($values['age_range'] !== '' || $values['day'] !== '' || $values['price'] !== '') ? 'open' : ''; ?>>
                    <summary>Advanced Search</summary>
                    <div class="bh-directory-search__advanced-grid">
                        <div class="bh-directory-search__field">
                            <label for="bh-age-range">Age Range</label>
                            <select id="bh-age-range" name="bh_age_range">
                                <option value="">Any age</option>
                                <?php foreach ($age_options as $option) : ?>
                                    <option value="<?php echo esc_attr($option['value']); ?>" <?php selected($values['age_range'], $option['value']); ?>><?php echo esc_html($option['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="bh-directory-search__field">
                            <label for="bh-day">Day</label>
                            <select id="bh-day" name="bh_day">
                                <option value="">Any day</option>
                                <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day) : ?>
                                    <option value="<?php echo esc_attr($day); ?>" <?php selected($values['day'], $day); ?>><?php echo esc_html(ucfirst($day)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                                        <div class="bh-directory-search__field">
                            <label for="bh-price">Price</label>
                            <input id="bh-price" name="bh_price" type="text" value="<?php echo esc_attr($values['price']); ?>" placeholder="e.g. £5">
                        </div>

                        <div class="bh-directory-search__actions">
                            <button type="submit">Search</button>
                            <a href="<?php echo esc_url(remove_query_arg(['bh_search','bh_category','bh_age_range','bh_location','bh_day','bh_price','bh_free_activity','bh_page'])); ?>">Clear</a>
                        </div>
                    </div>
                </details>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function categories(): array {
        $terms = get_terms([
            'taxonomy' => 'at_biz_dir-category',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    private static function locations(): array {
        $terms = get_terms([
            'taxonomy' => self::LOCATION_TAXONOMY,
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

