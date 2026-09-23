<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class MyHub {
    private const CHILDREN_META = '_bh_children';
    private const BUMP_META = '_bh_bump';

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
            $account_url = function_exists('um_get_core_page')
                ? um_get_core_page('login')
                : wp_login_url(get_permalink());

            return '<div class="bh-my-hub bh-my-hub--login"><h2>My Bubba Hub</h2><p>Please log in to access your family hub.</p><a class="bh-my-hub__button" href="' . esc_url($account_url) . '">Log in</a></div>';
        }

        $user = wp_get_current_user();
        $children = get_user_meta($user->ID, self::CHILDREN_META, true);
        $children = is_array($children) ? $children : [];
        $bump = get_user_meta($user->ID, self::BUMP_META, true);
        $bump = is_array($bump) ? $bump : [];

        ob_start();
        ?>
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
                        <div>
                            <span class="bh-my-hub__icon">👨‍👩‍👧</span>
                            <h2>My Family</h2>
                        </div>
                        <span class="bh-my-hub__count"><?php echo esc_html(count($children)); ?></span>
                    </div>
                    <div class="bh-my-hub__family-actions">
                        <button type="button" class="bh-my-hub__button bh-my-hub__open-modal" data-bh-modal="child">Add child</button>
                        <button type="button" class="bh-my-hub__button bh-my-hub__button--outline bh-my-hub__open-modal" data-bh-modal="bump"><?php echo $bump ? 'Update bump' : 'Add bump'; ?></button>
                    </div>


                    <?php if ($children): ?>
                        <div class="bh-my-hub__profiles">
                            <?php foreach ($children as $index => $child): ?>
                                <article class="bh-my-hub__profile">
                                    <?php if (!empty($child['avatar_id'])): ?>
                                        <?php echo wp_get_attachment_image((int) $child['avatar_id'], 'thumbnail', false, ['class' => 'bh-my-hub__avatar', 'alt' => '']); ?>
                                    <?php else: ?>
                                        <span class="bh-my-hub__avatar bh-my-hub__avatar--placeholder" aria-hidden="true">👶</span>
                                    <?php endif; ?>
                                    <strong><?php echo esc_html($child['name'] ?? 'Child'); ?></strong>
                                    <?php if (!empty($child['dob'])): ?>
                                        <span><?php echo esc_html(self::age_label($child['dob'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($child['gender'])): ?>
                                        <span><?php echo esc_html(ucfirst($child['gender'])); ?></span>
                                    <?php endif; ?>
                                    <?php
                                    $school_tracker = self::school_tracker($child['dob'] ?? '', $child['school_authority'] ?? '');
                                    if ($school_tracker):
                                    ?>
                                        <div class="bh-my-hub__school-tracker">
                                            <strong>🎓 School tracker</strong>
                                            <span><?php echo esc_html($school_tracker['label']); ?></span>
                                            <small><?php echo esc_html($school_tracker['countdown']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <button type="button" class="bh-my-hub__edit-link bh-my-hub__open-modal" data-bh-modal="edit-child-<?php echo esc_attr((string) $index); ?>">Edit</button>
                                    <form method="post" class="bh-my-hub__delete">
                                        <?php wp_nonce_field('bh_my_hub_delete_child', 'bh_my_hub_nonce'); ?>
                                        <input type="hidden" name="bh_my_hub_action" value="delete_child">
                                        <input type="hidden" name="child_index" value="<?php echo esc_attr((string) $index); ?>">
                                        <button type="submit">Remove</button>
                                    </form>
                                    <div class="bh-my-hub__modal" data-bh-modal-panel="edit-child-<?php echo esc_attr((string) $index); ?>" hidden>
                                        <div class="bh-my-hub__modal-backdrop" data-bh-modal-close></div>
                                        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bh-edit-child-<?php echo esc_attr((string) $index); ?>-title">
                                            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
                                            <h3 id="bh-edit-child-<?php echo esc_attr((string) $index); ?>-title">Edit <?php echo esc_html($child['name'] ?? 'child'); ?></h3>
                                            <form method="post" class="bh-my-hub__form" enctype="multipart/form-data">
                                                <?php wp_nonce_field('bh_my_hub_edit_child', 'bh_my_hub_nonce'); ?>
                                                <input type="hidden" name="bh_my_hub_action" value="edit_child">
                                                <input type="hidden" name="child_index" value="<?php echo esc_attr((string) $index); ?>">
                                                <div class="bh-my-hub__fields">
                                                    <label>Name<input type="text" name="child_name" maxlength="100" value="<?php echo esc_attr($child['name'] ?? ''); ?>" required></label>
                                                    <label>Gender
                                                        <select name="child_gender">
                                                            <option value="" <?php selected($child['gender'] ?? '', ''); ?>>Prefer not to say</option>
                                                            <option value="girl" <?php selected($child['gender'] ?? '', 'girl'); ?>>Girl</option>
                                                            <option value="boy" <?php selected($child['gender'] ?? '', 'boy'); ?>>Boy</option>
                                                            <option value="other" <?php selected($child['gender'] ?? '', 'other'); ?>>Other</option>
                                                        </select>
                                                    </label>
                                                    <label>Date of birth<input type="date" name="child_dob" value="<?php echo esc_attr($child['dob'] ?? ''); ?>"></label>
                                                    <label class="bh-my-hub__avatar-field">Avatar<input type="file" name="child_avatar" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP</small></label>
                                                    <label>Local authority
                                                        <select name="child_school_authority">
                                                            <option value="">Select local authority</option>
                                                            <option value="devon" <?php selected($child['school_authority'] ?? '', 'devon'); ?>>Devon</option>
                                                            <option value="cornwall" <?php selected($child['school_authority'] ?? '', 'cornwall'); ?>>Cornwall</option>
                                                            <option value="torbay" <?php selected($child['school_authority'] ?? '', 'torbay'); ?>>Torbay</option>
                                                            <option value="plymouth" <?php selected($child['school_authority'] ?? '', 'plymouth'); ?>>Plymouth</option>
                                                        </select>
                                                    </label>
                                                </div>
                                                <?php if (!empty($child['avatar_id'])): ?>
                                                    <label class="bh-my-hub__avatar-remove"><input type="checkbox" name="remove_child_avatar" value="1"> Remove current avatar</label>
                                                <?php endif; ?>
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
                        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bh-child-modal-title">
                            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
                            <h3 id="bh-child-modal-title">Add a child</h3>
                            <form method="post" class="bh-my-hub__form">
                                <?php wp_nonce_field('bh_my_hub_save_child', 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="save_child">
                                <div class="bh-my-hub__fields">
                                    <label>Name<input type="text" name="child_name" maxlength="100" required></label>
                                    <label>Gender
                                        <select name="child_gender">
                                            <option value="">Prefer not to say</option>
                                            <option value="girl">Girl</option>
                                            <option value="boy">Boy</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </label>
                                    <label>Date of birth<input type="date" name="child_dob"></label>
                                    <label>Local authority
                                        <select name="child_school_authority">
                                            <option value="">Select local authority</option>
                                            <option value="devon">Devon</option>
                                            <option value="cornwall">Cornwall</option>
                                            <option value="torbay">Torbay</option>
                                            <option value="plymouth">Plymouth</option>
                                        </select>
                                    </label>
                                </div>
                                <button class="bh-my-hub__button" type="submit">Save child</button>
                            </form>
                        </div>
                    </div>

                </section>

                <section class="bh-my-hub__card">
                    <div class="bh-my-hub__card-head">
                        <div>
                            <span class="bh-my-hub__icon">🤰</span>
                            <h2>My Bump</h2>
                        </div>
                    </div>
                    <div class="bh-my-hub__family-actions">
                        <button type="button" class="bh-my-hub__button bh-my-hub__button--outline bh-my-hub__open-modal" data-bh-modal="bump"><?php echo $bump ? 'Update bump' : 'Add bump'; ?></button>
                    </div>


                    <?php if ($bump): ?>
                        <article class="bh-my-hub__profile bh-my-hub__bump-profile">
                            <strong><?php echo esc_html($bump['nickname'] ?? 'My bump'); ?></strong>
                            <?php if (!empty($bump['due_date'])): ?>
                                <?php $antenatal_tracker = self::antenatal_tracker($bump['due_date']); ?>
                                <span>Due <?php echo esc_html(wp_date(get_option('date_format'), strtotime($bump['due_date']))); ?></span>
                                <?php if ($antenatal_tracker): ?>
                                    <div class="bh-my-hub__antenatal-tracker">
                                        <strong>🤰 Antenatal tracker</strong>
                                        <span><?php echo esc_html($antenatal_tracker['countdown']); ?></span>
                                        <small><?php echo esc_html($antenatal_tracker['classes']); ?></small>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <form method="post" class="bh-my-hub__delete">
                                <?php wp_nonce_field('bh_my_hub_delete_bump', 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="delete_bump">
                                <button type="submit">Remove bump</button>
                            </form>
                        </article>
                    <?php else: ?>
                        <p class="bh-my-hub__muted">Add a bump if you're expecting, and Bubba Hub can use your due date later for personalised features.</p>
                    <?php endif; ?>

                    <div class="bh-my-hub__modal" data-bh-modal-panel="bump" hidden>
                        <div class="bh-my-hub__modal-backdrop" data-bh-modal-close></div>
                        <div class="bh-my-hub__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bh-bump-modal-title">
                            <button type="button" class="bh-my-hub__modal-close" data-bh-modal-close aria-label="Close">×</button>
                            <h3 id="bh-bump-modal-title"><?php echo $bump ? 'Update bump' : 'Add a bump'; ?></h3>
                            <form method="post" class="bh-my-hub__form">
                                <?php wp_nonce_field('bh_my_hub_save_bump', 'bh_my_hub_nonce'); ?>
                                <input type="hidden" name="bh_my_hub_action" value="save_bump">
                                <div class="bh-my-hub__fields">
                                    <label>Nickname<input type="text" name="bump_nickname" maxlength="100" value="<?php echo esc_attr($bump['nickname'] ?? ''); ?>"></label>
                                    <label>Due date<input type="date" name="bump_due_date" value="<?php echo esc_attr($bump['due_date'] ?? ''); ?>"></label>
                                </div>
                                <button class="bh-my-hub__button" type="submit"><?php echo $bump ? 'Save changes' : 'Save bump'; ?></button>
                            </form>
                        </div>
                    </div>

                </section>

                <section class="bh-my-hub__card bh-my-hub__card--wide">
                    <div class="bh-my-hub__card-head">
                        <div>
                            <span class="bh-my-hub__icon">👥</span>
                            <h2>My Groups</h2>
                        </div>
                    </div>

                    <div class="bh-my-hub__groups">
                        <article class="bh-my-hub__group">
                            <span class="bh-my-hub__group-icon">🕘</span>
                            <div>
                                <h3>Recently viewed</h3>
                                <p>Groups you have recently looked at.</p>
                            </div>
                        </article>

                        <article class="bh-my-hub__group">
                            <span class="bh-my-hub__group-icon">📍</span>
                            <div>
                                <h3>Visited</h3>
                                <p>Groups and activities you have marked as visited.</p>
                            </div>
                        </article>

                        <article class="bh-my-hub__group">
                            <span class="bh-my-hub__group-icon">♡</span>
                            <div>
                                <h3>Saved</h3>
                                <p>Your saved Bubba Hub groups and activities.</p>
                            </div>
                        </article>

                        <article class="bh-my-hub__group">
                            <span class="bh-my-hub__group-icon">✨</span>
                            <div>
                                <h3>Suggestions</h3>
                                <p>Groups suggested from your family and search preferences.</p>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_forms(): void {
        if (!is_user_logged_in() || empty($_POST['bh_my_hub_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['bh_my_hub_action']));
        $user_id = get_current_user_id();

        if ($action === 'save_child') {
            check_admin_referer('bh_my_hub_save_child', 'bh_my_hub_nonce');
            $children = get_user_meta($user_id, self::CHILDREN_META, true);
            $children = is_array($children) ? $children : [];
            $children[] = [
                'name' => sanitize_text_field(wp_unslash($_POST['child_name'] ?? '')),
                'gender' => sanitize_key(wp_unslash($_POST['child_gender'] ?? '')),
                'dob' => sanitize_text_field(wp_unslash($_POST['child_dob'] ?? '')),
                'school_authority' => sanitize_key(wp_unslash($_POST['child_school_authority'] ?? '')),
            ];
            update_user_meta($user_id, self::CHILDREN_META, $children);
            self::redirect_saved();
        }

        if ($action === 'edit_child') {
            check_admin_referer('bh_my_hub_edit_child', 'bh_my_hub_nonce');
            $children = get_user_meta($user_id, self::CHILDREN_META, true);
            $children = is_array($children) ? $children : [];
            $index = isset($_POST['child_index']) ? absint($_POST['child_index']) : -1;
            if (isset($children[$index])) {
                $old_avatar_id = !empty($children[$index]['avatar_id']) ? absint($children[$index]['avatar_id']) : 0;
                $avatar_id = $old_avatar_id;

                if (!empty($_POST['remove_child_avatar']) && $old_avatar_id) {
                    wp_delete_attachment($old_avatar_id, true);
                    $avatar_id = 0;
                }

                if (!empty($_FILES['child_avatar']['name'])) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $uploaded_avatar = media_handle_upload('child_avatar', 0, [], [
                        'test_form' => false,
                        'mimes' => [
                            'jpg|jpeg|jpe' => 'image/jpeg',
                            'png' => 'image/png',
                            'webp' => 'image/webp',
                        ],
                    ]);

                    if (!is_wp_error($uploaded_avatar)) {
                        if ($old_avatar_id && $old_avatar_id !== $uploaded_avatar) {
                            wp_delete_attachment($old_avatar_id, true);
                        }
                        $avatar_id = (int) $uploaded_avatar;
                    }
                }

                $children[$index] = [
                    'name' => sanitize_text_field(wp_unslash($_POST['child_name'] ?? '')),
                    'gender' => sanitize_key(wp_unslash($_POST['child_gender'] ?? '')),
                    'dob' => sanitize_text_field(wp_unslash($_POST['child_dob'] ?? '')),
                    'school_authority' => sanitize_key(wp_unslash($_POST['child_school_authority'] ?? '')),
                    'avatar_id' => $avatar_id,
                ];
                update_user_meta($user_id, self::CHILDREN_META, $children);
            }
            self::redirect_saved();
        }

        if ($action === 'delete_child') {
            check_admin_referer('bh_my_hub_delete_child', 'bh_my_hub_nonce');
            $children = get_user_meta($user_id, self::CHILDREN_META, true);
            $children = is_array($children) ? $children : [];
            $index = isset($_POST['child_index']) ? absint($_POST['child_index']) : -1;
            if (isset($children[$index])) {
                array_splice($children, $index, 1);
                update_user_meta($user_id, self::CHILDREN_META, $children);
            }
            self::redirect_saved();
        }

        if ($action === 'save_bump') {
            check_admin_referer('bh_my_hub_save_bump', 'bh_my_hub_nonce');
            $bump = [
                'nickname' => sanitize_text_field(wp_unslash($_POST['bump_nickname'] ?? '')),
                'due_date' => sanitize_text_field(wp_unslash($_POST['bump_due_date'] ?? '')),
            ];
            update_user_meta($user_id, self::BUMP_META, $bump);
            self::redirect_saved();
        }

        if ($action === 'delete_bump') {
            check_admin_referer('bh_my_hub_delete_bump', 'bh_my_hub_nonce');
            delete_user_meta($user_id, self::BUMP_META);
            self::redirect_saved();
        }
    }

    private static function redirect_saved(): void {
        $url = self::my_hub_url();
        $url = remove_query_arg('bh_hub_saved', $url);
        wp_safe_redirect(add_query_arg('bh_hub_saved', '1', $url));
        exit;
    }

    private static function my_hub_url(): string {
        global $post;
        if ($post instanceof \WP_Post && has_shortcode((string) $post->post_content, 'bh_my_hub')) {
            return get_permalink($post);
        }
        $page = get_page_by_path('my-hub');
        return $page instanceof \WP_Post ? get_permalink($page) : home_url('/');
    }

    public static function modal_script(): void {
        if (!self::is_my_hub_page()) {
            return;
        }
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
                    document.querySelectorAll('.bh-my-hub__modal:not([hidden])').forEach(function(panel){
                        panel.hidden=true;
                    });
                    document.body.classList.remove('bh-modal-open');
                }
            });
        });
        </script>
        <?php
    }

    private static function antenatal_tracker(string $due_date): array {
        try {
            $due = new \DateTimeImmutable($due_date);
            $today = new \DateTimeImmutable('today');

            if ($due < $today) {
                return [
                    'countdown' => 'Due date has passed',
                    'classes' => 'Antenatal classes: pregnancy complete',
                ];
            }

            $diff = $today->diff($due);
            $parts = [];
            if ($diff->m) {
                $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'month' : 'months');
            }
            $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'day' : 'days');

            $class_start = $due->modify('-12 weeks');
            $class_end = $due->modify('-8 weeks');

            return [
                'countdown' => implode(', ', $parts) . ' until due date',
                'classes' => 'Antenatal classes: ' . $class_start->format('F') . ' to ' . $class_end->format('F'),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function school_tracker(string $dob, string $authority): array {
        if (!$dob) {
            return [];
        }

        try {
            $birth = new \DateTimeImmutable($dob);
            $today = new \DateTimeImmutable('today');

            if ($birth > $today) {
                return [];
            }

            // Normal Reception intake follows the child's school year.
            $fourth_birthday = $birth->modify('+4 years');
            $reception_year = (int) $fourth_birthday->format('Y');
            if ((int) $fourth_birthday->format('m') > 8) {
                $reception_year++;
            }

            $deadline = new \DateTimeImmutable($reception_year . '-01-15');
            $label = 'Reception application deadline';

            // Once Reception is past, track the next normal Year 7 application.
            if ($deadline < $today) {
                $eleven_birthday = $birth->modify('+11 years');
                $secondary_year = (int) $eleven_birthday->format('Y');
                $deadline = new \DateTimeImmutable($secondary_year . '-10-31');
                $label = 'Secondary school application deadline';
            }

            $authority_label = self::school_authority_label($authority);
            $prefix = $label . ($authority_label ? ' • ' . $authority_label : '');
            if ($deadline < $today) {
                return [
                    'label' => $prefix,
                    'countdown' => 'Deadline passed — check your local authority for late applications.',
                ];
            }

            $diff = $today->diff($deadline);
            $parts = [];
            if ($diff->y) {
                $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'year' : 'years');
            }
            if ($diff->m) {
                $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'month' : 'months');
            }
            $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'day' : 'days');

            return [
                'label' => $prefix,
                'countdown' => implode(', ', $parts) . ' to apply',
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function school_authority_label(string $authority): string {
        $labels = [
            'devon' => 'Devon',
            'cornwall' => 'Cornwall',
            'torbay' => 'Torbay',
            'plymouth' => 'Plymouth',
        ];

        return $labels[$authority] ?? '';
    }

    private static function age_label(string $dob): string {
        try {
            $birth = new \DateTimeImmutable($dob);
            $today = new \DateTimeImmutable('today');
            if ($birth > $today) {
                return '';
            }
            $diff = $birth->diff($today);
            if ($diff->y > 0) {
                return $diff->y . ' ' . ($diff->y === 1 ? 'year' : 'years') . ' old';
            }
            return $diff->m > 0
                ? $diff->m . ' ' . ($diff->m === 1 ? 'month' : 'months') . ' old'
                : 'Under 1 month';
        } catch (\Exception $e) {
            return '';
        }
    }
}
