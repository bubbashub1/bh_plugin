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
        ]);

        ob_start();
        ?>
        <section class="bh-directory" aria-label="<?php echo esc_attr($atts['title']); ?>">
            <header class="bh-directory__header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
            </header>
            <?php if ($query->have_posts()) : ?>
                <div class="bh-directory__grid">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <article class="bh-directory-card">
                            <a class="bh-directory-card__link" href="<?php the_permalink(); ?>">
                                <?php if (has_post_thumbnail()) : ?>
                                    <div class="bh-directory-card__image"><?php the_post_thumbnail('medium'); ?></div>
                                <?php endif; ?>
                                <div class="bh-directory-card__body">
                                    <h3><?php the_title(); ?></h3>
                                    <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 20)); ?></p>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>
                <?php
                $big = 999999;
                $pagination = paginate_links([
                    'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                    'format' => '?bh_page=%#%',
                    'current' => max(1, (int) ($_GET['bh_page'] ?? 1)),
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
    }
}
