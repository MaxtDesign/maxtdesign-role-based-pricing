<?php
/**
 * Temporary Debug Script for Role-Based Pricing
 * Add this to your theme's functions.php temporarily to debug
 * REMOVE AFTER DEBUGGING
 */

add_action('wp_footer', function() {
    if (!is_admin() && is_user_logged_in() && (is_product() || is_shop())) {
        echo '<div style="position:fixed; bottom:0; left:0; background:#000; color:#0f0; padding:10px; font-family:monospace; font-size:11px; max-width:400px; z-index:99999;">';
        echo '<strong>🔧 RBP Debug Info:</strong><br>';
        
        // Check if plugin class exists
        if (class_exists('MaxtDesign_Role_Based_Pricing')) {
            echo '✅ Plugin Class Loaded<br>';
        } else {
            echo '❌ Plugin Class NOT Found<br>';
        }
        
        // Check user role
        $user = wp_get_current_user();
        echo '👤 User Role: ' . ($user->roles[0] ?? 'No role') . '<br>';
        
        // Check if core exists
        global $wpdb;
        $global_rules = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}maxtdesign_rbp_global_rules WHERE is_active = 1");
        echo '📊 Active Global Rules: ' . count($global_rules) . '<br>';
        
        if ($global_rules) {
            foreach ($global_rules as $rule) {
                echo '&nbsp;&nbsp;→ ' . esc_html($rule->role_name) . ': ' . esc_html($rule->discount_value) . esc_html($rule->discount_type) . '<br>';
            }
        }
        
        // Check if on product page
        if (is_product()) {
            global $product;
            if ($product) {
                echo '🛍️ Product ID: ' . $product->get_id() . '<br>';
                echo '💰 Current Price: $' . $product->get_price() . '<br>';
                echo '💵 Regular Price: $' . $product->get_regular_price() . '<br>';
                
                // Deep dive into pricing calculation
                if (class_exists('MaxtDesign_Role_Based_Pricing')) {
                    $instance = MaxtDesign_Role_Based_Pricing::get_instance();
                    if (method_exists($instance, 'get_core')) {
                        $core = $instance->get_core();
                        $user_role = $user->roles[0] ?? false;
                        
                        // Check for pricing rule
                        $product_id = $product->get_id();
                        $global_rule = null;
                        
                        // Access protected method via reflection
                        $reflection = new ReflectionClass($core);
                        if ($reflection->hasMethod('get_global_rule')) {
                            $method = $reflection->getMethod('get_global_rule');
                            $method->setAccessible(true);
                            $global_rule = $method->invoke($core, $user_role);
                        }
                        
                        if ($global_rule) {
                            echo '✅ Rule Found: ' . $global_rule['discount_value'] . $global_rule['discount_type'] . '<br>';
                            echo '🔢 Expected Price: $' . ($global_rule['discount_type'] == 'percentage' 
                                ? ($product->get_regular_price() * (1 - $global_rule['discount_value']/100))
                                : ($product->get_regular_price() - $global_rule['discount_value'])) . '<br>';
                        } else {
                            echo '❌ No Rule Found for ' . $user_role . '<br>';
                        }
                        
                        // Check WP_DEBUG status
                        echo '🐛 WP_DEBUG: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'ON' : 'OFF') . '<br>';
                    }
                }
            }
        }
        
        echo '</div>';
    }
});

