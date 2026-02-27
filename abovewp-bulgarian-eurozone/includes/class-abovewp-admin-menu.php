<?php
/**
 * AboveWP Admin Menu
 *
 * @package AboveWP
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AboveWP Admin Menu class
 */
if (!class_exists('AboveWP_Admin_Menu')) {
    class AboveWP_Admin_Menu {

        /**
         * Initialize the admin menu
         */
        public static function init() {
            add_action('admin_menu', array(__CLASS__, 'add_menu_page'));
            add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_dashboard_styles'));
            add_action('admin_notices', array(__CLASS__, 'display_promo_notice'));
            add_action('wp_ajax_abovewp_dismiss_promo_notice', array(__CLASS__, 'ajax_dismiss_promo_notice'));
        }

        /**
         * Enqueue dashboard styles
         */
        public static function enqueue_dashboard_styles($hook) {
            // Only load on AboveWP admin page
            if ($hook === 'toplevel_page_abovewp') {
                wp_enqueue_style(
                    'abovewp-font-inter',
                    'https://fonts.bunny.net/css?family=inter:400,500,600,700',
                    array(),
                    null
                );
                wp_enqueue_style(
                    'abovewp-admin-dashboard',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/abovewp-admin-dashboard.css',
                    array('abovewp-font-inter'),
                    '1.0.0'
                );
            }
        }

        /**
         * Add the AboveWP menu page
         */
        public static function add_menu_page() {
            global $menu;

            // Check if AboveWP menu already exists
            $menu_exists = false;
            foreach ($menu as $item) {
                if (isset($item[2]) && $item[2] === 'abovewp') {
                    $menu_exists = true;
                    break;
                }
            }

            // Only add menu if it doesn't exist
            if (!$menu_exists) {
                // SVG icon for the menu
                $icon = 'data:image/svg+xml;base64,' . base64_encode('<svg id="Layer_1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 298.79 284.66"><defs><style>.cls-1{fill:#0582ff;}</style></defs><path class="cls-1" d="M198.41,29.27L46.61,148.08c-7.54,5.9-6.1,17.71,2.63,21.63l39.89,17.97c2.65,1.19,5.75.77,7.98-1.1l46.56-38.97c8.17-6.83,20.59-.91,20.41,9.74l-.98,58.43c-.05,3.14,1.77,6.01,4.63,7.3l26.88,12.11c5.43,2.45,11.75,2.43,17.27-.22,3.9-1.87,7.05-7.06,7.06-11.39l.02-4.04V39.29c0-10.61-12.22-16.56-20.57-10.02Z"/><g><path class="cls-1" d="M98.85,208.92l-17.2,14.4c-3.4,2.84-2.69,8.25,1.33,10.12l51.24,23.84c2.6,1.21,5.62,1.12,8.15-.25,1.78-.97,2.89-2.85,2.93-4.88l.4-22c.04-2.16-1.22-4.14-3.19-5.02l-38.01-17c-1.88-.84-4.07-.53-5.64.78Z"/><path class="cls-1" d="M142.12,211.84c1.83.82,3.91-.5,3.95-2.51l.5-27.3c.09-5.18-5.96-8.07-9.93-4.74l-21.55,18.04c-1.59,1.33-1.24,3.86.65,4.71l26.38,11.8Z"/></g></svg>');

                add_menu_page(
                    __('AboveWP', 'abovewp-bulgarian-eurozone'),
                    __('AboveWP', 'abovewp-bulgarian-eurozone'),
                    'manage_options',
                    'abovewp',
                    array(__CLASS__, 'display_menu_page'),
                    $icon,
                    2
                );
            }
        }

        /**
         * Display the menu page
         */
        public static function display_menu_page() {
            ?>
            <div class="abovewp-wrap">
                <div class="abovewp-bg-effects">
                    <div class="abovewp-bg-orb abovewp-bg-orb-1"></div>
                    <div class="abovewp-bg-orb abovewp-bg-orb-2"></div>
                </div>
                <div class="abovewp-container">
                    <header class="abovewp-header">
                        <div class="abovewp-logo-section">
                            <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/img/abovewp-logo.png'); ?>" alt="<?php esc_attr_e('AboveWP', 'abovewp-bulgarian-eurozone'); ?>" class="abovewp-logo">
                            <span class="abovewp-badge">
                                <span class="abovewp-badge-dot"></span>
                                <?php esc_html_e('Plugin Dashboard', 'abovewp-bulgarian-eurozone'); ?>
                            </span>
                        </div>
                        <div class="abovewp-header-actions">
                            <a href="https://abovewp.com/" target="_blank"><?php esc_html_e('Visit Website', 'abovewp-bulgarian-eurozone'); ?></a>
                        </div>
                    </header>

                    <div class="abovewp-ai-banner">
                        <div class="abovewp-ai-banner-content">
                            <div class="abovewp-ai-banner-text">
                                <h3><?php esc_html_e('Stop babysitting your WordPress sites', 'abovewp-bulgarian-eurozone'); ?></h3>
                                <p><?php esc_html_e('Hire AI agents that work 24/7 so you don\'t have to. Automation, updates, backups, security, performance, content — handled automatically while you sleep.', 'abovewp-bulgarian-eurozone'); ?></p>
                                <div class="abovewp-ai-banner-perks">
                                    <span class="abovewp-ai-banner-perk"><?php esc_html_e('15 free credits at launch', 'abovewp-bulgarian-eurozone'); ?></span>
                                    <span class="abovewp-ai-banner-perk"><?php esc_html_e('First 500 users lock in beta pricing forever', 'abovewp-bulgarian-eurozone'); ?></span>
                                </div>
                            </div>
                            <div class="abovewp-ai-banner-actions">
                                <a href="https://abovewp.com/prelaunch" target="_blank" class="abovewp-ai-banner-btn abovewp-ai-banner-btn-primary"><?php esc_html_e('Sign Up for Prelaunch', 'abovewp-bulgarian-eurozone'); ?></a>
                                <a href="https://abovewp.com/prelaunch/agencies" target="_blank" class="abovewp-ai-banner-btn abovewp-ai-banner-btn-secondary"><?php esc_html_e('Agency Partner Program', 'abovewp-bulgarian-eurozone'); ?></a>
                            </div>
                        </div>
                    </div>

                    <div class="abovewp-section">
                        <div class="abovewp-section-header">
                            <h2 class="abovewp-section-title"><?php esc_html_e('Available Plugins', 'abovewp-bulgarian-eurozone'); ?></h2>
                        </div>
                        <div class="aw-admin-dashboard-grid">
                            <?php do_action('abovewp_admin_dashboard_plugins'); ?>
                        </div>
                    </div>

                    <footer class="abovewp-footer">
                        <div class="abovewp-footer-links">
                            <a href="https://abovewp.com" target="_blank"><?php esc_html_e('Website', 'abovewp-bulgarian-eurozone'); ?></a>
                            <a href="https://abovewp.com/support" target="_blank"><?php esc_html_e('Support', 'abovewp-bulgarian-eurozone'); ?></a>
                            <a href="https://profiles.wordpress.org/wpabove/#content-plugins" target="_blank"><?php esc_html_e('Check our other plugins', 'abovewp-bulgarian-eurozone'); ?></a>
                        </div>
                        <p class="abovewp-footer-copy">&copy; <?php echo esc_html(gmdate('Y')); ?> AboveWP</p>
                    </footer>
                </div>
            </div>
            <?php
        }

        /**
         * Display promotional admin notice
         */
        public static function display_promo_notice() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $dismissed_at = get_user_meta(get_current_user_id(), 'abovewp_promo_notice_dismissed_at', true);
            if ($dismissed_at && (time() - (int) $dismissed_at) < 1209600) {
                return;
            }

            $nonce = wp_create_nonce('abovewp_dismiss_promo_notice');
            ?>
            <div id="abovewp-promo-notice" class="notice" style="display:flex;align-items:center;gap:18px;padding:20px 24px;border-left:4px solid #0582ff;background:linear-gradient(135deg,rgba(5,130,255,0.15) 0%,rgba(168,85,247,0.15) 50%,rgba(236,72,153,0.1) 100%),#0f0f17;position:relative;">
                <style>
                    #abovewp-promo-notice .abovewp-notice-content{flex:1;display:flex;flex-wrap:wrap;align-items:center;gap:12px 24px;}
                    #abovewp-promo-notice h3{margin:0;font-size:15px;font-weight:700;color:#ffffff;}
                    #abovewp-promo-notice p{margin:0;color:#94a3b8;font-size:13px;line-height:1.5;}
                    #abovewp-promo-notice .abovewp-notice-actions{display:flex;gap:10px;flex-shrink:0;}
                    #abovewp-promo-notice .abovewp-notice-btn{display:inline-block;padding:7px 16px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;line-height:1.4;}
                    #abovewp-promo-notice .abovewp-notice-btn-primary{background:#0582ff;color:#fff;}
                    #abovewp-promo-notice .abovewp-notice-btn-primary:hover{background:#0468d0;color:#fff;}
                    #abovewp-promo-notice .abovewp-notice-btn-secondary{background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;}
                    #abovewp-promo-notice .abovewp-notice-btn-secondary:hover{background:linear-gradient(135deg,#6d28d9,#9333ea);color:#fff;}
                    #abovewp-promo-notice .abovewp-notice-dismiss{position:absolute;top:8px;right:10px;background:none;border:none;cursor:pointer;color:#64748b;font-size:18px;line-height:1;padding:4px;}
                    #abovewp-promo-notice .abovewp-notice-dismiss:hover{color:#94a3b8;}
                    #abovewp-promo-notice .abovewp-notice-perks{display:flex;gap:10px;margin-top:6px;}
                    #abovewp-promo-notice .abovewp-notice-perk{font-size:12px;font-weight:600;color:#0582FF;background:rgba(5,130,255,0.1);padding:4px 12px;border-radius:100px;border:1px solid rgba(5,130,255,0.2);}
                    #abovewp-promo-notice .abovewp-notice-logo{height:30px;width:auto;display:block;margin-bottom:6px;}
                </style>
                <div class="abovewp-notice-content">
                    <div>
                        <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/img/abovewp-logo.png'); ?>" alt="AboveWP" class="abovewp-notice-logo">
                        <h3><?php esc_html_e('Stop babysitting your WordPress sites', 'abovewp-bulgarian-eurozone'); ?></h3>
                        <p><?php esc_html_e('Hire AI agents that work 24/7 so you don\'t have to. Automation, updates, backups, security, performance, content — handled automatically while you sleep.', 'abovewp-bulgarian-eurozone'); ?></p>
                        <div class="abovewp-notice-perks">
                            <span class="abovewp-notice-perk"><?php esc_html_e('15 free credits at launch', 'abovewp-bulgarian-eurozone'); ?></span>
                            <span class="abovewp-notice-perk"><?php esc_html_e('First 500 users lock in beta pricing forever', 'abovewp-bulgarian-eurozone'); ?></span>
                        </div>
                    </div>
                    <div class="abovewp-notice-actions">
                        <a href="https://abovewp.com/prelaunch" target="_blank" class="abovewp-notice-btn abovewp-notice-btn-primary"><?php esc_html_e('Sign Up for Prelaunch', 'abovewp-bulgarian-eurozone'); ?></a>
                        <a href="https://abovewp.com/prelaunch/agencies" target="_blank" class="abovewp-notice-btn abovewp-notice-btn-secondary"><?php esc_html_e('Agency Partner Program', 'abovewp-bulgarian-eurozone'); ?></a>
                    </div>
                </div>
                <button type="button" class="abovewp-notice-dismiss" title="<?php esc_attr_e('Dismiss this notice', 'abovewp-bulgarian-eurozone'); ?>">&times;</button>
                <script>
                (function(){
                    var notice = document.getElementById('abovewp-promo-notice');
                    if (!notice) return;
                    notice.querySelector('.abovewp-notice-dismiss').addEventListener('click', function(){
                        notice.style.display = 'none';
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', ajaxurl, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.send('action=abovewp_dismiss_promo_notice&_wpnonce=<?php echo esc_js($nonce); ?>');
                    });
                })();
                </script>
            </div>
            <?php
        }

        /**
         * AJAX handler for dismissing the promo notice
         */
        public static function ajax_dismiss_promo_notice() {
            check_ajax_referer('abovewp_dismiss_promo_notice');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized', 403);
            }

            update_user_meta(get_current_user_id(), 'abovewp_promo_notice_dismissed_at', time());
            wp_send_json_success();
        }
    }
}