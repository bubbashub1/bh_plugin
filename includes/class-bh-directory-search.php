<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class DirectorySearch {
    public static function register(): void {
        add_shortcode('bh_directory_search', [self::class, 'shortcode']);
    }

    public static function shortcode(): string {
        $values = [
            'age_range' => isset($_GET['bh_age_range']) ? sanitize_text_field(wp_unslash($_GET['bh_age_range'])) : '',
            'region' => isset($_GET['bh_region']) ? sanitize_title(wp_unslash($_GET['bh_region'])) : '',
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
                        <option value="">All locations</option>
                        <?php $regions = get_terms(['taxonomy' => 'at_biz_dir-location', 'hide_empty' => true]); ?>
                        <?php if (!is_wp_error($regions)) foreach ($regions as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($values['region'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-directory-search__field">
                    <label for="bh-price">Price</label>
                    <input id="bh-price" name="bh_price" type="text" value="<?php echo esc_attr($values['price']); ?>" placeholder="e.g. £5">
                </div>
                <div class="bh-directory-search__actions">
                    <button type="submit">Search</button>
                    <a href="<?php echo esc_url(remove_query_arg(['bh_age_range','bh_category','bh_region','bh_price','bh_page'])); ?>">Clear</a>
                </div>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
