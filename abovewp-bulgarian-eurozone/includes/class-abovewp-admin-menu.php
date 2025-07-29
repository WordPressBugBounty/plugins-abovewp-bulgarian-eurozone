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
        }

        /**
         * Enqueue dashboard styles
         */
        public static function enqueue_dashboard_styles($hook) {
            // Only load on AboveWP admin page
            if ($hook === 'toplevel_page_abovewp') {
                wp_enqueue_style(
                    'abovewp-admin-dashboard',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/abovewp-admin-dashboard.css',
                    array(),
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
            <div class="wrap">
                <div class="abovewp-admin-header">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/img/abovewp-logo.png'); ?>" alt="AboveWP" class="abovewp-logo">
                    <div class="about-text">
                        <a href="https://abovewp.com/" target="_blank"><?php echo esc_html_x('Visit our website', 'abovewp', 'abovewp-bulgarian-eurozone'); ?></a>
                    </div>
                </div>
                <div class="aw-admin-dashboard">
                    <div class="aw-admin-dashboard-content">
                        <h2><?php echo esc_html_x('Available Plugins', 'abovewp', 'abovewp-bulgarian-eurozone'); ?></h2>
                        <div class="aw-admin-dashboard-grid">
                            <?php do_action('abovewp_admin_dashboard_plugins'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
} 