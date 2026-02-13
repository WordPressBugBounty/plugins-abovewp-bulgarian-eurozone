<?php
/**
 * Plugin Name: AboveWP Bulgarian Eurozone
 * Description: Adds bidirectional dual currency display (BGN ⇄ EUR) for WooCommerce as Bulgaria prepares to join the Eurozone
 * Version: 2.2.1
 * Author: AboveWP
 * Author URI: https://abovewp.com
 * Text Domain: abovewp-bulgarian-eurozone
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 10.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ABOVEWP_BGE_VERSION', '2.2.1');
define('ABOVEWP_BGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ABOVEWP_BGE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once ABOVEWP_BGE_PLUGIN_DIR . 'includes/class-abovewp-admin-menu.php';

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Class AboveWP_Bulgarian_Eurozone
 */
class AboveWP_Bulgarian_Eurozone {
    /**
     * Fixed conversion rate from BGN to EUR
     * This is the official conversion rate established by the European Central Bank
     * and is no longer configurable to ensure compliance with EU standards
     */
    private $conversion_rate = 1.95583;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize the plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize parent menu
        AboveWP_Admin_Menu::init();

        // Add admin settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Remove WordPress admin notices on our admin pages
        add_action('admin_head', array($this, 'remove_admin_notices_on_plugin_pages'));
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        // Add plugin card to AboveWP dashboard
        add_action('abovewp_admin_dashboard_plugins', array($this, 'display_plugin_card'));

        // Only proceed if dual currency display is enabled and should be displayed
        // (BGN stores always show EUR, EUR stores only show BGN on Bulgarian locale)
        if (get_option('abovewp_bge_enabled', 'yes') !== 'yes' || !$this->should_display_dual_currency()) {
            return;
        }

        // Initialize all hooks if enabled
        $this->init_hooks();

        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));

        // Add admin notice for currency migration
        if ($this->is_site_currency_bgn()) {
            add_action('admin_notices', array($this, 'migration_admin_notice'));
        }

        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    /**
     * Check if site currency is set to BGN
     *
     * @return bool
     */
    private function is_site_currency_bgn() {
        if (!function_exists('get_woocommerce_currency')) {
            return false;
        }
        
        return get_woocommerce_currency() === 'BGN';
    }

    /**
     * Check if site currency is set to EUR
     *
     * @return bool
     */
    private function is_site_currency_eur() {
        if (!function_exists('get_woocommerce_currency')) {
            return false;
        }
        
        return get_woocommerce_currency() === 'EUR';
    }

    /**
     * Check if site currency is supported (BGN or EUR)
     *
     * @return bool
     */
    private function is_site_currency_supported() {
        return $this->is_site_currency_bgn() || $this->is_site_currency_eur();
    }

    /**
     * Check if dual currency display should be active
     * For BGN stores: always show EUR (everyone should see the EUR equivalent)
     * For EUR stores: only show BGN on Bulgarian locale (for multilang/multicurrency compatibility)
     *
     * @return bool
     */
    private function should_display_dual_currency() {
        if (!$this->is_site_currency_supported()) {
            return false;
        }
        
        // For BGN stores, always show EUR equivalent
        if ($this->is_site_currency_bgn()) {
            return true;
        }
        
        // For EUR stores, only show BGN equivalent on Bulgarian locale
        if ($this->is_site_currency_eur()) {
            $locale = get_locale();
            // Check for Bulgarian locale (bg_BG or just bg)
            return strpos($locale, 'bg') === 0;
        }
        
        return false;
    }

    /**
     * Get the primary currency (the one set in WooCommerce)
     *
     * @return string 'BGN' or 'EUR' or empty string if not supported
     */
    private function get_primary_currency() {
        if ($this->is_site_currency_bgn()) {
            return 'BGN';
        } elseif ($this->is_site_currency_eur()) {
            return 'EUR';
        }
        return '';
    }

    /**
     * Get the secondary currency (the one to display alongside primary)
     *
     * @return string 'EUR' or 'BGN' or empty string if not supported
     */
    private function get_secondary_currency() {
        if ($this->is_site_currency_bgn()) {
            return 'EUR';
        } elseif ($this->is_site_currency_eur()) {
            return 'BGN';
        }
        return '';
    }

    /**
     * Show admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e('AboveWP Bulgarian Eurozone requires WooCommerce to be installed and active.', 'abovewp-bulgarian-eurozone'); ?></p>
        </div>
        <?php
    }

    /**
     * Show admin notice for currency migration
     */
    public function migration_admin_notice() {
        // Don't show on our own pages
        $screen = get_current_screen();
        if (isset($screen->id) && (
            strpos($screen->id, 'abovewp-bulgarian-eurozone') !== false || 
            strpos($screen->id, 'abovewp-currency-migration') !== false
        )) {
            return;
        }

        // Check if user has dismissed the notice
        $dismissed = get_user_meta(get_current_user_id(), 'abovewp_migration_notice_dismissed', true);
        if ($dismissed) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible abovewp-migration-notice">
            <p>
                <strong><?php esc_html_e('Ready for Bulgaria\'s Eurozone Transition?', 'abovewp-bulgarian-eurozone'); ?></strong><br>
                <?php esc_html_e('Use our Currency Migration tool to automatically convert all your product prices from BGN to EUR using the official rate.', 'abovewp-bulgarian-eurozone'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=abovewp-currency-migration')); ?>" class="button button-primary">
                    <?php esc_html_e('Start Migration', 'abovewp-bulgarian-eurozone'); ?>
                </a>
            </p>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('click', '.abovewp-migration-notice .notice-dismiss', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'abovewp_dismiss_migration_notice',
                        nonce: '<?php echo wp_create_nonce('abovewp_dismiss_notice'); ?>'
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Add plugin action links
     *
     * @param array $links
     * @return array
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=abovewp-bulgarian-eurozone') . '">' . __('Settings', 'abovewp-bulgarian-eurozone') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Initialize all hooks for price display
     */
    private function init_hooks() {
        // Single product price
        if (get_option('abovewp_bge_show_single_product', 'yes') === 'yes') {
            add_filter('woocommerce_get_price_html', array($this, 'display_dual_price'), 10, 2);
        }

        // Variable product price range
        if (get_option('abovewp_bge_show_variable_product', 'yes') === 'yes') {
            add_filter('woocommerce_variable_price_html', array($this, 'display_dual_price_variable'), 10, 2);
        }

        // Cart item price
        if (get_option('abovewp_bge_show_cart_item', 'yes') === 'yes') {
            add_filter('woocommerce_cart_item_price', array($this, 'display_dual_price_cart_item'), 10, 3);
        }

        // Cart item subtotal
        if (get_option('abovewp_bge_show_cart_subtotal', 'yes') === 'yes') {
            add_filter('woocommerce_cart_item_subtotal', array($this, 'display_dual_price_cart_subtotal'), 10, 3);
            add_filter('woocommerce_cart_subtotal', array($this, 'display_dual_price_cart_subtotal_total'), 10, 3);
        }

        // Cart total
        if (get_option('abovewp_bge_show_cart_total', 'yes') === 'yes') {
            add_filter('woocommerce_cart_totals_order_total_html', array($this, 'display_dual_price_cart_total'), 10, 1);
        }

        // Cart fees (like Cash on Delivery fees)
        if (get_option('abovewp_bge_show_cart_total', 'yes') === 'yes') {
            add_filter('woocommerce_cart_totals_fee_html', array($this, 'display_dual_price_cart_fee'), 10, 2);
        }

        // Cart/Checkout coupons/promocodes
        if (get_option('abovewp_bge_show_cart_total', 'yes') === 'yes') {
            add_filter('woocommerce_cart_totals_coupon_html', array($this, 'display_dual_price_coupon_html'), 10, 2);
        }

        // Order confirmation and email
        if (get_option('abovewp_bge_show_order_totals', 'yes') === 'yes') {
            add_filter('woocommerce_get_order_item_totals', array($this, 'add_eur_to_order_totals'), 10, 3);
        }

        // My Account - Orders List
        if (get_option('abovewp_bge_show_orders_table', 'yes') === 'yes') {
            add_filter('woocommerce_my_account_my_orders_columns', array($this, 'add_eur_column_to_orders_table'));
            add_action('woocommerce_my_account_my_orders_column_order-total-eur', array($this, 'add_eur_value_to_orders_table'));
        }

        // Product feeds and APIs
        if (get_option('abovewp_bge_show_api_prices', 'yes') === 'yes') {
            add_filter('woocommerce_rest_prepare_product_object', array($this, 'add_eur_price_to_api'), 10, 3);
        }

        // Shipping and tax
        if (get_option('abovewp_bge_show_shipping_labels', 'yes') === 'yes') {
            add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_eur_to_shipping_label'), 10, 2);
        }
        
        if (get_option('abovewp_bge_show_tax_labels', 'yes') === 'yes') {
            add_filter('woocommerce_order_get_tax_totals', array($this, 'add_eur_to_order_tax_totals'), 10, 2);
        }

        // Mini cart
        if (get_option('abovewp_bge_show_mini_cart', 'yes') === 'yes') {
            add_filter('woocommerce_widget_cart_item_quantity', array($this, 'add_eur_to_mini_cart'), 10, 3);
        }

        // Thank you page
        if (get_option('abovewp_bge_show_thank_you_page', 'yes') === 'yes') {
            add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'add_eur_to_thank_you_line_subtotal'), 10, 3);
            add_filter('woocommerce_get_order_item_totals', array($this, 'add_eur_to_order_totals'), 10, 3);
        }
        
        // Fix legacy BGN orders displaying with wrong currency after migration to EUR
        if ($this->is_site_currency_eur()) {
            add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'fix_legacy_order_line_subtotal'), 5, 3);
            add_filter('woocommerce_get_formatted_order_total', array($this, 'fix_legacy_order_total'), 5, 2);
            add_filter('woocommerce_order_subtotal_to_display', array($this, 'fix_legacy_order_subtotal_display'), 5, 3);
            add_filter('woocommerce_order_shipping_to_display', array($this, 'fix_legacy_order_shipping_display'), 5, 3);
            add_filter('woocommerce_order_discount_to_display', array($this, 'fix_legacy_order_discount_display'), 5, 2);
        }
        
        // Enqueue JavaScript for Blocks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_blocks_scripts'));
        
        // AJAX handlers for currency migration
        add_action('wp_ajax_abovewp_get_product_count', array($this, 'ajax_get_product_count'));
        add_action('wp_ajax_abovewp_migrate_products', array($this, 'ajax_migrate_products'));
        add_action('wp_ajax_abovewp_finalize_migration', array($this, 'ajax_finalize_migration'));
        add_action('wp_ajax_abovewp_reset_migration', array($this, 'ajax_reset_migration'));
        add_action('wp_ajax_abovewp_dismiss_migration_notice', array($this, 'ajax_dismiss_migration_notice'));
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        // Load on our plugin's admin pages
        if (strpos($hook, 'abovewp-bulgarian-eurozone') !== false || strpos($hook, 'abovewp-currency-migration') !== false) {
            wp_enqueue_style(
                'abovewp-admin-default',
                ABOVEWP_BGE_PLUGIN_URL . 'assets/css/admin-page-default.css',
                array(),
                ABOVEWP_BGE_VERSION
            );
        }
    }

    /**
     * Enqueue CSS styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'abovewp-bulgarian-eurozone',
            ABOVEWP_BGE_PLUGIN_URL . 'assets/css/abovewp-bulgarian-eurozone.css',
            array(),
            ABOVEWP_BGE_VERSION
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'abovewp',
            __('Bulgarian Eurozone Settings', 'abovewp-bulgarian-eurozone'),
            __('Eurozone Settings', 'abovewp-bulgarian-eurozone'),
            'manage_options',
            'abovewp-bulgarian-eurozone',
            array($this, 'settings_page')
        );
        
        // Add currency migration page only if currency is BGN
        if ($this->is_site_currency_bgn()) {
            add_submenu_page(
                'abovewp',
                __('Currency Migration', 'abovewp-bulgarian-eurozone'),
                __('Currency Migration', 'abovewp-bulgarian-eurozone'),
                'manage_options',
                'abovewp-currency-migration',
                array($this, 'migration_page')
            );
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Yes/No setting for main toggle
        register_setting(
            'abovewp_bge_settings',   // Option group
            'abovewp_bge_enabled',     // Option name
            array(                     // Args
                'type' => 'string',
                'sanitize_callback' => function($value) {
                    return ($value === 'yes') ? 'yes' : 'no';
                },
                'default' => 'yes',
                'description' => 'Enable or disable dual currency display'
            )
        );
        

        
        // EUR price position setting
        register_setting(
            'abovewp_bge_settings',   // Option group
            'abovewp_bge_eur_position', // Option name
            array(                     // Args
                'type' => 'string',
                'sanitize_callback' => function($value) {
                    return in_array($value, array('left', 'right')) ? $value : 'right';
                },
                'default' => 'right',
                'description' => 'EUR price position (left or right of BGN price)'
            )
        );
        
        // EUR price display format setting
        register_setting(
            'abovewp_bge_settings',   // Option group
            'abovewp_bge_eur_format', // Option name
            array(                     // Args
                'type' => 'string',
                'sanitize_callback' => function($value) {
                    return in_array($value, array('brackets', 'divider')) ? $value : 'brackets';
                },
                'default' => 'brackets',
                'description' => 'EUR price display format (brackets or side divider)'
            )
        );

        register_setting(
            'abovewp_bge_settings',
            'abovewp_bge_bgn_rounding',
            array(
                'type' => 'string',
                'sanitize_callback' => function($value) {
                    return in_array($value, array('exact', 'smart')) ? $value : 'smart';
                },
                'default' => 'smart',
                'description' => 'BGN price rounding (exact decimals or smart rounding)'
            )
        );

        // Display location settings (checkboxes)
        $display_locations = array(
            'single_product' => esc_html__('Single product pages', 'abovewp-bulgarian-eurozone'),
            'variable_product' => esc_html__('Variable product pages', 'abovewp-bulgarian-eurozone'),
            'cart_item' => esc_html__('Cart item prices', 'abovewp-bulgarian-eurozone'),
            'cart_subtotal' => esc_html__('Cart subtotals', 'abovewp-bulgarian-eurozone'),
            'cart_total' => esc_html__('Cart totals', 'abovewp-bulgarian-eurozone'),
            'order_totals' => esc_html__('Order confirmation & email', 'abovewp-bulgarian-eurozone'),
            'orders_table' => esc_html__('My Account orders table', 'abovewp-bulgarian-eurozone'),
            'api_prices' => esc_html__('REST API responses', 'abovewp-bulgarian-eurozone'),
            'shipping_labels' => esc_html__('Shipping method labels', 'abovewp-bulgarian-eurozone'),
            'tax_labels' => esc_html__('Tax amount labels', 'abovewp-bulgarian-eurozone'),
            'mini_cart' => esc_html__('Mini cart', 'abovewp-bulgarian-eurozone'),
            'thank_you_page' => esc_html__('Thank you / Order received page', 'abovewp-bulgarian-eurozone')
        );
        
        foreach ($display_locations as $key => $label) {
            register_setting(
                'abovewp_bge_settings',
                'abovewp_bge_show_' . $key,
                array(
                    'type' => 'string',
                    'sanitize_callback' => function($value) {
                        return ($value === 'yes') ? 'yes' : 'no';
                    },
                    'default' => 'yes',
                    // Translators: %s is the name of the location where EUR price can be displayed (e.g. "Single product pages")
                    'description' => sprintf(__('Show EUR price on %s', 'abovewp-bulgarian-eurozone'), $label)
                )
            );
        }
    }

    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="abovewp-admin-page">
            <div class="abovewp-admin-header">
                <img src="<?php echo esc_url(ABOVEWP_BGE_PLUGIN_URL . 'assets/img/abovewp-logo.png'); ?>" alt="AboveWP" class="abovewp-logo">
            </div>
            <h1><?php esc_html_e('Bulgarian Eurozone Settings', 'abovewp-bulgarian-eurozone'); ?></h1>
            
            <?php if ($this->is_site_currency_bgn()): ?>
            <div class="abovewp-currency-notice">
                <div class="abovewp-currency-notice-content">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <p class="abovewp-currency-notice-text">
                            <strong><?php esc_html_e('Your store is currently using Bulgarian Lev (BGN) as the primary currency.', 'abovewp-bulgarian-eurozone'); ?></strong>
                        </p>
                        <p class="abovewp-currency-notice-text">
                            <?php esc_html_e('When Bulgaria joins the Eurozone, you can use our Currency Migration tool to seamlessly convert all prices to EUR.', 'abovewp-bulgarian-eurozone'); ?>
                        </p>
                    </div>
                </div>
                <p class="abovewp-currency-notice-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=abovewp-currency-migration')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-migrate"></span>
                        <?php esc_html_e('Currency Migration Tool', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (!$this->is_site_currency_supported()): ?>
            <div class="abovewp-error-box">
                <p>
                    <?php esc_html_e('This plugin requires your WooCommerce currency to be set to either Bulgarian Lev (BGN) or Euro (EUR). The dual currency display will not work until you change your store currency to BGN or EUR.', 'abovewp-bulgarian-eurozone'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=general')); ?>" class="button button-secondary">
                        <?php esc_html_e('Change Currency Settings', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
                </p>
            </div>
            <?php elseif ($this->is_site_currency_eur()): ?>
            <div class="abovewp-info-box">
                <p>
                    <?php esc_html_e('Your store currency is set to EUR. Bulgarian Lev (BGN) prices will be displayed alongside EUR prices.', 'abovewp-bulgarian-eurozone'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('abovewp_bge_settings'); ?>
                <?php do_settings_sections('abovewp_bge_settings'); ?>
                
                <h2><?php esc_html_e('General Settings', 'abovewp-bulgarian-eurozone'); ?></h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Enable Dual Currency Display', 'abovewp-bulgarian-eurozone'); ?></th>
                        <td>
                            <select name="abovewp_bge_enabled" <?php disabled(!$this->is_site_currency_supported()); ?>>
                                <option value="yes" <?php selected(get_option('abovewp_bge_enabled', 'yes'), 'yes'); ?>><?php esc_html_e('Yes', 'abovewp-bulgarian-eurozone'); ?></option>
                                <option value="no" <?php selected(get_option('abovewp_bge_enabled', 'yes'), 'no'); ?>><?php esc_html_e('No', 'abovewp-bulgarian-eurozone'); ?></option>
                            </select>
                            <?php if (!$this->is_site_currency_supported()): ?>
                                <p class="description"><?php esc_html_e('Dual currency display is only available when your store currency is BGN or EUR.', 'abovewp-bulgarian-eurozone'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Secondary Currency Position', 'abovewp-bulgarian-eurozone'); ?></th>
                        <td>
                            <select name="abovewp_bge_eur_position" <?php disabled(!$this->is_site_currency_supported()); ?>>
                                <option value="right" <?php selected(get_option('abovewp_bge_eur_position', 'right'), 'right'); ?>><?php esc_html_e('Right of primary price', 'abovewp-bulgarian-eurozone'); ?></option>
                                <option value="left" <?php selected(get_option('abovewp_bge_eur_position', 'right'), 'left'); ?>><?php esc_html_e('Left of primary price', 'abovewp-bulgarian-eurozone'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose whether secondary currency appears on the left or right of primary currency', 'abovewp-bulgarian-eurozone'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Secondary Currency Display Format', 'abovewp-bulgarian-eurozone'); ?></th>
                        <td>
                            <select name="abovewp_bge_eur_format" <?php disabled(!$this->is_site_currency_supported()); ?>>
                                <?php if ($this->is_site_currency_bgn()): ?>
                                    <option value="brackets" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'brackets'); ?>><?php esc_html_e('Brackets (25лв. (12.78 €))', 'abovewp-bulgarian-eurozone'); ?></option>
                                    <option value="divider" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'divider'); ?>><?php esc_html_e('Side divider (25лв. / 12.78 €)', 'abovewp-bulgarian-eurozone'); ?></option>
                                <?php elseif ($this->is_site_currency_eur()): ?>
                                    <option value="brackets" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'brackets'); ?>><?php esc_html_e('Brackets (12.78 € (25лв.))', 'abovewp-bulgarian-eurozone'); ?></option>
                                    <option value="divider" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'divider'); ?>><?php esc_html_e('Side divider (12.78 € / 25лв.)', 'abovewp-bulgarian-eurozone'); ?></option>
                                <?php else: ?>
                                    <option value="brackets" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'brackets'); ?>><?php esc_html_e('Brackets', 'abovewp-bulgarian-eurozone'); ?></option>
                                    <option value="divider" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'divider'); ?>><?php esc_html_e('Side divider', 'abovewp-bulgarian-eurozone'); ?></option>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Choose how secondary currency is displayed relative to primary currency', 'abovewp-bulgarian-eurozone'); ?></p>
                        </td>
                    </tr>
                    <?php if ($this->is_site_currency_eur()): ?>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('BGN Price Rounding', 'abovewp-bulgarian-eurozone'); ?></th>
                        <td>
                            <select name="abovewp_bge_bgn_rounding">
                                <option value="exact" <?php selected(get_option('abovewp_bge_bgn_rounding', 'smart'), 'exact'); ?>><?php esc_html_e('Keep exact decimals (e.g., 19.99 лв.)', 'abovewp-bulgarian-eurozone'); ?></option>
                                <option value="smart" <?php selected(get_option('abovewp_bge_bgn_rounding', 'smart'), 'smart'); ?>><?php esc_html_e('Round to exact decimal (e.g., 19.91 → 19.90 лв.)', 'abovewp-bulgarian-eurozone'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('When the converted BGN price is within 0.015 of an exact decimal (e.g., 19.91 vs 19.90), choose whether to keep the calculated value or round to the nearest decimal.', 'abovewp-bulgarian-eurozone'); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php if ($this->is_site_currency_supported()): ?>
                <h2><?php esc_html_e('Display Locations', 'abovewp-bulgarian-eurozone'); ?></h2>
                <p class="description">
                    <?php 
                    if ($this->is_site_currency_bgn()) {
                        esc_html_e('Select where you want to display EUR prices:', 'abovewp-bulgarian-eurozone');
                    } elseif ($this->is_site_currency_eur()) {
                        esc_html_e('Select where you want to display BGN prices:', 'abovewp-bulgarian-eurozone');
                    }
                    ?>
                </p>
                
                <table class="form-table">
                    <?php
                    $display_locations = array(
                        'single_product' => esc_html__('Single product pages', 'abovewp-bulgarian-eurozone'),
                        'variable_product' => esc_html__('Variable product pages', 'abovewp-bulgarian-eurozone'),
                        'cart_item' => esc_html__('Cart item prices', 'abovewp-bulgarian-eurozone'),
                        'cart_subtotal' => esc_html__('Cart subtotals', 'abovewp-bulgarian-eurozone'),
                        'cart_total' => esc_html__('Cart totals', 'abovewp-bulgarian-eurozone'),
                        'order_totals' => esc_html__('Order confirmation & email', 'abovewp-bulgarian-eurozone'),
                        'orders_table' => esc_html__('My Account orders table', 'abovewp-bulgarian-eurozone'),
                        'api_prices' => esc_html__('REST API responses', 'abovewp-bulgarian-eurozone'),
                        'shipping_labels' => esc_html__('Shipping method labels', 'abovewp-bulgarian-eurozone'),
                        'tax_labels' => esc_html__('Tax amount labels', 'abovewp-bulgarian-eurozone'),
                        'mini_cart' => esc_html__('Mini cart', 'abovewp-bulgarian-eurozone'),
                        'thank_you_page' => esc_html__('Thank you / Order received page', 'abovewp-bulgarian-eurozone')
                    );
                    
                    foreach ($display_locations as $key => $label) :
                    ?>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="abovewp_bge_show_<?php echo esc_attr($key); ?>" value="yes" <?php checked(get_option('abovewp_bge_show_' . $key, 'yes'), 'yes'); ?> />
                                <?php 
                                if ($this->is_site_currency_bgn()) {
                                    esc_html_e('Show EUR price', 'abovewp-bulgarian-eurozone');
                                } elseif ($this->is_site_currency_eur()) {
                                    esc_html_e('Show BGN price', 'abovewp-bulgarian-eurozone');
                                } else {
                                    esc_html_e('Show secondary currency', 'abovewp-bulgarian-eurozone');
                                }
                                ?>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
                
                <?php submit_button(null, 'primary', 'submit', true, $this->is_site_currency_supported() ? [] : ['disabled' => 'disabled']); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Convert BGN to EUR
     *
     * @param float $price_bgn
     * @return float
     */
    public function convert_bgn_to_eur($price_bgn) {
        // Always use the official BGN to EUR conversion rate
        $price_eur = $price_bgn / $this->conversion_rate;
        return round($price_eur, 2);
    }

    /**
     * Convert EUR to BGN
     *
     * Rounding behavior depends on the 'abovewp_bge_bgn_rounding' setting:
     * - 'exact': Keep exact decimals (19.99 stays 19.99)
     * - 'smart': Round to nearest whole number if close (19.99 → 20.00)
     *
     * @param float $price_eur
     * @return float
     */
    public function convert_eur_to_bgn($price_eur) {
        $price_bgn_raw = $price_eur * $this->conversion_rate;

        $rounding = get_option('abovewp_bge_bgn_rounding', 'smart');

        $price_bgn = round($price_bgn_raw, 2);

        if ($rounding === 'smart') {
            $nearest_int = round($price_bgn);
            if (abs($price_bgn - $nearest_int) < 0.015) {
                $price_bgn = $nearest_int;
            }
        }
        
        /**
         * Filter the converted BGN price from EUR
         * 
         * Allows developers to customize the rounding logic for EUR to BGN conversion.
         *
         * @since 2.0.3
         * @param float $price_bgn The rounded BGN price (after smart rounding)
         * @param float $price_eur The original EUR price (input)
         * @param float $price_bgn_raw The raw unrounded BGN price (before rounding)
         */
        return apply_filters('abovewp_bge_convert_eur_to_bgn', $price_bgn, $price_eur, $price_bgn_raw);
    }

    /**
     * Convert price from primary to secondary currency
     *
     * @param float $price
     * @return float
     */
    private function convert_to_secondary_currency($price) {
        if ($this->is_site_currency_bgn()) {
            return $this->convert_bgn_to_eur($price);
        } elseif ($this->is_site_currency_eur()) {
            return $this->convert_eur_to_bgn($price);
        }
        return $price;
    }

    /**
     * Get tax-aware price for a product based on context
     *
     * @param WC_Product $product
     * @param string $context Context: 'shop', 'cart', 'order'
     * @param int $qty Quantity for price calculation
     * @return float
     */
    private function get_tax_aware_price($product, $context = 'shop', $qty = 1) {
        if (!$product) {
            return 0;
        }

        switch ($context) {
            case 'cart':
                // Use cart tax display setting
                if (WC()->cart && WC()->cart->display_prices_including_tax()) {
                    return wc_get_price_including_tax($product, array('qty' => $qty));
                } else {
                    return wc_get_price_excluding_tax($product, array('qty' => $qty));
                }
                
            case 'order':
                // Use cart tax display setting for orders
                $tax_display = get_option('woocommerce_tax_display_cart');
                if ('incl' === $tax_display) {
                    return wc_get_price_including_tax($product, array('qty' => $qty));
                } else {
                    return wc_get_price_excluding_tax($product, array('qty' => $qty));
                }
                
            case 'shop':
            default:
                // Use shop tax display setting
                return wc_get_price_to_display($product, array('qty' => $qty));
        }
    }

    /**
     * Get EUR label (always €)
     *
     * @return string
     */
    private function get_eur_label() {
        return '€';
    }

    /**
     * Get BGN label
     *
     * @return string
     */
    private function get_bgn_label() {
        return 'лв.';
    }

    /**
     * Get secondary currency label (EUR when primary is BGN, BGN when primary is EUR)
     *
     * @return string
     */
    private function get_secondary_currency_label() {
        if ($this->is_site_currency_bgn()) {
            return $this->get_eur_label();
        } elseif ($this->is_site_currency_eur()) {
            return $this->get_bgn_label();
        }
        return '';
    }

    /**
     * Format EUR price with label
     *
     * @param float $price_eur
     * @return string
     */
    private function format_eur_price($price_eur) {
        return number_format($price_eur, 2) . ' ' . $this->get_eur_label();
    }

    /**
     * Format BGN price with label
     *
     * @param float $price_bgn
     * @return string
     */
    private function format_bgn_price($price_bgn) {
        return number_format($price_bgn, 2) . ' ' . $this->get_bgn_label();
    }

    /**
     * Format secondary currency price with label
     *
     * @param float $price
     * @return string
     */
    private function format_secondary_price($price) {
        return number_format($price, 2) . ' ' . $this->get_secondary_currency_label();
    }

    /**
     * Format dual currency price based on position setting
     *
     * @param string $primary_price_html The original primary currency price HTML
     * @param float $secondary_price The secondary currency price amount
     * @param string $css_class Optional CSS class for secondary price span
     * @return string The formatted dual currency price
     */
    private function format_dual_price($primary_price_html, $secondary_price, $css_class = 'eur-price') {
        $secondary_formatted = $this->format_secondary_price($secondary_price);
        $format = get_option('abovewp_bge_eur_format', 'brackets');
        $position = get_option('abovewp_bge_eur_position', 'right');
        
        if ($format === 'divider') {
            // Side divider format: "25лв. / 12.78 €" or "12.78 € / 25лв."
            $secondary_span = '<span class="' . esc_attr($css_class) . '">/ ' . esc_html($secondary_formatted) . '</span>';
        } else {
            // Brackets format: "25лв. (12.78 €)" or "12.78 € (25лв.)"
            $secondary_span = '<span class="' . esc_attr($css_class) . '">(' . esc_html($secondary_formatted) . ')</span>';
        }
        
        if ($position === 'left') {
            return $secondary_span . ' ' . $primary_price_html;
        } else {
            return $primary_price_html . ' ' . $secondary_span;
        }
    }

    /**
     * Add secondary currency conversion to inline tax display within includes_tax elements
     *
     * @param string $html The HTML containing potential includes_tax elements
     * @return string Modified HTML with secondary currency tax amounts added
     */
    private function add_eur_to_inline_tax_display($html) {
        // Check if the HTML contains includes_tax class
        if (strpos($html, 'includes_tax') === false) {
            return $html;
        }
        
        // Check for primary currency symbol
        $primary_currency = $this->get_primary_currency();
        if ($primary_currency === 'BGN' && strpos($html, 'лв.') === false) {
            return $html;
        } elseif ($primary_currency === 'EUR' && strpos($html, '€') === false) {
            return $html;
        }
        
        // Use a regex to find and replace tax amounts within includes_tax elements
        $pattern = '/<small[^>]*class="[^"]*includes_tax[^"]*"[^>]*>(.*?)<\/small>/s';
        
        return preg_replace_callback($pattern, array($this, 'replace_inline_tax_amounts'), $html);
    }

    /**
     * Callback function to replace tax amounts within includes_tax elements
     *
     * @param array $matches Regex matches
     * @return string Modified small element with secondary currency tax amounts
     */
    private function replace_inline_tax_amounts($matches) {
        $tax_content = $matches[1];
        $primary_currency = $this->get_primary_currency();
        $format = get_option('abovewp_bge_eur_format', 'brackets');
        
        if ($primary_currency === 'BGN') {
            // Find all BGN price amounts within the tax content
            // Look for patterns like "8.32&nbsp;<span class="woocommerce-Price-currencySymbol">лв.</span>"
            $price_pattern = '/(\d+(?:\.\d{2})?)\s*(?:&nbsp;)?<span[^>]*class="[^"]*woocommerce-Price-currencySymbol[^"]*"[^>]*>лв\.<\/span>/';
            
            $modified_content = preg_replace_callback($price_pattern, function($price_matches) use ($format) {
                $bgn_amount = floatval($price_matches[1]);
                $eur_amount = $this->convert_bgn_to_eur($bgn_amount);
                $eur_formatted = number_format($eur_amount, 2);
                
                if ($format === 'divider') {
                    return $price_matches[0] . ' / ' . esc_html($eur_formatted) . ' ' . esc_html($this->get_eur_label());
                } else {
                    return $price_matches[0] . ' (' . esc_html($eur_formatted) . ' ' . esc_html($this->get_eur_label()) . ')';
                }
            }, $tax_content);
            
            // Also handle simpler patterns like "8.32 лв." without spans
            $simple_pattern = '/(\d+(?:\.\d{2})?)\s*лв\./';
            $modified_content = preg_replace_callback($simple_pattern, function($price_matches) use ($format) {
                $bgn_amount = floatval($price_matches[1]);
                $eur_amount = $this->convert_bgn_to_eur($bgn_amount);
                $eur_formatted = number_format($eur_amount, 2);
                
                if ($format === 'divider') {
                    return $price_matches[0] . ' / ' . esc_html($eur_formatted) . ' ' . esc_html($this->get_eur_label());
                } else {
                    return $price_matches[0] . ' (' . esc_html($eur_formatted) . ' ' . esc_html($this->get_eur_label()) . ')';
                }
            }, $modified_content);
        } elseif ($primary_currency === 'EUR') {
            // Find all EUR price amounts within the tax content
            // Look for patterns like "8.32&nbsp;<span class="woocommerce-Price-currencySymbol">€</span>"
            $price_pattern = '/(\d+(?:\.\d{2})?)\s*(?:&nbsp;)?<span[^>]*class="[^"]*woocommerce-Price-currencySymbol[^"]*"[^>]*>€<\/span>/';
            
            $modified_content = preg_replace_callback($price_pattern, function($price_matches) use ($format) {
                $eur_amount = floatval($price_matches[1]);
                $bgn_amount = $this->convert_eur_to_bgn($eur_amount);
                $bgn_formatted = number_format($bgn_amount, 2);
                
                if ($format === 'divider') {
                    return $price_matches[0] . ' / ' . esc_html($bgn_formatted) . ' ' . esc_html($this->get_bgn_label());
                } else {
                    return $price_matches[0] . ' (' . esc_html($bgn_formatted) . ' ' . esc_html($this->get_bgn_label()) . ')';
                }
            }, $tax_content);
            
            // Also handle simpler patterns like "8.32 €" without spans
            $simple_pattern = '/(\d+(?:\.\d{2})?)\s*€/';
            $modified_content = preg_replace_callback($simple_pattern, function($price_matches) use ($format) {
                $eur_amount = floatval($price_matches[1]);
                $bgn_amount = $this->convert_eur_to_bgn($eur_amount);
                $bgn_formatted = number_format($bgn_amount, 2);
                
                if ($format === 'divider') {
                    return $price_matches[0] . ' / ' . esc_html($bgn_formatted) . ' ' . esc_html($this->get_bgn_label());
                } else {
                    return $price_matches[0] . ' (' . esc_html($bgn_formatted) . ' ' . esc_html($this->get_bgn_label()) . ')';
                }
            }, $modified_content);
        } else {
            $modified_content = $tax_content;
        }
        
        return '<small' . substr($matches[0], 6, strpos($matches[0], '>') - 6) . '>' . $modified_content . '</small>';
    }

    /**
     * Add secondary currency price to existing value based on position setting
     *
     * @param string $existing_value The existing price value
     * @param float $secondary_price The secondary currency price amount
     * @param string $css_class Optional CSS class for secondary price span
     * @return string The modified value with secondary currency price added
     */
    private function add_eur_to_value($existing_value, $secondary_price, $css_class = 'eur-price') {
        $secondary_formatted = $this->format_secondary_price($secondary_price);
        $format = get_option('abovewp_bge_eur_format', 'brackets');
        $position = get_option('abovewp_bge_eur_position', 'right');
        
        if ($format === 'divider') {
            // Side divider format: "25лв. / 12.78 €" or "12.78 € / 25лв."
            $secondary_span = '<span class="' . esc_attr($css_class) . '">/ ' . esc_html($secondary_formatted) . '</span>';
        } else {
            // Brackets format: "25лв. (12.78 €)" or "12.78 € (25лв.)"
            $secondary_span = '<span class="' . esc_attr($css_class) . '">(' . esc_html($secondary_formatted) . ')</span>';
        }
        
        if ($position === 'left') {
            return $secondary_span . ' ' . $existing_value;
        } else {
            return $existing_value . ' ' . $secondary_span;
        }
    }

    /**
     * Display dual price for single products
     *
     * @param string $price_html
     * @param object $product
     * @return string
     */
    public function display_dual_price($price_html, $product) {
        if (empty($price_html)) {
            return $price_html;
        }
        
        // Skip variable products as they're handled by display_dual_price_variable
        if ($product->is_type('variable')) {
            return $price_html;
        }

        if ($product->is_on_sale()) {
            $regular_price_primary = wc_get_price_to_display($product, array('price' => $product->get_regular_price()));
            $sale_price_primary = wc_get_price_to_display($product, array('price' => $product->get_sale_price()));
            
            // Convert to secondary currency
            $regular_price_secondary = $this->convert_to_secondary_currency($regular_price_primary);
            $sale_price_secondary = $this->convert_to_secondary_currency($sale_price_primary);
            
            $regular_price_dual = $this->format_dual_price(wc_price($regular_price_primary), $regular_price_secondary);
            $sale_price_dual = $this->format_dual_price(wc_price($sale_price_primary), $sale_price_secondary);
            
            // Use WooCommerce's built-in sale price formatting
            $price_html = wc_format_sale_price($regular_price_dual, $sale_price_dual);
            
            return $price_html;
        }
        
        // Use WooCommerce function that respects tax display settings
        $price_primary = wc_get_price_to_display($product);
        $price_secondary = $this->convert_to_secondary_currency($price_primary);
        
        return $this->format_dual_price($price_html, $price_secondary);
    }

    /**
     * Display dual price for variable products
     *
     * @param string $price_html
     * @param object $product
     * @return string
     */
    public function display_dual_price_variable($price_html, $product) {
        // Get min and max prices using tax-aware functions
        $tax_display_mode = get_option('woocommerce_tax_display_shop');
        
        if ('incl' === $tax_display_mode) {
            $min_price_primary = $product->get_variation_price('min', true); // true = include taxes
            $max_price_primary = $product->get_variation_price('max', true);
        } else {
            $min_price_primary = $product->get_variation_price('min', false); // false = exclude taxes
            $max_price_primary = $product->get_variation_price('max', false);
        }
        
        // Convert to secondary currency
        $min_price_secondary = $this->convert_to_secondary_currency($min_price_primary);
        $max_price_secondary = $this->convert_to_secondary_currency($max_price_primary);
        
        // If prices are the same, show single price, otherwise show range
        if ($min_price_primary === $max_price_primary) {
            return $this->format_dual_price($price_html, $min_price_secondary);
        } else {
            $min_price_formatted = esc_html(number_format($min_price_secondary, 2));
            $max_price_formatted = esc_html(number_format($max_price_secondary, 2));
            $secondary_label = esc_html($this->get_secondary_currency_label());
            $secondary_range = $min_price_formatted . ' - ' . $max_price_formatted . ' ' . $secondary_label;
            
            $format = get_option('abovewp_bge_eur_format', 'brackets');
            $position = get_option('abovewp_bge_eur_position', 'right');
            
            if ($format === 'divider') {
                // Side divider format
                $secondary_span = '<span class="eur-price">/ ' . $secondary_range . '</span>';
            } else {
                // Brackets format
                $secondary_span = '<span class="eur-price">(' . $secondary_range . ')</span>';
            }
            
            if ($position === 'left') {
                return $secondary_span . ' ' . $price_html;
            } else {
                return $price_html . ' ' . $secondary_span;
            }
        }
    }

    /**
     * Display dual price for cart items
     *
     * @param string $price_html
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function display_dual_price_cart_item($price_html, $cart_item, $cart_item_key) {
        // Use cart's tax-aware price calculation
        if (WC()->cart->display_prices_including_tax()) {
            $product_price = wc_get_price_including_tax($cart_item['data']);
        } else {
            $product_price = wc_get_price_excluding_tax($cart_item['data']);
        }
        
        $price_secondary = $this->convert_to_secondary_currency($product_price);
        
        return $this->format_dual_price($price_html, $price_secondary);
    }

    /**
     * Display dual price for cart item subtotals
     *
     * @param string $subtotal
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function display_dual_price_cart_subtotal($subtotal, $cart_item, $cart_item_key) {
        $quantity = $cart_item['quantity'];
        
        // Use WooCommerce cart's tax-aware subtotal calculation
        if (WC()->cart->display_prices_including_tax()) {
            $subtotal_primary = wc_get_price_including_tax($cart_item['data'], array('qty' => $quantity));
        } else {
            $subtotal_primary = wc_get_price_excluding_tax($cart_item['data'], array('qty' => $quantity));
        }
        
        $subtotal_secondary = $this->convert_to_secondary_currency($subtotal_primary);
        
        return $this->format_dual_price($subtotal, $subtotal_secondary);
    }

    /**
     * Display dual price for cart totals
     *
     * @param string $total
     * @return string
     */
    public function display_dual_price_cart_total($total) {
        // Cart total always includes all taxes and fees as displayed
        $cart_total_primary = WC()->cart->get_total(false);
        $cart_total_secondary = $this->convert_to_secondary_currency($cart_total_primary);
        
        // Handle inline tax display within includes_tax small element
        $total = $this->add_eur_to_inline_tax_display($total);
        
        return $this->format_dual_price($total, $cart_total_secondary);
    }

    /**
     * Display dual price for cart fees
     *
     * @param string $fee_html
     * @param object $fee
     * @return string
     */
    public function display_dual_price_cart_fee($fee_html, $fee) {
        $secondary_label = $this->get_secondary_currency_label();
        if (strpos($fee_html, $secondary_label) !== false) {
            return $fee_html;
        }
        
        $fee_amount_primary = $fee->amount;
        if ($fee_amount_primary > 0) {
            $fee_amount_secondary = $this->convert_to_secondary_currency($fee_amount_primary);
            $fee_html = $this->add_eur_to_value($fee_html, $fee_amount_secondary);
        }
        
        return $fee_html;
    }

    /**
     * Display dual price for cart subtotal
     *
     * @param string $subtotal
     * @param bool $compound
     * @param object $cart
     * @return string
     */
    public function display_dual_price_cart_subtotal_total($subtotal, $compound, $cart) {
        // Use cart's display-aware subtotal calculation
        if ($cart->display_prices_including_tax()) {
            $cart_subtotal_primary = $cart->get_subtotal() + $cart->get_subtotal_tax();
        } else {
            $cart_subtotal_primary = $cart->get_subtotal();
        }
        
        $cart_subtotal_secondary = $this->convert_to_secondary_currency($cart_subtotal_primary);
        
        return $this->format_dual_price($subtotal, $cart_subtotal_secondary);
    }

    /**
     * Add secondary currency to order totals
     *
     * @param array $total_rows
     * @param object $order
     * @param string $tax_display
     * @return array
     */
    public function add_eur_to_order_totals($total_rows, $order, $tax_display) {
        // Create a new array for the modified rows
        $modified_rows = array();
        
        foreach ($total_rows as $key => $row) {
            if ($key === 'cart_subtotal') {
                // Add secondary currency to subtotal based on tax display mode
                if ('incl' === $tax_display) {
                    $subtotal_primary = $order->get_subtotal() + $order->get_total_tax();
                } else {
                    $subtotal_primary = $order->get_subtotal();
                }
                $subtotal_secondary = $this->convert_to_secondary_currency($subtotal_primary);
                $row['value'] = $this->add_eur_to_value($row['value'], $subtotal_secondary);
            } 
            elseif ($key === 'shipping') {
                // Add secondary currency to shipping based on tax display mode
                if ('incl' === $tax_display) {
                    $shipping_total_primary = $order->get_shipping_total() + $order->get_shipping_tax();
                } else {
                    $shipping_total_primary = $order->get_shipping_total();
                }
                if ($shipping_total_primary > 0 && strpos($row['value'], $this->get_secondary_currency_label()) === false) {
                    $shipping_total_secondary = $this->convert_to_secondary_currency($shipping_total_primary);
                    $row['value'] = $this->add_eur_to_value($row['value'], $shipping_total_secondary);
                }
            }
            elseif ($key === 'tax' || strpos($key, 'tax') === 0) {
                $tax_total_primary = $order->get_total_tax();
                if ($tax_total_primary > 0) {
                    $tax_total_secondary = $this->convert_to_secondary_currency($tax_total_primary);
                    $row['value'] = $this->add_eur_to_value($row['value'], $tax_total_secondary);
                }
            }
            elseif (strpos($key, 'fee') === 0) {
                $fees = $order->get_fees();
                foreach ($fees as $fee) {
                    $fee_total = $fee->get_total();
                    if ('incl' === $tax_display) {
                        $fee_total += $fee->get_total_tax();
                    }
                    if ($fee_total > 0) {
                        $fee_total_secondary = $this->convert_to_secondary_currency($fee_total);
                        $row['value'] = $this->add_eur_to_value($row['value'], $fee_total_secondary);
                        break; // Only process the first fee that matches
                    }
                }
            }
            elseif ($key === 'order_total') {
                // Add secondary currency to order total (total always includes all taxes and fees)
                $total_primary = $order->get_total();
                $total_secondary = $this->convert_to_secondary_currency($total_primary);
                
                // Handle inline tax display within includes_tax small element
                $row['value'] = $this->add_eur_to_inline_tax_display($row['value']);
                
                $row['value'] = $this->add_eur_to_value($row['value'], $total_secondary);
            }
            
            $modified_rows[$key] = $row;
        }
        
        return $modified_rows;
    }

    /**
     * Add secondary currency column to orders table
     *
     * @param array $columns
     * @return array
     */
    public function add_eur_column_to_orders_table($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            if ($key === 'order-total') {
                // Translators: %s is the currency label (EUR or BGN)
                $new_columns['order-total-eur'] = sprintf(esc_html__('Total (%s)', 'abovewp-bulgarian-eurozone'), esc_html($this->get_secondary_currency_label()));
            }
        }
        
        return $new_columns;
    }

    /**
     * Add secondary currency value to orders table
     *
     * @param object $order
     */
    public function add_eur_value_to_orders_table($order) {
        $order_total_primary = $order->get_total();
        $order_total_secondary = $this->convert_to_secondary_currency($order_total_primary);
        
        echo esc_html($this->format_secondary_price($order_total_secondary));
    }

    /**
     * Add secondary currency price to API responses
     *
     * @param object $response
     * @param object $post
     * @param object $request
     * @return object
     */
    public function add_eur_price_to_api($response, $post, $request) {
        $data = $response->get_data();
        
        if (isset($data['price']) && isset($data['id'])) {
            $product = wc_get_product($data['id']);
            if ($product) {
                // Use tax-aware price for API responses
                $price_primary = wc_get_price_to_display($product);
                $price_secondary = $this->convert_to_secondary_currency($price_primary);
                
                // Add both possible fields for backward compatibility
                if ($this->is_site_currency_bgn()) {
                    $data['price_eur'] = number_format($price_secondary, 2);
                } elseif ($this->is_site_currency_eur()) {
                    $data['price_bgn'] = number_format($price_secondary, 2);
                }
                
                $response->set_data($data);
            }
        }
        
        return $response;
    }

    /**
     * Add secondary currency to shipping label
     *
     * @param string $label
     * @param object $method
     * @return string
     */
    public function add_eur_to_shipping_label($label, $method) {
        if ($method->cost > 0) {
            $shipping_cost_primary = $method->cost;
            $shipping_cost_secondary = $this->convert_to_secondary_currency($shipping_cost_primary);
            $label = $this->add_eur_to_value($label, $shipping_cost_secondary);
        }
        
        return $label;
    }

    /**
     * Add secondary currency to mini cart
     *
     * @param string $html
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function add_eur_to_mini_cart($html, $cart_item, $cart_item_key) {
        // Check if the HTML already contains secondary currency price to prevent duplicates
        $secondary_label = $this->get_secondary_currency_label();
        if (strpos($html, $secondary_label) !== false || strpos($html, 'eur-price') !== false) {
            return $html;
        }
        
        $quantity = $cart_item['quantity'];
        
        // Use WooCommerce cart's tax-aware calculation for mini cart
        if (WC()->cart->display_prices_including_tax()) {
            $subtotal_primary = wc_get_price_including_tax($cart_item['data'], array('qty' => $quantity));
        } else {
            $subtotal_primary = wc_get_price_excluding_tax($cart_item['data'], array('qty' => $quantity));
        }
        
        $subtotal_secondary = $this->convert_to_secondary_currency($subtotal_primary);
        
        return $this->add_eur_to_value($html, $subtotal_secondary);
    }

    /**
     * Display dual currency for coupons
     *
     * @param float $discount
     * @param float $discounting_amount
     * @param array $cart_item
     * @param bool $single
     * @param object $coupon
     * @return float
     */
    public function display_dual_currency_coupon($discount, $discounting_amount, $cart_item, $single, $coupon) {
        if (!is_cart() && !is_checkout()) {
            return $discount;
        }
        
        $discount_secondary = $this->convert_to_secondary_currency($discount);
        $GLOBALS['dual_currency_coupon_secondary'] = $discount_secondary;
        
        return $discount;
    }

    /**
     * Display dual price for coupon/promocode HTML in cart totals
     *
     * @param string $coupon_html
     * @param object $coupon
     * @return string
     */
    public function display_dual_price_coupon_html($coupon_html, $coupon) {
        // Get the discount amount for this coupon
        $discount_amount = WC()->cart->get_coupon_discount_amount($coupon->get_code(), WC()->cart->display_prices_including_tax());
        
        if ($discount_amount > 0) {
            $discount_secondary = $this->convert_to_secondary_currency($discount_amount);
            $discount_secondary = -$discount_secondary;
            return $this->add_eur_to_value($coupon_html, $discount_secondary);
        }
        
        return $coupon_html;
    }

    /**
     * Display plugin card on AboveWP dashboard
     */
    public function display_plugin_card() {
        ?>
        <div class="aw-admin-dashboard-plugin">
            <h3><?php esc_html_e('Bulgarian Eurozone', 'abovewp-bulgarian-eurozone'); ?></h3>
            <p><?php esc_html_e('Adds bidirectional dual currency display (BGN ⇄ EUR) for WooCommerce as Bulgaria prepares to join the Eurozone', 'abovewp-bulgarian-eurozone'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=abovewp-bulgarian-eurozone')); ?>" class="button button-primary">
                <?php esc_html_e('Configure', 'abovewp-bulgarian-eurozone'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Currency migration page
     */
    public function migration_page() {
        ?>
        <div class="abovewp-admin-page">
            <div class="abovewp-admin-header">
                <img src="<?php echo esc_url(ABOVEWP_BGE_PLUGIN_URL . 'assets/img/abovewp-logo.png'); ?>" alt="AboveWP" class="abovewp-logo">
            </div>
            <h1 class="abovewp-migration-title">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Currency Migration: BGN to EUR', 'abovewp-bulgarian-eurozone'); ?>
            </h1>
            
            <p class="abovewp-migration-description">
                <?php esc_html_e('Automatically convert all your product prices from Bulgarian Lev (BGN) to Euro (EUR) using the official exchange rate.', 'abovewp-bulgarian-eurozone'); ?>
            </p>
            
            <?php if (!$this->is_site_currency_bgn()): ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Currency migration is only available when your store currency is set to BGN.', 'abovewp-bulgarian-eurozone'); ?></strong>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=abovewp-bulgarian-eurozone')); ?>" class="button">
                        <?php esc_html_e('Back to Settings', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
                </p>
            </div>
            <?php return; endif; ?>
            
            <div class="abovewp-warning-box">
                <h3 class="abovewp-warning-title">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Important: Read Before Starting', 'abovewp-bulgarian-eurozone'); ?>
                </h3>
                <ul class="abovewp-warning-list">
                    <li><?php esc_html_e('This process will convert ALL product prices from BGN to EUR using the official rate (1.95583 BGN = 1 EUR).', 'abovewp-bulgarian-eurozone'); ?></li>
                    <li><?php esc_html_e('It will also change your WooCommerce store currency to EUR.', 'abovewp-bulgarian-eurozone'); ?></li>
                    <li><?php esc_html_e('This includes regular prices, sale prices, and all product variations.', 'abovewp-bulgarian-eurozone'); ?></li>
                    <li class="abovewp-warning-critical"><?php esc_html_e('BACKUP YOUR DATABASE BEFORE PROCEEDING!', 'abovewp-bulgarian-eurozone'); ?></li>
                    <li><?php esc_html_e('The process runs in batches to handle stores with thousands of products.', 'abovewp-bulgarian-eurozone'); ?></li>
                </ul>
            </div>
            
            <div id="migration-status" class="abovewp-migration-status">
                <h3 class="abovewp-migration-progress-title">
                    <span class="dashicons dashicons-update-alt dashicons-spin"></span>
                    <?php esc_html_e('Migration Progress', 'abovewp-bulgarian-eurozone'); ?>
                </h3>
                <div class="abovewp-progress-wrapper">
                    <div class="abovewp-progress-bar-container">
                        <div id="progress-bar" class="abovewp-progress-bar"></div>
                    </div>
                    <p id="progress-text" class="abovewp-progress-text">0%</p>
                </div>
                <div id="migration-warnings"></div>
            </div>
            
            <div id="migration-error" class="abovewp-error-box" style="display: none;">
                <h3 class="abovewp-error-title">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Migration Error', 'abovewp-bulgarian-eurozone'); ?>
                </h3>
                <p id="migration-error-message"></p>
                <p>
                    <button onclick="location.reload();" class="button button-primary">
                        <?php esc_html_e('Refresh and Resume', 'abovewp-bulgarian-eurozone'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=abovewp-bulgarian-eurozone')); ?>" class="button button-secondary">
                        <?php esc_html_e('Back to Settings', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
                </p>
            </div>
            
            <div id="migration-complete" class="abovewp-success-box">
                <h3 class="abovewp-success-title">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Migration Complete!', 'abovewp-bulgarian-eurozone'); ?>
                </h3>
                <p class="abovewp-success-text">
                    <?php esc_html_e('All product prices have been successfully converted to EUR.', 'abovewp-bulgarian-eurozone'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=general')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('View Currency Settings', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-products"></span>
                        <?php esc_html_e('View Products', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
                </p>
            </div>
            
            <?php
            $migration_in_progress = get_option('abovewp_bge_migration_in_progress', false);
            $migration_offset = (int) get_option('abovewp_bge_migration_offset', 0);
            $migration_total = (int) get_option('abovewp_bge_migration_total', 0);
            ?>

            <?php if ($migration_in_progress && $migration_offset > 0): ?>
            <div id="migration-resume" class="abovewp-resume-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('Migration In Progress', 'abovewp-bulgarian-eurozone'); ?>
                </h3>
                <p>
                    <?php printf(esc_html__('A previous migration was interrupted. Progress: %d of %d products processed.', 'abovewp-bulgarian-eurozone'), $migration_offset, $migration_total); ?>
                </p>
                <p>
                    <button id="resume-migration" class="button button-primary">
                        <?php esc_html_e('Resume Migration', 'abovewp-bulgarian-eurozone'); ?>
                    </button>
                    <button id="reset-migration" class="button button-secondary">
                        <?php esc_html_e('Start Over', 'abovewp-bulgarian-eurozone'); ?>
                    </button>
                </p>
            </div>
            <?php endif; ?>

            <div id="migration-controls" class="abovewp-migration-controls" <?php echo ($migration_in_progress && $migration_offset > 0) ? 'style="display:none;"' : ''; ?>>
                <h2 class="abovewp-migration-controls-title">
                    <span class="dashicons dashicons-migrate"></span>
                    <?php esc_html_e('Start Migration', 'abovewp-bulgarian-eurozone'); ?>
                </h2>
                <p class="abovewp-migration-controls-text">
                    <?php esc_html_e('Click the button below to start the currency migration process. Make sure you have read all the warnings above and have backed up your database.', 'abovewp-bulgarian-eurozone'); ?>
                </p>

                <p class="abovewp-migration-controls-buttons">
                    <button id="start-migration" class="button button-primary button-hero">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Start Migration to EUR', 'abovewp-bulgarian-eurozone'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=abovewp-bulgarian-eurozone')); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('Cancel', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            let isRunning = false;
            let totalProducts = 0;
            let processedProducts = 0;

            $('#start-migration').on('click', function() {
                if (isRunning) return;

                if (!confirm('<?php esc_html_e('Are you sure you want to start the migration? This will convert all prices from BGN to EUR. Make sure you have a database backup!', 'abovewp-bulgarian-eurozone'); ?>')) {
                    return;
                }

                isRunning = true;
                $(this).prop('disabled', true);
                $('#migration-controls').hide();
                $('#migration-status').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'abovewp_get_product_count',
                        nonce: '<?php echo wp_create_nonce('abovewp_migration'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            totalProducts = response.data.count;
                            processedProducts = 0;
                            processBatch(0);
                        } else {
                            alert('<?php esc_html_e('Error:', 'abovewp-bulgarian-eurozone'); ?> ' + response.data.message);
                            isRunning = false;
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Failed to get product count', 'abovewp-bulgarian-eurozone'); ?>');
                        isRunning = false;
                    }
                });
            });

            $('#resume-migration').on('click', function() {
                if (isRunning) return;

                if (!confirm('<?php esc_html_e('Resume the migration from where it left off?', 'abovewp-bulgarian-eurozone'); ?>')) {
                    return;
                }

                isRunning = true;
                processedProducts = <?php echo (int) $migration_offset; ?>;
                totalProducts = <?php echo (int) $migration_total; ?>;

                $('#migration-resume').hide();
                $('#migration-status').show();

                const percentage = Math.round((processedProducts / totalProducts) * 100);
                $('#progress-bar').css('width', percentage + '%').text(percentage + '%');
                $('#progress-text').text(percentage + '% (' + processedProducts + ' / ' + totalProducts + ')');

                processBatch(processedProducts);
            });

            $('#reset-migration').on('click', function() {
                if (!confirm('<?php esc_html_e('Are you sure you want to start over? This will reset migration progress.', 'abovewp-bulgarian-eurozone'); ?>')) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'abovewp_reset_migration',
                        nonce: '<?php echo wp_create_nonce('abovewp_migration'); ?>'
                    },
                    success: function() {
                        location.reload();
                    }
                });
            });

            function processBatch(offset) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'abovewp_migrate_products',
                        offset: offset,
                        nonce: '<?php echo wp_create_nonce('abovewp_migration'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            processedProducts += response.data.processed;
                            const percentage = Math.round((processedProducts / totalProducts) * 100);

                            $('#progress-bar').css('width', percentage + '%').text(percentage + '%');
                            $('#progress-text').text(percentage + '% (' + processedProducts + ' / ' + totalProducts + ')');

                            // Show warnings if any products had issues
                            if (response.data.warnings && response.data.warnings.length > 0) {
                                let warningHtml = '<div class="abovewp-migration-warning"><p><strong><?php esc_html_e('Some products had issues:', 'abovewp-bulgarian-eurozone'); ?></strong></p><ul>';
                                response.data.warnings.forEach(function(warning) {
                                    warningHtml += '<li>' + warning + '</li>';
                                });
                                warningHtml += '</ul></div>';
                                $('#migration-warnings').append(warningHtml);
                            }

                            if (response.data.has_more) {
                                processBatch(processedProducts);
                            } else {
                                finalizeMigration();
                            }
                        } else {
                            showMigrationError(response.data.message);
                            isRunning = false;
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMsg = '<?php esc_html_e('Failed to process batch.', 'abovewp-bulgarian-eurozone'); ?>';
                        if (error) {
                            errorMsg += ' ' + error;
                        }
                        errorMsg += ' <?php esc_html_e('You can resume later from where it stopped.', 'abovewp-bulgarian-eurozone'); ?>';
                        showMigrationError(errorMsg);
                        isRunning = false;
                    }
                });
            }

            function showMigrationError(message) {
                $('#migration-status').hide();
                $('#migration-error-message').text(message);
                $('#migration-error').show();
            }

            function finalizeMigration() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'abovewp_finalize_migration',
                        nonce: '<?php echo wp_create_nonce('abovewp_migration'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#migration-status').hide();
                            $('#migration-complete').show();
                            isRunning = false;
                        } else {
                            alert('<?php esc_html_e('Error finalizing migration:', 'abovewp-bulgarian-eurozone'); ?> ' + response.data.message);
                            isRunning = false;
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Failed to finalize migration', 'abovewp-bulgarian-eurozone'); ?>');
                        isRunning = false;
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Remove WordPress admin notices on plugin pages
     */
    public function remove_admin_notices_on_plugin_pages() {
        $screen = get_current_screen();
        
        // Only remove notices on our plugin pages
        if (isset($screen->id) && (
            strpos($screen->id, 'abovewp-bulgarian-eurozone') !== false || 
            strpos($screen->id, 'toplevel_page_abovewp') !== false
        )) {
            // Remove all admin notices
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            
            // Add our custom CSS to hide any notices that might get through
            echo '<style>
                .notice:not(.abovewp-migration-warning), .updated, .update-nag, .error:not(.abovewp-error-box), .warning, .info { 
                    display: none !important; 
                }
                .abovewp-migration-warning {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 12px 15px;
                    margin: 10px 0;
                }
                .abovewp-migration-warning ul {
                    margin: 10px 0 0 20px;
                }
                .abovewp-migration-warning li {
                    margin: 5px 0;
                }
            </style>';
        }
    }

    /**
     * Enqueue scripts and styles for blocks
     */
    public function enqueue_blocks_scripts() {
        if (!$this->is_site_currency_supported() || get_option('abovewp_bge_enabled', 'yes') !== 'yes') {
            return;
        }

        // Add CSS
        wp_enqueue_style(
            'abovewp-bulgarian-eurozone-blocks',
            ABOVEWP_BGE_PLUGIN_URL . 'assets/css/blocks.css',
            array(),
            ABOVEWP_BGE_VERSION
        );
        
        // Add JavaScript
        wp_enqueue_script(
            'abovewp-bulgarian-eurozone-blocks',
            ABOVEWP_BGE_PLUGIN_URL . 'assets/js/blocks.js',
            array('jquery'),
            ABOVEWP_BGE_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('abovewp-bulgarian-eurozone-blocks', 'abovewpBGE', array(
            'conversionRate' => $this->conversion_rate,
            'primaryCurrency' => $this->get_primary_currency(),
            'secondaryCurrency' => $this->get_secondary_currency(),
            'eurLabel' => esc_html($this->get_eur_label()),
            'bgnLabel' => esc_html($this->get_bgn_label()),
            'secondaryLabel' => esc_html($this->get_secondary_currency_label()),
            'eurPosition' => get_option('abovewp_bge_eur_position', 'right'),
            'eurFormat' => get_option('abovewp_bge_eur_format', 'brackets'),
            'bgnRounding' => get_option('abovewp_bge_bgn_rounding', 'smart')
        ));
    }

    /**
     * Add secondary currency price to line subtotal on thank you page
     *
     * @param string $subtotal Formatted line subtotal
     * @param object $item Order item
     * @param object $order WC_Order
     * @return string Modified subtotal with secondary currency equivalent
     */
    public function add_eur_to_thank_you_line_subtotal($subtotal, $item, $order) {
        // Get the tax display setting for orders
        $tax_display = get_option('woocommerce_tax_display_cart');
        
        if ('incl' === $tax_display) {
            // Include item tax in the subtotal for conversion
            $subtotal_primary = $item->get_total() + $item->get_total_tax();
        } else {
            $subtotal_primary = $item->get_total();
        }
        
        $subtotal_secondary = $this->convert_to_secondary_currency($subtotal_primary);
        
        return $this->add_eur_to_value($subtotal, $subtotal_secondary);
    }

    /**
     * Add secondary currency to order tax totals
     *
     * @param array $tax_totals
     * @param object $order
     * @return array
     */
    public function add_eur_to_order_tax_totals($tax_totals, $order) {
        $secondary_label = $this->get_secondary_currency_label();
        
        foreach ($tax_totals as $code => $tax) {
            $formatted_amount = null;
            $amount = 0;
            
            if (is_array($tax)) {
                $formatted_amount = isset($tax['formatted_amount']) ? $tax['formatted_amount'] : null;
                $amount = isset($tax['amount']) ? $tax['amount'] : 0;
            } elseif (is_object($tax)) {
                $formatted_amount = isset($tax->formatted_amount) ? $tax->formatted_amount : null;
                $amount = isset($tax->amount) ? $tax->amount : 0;
            }
            
            if ($formatted_amount && strpos($formatted_amount, $secondary_label) === false && $amount > 0) {
                $tax_amount_secondary = $this->convert_to_secondary_currency($amount);
                $formatted_amount_with_secondary = $this->add_eur_to_value($formatted_amount, $tax_amount_secondary);
                
                if (is_array($tax)) {
                    $tax['formatted_amount'] = $formatted_amount_with_secondary;
                    $tax_totals[$code] = $tax;
                } elseif (is_object($tax)) {
                    $tax->formatted_amount = $formatted_amount_with_secondary;
                    $tax_totals[$code] = $tax;
                }
            }
        }
        return $tax_totals;
    }

    /**
     * Check if an order was placed in BGN (legacy order before migration)
     *
     * @param WC_Order $order
     * @return bool
     */
    private function is_legacy_bgn_order($order) {
        if (!$order) {
            return false;
        }
        return $order->get_currency() === 'BGN';
    }

    /**
     * Format amount with EUR currency symbol (no conversion, just symbol change)
     *
     * @param float $amount The amount to format
     * @return string Formatted price with EUR symbol
     */
    private function format_as_eur($amount) {
        return wc_price($amount, array(
            'currency' => 'EUR',
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals' => wc_get_price_decimals(),
        ));
    }

    /**
     * Fix legacy order line subtotal to display with EUR symbol instead of BGN
     *
     * @param string $subtotal
     * @param object $item
     * @param WC_Order $order
     * @return string
     */
    public function fix_legacy_order_line_subtotal($subtotal, $item, $order) {
        if (!$this->is_legacy_bgn_order($order)) {
            return $subtotal;
        }
        
        // Get the line subtotal based on tax display setting
        $tax_display = get_option('woocommerce_tax_display_cart');
        
        if ('incl' === $tax_display) {
            $amount = $item->get_total() + $item->get_total_tax();
        } else {
            $amount = $item->get_total();
        }
        
        return $this->format_as_eur($amount);
    }

    /**
     * Fix legacy order total to display with EUR symbol instead of BGN
     *
     * @param string $formatted_total
     * @param WC_Order $order
     * @return string
     */
    public function fix_legacy_order_total($formatted_total, $order) {
        if (!$this->is_legacy_bgn_order($order)) {
            return $formatted_total;
        }
        
        $total = $order->get_total();
        return $this->format_as_eur($total);
    }

    /**
     * Fix legacy order subtotal display to show with EUR symbol instead of BGN
     *
     * @param string $subtotal
     * @param bool $compound
     * @param WC_Order $order
     * @return string
     */
    public function fix_legacy_order_subtotal_display($subtotal, $compound, $order) {
        if (!$this->is_legacy_bgn_order($order)) {
            return $subtotal;
        }
        
        $tax_display = get_option('woocommerce_tax_display_cart');
        
        if ('incl' === $tax_display) {
            $amount = $order->get_subtotal() + $order->get_cart_tax();
        } else {
            $amount = $order->get_subtotal();
        }
        
        return $this->format_as_eur($amount);
    }

    /**
     * Fix legacy order shipping display to show with EUR symbol instead of BGN
     *
     * @param string $shipping
     * @param WC_Order $order
     * @param string $tax_display
     * @return string
     */
    public function fix_legacy_order_shipping_display($shipping, $order, $tax_display) {
        if (!$this->is_legacy_bgn_order($order)) {
            return $shipping;
        }
        
        if ('incl' === $tax_display) {
            $amount = $order->get_shipping_total() + $order->get_shipping_tax();
        } else {
            $amount = $order->get_shipping_total();
        }
        
        if ($amount == 0) {
            return $shipping; // Keep original "Free!" text if applicable
        }
        
        return $this->format_as_eur($amount);
    }

    /**
     * Fix legacy order discount display to show with EUR symbol instead of BGN
     *
     * @param string $discount
     * @param WC_Order $order
     * @return string
     */
    public function fix_legacy_order_discount_display($discount, $order) {
        if (!$this->is_legacy_bgn_order($order)) {
            return $discount;
        }
        
        $amount = $order->get_total_discount();
        
        if ($amount == 0) {
            return $discount;
        }
        
        return '-' . $this->format_as_eur($amount);
    }

    /**
     * AJAX: Get total product count
     */
    public function ajax_get_product_count() {
        check_ajax_referer('abovewp_migration', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $products = get_posts($args);
        $count = count($products);

        update_option('abovewp_bge_migration_in_progress', true);
        update_option('abovewp_bge_migration_total', $count);
        update_option('abovewp_bge_migration_offset', 0);

        wp_send_json_success(array('count' => $count));
    }

    /**
     * AJAX: Migrate products in batches
     */
    public function ajax_migrate_products() {
        check_ajax_referer('abovewp_migration', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'abovewp-bulgarian-eurozone')));
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 50; // Process 50 products at a time

        $args = array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids'
        );

        $product_ids = get_posts($args);
        $processed = 0;
        $errors = array();

        foreach ($product_ids as $product_id) {
            $result = $this->migrate_product_prices($product_id);
            
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    /* translators: %1$d is the product ID, %2$s is the error message */
                    __('Product #%1$d: %2$s', 'abovewp-bulgarian-eurozone'),
                    $product_id,
                    $result->get_error_message()
                );
                // Store the last error for display
                update_option('abovewp_bge_migration_last_error', array(
                    'product_id' => $product_id,
                    'message' => $result->get_error_message(),
                    'time' => current_time('mysql')
                ));
            }
            $processed++;
            
            // Save progress after each product for granular resume capability
            update_option('abovewp_bge_migration_offset', $offset + $processed);
        }

        $has_more = count($product_ids) === $batch_size;

        $response = array(
            'processed' => $processed,
            'has_more' => $has_more
        );

        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }

        wp_send_json_success($response);
    }

    /**
     * Migrate a single product's prices from BGN to EUR
     *
     * @param int $product_id
     * @return true|WP_Error True on success, WP_Error on failure
     */
    private function migrate_product_prices($product_id) {
        try {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                return new WP_Error(
                    'product_not_found',
                    __('Product could not be loaded', 'abovewp-bulgarian-eurozone')
                );
            }
            
            // Convert regular price
            $regular_price = $product->get_regular_price();
            if ($regular_price) {
                $new_regular_price = $this->convert_bgn_to_eur($regular_price);
                $product->set_regular_price($new_regular_price);
            }
            
            // Convert sale price
            $sale_price = $product->get_sale_price();
            if ($sale_price) {
                $new_sale_price = $this->convert_bgn_to_eur($sale_price);
                $product->set_sale_price($new_sale_price);
            }
            
            // Save the product
            $product->save();
            
            // Handle variations if it's a variable product
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    
                    if (!$variation) {
                        continue; // Skip missing variations but don't fail the whole product
                    }
                    
                    // Convert variation regular price
                    $var_regular_price = $variation->get_regular_price();
                    if ($var_regular_price) {
                        $new_var_regular_price = $this->convert_bgn_to_eur($var_regular_price);
                        $variation->set_regular_price($new_var_regular_price);
                    }
                    
                    // Convert variation sale price
                    $var_sale_price = $variation->get_sale_price();
                    if ($var_sale_price) {
                        $new_var_sale_price = $this->convert_bgn_to_eur($var_sale_price);
                        $variation->set_sale_price($new_var_sale_price);
                    }
                    
                    $variation->save();
                }
                
                // Sync variable product price range
                WC_Product_Variable::sync($product_id);
            }
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error(
                'migration_exception',
                $e->getMessage()
            );
        }
    }

    /**
     * AJAX: Finalize migration by changing store currency
     */
    public function ajax_finalize_migration() {
        check_ajax_referer('abovewp_migration', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'abovewp-bulgarian-eurozone')));
        }

        try {
            // Update WooCommerce currency to EUR
            update_option('woocommerce_currency', 'EUR');

            delete_option('abovewp_bge_migration_in_progress');
            delete_option('abovewp_bge_migration_offset');
            delete_option('abovewp_bge_migration_total');
            delete_option('abovewp_bge_migration_last_error');

            // Clear WooCommerce caches
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients();
            }

            wp_send_json_success(array('message' => __('Migration completed successfully', 'abovewp-bulgarian-eurozone')));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s is the error message */
                    __('Failed to finalize migration: %s', 'abovewp-bulgarian-eurozone'),
                    $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX: Reset migration progress
     */
    public function ajax_reset_migration() {
        check_ajax_referer('abovewp_migration', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'abovewp-bulgarian-eurozone')));
        }

        delete_option('abovewp_bge_migration_in_progress');
        delete_option('abovewp_bge_migration_offset');
        delete_option('abovewp_bge_migration_total');
        delete_option('abovewp_bge_migration_last_error');

        wp_send_json_success(array('message' => __('Migration progress reset', 'abovewp-bulgarian-eurozone')));
    }

    /**
     * AJAX: Dismiss migration notice
     */
    public function ajax_dismiss_migration_notice() {
        check_ajax_referer('abovewp_dismiss_notice', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        update_user_meta(get_current_user_id(), 'abovewp_migration_notice_dismissed', true);

        wp_send_json_success();
    }
}

// Initialize the plugin
$abovewp_bge = new AboveWP_Bulgarian_Eurozone();