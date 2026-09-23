<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class Directory {
    public static function register(): void {
        add_shortcode('bh_directory', [self::class, 'shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_bh_calendar_ics', [Planner::class, 'export_ics']);
        add_action('admin_post_nopriv_bh_calendar_ics', [Planner::class, 'export_ics']);
    }

    public static function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'posts_per_page' => 12,
            'title' => 'Find Family Activities',
            'columns' => 3,
        ], $atts, 'bh_directory');

        if (!Listing::is_available()) {
            return '<div class="bh-directory bh-directory--notice"><p>Directory temporarily unavailable. Please activate Directorist.</p></div>';
        }

        $view = Planner::view();
        $filters = self::filters();
        $filters['posts_per_page'] = self::per_page($atts['posts_per_page']);
        $columns = self::columns($atts['columns']);
        $filters['paged'] = max(1, (int) ($_GET['bh_page'] ?? 1));

        if (Planner::is_calendar_view($view)) {
            $filters['all_results'] = true;
            $filters['paged'] = 1;
        }

        $query = DirectoryQuery::run($filters);

        ob_start();
        ?>
        <section class="bh-directory bh-directory--view-<?php echo esc_attr($view); ?>" aria-label="<?php echo esc_attr($atts['title']); ?>">
            <header class="bh-directory__header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
            </header>

            <?php echo DirectorySearch::shortcode(); ?>

            <nav class="bh-directory-views" aria-label="Activity views">
                <?php foreach ([
                    'list' => 'List',
                    'map' => 'Map',
                    'grid' => 'Grid',
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                ] as $key => $label) : ?>
                    <a class="bh-directory-views__item<?php echo $view === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url(Planner::view_url($key)); ?>"<?php echo $view === $key ? ' aria-current="page"' : ''; ?>>
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (Planner::is_calendar_view($view)) : ?>
                <section class="bh-planner-shell" data-bh-planner aria-label="<?php echo esc_attr(ucfirst($view)); ?> activity planner">
                    <div class="bh-planner-shell__heading">
                        <div>
                            <h3><?php echo esc_html(ucfirst($view)); ?> planner</h3>
                            <p class="bh-planner-shell__date"><?php echo esc_html(Planner::title($view, Planner::date())); ?></p>
                            <p class="bh-directory__count"><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?> <?php echo esc_html((int) $query->found_posts === 1 ? 'activity' : 'activities'); ?> found</p>
                        </div>
                        <div class="bh-planner-shell__navigation" aria-label="Calendar navigation">
                            <a href="<?php echo esc_url(Planner::navigation_url($view, Planner::date(), -1)); ?>" aria-label="Previous <?php echo esc_attr($view); ?>">‹</a>
                            <a href="<?php echo esc_url(Planner::navigation_url($view, current_datetime()->setTime(0, 0), 0)); ?>">Today</a>
                            <a href="<?php echo esc_url(Planner::navigation_url($view, Planner::date(), 1)); ?>" aria-label="Next <?php echo esc_attr($view); ?>">›</a>
                        </div>
                    </div>
                    

                    <?php echo Planner::render($view, $query); ?>

                    <?php
                    $calendar_export_args = [
                        'action' => 'bh_calendar_ics',
                        'bh_view' => $view,
                        'bh_date' => Planner::date()->format('Y-m-d'),
                        'bh_search' => $filters['search'],
                        'bh_category' => $filters['category'],
                        'bh_age_range' => $filters['age_range'],
                        'bh_region' => $filters['region'],
                        'bh_town' => $filters['town'],
                        'bh_day' => $filters['day'],
                        'bh_price' => $filters['price'],
                        'bh_free_activity' => $filters['free_activity'],
                    ];
                    $calendar_export_url = add_query_arg($calendar_export_args, admin_url('admin-post.php'));
                    ?>
                    <div class="bh-planner-actions" aria-label="Calendar actions">
                        <a class="bh-planner-actions__button" href="<?php echo esc_url($calendar_export_url); ?>" download>Subscribe</a>
                        <button class="bh-planner-actions__button" type="button" data-bh-print-calendar>Print</button>
                    </div>
                    <div class="bh-print-footer" aria-hidden="true">
                        <span>www.bubbahub.co.uk</span>
                        <span><?php echo esc_html(wp_date('j F Y', current_datetime()->getTimestamp(), wp_timezone())); ?></span>
                    </div>
                </section>
            <?php else : ?>
                <div class="bh-directory__header bh-directory__header--controls">
                    <p class="bh-directory__count"><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?> <?php echo esc_html((int) $query->found_posts === 1 ? 'activity' : 'activities'); ?> found</p>
                    <?php if ($view !== 'map') : ?>
                        <form class="bh-directory-listing-controls" method="get" aria-label="Listing display options">
                            <?php self::hidden_listing_params(); ?>
                            <label><span>Sort by</span><select name="bh_sort" onchange="this.form.submit()"><?php foreach (self::sort_options() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters['sort'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                            <label><span>Per page</span><select name="bh_per_page" onchange="this.form.submit()"><?php foreach ([6,12,18,24,36,48] as $number) : ?><option value="<?php echo esc_attr($number); ?>" <?php selected($filters['posts_per_page'], $number); ?>><?php echo esc_html($number); ?></option><?php endforeach; ?></select></label>
                            <?php if ($view === 'grid') : ?>
                                <label><span>Columns</span><select name="bh_columns" onchange="this.form.submit()"><?php foreach ([1,2,3,4,5] as $number) : ?><option value="<?php echo esc_attr($number); ?>" <?php selected($columns, $number); ?>><?php echo esc_html($number); ?></option><?php endforeach; ?></select></label>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($view === 'map') : ?>
                    <?php echo self::render_map($query); ?>
                <?php elseif ($query->have_posts()) : ?>
                    <div class="bh-directory__grid<?php echo $view === 'list' ? ' bh-directory__grid--list' : ''; ?> bh-directory__grid--columns-<?php echo esc_attr($columns); ?>">
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <?php $card = DirectoryCard::meta((int) get_the_ID()); ?>
                            <article class="bh-directory-card">
                                <a class="bh-directory-card__link" href="<?php the_permalink(); ?>">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <div class="bh-directory-card__image"><?php the_post_thumbnail('medium'); ?></div>
                                    <?php else : ?>
                                        <div class="bh-directory-card__image bh-directory-card__image--placeholder" aria-hidden="true">Bubba Hub</div>
                                    <?php endif; ?>
                                    <div class="bh-directory-card__body">
                                        <div class="bh-directory-card__meta">
                                            <?php if ($card['category']) : ?><span class="bh-directory-card__badge"><?php echo esc_html($card['category']); ?></span><?php endif; ?>
                                        </div>
                                        <h3><?php the_title(); ?></h3>
                                        <div class="bh-directory-card__details">
                                            <?php if ($card['location']) : ?><div class="bh-directory-card__detail"><span class="bh-directory-card__detail-label">Location:</span><span><?php echo esc_html($card['location']); ?></span></div><?php endif; ?>
                                            <?php if ($card['age_range']) : ?><div class="bh-directory-card__detail"><span class="bh-directory-card__detail-label">Age:</span><span><?php echo esc_html(DirectoryCard::age_label($card['age_range'])); ?></span></div><?php endif; ?>
                                            <?php if ($card['price']) : ?><div class="bh-directory-card__detail"><span class="bh-directory-card__detail-label">Price:</span><span><?php echo esc_html($card['price']); ?></span></div><?php endif; ?>
                                        </div>
                                        <?php echo Schedule::summary((int) get_the_ID()); ?>
                                        <span class="bh-directory-card__cta">View activity</span>
                                    </div>
                                </a>
                            </article>
                        <?php endwhile; ?>
                    </div>

                    <?php
                    $current_page = max(1, (int) ($_GET['bh_page'] ?? 1));
                    $base_url = remove_query_arg('bh_page');
                    $pagination = paginate_links([
                        'base' => esc_url_raw(add_query_arg('bh_page', '%#%', $base_url)),
                        'format' => '',
                        'current' => $current_page,
                        'total' => max(1, (int) $query->max_num_pages),
                        'type' => 'list',
                    ]);
                    if ($pagination) {
                        echo '<nav class="bh-directory__pagination" aria-label="Directory pages">' . wp_kses_post($pagination) . '</nav>';
                    }
                    ?>
                <?php else : ?>
                    <p class="bh-directory__empty">No family activities found.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    private static function filters(): array {
        return [
            'search' => isset($_GET['bh_search']) ? sanitize_text_field(wp_unslash($_GET['bh_search'])) : '',
            'category' => isset($_GET['bh_category']) ? sanitize_title(wp_unslash($_GET['bh_category'])) : '',
            'age_range' => isset($_GET['bh_age_range']) ? sanitize_text_field(wp_unslash($_GET['bh_age_range'])) : '',
            'region' => isset($_GET['bh_region']) ? sanitize_title(wp_unslash($_GET['bh_region'])) : '',
            'town' => isset($_GET['bh_town']) ? sanitize_title(wp_unslash($_GET['bh_town'])) : '',
            'saved_location' => isset($_GET['bh_saved_location']) ? sanitize_title(wp_unslash($_GET['bh_saved_location'])) : '',
            'day' => isset($_GET['bh_day']) ? sanitize_title(wp_unslash($_GET['bh_day'])) : '',
            'price' => isset($_GET['bh_price']) ? sanitize_text_field(wp_unslash($_GET['bh_price'])) : '',
            'free_activity' => isset($_GET['bh_free_activity']) ? sanitize_text_field(wp_unslash($_GET['bh_free_activity'])) : '',
            'sort' => isset($_GET['bh_sort']) ? sanitize_key(wp_unslash($_GET['bh_sort'])) : 'latest',
        ];
    }

    private static function sort_options(): array {
        return [
            'az' => 'A to Z',
            'za' => 'Z to A',
            'latest' => 'Latest listings',
            'oldest' => 'Oldest listings',
            'popular' => 'Popular listings',
            'price_low' => 'Price (low to high)',
            'price_high' => 'Price (high to low)',
            'random' => 'Random listings',
        ];
    }

    private static function per_page($default): int {
        $value = isset($_GET['bh_per_page']) ? absint($_GET['bh_per_page']) : absint($default);
        return in_array($value, [6,12,18,24,36,48], true) ? $value : 12;
    }

    private static function columns($default): int {
        $value = isset($_GET['bh_columns']) ? absint($_GET['bh_columns']) : absint($default);
        return in_array($value, [1,2,3,4,5], true) ? $value : 3;
    }

    private static function hidden_listing_params(): void {
        foreach (['bh_search','bh_category','bh_age_range','bh_region','bh_town','bh_day','bh_price','bh_free_activity','bh_view','bh_date'] as $key) {
            if (isset($_GET[$key]) && !is_array($_GET[$key])) {
                echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET[$key]))) . '">';
            }
        }
        echo '<input type="hidden" name="bh_page" value="1">';
    }

    private static function render_map(\WP_Query $query): string {
        if (!$query->have_posts()) {
            return '<p class="bh-directory__empty">No family activities found.</p>';
        }

        if (!shortcode_exists('directorist_all_listing')) {
            return '<div class="bh-directory--notice"><p>Map view is not available from Directorist on this site.</p></div>';
        }

        $ids = wp_list_pluck($query->posts, 'ID');
        $ids = array_filter(array_map('absint', $ids));
        if (!$ids) {
            return '<p class="bh-directory__empty">No family activities found.</p>';
        }

        $output = do_shortcode(sprintf(
            '[directorist_all_listing view="map" ids="%s" directory_type="groups-classes" header="no" advanced_filter="no" show_pagination="no" listings_per_page="%d"]',
            esc_attr(implode(',', $ids)),
            count($ids)
        ));

        return $output !== '' ? '<div class="bh-directory-map">' . $output . '</div>' : '<div class="bh-directory--notice"><p>Map view is not available from Directorist on this site.</p></div>';
    }

    public static function assets(): void {
        if (!is_singular()) {
            return;
        }
        wp_register_style('bh-directory', BH_PLUGIN_URL . 'assets/css/bh-directory.css', [], BH_PLUGIN_VERSION);
        wp_enqueue_style('bh-directory');
        wp_register_style('bh-directory-search', BH_PLUGIN_URL . 'assets/css/bh-directory-search.css', ['bh-directory'], BH_PLUGIN_VERSION);
        wp_enqueue_style('bh-directory-search');
        wp_register_style('bh-schedule', BH_PLUGIN_URL . 'assets/css/bh-schedule.css', ['bh-directory'], BH_PLUGIN_VERSION);
        wp_enqueue_style('bh-schedule');
        wp_register_style('bh-directory-card', BH_PLUGIN_URL . 'assets/css/bh-directory-card.css', ['bh-directory'], BH_PLUGIN_VERSION);
        wp_enqueue_style('bh-directory-card');
        wp_register_style('bh-listing-info-widget', BH_PLUGIN_URL . 'assets/css/bh-listing-info-widget.css', ['bh-directory'], BH_PLUGIN_VERSION);
        wp_enqueue_style('bh-listing-info-widget');
        wp_register_style('bh-planner', BH_PLUGIN_URL . 'assets/css/bh-planner.css', ['bh-directory', 'bh-directory-search'], BH_PLUGIN_VERSION);
        wp_enqueue_style('bh-planner');
        wp_register_script('bh-weekly-schedule', BH_PLUGIN_URL . 'assets/js/bh-weekly-schedule.js', [], BH_PLUGIN_VERSION, true);
        wp_enqueue_script('bh-weekly-schedule');
        wp_register_script('bh-planner', BH_PLUGIN_URL . 'assets/js/bh-planner.js', [], BH_PLUGIN_VERSION, true);
        wp_enqueue_script('bh-planner');
        wp_register_script('bh-directory-search', BH_PLUGIN_URL . 'assets/js/bh-directory-search.js', [], BH_PLUGIN_VERSION, true);
        wp_enqueue_script('bh-directory-search');
        wp_localize_script('bh-directory-search', 'BubbaHubDirectorySearch', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bh_directory_search'),
        ]);
    }
}
