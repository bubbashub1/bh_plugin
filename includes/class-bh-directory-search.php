<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class DirectorySearch {
    private const LOCATION_TAXONOMY = 'at_biz_dir-location';

    public static function register(): void {
        add_shortcode('bh_directory_search', [self::class, 'shortcode']);
        add_shortcode('bh_saved_searches', [self::class, 'saved_searches_shortcode']);
        add_action('wp_ajax_bh_get_towns', [self::class, 'ajax_towns']);
        add_action('wp_ajax_nopriv_bh_get_towns', [self::class, 'ajax_towns']);
        add_action('init', [self::class, 'handle_saved_search']);
        add_action('init', [self::class, 'handle_delete_saved_search']);
    }


    public static function handle_saved_search(): void {
        if (!is_user_logged_in() || empty($_POST['bh_saved_search_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['bh_saved_search_action']));
        if ($action !== 'save') {
            return;
        }

        check_admin_referer('bh_save_directory_search', 'bh_saved_search_nonce');

        $name = sanitize_text_field(wp_unslash($_POST['bh_saved_search_name'] ?? ''));
        if ($name === '') {
            $name = 'Saved search';
        }

        // Build the saved URL from the current site request rather than trusting
        // the hidden form URL. This keeps the saved search on this site and
        // preserves the active filters, planner view and selected date.
        $allowed = ['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_day','bh_price','bh_free_activity','bh_view','bh_date'];
        $query = [];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $value = wp_unslash($_POST[$key]);
                $query[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
            } elseif (isset($_GET[$key])) {
                $value = wp_unslash($_GET[$key]);
                $query[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
            }
        }
        $query = array_filter($query, static function ($value): bool {
            return is_array($value) ? !empty($value) : trim((string) $value) !== '';
        });
        $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $saved_url = home_url($path);
        if ($query) {
            $saved_url = add_query_arg($query, $saved_url);
        }

        $saved = get_user_meta(get_current_user_id(), '_bh_saved_searches', true);
        if (!is_array($saved)) {
            $saved = [];
        }

        $id = wp_generate_uuid4();
        $saved[$id] = [
            'name' => $name,
            'url' => $saved_url,
            'created' => current_time('mysql'),
        ];

        if (count($saved) > 25) {
            $saved = array_slice($saved, -25, 25, true);
        }

        update_user_meta(get_current_user_id(), '_bh_saved_searches', $saved);
        wp_safe_redirect(add_query_arg('bh_saved', '1', self::saved_searches_url()));
        exit;
    }

    public static function saved_searches_url(): string {
        // Resolve the actual page containing the saved-searches shortcode first.
        // This avoids WordPress nested-page redirects (for example to /groups/).
        $pages = get_pages([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'number'      => 100,
        ]);

        foreach ($pages as $page) {
            if ($page instanceof \WP_Post && has_shortcode((string) $page->post_content, 'bh_saved_searches')) {
                $permalink = get_permalink($page);
                if ($permalink) {
                    return (string) $permalink;
                }
            }
        }

        // Fallback to the expected nested page path.
        $page = get_page_by_path('my-hub/saved-searches');
        if ($page instanceof \WP_Post) {
            return (string) get_permalink($page);
        }

        return home_url('/my-hub/saved-searches/');
    }
    public static function handle_delete_saved_search(): void {
        if (!is_user_logged_in() || empty($_POST['bh_delete_saved_search'])) {
            return;
        }

        check_admin_referer('bh_delete_saved_search', 'bh_delete_saved_search_nonce');
        $id = sanitize_text_field(wp_unslash($_POST['bh_delete_saved_search']));
        $saved = get_user_meta(get_current_user_id(), '_bh_saved_searches', true);

        if (is_array($saved) && isset($saved[$id])) {
            unset($saved[$id]);
            update_user_meta(get_current_user_id(), '_bh_saved_searches', $saved);
        }

        wp_safe_redirect(self::saved_searches_url());
        exit;
    }

    public static function saved_searches_shortcode(): string {
        if (!is_user_logged_in()) {
            $url = function_exists('um_get_core_page') ? um_get_core_page('login') : wp_login_url(get_permalink());
            return '<div class="bh-saved-searches bh-saved-searches--login"><h1>Saved Searches</h1><p>Please log in to view your saved searches.</p><a class="bh-saved-searches__button" href="' . esc_url($url) . '">Log in</a></div>';
        }

        wp_enqueue_style(
            'bh-saved-searches',
            BH_PLUGIN_URL . 'assets/css/bh-saved-searches.css',
            [],
            BH_PLUGIN_VERSION
        );

        $saved = get_user_meta(get_current_user_id(), '_bh_saved_searches', true);
        if (!is_array($saved)) {
            $saved = [];
        }

        ob_start();
        ?>
        <section class="bh-saved-searches">
            <div class="bh-saved-searches__intro">
                <div>
                    <p class="bh-saved-searches__eyebrow">My Bubba Hub</p>
                    <h1>Saved Searches</h1>
                    <p>Keep your favourite group searches here and reopen them whenever you need them.</p>
                </div>
                <a class="bh-saved-searches__back" href="<?php echo esc_url(home_url('/my-hub/')); ?>">← My Hub</a>
            </div>

            <?php if (isset($_GET['bh_saved'])): ?>
                <div class="bh-saved-searches__notice" role="status">Search saved. You can run it whenever you like.</div>
            <?php endif; ?>

            <?php if ($saved): ?>
                <div class="bh-saved-searches__list">
                    <?php foreach ($saved as $search_id => $item): ?>
                        <?php
                        $name = is_array($item) ? (string) ($item['name'] ?? 'Saved search') : 'Saved search';
                        $url = is_array($item) ? (string) ($item['url'] ?? '') : '';
                        $criteria = self::saved_search_criteria($url);
                        ?>
                        <article class="bh-saved-searches__card">
                            <div class="bh-saved-searches__card-content">
                                <h2><?php echo esc_html($name); ?></h2>
                                <?php if ($criteria): ?>
                                    <dl class="bh-saved-searches__criteria">
                                        <?php foreach ($criteria as $label => $value): ?>
                                            <div>
                                                <dt><?php echo esc_html($label); ?>:</dt>
                                                <dd><?php echo esc_html($value); ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php else: ?>
                                    <p class="bh-saved-searches__empty-criteria">No filters were saved with this search.</p>
                                <?php endif; ?>
                            </div>
                            <div class="bh-saved-searches__actions">
                                <?php if ($url): ?>
                                    <a class="bh-saved-searches__button" href="<?php echo esc_url($url); ?>">Re-run Search</a>
                                <?php endif; ?>
                                <form method="post" class="bh-saved-searches__delete">
                                    <?php wp_nonce_field('bh_delete_saved_search', 'bh_delete_saved_search_nonce'); ?>
                                    <input type="hidden" name="bh_delete_saved_search" value="<?php echo esc_attr($search_id); ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bh-saved-searches__empty">
                    <strong>No saved searches yet</strong>
                    <p>Run a search in the Bubba Hub directory and save it to see it here.</p>
                    <a class="bh-saved-searches__button" href="<?php echo esc_url(home_url('/directory/')); ?>">Browse the directory</a>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function saved_search_criteria(string $url): array {
        if ($url === '') {
            return [];
        }

        $parts = wp_parse_url($url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $criteria = [];
        $map = [
            'bh_search' => 'Search',
            'bh_age_range' => 'Age range',
            'bh_category' => 'Category',
            'bh_region' => 'Region',
            'bh_town' => 'Town',
            'bh_day' => 'Day',
            'bh_price' => 'Price',
        ];

        foreach ($map as $key => $label) {
            if (!isset($query[$key]) || is_array($query[$key]) || trim((string) $query[$key]) === '') {
                continue;
            }
            $value = sanitize_text_field((string) $query[$key]);
            if ($key === 'bh_age_range') {
                $value = self::age_range_label($value);
            } elseif ($key === 'bh_category') {
                $value = self::term_label($value, 'at_biz_dir-category');
            } elseif ($key === 'bh_region' || $key === 'bh_town') {
                $value = self::term_label($value, self::LOCATION_TAXONOMY);
            } elseif ($key === 'bh_day') {
                $value = ucfirst($value);
            }
            if ($value !== '') {
                $criteria[$label] = $value;
            }
        }

        if (!empty($query['bh_free_activity'])) {
            $criteria['Free activity'] = 'Yes';
        }

        return $criteria;
    }

    private static function term_label(string $slug, string $taxonomy): string {
        $term = get_term_by('slug', sanitize_title($slug), $taxonomy);
        return $term && !is_wp_error($term) ? $term->name : ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    private static function age_range_label(string $value): string {
        foreach (DirectoristFields::options('age_range') as $option) {
            if ((string) ($option['value'] ?? '') === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        $labels = [
            '0-3' => '0–3 months',
            '3-6' => '3–6 months',
            '6-9' => '6–9 months',
            '9-12' => '9–12 months',
            '1-3' => '1–3 years',
            '2-4' => '2–4 years',
            '3-5' => '3–5 years',
            '5-plus' => '5+ years',
            'all' => 'All ages',
        ];
        return $labels[$value] ?? ucwords(str_replace(['-', '_'], ' ', $value));
    }

    public static function ajax_towns(): void {
        check_ajax_referer('bh_directory_search', 'nonce');

        $region = isset($_POST['region']) ? sanitize_title(wp_unslash($_POST['region'])) : '';
        $towns = self::towns($region);

        wp_send_json_success(array_map(static function ($term): array {
            return [
                'value' => $term->slug,
                'label' => $term->name,
            ];
        }, $towns));
    }

    public static function shortcode(): string {
        $values = [
            'search' => isset($_GET['bh_search']) ? sanitize_text_field(wp_unslash($_GET['bh_search'])) : '',
            'category' => isset($_GET['bh_category']) ? sanitize_title(wp_unslash($_GET['bh_category'])) : '',
            'age_range' => isset($_GET['bh_age_range']) ? sanitize_text_field(wp_unslash($_GET['bh_age_range'])) : '',
            'region' => isset($_GET['bh_region']) ? sanitize_title(wp_unslash($_GET['bh_region'])) : '',
            'town' => isset($_GET['bh_town']) ? sanitize_title(wp_unslash($_GET['bh_town'])) : '',
            'day' => isset($_GET['bh_day']) ? sanitize_title(wp_unslash($_GET['bh_day'])) : '',
            'price' => isset($_GET['bh_price']) ? sanitize_text_field(wp_unslash($_GET['bh_price'])) : '',
            'free_activity' => isset($_GET['bh_free_activity']) ? sanitize_text_field(wp_unslash($_GET['bh_free_activity'])) : '',
        ];

        $args = [
            'posts_per_page' => 12,
            'paged' => max(1, (int) ($_GET['bh_page'] ?? 1)),
        ];

        foreach ($values as $key => $value) {
            if ($value !== '') {
                $args[$key] = $value;
            }
        }

        DirectoryQuery::run($args);
        $categories = self::categories();
        $regions = self::regions();
        $towns = self::towns($values['region']);
        $age_options = DirectoristFields::options('age_range');
        $free_activity_option = DirectoristFields::free_activity_option();

        ob_start();
        ?>
        <section class="bh-directory-search" aria-label="Find family activities">
            <form class="bh-directory-search__form" method="get">
                <input type="hidden" name="bh_view" value="<?php echo esc_attr(isset($_GET['bh_view']) ? sanitize_key(wp_unslash($_GET['bh_view'])) : ''); ?>">
                <input type="hidden" name="bh_date" value="<?php echo esc_attr(isset($_GET['bh_date']) ? sanitize_text_field(wp_unslash($_GET['bh_date'])) : ''); ?>">
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
                    <select id="bh-town" name="bh_town" <?php disabled($values["region"], ""); ?>>
                        <option value="">Select a region first</option>
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
                            <a href="<?php echo esc_url(remove_query_arg(['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_day','bh_price','bh_free_activity','bh_page'])); ?>">Clear</a>
                        </div>
                    </div>
                </details>

            </form>

            <?php if (is_user_logged_in()) : ?>
                <?php $save_url = remove_query_arg('bh_page'); ?>
                <div class="bh-directory-search__save">
                    <div class="bh-directory-search__save-intro">
                        <strong>Save this search</strong>
                        <a href="<?php echo esc_url(self::saved_searches_url()); ?>">View Saved Searches</a>
                    </div>
                    <form method="post" class="bh-directory-search__save-form">
                        <?php wp_nonce_field('bh_save_directory_search', 'bh_saved_search_nonce'); ?>
                        <input type="hidden" name="bh_saved_search_action" value="save">
                        <input type="hidden" name="bh_saved_search_url" value="<?php echo esc_attr($save_url); ?>">
                        <?php foreach (['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_day','bh_price','bh_free_activity','bh_view','bh_date'] as $saved_key) : ?>
                            <?php if (isset($_GET[$saved_key])) : ?>
                                <input type="hidden" name="<?php echo esc_attr($saved_key); ?>" value="<?php echo esc_attr(is_array($_GET[$saved_key]) ? '' : sanitize_text_field(wp_unslash($_GET[$saved_key]))); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <input id="bh-saved-search-name" name="bh_saved_search_name" type="text" maxlength="80" placeholder="Name this search" required>
                        <button type="submit">Save</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function my_hub_url(): string {
        $page = get_page_by_path('my-hub');
        return $page instanceof \WP_Post ? get_permalink($page) : home_url('/my-hub/');
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

    private static function regions(): array {
        $terms = get_terms([
            'taxonomy' => self::LOCATION_TAXONOMY,
            'hide_empty' => false,
            'parent' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    private static function towns(string $region = ''): array {
        $region = sanitize_title($region);

        if ($region === '') {
            return [];
        }

        $parent = get_term_by('slug', $region, self::LOCATION_TAXONOMY);

        if (!$parent || is_wp_error($parent)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => self::LOCATION_TAXONOMY,
            'hide_empty' => false,
            'parent' => (int) $parent->term_id,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }
}
