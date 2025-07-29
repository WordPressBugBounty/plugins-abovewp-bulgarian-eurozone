<?php
/**
 * Plugin Name: AboveWP Bulgarian Eurozone
 * Description: Adds dual currency display (BGN and EUR) for WooCommerce as Bulgaria prepares to join the Eurozone
 * Version: 1.2.3
 * Author: AboveWP
 * Author URI: https://abovewp.com
 * Text Domain: abovewp-bulgarian-eurozone
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 9.8
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ABOVEWP_BGE_VERSION', '1.2.3');
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

        // Only proceed if dual currency display is enabled and currency is BGN
        if (get_option('abovewp_bge_enabled', 'yes') !== 'yes' || !$this->is_site_currency_bgn()) {
            return;
        }

        // Initialize all hooks if enabled
        $this->init_hooks();

        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));

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
            add_filter('woocommerce_order_tax_totals', array($this, 'add_eur_to_order_tax_totals'), 10, 2);
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
        
        // Enqueue JavaScript for Blocks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_blocks_scripts'));
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        // Only load on our plugin's admin page
        if (strpos($hook, 'abovewp-bulgarian-eurozone') !== false) {
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
        

        
        // EUR label setting
        register_setting(
            'abovewp_bge_settings',   // Option group
            'abovewp_bge_eur_label',   // Option name
            array(                     // Args
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '€',
                'description' => 'EUR price label'
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
            
            <?php if (!$this->is_site_currency_bgn()): ?>
            <div class="notice notice-error">
                <p>
                    <?php esc_html_e('This plugin requires your WooCommerce currency to be set to Bulgarian Lev (BGN). The dual currency display will not work until you change your store currency to BGN.', 'abovewp-bulgarian-eurozone'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=general')); ?>" class="button button-secondary">
                        <?php esc_html_e('Change Currency Settings', 'abovewp-bulgarian-eurozone'); ?>
                    </a>
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
                            <select name="abovewp_bge_enabled" <?php disabled(!$this->is_site_currency_bgn()); ?>>
                                <option value="yes" <?php selected(get_option('abovewp_bge_enabled', 'yes'), 'yes'); ?>><?php esc_html_e('Yes', 'abovewp-bulgarian-eurozone'); ?></option>
                                <option value="no" <?php selected(get_option('abovewp_bge_enabled', 'yes'), 'no'); ?>><?php esc_html_e('No', 'abovewp-bulgarian-eurozone'); ?></option>
                            </select>
                            <?php if (!$this->is_site_currency_bgn()): ?>
                                <p class="description"><?php esc_html_e('Dual currency display is only available when your store currency is BGN.', 'abovewp-bulgarian-eurozone'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('EUR Price Label', 'abovewp-bulgarian-eurozone'); ?></th>
                        <td>
                            <input type="text" name="abovewp_bge_eur_label" value="<?php echo esc_attr(get_option('abovewp_bge_eur_label', '€')); ?>" <?php disabled(!$this->is_site_currency_bgn()); ?> />
                            <p class="description"><?php esc_html_e('The label to use for EUR prices (default: €)', 'abovewp-bulgarian-eurozone'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('EUR Price Position', 'abovewp-bulgarian-eurozone'); ?></th>
                        <td>
                            <select name="abovewp_bge_eur_position" <?php disabled(!$this->is_site_currency_bgn()); ?>>
                                <option value="right" <?php selected(get_option('abovewp_bge_eur_position', 'right'), 'right'); ?>><?php esc_html_e('Right of BGN price', 'abovewp-bulgarian-eurozone'); ?></option>
                                <option value="left" <?php selected(get_option('abovewp_bge_eur_position', 'right'), 'left'); ?>><?php esc_html_e('Left of BGN price', 'abovewp-bulgarian-eurozone'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose whether EUR prices appear on the left or right of BGN prices', 'abovewp-bulgarian-eurozone'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('EUR Price Display Format', 'abovewp-bulgarian-eurozone'); ?></th>
                        <td>
                            <select name="abovewp_bge_eur_format" <?php disabled(!$this->is_site_currency_bgn()); ?>>
                                <option value="brackets" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'brackets'); ?>><?php esc_html_e('Brackets (25лв. (12.78 €))', 'abovewp-bulgarian-eurozone'); ?></option>
                                <option value="divider" <?php selected(get_option('abovewp_bge_eur_format', 'brackets'), 'divider'); ?>><?php esc_html_e('Side divider (25лв. / 12.78 €)', 'abovewp-bulgarian-eurozone'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose how EUR prices are displayed relative to BGN prices', 'abovewp-bulgarian-eurozone'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php if ($this->is_site_currency_bgn()): ?>
                <h2><?php esc_html_e('Display Locations', 'abovewp-bulgarian-eurozone'); ?></h2>
                <p class="description"><?php esc_html_e('Select where you want to display EUR prices:', 'abovewp-bulgarian-eurozone'); ?></p>
                
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
                                <?php esc_html_e('Show EUR price', 'abovewp-bulgarian-eurozone'); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
                
                <?php submit_button(null, 'primary', 'submit', true, $this->is_site_currency_bgn() ? [] : ['disabled' => 'disabled']); ?>
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
     * Get EUR label from settings
     *
     * @return string
     */
    private function get_eur_label() {
        return esc_html(get_option('abovewp_bge_eur_label', '€'));
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
     * Format dual currency price based on position setting
     *
     * @param string $bgn_price_html The original BGN price HTML
     * @param float $eur_price The EUR price amount
     * @param string $css_class Optional CSS class for EUR price span
     * @return string The formatted dual currency price
     */
    private function format_dual_price($bgn_price_html, $eur_price, $css_class = 'eur-price') {
        $eur_formatted = $this->format_eur_price($eur_price);
        $format = get_option('abovewp_bge_eur_format', 'brackets');
        $position = get_option('abovewp_bge_eur_position', 'right');
        
        if ($format === 'divider') {
            // Side divider format: "25лв. / 12.78 €"
            $eur_span = '<span class="' . esc_attr($css_class) . '">/ ' . esc_html($eur_formatted) . '</span>';
        } else {
            // Brackets format: "25лв. (12.78 €)"
            $eur_span = '<span class="' . esc_attr($css_class) . '">(' . esc_html($eur_formatted) . ')</span>';
        }
        
        if ($position === 'left') {
            return $eur_span . ' ' . $bgn_price_html;
        } else {
            return $bgn_price_html . ' ' . $eur_span;
        }
    }

    /**
     * Add EUR price to existing value based on position setting
     *
     * @param string $existing_value The existing price value
     * @param float $eur_price The EUR price amount
     * @param string $css_class Optional CSS class for EUR price span
     * @return string The modified value with EUR price added
     */
    private function add_eur_to_value($existing_value, $eur_price, $css_class = 'eur-price') {
        $eur_formatted = $this->format_eur_price($eur_price);
        $format = get_option('abovewp_bge_eur_format', 'brackets');
        $position = get_option('abovewp_bge_eur_position', 'right');
        
        if ($format === 'divider') {
            // Side divider format: "25лв. / 12.78 €"
            $eur_span = '<span class="' . esc_attr($css_class) . '">/ ' . esc_html($eur_formatted) . '</span>';
        } else {
            // Brackets format: "25лв. (12.78 €)"
            $eur_span = '<span class="' . esc_attr($css_class) . '">(' . esc_html($eur_formatted) . ')</span>';
        }
        
        if ($position === 'left') {
            return $eur_span . ' ' . $existing_value;
        } else {
            return $existing_value . ' ' . $eur_span;
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
            $regular_price_bgn = wc_get_price_to_display($product, array('price' => $product->get_regular_price()));
            $sale_price_bgn = wc_get_price_to_display($product, array('price' => $product->get_sale_price()));
            
            // Convert to EUR
            $regular_price_eur = $this->convert_bgn_to_eur($regular_price_bgn);
            $sale_price_eur = $this->convert_bgn_to_eur($sale_price_bgn);
            
            $regular_price_dual = $this->format_dual_price(wc_price($regular_price_bgn), $regular_price_eur);
            $sale_price_dual = $this->format_dual_price(wc_price($sale_price_bgn), $sale_price_eur);
            
            // Use WooCommerce's built-in sale price formatting
            $price_html = wc_format_sale_price($regular_price_dual, $sale_price_dual);
            
            return $price_html;
        }
        
        // Use WooCommerce function that respects tax display settings
        $price_bgn = wc_get_price_to_display($product);
        $price_eur = $this->convert_bgn_to_eur($price_bgn);
        
        return $this->format_dual_price($price_html, $price_eur);
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
            $min_price_bgn = $product->get_variation_price('min', true); // true = include taxes
            $max_price_bgn = $product->get_variation_price('max', true);
        } else {
            $min_price_bgn = $product->get_variation_price('min', false); // false = exclude taxes
            $max_price_bgn = $product->get_variation_price('max', false);
        }
        
        // Convert to EUR
        $min_price_eur = $this->convert_bgn_to_eur($min_price_bgn);
        $max_price_eur = $this->convert_bgn_to_eur($max_price_bgn);
        
        // If prices are the same, show single price, otherwise show range
        if ($min_price_bgn === $max_price_bgn) {
            return $this->format_dual_price($price_html, $min_price_eur);
        } else {
            $min_price_formatted = esc_html(number_format($min_price_eur, 2));
            $max_price_formatted = esc_html(number_format($max_price_eur, 2));
            $eur_label = esc_html($this->get_eur_label());
            $eur_range = $min_price_formatted . ' - ' . $max_price_formatted . ' ' . $eur_label;
            
            $format = get_option('abovewp_bge_eur_format', 'brackets');
            $position = get_option('abovewp_bge_eur_position', 'right');
            
            if ($format === 'divider') {
                // Side divider format: "25лв. / 12.78 €"
                $eur_span = '<span class="eur-price">/ ' . $eur_range . '</span>';
            } else {
                // Brackets format: "25лв. (12.78 €)"
                $eur_span = '<span class="eur-price">(' . $eur_range . ')</span>';
            }
            
            if ($position === 'left') {
                return $eur_span . ' ' . $price_html;
            } else {
                return $price_html . ' ' . $eur_span;
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
        
        $price_eur = $this->convert_bgn_to_eur($product_price);
        
        return $this->format_dual_price($price_html, $price_eur);
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
            $subtotal_bgn = wc_get_price_including_tax($cart_item['data'], array('qty' => $quantity));
        } else {
            $subtotal_bgn = wc_get_price_excluding_tax($cart_item['data'], array('qty' => $quantity));
        }
        
        $subtotal_eur = $this->convert_bgn_to_eur($subtotal_bgn);
        
        return $this->format_dual_price($subtotal, $subtotal_eur);
    }

    /**
     * Display dual price for cart totals
     *
     * @param string $total
     * @return string
     */
    public function display_dual_price_cart_total($total) {
        // Cart total always includes all taxes and fees as displayed
        $cart_total_bgn = WC()->cart->get_total(false);
        $cart_total_eur = $this->convert_bgn_to_eur($cart_total_bgn);
        
        return $this->format_dual_price($total, $cart_total_eur);
    }

    /**
     * Display dual price for cart fees
     *
     * @param string $fee_html
     * @param object $fee
     * @return string
     */
    public function display_dual_price_cart_fee($fee_html, $fee) {
        if (strpos($fee_html, $this->get_eur_label()) !== false) {
            return $fee_html;
        }
        
        $fee_amount_bgn = $fee->amount;
        if ($fee_amount_bgn > 0) {
            $fee_amount_eur = $this->convert_bgn_to_eur($fee_amount_bgn);
            $fee_html = $this->add_eur_to_value($fee_html, $fee_amount_eur);
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
            $cart_subtotal_bgn = $cart->get_subtotal() + $cart->get_subtotal_tax();
        } else {
            $cart_subtotal_bgn = $cart->get_subtotal();
        }
        
        $cart_subtotal_eur = $this->convert_bgn_to_eur($cart_subtotal_bgn);
        
        return $this->format_dual_price($subtotal, $cart_subtotal_eur);
    }

    /**
     * Add EUR to order totals
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
                // Add EUR to subtotal based on tax display mode
                if ('incl' === $tax_display) {
                    $subtotal_bgn = $order->get_subtotal() + $order->get_total_tax();
                } else {
                    $subtotal_bgn = $order->get_subtotal();
                }
                $subtotal_eur = $this->convert_bgn_to_eur($subtotal_bgn);
                $row['value'] = $this->add_eur_to_value($row['value'], $subtotal_eur);
            } 
            elseif ($key === 'shipping') {
                // Add EUR to shipping based on tax display mode
                if ('incl' === $tax_display) {
                    $shipping_total_bgn = $order->get_shipping_total() + $order->get_shipping_tax();
                } else {
                    $shipping_total_bgn = $order->get_shipping_total();
                }
                if ($shipping_total_bgn > 0 && strpos($row['value'], $this->get_eur_label()) === false) {
                    $shipping_total_eur = $this->convert_bgn_to_eur($shipping_total_bgn);
                    $row['value'] = $this->add_eur_to_value($row['value'], $shipping_total_eur);
                }
            }
            elseif ($key === 'tax' || strpos($key, 'tax') === 0) {
                $tax_total_bgn = $order->get_total_tax();
                if ($tax_total_bgn > 0) {
                    $tax_total_eur = $this->convert_bgn_to_eur($tax_total_bgn);
                    $row['value'] = $this->add_eur_to_value($row['value'], $tax_total_eur);
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
                        $fee_total_eur = $this->convert_bgn_to_eur($fee_total);
                        $row['value'] = $this->add_eur_to_value($row['value'], $fee_total_eur);
                        break; // Only process the first fee that matches
                    }
                }
            }
            elseif ($key === 'order_total') {
                // Add EUR to order total (total always includes all taxes and fees)
                $total_bgn = $order->get_total();
                $total_eur = $this->convert_bgn_to_eur($total_bgn);
                $row['value'] = $this->add_eur_to_value($row['value'], $total_eur);
            }
            
            $modified_rows[$key] = $row;
        }
        
        return $modified_rows;
    }

    /**
     * Add EUR column to orders table
     *
     * @param array $columns
     * @return array
     */
    public function add_eur_column_to_orders_table($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            if ($key === 'order-total') {
                // Translators: %s is the currency label (EUR)
                $new_columns['order-total-eur'] = sprintf(esc_html__('Total (%s)', 'abovewp-bulgarian-eurozone'), esc_html($this->get_eur_label()));
            }
        }
        
        return $new_columns;
    }

    /**
     * Add EUR value to orders table
     *
     * @param object $order
     */
    public function add_eur_value_to_orders_table($order) {
        $order_total_bgn = $order->get_total();
        $order_total_eur = $this->convert_bgn_to_eur($order_total_bgn);
        
        echo esc_html($this->format_eur_price($order_total_eur));
    }

    /**
     * Add EUR price to API responses
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
                $price_bgn = wc_get_price_to_display($product);
                $price_eur = $this->convert_bgn_to_eur($price_bgn);
                $data['price_eur'] = number_format($price_eur, 2);
                $response->set_data($data);
            }
        }
        
        return $response;
    }

    /**
     * Add EUR to shipping label
     *
     * @param string $label
     * @param object $method
     * @return string
     */
    public function add_eur_to_shipping_label($label, $method) {
        if ($method->cost > 0) {
            $shipping_cost_bgn = $method->cost;
            $shipping_cost_eur = $this->convert_bgn_to_eur($shipping_cost_bgn);
            $label = $this->add_eur_to_value($label, $shipping_cost_eur);
        }
        
        return $label;
    }

    /**
     * Add EUR to mini cart
     *
     * @param string $html
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function add_eur_to_mini_cart($html, $cart_item, $cart_item_key) {
        // Check if the HTML already contains EUR price to prevent duplicates
        if (strpos($html, $this->get_eur_label()) !== false || strpos($html, 'eur-price') !== false) {
            return $html;
        }
        
        $quantity = $cart_item['quantity'];
        
        // Use WooCommerce cart's tax-aware calculation for mini cart
        if (WC()->cart->display_prices_including_tax()) {
            $subtotal_bgn = wc_get_price_including_tax($cart_item['data'], array('qty' => $quantity));
        } else {
            $subtotal_bgn = wc_get_price_excluding_tax($cart_item['data'], array('qty' => $quantity));
        }
        
        $subtotal_eur = $this->convert_bgn_to_eur($subtotal_bgn);
        
        return $this->add_eur_to_value($html, $subtotal_eur);
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
        
        $discount_eur = $this->convert_bgn_to_eur($discount);
        $GLOBALS['dual_currency_coupon_eur'] = $discount_eur;
        
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
            $discount_eur = $this->convert_bgn_to_eur($discount_amount);
            $discount_eur = -$discount_eur;
            return $this->add_eur_to_value($coupon_html, $discount_eur);
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
            <p><?php esc_html_e('Adds dual currency display (BGN and EUR) for WooCommerce as Bulgaria prepares to join the Eurozone', 'abovewp-bulgarian-eurozone'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=abovewp-bulgarian-eurozone')); ?>" class="button button-primary">
                <?php esc_html_e('Configure', 'abovewp-bulgarian-eurozone'); ?>
            </a>
        </div>
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
                .notice, .updated, .update-nag, .error, .warning, .info { 
                    display: none !important; 
                }
            </style>';
        }
    }

    /**
     * Enqueue scripts and styles for blocks
     */
    public function enqueue_blocks_scripts() {
        if (!$this->is_site_currency_bgn() || get_option('abovewp_bge_enabled', 'yes') !== 'yes') {
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
            'eurLabel' => esc_html($this->get_eur_label()),
            'eurPosition' => get_option('abovewp_bge_eur_position', 'right'),
            'eurFormat' => get_option('abovewp_bge_eur_format', 'brackets')
        ));
    }

    /**
     * Add EUR price to line subtotal on thank you page
     *
     * @param string $subtotal Formatted line subtotal
     * @param object $item Order item
     * @param object $order WC_Order
     * @return string Modified subtotal with EUR equivalent
     */
    public function add_eur_to_thank_you_line_subtotal($subtotal, $item, $order) {
        // Get the tax display setting for orders
        $tax_display = get_option('woocommerce_tax_display_cart');
        
        if ('incl' === $tax_display) {
            // Include item tax in the subtotal for EUR conversion
            $subtotal_bgn = $item->get_total() + $item->get_total_tax();
        } else {
            $subtotal_bgn = $item->get_total();
        }
        
        $subtotal_eur = $this->convert_bgn_to_eur($subtotal_bgn);
        
        return $this->add_eur_to_value($subtotal, $subtotal_eur);
    }

    /**
     * Add EUR to order tax totals
     *
     * @param array $tax_totals
     * @param object $order
     * @return array
     */
    public function add_eur_to_order_tax_totals($tax_totals, $order) {
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
            
            if ($formatted_amount && strpos($formatted_amount, $this->get_eur_label()) === false && $amount > 0) {
                $tax_amount_eur = $this->convert_bgn_to_eur($amount);
                $formatted_amount_with_eur = $this->add_eur_to_value($formatted_amount, $tax_amount_eur);
                
                if (is_array($tax)) {
                    $tax['formatted_amount'] = $formatted_amount_with_eur;
                    $tax_totals[$code] = $tax;
                } elseif (is_object($tax)) {
                    $tax->formatted_amount = $formatted_amount_with_eur;
                    $tax_totals[$code] = $tax;
                }
            }
        }
        return $tax_totals;
    }
}

// Initialize the plugin
$abovewp_bge = new AboveWP_Bulgarian_Eurozone();