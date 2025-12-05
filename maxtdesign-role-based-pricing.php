<?php
/**
 * Plugin Name: MaxtDesign Role-Based Pricing for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/maxtdesign-role-based-pricing
 * Description: Professional role-based pricing for WooCommerce. Set different prices for different user roles with percentage or fixed discounts.
 * Version: 1.0.0
 * Author: MaxtDesign
 * Author URI: https://maxtdesign.com
 * Text Domain: maxtdesign-role-based-pricing
 * Requires at least: 6.2
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MaxtDesign_RBP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAXTDESIGN_RBP_VERSION', '1.0.0');
define('MAXTDESIGN_RBP_PLUGIN_FILE', __FILE__);
define('MAXTDESIGN_RBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAXTDESIGN_RBP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAXTDESIGN_RBP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Define performance monitoring constant if not already defined
if (!defined('MAXTDESIGN_RBP_PERFORMANCE_MONITORING')) {
    define('MAXTDESIGN_RBP_PERFORMANCE_MONITORING', false);
}

// Include required files
require_once MAXTDESIGN_RBP_PLUGIN_DIR . 'includes/class-core.php';
require_once MAXTDESIGN_RBP_PLUGIN_DIR . 'includes/class-admin.php';
require_once MAXTDESIGN_RBP_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * Main WooCommerce Role-Based Pricing Class
 */
class MaxtDesign_Role_Based_Pricing {

    /**
     * Single instance of the class
     *
     * @var MaxtDesign_Role_Based_Pricing
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
     * @var MaxtDesign_RBP_Core
     */
    private $core;

    /**
     * Admin instance
     *
     * @var MaxtDesign_RBP_Admin
     */
    private $admin;

    /**
     * Frontend instance
     *
     * @var MaxtDesign_RBP_Frontend
     */
    private $frontend;

    /**
     * Get single instance
     *
     * @return MaxtDesign_Role_Based_Pricing
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
        register_uninstall_hook(__FILE__, array('MaxtDesign_Role_Based_Pricing', 'uninstall'));
        
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
        $this->core = new MaxtDesign_RBP_Core();

        // Initialize admin interface
        $this->admin = new MaxtDesign_RBP_Admin($this->core);
        $this->admin->init();

        // Initialize frontend
        $this->frontend = new MaxtDesign_RBP_Frontend($this->core);
        $this->frontend->init();

        // Text domain is automatically loaded by WordPress since 4.6

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
        
        // CRITICAL: Clear cache and memory after each order to prevent rapid-order exploits
        add_action('woocommerce_checkout_order_processed', array($this, 'clear_cache_after_order'), 99, 1);
        add_action('woocommerce_thankyou', array($this, 'clear_session_after_order'), 99, 1);
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
            if (defined('MAXTDESIGN_RBP_PERFORMANCE_MONITORING') && MAXTDESIGN_RBP_PERFORMANCE_MONITORING) {
                add_action('wp_footer', array($this, 'log_hook_performance'));
            }
            
        } catch (Exception $e) {
            // Log hook registration errors
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
            }
        }
    }

    /**
     * Plugin activation
     * PERFORMANCE FIX: Only run database operations on version changes
     */
    public function activate() {
        // Check if this is a version upgrade
        $current_version = get_option('maxtdesign_rbp_version', '0');
        $is_upgrade = version_compare($current_version, MAXTDESIGN_RBP_VERSION, '<');
        
        // Initialize core functionality
        $core = new MaxtDesign_RBP_Core();
        
        // Only run database operations on first install or version upgrade
        if ($is_upgrade || $current_version === '0') {
            // Create custom table for pricing rules
            $core->create_table();
            
            // Add database indexes for existing installations
            $core->add_database_indexes();
            
            // Set default options
            $this->set_default_options();
            
            // Update version
            update_option('maxtdesign_rbp_version', MAXTDESIGN_RBP_VERSION);
        }
        
        // Set activation notice transient
        set_transient('maxtdesign_rbp_activation_notice', true, 60);
        
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
            'maxtdesign_rbp_version' => MAXTDESIGN_RBP_VERSION,
            'maxtdesign_rbp_cache_duration' => 3600, // 1 hour
            'maxtdesign_rbp_display_original_price' => true,
            'maxtdesign_rbp_cache_method' => 'auto', // Will be auto-detected
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
            
            // Note: Variable products (parent) will return original price, but variations will get discounted prices
            // This allows WooCommerce to calculate discounted price ranges from the variation prices
            
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
                // Debug logging removed for security compliance
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
            
            // Handle variable products - calculate discounted price range
            if ($product->is_type('variable')) {
                return $this->format_variable_product_discounted_range($price_html, $product);
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
                '<span class="maxtdesign-rbp-price"><del class="maxtdesign-rbp-original">%s</del> <ins class="maxtdesign-rbp-member">%s</ins></span>',
                $original_price_html,
                $discounted_price_html
            );
            
        } catch (Exception $e) {
            // Log error and return original price HTML to ensure functionality continues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
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
                '<span class="maxtdesign-rbp-price"><del class="maxtdesign-rbp-original">%s</del> <ins class="maxtdesign-rbp-member">%s</ins></span>',
                $original_price_html,
                $discounted_price_html
            );
            
            return $variation_data;
            
        } catch (Exception $e) {
            // Log error and return original variation data to ensure functionality continues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
            }
            
            return $variation_data;
        }
    }

    /**
     * Format variable product discounted price range
     * NEW FEATURE: Shows discounted price ranges for variable products instead of original ranges
     */
    private function format_variable_product_discounted_range($price_html, $product) {
        try {
            // Only apply to logged-in users with pricing rules
            if (!is_user_logged_in()) {
                return $price_html;
            }
            
            $user_has_pricing_rules = $this->get_user_pricing_rules_status();
            if (!$user_has_pricing_rules) {
                return $price_html;
            }
            
            // Get all available variations
            $variations = $product->get_available_variations();
            if (empty($variations)) {
                return $price_html;
            }
            
            $min_price = PHP_INT_MAX;
            $max_price = 0;
            $has_discounted_variations = false;
            
            // Loop through variations to find min and max discounted prices
            foreach ($variations as $variation_data) {
                $variation_id = $variation_data['variation_id'];
                $variation_product = wc_get_product($variation_id);
                
                if (!$variation_product || !$variation_product->exists()) {
                    continue;
                }
                
                // Get the current price (which should already be discounted by get_role_based_price)
                $current_price = $variation_product->get_price();
                if (!$current_price || $current_price <= 0) {
                    continue;
                }
                
                // Get the original price from our in-memory storage to check if discount was applied
                $original_price = isset(self::$original_prices[$variation_id]) ? self::$original_prices[$variation_id] : $current_price;
                
                // Use the current price (already discounted) as the discounted price
                $discounted_price = $current_price;
                
                // Only include variations that have discounts
                if ($discounted_price && $discounted_price < $original_price) {
                    $has_discounted_variations = true;
                    
                    // Update min and max prices
                    if ($discounted_price < $min_price) {
                        $min_price = $discounted_price;
                    }
                    if ($discounted_price > $max_price) {
                        $max_price = $discounted_price;
                    }
                }
            }
            
            // If no variations have discounts, return original price HTML
            if (!$has_discounted_variations || $min_price === PHP_INT_MAX || $max_price === 0) {
                return $price_html;
            }
            
            // Format the discounted price range
            if ($min_price === $max_price) {
                return wc_price($min_price);
            } else {
                return wc_price($min_price) . ' - ' . wc_price($max_price);
            }
            
        } catch (Exception $e) {
            // Log error and return original price HTML to ensure functionality continues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
            }
            
            return $price_html;
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
        $cache_key = 'maxtdesign_rbp_user_role_' . $user_id;
        $cached_role = wp_cache_get($cache_key, 'maxtdesign_rbp_user_roles');
        
        if ($cached_role !== false) {
            return $cached_role;
        }
        
        $user = wp_get_current_user();
        $role = $user->roles[0] ?? false;
        
        // Cache for 5 minutes
        if ($role) {
            wp_cache_set($cache_key, $role, 'maxtdesign_rbp_user_roles', 300);
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
        $cache_key = 'maxtdesign_rbp_user_has_rules_' . $site_prefix . $user_id;
        
        // Check cache first with error handling
        $cached_status = false;
        try {
            $cached_status = wp_cache_get($cache_key, 'maxtdesign_rbp_user_status');
        } catch (Exception $e) {
            // Log cache error and continue with database check
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
            }
        }
        
        // Bypass cache in debug mode to ensure fresh data during development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $cached_status = false;
        }
        
        if ($cached_status !== false) {
            return $cached_status;
        }
        
        // If not cached, check if user has any applicable pricing rules
        $user_role = $this->get_current_user_role();
        if (!$user_role) {
            // Cache negative result for 5 minutes
            try {
                wp_cache_set($cache_key, false, 'maxtdesign_rbp_user_status', 300);
            } catch (Exception $e) {
                // Cache failed, but continue
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // Debug logging removed for security compliance
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
                // Debug logging removed for security compliance
            }
            return false;
        }
        
        // Cache the result for 5 minutes with error handling
        try {
            wp_cache_set($cache_key, $has_rules, 'maxtdesign_rbp_user_status', 300);
        } catch (Exception $e) {
            // Cache failed, but continue
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
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
                $cache_key = 'maxtdesign_rbp_user_has_rules_' . $site_prefix . $user_id;
                
                wp_cache_delete($cache_key, 'maxtdesign_rbp_user_status');
            } else {
                // Clear all user status cache with compatibility check
                if (function_exists('wp_cache_flush_group')) {
                    wp_cache_flush_group('maxtdesign_rbp_user_status');
                } else {
                    // Fallback for shared hosting environments
                    global $wpdb;
                    // @codingStandardsIgnoreLine - Direct database query required for cache clearing
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_maxtdesign_rbp_user_%'");
                    // @codingStandardsIgnoreLine - Direct database query required for cache clearing
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_maxtdesign_rbp_user_%'");
                }
            }
        } catch (Exception $e) {
            // Log cache clearing errors
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
            }
        }
    }

    /**
     * Log hook execution performance for monitoring
     */
    public function log_hook_performance() {
        if (!defined('MAXTDESIGN_RBP_PERFORMANCE_MONITORING') || !MAXTDESIGN_RBP_PERFORMANCE_MONITORING) {
            return;
        }
        
        $hook_stats = get_transient('maxtdesign_rbp_hook_stats');
        if (!$hook_stats) {
            $hook_stats = array();
        }
        
        // Log current page hook execution
        $current_page = get_queried_object_id();
        $hook_stats['pages'][$current_page] = ($hook_stats['pages'][$current_page] ?? 0) + 1;
        $hook_stats['last_updated'] = current_time('mysql');
        
        set_transient('maxtdesign_rbp_hook_stats', $hook_stats, 3600); // Cache for 1 hour
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
     * Clear cache and in-memory storage after order is processed
     * CRITICAL FIX: Prevents stale pricing data from affecting subsequent rapid orders
     * 
     * This addresses a security vulnerability where:
     * - Order 1 calculates and caches pricing data in memory
     * - Order 2 (placed within minutes) reuses stale cached data
     * - Result: Price corruption allowing fraudulent sub-$1 purchases
     * 
     * Real-world exploit: Products worth $500+ sold for $0.54 due to
     * rapid automated ordering exploiting cache persistence.
     * 
     * @param int $order_id The WooCommerce order ID
     * @return void
     */
    public function clear_cache_after_order($order_id) {
        try {
            // Validate order exists
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Get user ID for targeted cache clearing
            $user_id = $order->get_user_id();
            
            // STEP 1: Clear in-memory storage (MOST CRITICAL)
            // This eliminates the primary attack vector
            self::clear_in_memory_storage();
            
            // STEP 2: Clear user-specific pricing rule status cache
            // Ensures fresh role/rule lookup on next request
            if ($user_id && $user_id > 0) {
                // Clear the "user has rules" cache
                $site_prefix = is_multisite() ? get_current_blog_id() . '_' : '';
                $user_rules_cache_key = 'maxtdesign_rbp_user_has_rules_' . $site_prefix . $user_id;
                wp_cache_delete($user_rules_cache_key, 'maxtdesign_rbp_user_status');
                
                // Clear user role cache
                $user_role_cache_key = 'maxtdesign_rbp_user_role_' . $user_id;
                wp_cache_delete($user_role_cache_key, 'maxtdesign_rbp_user_roles');
                
                // Clear transient fallbacks (for non-object-cache environments)
                delete_transient($user_rules_cache_key);
                delete_transient($user_role_cache_key);
            }
            
            // STEP 3: Clear price calculation cache for ordered items
            // Prevents cached prices from affecting future orders
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if ($product_id && $this->core) {
                    // Clear all cached prices for this product across all roles
                    $this->core->clear_product_cache($product_id);
                }
            }
            
            // STEP 4: Log cache clearing for debugging (only in WP_DEBUG mode)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'MaxtDesign RBP: Cleared cache after order #%d for user #%d (%d items)',
                    $order_id,
                    $user_id,
                    count($order->get_items())
                ));
            }
            
        } catch (Exception $e) {
            // CRITICAL: Never let cache clearing break order processing
            // Silent failure - order completion must succeed even if cache clear fails
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxtDesign RBP Cache Clear Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Clear WooCommerce session data after order completion
     * CRITICAL FIX: Prevents session-based pricing data persistence
     * 
     * This ensures that any session-stored pricing information is
     * cleared when the user completes checkout, preventing potential
     * session hijacking or session reuse exploits.
     * 
     * @param int $order_id The WooCommerce order ID
     * @return void
     */
    public function clear_session_after_order($order_id) {
        try {
            // Verify WooCommerce session exists
            if (!WC()->session) {
                return;
            }
            
            // Clear any plugin-specific session data
            WC()->session->set('maxtdesign_rbp_prices', null);
            WC()->session->set('maxtdesign_rbp_user_role', null);
            WC()->session->set('maxtdesign_rbp_discount_applied', null);
            
            // Force session regeneration for security
            // This prevents session fixation attacks
            if (method_exists(WC()->session, 'regenerate_id')) {
                WC()->session->regenerate_id(true);
            }
            
            // Log session clearing for debugging (only in WP_DEBUG mode)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'MaxtDesign RBP: Cleared session after order #%d',
                    $order_id
                ));
            }
            
        } catch (Exception $e) {
            // CRITICAL: Never let session clearing break order processing
            // Silent failure - order completion must succeed
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxtDesign RBP Session Clear Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get core instance
     *
     * @return MaxtDesign_RBP_Core
     */
    public function get_core() {
        return $this->core;
    }

    /**
     * Get admin instance
     *
     * @return MaxtDesign_RBP_Admin
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Get frontend instance
     *
     * @return MaxtDesign_RBP_Frontend
     */
    public function get_frontend() {
        return $this->frontend;
    }

    /**
     * Activation notice
     */
    public function activation_notice() {
        if (get_transient('maxtdesign_rbp_activation_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . esc_html__('MaxtDesign Role-Based Pricing for WooCommerce', 'maxtdesign-role-based-pricing') . '</strong> ' . 
                 esc_html__('has been activated!', 'maxtdesign-role-based-pricing') . '</p>';
            /* translators: %s is the link to the plugin settings page */
            echo '<p>' . sprintf(
                /* translators: %s is the link to the plugin settings page */
                esc_html__('Go to %s to create custom roles and set up pricing rules.', 'maxtdesign-role-based-pricing'),
                '<a href="' . esc_url(admin_url('admin.php?page=maxtdesign-role-pricing')) . '">' . esc_html__('WooCommerce > Role-Based Pricing', 'maxtdesign-role-based-pricing') . '</a>'
            ) . '</p>';
            echo '</div>';
            delete_transient('maxtdesign_rbp_activation_notice');
        }
    }

    /**
     * Schedule performance data cleanup to prevent database bloat
     * PERFORMANCE FIX: Automatically clean up old monitoring data
     */
    public function schedule_performance_cleanup() {
        // Only run cleanup once per day to avoid performance impact
        $last_cleanup = get_option('maxtdesign_rbp_last_performance_cleanup', 0);
        $current_time = time();
        
        if ($current_time - $last_cleanup > 86400) { // 24 hours
            if ($this->core) {
                $this->core->cleanup_performance_data();
                update_option('maxtdesign_rbp_last_performance_cleanup', $current_time);
            }
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . esc_html__('MaxtDesign Role-Based Pricing for WooCommerce', 'maxtdesign-role-based-pricing') . '</strong> ' . 
             esc_html__('requires WooCommerce to be installed and active.', 'maxtdesign-role-based-pricing') . '</p></div>';
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
        require_once MAXTDESIGN_RBP_PLUGIN_DIR . 'includes/class-core.php';
        require_once MAXTDESIGN_RBP_PLUGIN_DIR . 'includes/class-admin.php';
        require_once MAXTDESIGN_RBP_PLUGIN_DIR . 'includes/class-frontend.php';

        // Initialize core and cleanup
        $core = new MaxtDesign_RBP_Core();
        
        // Drop all plugin database tables
        $core->drop_table();
        
        // Remove only custom roles created by this plugin (not WordPress built-in roles)
        $core->remove_all_custom_roles();
        
        // Clear all plugin cache
        $core->clear_all_cache();

        // Delete all plugin options
        $plugin_options = array(
            'maxtdesign_rbp_version',
            'maxtdesign_rbp_cache_duration',
            'maxtdesign_rbp_display_original_price',
            'maxtdesign_rbp_cache_method',
            'maxtdesign_rbp_last_cache_clear',
            'maxtdesign_rbp_cache_logs',
            'maxtdesign_rbp_db_indexes_version',
            'maxtdesign_rbp_db_logs',
            'maxtdesign_rbp_query_logs',
        );

        foreach ($plugin_options as $option) {
            delete_option($option);
        }

        // Clear all transients and cache entries
        global $wpdb;
        // @codingStandardsIgnoreLine - Direct database query required for plugin cleanup
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_maxtdesign_rbp_%'");
        // @codingStandardsIgnoreLine - Direct database query required for plugin cleanup
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_maxtdesign_rbp_%'");
        // @codingStandardsIgnoreLine - Direct database query required for plugin cleanup
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'maxtdesign_rbp_%'");

        // Clear any cached data
        wp_cache_flush();

        // Remove any debug logs related to this plugin
        // @codingStandardsIgnoreLine - Direct database query required for plugin cleanup
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%maxtdesign_rbp_debug%'");
    }
}

// Initialize the plugin
MaxtDesign_Role_Based_Pricing::get_instance();
