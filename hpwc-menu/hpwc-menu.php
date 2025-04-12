<?php
/**
 * Plugin Name: Menu Tools for HivePress & WooCommerce
 * Description: Seamlessly integrate HivePress and WooCommerce navigation menus with custom links and visibility controls.
 * Version: 1.0.0
 * Author: Chris Bruce
 * Author URI: https://freestylr.co.uk/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: hpwc-menu
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('HPWC_MENU_VERSION', '1.0.0');
define('HPWC_MENU_PLUGIN_FILE', __FILE__);
define('HPWC_MENU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HPWC_MENU_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if required plugins are active
 *
 * @return bool Whether requirements are met
 */
function hpwc_menu_check_requirements() {
    $missing_plugins = [];

    if (!class_exists('WooCommerce')) {
        $missing_plugins[] = 'WooCommerce';
    }

    if (!class_exists('HivePress\Core')) {
        $missing_plugins[] = 'HivePress';
    }

    if (!empty($missing_plugins)) {
        add_action('admin_notices', function() use ($missing_plugins) {
            echo '<div class="notice notice-error"><p>' .
                sprintf(
                    esc_html__('Menu Tools for HivePress & WooCommerce requires the following plugins: %s.', 'hpwc-menu'),
                    '<strong>' . implode(', ', $missing_plugins) . '</strong>'
                ) .
                '</p></div>';
        });
        return false;
    }

    return true;
}

/**
 * Initialize the plugin
 */
function hpwc_menu_init() {
    load_plugin_textdomain('hpwc-menu', false, basename(HPWC_MENU_PLUGIN_DIR) . '/languages');

    if (!hpwc_menu_check_requirements()) {
        return;
    }

    require_once HPWC_MENU_PLUGIN_DIR . 'includes/class-menu-integration.php';

    $integration = new HPWC_Menu_Integration();
    $integration->init();
}

/**
 * Flush rewrite rules if needed
 */
function hpwc_menu_maybe_flush_rewrite_rules() {
    if (get_option('hpwc_menu_flush_rules') === 'yes') {
        flush_rewrite_rules();
        delete_option('hpwc_menu_flush_rules');
    }
}

/**
 * Plugin activation hook
 */
function hpwc_menu_activate() {
    update_option('hpwc_menu_flush_rules', 'yes');

    $default_options = [
        'hpwc_menu_enable_integration' => 'yes',
        'hpwc_menu_enable_custom_links' => 'no',
        'hpwc_menu_excluded_wc_items' => [],
        'hpwc_menu_custom_links' => [],
        'hpwc_menu_custom_links_visibility' => [],
    ];

    foreach ($default_options as $option => $value) {
        if (get_option($option) === false) {
            update_option($option, $value);
        }
    }
}

/**
 * Plugin deactivation hook
 */
function hpwc_menu_deactivate() {
    update_option('hpwc_menu_flush_rules', 'yes');
}

/**
 * Add settings link to plugin action links
 *
 * @param array $links Existing action links
 * @return array Modified action links
 */
function hpwc_menu_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=hpwc-menu-settings') . '">' . __('Settings', 'hpwc-menu') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

register_activation_hook(__FILE__, 'hpwc_menu_activate');
register_deactivation_hook(__FILE__, 'hpwc_menu_deactivate');

add_action('plugins_loaded', 'hpwc_menu_init', 20);
add_action('init', 'hpwc_menu_maybe_flush_rewrite_rules');
add_filter('plugin_action_links_' . plugin_basename(HPWC_MENU_PLUGIN_FILE), 'hpwc_menu_add_settings_link');