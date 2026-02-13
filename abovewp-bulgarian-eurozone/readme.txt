=== AboveWP Bulgarian Eurozone ===
Contributors: wpabove, pdpetrov98
Tags: eurozone, bulgaria, currency, dual-currency, euro
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 5.0
WC tested up to: 10.4

Display WooCommerce prices in both Bulgarian Lev (BGN) and Euro (EUR) bidirectionally as Bulgaria prepares to join the Eurozone.

== Description ==

A WordPress plugin that adds bidirectional dual currency display (BGN ⇄ EUR) for WooCommerce as Bulgaria prepares to join the Eurozone. The plugin automatically displays prices in both Bulgarian Lev (BGN) and Euro (EUR) throughout your WooCommerce store, working in both directions.

**[AboveWP](https://abovewp.com)**

= Features =
* **Bidirectional support**: Works when store currency is BGN (shows EUR) OR when store currency is EUR (shows BGN)
* **Currency Migration Tool**: One-click conversion of all product prices from BGN to EUR with automatic store currency update
* Display prices in both BGN and EUR throughout your WooCommerce store
* Fixed conversion rate at the official rate (1.95583 BGN = 1 EUR)
* Standard currency symbols (€ for EUR, лв. for BGN)
* Configurable secondary currency positioning (left or right of primary prices)
* Batch processing for stores with thousands of products
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

== Installation ==

1. Upload the `abovewp-bulgarian-eurozone` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to AboveWP > Eurozone Settings to configure the plugin

== Configuration ==

1. Navigate to AboveWP > Eurozone Settings in your WordPress admin
2. Enable or disable dual currency display
3. Choose whether secondary currency appears on the left or right of primary prices
4. Select display format (brackets or side divider)
5. Choose which pages should display dual currency
6. Save changes

== Currency Migration Tool ==

When Bulgaria joins the Eurozone, use the built-in Currency Migration Tool to seamlessly transition your store from BGN to EUR:

1. Navigate to AboveWP > Currency Migration in your WordPress admin
2. **IMPORTANT**: Create a full database backup before proceeding
3. Review the migration warnings and instructions
4. Click "Start Migration to EUR" to begin the process
5. The tool will:
   * Convert all product prices (regular and sale prices) from BGN to EUR
   * Update all product variations
   * Change your WooCommerce store currency to EUR
   * Process products in batches to handle large catalogs
6. After completion, verify your product prices and currency settings

**Note**: The migration is irreversible without a database backup. Always backup your database first!

== Frequently Asked Questions ==

= Will this plugin change how payments are processed? =
No, this plugin only affects how prices are displayed. It adds EUR prices as informational display alongside the main BGN prices. Your payment gateway will continue to process transactions in your store's base currency (BGN).

= Can I customize how the EUR prices are displayed? =
Yes, basic styling is included but you can add custom CSS to your theme to further customize the appearance of the EUR prices.

= Does this plugin work with other WooCommerce extensions? =
The plugin is designed to be compatible with standard WooCommerce features. For specific extensions, compatibility may vary.

= Will this plugin continue to be useful after Bulgaria joins the Eurozone? =
Yes! The plugin now supports bidirectional currency display. If your store currency is EUR, it will automatically show BGN prices alongside, complying with the law requirements for the first year after joining the Eurozone.

= How does the Currency Migration Tool work? =
The Currency Migration Tool automatically converts all your product prices from BGN to EUR using the official exchange rate (1.95583 BGN = 1 EUR). It processes products in batches to handle stores with thousands of products without timeout issues. The tool updates regular prices, sale prices, and all product variations, then changes your store currency to EUR.

= Is the currency migration reversible? =
The migration permanently changes your product prices and store currency. It is only reversible if you have a database backup. Always create a full database backup before running the migration tool.

= Will the migration tool work with large product catalogs? =
Yes! The migration tool processes products in batches of 50, making it suitable for stores with thousands of products. You'll see a progress bar showing the migration status in real-time.

== Screenshots ==
1. Settings Page Part 1
2. Settings Page Part 2

== Changelog ==

= 2.2.1 =
* FIX: Replaced deprecated `woocommerce_order_tax_totals` filter with `woocommerce_order_get_tax_totals` (deprecated since WooCommerce 3.0)

= 2.1.1 =
* FIX: Shipping price display order now correctly shows EUR first when website currency is EUR (EUR → BGN conversion)
* FIX: Enhanced Econt shipping method detection for dynamic price updates

= 2.1.0 =
* NEW: BGN price rounding setting - choose to keep exact decimals (19.99 лв.) or round up to whole numbers (20.00 лв.) when close
* NEW: Migration resume capability - if migration is interrupted, you can resume from where it left off

= 2.0.3 =
* NEW: Locale-aware dual currency display - BGN prices now only appear alongside EUR on Bulgarian locale (bg_BG) for better multilang/multicurrency compatibility
* NEW: Filter `abovewp_bge_convert_eur_to_bgn` - developers can now customize the EUR to BGN rounding logic

= 2.0.2 =
Improved rounding on bgn currency while euro is the main one

= 2.0.1 =
* FIXED: BGN to EUR conversion rounding issue - prices like 49 BGN now correctly display as 49.00 лв. instead of 48.99 лв. after migration
* FIXED: Legacy order display - orders placed before EUR migration now show € symbol instead of лв. for consistent currency display

= 2.0.0 =
* NEW: Currency Migration Tool - one-click conversion of all product prices from BGN to EUR
* NEW: Bidirectional currency support - now works when store currency is EUR (shows BGN) or BGN (shows EUR)
* NEW: Automatic detection of primary currency and displays the opposite as secondary
* NEW: Batch processing for migration to handle stores with thousands of products
* NEW: Real-time progress tracking during currency migration
* NEW: Admin-wide notices for currency migration availability
* IMPROVED: All conversion functions now work bidirectionally
* IMPROVED: Admin settings updated to reflect dual-mode support with clear indicators
* IMPROVED: API responses now include both price_eur and price_bgn fields depending on primary currency
* IMPROVED: JavaScript blocks updated with primary/secondary currency awareness
* IMPROVED: Simplified settings interface by removing unnecessary customization options
* IMPROVED: Enhanced admin UI with AboveWP dark theme styling
* FIXED: Sale prices now correctly converted in Gutenberg cart blocks
* This update prepares the plugin for Bulgaria's Eurozone transition, allowing stores to switch to EUR while still displaying BGN

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

= 2.1.0 =
New BGN rounding setting lets you choose exact decimals or round up. Migration now supports resume after interruption.

= 2.0.3 =
Improved multilang/multicurrency support: BGN prices now only display on Bulgarian locale when store currency is EUR. Added developer filter for custom rounding logic.

= 2.0.2 =
Improved rounding

= 2.0.1 =
Fixes rounding issues with BGN prices after EUR migration and corrects currency display for legacy orders.

= 2.0.0 =
Major update: Version 2.0 adds the Currency Migration Tool and bidirectional currency support! When Bulgaria joins the Eurozone, use the built-in migration tool to automatically convert all product prices from BGN to EUR. The plugin now works when your store currency is EUR (showing BGN) or BGN (showing EUR). IMPORTANT: Always backup your database before using the migration tool. Update now to prepare for the currency switch.

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