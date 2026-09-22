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
            'age_range' => isset($_GET['bh_age_range']) ? sanitize_text_field(wp_unslash($_GET['bh_age_range'])) : '',
            'region' => isset($_GET['bh_region']) ? sanitize_title(wp_unslash($_GET['bh_region'])) : '',
            'town' => isset($_GET['bh_town']) ? sanitize_title(wp_unslash($_GET['bh_town'])) : '',
            'category' => isset($_GET['bh_category']) ? sanitize_title(wp_unslash($_GET['bh_category'])) : '',
            'price' => isset($_GET['bh_price']) ? sanitize_text_field(wp_unslash($_GET['bh_price'])) : '',
        ];

        $args = [
            'posts_per_page' => 12,
            'paged' => max(1, (int) ($_GET['bh_page'] ?? 1)),
        ];

        foreach ($values as $key => $value) {
            if ($value !== '') $args[$key] = $value;
        }

        $query = DirectoryQuery::run($args);
        $regions = self::top_level_locations();
        $towns = $values['region'] !== '' ? self::child_locations($values['region']) : [];

        ob_start();
        ?>
        <section class="bh-directory-search" aria-label="Find family activities">
            <form class="bh-directory-search__form" method="get">
                <div class="bh-directory-search__field">
                    <label for="bh-age-range">Age Range</label>
                    <select id="bh-age-range" name="bh_age_range">
                        <option value="">Any age</option>
                        <?php foreach (DirectoryFields::definitions()['age_range']['values'] as $age) : ?>
                            <option value="<?php echo esc_attr($age); ?>" <?php selected($values['age_range'], $age); ?>><?php echo esc_html($age === 'all' ? 'All ages' : $age); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-directory-search__field">
                    <label for="bh-category">Category</label>
                    <select id="bh-category" name="bh_category">
                        <option value="">All categories</option>
                        <?php $categories = get_terms(['taxonomy' => 'at_biz_dir-category', 'hide_empty' => true]); ?>
                        <?php if (!is_wp_error($categories)) foreach ($categories as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($values['category'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-directory-search__field">
                    <label for="bh-region">Region</label>
                    <select id="bh-region" name="bh_region">
                        <option value="">All regions</option>
                        <?php foreach ($regions as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($values['region'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-directory-search__field">
                    <label for="bh-town">Town</label>
                    <select id="bh-town" name="bh_town" <?php disabled($values['region'], ''); ?>>
                        <option value=""><?php echo $values['region'] === '' ? 'Select a region first' : 'All towns'; ?></option>
                        <?php foreach ($towns as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($values['town'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-directory-search__field">
                    <label for="bh-price">Price</label>
                    <input id="bh-price" name="bh_price" type="text" value="<?php echo esc_attr($values['price']); ?>" placeholder="e.g. £5">
                </div>
                <div class="bh-directory-search__actions">
                    <button type="submit">Search</button>
                    <a href="<?php echo esc_url(remove_query_arg(['bh_age_range','bh_category','bh_region','bh_town','bh_price','bh_page'])); ?>">Clear</a>
                </div>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function top_level_locations(): array {
        $terms = get_terms([
            'taxonomy' => self::LOCATION_TAXONOMY,
            'hide_empty' => true,
            'parent' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    private static function child_locations(string $region_slug): array {
        $region = get_term_by('slug', sanitize_title($region_slug), self::LOCATION_TAXONOMY);
        if (!$region || is_wp_error($region)) return [];

        $terms = get_terms([
            'taxonomy' => self::LOCATION_TAXONOMY,
            'hide_empty' => true,
            'parent' => (int) $region->term_id,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }
}
