<?php
/**
 * Menu Integration Class
 *
 * @package HivePress & WooCommerce Menu Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main integration class
 */
class HPWC_Menu_Integration {
    /**
     * HivePress URLs cache
     */
    private $hp_urls = [];

    /**
     * Init integration
     */
    public function init() {
        // Admin settings
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Front-end settings
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Initialize HP URL cache
        add_action('init', [$this, 'initialize_hp_urls'], 5);
        
        // Register endpoints
        add_action('init', [$this, 'register_endpoints']);
        
        // Add cross-menu integration
        add_filter('woocommerce_account_menu_items', [$this, 'add_custom_links_to_wc_menu'], 20);
        add_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
        
        // Endpoint URL modification
        add_filter('woocommerce_get_endpoint_url', [$this, 'modify_menu_item_urls'], 10, 2);
        add_action('template_redirect', [$this, 'fix_endpoint_urls'], 10);
        
        // Hide excluded items
        add_action('wp_head', [$this, 'hide_excluded_items']);
        
        // Fix menu items
        add_filter('wp_nav_menu_items', [$this, 'fix_menu_item_links'], 100, 2);
        
        // Cache URLs for better performance
        add_action('wp', [$this, 'cache_hivepress_urls']);
        
        // Add direct link support
        add_action('wp', [$this, 'add_direct_hp_link_support']);
        
        // AJAX handlers
        add_action('wp_ajax_hpwc_menu_get_hp_endpoint_url', [$this, 'ajax_get_hp_endpoint_url']);
        add_action('wp_ajax_nopriv_hpwc_menu_get_hp_endpoint_url', [$this, 'ajax_get_hp_endpoint_url']);
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            __('Menu Tools for HivePress & WooCommerce', 'hpwc-menu'),
            __('Menu Tools', 'hpwc-menu'),
            'manage_options',
            'hpwc-menu-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('hpwc_menu_settings', 'hpwc_menu_enable_integration', ['default' => 'yes']);
        register_setting('hpwc_menu_settings', 'hpwc_menu_enable_custom_links', ['default' => 'no']);
        register_setting('hpwc_menu_settings', 'hpwc_menu_custom_links', ['sanitize_callback' => [$this, 'sanitize_custom_links']]);
        register_setting('hpwc_menu_settings', 'hpwc_menu_excluded_wc_items', [
            'sanitize_callback' => function($value) {
                if (!isset($value) || $value === '') {
                    return [];
                }
                if (!is_array($value)) {
                    return [];
                }
                $sanitized = array_map('sanitize_text_field', $value);
                return array_unique(array_filter($sanitized));
            },
            'default' => [],
        ]);
        register_setting('hpwc_menu_settings', 'hpwc_menu_custom_links_visibility', [
            'sanitize_callback' => function($value) {
                return is_array($value) ? $value : [];
            },
            'default' => [],
        ]);
        
        add_settings_section(
            'hpwc_menu_general_section',
            __('Menu Integration Settings', 'hpwc-menu'),
            function() {
                echo '<p>' . esc_html__('Configure the integration between HivePress and WooCommerce menus.', 'hpwc-menu') . '</p>';
            },
            'hpwc-menu-settings'
        );
        
        add_settings_field(
            'hpwc_menu_enable_integration',
            __('Enable Integration', 'hpwc-menu'),
            function() {
                $value = get_option('hpwc_menu_enable_integration', 'yes');
                echo '<input type="checkbox" id="hpwc_menu_enable_integration" name="hpwc_menu_enable_integration" value="yes" ' . checked('yes', $value, false) . '>';
                echo '<label for="hpwc_menu_enable_integration">' . esc_html__('Enable full menu integration between HivePress and WooCommerce.', 'hpwc-menu') . '</label>';
            },
            'hpwc-menu-settings',
            'hpwc_menu_general_section'
        );
        
        add_settings_field(
            'hpwc_menu_enable_custom_links',
            __('Enable Custom Links Only', 'hpwc-menu'),
            function() {
                $value = get_option('hpwc_menu_enable_custom_links', 'no');
                echo '<input type="checkbox" id="hpwc_menu_enable_custom_links" name="hpwc_menu_enable_custom_links" value="yes" ' . checked('yes', $value, false) . '>';
                echo '<label for="hpwc_menu_enable_custom_links">' . esc_html__('Enable custom links independent of menu integration.', 'hpwc-menu') . '</label>';
                echo '<p class="description">' . esc_html__('This allows using custom links without full menu integration.', 'hpwc-menu') . '</p>';
            },
            'hpwc-menu-settings',
            'hpwc_menu_general_section'
        );
        
        add_settings_field(
            'hpwc_menu_excluded_wc_items',
            __('Hide WooCommerce Menu Items', 'hpwc-menu'),
            function() {
                $excluded = (array) get_option('hpwc_menu_excluded_wc_items', []);
                $default_wc_items = [
                    'dashboard' => __('Dashboard', 'woocommerce'),
                    'orders' => __('Orders', 'woocommerce'),
                    'downloads' => __('Downloads', 'woocommerce'),
                    'edit-address' => __('Addresses', 'woocommerce'),
                    'edit-account' => __('Account details', 'woocommerce'),
                ];
                if (function_exists('wc_get_account_menu_items')) {
                    $wc_items = wc_get_account_menu_items();
                    foreach ($wc_items as $key => $label) {
                        if ($key === 'customer-logout' || strpos($key, 'custom-') === 0 || strpos($key, 'hp-') === 0) {
                            continue;
                        }
                        $default_wc_items[$key] = $label;
                    }
                }
                echo '<input type="hidden" name="hpwc_menu_excluded_wc_items" value="">';
                wp_nonce_field('hpwc_menu_settings_save', 'hpwc_menu_settings_nonce');
                if (!empty($default_wc_items)) {
                    echo '<div style="margin-bottom: 10px;">';
                    foreach ($default_wc_items as $key => $label) {
                        $checked = in_array($key, $excluded, true) ? 'checked' : '';
                        echo '<label style="display: block; margin-bottom: 5px;">';
                        echo '<input type="checkbox" name="hpwc_menu_excluded_wc_items[]" value="' . esc_attr($key) . '" ' . $checked . '> ';
                        echo esc_html($label);
                        echo '</label>';
                    }
                    echo '</div>';
                } else {
                    echo '<p>' . esc_html__('No WooCommerce menu items found.', 'hpwc-menu') . '</p>';
                }
                echo '<p class="description">' . esc_html__('Select WooCommerce items to hide from the menu.', 'hpwc-menu') . '</p>';
            },
            'hpwc-menu-settings',
            'hpwc_menu_general_section'
        );
        
        add_settings_field(
            'hpwc_menu_custom_links',
            __('Custom Menu Links', 'hpwc-menu'),
            function() {
                $links = get_option('hpwc_menu_custom_links', []);
                $visibility_settings = get_option('hpwc_menu_custom_links_visibility', []);
                if (empty($links) || !is_array($links)) {
                    $links = [['label' => '', 'type' => 'wp_page', 'page_id' => '', 'url' => '', 'menu_location' => 'both', 'position' => 'top']];
                }
                $roles = wp_roles()->get_names();
                $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
                echo '<div id="hpwc-links-container">';
                foreach ($links as $index => $link) {
                    if (!is_array($link)) {
                        $link = ['label' => '', 'type' => 'wp_page', 'page_id' => '', 'url' => '', 'menu_location' => 'both', 'position' => 'top'];
                    }
                    $current_visibility = $visibility_settings[$index] ?? [];
                    echo '<div class="hpwc-link-row" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">';
                    echo '<div class="hpwc-link-field" style="margin-bottom: 10px;">';
                    echo '<label style="font-weight: bold; display: block; margin-bottom: 5px;">' . esc_html__('Label', 'hpwc-menu') . '</label>';
                    echo '<input type="text" name="hpwc_menu_custom_links[' . $index . '][label]" value="' . esc_attr($link['label'] ?? '') . '" style="width: 300px;">';
                    echo '</div>';
                    echo '<div class="hpwc-link-field" style="margin-bottom: 10px;">';
                    echo '<label style="font-weight: bold; display: block; margin-bottom: 5px;">' . esc_html__('Link Source', 'hpwc-menu') . '</label>';
                    echo '<div style="margin-bottom: 10px;">';
                    echo '<label style="margin-right: 10px;"><input type="radio" name="hpwc_menu_custom_links[' . $index . '][type]" value="wp_page" class="hpwc-link-type" ' . ($link['type'] === 'wp_page' ? 'checked' : '') . '> ' . esc_html__('Select a page:', 'hpwc-menu') . '</label>';
                    echo '<select name="hpwc_menu_custom_links[' . $index . '][page_id]" class="hpwc-wp-page-select" style="width: 300px;" ' . ($link['type'] !== 'wp_page' ? 'disabled' : '') . '>';
                    echo '<option value="">' . esc_html__('-- Select a page --', 'hpwc-menu') . '</option>';
                    foreach ($pages as $page) {
                        $selected = (isset($link['page_id']) && $link['page_id'] == $page->ID) ? 'selected' : '';
                        echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                    echo '<div>';
                    echo '<label style="margin-right: 10px;"><input type="radio" name="hpwc_menu_custom_links[' . $index . '][type]" value="custom" class="hpwc-link-type" ' . ($link['type'] === 'custom' ? 'checked' : '') . '> ' . esc_html__('Custom URL:', 'hpwc-menu') . '</label>';
                    echo '<input type="text" name="hpwc_menu_custom_links[' . $index . '][url]" value="' . esc_attr($link['url'] ?? '') . '" placeholder="' . esc_attr__('https://example.com/page', 'hpwc-menu') . '" style="width: 300px;" class="hpwc-url-input" ' . ($link['type'] !== 'custom' ? 'disabled' : '') . '>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="hpwc-link-field" style="margin-bottom: 10px;">';
                    echo '<label style="font-weight: bold; display: block; margin-bottom: 5px;">' . esc_html__('Menu Location', 'hpwc-menu') . '</label>';
                    echo '<div class="hpwc-menu-location-options">';
                    $menu_location = isset($link['menu_location']) ? $link['menu_location'] : 'both';
                    echo '<label style="margin-right: 15px;"><input type="radio" name="hpwc_menu_custom_links[' . $index . '][menu_location]" value="both" ' . ($menu_location === 'both' ? 'checked' : '') . '> ' . esc_html__('Both menus', 'hpwc-menu') . '</label>';
                    echo '<label style="margin-right: 15px;"><input type="radio" name="hpwc_menu_custom_links[' . $index . '][menu_location]" value="hivepress" ' . ($menu_location === 'hivepress' ? 'checked' : '') . '> ' . esc_html__('HivePress only', 'hpwc-menu') . '</label>';
                    echo '<label><input type="radio" name="hpwc_menu_custom_links[' . $index . '][menu_location]" value="woocommerce" ' . ($menu_location === 'woocommerce' ? 'checked' : '') . '> ' . esc_html__('WooCommerce only', 'hpwc-menu') . '</label>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="hpwc-link-field" style="margin-bottom: 10px;">';
                    echo '<label style="font-weight: bold; display: block; margin-bottom: 5px;">' . esc_html__('Position', 'hpwc-menu') . '</label>';
                    echo '<div class="hpwc-position-options">';
                    $position = isset($link['position']) ? $link['position'] : 'top';
                    echo '<label style="margin-right: 15px;"><input type="radio" name="hpwc_menu_custom_links[' . $index . '][position]" value="top" ' . ($position === 'top' ? 'checked' : '') . '> ' . esc_html__('Top of menu', 'hpwc-menu') . '</label>';
                    echo '<label><input type="radio" name="hpwc_menu_custom_links[' . $index . '][position]" value="bottom" ' . ($position === 'bottom' ? 'checked' : '') . '> ' . esc_html__('Bottom of menu (above logout)', 'hpwc-menu') . '</label>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="hpwc-link-field" style="margin-bottom: 10px;">';
                    echo '<label style="font-weight: bold; display: block; margin-bottom: 5px;">' . esc_html__('Visibility', 'hpwc-menu') . '</label>';
                    echo '<div class="hpwc-visibility-options">';
                    echo '<label style="margin-right: 15px;"><input type="radio" name="hpwc_menu_custom_links_visibility[' . $index . '][type]" value="all" ' . (empty($current_visibility['type']) || $current_visibility['type'] === 'all' ? 'checked' : '') . '> ' . esc_html__('All users', 'hpwc-menu') . '</label>';
                    echo '<label style="margin-right: 15px;"><input type="radio" name="hpwc_menu_custom_links_visibility[' . $index . '][type]" value="roles" class="hpwc-visibility-roles" ' . (!empty($current_visibility['type']) && $current_visibility['type'] === 'roles' ? 'checked' : '') . '> ' . esc_html__('Specific roles', 'hpwc-menu') . '</label>';
                    echo '<label><input type="radio" name="hpwc_menu_custom_links_visibility[' . $index . '][type]" value="subscription" class="hpwc-visibility-subscription" ' . (!empty($current_visibility['type']) && $current_visibility['type'] === 'subscription' ? 'checked' : '') . '> ' . esc_html__('Customer history', 'hpwc-menu') . '</label>';
                    echo '</div>';
                    echo '<div class="hpwc-roles-selector" style="margin-top: 10px; padding-left: 20px; ' . (!empty($current_visibility['type']) && $current_visibility['type'] === 'roles' ? '' : 'display: none;') . '">';
                    foreach ($roles as $role_id => $role_name) {
                        $role_checked = in_array($role_id, $current_visibility['roles'] ?? [], true) ? 'checked' : '';
                        echo '<label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">';
                        echo '<input type="checkbox" name="hpwc_menu_custom_links_visibility[' . $index . '][roles][]" value="' . esc_attr($role_id) . '" ' . $role_checked . '> ';
                        echo esc_html($role_name);
                        echo '</label>';
                    }
                    echo '</div>';
                    echo '</div>';
                    echo '<button type="button" class="button hpwc-remove-link">' . esc_html__('Remove', 'hpwc-menu') . '</button>';
                    echo '</div>';
                }
                echo '</div>';
                echo '<button type="button" class="button" id="hpwc-add-link">' . esc_html__('Add Link', 'hpwc-menu') . '</button>';
            },
            'hpwc-menu-settings',
            'hpwc_menu_general_section'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you are not allowed to access this page.', 'hpwc-menu'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('hpwc_menu_settings');
                do_settings_sections('hpwc-menu-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook != 'settings_page_hpwc-menu-settings') {
            return;
        }
        wp_enqueue_style(
            'hpwc-menu-admin-styles',
            HPWC_MENU_PLUGIN_URL . 'assets/css/admin-styles.css',
            [],
            HPWC_MENU_VERSION
        );
        wp_enqueue_script(
            'hpwc-menu-admin-scripts',
            HPWC_MENU_PLUGIN_URL . 'assets/js/admin-scripts.js',
            ['jquery'],
            HPWC_MENU_VERSION,
            true
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!is_user_logged_in() || !is_account_page()) {
            return;
        }
        wp_enqueue_style(
            'hpwc-menu-frontend-styles',
            HPWC_MENU_PLUGIN_URL . 'assets/css/frontend-styles.css',
            [],
            HPWC_MENU_VERSION
        );
    }

    /**
     * Initialize HivePress URLs
     */
    public function initialize_hp_urls() {
        $this->hp_urls = [];
    }

    /**
     * Register endpoints for custom links in WooCommerce
     */
    public function register_endpoints() {
        if (get_option('hpwc_menu_enable_integration', 'yes') === 'yes' || 
            get_option('hpwc_menu_enable_custom_links', 'yes') === 'yes') {
            if (get_option('hpwc_menu_enable_integration', 'yes') === 'yes') {
                $hp_items = $this->get_hivepress_menu_items();
                if (!empty($hp_items)) {
                    foreach ($hp_items as $key => $item) {
                        if ($key === 'orders' || $key === 'user_logout') {
                            continue;
                        }
                        $endpoint = 'hp-' . $key;
                        add_rewrite_endpoint($endpoint, EP_ROOT | EP_PAGES);
                    }
                }
            }
            $custom_links = get_option('hpwc_menu_custom_links', []);
            foreach ($custom_links as $index => $link) {
                if (!empty($link['label']) && $link['type']) {
                    if ($link['menu_location'] === 'woocommerce' || $link['menu_location'] === 'both') {
                        $endpoint = 'custom-' . $index;
                        add_rewrite_endpoint($endpoint, EP_ROOT | EP_PAGES);
                    }
                }
            }
            if (get_option('hpwc_menu_flush_rules', 'yes') === 'yes') {
                flush_rewrite_rules();
                update_option('hpwc_menu_flush_rules', 'no');
            }
        }
    }

    /**
     * Get HivePress menu items for cross-menu integration
     */
    private function get_hivepress_menu_items() {
        if (!class_exists('\HivePress\Core')) {
            return [];
        }
        static $in_process = false;
        if ($in_process) {
            return [];
        }
        $in_process = true;
        remove_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
        if (class_exists('\HivePress\Menus\Menu_User_Account')) {
            $menu_instance = new \HivePress\Menus\Menu_User_Account();
            $menu = $menu_instance->get_menu();
        } else {
            $menu = apply_filters('hivepress/v1/menus/user_account', []);
        }
        add_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
        $in_process = false;
        if (empty($menu['items']) || !is_array($menu['items'])) {
            return [];
        }
        return $menu['items'];
    }

    /**
     * Add custom links to WooCommerce menu
     */
    public function add_custom_links_to_wc_menu($menu_items) {
        if ((get_option('hpwc_menu_enable_integration', 'yes') !== 'yes' && 
             get_option('hpwc_menu_enable_custom_links', 'yes') !== 'yes') || 
            !is_user_logged_in()) {
            return $menu_items;
        }
        static $in_process = false;
        if ($in_process) {
            return $menu_items;
        }
        $in_process = true;
        foreach ($menu_items as $key => $label) {
            if (strpos($key, 'subscriptions') !== false || strpos($label, 'Subscription #') !== false) {
                unset($menu_items[$key]);
            }
        }
        $custom_links = get_option('hpwc_menu_custom_links', []);
        $user_id = get_current_user_id();
        $top_links = [];
        $bottom_links = [];
        foreach ($custom_links as $index => $link) {
            if (!empty($link['label']) && $link['type'] && 
                ($link['menu_location'] === 'woocommerce' || $link['menu_location'] === 'both') && 
                $this->is_link_visible_to_user($index, $user_id)) {
                $unique_key = 'custom-' . $index;
                if (isset($link['position']) && $link['position'] === 'bottom') {
                    $bottom_links[$unique_key] = $link['label'];
                } else {
                    $top_links[$unique_key] = $link['label'];
                }
            }
        }
        $hp_menu = [];
        if (get_option('hpwc_menu_enable_integration', 'yes') === 'yes') {
            remove_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
            $hp_menu = apply_filters('hivepress/v1/menus/user_account', []);
            add_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
            $hp_url_map = [
                'listings_edit' => 'account/listings',
                'user_edit_settings' => 'account/settings',
                'listings_favorite' => 'account/favorites',
                'user_listing_packages_view' => 'account/listing-packages',
                'search_alerts_view' => 'account/searches',
                'messages_view' => 'account/messages',
                'reviews_view' => 'account/reviews',
                'orders_view' => 'my-account/orders',
            ];
            if (!empty($hp_menu['items']) && is_array($hp_menu['items'])) {
                foreach ($hp_menu['items'] as $key => $item) {
                    if (strpos($key, 'custom-') === 0 || strpos($key, 'wc-') === 0) {
                        continue;
                    }
                    $unique_key = 'hp-' . $key;
                    if ($key === 'user_logout') {
                        if (isset($menu_items['customer-logout'])) {
                            $menu_items['customer-logout'] = __('Sign Out', 'hpwc-menu');
                        }
                        continue;
                    }
                    $label = $this->get_friendly_hp_item_name($key);
                    if (isset($hp_url_map[$key])) {
                        $this->hp_urls[$unique_key] = home_url($hp_url_map[$key]);
                    } else if (isset($item['url'])) {
                        $this->hp_urls[$unique_key] = $item['url'];
                    }
                    if (isset($item['_order']) && $item['_order'] > 50) {
                        $bottom_links[$unique_key] = $label;
                    } else {
                        $top_links[$unique_key] = $label;
                    }
                }
            }
            if (!isset($top_links['hp-user_edit_settings']) && !isset($bottom_links['hp-user_edit_settings'])) {
                if (function_exists('hivepress') && method_exists(hivepress()->router, 'get_url')) {
                    $settings_url = hivepress()->router->get_url('user_edit_settings_page');
                    if ($settings_url) {
                        $top_links['hp-user_edit_settings'] = __('Settings', 'hpwc-menu');
                        $this->hp_urls['hp-user_edit_settings'] = $settings_url;
                    }
                } else {
                    $top_links['hp-user_edit_settings'] = __('Settings', 'hpwc-menu');
                    $this->hp_urls['hp-user_edit_settings'] = home_url('account/settings');
                }
            }
        }
        $logout_key = 'customer-logout';
        $new_menu_items = [];
        foreach ($top_links as $key => $label) {
            $new_menu_items[$key] = $label;
        }
        foreach ($menu_items as $key => $label) {
            if ($key !== $logout_key) {
                $new_menu_items[$key] = $label;
            }
        }
        foreach ($bottom_links as $key => $label) {
            $new_menu_items[$key] = $label;
        }
        if (isset($menu_items[$logout_key])) {
            $new_menu_items[$logout_key] = $menu_items[$logout_key];
        }
        $in_process = false;
        return $new_menu_items;
    }

    /**
     * Add custom links to HivePress menu
     */
    public function add_custom_links_to_hp_menu($menu) {
        if ((get_option('hpwc_menu_enable_integration', 'yes') !== 'yes' && 
             get_option('hpwc_menu_enable_custom_links', 'yes') !== 'yes') || 
            !is_user_logged_in()) {
            return $menu;
        }
        static $in_process = false;
        if ($in_process) {
            return $menu;
        }
        $in_process = true;
        $user_id = get_current_user_id();
        $custom_links = get_option('hpwc_menu_custom_links', []);
        if (!isset($menu['items']) || !is_array($menu['items'])) {
            $menu['items'] = [];
        }
        $max_order = 0;
        $min_order = PHP_INT_MAX;
        foreach ($menu['items'] as $key => $item) {
            if (isset($item['_order'])) {
                $max_order = max($max_order, $item['_order']);
                $min_order = min($min_order, $item['_order']);
            }
        }
        if ($max_order === 0) {
            $max_order = 100;
        }
        if ($min_order === PHP_INT_MAX) {
            $min_order = 10;
        }
        foreach ($custom_links as $index => $link) {
            if (!empty($link['label']) && $link['type'] && 
                ($link['menu_location'] === 'hivepress' || $link['menu_location'] === 'both') && 
                $this->is_link_visible_to_user($index, $user_id)) {
                $unique_key = 'custom-' . $index;
                $url = '';
                if ($link['type'] === 'wp_page' && !empty($link['page_id'])) {
                    $url = get_permalink($link['page_id']);
                } elseif ($link['type'] === 'custom' && !empty($link['url'])) {
                    $url = $link['url'];
                }
                if (!empty($url)) {
                    $order_value = ($link['position'] === 'bottom') ? $max_order - 5 : $min_order - 5;
                    $menu['items'][$unique_key] = [
                        'label' => $link['label'],
                        'url' => $url,
                        '_order' => $order_value
                    ];
                }
            }
        }
        if (get_option('hpwc_menu_enable_integration', 'yes') === 'yes') {
            if (function_exists('wc_get_account_menu_items') && $this->user_has_checkout_history($user_id)) {
                remove_filter('woocommerce_account_menu_items', [$this, 'add_custom_links_to_wc_menu'], 20);
                $wc_items = wc_get_account_menu_items();
                add_filter('woocommerce_account_menu_items', [$this, 'add_custom_links_to_wc_menu'], 20);
                if (isset($wc_items['customer-logout'])) {
                    unset($wc_items['customer-logout']);
                }
                $excluded_items = (array) get_option('hpwc_menu_excluded_wc_items', []);
                foreach ($wc_items as $key => $label) {
                    if (in_array($key, $excluded_items, true) || $key === 'orders' || strpos($key, 'custom-') === 0 || strpos($key, 'hp-') === 0) {
                        continue;
                    }
                    $unique_key = 'wc-' . $key;
                    $menu['items'][$unique_key] = [
                        'label' => $label,
                        'url' => wc_get_account_endpoint_url($key),
                        '_order' => ($key === 'dashboard') ? $min_order - 10 : $max_order - 10
                    ];
                }
            }
        }
        $in_process = false;
        return $menu;
    }

    /**
     * Get user-friendly name for HivePress menu items
     */
    private function get_friendly_hp_item_name($key) {
        $mappings = [
            'user_edit_settings' => __('Settings', 'hpwc-menu'),
            'listings_edit' => __('Listings', 'hpwc-menu'),
            'listings_favorite' => __('Favorites', 'hpwc-menu'),
            'user_listing_packages_view' => __('Packages', 'hpwc-menu'),
            'search_alerts_view' => __('Searches', 'hpwc-menu'),
            'messages_view' => __('Messages', 'hpwc-menu'),
            'reviews_view' => __('Reviews', 'hpwc-menu'),
            'orders_view' => __('Orders', 'hpwc-menu'),
            'user_logout' => __('Sign Out', 'hpwc-menu'),
        ];
        if (isset($mappings[$key])) {
            return $mappings[$key];
        }
        $label = str_replace('_', ' ', $key);
        $label = ucwords($label);
        return $label;
    }

    /**
     * Check if user has checkout history or order history
     */
    private function user_has_checkout_history($user_id) {
        if (user_can($user_id, 'administrator')) {
            return true;
        }
        if (function_exists('wc_get_orders')) {
            $args = [
                'customer_id' => $user_id,
                'limit' => 1,
                'return' => 'ids',
            ];
            $orders = wc_get_orders($args);
            if (!empty($orders)) {
                return true;
            }
        }
        if (function_exists('wcs_get_users_subscriptions')) {
            $subscriptions = wcs_get_users_subscriptions($user_id);
            if (!empty($subscriptions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a custom link is visible to the current user
     */
    private function is_link_visible_to_user($link_index, $user_id) {
        $visibility_settings = get_option('hpwc_menu_custom_links_visibility', []);
        if (empty($visibility_settings) || !isset($visibility_settings[$link_index]) || 
            empty($visibility_settings[$link_index]['type']) || 
            $visibility_settings[$link_index]['type'] === 'all') {
            return true;
        }
        if (user_can($user_id, 'administrator')) {
            return true;
        }
        if ($visibility_settings[$link_index]['type'] === 'roles') {
            if (empty($visibility_settings[$link_index]['roles'])) {
                return false;
            }
            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }
            $user_roles = $user->roles;
            $required_roles = $visibility_settings[$link_index]['roles'];
            foreach ($required_roles as $role) {
                if (in_array($role, $user_roles, true)) {
                    return true;
                }
            }
            return false;
        }
        if ($visibility_settings[$link_index]['type'] === 'subscription') {
            return $this->user_has_checkout_history($user_id);
        }
        return true;
    }

    /**
     * Modify URLs for custom and HivePress menu items in WooCommerce
     */
    public function modify_menu_item_urls($url, $endpoint) {
        if (strpos($endpoint, 'custom-') === 0) {
            $custom_links = get_option('hpwc_menu_custom_links', []);
            $index = substr($endpoint, 7);
            if (isset($custom_links[$index])) {
                $link = $custom_links[$index];
                if ($link['type'] === 'wp_page' && !empty($link['page_id'])) {
                    return get_permalink($link['page_id']);
                } elseif ($link['type'] === 'custom' && !empty($link['url'])) {
                    return $link['url'];
                }
            }
        }
        if (strpos($endpoint, 'hp-') === 0) {
            $key = substr($endpoint, 3);
            $hp_url_map = [
                'listings_edit' => 'account/listings',
                'user_edit_settings' => 'account/settings',
                'listings_favorite' => 'account/favorites',
                'user_listing_packages_view' => 'account/listing-packages',
                'search_alerts_view' => 'account/searches',
                'messages_view' => 'account/messages',
                'reviews_view' => 'account/reviews',
                'orders_view' => 'my-account/orders',
            ];
            if (isset($hp_url_map[$key])) {
                return home_url($hp_url_map[$key]);
            }
            if (function_exists('hivepress') && method_exists(hivepress()->router, 'get_url')) {
                $page_map = [
                    'listings_edit' => 'listings_edit_page',
                    'user_edit_settings' => 'user_edit_settings_page',
                    'listings_favorite' => 'listings_favorite_page',
                    'user_listing_packages_view' => 'user_listing_packages_view_page',
                    'search_alerts_view' => 'search_alerts_view_page',
                    'messages_view' => 'messages_view_page',
                    'reviews_view' => 'reviews_view_page',
                    'orders_view' => 'orders_view_page',
                ];
                if (isset($page_map[$key])) {
                    $hp_url = hivepress()->router->get_url($page_map[$key]);
                    if ($hp_url) {
                        return $hp_url;
                    }
                }
            }
            if (!empty($this->hp_urls[$endpoint])) {
                return $this->hp_urls[$endpoint];
            }
            remove_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
            $hp_menu = apply_filters('hivepress/v1/menus/user_account', []);
            add_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
            if (isset($hp_menu['items'][$key]) && isset($hp_menu['items'][$key]['url'])) {
                $this->hp_urls[$endpoint] = $hp_menu['items'][$key]['url'];
                return $hp_menu['items'][$key]['url'];
            }
        }
        return $url;
    }

    /**
     * Fix endpoint URLs for correct redirection
     */
    public function fix_endpoint_urls() {
        global $wp;
        $current_endpoint = '';
        if (function_exists('is_account_page') && is_account_page()) {
            foreach ($wp->query_vars as $key => $value) {
                if (strpos($key, 'hp-') === 0) {
                    $current_endpoint = $key;
                    $hp_key = substr($current_endpoint, 3);
                    $hp_url_map = [
                        'listings_edit' => 'account/listings',
                        'user_edit_settings' => 'account/settings',
                        'listings_favorite' => 'account/favorites',
                        'user_listing_packages_view' => 'account/listing-packages',
                        'search_alerts_view' => 'account/searches',
                        'messages_view' => 'account/messages',
                        'reviews_view' => 'account/reviews',
                        'orders_view' => 'my-account/orders',
                    ];
                    if (isset($hp_url_map[$hp_key])) {
                        wp_redirect(home_url($hp_url_map[$hp_key]));
                        exit;
                    }
                    if (function_exists('hivepress') && method_exists(hivepress()->router, 'get_url')) {
                        $page_map = [
                            'listings_edit' => 'listings_edit_page',
                            'user_edit_settings' => 'user_edit_settings_page',
                            'listings_favorite' => 'listings_favorite_page',
                            'user_listing_packages_view' => 'user_listing_packages_view_page',
                            'search_alerts_view' => 'search_alerts_view_page',
                            'messages_view' => 'messages_view_page',
                            'reviews_view' => 'reviews_view_page',
                            'orders_view' => 'orders_view_page',
                        ];
                        if (isset($page_map[$hp_key])) {
                            $url = hivepress()->router->get_url($page_map[$hp_key]);
                            if ($url) {
                                wp_redirect($url);
                                exit;
                            }
                        }
                    }
                    remove_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
                    $hp_menu = apply_filters('hivepress/v1/menus/user_account', []);
                    add_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
                    if (isset($hp_menu['items'][$hp_key]) && isset($hp_menu['items'][$hp_key]['url'])) {
                        wp_redirect($hp_menu['items'][$hp_key]['url']);
                        exit;
                    }
                    break;
                } else if (strpos($key, 'custom-') === 0) {
                    $current_endpoint = $key;
                    break;
                }
            }
            if (strpos($current_endpoint, 'custom-') === 0) {
                $correct_url = $this->modify_menu_item_urls('', $current_endpoint);
                if (!empty($correct_url) && $correct_url !== '') {
                    wp_redirect($correct_url);
                    exit;
                }
            }
        }
    }

    /**
     * Hide excluded WooCommerce menu items using CSS
     */
    public function hide_excluded_items() {
        if (get_option('hpwc_menu_enable_integration', 'yes') === 'yes' || 
            get_option('hpwc_menu_enable_custom_links', 'yes') === 'yes') {
            $excluded_items = (array) get_option('hpwc_menu_excluded_wc_items', []);
            if (!empty($excluded_items)) {
                echo '<style type="text/css">';
                foreach ($excluded_items as $item) {
                    echo '.woocommerce-account .woocommerce-MyAccount-navigation-link--' . esc_attr($item) . ' { display: none !important; }';
                }
                echo '</style>';
            }
        }
    }

    /**
     * Fix menu item links in navigation
     */
    public function fix_menu_item_links($items, $args) {
        if (!function_exists('is_account_page') || !is_account_page()) {
            return $items;
        }
        if (!isset($args->menu_class) || strpos($args->menu_class, 'woocommerce-MyAccount-navigation') === false) {
            return $items;
        }
        $items = preg_replace_callback(
            '/<li[^>]*class="([^"]*woocommerce-MyAccount-navigation-link--(hp|custom)-[^"]*)"[^>]*>.*?href="([^"]*)".*?<\/li>/s',
            function($matches) {
                $classes = $matches[1];
                $prefix = $matches[2];
                $current_url = $matches[3];
                if (preg_match('/woocommerce-MyAccount-navigation-link--(' . $prefix . '-[^\\s"]+)/', $classes, $endpoint_match)) {
                    $endpoint = $endpoint_match[1];
                    $new_url = $this->modify_menu_item_urls('', $endpoint);
                    if (!empty($new_url)) {
                        return str_replace('href="' . $current_url . '"', 'href="' . esc_url($new_url) . '"', $matches[0]);
                    }
                }
                return $matches[0];
            },
            $items
        );
        return $items;
    }

    /**
     * Cache HivePress URLs for better performance and direct access
     */
    public function cache_hivepress_urls() {
        if (!is_user_logged_in() || (get_option('hpwc_menu_enable_integration', 'yes') !== 'yes' && 
                                    get_option('hpwc_menu_enable_custom_links', 'yes') !== 'yes')) {
            return;
        }
        $hp_url_map = [
            'listings_edit' => 'account/listings',
            'user_edit_settings' => 'account/settings',
            'listings_favorite' => 'account/favorites',
            'user_listing_packages_view' => 'account/listing-packages',
            'search_alerts_view' => 'account/searches',
            'messages_view' => 'account/messages',
            'reviews_view' => 'account/reviews',
            'orders_view' => 'my-account/orders',
        ];
        foreach ($hp_url_map as $key => $path) {
            $this->hp_urls['hp-' . $key] = home_url($path);
        }
        if (function_exists('hivepress') && method_exists(hivepress()->router, 'get_url')) {
            $page_map = [
                'listings_edit' => 'listings_edit_page',
                'user_edit_settings' => 'user_edit_settings_page',
                'listings_favorite' => 'listings_favorite_page',
                'user_listing_packages_view' => 'user_listing_packages_view_page',
                'search_alerts_view' => 'search_alerts_view_page',
                'messages_view' => 'messages_view_page',
                'reviews_view' => 'reviews_view_page',
                'orders_view' => 'orders_view_page',
            ];
            foreach ($page_map as $key => $page_id) {
                $url = hivepress()->router->get_url($page_id);
                if ($url) {
                    $this->hp_urls['hp-' . $key] = $url;
                }
            }
        }
        remove_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
        $hp_menu = apply_filters('hivepress/v1/menus/user_account', []);
        add_filter('hivepress/v1/menus/user_account', [$this, 'add_custom_links_to_hp_menu'], 1000);
        if (!empty($hp_menu['items']) && is_array($hp_menu['items'])) {
            foreach ($hp_menu['items'] as $key => $item) {
                if (isset($item['url']) && !isset($this->hp_urls['hp-' . $key])) {
                    $this->hp_urls['hp-' . $key] = $item['url'];
                }
            }
        }
    }

    /**
     * Add direct link support for HivePress items
     */
    public function add_direct_hp_link_support() {
        if (!is_admin() && function_exists('is_account_page') && is_account_page()) {
            add_action('wp_footer', function() {
                ?>
                <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    var hpLinks = document.querySelectorAll('.woocommerce-MyAccount-navigation-link[class*="hp-"] a');
                    var hpUrlMap = {
                        'hp-listings_edit': '<?php echo esc_url(home_url('account/listings')); ?>',
                        'hp-user_edit_settings': '<?php echo esc_url(home_url('account/settings')); ?>',
                        'hp-listings_favorite': '<?php echo esc_url(home_url('account/favorites')); ?>',
                        'hp-user_listing_packages_view': '<?php echo esc_url(home_url('account/listing-packages')); ?>',
                        'hp-search_alerts_view': '<?php echo esc_url(home_url('account/searches')); ?>',
                        'hp-messages_view': '<?php echo esc_url(home_url('account/messages')); ?>',
                        'hp-reviews_view': '<?php echo esc_url(home_url('account/reviews')); ?>',
                        'hp-orders_view': '<?php echo esc_url(home_url('my-account/orders')); ?>'
                    };
                    hpLinks.forEach(function(link) {
                        var classes = link.parentNode.className.split(' ');
                        var hpClass = classes.find(function(cls) { 
                            return cls.startsWith('woocommerce-MyAccount-navigation-link--hp-'); 
                        });
                        if (hpClass) {
                            var endpoint = hpClass.replace('woocommerce-MyAccount-navigation-link--', '');
                            if (hpUrlMap[endpoint]) {
                                link.href = hpUrlMap[endpoint];
                                return;
                            }
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success && response.data.url) {
                                            link.href = response.data.url;
                                        }
                                    } catch(e) {
                                        console.error('Error parsing AJAX response:', e);
                                    }
                                }
                            };
                            xhr.send('action=hpwc_menu_get_hp_endpoint_url&endpoint=' + endpoint);
                        }
                    });
                });
                </script>
                <?php
            }, 999);
        }
    }

    /**
     * AJAX handler to get HivePress endpoint URLs
     */
    public function ajax_get_hp_endpoint_url() {
        if (!isset($_POST['endpoint'])) {
            wp_send_json_error();
        }
        $endpoint = sanitize_text_field($_POST['endpoint']);
        $url = $this->modify_menu_item_urls('', $endpoint);
        if (!empty($url)) {
            wp_send_json_success(['url' => $url]);
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Sanitize custom links
     */
    public function sanitize_custom_links($links) {
        $sanitized = [];
        if (!is_array($links)) {
            return $sanitized;
        }
        foreach ($links as $index => $link) {
            if (empty($link['label']) || !isset($link['type']) || !in_array($link['type'], ['wp_page', 'custom'], true)) {
                continue;
            }
            $sanitized[$index] = [
                'label' => sanitize_text_field($link['label']),
                'type' => $link['type'],
                'menu_location' => in_array($link['menu_location'] ?? '', ['both', 'hivepress', 'woocommerce'], true) 
                    ? $link['menu_location'] 
                    : 'both',
                'position' => in_array($link['position'] ?? '', ['top', 'bottom'], true)
                    ? $link['position']
                    : 'top',
            ];
            if ($link['type'] === 'wp_page' && !empty($link['page_id'])) {
                $sanitized[$index]['page_id'] = absint($link['page_id']);
            } elseif ($link['type'] === 'custom' && !empty($link['url'])) {
                $sanitized[$index]['url'] = esc_url_raw($link['url'], ['http', 'https']);
            }
        }
        return $sanitized;
    }
}