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
        $bump = self::get_bump($user->ID);

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

            <?php if (isset($_GET['bh_hub_saved'])): ?>
                <div class="bh-my-hub__notice" role="status">Your details have been saved.</div>
            <?php endif; ?>

            <div class="bh-my-hub__grid">
                <section class="bh-my-hub__card">
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
                                $avatar = (string) get_field('avatar_url', $id);
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
                                            <form method="post" class="bh-my-hub__form">
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
                            <form method="post" class="bh-my-hub__form">
                                <?php wp_nonce_field('bh_my_hub_save_child', 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="save_child">
                                <?php self::child_fields(); ?>
                                <button class="bh-my-hub__button" type="submit">Save child</button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="bh-my-hub__card">
                    <div class="bh-my-hub__card-head">
                        <div><span class="bh-my-hub__icon">🤰</span><h2>My Bump</h2></div>
                    </div>

                    <div class="bh-my-hub__family-actions">
                        <button type="button" class="bh-my-hub__button bh-my-hub__button--outline bh-my-hub__open-modal" data-bh-modal="bump"><?php echo $bump ? 'Update bump' : 'Add bump'; ?></button>
                    </div>

                    <?php if ($bump): ?>
                        <?php
                        $bump_id = (int) $bump->ID;
                        $nickname = (string) get_field('child_nickname', $bump_id);
                        $due = (string) get_field('child_due_date', $bump_id);
                        $tracker = $due ? self::antenatal_tracker($due) : [];
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
                            <form method="post" class="bh-my-hub__delete">
                                <?php wp_nonce_field('bh_my_hub_delete_bump_' . $bump_id, 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="delete_bump">
                                <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $bump_id); ?>">
                                <button type="submit">Remove bump</button>
                            </form>
                        </article>
                    <?php else: ?>
                        <p class="bh-my-hub__muted">Add a bump if you're expecting, and Bubba Hub can use your due date for personalised features.</p>
                    <?php endif; ?>

                    <div class="bh-my-hub__modal" data-bh-modal-panel="bump" hidden>
                        <div class="bh-my-hub__modal-backdrop" data-bh-modal-close></div>
                        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true">
                            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
                            <h3><?php echo $bump ? 'Update bump' : 'Add a bump'; ?></h3>
                            <form method="post" class="bh-my-hub__form">
                                <?php wp_nonce_field('bh_my_hub_save_bump', 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="save_bump">
                                <input type="hidden" name="child_id" value="<?php echo $bump ? esc_attr((string) $bump->ID) : ''; ?>">
                                <div class="bh-my-hub__fields">
                                    <label>Nickname<input type="text" name="bump_nickname" maxlength="100" value="<?php echo $bump ? esc_attr((string) get_field('child_nickname', $bump->ID)) : ''; ?>"></label>
                                    <label>Due date<input type="date" name="bump_due_date" value="<?php echo $bump ? esc_attr((string) get_field('child_due_date', $bump->ID)) : ''; ?>" required></label>
                                </div>
                                <button class="bh-my-hub__button" type="submit"><?php echo $bump ? 'Save changes' : 'Save bump'; ?></button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="bh-my-hub__card bh-my-hub__card--wide">
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
        $name = $post_id ? (string) get_field('child_name', $post_id) : '';
        $nickname = $post_id ? (string) get_field('child_nickname', $post_id) : '';
        $status = $post_id ? (string) get_field('child_status', $post_id) : 'born';
        $dob = $post_id ? (string) get_field('child_date_of_birth', $post_id) : '';
        $due = $post_id ? (string) get_field('child_due_date', $post_id) : '';
        $avatar = $post_id ? (string) get_field('avatar_url', $post_id) : '';
        $selected_locations = $post_id ? wp_get_post_terms($post_id, 'location', ['fields' => 'ids']) : [];
        $selected_location = (!is_wp_error($selected_locations) && !empty($selected_locations)) ? (int) $selected_locations[0] : 0;
        $locations = get_terms(['taxonomy' => 'location', 'hide_empty' => false]);
        ?>
        <div class="bh-my-hub__fields">
            <label>Name<input type="text" name="child_name" maxlength="100" value="<?php echo esc_attr($name); ?>" required></label>
            <label>Nickname<input type="text" name="child_nickname" maxlength="100" value="<?php echo esc_attr($nickname); ?>"></label>
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
            <label class="bh-my-hub__avatar-field">Avatar URL<input type="url" name="child_avatar_url" value="<?php echo esc_attr($avatar); ?>" placeholder="https://..."><small>Optional image URL.</small></label>
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
            check_admin_referer('bh_my_hub_save_bump', 'bh_my_hub_nonce');
            $id = absint($_POST['child_id'] ?? 0);
            if (!$id || !self::user_owns_child($id, $user_id)) {
                $existing = self::get_bump($user_id);
                if ($existing) {
                    $id = (int) $existing->ID;
                } else {
                    $created = wp_insert_post(['post_type'=>self::CHILD_POST_TYPE,'post_status'=>'publish','post_author'=>$user_id,'post_title'=>'My bump'], true);
                    $id = is_wp_error($created) ? 0 : (int) $created;
                }
            }
            if ($id) self::save_bump_post($id);
            self::redirect_saved();
        }

        if ($action === 'delete_bump') {
            $id = absint($_POST['child_id'] ?? 0);
            check_admin_referer('bh_my_hub_delete_bump_' . $id, 'bh_my_hub_nonce');
            if (self::user_owns_child($id, $user_id) && get_field('child_status', $id) === 'expecting') wp_delete_post($id, true);
            self::redirect_saved();
        }
    }

    private static function save_child_post(int $user_id, int $id = 0): int {
        $name = sanitize_text_field(wp_unslash($_POST['child_name'] ?? ''));
        $nickname = sanitize_text_field(wp_unslash($_POST['child_nickname'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['child_status'] ?? 'born'));
        $dob = sanitize_text_field(wp_unslash($_POST['child_dob'] ?? ''));
        $due = sanitize_text_field(wp_unslash($_POST['child_due_date'] ?? ''));
        $avatar = esc_url_raw(wp_unslash($_POST['child_avatar_url'] ?? ''));
        $location_id = absint($_POST['child_location'] ?? 0);

        if (!in_array($status, ['born','expecting'], true)) $status = 'born';

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
        update_field('field_bubbahub_child_status', $status, $id);
        update_field('field_bubbahub_child_date_of_birth', $status === 'born' ? $dob : '', $id);
        update_field('field_bubbahub_child_due_date', $status === 'expecting' ? $due : '', $id);
        update_field('field_bubbahub_child_avatar_url', $avatar, $id);
        update_field('field_bubbahub_child_age_group', $status === 'born' ? self::age_group($dob) : '', $id);

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

    private static function get_bump(int $user_id): ?\WP_Post {
        $posts = get_posts([
            'post_type'=>self::CHILD_POST_TYPE,
            'post_status'=>['publish','private'],
            'author'=>$user_id,
            'posts_per_page'=>1,
            'orderby'=>'date',
            'order'=>'DESC',
            'meta_query'=>[['key'=>'child_status','value'=>'expecting','compare'=>'=']],
        ]);
        return $posts ? $posts[0] : null;
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
            update_field('field_bubbahub_child_name',$name,$id);
            update_field('field_bubbahub_child_status','born',$id);
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
