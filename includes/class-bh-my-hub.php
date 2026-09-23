<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class MyHub {
    private const CHILD_POST_TYPE = 'bh_child';

    public static function register(): void {
        add_shortcode('bh_my_hub', [self::class, 'shortcode']);
        add_action('init', [self::class, 'handle_forms']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('wp_footer', [self::class, 'modal_script']);
    }

    public static function assets(): void {
        if (!self::is_my_hub_page()) {
            return;
        }

        wp_enqueue_style(
            'bh-my-hub',
            BH_PLUGIN_URL . 'assets/css/bh-my-hub.css',
            [],
            BH_PLUGIN_VERSION
        );
    }

    private static function is_my_hub_page(): bool {
        global $post;
        if (!$post instanceof \WP_Post) {
            return false;
        }
        return has_shortcode((string) $post->post_content, 'bh_my_hub');
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
                <a href="#bh-my-hub-groups" class="bh-my-hub__quick-link"><span aria-hidden="true">🔎</span><strong>Find Activities</strong><small>Discover local groups</small></a>
                <a href="#bh-my-hub-planner" class="bh-my-hub__quick-link"><span aria-hidden="true">📅</span><strong>My Planner</strong><small>Plan your week</small></a>
                <a href="<?php echo esc_url(DirectorySearch::saved_searches_url()); ?>" class="bh-my-hub__quick-link"><span aria-hidden="true">♡</span><strong>Saved Searches</strong><small>Quickly revisit searches</small></a>
            </nav>

            <?php if (isset($_GET['bh_hub_saved'])): ?>
                <div class="bh-my-hub__notice" role="status">Your details have been saved.</div>
            <?php endif; ?>

            <div class="bh-my-hub__grid">
                <section id="bh-my-hub-family" class="bh-my-hub__card bh-my-hub__family-container">
                    <div class="bh-my-hub__family-scroll">
                    <div class="bh-my-hub__family-panel">
                    <div class="bh-my-hub__card-head">
                        <div><span class="bh-my-hub__icon">👨‍👩‍👧</span><h2>My Family</h2></div>
                        <span class="bh-my-hub__count"><?php echo esc_html(count($children)); ?></span>
                    </div>
                    <div class="bh-my-hub__family-actions">
                        <button type="button" class="bh-my-hub__button bh-my-hub__open-modal" data-bh-modal="child">Add child</button>
                    </div>

                    <?php if ($children): ?>
                        <div class="bh-my-hub__profiles">
                            <?php foreach ($children as $child): ?>
                                <?php
                                $id = (int) $child->ID;
                                $name = (string) get_field('child_name', $id);
                                $dob = (string) get_field('child_date_of_birth', $id);
                                $avatar = (string) get_field('field_bubbahub_child_avatar_url', $id);
                                $gender = (string) get_field('field_bubbahub_child_gender', $id);
                                $age_group = (string) get_field('field_bubbahub_child_age_group', $id);
                                $school = self::school_tracker($id, $dob);
                                ?>
                                <article class="bh-my-hub__profile">
                                    <?php if ($avatar): ?>
                                        <img class="bh-my-hub__avatar" src="<?php echo esc_url($avatar); ?>" alt="">
                                    <?php else: ?>
                                        <span class="bh-my-hub__avatar bh-my-hub__avatar--placeholder" aria-hidden="true">👶</span>
                                    <?php endif; ?>
                                    <strong><?php echo esc_html($name ?: 'Child'); ?></strong>
                                    <?php if ($dob): ?><span><?php echo esc_html(self::age_label($dob)); ?></span><?php endif; ?>
                                    <?php if ($gender): ?><span><?php echo esc_html(ucwords(str_replace('_', ' ', $gender))); ?></span><?php endif; ?>
                                    <?php if ($age_group): ?><span>Age group: <?php echo esc_html($age_group); ?></span><?php endif; ?>

                                    <?php if ($school): ?>
                                        <div class="bh-my-hub__school-tracker">
                                            <strong>🎓 School tracker</strong>
                                            <span><?php echo esc_html($school['label']); ?></span>
                                            <small><?php echo esc_html($school['countdown']); ?></small>
                                            <?php if (!empty($school['url'])): ?>
                                                <a href="<?php echo esc_url($school['url']); ?>" target="_blank" rel="noopener">School admissions</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <button type="button" class="bh-my-hub__edit-link bh-my-hub__open-modal" data-bh-modal="edit-child-<?php echo esc_attr((string) $id); ?>">Edit</button>

                                    <form method="post" class="bh-my-hub__delete">
                                        <?php wp_nonce_field('bh_my_hub_delete_child_' . $id, 'bh_my_hub_nonce'); ?>
                                        <input type="hidden" name="bh_my_hub_action" value="delete_child">
                                        <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $id); ?>">
                                        <button type="submit">Remove</button>
                                    </form>

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
                    <?php else: ?>
                        <p class="bh-my-hub__muted">Add your children to personalise Bubba Hub around your family.</p>
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
                    </div>

                <section class="bh-my-hub__family-panel">
                    <div class="bh-my-hub__card-head">
                        <div><span class="bh-my-hub__icon">🤰</span><h2>My Bumps</h2></div>
                        <span class="bh-my-hub__count"><?php echo esc_html(count($bumps)); ?></span>
                    </div>

                    <div class="bh-my-hub__family-actions">
                        <button type="button" class="bh-my-hub__button bh-my-hub__button--outline bh-my-hub__open-modal" data-bh-modal="add-bump">Add bump</button>
                    </div>

                    <?php if ($bumps): ?>
                        <div class="bh-my-hub__profiles">
                            <?php foreach ($bumps as $bump): ?>
                                <?php
                                $bump_id = (int) $bump->ID;
                                $nickname = (string) get_field('child_nickname', $bump_id);
                                $due = (string) get_field('child_due_date', $bump_id);
                                $tracker = $due ? self::antenatal_tracker($due) : [];
                                $baby_is_here = $due ? self::bump_is_38_weeks($due) : false;
                                ?>
                                <article class="bh-my-hub__profile bh-my-hub__bump-profile">
                                    <strong><?php echo esc_html($nickname ?: 'My bump'); ?></strong>
                                    <?php if ($due): ?>
                                        <span>Due <?php echo esc_html(wp_date(get_option('date_format'), strtotime($due))); ?></span>
                                        <?php if ($tracker): ?>
                                            <div class="bh-my-hub__antenatal-tracker">
                                                <strong>🤰 Antenatal tracker</strong>
                                                <span><?php echo esc_html($tracker['countdown']); ?></span>
                                                <small><?php echo esc_html($tracker['classes']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="bh-my-hub__profile-actions">
                                        <button type="button" class="bh-my-hub__edit-link bh-my-hub__open-modal" data-bh-modal="edit-bump-<?php echo esc_attr((string) $bump_id); ?>">Edit bump</button>
                                        <?php if ($baby_is_here): ?>
                                            <form method="post" class="bh-my-hub__inline-form">
                                                <?php wp_nonce_field('bh_my_hub_baby_is_here_' . $bump_id, 'bh_my_hub_nonce'); ?>
                                                <input type="hidden" name="bh_my_hub_action" value="baby_is_here">
                                                <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $bump_id); ?>">
                                                <button type="submit" class="bh-my-hub__button bh-my-hub__button--success">Baby is here</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="bh-my-hub__delete">
                                            <?php wp_nonce_field('bh_my_hub_delete_bump_' . $bump_id, 'bh_my_hub_nonce'); ?>
                                            <input type="hidden" name="bh_my_hub_action" value="delete_bump">
                                            <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $bump_id); ?>">
                                            <button type="submit">Remove bump</button>
                                        </form>
                                    </div>
                                </article>

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
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="bh-my-hub__muted">Add a bump if you're expecting. You can add more than one for a multiple pregnancy.</p>
                    <?php endif; ?>

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
                </div>
                </section>

                <?php echo self::render_planner_section($user->ID); ?>

                <section id="bh-my-hub-saved-searches" class="bh-my-hub__card bh-my-hub__card--wide">
                    <div class="bh-my-hub__saved-searches">
                        <div class="bh-my-hub__saved-searches-head">
                            <div>
                                <strong>🔎 Saved Searches</strong>
                                <p>Your saved group searches at a glance. Select a search to view and manage all your saved searches.</p>
                            </div>
                            <a class="bh-my-hub__button" href="<?php echo esc_url(DirectorySearch::saved_searches_url()); ?>">View Saved Searches</a>
                        </div>
                        <?php echo self::render_saved_search_cards($user->ID); ?>
                    </div>
                </section>

                <section id="bh-my-hub-groups" class="bh-my-hub__card bh-my-hub__card--wide">
                    <div class="bh-my-hub__card-head"><div><span class="bh-my-hub__icon">👥</span><h2>My Groups</h2></div></div>
                    <div class="bh-my-hub__groups">
                        <article class="bh-my-hub__group"><span class="bh-my-hub__group-icon">🕘</span><div><h3>Recently viewed</h3><p>Groups you have recently looked at.</p></div></article>
                        <article class="bh-my-hub__group"><span class="bh-my-hub__group-icon">📍</span><div><h3>Visited</h3><p>Groups and activities you have marked as visited.</p></div></article>
                        <article class="bh-my-hub__group"><span class="bh-my-hub__group-icon">♡</span><div><h3>Saved</h3><p>Your saved Bubba Hub groups and activities.</p></div></article>
                        <article class="bh-my-hub__group"><span class="bh-my-hub__group-icon">✨</span><div><h3>Suggestions</h3><p>Groups suggested from your family and search preferences.</p></div></article>
                    </div>
                </section>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
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
            <label>Nickname<input type="text" name="child_nickname" maxlength="100" value="<?php echo esc_attr($nickname); ?>"></label>
            <label>Gender
                <select name="gender">
                    <option value="">Select gender</option>
                    <option value="girl" <?php selected($gender, 'girl'); ?>>Girl</option>
                    <option value="boy" <?php selected($gender, 'boy'); ?>>Boy</option>
                    <option value="prefer_not_to_say" <?php selected($gender, 'prefer_not_to_say'); ?>>Prefer not to say</option>
                </select>
            </label>
            <label>Profile type
                <select name="child_status">
                    <option value="born" <?php selected($status, 'born'); ?>>Child born</option>
                    <option value="expecting" <?php selected($status, 'expecting'); ?>>Expecting a baby</option>
                </select>
            </label>
            <label>Date of birth<input type="date" name="child_dob" value="<?php echo esc_attr($dob); ?>"></label>
            <label>Due date<input type="date" name="child_due_date" value="<?php echo esc_attr($due); ?>"></label>
            <label>Location
                <select name="child_location">
                    <option value="">Select location</option>
                    <?php if (!is_wp_error($locations)): foreach ($locations as $location): ?>
                        <option value="<?php echo esc_attr((string) $location->term_id); ?>" <?php selected($selected_location, (int) $location->term_id); ?>><?php echo esc_html($location->name); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </label>
            <label>Age group
                <select name="child_age_group">
                    <option value="">Auto-calculate from date of birth</option>
                    <?php
                    $age_choices = [
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
                    foreach ($age_choices as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($age_group, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Allergies<textarea name="child_allergies" rows="3"><?php echo esc_textarea($allergies); ?></textarea></label>
            <label>Notes<textarea name="child_notes" rows="3"><?php echo esc_textarea($notes); ?></textarea></label>

            <div class="bh-my-hub__field-group">
                <strong>School information</strong>
                <label>School name<input type="text" name="school_name" value="<?php echo esc_attr($school_name); ?>"></label>
                <label>Application status<input type="text" name="school_application_status" value="<?php echo esc_attr($school_status); ?>"></label>
                <label>Application deadline<input type="date" name="school_application_deadline" value="<?php echo esc_attr($school_deadline); ?>"></label>
                <label>School year<input type="text" name="school_year" value="<?php echo esc_attr($school_year); ?>"></label>
                <label>Ofsted rating<input type="text" name="ofsted_rating" value="<?php echo esc_attr($ofsted); ?>"></label>
            </div>

            <div class="bh-my-hub__field-group">
                <strong>Nap schedule</strong>
                <?php
                $nap_days = [
                    'monday' => 'Monday',
                    'tuesday' => 'Tuesday',
                    'wednesday' => 'Wednesday',
                    'thursday' => 'Thursday',
                    'friday' => 'Friday',
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday',
                ];
                foreach ($nap_days as $day_key => $day_label):
                    $nap = $nap_by_day[$day_key] ?? [];
                ?>
                    <div class="bh-my-hub__nap-row">
                        <label>
                            <span><?php echo esc_html($day_label); ?></span>
                            <input type="checkbox" name="nap_schedule[<?php echo esc_attr($day_key); ?>][enabled]" value="1" <?php checked(!empty($nap['enabled'])); ?>>
                        </label>
                        <input type="time" name="nap_schedule[<?php echo esc_attr($day_key); ?>][start_time]" value="<?php echo esc_attr((string) ($nap['start_time'] ?? '')); ?>">
                        <input type="time" name="nap_schedule[<?php echo esc_attr($day_key); ?>][end_time]" value="<?php echo esc_attr((string) ($nap['end_time'] ?? '')); ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <label class="bh-my-hub__avatar-field">Avatar<input type="file" name="child_avatar" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP</small></label>
            <label class="bh-my-hub__avatar-field">Avatar URL<input type="url" name="child_avatar_url" value="<?php echo esc_attr($avatar); ?>" placeholder="https://..."><small>Or use an image URL.</small></label>
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
            self::redirect_saved();
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
            if ($id && self::valid_planner_date($date) && in_array($id, self::saved_activities($user_id), true)) {
                $planner = self::planner_items($user_id);
                $planner[$date] = isset($planner[$date]) && is_array($planner[$date]) ? $planner[$date] : [];
                if (!in_array($id, $planner[$date], true)) $planner[$date][] = $id;
                update_user_meta($user_id, '_bh_planner_items', $planner);
            }
            self::redirect_saved();
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
            self::redirect_saved();
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
        $saved = self::saved_activities($user_id);
        $planner = self::planner_items($user_id);
        $today = current_datetime()->setTime(0, 0);
        $week_start = $today->modify('monday this week');
        $days = [];
        for ($i = 0; $i < 7; $i++) $days[] = $week_start->modify('+' . $i . ' days');

        ob_start(); ?>
        <section id="bh-my-hub-planner" class="bh-my-hub__card bh-my-hub__card--wide">
            <div class="bh-my-hub__card-head">
                <div><span class="bh-my-hub__icon">📅</span><h2>My Planner</h2></div>
                <span class="bh-my-hub__count"><?php echo esc_html(count($saved)); ?> saved</span>
            </div>
            <p class="bh-my-hub__muted">Save activities from the directory, then add them to the day you want to visit.</p>

            <?php if ($saved): ?>
                <div class="bh-my-hub__planner-days">
                    <?php foreach ($days as $day): $date_key = $day->format('Y-m-d'); ?>
                        <div class="bh-my-hub__planner-day">
                            <div class="bh-my-hub__planner-day-head">
                                <strong><?php echo esc_html(wp_date('D', $day->getTimestamp(), wp_timezone())); ?></strong>
                                <span><?php echo esc_html(wp_date('j M', $day->getTimestamp(), wp_timezone())); ?></span>
                            </div>
                            <?php foreach ((array) ($planner[$date_key] ?? []) as $listing_id):
                                $post = get_post(absint($listing_id));
                                if (!$post || $post->post_status !== 'publish') continue;
                                ?>
                                <article class="bh-my-hub__planner-item">
                                    <a href="<?php echo esc_url(get_permalink($post)); ?>"><strong><?php echo esc_html(get_the_title($post)); ?></strong></a>
                                    <form method="post">
                                        <?php wp_nonce_field('bh_my_hub_remove_from_planner', 'bh_my_hub_nonce'); ?>
                                        <input type="hidden" name="bh_my_hub_action" value="remove_from_planner">
                                        <input type="hidden" name="listing_id" value="<?php echo esc_attr((string) $post->ID); ?>">
                                        <input type="hidden" name="planner_date" value="<?php echo esc_attr($date_key); ?>">
                                        <button type="submit">Remove</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                            <?php if (empty($planner[$date_key])): ?>
                                <span class="bh-my-hub__planner-empty">Nothing planned</span>
                            <?php endif; ?>
                            <?php foreach ($saved as $listing_id):
                                $post = get_post($listing_id);
                                if (!$post || $post->post_status !== 'publish' || in_array($listing_id, (array) ($planner[$date_key] ?? []), true)) continue;
                                ?>
                                <form method="post" class="bh-my-hub__planner-add">
                                    <?php wp_nonce_field('bh_my_hub_add_to_planner', 'bh_my_hub_nonce'); ?>
                                    <input type="hidden" name="bh_my_hub_action" value="add_to_planner">
                                    <input type="hidden" name="listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                                    <input type="hidden" name="planner_date" value="<?php echo esc_attr($date_key); ?>">
                                    <button type="submit">+ <?php echo esc_html(get_the_title($post)); ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bh-my-hub__coming">
                    <strong>Start building your family week.</strong>
                    <p>Save an activity from the directory and it will appear here ready to add to your planner.</p>
                </div>
            <?php endif; ?>
        </section>
        <?php return (string) ob_get_clean();
    }

    private static function save_child_post(int $user_id, int $id = 0): int {
        $name = sanitize_text_field(wp_unslash($_POST['child_name'] ?? ''));
        $nickname = sanitize_text_field(wp_unslash($_POST['child_nickname'] ?? ''));
        $gender = sanitize_key(wp_unslash($_POST['gender'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['child_status'] ?? 'born'));
        $dob = sanitize_text_field(wp_unslash($_POST['child_dob'] ?? ''));
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

    private static function age_label(string $dob): string {
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
        if (!is_array($saved) || !$saved) return '<div class="bh-my-hub__saved-searches-empty"><strong>No saved searches yet</strong><p>Save a search from the directory and it will appear here.</p></div>';
        ob_start();
        echo '<div class="bh-my-hub__saved-search-cards">';
        foreach ($saved as $item) {
            if (!is_array($item)) continue;
            $name = sanitize_text_field((string) ($item['name'] ?? 'Saved search'));
            if (empty($item['url'])) continue;
            $parts = wp_parse_url((string) $item['url']);
            $query = [];
            if (!empty($parts['query'])) parse_str($parts['query'], $query);
            $details = [];
            $map = ['bh_search'=>'Search','bh_region'=>'Region','bh_town'=>'Town','bh_day'=>'Day','bh_age_range'=>'Age','bh_category'=>'Category'];
            foreach ($map as $key => $label) {
                if (!isset($query[$key]) || is_array($query[$key]) || trim((string) $query[$key]) === '') continue;
                $value = sanitize_text_field((string) $query[$key]);
                if (in_array($key, ['bh_region','bh_town'], true)) {
                    $term = get_term_by('slug', sanitize_title($value), 'at_biz_dir-location');
                    $value = ($term && !is_wp_error($term)) ? $term->name : ucwords(str_replace(['-','_'], ' ', $value));
                } elseif ($key === 'bh_category') {
                    $term = get_term_by('slug', sanitize_title($value), 'at_biz_dir-category');
                    $value = ($term && !is_wp_error($term)) ? $term->name : ucwords(str_replace(['-','_'], ' ', $value));
                } elseif ($key === 'bh_day') $value = ucfirst($value);
                $details[] = $label . ': ' . $value;
            }
            echo '<article class="bh-my-hub__saved-search-card">';
            echo '<div class="bh-my-hub__saved-search-card-icon" aria-hidden="true">🔎</div>';
            echo '<div class="bh-my-hub__saved-search-card-body">';
            echo '<strong>' . esc_html($name) . '</strong>';
            echo '<p>' . esc_html($details ? implode(' · ', $details) : 'Saved Bubba Hub search') . '</p>';
            echo '</div>';
            echo '<a class="bh-my-hub__saved-search-card-link" href="' . esc_url(DirectorySearch::saved_searches_url()) . '">View more</a>';
            echo '</article>';
        }
        echo '</div>';
        return (string) ob_get_clean();
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
