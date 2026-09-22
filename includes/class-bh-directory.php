<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class Directory {
    public static function register(): void {
        add_shortcode('bh_directory', [self::class, 'shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
    }

    public static function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'posts_per_page' => 12,
            'title' => 'Find Family Activities',
        ], $atts, 'bh_directory');

        if (!Listing::is_available()) {
            return '<div class="bh-directory bh-directory--notice"><p>Directory temporarily unavailable. Please activate Directorist.</p></div>';
        }

        $query = DirectoryQuery::run([
            'posts_per_page' => (int) $atts['posts_per_page'],
            'paged' => max(1, (int) ($_GET['bh_page'] ?? 1)),
            'search' => isset($_GET['bh_search']) ? sanitize_text_field(wp_unslash($_GET['bh_search'])) : '',
            'category' => isset($_GET['bh_category']) ? sanitize_title(wp_unslash($_GET['bh_category'])) : '',
            'age_range' => isset($_GET['bh_age_range']) ? sanitize_text_field(wp_unslash($_GET['bh_age_range'])) : '',
            'region' => isset($_GET['bh_region']) ? sanitize_title(wp_unslash($_GET['bh_region'])) : '',
            'town' => isset($_GET['bh_town']) ? sanitize_title(wp_unslash($_GET['bh_town'])) : '',
            'day' => isset($_GET['bh_day']) ? sanitize_title(wp_unslash($_GET['bh_day'])) : '',
            'price' => isset($_GET['bh_price']) ? sanitize_text_field(wp_unslash($_GET['bh_price'])) : '',
            'free_activity' => isset($_GET['bh_free_activity']) ? sanitize_text_field(wp_unslash($_GET['bh_free_activity'])) : '',
        ]);

        ob_start();
        ?>
        <section class="bh-directory" aria-label="<?php echo esc_attr($atts['title']); ?>">
            <header class="bh-directory__header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
            </header>
            <?php echo DirectorySearch::shortcode(); ?>
            <div class="bh-directory__header">
                <p class="bh-directory__count"><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?> <?php echo esc_html((int) $query->found_posts === 1 ? 'activity' : 'activities'); ?> found</p>
            </div>
            <?php if ($query->have_posts()) : ?>
                <div class="bh-directory__grid">
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
                if ($pagination) echo '<nav class="bh-directory__pagination" aria-label="Directory pages">' . wp_kses_post($pagination) . '</nav>';
                ?>
            <?php else : ?>
                <p class="bh-directory__empty">No family activities found.</p>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return (string) ob_get_clean();
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
    }
}
