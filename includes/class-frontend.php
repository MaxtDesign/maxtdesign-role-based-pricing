<?php
/**
 * Frontend functionality for Role-Based Pricing for WooCommerce
 *
 * @package MaxtDesign_RBP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class for handling role-based pricing display
 */
class MaxtDesign_RBP_Frontend {

    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        // ARCHITECTURAL FIX: Removed duplicate display filters - now handled by main plugin
        // Frontend class now only handles style enqueuing
    }

    public function enqueue_styles() {
        if ($this->should_enqueue_styles()) {
            wp_enqueue_style('maxtdesign-rbp-frontend', MAXTDESIGN_RBP_PLUGIN_URL . 'assets/css/frontend.css', array(), MAXTDESIGN_RBP_VERSION);
        }
    }

    private function should_enqueue_styles() {
        return is_woocommerce() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag() || is_product() || is_page('shop') || is_page('cart') || is_page('checkout');
    }

    // ARCHITECTURAL FIX: All display methods removed - now handled by main plugin
    // This eliminates duplicate logic and the global flag hack
}