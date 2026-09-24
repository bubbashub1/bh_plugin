<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class MyHub {
    private const CHILD_POST_TYPE = 'bh_child';

    public static function register(): void {
        add_shortcode('bh_my_hub', [self::class, 'shortcode']);
        add_shortcode('bh_planner', [self::class, 'planner_shortcode']);
        add_shortcode('bh_advanced_settings', [self::class, 'advanced_settings_shortcode']);
        add_shortcode('bh_account_settings', [self::class, 'advanced_settings_shortcode']);
        add_action('init', [self::class, 'handle_forms']);
        add_action('init', [self::class, 'handle_advanced_settings']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('wp_enqueue_scripts', [self::class, 'visited_assets']);
        add_action('wp_footer', [self::class, 'modal_script']);
        // Directorist visited-listing integration (read/write only in Bubba Hub user meta).
        add_action('directorist_single_listing_after_title', [self::class, 'render_visited_listing_controls'], 20);
        add_action('atbdp_after_listing_tagline', [self::class, 'render_visited_listing_controls'], 20);
        add_action('init', [self::class, 'handle_visited_listing']);
        add_action('wp', [self::class, 'track_recently_viewed_listing']);
    }

    public static function assets(): void {
        if (!self::is_my_hub_page()) {
            return;
        }

        wp_enqueue_style(
            'bh-frontend',
            BH_PLUGIN_URL . 'assets/css/bh-frontend.css',
            [],
            BH_PLUGIN_VERSION . '-frontend-' . substr(md5_file(BH_PLUGIN_DIR . 'assets/css/bh-frontend.css'), 0, 10)
        );
        wp_enqueue_style('bh-saved-searches', BH_PLUGIN_URL . 'assets/css/bh-saved-searches.css', [], BH_PLUGIN_VERSION);
        wp_enqueue_script('bh-saved-searches', BH_PLUGIN_URL . 'assets/js/bh-saved-searches.js', [], BH_PLUGIN_VERSION, true);
        wp_localize_script('bh-saved-searches', 'BubbaHubSavedSearches', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bh_edit_saved_search'),
        ]);
    }

    public static function visited_assets(): void {
        global $post;
        $load = is_singular('at_biz_dir') || is_post_type_archive('at_biz_dir');
        if (!$load && $post instanceof \WP_Post) {
            foreach (['directorist_all_listing','directorist_search_result','directorist_category','directorist_location','directorist_tag'] as $shortcode) {
                if (has_shortcode((string) $post->post_content, $shortcode)) { $load = true; break; }
            }
        }
        if (!$load) { return; }
        wp_enqueue_style('bh-visited-listing', BH_PLUGIN_URL . 'assets/css/bh-visited.css', [], BH_PLUGIN_VERSION);
        wp_enqueue_script('bh-visited-listing', BH_PLUGIN_URL . 'assets/js/bh-visited.js', [], BH_PLUGIN_VERSION, true);
    }

    private static function is_my_hub_page(): bool {
        global $post;
        if (!$post instanceof \WP_Post) {
            return false;
        }
        return has_shortcode((string) $post->post_content, 'bh_my_hub') || has_shortcode((string) $post->post_content, 'bh_planner') || has_shortcode((string) $post->post_content, 'bh_advanced_settings') || has_shortcode((string) $post->post_content, 'bh_account_settings');
    }

    /**
     * Return the Directorist listing ID currently being rendered.
     * We deliberately use the native at_biz_dir post and the current WP loop/query
     * rather than modifying Directorist templates or relying on version-specific
     * hook arguments.
     */
    private static function current_directorist_listing_id(): int {
        global $post;

        $id = 0;
        if ($post instanceof \WP_Post) {
            $id = (int) $post->ID;
        }
        if (!$id) {
            $id = (int) get_queried_object_id();
        }

        if (!$id || get_post_type($id) !== 'at_biz_dir') {
            return 0;
        }

        return $id;
    }

    private static function visited_listing_ids(int $user_id): array {
        if ($user_id < 1) {
            return [];
        }

        $visited = get_user_meta($user_id, 'bh_visited_listings', true);
        if (!is_array($visited)) {
            return [];
        }

        $ids = [];
        foreach ($visited as $listing_id => $visited_at) {
            $listing_id = (int) $listing_id;
            if ($listing_id > 0 && get_post_type($listing_id) === 'at_biz_dir') {
                $ids[$listing_id] = (string) $visited_at;
            }
        }

        return $ids;
    }

    private static function has_visited_listing(int $user_id, int $listing_id): bool {
        if ($user_id < 1 || $listing_id < 1) {
            return false;
        }

        $visited = self::visited_listing_ids($user_id);
        return isset($visited[$listing_id]);
    }

    /**
     * Record the Directorist group a logged-in member actually views.
     * This is separate from the manual Visited badge.
     */
    public static function track_recently_viewed_listing(): void {
        if (!is_user_logged_in() || !is_singular('at_biz_dir')) {
            return;
        }

        $listing_id = self::current_directorist_listing_id();
        if (!$listing_id || get_post_status($listing_id) !== 'publish') {
            return;
        }

        $user_id = get_current_user_id();
        $recent = get_user_meta($user_id, 'bh_recently_viewed_listings', true);
        if (!is_array($recent)) {
            $recent = [];
        }

        // Keep listing IDs as keys so a repeat view moves the group to the front.
        $recent[$listing_id] = current_time('timestamp');
        arsort($recent, SORT_NUMERIC);

        // Keep a small, fast history for My Hub.
        $recent = array_slice($recent, 0, 12, true);
        update_user_meta($user_id, 'bh_recently_viewed_listings', $recent);
    }

    private static function listing_location_label(int $listing_id): string {
        if ($listing_id < 1) {
            return '';
        }

        // Directorist stores listing locations in its own taxonomy.
        // Keep the older custom "location" taxonomy as a fallback.
        $taxonomies = ['at_biz_dir-location', 'location'];

        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = wp_get_post_terms($listing_id, $taxonomy, ['fields' => 'names']);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            return (string) $terms[0];
        }

        return '';
    }

    private static function recently_viewed_listing_ids(int $user_id): array {
        if ($user_id < 1) {
            return [];
        }

        $recent = get_user_meta($user_id, 'bh_recently_viewed_listings', true);
        if (!is_array($recent)) {
            return [];
        }

        $ids = [];
        foreach ($recent as $listing_id => $viewed_at) {
            $listing_id = (int) $listing_id;
            if ($listing_id > 0 && get_post_type($listing_id) === 'at_biz_dir' && get_post_status($listing_id) === 'publish') {
                $ids[$listing_id] = (int) $viewed_at;
            }
        }

        arsort($ids, SORT_NUMERIC);
        return array_keys($ids);
    }

    /**
     * Read Directorist's own bookmark/favourite list.
     * This keeps Saved Groups linked to the same Save/Bookmark control
     * already used on Directorist listings.
     */
    private static function saved_listing_ids(int $user_id): array {
        if ($user_id < 1 || !function_exists('directorist_get_user_favorites')) {
            return [];
        }

        $saved = directorist_get_user_favorites($user_id);
        if (!is_array($saved)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('absint', $saved),
            static fn($id) => $id > 0 && get_post_type($id) === 'at_biz_dir' && get_post_status($id) === 'publish'
        )));
    }


    public static function handle_visited_listing(): void {
        if (!is_user_logged_in() || empty($_POST['bh_visited_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['bh_visited_action']));
        if (!in_array($action, ['mark', 'unmark'], true)) {
            return;
        }

        $listing_id = isset($_POST['listing_id']) ? absint($_POST['listing_id']) : 0;
        $nonce = isset($_POST['bh_visited_nonce']) ? sanitize_text_field(wp_unslash($_POST['bh_visited_nonce'])) : '';

        if (!$listing_id || get_post_type($listing_id) !== 'at_biz_dir' || !wp_verify_nonce($nonce, 'bh_visited_listing_' . $listing_id)) {
            return;
        }

        $user_id = get_current_user_id();
        $visited = self::visited_listing_ids($user_id);

        if ('mark' === $action) {
            $visited[$listing_id] = current_time('mysql');
        } else {
            unset($visited[$listing_id]);
        }

        update_user_meta($user_id, 'bh_visited_listings', $visited);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = get_permalink($listing_id);
        }

        wp_safe_redirect(remove_query_arg(['bh_visited'], $redirect));
        exit;
    }

    public static function render_visited_listing_controls(): void {
        if (!is_user_logged_in()) {
            return;
        }

        $listing_id = self::current_directorist_listing_id();
        if (!$listing_id) {
            return;
        }

        static $rendered = [];
        if (isset($rendered[$listing_id])) {
            return;
        }
        $rendered[$listing_id] = true;

        $user_id = get_current_user_id();
        $visited = self::has_visited_listing($user_id, $listing_id);
        $planner = self::planner_items($user_id);
        $planned_dates = [];
        foreach ($planner as $date => $items) {
            if (in_array($listing_id, array_map('absint', (array) $items), true)) {
                $planned_dates[] = $date;
            }
        }
        sort($planned_dates);
        $today = current_datetime()->setTime(0, 0);
        $week_start = $today->modify('monday this week');
        $week_days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $week_start->modify('+' . $i . ' days');
            $week_days[] = [
                'date' => $day->format('Y-m-d'),
                'label' => wp_date('D j M', $day->getTimestamp(), wp_timezone()),
            ];
        }
        ?>
        <div class="bh-visited-listing" data-bh-visited-listing="<?php echo esc_attr((string) $listing_id); ?>">
            <?php if ($visited): ?>
                <span class="bh-visited-listing__badge" aria-label="Visited">✓ Visited</span>
                <form method="post" class="bh-visited-listing__form">
                    <?php wp_nonce_field('bh_visited_listing_' . $listing_id, 'bh_visited_nonce'); ?>
                    <input type="hidden" name="bh_visited_action" value="unmark">
                    <input type="hidden" name="listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                    <button type="submit" class="bh-visited-listing__button bh-visited-listing__button--remove">Remove visited</button>
                </form>
            <?php else: ?>
                <form method="post" class="bh-visited-listing__form">
                    <?php wp_nonce_field('bh_visited_listing_' . $listing_id, 'bh_visited_nonce'); ?>
                    <input type="hidden" name="bh_visited_action" value="mark">
                    <input type="hidden" name="listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                    <button type="submit" class="bh-visited-listing__button">📍 Mark as Visited</button>
                </form>
            <?php endif; ?>

            <div class="bh-planner-listing-action">
                <?php if ($planned_dates): ?>
                    <button type="button" class="bh-visited-listing__button bh-visited-listing__button--planned" data-bh-planner-toggle="<?php echo esc_attr((string) $listing_id); ?>">✓ In Planner</button>
                <?php else: ?>
                    <button type="button" class="bh-visited-listing__button" data-bh-planner-toggle="<?php echo esc_attr((string) $listing_id); ?>">🗓 Add to Planner</button>
                <?php endif; ?>
                <div class="bh-planner-listing-popover" data-bh-planner-popover="<?php echo esc_attr((string) $listing_id); ?>" hidden>
                    <div class="bh-planner-listing-popover__head">
                        <strong><?php echo $planned_dates ? 'In your planner' : 'Add to your planner'; ?></strong>
                        <button type="button" class="bh-planner-listing-popover__close" data-bh-planner-close aria-label="Close">×</button>
                    </div>
                    <?php if ($planned_dates): ?>
                        <p>Planned for <?php echo esc_html(implode(', ', array_map(static fn($date) => wp_date('D j M', strtotime($date), wp_timezone()), $planned_dates))); ?>.</p>
                    <?php endif; ?>
                    <div class="bh-planner-listing-popover__days">
                        <?php foreach ($week_days as $day): ?>
                            <form method="post">
                                <?php wp_nonce_field('bh_my_hub_add_to_planner', 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="add_to_planner">
                                <input type="hidden" name="listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                                <input type="hidden" name="planner_date" value="<?php echo esc_attr($day['date']); ?>">
                                <button type="submit" class="<?php echo in_array($day['date'], $planned_dates, true) ? 'is-planned' : ''; ?>">
                                    <span><?php echo esc_html($day['label']); ?></span>
                                    <?php if (in_array($day['date'], $planned_dates, true)): ?><small>✓ Planned</small><?php else: ?><small>Add</small><?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($planned_dates): ?>
                        <form method="post" class="bh-planner-listing-popover__remove">
                            <?php wp_nonce_field('bh_my_hub_remove_from_planner', 'bh_my_hub_nonce'); ?>
                            <input type="hidden" name="bh_my_hub_action" value="remove_activity_from_planner">
                            <input type="hidden" name="listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                            <button type="submit">Remove from planner</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            $url = function_exists('um_get_core_page') ? um_get_core_page('login') : wp_login_url(get_permalink());
            return '<div class="bh-my-hub bh-my-hub--login"><h2>My Bubba Hub</h2><p>Please log in to access your family hub.</p><a class="bh-my-hub__button" href="' . esc_url($url) . '">Log in</a></div>';
        }

        $user = wp_get_current_user();
        self::migrate_legacy_profiles($user->ID);
        $children = self::get_children($user->ID);
        $bumps = self::get_bumps($user->ID);
        $visited_listings = self::visited_listing_ids($user->ID);

        ob_start(); ?>
        <div class="bh-my-hub">
            <div class="bh-my-hub__intro">
                <div>
                    <p class="bh-my-hub__eyebrow">My Bubba Hub</p>
                    <h1>Welcome, <?php echo esc_html($user->display_name ?: $user->user_login); ?></h1>
                    <p>Your family and favourite Bubba Hub groups all in one place.</p>
                </div>
                <?php if (function_exists('um_get_core_page')): ?>
                    <a class="bh-my-hub__account-link" href="<?php echo esc_url(um_get_core_page('account')); ?>">Update preferences</a>
                <?php endif; ?>
            </div>

            <nav class="bh-my-hub__quick-nav" aria-label="My Bubba Hub">
                <a href="#bh-my-hub-family" class="bh-my-hub__quick-link bh-my-hub__quick-link--active"><span aria-hidden="true">👨‍👩‍👧</span><strong>My Family</strong><small>Children &amp; bumps</small></a>
                <a href="<?php echo esc_url(home_url('/directory/')); ?>" class="bh-my-hub__quick-link"><span aria-hidden="true">🔎</span><strong>Find Activities</strong><small>Discover local groups</small></a>
                <a href="<?php echo esc_url(self::planner_url()); ?>" class="bh-my-hub__quick-link"><span aria-hidden="true">📅</span><strong>My Planner</strong><small>Plan your week</small></a>
                <a href="<?php echo esc_url(DirectorySearch::saved_searches_url()); ?>" class="bh-my-hub__quick-link"><span aria-hidden="true">♡</span><strong>Saved Searches</strong><small>Quickly revisit searches</small></a>
                <a href="#bh-my-hub-groups" class="bh-my-hub__quick-link"><span aria-hidden="true">👥</span><strong>My Groups</strong><small>Your group activity</small></a>
            </nav>

            <?php if (isset($_GET['bh_hub_saved'])): ?>
                <div class="bh-my-hub__notice" role="status">Your details have been saved.</div>
            <?php endif; ?>

            <div class="bh-my-hub__grid">
                <section id="bh-my-hub-family" class="bh-my-hub__card bh-my-hub__family-container">
    <div class="bh-my-hub__family-header">
        <div class="bh-my-hub__card-head">
            <div>
                <span class="bh-my-hub__icon" aria-hidden="true">👨‍👩‍👧</span>
                <div>
                    <h2>My Family</h2>
                    <p class="bh-my-hub__family-subtitle">Keep your family details together for a more personal Bubba Hub.</p>
                </div>
            </div>
            <span class="bh-my-hub__count"><?php echo esc_html(count($children) + count($bumps)); ?></span>
        </div>

        <div class="bh-my-hub__family-actions">
            <div class="bh-my-hub__family-view-toggle" role="group" aria-label="Family view">
                <?php if ($children): ?>
                    <button type="button" class="bh-my-hub__family-view-button is-active" data-bh-family-view="children" aria-pressed="true">My Children</button>
                <?php endif; ?>
                <?php if ($bumps): ?>
                    <button type="button" class="bh-my-hub__family-view-button<?php echo $children ? '' : ' is-active'; ?>" data-bh-family-view="bumps" aria-pressed="<?php echo $children ? 'false' : 'true'; ?>">My Bumps</button>
                <?php endif; ?>
            </div>
            <button type="button" class="bh-my-hub__button bh-my-hub__open-modal" data-bh-modal="child">+ Add child</button>
            <button type="button" class="bh-my-hub__button bh-my-hub__button--outline bh-my-hub__open-modal" data-bh-modal="add-bump">+ Add bump</button>
        </div>
    </div>

    <?php if ($children || $bumps): ?>
        <?php if ($children): ?>
            <div class="bh-my-hub__profiles bh-my-hub__profiles--children" data-bh-family-view-panel="children" aria-label="Children">
            <?php foreach ($children as $child): ?>
                <?php
                $id = (int) $child->ID;
                $name = (string) get_field('field_bubbahub_child_name', $id);
                $dob = (string) get_field('field_bubbahub_child_date_of_birth', $id);
                $avatar = (string) get_field('field_bubbahub_child_avatar_url', $id);
                $gender = (string) get_field('field_bubbahub_child_gender', $id);
                ?>
                <article class="bh-my-hub__profile bh-my-hub__family-profile bh-my-hub__family-profile-card bh-my-hub__family-profile--child">
                    <div class="bh-my-hub__family-profile-top">
                        <?php if ($avatar): ?>
                            <img class="bh-my-hub__avatar" src="<?php echo esc_url($avatar); ?>" alt="">
                        <?php else: ?>
                            <span class="bh-my-hub__avatar bh-my-hub__avatar--placeholder" aria-hidden="true">👶</span>
                        <?php endif; ?>
                        <div class="bh-my-hub__family-profile-identity">
                            <span class="bh-my-hub__family-profile-type">Child</span>
                            <strong><?php echo esc_html($name ?: 'Child'); ?></strong>
                        </div>
                    </div>

                    <div class="bh-my-hub__family-profile-details">
                        <?php if ($dob): ?><span>🎂 <?php echo esc_html(self::family_age_label($dob)); ?></span><?php endif; ?>
                        <?php if ($gender): ?><span>♡ <?php echo esc_html(ucwords(str_replace('_', ' ', $gender))); ?></span><?php endif; ?>
                    </div>

                    <div class="bh-my-hub__family-profile-actions">
                        <button type="button" class="bh-my-hub__family-action bh-my-hub__open-modal" data-bh-modal="edit-child-<?php echo esc_attr((string) $id); ?>">Edit</button>
                        <form method="post">
                            <?php wp_nonce_field('bh_my_hub_delete_child_' . $id, 'bh_my_hub_nonce'); ?>
                            <input type="hidden" name="bh_my_hub_action" value="delete_child">
                            <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $id); ?>">
                            <button type="submit" class="bh-my-hub__family-action bh-my-hub__family-action--remove">Remove</button>
                        </form>
                    </div>

                    <div class="bh-my-hub__modal" data-bh-modal-panel="edit-child-<?php echo esc_attr((string) $id); ?>" hidden>
                        <div class="bh-my-hub__modal-backdrop" data-bh-modal-close></div>
                        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true">
                            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
                            <h3>Edit <?php echo esc_html($name ?: 'child'); ?></h3>
                            <form method="post" class="bh-my-hub__form" enctype="multipart/form-data">
                                <?php wp_nonce_field('bh_my_hub_edit_child_' . $id, 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="edit_child">
                                <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $id); ?>">
                                <?php self::child_fields($id); ?>
                                <button class="bh-my-hub__button" type="submit">Save changes</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($bumps): ?>
            <div class="bh-my-hub__profiles bh-my-hub__profiles--bumps<?php echo $children ? ' bh-my-hub__family-view-hidden' : ''; ?>" data-bh-family-view-panel="bumps" aria-label="Expected babies">
            <?php foreach ($bumps as $bump): ?>
                <?php
                $bump_id = (int) $bump->ID;
                $nickname = (string) get_field('field_bubbahub_child_nickname', $bump_id);
                $due = (string) get_field('field_bubbahub_child_due_date', $bump_id);
                $baby_is_here = $due ? self::bump_is_38_weeks($due) : false;
                ?>
                <article class="bh-my-hub__profile bh-my-hub__family-profile bh-my-hub__family-profile-card bh-my-hub__family-profile--bump">
                    <div class="bh-my-hub__family-profile-top">
                        <span class="bh-my-hub__avatar bh-my-hub__avatar--placeholder" aria-hidden="true">🤰</span>
                        <div class="bh-my-hub__family-profile-identity">
                            <span class="bh-my-hub__family-profile-type">Expected baby</span>
                            <strong><?php echo esc_html($nickname ?: 'My bump'); ?></strong>
                        </div>
                    </div>

                    <div class="bh-my-hub__family-profile-details">
                        <?php if ($due): ?><span>📅 Due <?php echo esc_html(wp_date(get_option('date_format'), strtotime($due))); ?></span><?php endif; ?>
                    </div>

                    <div class="bh-my-hub__family-profile-actions">
                        <button type="button" class="bh-my-hub__family-action bh-my-hub__open-modal" data-bh-modal="edit-bump-<?php echo esc_attr((string) $bump_id); ?>">Edit</button>
                        <?php if ($baby_is_here): ?>
                            <form method="post">
                                <?php wp_nonce_field('bh_my_hub_baby_is_here_' . $bump_id, 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="baby_is_here">
                                <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $bump_id); ?>">
                                <button type="submit" class="bh-my-hub__family-action bh-my-hub__family-action--success">Baby is here</button>
                            </form>
                        <?php endif; ?>
                        <form method="post">
                            <?php wp_nonce_field('bh_my_hub_delete_bump_' . $bump_id, 'bh_my_hub_nonce'); ?>
                            <input type="hidden" name="bh_my_hub_action" value="delete_bump">
                            <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $bump_id); ?>">
                            <button type="submit" class="bh-my-hub__family-action bh-my-hub__family-action--remove">Remove</button>
                        </form>
                    </div>

                    <div class="bh-my-hub__modal" data-bh-modal-panel="edit-bump-<?php echo esc_attr((string) $bump_id); ?>" hidden>
                        <div class="bh-my-hub__modal-backdrop" data-bh-modal-close></div>
                        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true">
                            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
                            <h3>Edit bump</h3>
                            <form method="post" class="bh-my-hub__form">
                                <?php wp_nonce_field('bh_my_hub_save_bump_' . $bump_id, 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="save_bump">
                                <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $bump_id); ?>">
                                <div class="bh-my-hub__fields">
                                    <label>Nickname<input type="text" name="bump_nickname" maxlength="100" value="<?php echo esc_attr($nickname); ?>"></label>
                                    <label>Due date<input type="date" name="bump_due_date" value="<?php echo esc_attr($due); ?>" required></label>
                                </div>
                                <button class="bh-my-hub__button" type="submit">Save changes</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bh-my-hub__family-empty">
            <span aria-hidden="true">👨‍👩‍👧</span>
            <div>
                <strong>Add your family</strong>
                <p>Add a child or bump to personalise your Bubba Hub.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="bh-my-hub__modal" data-bh-modal-panel="child" hidden>
        <div class="bh-my-hub__modal-backdrop" data-bh-modal-close></div>
        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true">
            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
            <h3>Add a child</h3>
            <form method="post" class="bh-my-hub__form" enctype="multipart/form-data">
                <?php wp_nonce_field('bh_my_hub_save_child', 'bh_my_hub_nonce'); ?>
                <input type="hidden" name="bh_my_hub_action" value="save_child">
                <?php self::child_fields(); ?>
                <button class="bh-my-hub__button" type="submit">Save child</button>
            </form>
        </div>
    </div>

    <div class="bh-my-hub__modal" data-bh-modal-panel="add-bump" hidden>
        <div class="bh-my-hub__modal-backdrop" data-bh-modal-close></div>
        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true">
            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
            <h3>Add a bump</h3>
            <form method="post" class="bh-my-hub__form">
                <?php wp_nonce_field('bh_my_hub_save_bump', 'bh_my_hub_nonce'); ?>
                <input type="hidden" name="bh_my_hub_action" value="save_bump">
                <input type="hidden" name="child_id" value="">
                <div class="bh-my-hub__fields">
                    <label>Nickname<input type="text" name="bump_nickname" maxlength="100" value=""></label>
                    <label>Due date<input type="date" name="bump_due_date" value="" required></label>
                </div>
                <button class="bh-my-hub__button" type="submit">Save bump</button>
            </form>
        </div>
    </div>
</section>

<section id="bh-my-hub-groups" class="bh-my-hub__card bh-my-hub__card--wide">
                    <div class="bh-my-hub__card-head">
                        <div><span class="bh-my-hub__icon">👥</span><h2>My Groups</h2></div>
                    </div>

                    <?php
                    $recently_viewed = self::recently_viewed_listing_ids($user->ID);
                    $saved_groups = self::saved_listing_ids($user->ID);
                    ?>
                    <div class="bh-my-hub__group-rows">
                        <section class="bh-my-hub__group-row" aria-labelledby="bh-recently-viewed-title">
                            <div class="bh-my-hub__group-row-head">
                                <div><span class="bh-my-hub__group-icon">🕘</span><h3 id="bh-recently-viewed-title">Recently viewed</h3></div>
                                <span><?php echo $recently_viewed ? esc_html(count($recently_viewed)) . ' viewed' : 'Your latest groups'; ?></span>
                            </div>
                            <div class="bh-my-hub__group-scroll">
                                <?php if ($recently_viewed): ?>
                                    <?php foreach ($recently_viewed as $recent_id): ?>
                                        <?php
                                        $recent_post = get_post((int) $recent_id);
                                        if (!$recent_post || $recent_post->post_status !== 'publish') {
                                            continue;
                                        }
                                        $recent_title = get_the_title($recent_post);
                                        $recent_url = get_permalink($recent_post);
                                        ?>
                                        <article class="bh-my-hub__group-card">
                                            <strong><?php echo esc_html($recent_title ?: 'Activity'); ?></strong>
                                            <?php $recent_location = self::listing_location_label((int) $recent_id); ?>
                                            <?php if ($recent_location): ?><p><?php echo esc_html($recent_location); ?></p><?php endif; ?>
                                            <?php if ($recent_url): ?><a href="<?php echo esc_url($recent_url); ?>">View group</a><?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <article class="bh-my-hub__group-card">
                                        <strong>No recently viewed groups yet</strong>
                                        <p>Open a group in the directory and it will appear here automatically.</p>
                                    </article>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="bh-my-hub__group-row" aria-labelledby="bh-visited-title">
                            <div class="bh-my-hub__group-row-head">
                                <div><span class="bh-my-hub__group-icon">📍</span><h3 id="bh-visited-title">Visited</h3></div>
                                <span><?php echo $visited_listings ? esc_html(count($visited_listings)) . ' visited' : 'Your visited groups'; ?></span>
                            </div>
                            <div class="bh-my-hub__group-scroll">
                                <?php if ($visited_listings): ?>
                                    <?php foreach (array_keys($visited_listings) as $visited_id): ?>
                                        <?php
                                        $visited_post = get_post((int) $visited_id);
                                        if (!$visited_post || $visited_post->post_status !== 'publish') {
                                            continue;
                                        }
                                        $visited_title = get_the_title($visited_post);
                                        $visited_url = get_permalink($visited_post);
                                        ?>
                                        <article class="bh-my-hub__group-card bh-my-hub__group-card--visited">
                                            <strong><?php echo esc_html($visited_title ?: 'Activity'); ?></strong>
                                            <?php $visited_location = self::listing_location_label((int) $visited_id); ?>
                                            <?php if ($visited_location): ?><p><?php echo esc_html($visited_location); ?></p><?php endif; ?>
                                            <?php if ($visited_url): ?><a href="<?php echo esc_url($visited_url); ?>">View group</a><?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <article class="bh-my-hub__group-card">
                                        <strong>No visited groups yet</strong>
                                        <p>Mark a group as visited in the directory and it will appear here.</p>
                                    </article>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="bh-my-hub__group-row" aria-labelledby="bh-saved-groups-title">
                            <div class="bh-my-hub__group-row-head">
                                <div><span class="bh-my-hub__group-icon">♡</span><h3 id="bh-saved-groups-title">Saved</h3></div>
                                <span><?php echo $saved_groups ? esc_html(count($saved_groups)) . ' saved' : 'Your saved groups'; ?></span>
                            </div>
                            <div class="bh-my-hub__group-scroll">
                                <?php if ($saved_groups): ?>
                                    <?php foreach ($saved_groups as $saved_id): ?>
                                        <?php
                                        $saved_post = get_post((int) $saved_id);
                                        if (!$saved_post || $saved_post->post_status !== 'publish') {
                                            continue;
                                        }
                                        $saved_title = get_the_title($saved_post);
                                        $saved_url = get_permalink($saved_post);
                                        ?>
                                        <article class="bh-my-hub__group-card">
                                            <strong><?php echo esc_html($saved_title ?: 'Activity'); ?></strong>
                                            <?php $saved_location = self::listing_location_label((int) $saved_id); ?>
                                            <?php if ($saved_location): ?><p><?php echo esc_html($saved_location); ?></p><?php endif; ?>
                                            <?php if ($saved_url): ?><a href="<?php echo esc_url($saved_url); ?>">View group</a><?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <article class="bh-my-hub__group-card">
                                        <strong>No saved groups yet</strong>
                                        <p>Use the Save/Bookmark control on a group and it will appear here.</p>
                                    </article>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="bh-my-hub__group-row" aria-labelledby="bh-suggestions-title">
                            <div class="bh-my-hub__group-row-head">
                                <div><span class="bh-my-hub__group-icon">✨</span><h3 id="bh-suggestions-title">Suggestions</h3></div>
                                <span>Coming next</span>
                            </div>
                            <div class="bh-my-hub__group-scroll">
                                <article class="bh-my-hub__group-card">
                                    <strong>Suggestions for your family</strong>
                                    <p>Recommended groups will appear here based on your family and search preferences.</p>
                                </article>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function planner_shortcode(): string {
        if (!is_user_logged_in()) {
            $url = function_exists('um_get_core_page') ? um_get_core_page('login') : wp_login_url(get_permalink());
            return '<div class="bh-planner bh-planner--login"><h2>My Planner</h2><p>Please log in to access your planner.</p><a class="bh-my-hub__button" href="' . esc_url($url) . '">Log in</a></div>';
        }

        $user = wp_get_current_user();
        return self::render_planner_section($user->ID);
    }

    private static function child_fields(int $post_id = 0): void {
        $name = $post_id ? (string) get_field('field_bubbahub_child_name', $post_id) : '';
        $nickname = $post_id ? (string) get_field('field_bubbahub_child_nickname', $post_id) : '';
        $gender = $post_id ? (string) get_field('field_bubbahub_child_gender', $post_id) : '';
        $status = $post_id ? (string) get_field('field_bubbahub_child_status', $post_id) : 'born';
        $dob = $post_id ? (string) get_field('field_bubbahub_child_date_of_birth', $post_id) : '';
        $due = $post_id ? (string) get_field('field_bubbahub_child_due_date', $post_id) : '';
        $avatar = $post_id ? (string) get_field('field_bubbahub_child_avatar_url', $post_id) : '';
        $allergies = $post_id ? (string) get_field('field_bubbahub_child_allergies', $post_id) : '';
        $notes = $post_id ? (string) get_field('field_bubbahub_child_notes', $post_id) : '';
        $age_group = $post_id ? (string) get_field('field_bubbahub_child_age_group', $post_id) : '';
        $school_name = $post_id ? (string) get_field('field_bubbahub_child_school_name', $post_id) : '';
        $school_status = $post_id ? (string) get_field('field_bubbahub_child_school_application_status', $post_id) : '';
        $school_deadline = $post_id ? (string) get_field('field_bubbahub_child_school_application_deadline', $post_id) : '';
        $school_year = $post_id ? (string) get_field('field_bubbahub_child_school_year', $post_id) : '';
        $ofsted = $post_id ? (string) get_field('field_bubbahub_child_ofsted_rating', $post_id) : '';
        $nap_schedule = $post_id ? get_field('field_bubbahub_child_nap_schedule', $post_id) : [];
        if (!is_array($nap_schedule)) $nap_schedule = [];
        $nap_by_day = [];
        foreach ($nap_schedule as $nap) {
            if (!empty($nap['day_name'])) $nap_by_day[$nap['day_name']] = $nap;
        }

        $selected_locations = $post_id ? wp_get_post_terms($post_id, 'location', ['fields' => 'ids']) : [];
        $selected_location = (!is_wp_error($selected_locations) && !empty($selected_locations)) ? (int) $selected_locations[0] : 0;
        $locations = get_terms(['taxonomy' => 'location', 'hide_empty' => false]);
        ?>
        <div class="bh-my-hub__fields">
            <label>Name<input type="text" name="child_name" maxlength="100" value="<?php echo esc_attr($name); ?>" required></label>
            <label>Gender
                <select name="gender">
                    <option value="">Select gender</option>
                    <option value="girl" <?php selected($gender, 'girl'); ?>>Girl</option>
                    <option value="boy" <?php selected($gender, 'boy'); ?>>Boy</option>
                    <option value="prefer_not_to_say" <?php selected($gender, 'prefer_not_to_say'); ?>>Prefer not to say</option>
                </select>
            </label>
            <label>Date of birth<input type="text" name="child_dob" value="<?php echo esc_attr($dob ? wp_date('d/m/Y', strtotime($dob)) : ''); ?>" placeholder="DD/MM/YYYY" inputmode="numeric" autocomplete="bday" pattern="\\d{2}/\\d{2}/\\d{4}" maxlength="10"></label>
            <input type="hidden" name="child_status" value="born">

            <label class="bh-my-hub__avatar-field">Avatar
                <input type="file" name="child_avatar" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG or WebP</small>
            </label>
        </div>
</div>
        <?php
    }
    public static function handle_forms(): void {
        if (!is_user_logged_in() || empty($_POST['bh_my_hub_action'])) return;

        $action = sanitize_key(wp_unslash($_POST['bh_my_hub_action']));
        $user_id = get_current_user_id();

        if ($action === 'save_child') {
            check_admin_referer('bh_my_hub_save_child', 'bh_my_hub_nonce');
            self::save_child_post($user_id);
            self::redirect_saved();
        }

        if ($action === 'edit_child') {
            $id = absint($_POST['child_id'] ?? 0);
            check_admin_referer('bh_my_hub_edit_child_' . $id, 'bh_my_hub_nonce');
            if (self::user_owns_child($id, $user_id)) self::save_child_post($user_id, $id);
            self::redirect_saved();
        }

        if ($action === 'delete_child') {
            $id = absint($_POST['child_id'] ?? 0);
            check_admin_referer('bh_my_hub_delete_child_' . $id, 'bh_my_hub_nonce');
            if (self::user_owns_child($id, $user_id) && get_field('child_status', $id) === 'born') wp_delete_post($id, true);
            self::redirect_saved();
        }

        if ($action === 'save_bump') {
            $id = absint($_POST['child_id'] ?? 0);
            check_admin_referer($id ? 'bh_my_hub_save_bump_' . $id : 'bh_my_hub_save_bump', 'bh_my_hub_nonce');
            if (!$id || !self::user_owns_child($id, $user_id)) {
                $created = wp_insert_post(['post_type'=>self::CHILD_POST_TYPE,'post_status'=>'publish','post_author'=>$user_id,'post_title'=>'My bump'], true);
                $id = is_wp_error($created) ? 0 : (int) $created;
            }
            if ($id) self::save_bump_post($id);
            self::redirect_saved();
        }

        if ($action === 'baby_is_here') {
            $id = absint($_POST['child_id'] ?? 0);
            check_admin_referer('bh_my_hub_baby_is_here_' . $id, 'bh_my_hub_nonce');
            if (self::user_owns_child($id, $user_id) && get_field('child_status', $id) === 'expecting') {
                $due = (string) get_field('field_bubbahub_child_due_date', $id);
                if ($due && self::bump_is_38_weeks($due)) self::convert_bump_to_child($id);
            }
            self::redirect_saved();
        }

        if ($action === 'save_nap_window') {
            check_admin_referer('bh_my_hub_save_nap_window', 'bh_my_hub_nonce');

            $child_id = absint($_POST['nap_child_id'] ?? 0);
            $day = sanitize_key(wp_unslash($_POST['nap_day'] ?? ''));
            $start = sanitize_text_field(wp_unslash($_POST['nap_start_time'] ?? ''));
            $end = sanitize_text_field(wp_unslash($_POST['nap_end_time'] ?? ''));
            $valid_days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

            if (
                self::user_owns_child($child_id, $user_id) &&
                get_field('child_status', $child_id) === 'born' &&
                in_array($day, $valid_days, true) &&
                preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $start) &&
                preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $end) &&
                $start < $end
            ) {
                $schedule = get_field('field_bubbahub_child_nap_schedule', $child_id);
                if (!is_array($schedule)) $schedule = [];

                $schedule[] = [
                    'day_name' => $day,
                    'enabled' => 1,
                    'start_time' => $start,
                    'end_time' => $end,
                ];

                update_field('field_bubbahub_child_nap_schedule', array_values($schedule), $child_id);
            }

            self::redirect_planner();
        }

        if ($action === 'edit_nap_window') {
            check_admin_referer('bh_my_hub_edit_nap_window', 'bh_my_hub_nonce');

            $child_id = absint($_POST['nap_child_id'] ?? 0);
            $index = isset($_POST['nap_index']) ? absint($_POST['nap_index']) : -1;
            $day = sanitize_key(wp_unslash($_POST['nap_day'] ?? ''));
            $start = sanitize_text_field(wp_unslash($_POST['nap_start_time'] ?? ''));
            $end = sanitize_text_field(wp_unslash($_POST['nap_end_time'] ?? ''));
            $valid_days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

            if (
                self::user_owns_child($child_id, $user_id) &&
                get_field('child_status', $child_id) === 'born' &&
                $index >= 0 &&
                in_array($day, $valid_days, true) &&
                preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $start) &&
                preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $end) &&
                $start < $end
            ) {
                $schedule = get_field('field_bubbahub_child_nap_schedule', $child_id);
                if (is_array($schedule) && array_key_exists($index, $schedule)) {
                    $schedule[$index]['day_name'] = $day;
                    $schedule[$index]['enabled'] = 1;
                    $schedule[$index]['start_time'] = $start;
                    $schedule[$index]['end_time'] = $end;
                    update_field('field_bubbahub_child_nap_schedule', array_values($schedule), $child_id);
                }
            }

            self::redirect_planner();
        }

        if ($action === 'remove_nap_window') {
            check_admin_referer('bh_my_hub_remove_nap_window', 'bh_my_hub_nonce');

            $child_id = absint($_POST['child_id'] ?? 0);
            $index = isset($_POST['nap_index']) ? absint($_POST['nap_index']) : -1;

            if (self::user_owns_child($child_id, $user_id) && $index >= 0) {
                $schedule = get_field('field_bubbahub_child_nap_schedule', $child_id);
                if (is_array($schedule) && array_key_exists($index, $schedule)) {
                    unset($schedule[$index]);
                    update_field('field_bubbahub_child_nap_schedule', array_values($schedule), $child_id);
                }
            }

            self::redirect_planner();
        }

        if ($action === 'save_activity') {
            check_admin_referer('bh_my_hub_save_activity', 'bh_my_hub_nonce');
            $id = absint($_POST['listing_id'] ?? 0);
            if ($id && get_post_status($id) && Listing::is_available()) {
                $saved = self::saved_activities($user_id);
                if (!in_array($id, $saved, true)) {
                    $saved[] = $id;
                    update_user_meta($user_id, '_bh_saved_activities', array_values(array_unique(array_map('absint', $saved))));
                }
            }
            wp_safe_redirect(self::planner_url());
            exit;
        }

        if ($action === 'remove_activity') {
            check_admin_referer('bh_my_hub_remove_activity', 'bh_my_hub_nonce');
            $id = absint($_POST['listing_id'] ?? 0);
            $saved = array_values(array_filter(self::saved_activities($user_id), static fn($item) => (int) $item !== $id));
            update_user_meta($user_id, '_bh_saved_activities', $saved);
            self::remove_activity_from_planner($user_id, $id);
            self::redirect_saved();
        }

        if ($action === 'add_to_planner') {
            check_admin_referer('bh_my_hub_add_to_planner', 'bh_my_hub_nonce');
            $id = absint($_POST['listing_id'] ?? 0);
            $date = sanitize_text_field(wp_unslash($_POST['planner_date'] ?? ''));
            if ($id && get_post_type($id) === 'at_biz_dir' && get_post_status($id) === 'publish' && self::valid_planner_date($date)) {
                $planner = self::planner_items($user_id);
                $planner[$date] = isset($planner[$date]) && is_array($planner[$date]) ? $planner[$date] : [];
                if (!in_array($id, $planner[$date], true)) $planner[$date][] = $id;
                update_user_meta($user_id, '_bh_planner_items', $planner);
            }
            self::redirect_planner();
        }

        if ($action === 'remove_from_planner') {
            check_admin_referer('bh_my_hub_remove_from_planner', 'bh_my_hub_nonce');
            $id = absint($_POST['listing_id'] ?? 0);
            $date = sanitize_text_field(wp_unslash($_POST['planner_date'] ?? ''));
            if ($id && self::valid_planner_date($date)) {
                $planner = self::planner_items($user_id);
                if (isset($planner[$date])) {
                    $planner[$date] = array_values(array_filter((array) $planner[$date], static fn($item) => (int) $item !== $id));
                    if (!$planner[$date]) unset($planner[$date]);
                    update_user_meta($user_id, '_bh_planner_items', $planner);
                }
            }
            self::redirect_planner();
        }

        if ($action === 'remove_activity_from_planner') {
            check_admin_referer('bh_my_hub_remove_activity_from_planner', 'bh_my_hub_nonce');
            $id = absint($_POST['listing_id'] ?? 0);
            if ($id) self::remove_activity_from_planner($user_id, $id);
            $redirect = wp_get_referer() ?: self::planner_url();
            wp_safe_redirect($redirect);
            exit;
        }

        if ($action === 'delete_bump') {
            $id = absint($_POST['child_id'] ?? 0);
            check_admin_referer('bh_my_hub_delete_bump_' . $id, 'bh_my_hub_nonce');
            if (self::user_owns_child($id, $user_id) && get_field('child_status', $id) === 'expecting') wp_delete_post($id, true);
            self::redirect_saved();
        }
    }

    private static function saved_activities(int $user_id): array {
        $saved = get_user_meta($user_id, '_bh_saved_activities', true);
        if (!is_array($saved)) return [];
        return array_values(array_unique(array_filter(array_map('absint', $saved))));
    }

    private static function planner_items(int $user_id): array {
        $items = get_user_meta($user_id, '_bh_planner_items', true);
        return is_array($items) ? $items : [];
    }

    private static function valid_planner_date(string $date): bool {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }

    private static function remove_activity_from_planner(int $user_id, int $listing_id): void {
        $planner = self::planner_items($user_id);
        foreach ($planner as $date => $items) {
            $planner[$date] = array_values(array_filter((array) $items, static fn($item) => (int) $item !== $listing_id));
            if (!$planner[$date]) unset($planner[$date]);
        }
        update_user_meta($user_id, '_bh_planner_items', $planner);
    }

    private static function render_planner_section(int $user_id): string {
        $planner = self::planner_items($user_id);
        $preferences = get_user_meta($user_id, '_bh_planner_preferences', true);
        $hidden_days = is_array($preferences) && !empty($preferences['hidden_days']) ? array_map('sanitize_key', (array) $preferences['hidden_days']) : [];
        $requested_week = isset($_GET['planner_week']) ? sanitize_text_field(wp_unslash($_GET['planner_week'])) : '';
        $anchor = self::valid_planner_date($requested_week) ? new \DateTimeImmutable($requested_week, wp_timezone()) : current_datetime();
        $week_start = $anchor->setTime(0, 0)->modify('monday this week');
        $days = [];
        $day_keys = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        foreach ($day_keys as $i => $day_key) {
            $day = $week_start->modify('+' . $i . ' days');
            $days[] = [
                'key' => $day_key,
                'date' => $day->format('Y-m-d'),
                'day' => $day,
            ];
        }
        $visible_days = array_values(array_filter($days, static fn($day) => !in_array($day['key'], $hidden_days, true)));
        if (!$visible_days) $visible_days = $days;

        $previous = $week_start->modify('-7 days')->format('Y-m-d');
        $next = $week_start->modify('+7 days')->format('Y-m-d');
        $range_label = wp_date('j M', $week_start->getTimestamp(), wp_timezone()) . ' – ' . wp_date('j M Y', $week_start->modify('+6 days')->getTimestamp(), wp_timezone());
        $planned_count = 0;
        foreach ($planner as $items) $planned_count += count((array) $items);

        ob_start(); ?>
        <section id="bh-planner" class="bh-planner">
            <div class="bh-planner__intro">
                <div>
                    <p class="bh-planner__eyebrow">My week</p>
                    <h1>My Planner</h1>
                    <p>Turn the activities you find on Bubba Hub into your family's plan for the week.</p>
                </div>
                <a class="bh-planner__find" href="<?php echo esc_url(home_url('/directory/')); ?>">Find activities</a>
            </div>

            <div class="bh-planner__toolbar">
                <a class="bh-planner__week-link" href="<?php echo esc_url(add_query_arg('planner_week', $previous, self::planner_url())); ?>" aria-label="Previous week">‹</a>
                <div class="bh-planner__week-title">
                    <strong><?php echo esc_html($range_label); ?></strong>
                    <a href="<?php echo esc_url(remove_query_arg('planner_week', self::planner_url())); ?>">This week</a>
                </div>
                <a class="bh-planner__week-link" href="<?php echo esc_url(add_query_arg('planner_week', $next, self::planner_url())); ?>" aria-label="Next week">›</a>
            </div>

            <?php if ($planned_count === 0): ?>
                <div class="bh-planner__empty">
                    <span aria-hidden="true">🗓️</span>
                    <h2>Your week is ready to fill</h2>
                    <p>When you find a group or class you want to go to, tap <strong>Add to Planner</strong> on its Directorist listing and choose the day.</p>
                    <a class="bh-planner__find" href="<?php echo esc_url(home_url('/directory/')); ?>">Browse activities</a>
                </div>
            <?php endif; ?>

            <div class="bh-planner__days" style="--bh-planner-day-count:<?php echo esc_attr((string) count($visible_days)); ?>">
                <?php foreach ($visible_days as $day): $date_key = $day['date']; ?>
                    <article class="bh-planner__day">
                        <header class="bh-planner__day-head">
                            <span><?php echo esc_html(wp_date('D', $day['day']->getTimestamp(), wp_timezone())); ?></span>
                            <strong><?php echo esc_html(wp_date('j', $day['day']->getTimestamp(), wp_timezone())); ?></strong>
                            <small><?php echo esc_html(wp_date('M', $day['day']->getTimestamp(), wp_timezone())); ?></small>
                        </header>

                        <div class="bh-planner__day-body">
                            <?php
                            $items = array_values(array_unique(array_map('absint', (array) ($planner[$date_key] ?? []))));
                            foreach ($items as $listing_id):
                                $post = get_post($listing_id);
                                if (!$post || $post->post_type !== 'at_biz_dir' || $post->post_status !== 'publish') continue;
                                ?>
                                <article class="bh-planner__item">
                                    <div class="bh-planner__item-main">
                                        <a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                                        <span><?php echo esc_html(self::listing_location_label($listing_id)); ?></span>
                                    </div>
                                    <form method="post">
                                        <?php wp_nonce_field('bh_my_hub_remove_from_planner', 'bh_my_hub_nonce'); ?>
                                        <input type="hidden" name="bh_my_hub_action" value="remove_from_planner">
                                        <input type="hidden" name="listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                                        <input type="hidden" name="planner_date" value="<?php echo esc_attr($date_key); ?>">
                                        <button type="submit" aria-label="Remove <?php echo esc_attr(get_the_title($post)); ?> from planner">×</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>

                            <?php if (empty($items)): ?>
                                <p class="bh-planner__nothing">Nothing planned</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="bh-planner__hint">
                <span aria-hidden="true">💡</span>
                <p><strong>Planning is personal.</strong> The directory calendar shows what's available; My Planner only shows the activities you've chosen.</p>
            </div>
        </section>
        <?php return (string) ob_get_clean();
    }

    private static function save_child_post(int $user_id, int $id = 0): int {
        $name = sanitize_text_field(wp_unslash($_POST['child_name'] ?? ''));
        $nickname = sanitize_text_field(wp_unslash($_POST['child_nickname'] ?? ''));
        $gender = sanitize_key(wp_unslash($_POST['gender'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['child_status'] ?? 'born'));
        $dob_input = sanitize_text_field(wp_unslash($_POST['child_dob'] ?? ''));
        $dob = '';
        if ($dob_input) {
            $dob_dt = \DateTimeImmutable::createFromFormat('!d/m/Y', $dob_input, wp_timezone());
            if ($dob_dt instanceof \DateTimeImmutable && $dob_dt->format('d/m/Y') === $dob_input) $dob = $dob_dt->format('Y-m-d');
        }
        $due = sanitize_text_field(wp_unslash($_POST['child_due_date'] ?? ''));
        $avatar = esc_url_raw(wp_unslash($_POST['child_avatar_url'] ?? ''));
        $location_id = absint($_POST['child_location'] ?? 0);
        $manual_age_group = sanitize_key(wp_unslash($_POST['child_age_group'] ?? ''));
        $allergies = sanitize_textarea_field(wp_unslash($_POST['child_allergies'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['child_notes'] ?? ''));
        $school_name = sanitize_text_field(wp_unslash($_POST['school_name'] ?? ''));
        $school_status = sanitize_text_field(wp_unslash($_POST['school_application_status'] ?? ''));
        $school_deadline = sanitize_text_field(wp_unslash($_POST['school_application_deadline'] ?? ''));
        $school_year = sanitize_text_field(wp_unslash($_POST['school_year'] ?? ''));
        $ofsted = sanitize_text_field(wp_unslash($_POST['ofsted_rating'] ?? ''));
        $posted_naps = isset($_POST['nap_schedule']) && is_array($_POST['nap_schedule']) ? wp_unslash($_POST['nap_schedule']) : [];
        $nap_schedule = [];

        $valid_nap_days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        foreach ($valid_nap_days as $day) {
            $row = isset($posted_naps[$day]) && is_array($posted_naps[$day]) ? $posted_naps[$day] : [];
            $enabled = !empty($row['enabled']) ? 1 : 0;
            $start_time = preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', (string) ($row['start_time'] ?? '')) ? (string) $row['start_time'] : '';
            $end_time = preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', (string) ($row['end_time'] ?? '')) ? (string) $row['end_time'] : '';
            if ($enabled || $start_time || $end_time) {
                $nap_schedule[] = [
                    'day_name' => $day,
                    'enabled' => $enabled,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                ];
            }
        }

        if (!empty($_FILES['child_avatar']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_id = media_handle_upload('child_avatar', $id ?: 0, [], [
                'test_form' => false,
                'mimes' => [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ],
            ]);
            if (!is_wp_error($attachment_id)) {
                $uploaded_url = wp_get_attachment_url((int) $attachment_id);
                if ($uploaded_url) $avatar = $uploaded_url;
            }
        }

        if (!in_array($status, ['born','expecting'], true)) $status = 'born';
        if (!in_array($gender, ['girl', 'boy', 'prefer_not_to_say'], true)) $gender = '';

        if ($id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== self::CHILD_POST_TYPE || (int)$post->post_author !== $user_id) return 0;
            wp_update_post(['ID'=>$id,'post_title'=>$name ?: 'Child']);
        } else {
            $id = wp_insert_post(['post_type'=>self::CHILD_POST_TYPE,'post_status'=>'publish','post_author'=>$user_id,'post_title'=>$name ?: 'Child'], true);
            if (is_wp_error($id)) return 0;
        }

        update_field('field_bubbahub_child_name', $name, $id);
        update_field('field_bubbahub_child_nickname', $nickname, $id);
        update_field('field_bubbahub_child_gender', $gender, $id);
        update_field('field_bubbahub_child_status', $status, $id);
        update_field('field_bubbahub_child_date_of_birth', $status === 'born' ? $dob : '', $id);
        update_field('field_bubbahub_child_due_date', $status === 'expecting' ? $due : '', $id);
        update_field('field_bubbahub_child_avatar_url', $avatar, $id);
        update_field('field_bubbahub_child_age_group', $status === 'born' ? ($manual_age_group ?: self::age_group($dob)) : '', $id);
        update_field('field_bubbahub_child_allergies', $allergies, $id);
        update_field('field_bubbahub_child_notes', $notes, $id);
        update_field('field_bubbahub_child_school_name', $school_name, $id);
        update_field('field_bubbahub_child_school_application_status', $school_status, $id);
        update_field('field_bubbahub_child_school_application_deadline', $school_deadline, $id);
        update_field('field_bubbahub_child_school_year', $school_year, $id);
        update_field('field_bubbahub_child_ofsted_rating', $ofsted, $id);
        update_field('field_bubbahub_child_nap_schedule', $nap_schedule, $id);

        if (taxonomy_exists('location')) {
            wp_set_post_terms($id, $location_id ? [$location_id] : [], 'location', false);
        }

        return $id;
    }

    private static function save_bump_post(int $id): void {
        $nickname = sanitize_text_field(wp_unslash($_POST['bump_nickname'] ?? ''));
        $due = sanitize_text_field(wp_unslash($_POST['bump_due_date'] ?? ''));

        wp_update_post(['ID'=>$id,'post_title'=>$nickname ?: 'My bump']);
        update_field('field_bubbahub_child_name', $nickname ?: 'My bump', $id);
        update_field('field_bubbahub_child_nickname', $nickname, $id);
        update_field('field_bubbahub_child_status', 'expecting', $id);
        update_field('field_bubbahub_child_due_date', $due, $id);
    }

    private static function get_children(int $user_id): array {
        return get_posts([
            'post_type'=>self::CHILD_POST_TYPE,
            'post_status'=>['publish','private'],
            'author'=>$user_id,
            'posts_per_page'=>-1,
            'orderby'=>'date',
            'order'=>'ASC',
            'meta_query'=>[['key'=>'child_status','value'=>'born','compare'=>'=']],
        ]);
    }

    private static function get_bumps(int $user_id): array {
        return get_posts([
            'post_type'=>self::CHILD_POST_TYPE,
            'post_status'=>['publish','private'],
            'author'=>$user_id,
            'posts_per_page'=>-1,
            'orderby'=>'date',
            'order'=>'ASC',
            'meta_query'=>[['key'=>'child_status','value'=>'expecting','compare'=>'=']],
        ]);
    }

    private static function bump_is_38_weeks(string $due_date): bool {
        try {
            $due = new \DateTimeImmutable($due_date);
            $today = new \DateTimeImmutable('today');
            return $today >= $due->modify('-14 days');
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function convert_bump_to_child(int $id): void {
        $nickname = (string) get_field('field_bubbahub_child_nickname', $id);
        $name = (string) get_field('field_bubbahub_child_name', $id);
        $name = $name ?: ($nickname ?: 'Baby');
        $today = current_time('Y-m-d');

        wp_update_post(['ID'=>$id,'post_title'=>$name]);
        update_field('field_bubbahub_child_name', $name, $id);
        update_field('field_bubbahub_child_status', 'born', $id);
        update_field('field_bubbahub_child_date_of_birth', $today, $id);
        update_field('field_bubbahub_child_due_date', '', $id);
        update_field('field_bubbahub_child_age_group', '0-3', $id);
    }

    private static function user_owns_child(int $id, int $user_id): bool {
        $post = get_post($id);
        return $post instanceof \WP_Post && $post->post_type === self::CHILD_POST_TYPE && (int)$post->post_author === $user_id;
    }

    private static function migrate_legacy_profiles(int $user_id): void {
        if (get_user_meta($user_id, '_bh_myhub_acf_migrated', true)) return;

        $legacy = get_user_meta($user_id, '_bh_children', true);
        if (is_array($legacy)) foreach ($legacy as $child) {
            $name = sanitize_text_field($child['name'] ?? '');
            if (!$name) continue;
            $id = wp_insert_post(['post_type'=>self::CHILD_POST_TYPE,'post_status'=>'publish','post_author'=>$user_id,'post_title'=>$name], true);
            if (is_wp_error($id)) continue;
            $dob = sanitize_text_field($child['dob'] ?? '');
            $legacy_gender = sanitize_key($child['gender'] ?? '');
            if (!in_array($legacy_gender, ['girl','boy','prefer_not_to_say'], true)) $legacy_gender = '';
            update_field('field_bubbahub_child_name',$name,$id);
            update_field('field_bubbahub_child_status','born',$id);
            update_field('field_bubbahub_child_gender',$legacy_gender,$id);
            update_field('field_bubbahub_child_date_of_birth',$dob,$id);
            update_field('field_bubbahub_child_age_group',self::age_group($dob),$id);
            if (!empty($child['avatar_id'])) {
                $url = wp_get_attachment_url((int)$child['avatar_id']);
                if ($url) update_field('field_bubbahub_child_avatar_url',$url,$id);
            }
        }

        $bump = get_user_meta($user_id, '_bh_bump', true);
        if (is_array($bump) && !empty($bump['due_date'])) {
            $id = wp_insert_post(['post_type'=>self::CHILD_POST_TYPE,'post_status'=>'publish','post_author'=>$user_id,'post_title'=>sanitize_text_field($bump['nickname'] ?? 'My bump')],true);
            if (!is_wp_error($id)) {
                update_field('field_bubbahub_child_name',sanitize_text_field($bump['nickname'] ?? 'My bump'),$id);
                update_field('field_bubbahub_child_nickname',sanitize_text_field($bump['nickname'] ?? ''),$id);
                update_field('field_bubbahub_child_status','expecting',$id);
                update_field('field_bubbahub_child_due_date',sanitize_text_field($bump['due_date']),$id);
            }
        }

        update_user_meta($user_id, '_bh_myhub_acf_migrated', 1);
    }

    private static function age_group(string $dob): string {
        if (!$dob) return '';
        try {
            $birth=new \DateTimeImmutable($dob); $today=new \DateTimeImmutable('today');
            if($birth>$today)return '';
            $months=((int)$today->format('Y')-(int)$birth->format('Y'))*12+((int)$today->format('n')-(int)$birth->format('n'));
            if((int)$today->format('d')<(int)$birth->format('d'))$months--;
            if($months<3)return'0-3'; if($months<6)return'3-6'; if($months<9)return'6-9'; if($months<12)return'9-12';
            $years=intdiv($months,12); if($years<2)return'1-3'; if($years<3)return'2-4'; if($years<5)return'3-5'; return'5-plus';
        } catch(\Exception $e){return '';}
    }

    private static function school_tracker(int $child_id, string $dob): array {
        if(!$dob)return [];
        $deadline=(string)get_field('school_application_deadline',$child_id);
        $url='';
        $label='School application deadline';

        if(!$deadline){
            $birth=new \DateTimeImmutable($dob);
            $fourth=$birth->modify('+4 years');
            $year=(int)$fourth->format('Y');
            if((int)$fourth->format('m')>8)$year++;
            $deadline=$year.'-01-15';
            $label='Reception application deadline';
        }

        $location_ids = wp_get_post_terms($child_id, 'location', ['fields'=>'ids']);
        if (!is_wp_error($location_ids) && $location_ids) {
            $location_id = (int)$location_ids[0];
            $location_deadline = (string)get_field('school_primary_application_deadline', 'location_' . $location_id);
            $location_url = (string)get_field('school_admissions_url', 'location_' . $location_id);
            if ($location_deadline) {
                $deadline = $location_deadline;
                $label = 'Reception application deadline';
            }
            if ($location_url) $url = $location_url;
        }

        try {
            $date=new \DateTimeImmutable($deadline); $today=new \DateTimeImmutable('today');
            if($date<$today)return['label'=>$label,'countdown'=>'Deadline passed — check your local authority for late applications.','url'=>$url];
            $diff=$today->diff($date); $parts=[];
            if($diff->y)$parts[]=$diff->y.' '.($diff->y===1?'year':'years');
            if($diff->m)$parts[]=$diff->m.' '.($diff->m===1?'month':'months');
            $parts[]=$diff->d.' '.($diff->d===1?'day':'days');
            return['label'=>$label,'countdown'=>implode(', ',$parts).' to apply','url'=>$url];
        }catch(\Exception $e){return[];}
    }

    private static function antenatal_tracker(string $due_date): array {
        try {
            $due=new \DateTimeImmutable($due_date); $today=new \DateTimeImmutable('today');
            if($due<$today)return['countdown'=>'Due date has passed','classes'=>'Attend Antenatal classes: pregnancy complete'];
            $diff=$today->diff($due); $parts=[];
            if($diff->m)$parts[]=$diff->m.' '.($diff->m===1?'month':'months');
            $parts[]=$diff->d.' '.($diff->d===1?'day':'days');
            return['countdown'=>implode(', ',$parts).' until due date','classes'=>'Attend Antenatal classes: '.$due->modify('-12 weeks')->format('F').' to '.$due->modify('-8 weeks')->format('F')];
        }catch(\Exception $e){return[];}
    }

    private static function family_age_label(string $dob): string {
        try{
            $birth=new \DateTimeImmutable($dob);$today=new \DateTimeImmutable('today');if($birth>$today)return'';
            $diff=$birth->diff($today);
            if($diff->y>0)return$diff->y.' '.($diff->y===1?'year':'years').' old';
            if($diff->m>0)return$diff->m.' '.($diff->m===1?'month':'months').' old';
            return'Under 1 month';
        }catch(\Exception $e){return'';}
    }

    private static function render_saved_search_cards(int $user_id): string {
        $saved = get_user_meta($user_id, '_bh_saved_searches', true);
        if (!is_array($saved) || !$saved) {
            return '<div class="bh-saved-searches__empty"><strong>No saved searches yet</strong><p>Run a search in the Bubba Hub directory and save it to see it here.</p><a class="bh-saved-searches__button" href="' . esc_url(home_url('/directory/')) . '">Browse the directory</a></div>';
        }

        ob_start();
        echo '<div class="bh-saved-searches__list">';
        foreach ($saved as $search_id => $item) {
            if (!is_array($item)) continue;
            $name = sanitize_text_field((string) ($item['name'] ?? 'Saved search'));
            $url = esc_url((string) ($item['url'] ?? ''));
            if (!$url) continue;
            $criteria = DirectorySearch::saved_search_criteria($url);

            echo '<article class="bh-saved-searches__card">';
            echo '<div><h2>🔎 ' . esc_html($name) . '</h2>';
            if ($criteria) {
                echo '<dl class="bh-saved-searches__criteria">';
                foreach ($criteria as $label => $value) {
                    echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
                }
                echo '</dl>';
            } else {
                echo '<p class="bh-saved-searches__empty-criteria">No filters saved with this search.</p>';
            }
            echo '</div><div class="bh-saved-searches__actions">';
            echo '<button type="button" class="bh-saved-searches__button bh-saved-searches__edit" data-bh-edit-search="' . esc_attr((string) $search_id) . '">Edit</button>';
            echo '<a class="bh-saved-searches__button" href="' . esc_url($url) . '">Search Again</a>';
            echo '<button type="button" class="bh-saved-searches__button bh-saved-searches__share" data-bh-share-url="' . esc_attr($url) . '" data-bh-share-name="' . esc_attr($name) . '">Share</button>';
            echo '<form method="post" class="bh-saved-searches__delete">';
            wp_nonce_field('bh_delete_saved_search', 'bh_delete_saved_search_nonce');
            echo '<input type="hidden" name="bh_delete_saved_search" value="' . esc_attr((string) $search_id) . '">';
            echo '<button type="submit">Delete</button>';
            echo '</form></div></article>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function redirect_planner(): void {
        wp_safe_redirect(self::planner_url());
        exit;
    }

    private static function planner_url(): string {
        $page = get_page_by_path('my-hub/planner');
        return $page instanceof WP_Post ? get_permalink($page) : home_url('/my-hub/planner/');
    }

    private static function redirect_saved(): void {
        $url=remove_query_arg('bh_hub_saved',self::my_hub_url());
        wp_safe_redirect(add_query_arg('bh_hub_saved','1',$url));
        exit;
    }

    private static function my_hub_url(): string {
        global $post;
        if($post instanceof \WP_Post && has_shortcode((string)$post->post_content,'bh_my_hub'))return get_permalink($post);
        $page=get_page_by_path('my-hub');
        return $page instanceof \WP_Post ? get_permalink($page) : home_url('/');
    }


    public static function handle_advanced_settings(): void {
        if (!is_user_logged_in() || empty($_POST['bh_advanced_settings_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['bh_advanced_settings_action']));
        if ('save' !== $action) {
            return;
        }

        if (empty($_POST['bh_advanced_settings_nonce']) || !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['bh_advanced_settings_nonce'])),
            'bh_advanced_settings_save'
        )) {
            return;
        }

        $user_id = get_current_user_id();
        $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last_name = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $display_name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $website = esc_url_raw(wp_unslash($_POST['website'] ?? ''));

        if (!$display_name) {
            $display_name = trim($first_name . ' ' . $last_name);
        }
        if (!$display_name) {
            $display_name = (string) wp_get_current_user()->user_login;
        }

        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
            'description' => $description,
            'user_url' => $website,
        ]);

        $settings = [
            'profile_visibility' => !empty($_POST['profile_visibility']) ? 'public' : 'private',
            'directory_visibility' => !empty($_POST['directory_visibility']) ? 'public' : 'private',
            'email_updates' => !empty($_POST['email_updates']) ? 'yes' : 'no',
            'family_personalisation' => !empty($_POST['family_personalisation']) ? 'yes' : 'no',
        ];
        update_user_meta($user_id, '_bh_advanced_settings', $settings);

        $valid_planner_days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $hidden_planner_days = isset($_POST['planner_hidden_days']) && is_array($_POST['planner_hidden_days'])
            ? array_values(array_intersect($valid_planner_days, array_map('sanitize_key', wp_unslash($_POST['planner_hidden_days']))))
            : [];
        update_user_meta($user_id, '_bh_planner_preferences', ['hidden_days' => $hidden_planner_days]);

        $redirect = wp_get_referer() ?: self::my_hub_url();
        wp_safe_redirect(add_query_arg('bh_settings_saved', '1', remove_query_arg('bh_settings_saved', $redirect)));
        exit;
    }

    private static function account_settings_url(): string {
        global $post;
        if ($post instanceof \WP_Post && has_shortcode((string) $post->post_content, 'bh_account_settings')) return get_permalink($post);
        $page = get_page_by_path('account');
        return $page instanceof \WP_Post ? get_permalink($page) : home_url('/account/');
    }

    private static function ultimate_member_account_url(): string {
        if (function_exists('um_get_core_page')) {
            $url = um_get_core_page('account');
            if ($url) return (string) $url;
        }
        return '';
    }

    public static function advanced_settings_shortcode(): string {
        if (!is_user_logged_in()) {
            $url = function_exists('um_get_core_page') ? um_get_core_page('login') : wp_login_url();
            return '<div class="bh-my-hub bh-my-hub--login"><h2>My Bubba Hub settings</h2><p>Please log in to manage your settings.</p><a class="bh-my-hub__button" href="' . esc_url($url) . '">Log in</a></div>';
        }

        $user = wp_get_current_user();
        $settings = get_user_meta($user->ID, '_bh_advanced_settings', true);
        if (!is_array($settings)) $settings = [];

        $profile_visibility = ($settings['profile_visibility'] ?? 'private') === 'public';
        $directory_visibility = ($settings['directory_visibility'] ?? 'public') === 'public';
        $email_updates = ($settings['email_updates'] ?? 'yes') === 'yes';
        $family_personalisation = ($settings['family_personalisation'] ?? 'yes') === 'yes';
        $planner_preferences = get_user_meta($user->ID, '_bh_planner_preferences', true);
        $hidden_planner_days = is_array($planner_preferences) && !empty($planner_preferences['hidden_days'])
            ? array_values(array_intersect(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'], array_map('sanitize_key', (array) $planner_preferences['hidden_days'])))
            : [];
        $planner_days = [
            'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
            'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
        ];

        $directorist_active = function_exists('directorist_get_user_favorites') || post_type_exists('at_biz_dir');

        ob_start(); ?>
        <div class="bh-my-hub bh-my-hub--settings bh-my-hub--account-settings">
            <div class="bh-my-hub__intro">
                <div>
                    <p class="bh-my-hub__eyebrow">Your Account</p>
                    <h1>Account Settings</h1>
                    <p>One place for your Bubba Hub, family, directory and account settings.</p>
                </div>
                <a class="bh-my-hub__account-link" href="<?php echo esc_url(self::my_hub_url()); ?>">Back to My Hub</a>
            </div>

            <?php if (isset($_GET['bh_settings_saved'])): ?>
                <div class="bh-my-hub__notice" role="status">Your settings have been saved.</div>
            <?php endif; ?>

            <nav class="bh-my-hub__settings-tabs" role="tablist" aria-label="Account settings sections">
                <button type="button" class="bh-my-hub__settings-tab is-active" role="tab" aria-selected="true" aria-controls="bh-settings-panel-profile" data-bh-settings-tab="profile" id="bh-settings-tab-profile">Profile</button>
                <button type="button" class="bh-my-hub__settings-tab" role="tab" aria-selected="false" aria-controls="bh-settings-panel-family" data-bh-settings-tab="family" id="bh-settings-tab-family">Family Hub</button>
                <button type="button" class="bh-my-hub__settings-tab" role="tab" aria-selected="false" aria-controls="bh-settings-panel-privacy" data-bh-settings-tab="privacy" id="bh-settings-tab-privacy">Privacy</button>
                <button type="button" class="bh-my-hub__settings-tab" role="tab" aria-selected="false" aria-controls="bh-settings-panel-planner" data-bh-settings-tab="planner" id="bh-settings-tab-planner">My Planner</button>
                <button type="button" class="bh-my-hub__settings-tab" role="tab" aria-selected="false" aria-controls="bh-settings-panel-notifications" data-bh-settings-tab="notifications" id="bh-settings-tab-notifications">Notifications</button>
                <button type="button" class="bh-my-hub__settings-tab" role="tab" aria-selected="false" aria-controls="bh-settings-panel-directory" data-bh-settings-tab="directory" id="bh-settings-tab-directory">Directory</button>
                <button type="button" class="bh-my-hub__settings-tab" role="tab" aria-selected="false" aria-controls="bh-settings-panel-account" data-bh-settings-tab="account" id="bh-settings-tab-account">Account & Security</button>
            </nav>

            <form method="post" class="bh-my-hub__settings-form">
                <?php wp_nonce_field('bh_advanced_settings_save', 'bh_advanced_settings_nonce'); ?>
                <input type="hidden" name="bh_advanced_settings_action" value="save">

                <section id="bh-settings-panel-profile" class="bh-my-hub__settings-card bh-my-hub__settings-panel" data-bh-settings-panel="profile" role="tabpanel" aria-labelledby="bh-settings-tab-profile">
                    <div class="bh-my-hub__settings-heading">
                        <span class="bh-my-hub__icon" aria-hidden="true">👤</span>
                        <div><h2>Profile</h2><p>This is your shared WordPress account profile. Directorist can use the same account for your directory activity.</p></div>
                    </div>
                    <div class="bh-my-hub__fields bh-my-hub__settings-fields">
                        <label>First name<input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" autocomplete="given-name"></label>
                        <label>Last name<input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" autocomplete="family-name"></label>
                        <label class="bh-my-hub__settings-field--full">Display name<input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" autocomplete="name"></label>
                        <label class="bh-my-hub__settings-field--full">Website<input type="url" name="website" value="<?php echo esc_attr($user->user_url); ?>" placeholder="https://"></label>
                        <label class="bh-my-hub__settings-field--full">About you<textarea name="description" rows="5" placeholder="A short introduction for your profile"><?php echo esc_textarea($user->description); ?></textarea></label>
                    </div>
                </section>

                <section id="bh-settings-panel-family" class="bh-my-hub__settings-card bh-my-hub__settings-panel" data-bh-settings-panel="family" role="tabpanel" aria-labelledby="bh-settings-tab-family" hidden>
                    <div class="bh-my-hub__settings-heading">
                        <span class="bh-my-hub__icon" aria-hidden="true">👨‍👩‍👧</span>
                        <div><h2>Family Hub</h2><p>Choose how much of your Bubba Hub experience is personalised around your family.</p></div>
                    </div>
                    <label class="bh-my-hub__setting-switch">
                        <input type="checkbox" name="family_personalisation" value="1" <?php checked($family_personalisation); ?>>
                        <span><strong>Family personalisation</strong><small>Use your family details to personalise My Hub, planning and family-related features.</small></span>
                    </label>
                </section>

                <section id="bh-settings-panel-planner" class="bh-my-hub__settings-card bh-my-hub__settings-panel" data-bh-settings-panel="planner" role="tabpanel" aria-labelledby="bh-settings-tab-planner" hidden>
                    <div class="bh-my-hub__settings-heading">
                        <span class="bh-my-hub__icon" aria-hidden="true">🗓️</span>
                        <div><h2>My Planner Preferences</h2><p>Choose which days appear in your weekly planner. Hiding a day does not delete any activities planned for it.</p></div>
                    </div>
                    <div class="bh-my-hub__planner-preference-days">
                        <?php foreach ($planner_days as $day_key => $day_label): ?>
                            <label class="bh-my-hub__setting-switch">
                                <input type="checkbox" name="planner_hidden_days[]" value="<?php echo esc_attr($day_key); ?>" <?php checked(in_array($day_key, $hidden_planner_days, true)); ?>>
                                <span><strong>Hide <?php echo esc_html($day_label); ?></strong><small>Remove this day from the Planner view only.</small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="bh-my-hub__settings-help">Tip: hide weekends if you mainly use Bubba Hub for weekday groups.</p>
                </section>

                <section id="bh-settings-panel-privacy" class="bh-my-hub__settings-card bh-my-hub__settings-panel" data-bh-settings-panel="privacy" role="tabpanel" aria-labelledby="bh-settings-tab-privacy" hidden>
                    <div class="bh-my-hub__settings-heading">
                        <span class="bh-my-hub__icon" aria-hidden="true">🔒</span>
                        <div><h2>Privacy</h2><p>These controls are stored with your Bubba Hub account and are ready for future profile features.</p></div>
                    </div>
                    <label class="bh-my-hub__setting-switch">
                        <input type="checkbox" name="profile_visibility" value="1" <?php checked($profile_visibility); ?>>
                        <span><strong>Make my profile visible</strong><small>Allow your member profile to be shown where Bubba Hub supports member profiles.</small></span>
                    </label>
                    <label class="bh-my-hub__setting-switch">
                        <input type="checkbox" name="directory_visibility" value="1" <?php checked($directory_visibility); ?>>
                        <span><strong>Show me in directory profile features</strong><small>Keep your account available for Directorist-connected profile features.</small></span>
                    </label>
                </section>

                <section id="bh-settings-panel-notifications" class="bh-my-hub__settings-card bh-my-hub__settings-panel" data-bh-settings-panel="notifications" role="tabpanel" aria-labelledby="bh-settings-tab-notifications" hidden>
                    <div class="bh-my-hub__settings-heading">
                        <span class="bh-my-hub__icon" aria-hidden="true">🔐</span>
                        <div><h2>Notifications</h2><p>Control general Bubba Hub email updates. More notification types can be added here later.</p></div>
                    </div>
                    <label class="bh-my-hub__setting-switch">
                        <input type="checkbox" name="email_updates" value="1" <?php checked($email_updates); ?>>
                        <span><strong>Email updates</strong><small>Receive useful Bubba Hub updates and account notifications.</small></span>
                    </label>
                </section>

                <section id="bh-settings-panel-directory" class="bh-my-hub__settings-card bh-my-hub__settings-card--directorist bh-my-hub__settings-panel" data-bh-settings-panel="directory" role="tabpanel" aria-labelledby="bh-settings-tab-directory" hidden>
                    <div class="bh-my-hub__settings-heading">
                        <span class="bh-my-hub__icon" aria-hidden="true">🔎</span>
                        <div><h2>Directory Profile</h2><p>Your identity is shared across Bubba Hub and Directorist through the same WordPress account. Directory-specific fields can be added here as we build them.</p></div>
                    </div>
                    <div class="bh-my-hub__integration-status">
                        <span class="bh-my-hub__integration-dot <?php echo $directorist_active ? 'is-connected' : ''; ?>" aria-hidden="true"></span>
                        <div>
                            <strong><?php echo $directorist_active ? 'Directorist connected' : 'Directory features available when enabled'; ?></strong>
                            <p>Basic profile details above are saved to the shared account, so they do not need to be maintained separately in Bubba Hub.</p>
                        </div>
                    </div>
                    <div class="bh-my-hub__settings-links">
                        <?php if ($directorist_active): ?>
                            <a class="bh-my-hub__button bh-my-hub__button--outline" href="<?php echo esc_url(get_author_posts_url($user->ID)); ?>">View public profile</a>
                        <?php endif; ?>
                    </div>
                </section>

                <section id="bh-settings-panel-account" class="bh-my-hub__settings-card bh-my-hub__settings-card--account bh-my-hub__settings-panel" data-bh-settings-panel="account" role="tabpanel" aria-labelledby="bh-settings-tab-account" hidden>
                    <div class="bh-my-hub__settings-heading">
                        <span class="bh-my-hub__icon" aria-hidden="true">🛡️</span>
                        <div><h2>Account & Security</h2><p>Account-wide controls belong here for every Bubba Hub user type. Ultimate Member remains the owner of login/security actions where it provides them.</p></div>
                    </div>
                    <div class="bh-my-hub__account-actions">
                        <?php $um_account_url = self::ultimate_member_account_url(); ?>
                        <?php if ($um_account_url): ?>
                            <a class="bh-my-hub__account-action" href="<?php echo esc_url($um_account_url); ?>"><strong>Ultimate Member account</strong><span>Manage password, security and any Ultimate Member-specific account fields.</span></a>
                        <?php endif; ?>
                        <a class="bh-my-hub__account-action" href="<?php echo esc_url(self::my_hub_url()); ?>"><strong>My Hub</strong><span>Return to your family, planner, saved searches and directory activity.</span></a>
                    </div>
                    <dl class="bh-my-hub__account-details">
                        <div><dt>Email</dt><dd><?php echo esc_html($user->user_email); ?></dd></div>
                        <div><dt>Username</dt><dd><?php echo esc_html($user->user_login); ?></dd></div>
                    </dl>
                </section>

                <div class="bh-my-hub__settings-actions">
                    <button class="bh-my-hub__button" type="submit">Save settings</button>
                    <a class="bh-my-hub__button bh-my-hub__button--outline" href="<?php echo esc_url(self::my_hub_url()); ?>">Cancel</a>
                </div>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function modal_script(): void {
        if(!self::is_my_hub_page())return;
        ?>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
            document.querySelectorAll('[data-bh-modal]').forEach(function(button){
                button.addEventListener('click',function(){
                    var panel=document.querySelector('[data-bh-modal-panel="'+button.getAttribute('data-bh-modal')+'"]');
                    if(panel){panel.hidden=false;document.body.classList.add('bh-modal-open');}
                });
            });
            document.querySelectorAll('[data-bh-modal-close]').forEach(function(button){
                button.addEventListener('click',function(){
                    var panel=button.closest('.bh-my-hub__modal');
                    if(panel){panel.hidden=true;document.body.classList.remove('bh-modal-open');}
                });
            });
            var accountTabs=document.querySelectorAll('[data-bh-settings-tab]');
            var accountPanels=document.querySelectorAll('[data-bh-settings-panel]');
            function activateAccountTab(tabName, updateHash){
                if(!accountTabs.length){return;}
                var valid=false;
                accountTabs.forEach(function(tab){
                    var active=tab.getAttribute('data-bh-settings-tab')===tabName;
                    if(active){valid=true;}
                    tab.classList.toggle('is-active',active);
                    tab.setAttribute('aria-selected',active?'true':'false');
                    tab.setAttribute('tabindex',active?'0':'-1');
                });
                accountPanels.forEach(function(panel){
                    var active=panel.getAttribute('data-bh-settings-panel')===tabName;
                    panel.hidden=!active;
                });
                if(valid && updateHash){
                    history.replaceState(null,'','#'+encodeURIComponent(tabName));
                }
            }
            if(accountTabs.length){
                var initialTab=decodeURIComponent(window.location.hash.replace('#','')) || 'profile';
                activateAccountTab(initialTab,false);
                accountTabs.forEach(function(tab){
                    tab.addEventListener('click',function(){activateAccountTab(tab.getAttribute('data-bh-settings-tab'),true);});
                    tab.addEventListener('keydown',function(event){
                        if(event.key!=='ArrowRight' && event.key!=='ArrowLeft'){return;}
                        event.preventDefault();
                        var tabs=Array.prototype.slice.call(accountTabs);
                        var index=tabs.indexOf(tab);
                        var next=event.key==='ArrowRight' ? (index+1)%tabs.length : (index-1+tabs.length)%tabs.length;
                        tabs[next].focus();
                        activateAccountTab(tabs[next].getAttribute('data-bh-settings-tab'),true);
                    });
                });
                window.addEventListener('hashchange',function(){
                    activateAccountTab(decodeURIComponent(window.location.hash.replace('#','')) || 'profile',false);
                });
            }

            document.querySelectorAll('[data-bh-family-view]').forEach(function(button){
                button.addEventListener('click',function(){
                    var family=button.closest('#bh-my-hub-family');
                    if(!family){return;}
                    var view=button.getAttribute('data-bh-family-view');
                    family.querySelectorAll('[data-bh-family-view]').forEach(function(toggle){
                        var active=toggle.getAttribute('data-bh-family-view')===view;
                        toggle.classList.toggle('is-active',active);
                        toggle.setAttribute('aria-pressed',active?'true':'false');
                    });
                    family.querySelectorAll('[data-bh-family-view-panel]').forEach(function(panel){
                        panel.classList.toggle('bh-my-hub__family-view-hidden',panel.getAttribute('data-bh-family-view-panel')!==view);
                    });
                });
            });

            document.addEventListener('keydown',function(event){
                if(event.key==='Escape'){
                    document.querySelectorAll('.bh-my-hub__modal:not([hidden])').forEach(function(panel){panel.hidden=true;});
                    document.body.classList.remove('bh-modal-open');
                }
            });
        });
        </script>
        <?php
    }
}
