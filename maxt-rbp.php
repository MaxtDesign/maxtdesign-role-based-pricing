<?php
/**
 * Plugin Name: MaxT Role Based Pricing
 * Plugin URI: https://github.com/[username]/maxt-rbp
 * Description: A lightweight WooCommerce plugin that provides role-based pricing with percentage or fixed amount discounts.
 * Version: 1.0.0
 * Author: MaxT
 * Author URI: https://github.com/[username]
 * Text Domain: maxt-rbp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MaxT_RBP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAXT_RBP_VERSION', '1.0.0');
define('MAXT_RBP_PLUGIN_FILE', __FILE__);
define('MAXT_RBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAXT_RBP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAXT_RBP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-core.php';
require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-admin.php';
require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * Main MaxT Role Based Pricing Class
 */
class MaxT_Role_Based_Pricing {

    /**
     * Single instance of the class
     *
     * @var MaxT_Role_Based_Pricing
     */
    private static $instance = null;

    /**
     * Core instance
     *
     * @var MaxT_RBP_Core
     */
    private $core;

    /**
     * Admin instance
     *
     * @var MaxT_RBP_Admin
     */
    private $admin;

    /**
     * Frontend instance
     *
     * @var MaxT_RBP_Frontend
     */
    private $frontend;

    /**
     * Get single instance
     *
     * @return MaxT_Role_Based_Pricing
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('MaxT_Role_Based_Pricing', 'uninstall'));
        
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', function() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize core functionality
        $this->core = new MaxT_RBP_Core();

        // Initialize admin interface
        $this->admin = new MaxT_RBP_Admin($this->core);
        $this->admin->init();

        // Initialize frontend
        $this->frontend = new MaxT_RBP_Frontend($this->core);
        $this->frontend->init();

        // Load text domain
        load_plugin_textdomain('maxt-rbp', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize plugin components
        $this->init_hooks();
        
        // Show activation notice
        add_action('admin_notices', array($this, 'activation_notice'));
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks are handled by admin interface class

        // WooCommerce hooks for price calculation - ENABLED for cart functionality
        // Frontend display filters handle product page display to prevent double discount
        add_filter('woocommerce_product_get_price', array($this, 'get_role_based_price'), 10, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'get_role_based_regular_price'), 10, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'get_role_based_sale_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'get_role_based_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'get_role_based_regular_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_sale_price', array($this, 'get_role_based_sale_price'), 10, 2);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Initialize core functionality
        $core = new MaxT_RBP_Core();
        
        // Create custom table for pricing rules
        $core->create_table();
        
        // Set default options
        $this->set_default_options();
        
        // Set activation notice transient
        set_transient('maxt_rbp_activation_notice', true, 60);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear transients
        $this->clear_pricing_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
    }



    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'maxt_rbp_version' => MAXT_RBP_VERSION,
            'maxt_rbp_cache_duration' => 3600, // 1 hour
            'maxt_rbp_display_original_price' => true,
            'maxt_rbp_cache_method' => 'auto', // Will be auto-detected
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }



    /**
     * Get role-based price
     */
    public function get_role_based_price($price, $product) {
        if (!$this->core) {
            return $price;
        }
        
        // Only modify price if user is logged in and has a role
        if (!is_user_logged_in()) {
            return $price;
        }
        
        $user_role = $this->get_current_user_role();
        if (!$user_role) {
            return $price;
        }
        
        // Use the current price as the base for calculation
        if (!$price || $price <= 0) {
            return $price;
        }
        
        // Calculate role-based price
        $role_price = $this->core->calculate_price($price, $product);
        
        // If we have a discount, return the discounted price
        if ($role_price && $role_price < $price) {
            return $role_price;
        }
        
        return $price;
    }

    /**
     * Get role-based regular price - keep original price for strikethrough display
     */
    public function get_role_based_regular_price($price, $product) {
        // Always return the original regular price to maintain strikethrough display
        return $price;
    }

    /**
     * Get role-based sale price - set discounted price as sale price
     */
    public function get_role_based_sale_price($price, $product) {
        if (!$this->core) {
            return $price;
        }
        
        // Only modify sale price if user is logged in and has a role
        if (!is_user_logged_in()) {
            return $price;
        }
        
        $user_role = $this->get_current_user_role();
        if (!$user_role) {
            return $price;
        }
        
        // Get the regular price using proper getter method
        $regular_price = $product->get_regular_price();
        if (!$regular_price || $regular_price <= 0) {
            return $price;
        }
        
        // Calculate role-based price
        $role_price = $this->core->calculate_price($regular_price, $product);
        
        // If we have a discount, set it as the sale price
        if ($role_price && $role_price < $regular_price) {
            return $role_price;
        }
        
        return $price;
    }


    /**
     * Get current user role
     */
    private function get_current_user_role() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return $user->roles[0] ?? false;
    }


    /**
     * Clear pricing cache
     */
    private function clear_pricing_cache() {
        if ($this->core) {
            $this->core->clear_all_cache();
        }
    }

    /**
     * Get core instance
     *
     * @return MaxT_RBP_Core
     */
    public function get_core() {
        return $this->core;
    }

    /**
     * Get admin instance
     *
     * @return MaxT_RBP_Admin
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Get frontend instance
     *
     * @return MaxT_RBP_Frontend
     */
    public function get_frontend() {
        return $this->frontend;
    }

    /**
     * Activation notice
     */
    public function activation_notice() {
        if (get_transient('maxt_rbp_activation_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . esc_html__('MaxT Role Based Pricing', 'maxt-rbp') . '</strong> ' . 
                 esc_html__('has been activated!', 'maxt-rbp') . '</p>';
            echo '<p>' . sprintf(
                esc_html__('Go to %s to create custom roles and set up pricing rules.', 'maxt-rbp'),
                '<a href="' . admin_url('admin.php?page=maxt-role-pricing') . '">' . esc_html__('WooCommerce > MaxT Role Pricing', 'maxt-rbp') . '</a>'
            ) . '</p>';
            echo '</div>';
            delete_transient('maxt_rbp_activation_notice');
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . esc_html__('MaxT Role Based Pricing', 'maxt-rbp') . '</strong> ' . 
             esc_html__('requires WooCommerce to be installed and active.', 'maxt-rbp') . '</p></div>';
    }

    /**
     * Plugin uninstall - complete cleanup
     */
    public static function uninstall() {
        // Check if user has permission to delete plugins
        if (!current_user_can('delete_plugins')) {
            return;
        }

        // Check if we're in the right context
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        // Include required classes
        require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-core.php';
        require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-admin.php';
        require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-frontend.php';

        // Initialize core and cleanup
        $core = new MaxT_RBP_Core();
        
        // Drop all plugin database tables
        $core->drop_table();
        
        // Remove only custom roles created by this plugin (not WordPress built-in roles)
        $core->remove_all_custom_roles();
        
        // Clear all plugin cache
        $core->clear_all_cache();

        // Delete all plugin options
        $plugin_options = array(
            'maxt_rbp_version',
            'maxt_rbp_cache_duration',
            'maxt_rbp_display_original_price',
            'maxt_rbp_cache_method',
            'maxt_rbp_last_cache_clear',
            'maxt_rbp_cache_logs',
        );

        foreach ($plugin_options as $option) {
            delete_option($option);
        }

        // Clear all transients and cache entries
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_maxt_rbp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_maxt_rbp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'maxt_rbp_%'");

        // Clear any cached data
        wp_cache_flush();

        // Remove any debug logs related to this plugin
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%maxt_rbp_debug%'");
    }
}

// Initialize the plugin
MaxT_Role_Based_Pricing::get_instance();
