<?php
/**
 * Frontend functionality for MaxT Role Based Pricing
 *
 * @package MaxT_RBP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class for handling role-based pricing display
 */
class MaxT_RBP_Frontend {

    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        // Frontend display filters disabled - using WooCommerce native sale price functionality
        // add_filter('woocommerce_get_price_html', array($this, 'display_role_based_price_html'), 10, 2);
        // add_filter('woocommerce_available_variation', array($this, 'display_variation_price_html'), 10, 3);
    }

    public function enqueue_styles() {
        if ($this->should_enqueue_styles()) {
            wp_enqueue_style('maxt-rbp-frontend', MAXT_RBP_PLUGIN_URL . 'assets/css/frontend.css', array(), MAXT_RBP_VERSION);
        }
    }

    private function should_enqueue_styles() {
        return is_woocommerce() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag() || is_product() || is_page('shop') || is_page('cart') || is_page('checkout');
    }

    public function display_role_based_price_html($price_html, $product) {
        if (!$product || !$this->core) return $price_html;
        if (!is_user_logged_in()) return $price_html;
        
        $user_role = $this->get_current_user_role();
        if (!$user_role) return $price_html;
        
        // Allow administrators to see role-based pricing for testing
        // if ($user_role === 'administrator') return $price_html;
        
        // Check if we're on a product page (not cart/checkout)
        if (is_cart() || is_checkout()) {
            // On cart/checkout pages, let WooCommerce price filters handle the pricing
            // and just return the modified price HTML
            return $price_html;
        }
        
        // On product pages, apply our custom display formatting
        $original_price = $product->get_regular_price();
        if (!$original_price || $original_price <= 0) return $price_html;
        
        $role_price = $this->core->calculate_price($original_price, $product);
        
        if ($role_price && $role_price < $original_price) {
            return $this->format_role_based_price($original_price, $role_price);
        }
        
        return $price_html;
    }

    public function display_variation_price_html($variation_data, $variable_product, $variation) {
        if (!$variation || !$this->core) return $variation_data;
        if (!is_user_logged_in()) return $variation_data;
        
        $user_role = $this->get_current_user_role();
        if (!$user_role) return $variation_data;
        
        // Allow administrators to see role-based pricing for testing
        // if ($user_role === 'administrator') return $variation_data;
        
        $original_price = $variation->get_regular_price();
        if (!$original_price || $original_price <= 0) return $variation_data;
        
        $role_price = $this->core->calculate_price($original_price, $variation);
        
        if ($role_price && $role_price < $original_price) {
            $variation_data['price_html'] = $this->format_role_based_price($original_price, $role_price);
        }
        
        return $variation_data;
    }

    private function format_role_based_price($original_price, $member_price) {
        $original_price_html = wc_price($original_price);
        $member_price_html = wc_price($member_price);
        
        return sprintf(
            '<span class="maxt-rbp-price"><del class="maxt-rbp-original">%s</del> <ins class="maxt-rbp-member">%s</ins></span>',
            $original_price_html,
            $member_price_html
        );
    }

    private function get_current_user_role() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        return $user->roles[0] ?? false;
    }
}