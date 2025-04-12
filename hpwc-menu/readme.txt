=== Menu Tools for HivePress & WooCommerce ===
Contributors: Chris Bruce
Tags: hivepress, woocommerce, menu, integration, custom links
Requires at least: 6.0
Tested up to: 6.7.2
Stable tag: 1.0.0
Requires PHP: 8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seamlessly integrate HivePress and WooCommerce navigation menus with custom links and visibility controls.

== Description ==

This plugin bridges the navigation menus of HivePress and WooCommerce, creating a unified menu experience for users. It allows you to:

- Merge HivePress and WooCommerce menu items into a single navigation structure.
- Add custom links to either or both menus with options for positioning (top or bottom).
- Control link visibility based on user roles or subscription/order history.
- Hide specific WooCommerce menu items as needed.

Ideal for sites using both HivePress and WooCommerce, enhancing user navigation with flexibility and customization.

== Installation ==

1. Upload the `hpwc-menu` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Settings > Menu Tools** to configure integration settings and custom links.

== Requirements ==

- WordPress 5.8 or higher
- PHP 7.2 or higher
- WooCommerce 5.0 or higher
- HivePress

== Usage ==

1. **Enable Integration**: In the settings, check "Enable Integration" to merge HivePress and WooCommerce menu items.
2. **Add Custom Links**: Use the "Custom Menu Links" section to add links, choosing from WordPress pages, custom URLs, WooCommerce pages, or HivePress routes. Specify menu location (HivePress, WooCommerce, or both) and position.
3. **Set Visibility**: Control who sees each custom link by selecting "All users," "Specific roles," or "Customer history" (based on orders/subscriptions).
4. **Hide Items**: Select WooCommerce menu items to exclude from display under "Hide WooCommerce Menu Items."

== Changelog ==

= 1.0.0 =
* Initial release.