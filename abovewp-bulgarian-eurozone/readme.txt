=== AboveWP Bulgarian Eurozone ===
Contributors: wpabove, pdpetrov98
Tags: eurozone, bulgaria, currency, dual-currency, euro
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 5.0
WC tested up to: 9.8

Display WooCommerce prices in both Bulgarian Lev (BGN) and Euro (EUR) as Bulgaria prepares to join the Eurozone.

== Description ==

A WordPress plugin that adds dual currency display (BGN and EUR) for WooCommerce as Bulgaria prepares to join the Eurozone. The plugin automatically displays prices in both Bulgarian Lev (BGN) and Euro (EUR) throughout your WooCommerce store.

**[AboveWP](https://abovewp.com)**

= Features =
* Display prices in both BGN and EUR throughout your WooCommerce store
* Fixed conversion rate at the official rate (1.95583 BGN = 1 EUR)
* Customizable EUR label
* Configurable EUR price positioning (left or right of BGN prices)
* Support for all WooCommerce price points including:
  * Single product pages
  * Variable product pages
  * Cart item prices
  * Cart subtotals
  * Cart totals
  * Order confirmation & email
  * My Account orders table
  * REST API responses
  * Shipping method labels
  * Tax amount labels
  * Mini cart
  * WooCommerce Gutenberg blocks (cart, checkout, and shipping methods)
  * Dynamic updates when shipping methods change in checkout blocks

= Requirements =
* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.2 or higher

== Installation ==

1. Upload the `abovewp-bulgarian-eurozone` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to AboveWP > Eurozone Settings to configure the plugin

== Configuration ==

1. Navigate to AboveWP > Eurozone Settings in your WordPress admin
2. Enable or disable dual currency display
3. Customize the EUR label if needed
4. Choose whether EUR prices appear on the left or right of BGN prices
5. Save changes

== Frequently Asked Questions ==

= Will this plugin change how payments are processed? =
No, this plugin only affects how prices are displayed. It adds EUR prices as informational display alongside the main BGN prices. Your payment gateway will continue to process transactions in your store's base currency (BGN).

= Can I customize how the EUR prices are displayed? =
Yes, basic styling is included but you can add custom CSS to your theme to further customize the appearance of the EUR prices.

= Does this plugin work with other WooCommerce extensions? =
The plugin is designed to be compatible with standard WooCommerce features. For specific extensions, compatibility may vary.

= Will this plugin continue to be useful after Bulgaria joins the Eurozone? =
Once Bulgaria changes the primary currency we will have a functionality ready, so that if your store changes to EUR we will be showing BGN as well, in order to comply with the law requirements for joining the eurozone for the first one year.

== Screenshots ==
1. Settings Page Part 1
2. Settings Page Part 2

== Changelog ==

= 1.2.4 =
* Fix tax display in order total for some themes

= 1.2.3 =
* Now shows old price on sale products in euro as well.

= 1.2.2 =
* Compatibility issue fixes for promo codes.

= 1.2.1 =
* FIX ORDER TOTALS BUG

= 1.2.0 =
* NEW: Added EUR price display format option - choose between brackets (25лв. (12.78 €)) or side divider (25лв. / 12.78 €)
* NEW: Enhanced admin settings with clear examples for both display formats
* IMPROVED: Fixed thank you page order total not showing EUR equivalent
* IMPROVED: Updated JavaScript to support both bracket and divider formats
* IMPROVED: Added proper translation support for new format options
* IMPROVED: Consistent format application across all price display locations

= 1.1.5 =
* Further enhancements to TAX support.

= 1.1.4 =
* Fixed an issue with Tax items on Thank You page

= 1.1.3 =
* Improved functionality for 3rd party shipping methods

= 1.1.2 =
* Resolved an issue causing fatal error in order edit page for some tax types.

= 1.1.1 =
* NEW: Support for 3rd party plugins (e.g. Speedy Shipping Method by Extensa)
* IMPROVED: Better handling of shipping method changes in both traditional and block-based checkouts
* IMPROVED: More robust mutation observer for real-time price updates

= 1.1.0 =
* REMOVED: Configurable conversion rate option - now uses fixed official rate (1.95583 BGN = 1 EUR)
* NEW: Added EUR price positioning option - choose left or right of BGN prices
* IMPROVED: Enhanced positioning consistency across all price display locations
* IMPROVED: Updated JavaScript for Gutenberg blocks to support positioning
* IMPROVED: Better admin interface with clearer settings organization

= 1.0.2 =
* Fixed issue with shipping prices not displaying in EUR in WooCommerce order emails
* Improved shipping price conversion handling in email templates

= 1.0.1 =
* Added support for WooCommerce block-based cart and checkout
* Fixed issue with double EUR price display in mini cart
* Improved handling of variable product prices
* Enhanced compatibility with other plugins

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.4 =
Fix tax display in order total for some themes

= 1.2.3 =
Now shows old price on sale products in euro as well.

= 1.2.2 =
Compatibility issue fixes for promo codes.

= 1.2.1 =
Fix an order totals bug displaying prices twice.

= 1.2.0 =
Major update: Added new EUR price display format option allowing you to choose between brackets and side divider formats. Also fixes thank you page order total display. Update now for more flexible price formatting options.

= 1.1.5 =
Further enhancements to TAX support.

= 1.1.4 =
Fixed an issue with Tax items on Thank You page

= 1.1.3 =
Improved functionality for 3rd party shipping methods

= 1.1.2 =
Resolved an issue causing fatal error in order edit page for some tax types.

= 1.1.1 =
Added support: 3rd party shipping plugins (e.g. Speedy Shipping Method by Extensa)

= 1.1.0 =
Major update: Removed configurable conversion rate (now fixed at official rate) and added EUR price positioning options. Update now for better compliance and positioning control.

= 1.0.2 =
This update fixes an issue where shipping prices were not displaying in EUR in WooCommerce order emails.

= 1.0.1 =
This update adds support for WooCommerce block-based cart and checkout, fixes issues with the mini cart, and improves compatibility.

= 1.0.0 =
Initial release of AboveWP Bulgarian Eurozone.

== Support ==

For support, feature requests, or bug reports, please contact us at:

* Website: [AboveWP.com](https://abovewp.com)
* Email: support@abovewp.com