<?php
/**
 * Plugin Name: WooCommerce Role-Based Pricing
 * Plugin URI: https://wordpress.org/plugins/woocommerce-role-based-pricing
 * Description: Professional role-based pricing for WooCommerce. Set different prices for different user roles with percentage or fixed discounts.
 * Version: 1.0.0
 * Author: MaxtDesign
 * Author URI: https://maxtdesign.com
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

// Define performance monitoring constant if not already defined
if (!defined('MAXT_RBP_PERFORMANCE_MONITORING')) {
    define('MAXT_RBP_PERFORMANCE_MONITORING', false);
}

// Include required files
require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-core.php';
require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-admin.php';
require_once MAXT_RBP_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * Main WooCommerce Role-Based Pricing Class
 */
class MaxT_Role_Based_Pricing {

    /**
     * Single instance of the class
     *
     * @var MaxT_Role_Based_Pricing
     */
    private static $instance = null;

    /**
     * In-memory storage for original prices during request lifecycle
     * PERFORMANCE FIX: Replaces database meta storage to eliminate DB writes
     *
     * @var array
     */
    private static $original_prices = array();

    /**
     * Flag to track if user has discounts applied during request
     * PERFORMANCE FIX: Enables early return in display methods
     *
     * @var bool
     */
    private static $user_has_discounts = false;

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
        
        // Set up automatic performance data cleanup to prevent database bloat
        add_action('wp_loaded', array($this, 'schedule_performance_cleanup'));
        
        // Show activation notice
        add_action('admin_notices', array($this, 'activation_notice'));
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks are handled by admin interface class

        // Smart hook loading - only register hooks when needed
        $this->init_smart_hooks();
    }

    /**
     * Initialize smart hook loading with conditional registration
     * ARCHITECTURAL FIX: Single calculation path with unified display logic
     */
    private function init_smart_hooks() {
        try {
            // Single calculation path - only modify actual prices, not regular prices
            // Use priority 50 to run after most plugins but before final output
            $hook_priority = 50;
            
            // Price modification hooks - these handle the actual price calculation
            add_filter('woocommerce_product_get_price', array($this, 'get_role_based_price'), $hook_priority, 2);
            add_filter('woocommerce_product_variation_get_price', array($this, 'get_role_based_price'), $hook_priority, 2);
            
            // Regular price hooks - return original price unchanged for display purposes
            add_filter('woocommerce_product_get_regular_price', array($this, 'get_role_based_regular_price'), $hook_priority, 2);
            add_filter('woocommerce_product_variation_get_regular_price', array($this, 'get_role_based_regular_price'), $hook_priority, 2);
            
            // Unified display logic - single filter for all price HTML formatting
            add_filter('woocommerce_get_price_html', array($this, 'format_role_based_price_html'), $hook_priority, 2);
            add_filter('woocommerce_available_variation', array($this, 'format_variation_price_html'), $hook_priority, 3);
            
            // Add performance monitoring hooks if enabled
            if (defined('MAXT_RBP_PERFORMANCE_MONITORING') && MAXT_RBP_PERFORMANCE_MONITORING) {
                add_action('wp_footer', array($this, 'log_hook_performance'));
            }
            
        } catch (Exception $e) {
            // Log hook registration errors
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Hook Registration Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Plugin activation
     * PERFORMANCE FIX: Only run database operations on version changes
     */
    public function activate() {
        // Check if this is a version upgrade
        $current_version = get_option('maxt_rbp_version', '0');
        $is_upgrade = version_compare($current_version, MAXT_RBP_VERSION, '<');
        
        // Initialize core functionality
        $core = new MaxT_RBP_Core();
        
        // Only run database operations on first install or version upgrade
        if ($is_upgrade || $current_version === '0') {
            // Create custom table for pricing rules
            $core->create_table();
            
            // Add database indexes for existing installations
            $core->add_database_indexes();
            
            // Set default options
            $this->set_default_options();
            
            // Update version
            update_option('maxt_rbp_version', MAXT_RBP_VERSION);
        }
        
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
     * PERFORMANCE FIX: In-memory storage eliminates database writes during price calculation
     */
    public function get_role_based_price($price, $product) {
        try {
            if (!$this->core) {
                return $price;
            }
            
            // Early return for invalid price
            if (!$price || $price <= 0) {
                return $price;
            }
            
            // Only modify price if user is logged in and has a role
            if (!is_user_logged_in()) {
                return $price;
            }
            
            // Cache user's pricing rules status to avoid repeated database queries
            $user_has_pricing_rules = $this->get_user_pricing_rules_status();
            if (!$user_has_pricing_rules) {
                return $price;
            }
            
            $user_role = $this->get_current_user_role();
            if (!$user_role) {
                return $price;
            }
            
            // Calculate role-based price
            $role_price = $this->core->calculate_price($price, $product);
            
            // If we have a discount, store original price in memory and return discounted price
            if ($role_price && $role_price < $price) {
                // Store original price in static array for display purposes
                self::$original_prices[$product->get_id()] = $price;
                self::$user_has_discounts = true;
                return $role_price;
            }
            
            // No discount applies - ensure no stale data in memory
            unset(self::$original_prices[$product->get_id()]);
            
            return $price;
            
        } catch (Exception $e) {
            // Log error and return original price to ensure functionality continues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Price Calculation Error: ' . $e->getMessage());
            }
            
            // Always return original price on error to prevent pricing failures
            return $price;
        }
    }

    /**
     * Get role-based regular price - always return original price unchanged
     * ARCHITECTURAL FIX: Removed global flag hack, simplified to always return original
     */
    public function get_role_based_regular_price($price, $product) {
        // Always return the original regular price unchanged
        // This ensures WooCommerce can display original prices with strikethrough
        return $price;
    }

    /**
     * Format role-based price HTML with strikethrough for discounted prices
     * PERFORMANCE FIX: Uses in-memory storage instead of database reads
     */
    public function format_role_based_price_html($price_html, $product) {
        try {
            if (!$product || !$this->core) {
                return $price_html;
            }
            
            // Early return if no discounts have been applied during this request
            if (!self::$user_has_discounts) {
                return $price_html;
            }
            
            // Only apply to logged-in users with pricing rules
            if (!is_user_logged_in()) {
                return $price_html;
            }
            
            $user_has_pricing_rules = $this->get_user_pricing_rules_status();
            if (!$user_has_pricing_rules) {
                return $price_html;
            }
            
            // Get original price from in-memory storage
            $product_id = $product->get_id();
            if (!isset(self::$original_prices[$product_id])) {
                return $price_html;
            }
            
            $original_price = self::$original_prices[$product_id];
            if (!$original_price || $original_price <= 0) {
                return $price_html;
            }
            
            // Get current price (which should be the discounted price)
            $current_price = $product->get_price();
            if (!$current_price || $current_price <= 0 || $current_price >= $original_price) {
                // Remove stale data from memory if prices don't match expected discount
                unset(self::$original_prices[$product_id]);
                return $price_html;
            }
            
            // Format with strikethrough original price and highlighted discounted price
            $original_price_html = wc_price($original_price);
            $discounted_price_html = wc_price($current_price);
            
            return sprintf(
                '<span class="maxt-rbp-price"><del class="maxt-rbp-original">%s</del> <ins class="maxt-rbp-member">%s</ins></span>',
                $original_price_html,
                $discounted_price_html
            );
            
        } catch (Exception $e) {
            // Log error and return original price HTML to ensure functionality continues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Price HTML Formatting Error: ' . $e->getMessage());
            }
            
            return $price_html;
        }
    }

    /**
     * Format variation price HTML with strikethrough for discounted prices
     * PERFORMANCE FIX: Uses in-memory storage instead of database reads for variations
     */
    public function format_variation_price_html($variation_data, $variable_product, $variation) {
        try {
            if (!$variation || !$this->core) {
                return $variation_data;
            }
            
            // Early return if no discounts have been applied during this request
            if (!self::$user_has_discounts) {
                return $variation_data;
            }
            
            // Only apply to logged-in users with pricing rules
            if (!is_user_logged_in()) {
                return $variation_data;
            }
            
            $user_has_pricing_rules = $this->get_user_pricing_rules_status();
            if (!$user_has_pricing_rules) {
                return $variation_data;
            }
            
            // Get original price from in-memory storage using variation ID
            $variation_id = $variation->get_id();
            if (!isset(self::$original_prices[$variation_id])) {
                return $variation_data;
            }
            
            $original_price = self::$original_prices[$variation_id];
            if (!$original_price || $original_price <= 0) {
                return $variation_data;
            }
            
            // Get current price (which should be the discounted price)
            $current_price = $variation->get_price();
            if (!$current_price || $current_price <= 0 || $current_price >= $original_price) {
                // Remove stale data from memory if prices don't match expected discount
                unset(self::$original_prices[$variation_id]);
                return $variation_data;
            }
            
            // Format with strikethrough original price and highlighted discounted price
            $original_price_html = wc_price($original_price);
            $discounted_price_html = wc_price($current_price);
            
            $variation_data['price_html'] = sprintf(
                '<span class="maxt-rbp-price"><del class="maxt-rbp-original">%s</del> <ins class="maxt-rbp-member">%s</ins></span>',
                $original_price_html,
                $discounted_price_html
            );
            
            return $variation_data;
            
        } catch (Exception $e) {
            // Log error and return original variation data to ensure functionality continues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Variation Price HTML Formatting Error: ' . $e->getMessage());
            }
            
            return $variation_data;
        }
    }



    /**
     * Get current user role with caching
     */
    private function get_current_user_role() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id || $user_id <= 0) {
            return false;
        }
        
        // Cache user role to avoid repeated wp_get_current_user() calls
        $cache_key = 'maxt_rbp_user_role_' . $user_id;
        $cached_role = wp_cache_get($cache_key, 'maxt_rbp_user_roles');
        
        if ($cached_role !== false) {
            return $cached_role;
        }
        
        $user = wp_get_current_user();
        $role = $user->roles[0] ?? false;
        
        // Cache for 5 minutes
        if ($role) {
            wp_cache_set($cache_key, $role, 'maxt_rbp_user_roles', 300);
        }
        
        return $role;
    }

    /**
     * Get user's pricing rules status with caching
     * This avoids repeated database queries for the same user
     * CRITICAL FIX: Now checks BOTH global AND product-specific rules
     */
    private function get_user_pricing_rules_status() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        
        // Input validation for user ID safety
        if (!$user_id || $user_id <= 0 || !is_numeric($user_id)) {
            return false;
        }
        
        // Add multisite-safe cache key prefix
        $site_prefix = is_multisite() ? get_current_blog_id() . '_' : '';
        $cache_key = 'maxt_rbp_user_has_rules_' . $site_prefix . $user_id;
        
        // Check cache first with error handling
        $cached_status = false;
        try {
            $cached_status = wp_cache_get($cache_key, 'maxt_rbp_user_status');
        } catch (Exception $e) {
            // Log cache error and continue with database check
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Cache Error: ' . $e->getMessage());
            }
        }
        
        if ($cached_status !== false) {
            return $cached_status;
        }
        
        // If not cached, check if user has any applicable pricing rules
        $user_role = $this->get_current_user_role();
        if (!$user_role) {
            // Cache negative result for 5 minutes
            try {
                wp_cache_set($cache_key, false, 'maxt_rbp_user_status', 300);
            } catch (Exception $e) {
                // Cache failed, but continue
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MaxT RBP Cache Set Error: ' . $e->getMessage());
                }
            }
            return false;
        }
        
        $has_rules = false;
        
        try {
            // Check if there are any global rules for this role
            $global_rule = $this->core->get_global_rule($user_role);
            if (!empty($global_rule)) {
                $has_rules = true;
            } else {
                // CRITICAL FIX: Also check for product-specific rules
                // This was missing and could cause pricing rules to be skipped
                $product_rules = $this->core->get_rules(array('role_name' => $user_role));
                if (!empty($product_rules)) {
                    $has_rules = true;
                }
            }
        } catch (Exception $e) {
            // Database error - log and return false for safety
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Database Error in get_user_pricing_rules_status: ' . $e->getMessage());
            }
            return false;
        }
        
        // Cache the result for 5 minutes with error handling
        try {
            wp_cache_set($cache_key, $has_rules, 'maxt_rbp_user_status', 300);
        } catch (Exception $e) {
            // Cache failed, but continue
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Cache Set Error: ' . $e->getMessage());
            }
        }
        
        return $has_rules;
    }

    /**
     * Clear user pricing rules status cache
     * Called when pricing rules are updated
     * CRITICAL FIX: Added multisite support and error handling
     */
    public function clear_user_pricing_rules_cache($user_id = null) {
        try {
            if ($user_id) {
                // Input validation for user ID
                if (!$user_id || $user_id <= 0 || !is_numeric($user_id)) {
                    return;
                }
                
                // Add multisite-safe cache key prefix
                $site_prefix = is_multisite() ? get_current_blog_id() . '_' : '';
                $cache_key = 'maxt_rbp_user_has_rules_' . $site_prefix . $user_id;
                
                wp_cache_delete($cache_key, 'maxt_rbp_user_status');
            } else {
                // Clear all user status cache with compatibility check
                if (function_exists('wp_cache_flush_group')) {
                    wp_cache_flush_group('maxt_rbp_user_status');
                } else {
                    // Fallback for shared hosting environments
                    global $wpdb;
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_maxt_rbp_user_%'");
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_maxt_rbp_user_%'");
                }
            }
        } catch (Exception $e) {
            // Log cache clearing errors
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Cache Clear Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Log hook execution performance for monitoring
     */
    public function log_hook_performance() {
        if (!defined('MAXT_RBP_PERFORMANCE_MONITORING') || !MAXT_RBP_PERFORMANCE_MONITORING) {
            return;
        }
        
        $hook_stats = get_transient('maxt_rbp_hook_stats');
        if (!$hook_stats) {
            $hook_stats = array();
        }
        
        // Log current page hook execution
        $current_page = get_queried_object_id();
        $hook_stats['pages'][$current_page] = ($hook_stats['pages'][$current_page] ?? 0) + 1;
        $hook_stats['last_updated'] = current_time('mysql');
        
        set_transient('maxt_rbp_hook_stats', $hook_stats, 3600); // Cache for 1 hour
    }


    /**
     * Clear pricing cache
     */
    private function clear_pricing_cache() {
        if ($this->core) {
            $this->core->clear_all_cache();
        }
        
        // Clear in-memory storage
        self::clear_in_memory_storage();
    }

    /**
     * Clear in-memory storage for original prices and discount flags
     * PERFORMANCE FIX: Ensures clean state between different users/requests
     */
    public static function clear_in_memory_storage() {
        self::$original_prices = array();
        self::$user_has_discounts = false;
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
            echo '<p><strong>' . esc_html__('WooCommerce Role-Based Pricing', 'maxt-rbp') . '</strong> ' . 
                 esc_html__('has been activated!', 'maxt-rbp') . '</p>';
            echo '<p>' . sprintf(
                esc_html__('Go to %s to create custom roles and set up pricing rules.', 'maxt-rbp'),
                '<a href="' . admin_url('admin.php?page=maxt-role-pricing') . '">' . esc_html__('WooCommerce > Role-Based Pricing', 'maxt-rbp') . '</a>'
            ) . '</p>';
            echo '</div>';
            delete_transient('maxt_rbp_activation_notice');
        }
    }

    /**
     * Schedule performance data cleanup to prevent database bloat
     * PERFORMANCE FIX: Automatically clean up old monitoring data
     */
    public function schedule_performance_cleanup() {
        // Only run cleanup once per day to avoid performance impact
        $last_cleanup = get_option('maxt_rbp_last_performance_cleanup', 0);
        $current_time = time();
        
        if ($current_time - $last_cleanup > 86400) { // 24 hours
            if ($this->core) {
                $this->core->cleanup_performance_data();
                update_option('maxt_rbp_last_performance_cleanup', $current_time);
            }
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . esc_html__('WooCommerce Role-Based Pricing', 'maxt-rbp') . '</strong> ' . 
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
            'maxt_rbp_db_indexes_version',
            'maxt_rbp_db_logs',
            'maxt_rbp_query_logs',
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
