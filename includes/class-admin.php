<?php
/**
 * Admin interface for Role-Based Pricing for WooCommerce
 *
 * @package MaxtDesign_RBP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for managing role-based pricing
 */
class MaxtDesign_RBP_Admin {

    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_product_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_ajax_maxtdesign_rbp_add_rule', array($this, 'ajax_add_rule'));
        add_action('wp_ajax_maxtdesign_rbp_delete_rule', array($this, 'ajax_delete_rule'));
        add_action('wp_ajax_maxtdesign_rbp_add_global_rule', array($this, 'ajax_add_global_rule'));
        add_action('wp_ajax_maxtdesign_rbp_delete_global_rule', array($this, 'ajax_delete_global_rule'));
        add_action('wp_ajax_maxtdesign_rbp_toggle_global_rule', array($this, 'ajax_toggle_global_rule'));
        add_action('wp_ajax_maxtdesign_rbp_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_maxtdesign_rbp_clear_role_cache', array($this, 'ajax_clear_role_cache'));
        add_action('wp_ajax_maxtdesign_rbp_clear_product_cache', array($this, 'ajax_clear_product_cache'));
        add_action('wp_ajax_maxtdesign_rbp_warm_cache', array($this, 'ajax_warm_cache'));
        add_action('wp_ajax_maxtdesign_rbp_get_cache_health', array($this, 'ajax_get_cache_health'));
        add_action('wp_ajax_maxtdesign_rbp_edit_global_rule', array($this, 'ajax_edit_global_rule'));
        add_action('wp_ajax_maxtdesign_rbp_edit_product_rule', array($this, 'ajax_edit_product_rule'));
        add_action('wp_ajax_maxtdesign_rbp_get_db_health', array($this, 'ajax_get_db_health'));
        add_action('wp_ajax_maxtdesign_rbp_get_db_performance', array($this, 'ajax_get_db_performance'));
        add_action('wp_ajax_maxtdesign_rbp_add_db_indexes', array($this, 'ajax_add_db_indexes'));
        add_action('wp_ajax_maxtdesign_rbp_get_hook_performance', array($this, 'ajax_get_hook_performance'));
        add_action('wp_ajax_maxtdesign_rbp_clear_hook_performance', array($this, 'ajax_clear_hook_performance'));
    }

    public function add_admin_menu() {
        add_submenu_page('woocommerce', __('Role-Based Pricing', 'maxtdesign-role-based-pricing'), __('Role-Based Pricing', 'maxtdesign-role-based-pricing'), 'manage_woocommerce', 'maxtdesign-role-pricing', array($this, 'settings_page'));
    }

    public function add_product_meta_box() {
        add_meta_box('maxtdesign-rbp-pricing', __('Role-Based Pricing', 'maxtdesign-role-based-pricing'), array($this, 'product_meta_box_content'), 'product', 'normal', 'high');
    }

    public function product_meta_box_content($post) {
        $product_id = $post->ID;
        $existing_rules = $this->core->get_rules(array('product_id' => $product_id));
        $global_rules = $this->core->get_all_global_rules();
        
        echo '<div class="maxtdesign-rbp-product-meta">';
        
        // Show global rules that apply to this product
        $active_global_rules = array_filter($global_rules, function($rule) { return $rule['is_active']; });
        if (!empty($active_global_rules)) {
            echo '<h4>' . esc_html__('Global Pricing Rules (Apply by Default)', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Status', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            foreach ($active_global_rules as $rule) {
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxtdesign-role-based-pricing') : __('Fixed Amount', 'maxtdesign-role-based-pricing');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                
                // Check if there's a product-specific override
                $has_override = false;
                foreach ($existing_rules as $existing_rule) {
                    if ($existing_rule['role_name'] === $rule['role_name']) {
                        $has_override = true;
                        break;
                    }
                }
                
                $status_display = $has_override ? '<span style="color: orange;">' . __('Overridden', 'maxtdesign-role-based-pricing') . '</span>' : '<span style="color: green;">' . __('Active', 'maxtdesign-role-based-pricing') . '</span>';
                
                echo '<tr><td>' . esc_html($role_display_name) . '</td><td>' . esc_html($discount_type_display) . '</td><td>' . esc_html($discount_value_display) . '</td><td>' . esc_html($status_display) . '</td></tr>';
            }
            echo '</tbody></table><br>';
        }
        
        // Show product-specific rules
        if (!empty($existing_rules)) {
            echo '<h4>' . esc_html__('Product-Specific Pricing Rules (Override Global)', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Actions', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            foreach ($existing_rules as $rule) {
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxtdesign-role-based-pricing') : __('Fixed Amount', 'maxtdesign-role-based-pricing');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                echo '<tr><td>' . esc_html($role_display_name) . '</td><td>' . esc_html($discount_type_display) . '</td><td>' . esc_html($discount_value_display) . '</td><td>';
                echo '<button type="button" class="button button-small maxtdesign-rbp-edit-product-rule" data-rule-id="' . esc_attr($rule['id']) . '" data-role-name="' . esc_attr($rule['role_name']) . '" data-discount-type="' . esc_attr($rule['discount_type']) . '" data-discount-value="' . esc_attr($rule['discount_value']) . '">' . esc_html__('Edit', 'maxtdesign-role-based-pricing') . '</button> ';
                echo '<a href="#" class="button button-small maxtdesign-rbp-delete-rule" data-rule-id="' . esc_attr($rule['id']) . '">' . esc_html__('Delete', 'maxtdesign-role-based-pricing') . '</a>';
                echo '</td></tr>';
            }
            echo '</tbody></table><br>';
        }
        
        echo '<h4>' . esc_html__('Add New Pricing Rule', 'maxtdesign-role-based-pricing') . '</h4>';
        
        $all_roles = $this->core->get_all_roles();
        $role_options = array('' => __('Select a role...', 'maxtdesign-role-based-pricing'));
        foreach ($all_roles as $role_name => $role_data) {
            $role_options[$role_name] = $role_data['display_name'];
        }
        foreach ($existing_rules as $rule) {
            unset($role_options[$rule['role_name']]);
        }
        
        if (empty($role_options) || count($role_options) === 1) {
            echo '<p>' . esc_html__('All available roles already have pricing rules for this product.', 'maxtdesign-role-based-pricing') . '</p>';
        } else {
            woocommerce_wp_select(array('id' => 'maxtdesign_rbp_role_name', 'label' => __('User Role', 'maxtdesign-role-based-pricing'), 'options' => $role_options, 'desc_tip' => true, 'description' => __('Select the user role for this pricing rule.', 'maxtdesign-role-based-pricing')));
            woocommerce_wp_select(array('id' => 'maxtdesign_rbp_discount_type', 'label' => __('Discount Type', 'maxtdesign-role-based-pricing'), 'options' => array('percentage' => __('Percentage', 'maxtdesign-role-based-pricing'), 'fixed' => __('Fixed Amount', 'maxtdesign-role-based-pricing')), 'desc_tip' => true, 'description' => __('Choose whether to apply a percentage or fixed amount discount.', 'maxtdesign-role-based-pricing')));
            woocommerce_wp_text_input(array('id' => 'maxtdesign_rbp_discount_value', 'label' => __('Discount Value', 'maxtdesign-role-based-pricing'), 'type' => 'number', 'custom_attributes' => array('step' => '0.01', 'min' => '0'), 'desc_tip' => true, 'description' => __('Enter the discount value (percentage or amount).', 'maxtdesign-role-based-pricing')));
            echo '<p class="form-field"><button type="button" class="button button-primary" id="maxtdesign-rbp-add-rule">' . esc_html__('Add Pricing Rule', 'maxtdesign-role-based-pricing') . '</button></p>';
        }
        
        echo '</div>';
    }

    public function settings_page() {
        // Handle cache clearing
        if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (wp_verify_nonce($nonce, 'clear_cache') && current_user_can('manage_woocommerce')) {
                $this->core->clear_all_cache();
                echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared successfully.', 'maxtdesign-role-based-pricing') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxtdesign-role-based-pricing') . '</p></div>';
            }
        }
        
        // Handle cache warming
        if (isset($_GET['action']) && $_GET['action'] === 'warm_cache' && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (wp_verify_nonce($nonce, 'warm_cache') && current_user_can('manage_woocommerce')) {
                $warmed_count = $this->core->warm_cache();
                /* translators: %d is the number of cache entries created */
                echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Cache warmed successfully. %d entries created.', 'maxtdesign-role-based-pricing'), esc_html($warmed_count)) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxtdesign-role-based-pricing') . '</p></div>';
            }
        }
        
        // Handle creating default global rules
        if (isset($_GET['action']) && $_GET['action'] === 'create_default_global_rules' && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (wp_verify_nonce($nonce, 'create_default_global_rules') && current_user_can('manage_woocommerce')) {
                $result = $this->create_default_global_rules();
                if ($result['success']) {
                    echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxtdesign-role-based-pricing') . '</p></div>';
            }
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete_role' && isset($_GET['role']) && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            $role_name = sanitize_text_field(wp_unslash($_GET['role']));
            if (wp_verify_nonce($nonce, 'delete_role_' . $role_name) && current_user_can('manage_woocommerce')) {
                $result = $this->core->delete_custom_role($role_name);
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Role deleted successfully.', 'maxtdesign-role-based-pricing') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxtdesign-role-based-pricing') . '</p></div>';
            }
        }

        if (isset($_POST['create_role'])) {
            $result = $this->handle_role_creation();
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }

        echo '<div class="wrap"><h1>' . esc_html__('Role-Based Pricing Settings', 'maxtdesign-role-based-pricing') . '</h1>';
        
        // === TOP SECTIONS: Role Management and Pricing ===
        
        // Role Management Section
        echo '<div class="maxtdesign-rbp-settings-section"><h2>' . esc_html__('Role Management', 'maxtdesign-role-based-pricing') . '</h2>';
        
        $all_roles = $this->core->get_all_roles();
        echo '<h3>' . esc_html__('Current Roles', 'maxtdesign-role-based-pricing') . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__('Role Name', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Display Name', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Type', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Users', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Actions', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
        foreach ($all_roles as $role_name => $role_data) {
            echo '<tr><td><code>' . esc_html($role_name) . '</code></td><td>' . esc_html($role_data['display_name']) . '</td><td>' . ($role_data['is_custom'] ? '<span class="dashicons dashicons-admin-users"></span> ' . esc_html__('Custom', 'maxtdesign-role-based-pricing') : '<span class="dashicons dashicons-wordpress"></span> ' . esc_html__('Built-in', 'maxtdesign-role-based-pricing')) . '</td><td>' . esc_html($role_data['user_count']) . '</td><td>';
            if ($role_data['is_custom'] && $role_data['user_count'] === 0) {
                echo '<a href="?page=maxtdesign-role-pricing&action=delete_role&role=' . urlencode($role_name) . '&_wpnonce=' . esc_attr(wp_create_nonce('delete_role_' . $role_name)) . '" class="button button-small" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this role?', 'maxtdesign-role-based-pricing')) . '\')">' . esc_html__('Delete', 'maxtdesign-role-based-pricing') . '</a>';
            } else {
                echo '<span class="description">' . esc_html__('No actions available', 'maxtdesign-role-based-pricing') . '</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        
        echo '<h3>' . esc_html__('Create New Role', 'maxtdesign-role-based-pricing') . '</h3>';
        $this->render_role_creation_form();
        echo '</div>';
        
        // Add create default global rules button
        echo '<p><a href="?page=maxtdesign-role-pricing&action=create_default_global_rules&_wpnonce=' . esc_attr(wp_create_nonce('create_default_global_rules')) . '" class="button button-primary">' . esc_html__('Create Global Rules for All Roles', 'maxtdesign-role-based-pricing') . '</a></p>';
        
        // Global Pricing Rules Section
        echo '<div class="maxtdesign-rbp-settings-section"><h2>' . esc_html__('Global Pricing Rules', 'maxtdesign-role-based-pricing') . '</h2>';
        echo '<p>' . esc_html__('Global pricing rules apply to all products by default. Product-specific rules will override global rules.', 'maxtdesign-role-based-pricing') . '</p>';
        
        $global_rules = $this->core->get_all_global_rules();
        if (!empty($global_rules)) {
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Status', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Actions', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            foreach ($global_rules as $rule) {
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxtdesign-role-based-pricing') : __('Fixed Amount', 'maxtdesign-role-based-pricing');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                $status_display = $rule['is_active'] ? '<span style="color: green;">' . __('Active', 'maxtdesign-role-based-pricing') . '</span>' : '<span style="color: red;">' . __('Inactive', 'maxtdesign-role-based-pricing') . '</span>';
                
                echo '<tr>';
                echo '<td>' . esc_html($role_display_name) . '</td>';
                echo '<td>' . esc_html($discount_type_display) . '</td>';
                echo '<td>' . wp_kses_post($discount_value_display) . '</td>';
                echo '<td>' . wp_kses_post($status_display) . '</td>';
                echo '<td>';
                echo '<button type="button" class="button button-small maxtdesign-rbp-edit-global-rule" data-rule-id="' . esc_attr($rule['id']) . '" data-role-name="' . esc_attr($rule['role_name']) . '" data-discount-type="' . esc_attr($rule['discount_type']) . '" data-discount-value="' . esc_attr($rule['discount_value']) . '">' . esc_html__('Edit', 'maxtdesign-role-based-pricing') . '</button> ';
                echo '<button type="button" class="button button-small maxtdesign-rbp-toggle-global-rule" data-rule-id="' . esc_attr($rule['id']) . '" data-current-status="' . esc_attr($rule['is_active']) . '">';
                echo $rule['is_active'] ? esc_html__('Deactivate', 'maxtdesign-role-based-pricing') : esc_html__('Activate', 'maxtdesign-role-based-pricing');
                echo '</button> ';
                echo '<button type="button" class="button button-small maxtdesign-rbp-delete-global-rule" data-rule-id="' . esc_attr($rule['id']) . '">' . esc_html__('Delete', 'maxtdesign-role-based-pricing') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table><br>';
        } else {
            echo '<p>' . esc_html__('No global pricing rules have been created yet.', 'maxtdesign-role-based-pricing') . '</p>';
        }
        
        echo '<h3>' . esc_html__('Add Global Pricing Rule', 'maxtdesign-role-based-pricing') . '</h3>';
        $this->render_global_rule_form();
        echo '</div>';
        
        // Pricing Rules Overview Section
        $all_rules = $this->core->get_rules();
        echo '<div class="maxtdesign-rbp-settings-section"><h2>' . esc_html__('Pricing Rules Overview', 'maxtdesign-role-based-pricing') . '</h2>';
        if (empty($all_rules)) {
            echo '<p>' . esc_html__('No pricing rules have been created yet.', 'maxtdesign-role-based-pricing') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__('Product', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Created', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            foreach ($all_rules as $rule) {
                $product = wc_get_product($rule['product_id']);
                $product_name = $product ? $product->get_name() : __('Product not found', 'maxtdesign-role-based-pricing');
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxtdesign-role-based-pricing') : __('Fixed Amount', 'maxtdesign-role-based-pricing');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                echo '<tr><td>' . esc_html($product_name) . '</td><td>' . esc_html($role_display_name) . '</td><td>' . esc_html($discount_type_display) . '</td><td>' . esc_html($discount_value_display) . '</td><td>' . esc_html(date_i18n(get_option('date_format'), strtotime($rule['created_at']))) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
        
        // Add edit modal
        $this->render_edit_modal();
        
        // === BOTTOM SECTIONS: Cache Management and Performance Monitoring ===
        
        // Enhanced Cache Management Section
        echo '<div class="maxtdesign-rbp-settings-section"><h2>' . esc_html__('Cache Management', 'maxtdesign-role-based-pricing') . '</h2>';
        $this->render_cache_management_section();
        echo '</div>';
        
        // Performance Monitoring Sections - Conditionally rendered based on monitoring status
        if (defined('MAXTDESIGN_RBP_PERFORMANCE_MONITORING') && MAXTDESIGN_RBP_PERFORMANCE_MONITORING) {
            // Show actual performance sections when monitoring is enabled
            echo '<div class="maxtdesign-rbp-settings-section"><h2>' . esc_html__('Database Performance', 'maxtdesign-role-based-pricing') . '</h2>';
            $this->render_database_performance_section();
            echo '</div>';
            
            echo '<div class="maxtdesign-rbp-settings-section"><h2>' . esc_html__('Hook Performance Monitoring', 'maxtdesign-role-based-pricing') . '</h2>';
            $this->render_hook_performance_section();
            echo '</div>';
        } else {
            // Show informative message when monitoring is disabled
            echo '<div class="maxtdesign-rbp-settings-section">';
            echo '<div class="notice notice-info inline">';
            echo '<h3>' . esc_html__('Performance Monitoring', 'maxtdesign-role-based-pricing') . '</h3>';
            echo '<p>' . esc_html__('Performance monitoring is currently disabled to optimize plugin performance.', 'maxtdesign-role-based-pricing') . '</p>';
            echo '<p><strong>' . esc_html__('To enable detailed performance statistics:', 'maxtdesign-role-based-pricing') . '</strong></p>';
            echo '<ol>';
            echo '<li>' . esc_html__('Add this line to your wp-config.php file:', 'maxtdesign-role-based-pricing') . '</li>';
            echo '<li><code>define(\'MAXTDESIGN_RBP_PERFORMANCE_MONITORING\', true);</code></li>';
            echo '<li>' . esc_html__('Save the file and reload this page', 'maxtdesign-role-based-pricing') . '</li>';
            echo '</ol>';
            echo '<p><em>' . esc_html__('Note: Performance monitoring is intended for development and troubleshooting purposes.', 'maxtdesign-role-based-pricing') . '</em></p>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    private function render_role_creation_form() {
        if ($this->core->get_custom_roles_count() >= 3) {
            /* translators: %d is the maximum number of custom roles allowed */
            echo '<p>' . sprintf(esc_html__('Maximum %d custom roles reached.', 'maxtdesign-role-based-pricing'), 3) . '</p>';
            return;
        }
        ?>
        <form method="post" action="" class="maxtdesign-rbp-role-form">
            <?php wp_nonce_field('maxtdesign_rbp_create_role', 'maxtdesign_rbp_role_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="role_name"><?php esc_html_e('Role Name', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td><input type="text" id="role_name" name="role_name" class="regular-text" placeholder="<?php esc_attr_e('e.g., premium', 'maxtdesign-role-based-pricing'); ?>" required />
                    <p class="description"><?php esc_html_e('Lowercase letters, numbers, and underscores only. Will be prefixed with "maxtdesign_rbp_".', 'maxtdesign-role-based-pricing'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="display_name"><?php esc_html_e('Display Name', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td><input type="text" id="display_name" name="display_name" class="regular-text" placeholder="<?php esc_attr_e('e.g., Premium Customer', 'maxtdesign-role-based-pricing'); ?>" required />
                    <p class="description"><?php esc_html_e('Human-readable name for the role.', 'maxtdesign-role-based-pricing'); ?></p></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="create_role" class="button button-primary" value="<?php esc_attr_e('Create Role', 'maxtdesign-role-based-pricing'); ?>" /></p>
        </form>
        <?php
    }

    private function handle_role_creation() {
        if (!isset($_POST['create_role']) || !isset($_POST['maxtdesign_rbp_role_nonce'])) {
            return array('success' => false, 'message' => '');
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['maxtdesign_rbp_role_nonce'])), 'maxtdesign_rbp_create_role')) {
            return array('success' => false, 'message' => __('Security check failed.', 'maxtdesign-role-based-pricing'));
        }
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => __('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        $role_name = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
        $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $result = $this->core->create_custom_role($role_name, $display_name);
        if (is_wp_error($result)) {
            return array('success' => false, 'message' => $result->get_error_message());
        }
        return array('success' => true, 'message' => __('Role created successfully.', 'maxtdesign-role-based-pricing'));
    }

    public function ajax_add_rule() {
        // WORDPRESS.ORG REQUIREMENT: Extract and sanitize $_POST variables BEFORE nonce verification
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $role_name = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
        
        // NOW perform nonce verification (WordPress.org coding standards compliance)
        check_ajax_referer('maxtdesign_rbp_add_rule', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        // SECURITY: Enhanced input validation and sanitization
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        // Note: $role_name already extracted and sanitized above for WordPress.org compliance
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field(wp_unslash($_POST['discount_type'])) : '';
        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        
        // Validate product ID
        if ($product_id <= 0) {
            wp_send_json_error(__('Invalid product ID.', 'maxtdesign-role-based-pricing'));
        }
        
        // Validate role name
        if (empty($role_name) || strlen($role_name) > 100) {
            wp_send_json_error(__('Invalid role name.', 'maxtdesign-role-based-pricing'));
        }
        
        // Validate discount type
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxtdesign-role-based-pricing'));
        }
        
        // Validate discount value
        if ($discount_value <= 0) {
            wp_send_json_error(__('Discount value must be greater than 0.', 'maxtdesign-role-based-pricing'));
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxtdesign-role-based-pricing'));
        }
        
        // Additional security: Check if product exists
        if (!wc_get_product($product_id)) {
            wp_send_json_error(__('Product not found.', 'maxtdesign-role-based-pricing'));
        }
        
        $rule_data = array(
            'role_name' => $role_name, 
            'product_id' => $product_id, 
            'discount_type' => $discount_type, 
            'discount_value' => $discount_value
        );
        
        try {
            $rule_id = $this->core->create_rule($rule_data);
            
            if ($rule_id) {
                $this->core->clear_product_cache($product_id);
                do_action('maxtdesign_rbp_after_rule_created', $rule_id, $rule_data);
                wp_send_json_success(__('Pricing rule added successfully.', 'maxtdesign-role-based-pricing'));
            } else {
                wp_send_json_error(__('Failed to add pricing rule. Please check your input values.', 'maxtdesign-role-based-pricing'));
            }
        } catch (Exception $e) {
            // Log error in debug mode only
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Debug logging removed for security compliance
            }
            wp_send_json_error(__('An error occurred while adding the pricing rule.', 'maxtdesign-role-based-pricing'));
        }
    }

    public function ajax_delete_rule() {
        check_ajax_referer('maxtdesign_rbp_delete_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $rule = $this->core->get_rules(array('id' => $rule_id));
        $rule = !empty($rule) ? $rule[0] : null;
        
        $result = $this->core->delete_rule($rule_id);
        
        if ($result && $rule) {
            $this->core->clear_product_cache($rule['product_id']);
            do_action('maxtdesign_rbp_after_rule_deleted', $rule_id, $rule);
            wp_send_json_success(__('Pricing rule deleted successfully.', 'maxtdesign-role-based-pricing'));
        } else {
            wp_send_json_error(__('Failed to delete pricing rule. Rule may not exist.', 'maxtdesign-role-based-pricing'));
        }
    }

    public function admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php', 'woocommerce_page_maxtdesign-role-pricing'))) {
            wp_enqueue_script('jquery');
            
            // Enqueue admin CSS for cache management page
            if ($hook === 'woocommerce_page_maxtdesign-role-pricing') {
                wp_enqueue_style('maxtdesign-rbp-admin', MAXTDESIGN_RBP_PLUGIN_URL . 'assets/css/admin.css', array(), MAXTDESIGN_RBP_VERSION);
            }
            
            // Enqueue inline scripts for product meta box
            if (in_array($hook, array('post.php', 'post-new.php'))) {
                $this->enqueue_product_meta_box_scripts();
            }
            
            // Enqueue inline scripts for settings page
            if ($hook === 'woocommerce_page_maxtdesign-role-pricing') {
                $this->enqueue_settings_page_scripts();
            }
        }
    }

    /**
     * Enqueue inline scripts for product meta box
     */
    private function enqueue_product_meta_box_scripts() {
        $product_id = get_the_ID();
        if (!$product_id) {
            return;
        }
        
        $inline_js = "
            jQuery(document).ready(function(\$) {
                $('#maxtdesign-rbp-add-rule').on('click', function() {
                    var roleName = $('#maxtdesign_rbp_role_name').val();
                    var discountType = $('#maxtdesign_rbp_discount_type').val();
                    var discountValue = $('#maxtdesign_rbp_discount_value').val();
                    if (!roleName || !discountType || !discountValue) {
                        alert('" . esc_js(__('Please fill in all fields.', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_add_rule',
                            product_id: " . intval($product_id) . ",
                            role_name: roleName,
                            discount_type: discountType,
                            discount_value: discountValue,
                            nonce: '" . esc_attr(wp_create_nonce('maxtdesign_rbp_add_rule')) . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data || '" . esc_js(__('Error adding rule.', 'maxtdesign-role-based-pricing')) . "');
                            }
                        }
                    });
                });
                $('.maxtdesign-rbp-delete-rule').on('click', function(e) {
                    e.preventDefault();
                    if (!confirm('" . esc_js(__('Are you sure you want to delete this pricing rule?', 'maxtdesign-role-based-pricing')) . "')) return;
                    var ruleId = $(this).data('rule-id');
                    var \$row = $(this).closest('tr');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_delete_rule',
                            rule_id: ruleId,
                            nonce: '" . esc_attr(wp_create_nonce('maxtdesign_rbp_delete_rule')) . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                \$row.fadeOut(function() { $(this).remove(); });
                            } else {
                                alert(response.data || '" . esc_js(__('Error deleting rule.', 'maxtdesign-role-based-pricing')) . "');
                            }
                        }
                    });
                });
                
                // Edit Product Rule functionality
                $('.maxtdesign-rbp-edit-product-rule').on('click', function(e) {
                    e.preventDefault();
                    
                    var \$button = $(this);
                    var ruleId = \$button.data('rule-id');
                    var roleName = \$button.data('role-name');
                    var discountType = \$button.data('discount-type');
                    var discountValue = \$button.data('discount-value');
                    
                    // Create a simple prompt-based edit (since we're in product meta box)
                    var newDiscountType = prompt('" . esc_js(__('Discount Type (percentage or fixed):', 'maxtdesign-role-based-pricing')) . "', discountType);
                    if (newDiscountType === null) return; // User cancelled
                    
                    if (!['percentage', 'fixed'].includes(newDiscountType)) {
                        alert('" . esc_js(__('Invalid discount type. Please enter \"percentage\" or \"fixed\".', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    
                    var newDiscountValue = prompt('" . esc_js(__('Discount Value:', 'maxtdesign-role-based-pricing')) . "', discountValue);
                    if (newDiscountValue === null) return; // User cancelled
                    
                    newDiscountValue = parseFloat(newDiscountValue);
                    if (isNaN(newDiscountValue) || newDiscountValue <= 0) {
                        alert('" . esc_js(__('Please enter a valid discount value greater than 0.', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    
                    if (newDiscountType === 'percentage' && newDiscountValue > 100) {
                        alert('" . esc_js(__('Percentage discount cannot exceed 100%.', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    
                    // Send AJAX request to update the rule
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_edit_product_rule',
                            rule_id: ruleId,
                            discount_type: newDiscountType,
                            discount_value: newDiscountValue,
                            nonce: '" . esc_attr(wp_create_nonce('maxtdesign_rbp_add_rule')) . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload(); // Reload to show updated data
                            } else {
                                alert(response.data || '" . esc_js(__('Error updating product rule.', 'maxtdesign-role-based-pricing')) . "');
                            }
                        },
                        error: function() {
                            alert('" . esc_js(__('Error updating product rule.', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
            });
        ";
        
        wp_add_inline_script('jquery', $inline_js);
    }

    /**
     * Enqueue inline scripts for settings page
     */
    private function enqueue_settings_page_scripts() {
        $inline_js = "
            jQuery(document).ready(function(\$) {
                // Global rule form submission
                $('#maxtdesign-rbp-global-rule-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var roleName = $('#global_role_name').val();
                    var discountType = $('#global_discount_type').val();
                    var discountValue = $('#global_discount_value').val();
                    
                    if (!roleName || !discountType || !discountValue) {
                        alert('" . esc_js(__('Please fill in all fields.', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_add_global_rule',
                            role_name: roleName,
                            discount_type: discountType,
                            discount_value: discountValue,
                            nonce: '" . esc_attr(wp_create_nonce('maxtdesign_rbp_global_rule')) . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data || '" . esc_js(__('Error adding global rule.', 'maxtdesign-role-based-pricing')) . "');
                            }
                        }
                    });
                });
                
                // Delete global rule
                $('.maxtdesign-rbp-delete-global-rule').on('click', function(e) {
                    e.preventDefault();
                    if (!confirm('" . esc_js(__('Are you sure you want to delete this global pricing rule?', 'maxtdesign-role-based-pricing')) . "')) return;
                    
                    var ruleId = $(this).data('rule-id');
                    var \$row = $(this).closest('tr');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_delete_global_rule',
                            rule_id: ruleId,
                            nonce: '" . esc_attr(wp_create_nonce('maxtdesign_rbp_global_rule')) . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                \$row.fadeOut(function() { $(this).remove(); });
                            } else {
                                alert(response.data || '" . esc_js(__('Error deleting global rule.', 'maxtdesign-role-based-pricing')) . "');
                            }
                        }
                    });
                });
                
                // Toggle global rule status
                $('.maxtdesign-rbp-toggle-global-rule').on('click', function(e) {
                    e.preventDefault();
                    
                    var ruleId = $(this).data('rule-id');
                    var \$button = $(this);
                    var \$row = \$button.closest('tr');
                    var \$statusCell = \$row.find('td:nth-child(4)');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_toggle_global_rule',
                            rule_id: ruleId,
                            nonce: '" . esc_attr(wp_create_nonce('maxtdesign_rbp_global_rule')) . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                var newStatus = response.data.new_status;
                                var statusText = response.data.status_text;
                                var buttonText = newStatus ? '" . esc_js(__('Deactivate', 'maxtdesign-role-based-pricing')) . "' : '" . esc_js(__('Activate', 'maxtdesign-role-based-pricing')) . "';
                                
                                \$statusCell.html('<span style=\"color: ' + (newStatus ? 'green' : 'red') + ';\">' + statusText + '</span>');
                                \$button.text(buttonText).data('current-status', newStatus);
                            } else {
                                alert(response.data || '" . esc_js(__('Error updating global rule status.', 'maxtdesign-role-based-pricing')) . "');
                            }
                        }
                    });
                });
                
                // Edit Global Rule functionality
                $('.maxtdesign-rbp-edit-global-rule').on('click', function(e) {
                    e.preventDefault();
                    
                    var \$button = $(this);
                    var ruleId = \$button.data('rule-id');
                    var roleName = \$button.data('role-name');
                    var discountType = \$button.data('discount-type');
                    var discountValue = \$button.data('discount-value');
                    
                    // Populate modal form
                    $('#edit_rule_id').val(ruleId);
                    $('#edit_role_name').val(roleName);
                    $('#edit_discount_type').val(discountType);
                    $('#edit_discount_value').val(discountValue);
                    
                    // Show modal
                    $('#maxtdesign-rbp-edit-modal').show();
                });
                
                // Close modal when clicking X or Cancel
                $('.maxtdesign-rbp-modal-close, #maxtdesign-rbp-cancel-edit').on('click', function() {
                    $('#maxtdesign-rbp-edit-modal').hide();
                });
                
                // Close modal when clicking outside
                $(window).on('click', function(e) {
                    if (e.target.id === 'maxtdesign-rbp-edit-modal') {
                        $('#maxtdesign-rbp-edit-modal').hide();
                    }
                });
                
                // Handle edit form submission
                $('#maxtdesign-rbp-edit-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var ruleId = $('#edit_rule_id').val();
                    var discountType = $('#edit_discount_type').val();
                    var discountValue = $('#edit_discount_value').val();
                    
                    if (!discountType || !discountValue) {
                        alert('" . esc_js(__('Please fill in all fields.', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    
                    var \$submitButton = $(this).find('button[type=\"submit\"]');
                    var originalText = \$submitButton.text();
                    \$submitButton.prop('disabled', true).text('" . esc_js(__('Updating...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_edit_global_rule',
                            rule_id: ruleId,
                            discount_type: discountType,
                            discount_value: discountValue,
                            nonce: '" . esc_attr(wp_create_nonce('maxtdesign_rbp_global_rule')) . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Close modal
                                $('#maxtdesign-rbp-edit-modal').hide();
                                
                                // Reload page to show updated data
                                location.reload();
                            } else {
                                alert(response.data || '" . esc_js(__('Error updating global rule.', 'maxtdesign-role-based-pricing')) . "');
                            }
                        },
                        error: function() {
                            alert('" . esc_js(__('Error updating global rule.', 'maxtdesign-role-based-pricing')) . "');
                        },
                        complete: function() {
                            \$submitButton.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Cache Management JavaScript
                var cacheNonce = '" . esc_attr(wp_create_nonce('maxtdesign_rbp_cache_action')) . "';
                
                // Clear all cache
                $('#maxtdesign-rbp-clear-all-cache').on('click', function() {
                    if (!confirm('" . esc_js(__('Are you sure you want to clear all cache?', 'maxtdesign-role-based-pricing')) . "')) return;
                    
                    var \$button = $(this);
                    \$button.prop('disabled', true).text('" . esc_js(__('Clearing...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_clear_cache',
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showCacheMessage(response.data.message, 'success');
                                updateCacheStatus();
                            } else {
                                showCacheMessage(response.data || '" . esc_js(__('Error clearing cache.', 'maxtdesign-role-based-pricing')) . "', 'error');
                            }
                        },
                        complete: function() {
                            \$button.prop('disabled', false).text('" . esc_js(__('Clear All Cache', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
                
                // Warm cache
                $('#maxtdesign-rbp-warm-cache').on('click', function() {
                    var \$button = $(this);
                    \$button.prop('disabled', true).text('" . esc_js(__('Warming...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_warm_cache',
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showCacheMessage(response.data.message, 'success');
                                updateCacheStatus();
                            } else {
                                showCacheMessage(response.data || '" . esc_js(__('Error warming cache.', 'maxtdesign-role-based-pricing')) . "', 'error');
                            }
                        },
                        complete: function() {
                            \$button.prop('disabled', false).text('" . esc_js(__('Warm Cache', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
                
                // Clear role cache
                $('#maxtdesign-rbp-clear-role-cache').on('click', function() {
                    var roleName = $('#maxtdesign-rbp-clear-role').val();
                    if (!roleName) {
                        alert('" . esc_js(__('Please select a role.', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    
                    var \$button = $(this);
                    \$button.prop('disabled', true).text('" . esc_js(__('Clearing...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_clear_role_cache',
                            role_name: roleName,
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showCacheMessage(response.data.message, 'success');
                                updateCacheStatus();
                            } else {
                                showCacheMessage(response.data || '" . esc_js(__('Error clearing role cache.', 'maxtdesign-role-based-pricing')) . "', 'error');
                            }
                        },
                        complete: function() {
                            \$button.prop('disabled', false).text('" . esc_js(__('Clear Role Cache', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
                
                // Clear product cache
                $('#maxtdesign-rbp-clear-product-cache').on('click', function() {
                    var productId = $('#maxtdesign-rbp-clear-product').val();
                    if (!productId) {
                        alert('" . esc_js(__('Please enter a product ID.', 'maxtdesign-role-based-pricing')) . "');
                        return;
                    }
                    
                    var \$button = $(this);
                    \$button.prop('disabled', true).text('" . esc_js(__('Clearing...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_clear_product_cache',
                            product_id: productId,
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showCacheMessage(response.data.message, 'success');
                                updateCacheStatus();
                            } else {
                                showCacheMessage(response.data || '" . esc_js(__('Error clearing product cache.', 'maxtdesign-role-based-pricing')) . "', 'error');
                            }
                        },
                        complete: function() {
                            \$button.prop('disabled', false).text('" . esc_js(__('Clear Product Cache', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
                
                // Refresh cache status
                $('#maxtdesign-rbp-refresh-cache-status').on('click', function() {
                    updateCacheStatus();
                });
                
                " . (defined('MAXTDESIGN_RBP_PERFORMANCE_MONITORING') && MAXTDESIGN_RBP_PERFORMANCE_MONITORING ? "
                // Database Performance JavaScript - Only load when monitoring is enabled
                // Add missing indexes
                $('#maxtdesign-rbp-add-db-indexes').on('click', function() {
                    if (!confirm('" . esc_js(__('Are you sure you want to add missing database indexes?', 'maxtdesign-role-based-pricing')) . "')) return;
                    
                    var \$button = $(this);
                    \$button.prop('disabled', true).text('" . esc_js(__('Adding...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_add_db_indexes',
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showCacheMessage(response.data.message, 'success');
                                setTimeout(function() {
                                    location.reload(); // Reload to show updated status
                                }, 2000);
                            } else {
                                showCacheMessage(response.data || '" . esc_js(__('Error adding database indexes.', 'maxtdesign-role-based-pricing')) . "', 'error');
                            }
                        },
                        complete: function() {
                            \$button.prop('disabled', false).text('" . esc_js(__('Add Missing Indexes', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
                
                // Refresh database health status
                $('#maxtdesign-rbp-refresh-db-health').on('click', function() {
                    location.reload(); // Simple refresh for now
                });
                
                // Hook Performance JavaScript - Only load when monitoring is enabled
                // Refresh hook statistics
                $('#maxtdesign-rbp-refresh-hook-stats').on('click', function() {
                    var \$button = $(this);
                    \$button.prop('disabled', true).text('" . esc_js(__('Refreshing...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_get_hook_performance',
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showCacheMessage('" . esc_js(__('Hook statistics refreshed successfully.', 'maxtdesign-role-based-pricing')) . "', 'success');
                                setTimeout(function() {
                                    location.reload(); // Reload to show updated stats
                                }, 1000);
                            } else {
                                showCacheMessage(response.data || '" . esc_js(__('Error refreshing hook statistics.', 'maxtdesign-role-based-pricing')) . "', 'error');
                            }
                        },
                        complete: function() {
                            \$button.prop('disabled', false).text('" . esc_js(__('Refresh Statistics', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
                
                // Clear hook statistics
                $('#maxtdesign-rbp-clear-hook-stats').on('click', function() {
                    if (!confirm('" . esc_js(__('Are you sure you want to clear all hook performance statistics?', 'maxtdesign-role-based-pricing')) . "')) return;
                    
                    var \$button = $(this);
                    \$button.prop('disabled', true).text('" . esc_js(__('Clearing...', 'maxtdesign-role-based-pricing')) . "');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_clear_hook_performance',
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showCacheMessage(response.data.message, 'success');
                                setTimeout(function() {
                                    location.reload(); // Reload to show cleared stats
                                }, 1000);
                            } else {
                                showCacheMessage(response.data || '" . esc_js(__('Error clearing hook statistics.', 'maxtdesign-role-based-pricing')) . "', 'error');
                            }
                        },
                        complete: function() {
                            \$button.prop('disabled', false).text('" . esc_js(__('Clear Statistics', 'maxtdesign-role-based-pricing')) . "');
                        }
                    });
                });
                " : "") . "
                
                // Helper functions
                function showCacheMessage(message, type) {
                    var className = type === 'success' ? 'notice-success' : 'notice-error';
                    var notice = '<div class=\"notice ' + className + ' is-dismissible\"><p>' + message + '</p></div>';
                    $('.maxtdesign-rbp-cache-actions').before(notice);
                    
                    // Auto-remove after 5 seconds
                    setTimeout(function() {
                        $('.notice.is-dismissible').fadeOut();
                    }, 5000);
                }
                
                function updateCacheStatus() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'maxtdesign_rbp_get_cache_health',
                            nonce: cacheNonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Update cache status display
                                location.reload(); // Simple refresh for now
                            }
                        }
                    });
                }
            });
        ";
        
        wp_add_inline_script('jquery', $inline_js);
    }

    private function get_role_display_name($role_name) {
        $role_obj = get_role($role_name);
        if ($role_obj) {
            global $wp_roles;
            $role_names = $wp_roles->get_names();
            return $role_names[$role_name] ?? $role_name;
        }
        return $role_name;
    }

    private function render_global_rule_form() {
        $all_roles = $this->core->get_all_roles();
        $global_rules = $this->core->get_all_global_rules();
        
        // Only filter out roles that have ACTIVE global rules
        $used_roles = array();
        foreach ($global_rules as $rule) {
            if ($rule['is_active'] == 1) {
                $used_roles[] = $rule['role_name'];
            }
        }
        
        $role_options = array('' => __('Select a role...', 'maxtdesign-role-based-pricing'));
        foreach ($all_roles as $role_name => $role_data) {
            if (!in_array($role_name, $used_roles)) {
                $role_options[$role_name] = $role_data['display_name'];
            }
        }
        
        if (count($role_options) === 1) {
            echo '<p>' . esc_html__('All available roles already have global pricing rules.', 'maxtdesign-role-based-pricing') . '</p>';
            return;
        }
        
        ?>
        <form id="maxtdesign-rbp-global-rule-form" class="maxtdesign-rbp-global-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="global_role_name"><?php esc_html_e('User Role', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td>
                        <select id="global_role_name" name="role_name" required>
                            <?php foreach ($role_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the user role for this global pricing rule.', 'maxtdesign-role-based-pricing'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="global_discount_type"><?php esc_html_e('Discount Type', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td>
                        <select id="global_discount_type" name="discount_type" required>
                            <option value="percentage"><?php esc_html_e('Percentage', 'maxtdesign-role-based-pricing'); ?></option>
                            <option value="fixed"><?php esc_html_e('Fixed Amount', 'maxtdesign-role-based-pricing'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose whether to apply a percentage or fixed amount discount.', 'maxtdesign-role-based-pricing'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="global_discount_value"><?php esc_html_e('Discount Value', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td>
                        <input type="number" id="global_discount_value" name="discount_value" step="0.01" min="0" required />
                        <p class="description"><?php esc_html_e('Enter the discount value (percentage or amount).', 'maxtdesign-role-based-pricing'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Add Global Rule', 'maxtdesign-role-based-pricing'); ?>" />
            </p>
        </form>
        <?php
    }

    /**
     * Render edit modal for global rules
     */
    private function render_edit_modal() {
        ?>
        <div id="maxtdesign-rbp-edit-modal" class="maxtdesign-rbp-modal" style="display: none;">
            <div class="maxtdesign-rbp-modal-content">
                <div class="maxtdesign-rbp-modal-header">
                    <h3><?php esc_html_e('Edit Global Pricing Rule', 'maxtdesign-role-based-pricing'); ?></h3>
                    <span class="maxtdesign-rbp-modal-close">&times;</span>
                </div>
                <div class="maxtdesign-rbp-modal-body">
                    <form id="maxtdesign-rbp-edit-form">
                        <input type="hidden" id="edit_rule_id" name="rule_id" />
                        
                        <div class="maxtdesign-rbp-form-group">
                            <label for="edit_role_name"><?php esc_html_e('User Role', 'maxtdesign-role-based-pricing'); ?></label>
                            <input type="text" id="edit_role_name" name="role_name" readonly class="maxtdesign-rbp-readonly" />
                            <p class="description"><?php esc_html_e('Role name cannot be changed.', 'maxtdesign-role-based-pricing'); ?></p>
                        </div>
                        
                        <div class="maxtdesign-rbp-form-group">
                            <label for="edit_discount_type"><?php esc_html_e('Discount Type', 'maxtdesign-role-based-pricing'); ?></label>
                            <select id="edit_discount_type" name="discount_type" required>
                                <option value="percentage"><?php esc_html_e('Percentage', 'maxtdesign-role-based-pricing'); ?></option>
                                <option value="fixed"><?php esc_html_e('Fixed Amount', 'maxtdesign-role-based-pricing'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose whether to apply a percentage or fixed amount discount.', 'maxtdesign-role-based-pricing'); ?></p>
                        </div>
                        
                        <div class="maxtdesign-rbp-form-group">
                            <label for="edit_discount_value"><?php esc_html_e('Discount Value', 'maxtdesign-role-based-pricing'); ?></label>
                            <input type="number" id="edit_discount_value" name="discount_value" step="0.01" min="0" required />
                            <p class="description"><?php esc_html_e('Enter the discount value (percentage or amount).', 'maxtdesign-role-based-pricing'); ?></p>
                        </div>
                        
                        <div class="maxtdesign-rbp-modal-footer">
                            <button type="button" class="button" id="maxtdesign-rbp-cancel-edit"><?php esc_html_e('Cancel', 'maxtdesign-role-based-pricing'); ?></button>
                            <button type="submit" class="button button-primary"><?php esc_html_e('Update Rule', 'maxtdesign-role-based-pricing'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_add_global_rule() {
        check_ajax_referer('maxtdesign_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $role_name = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field(wp_unslash($_POST['discount_type'])) : '';
        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        
        if (empty($role_name) || empty($discount_type) || $discount_value <= 0) {
            wp_send_json_error(__('Please fill in all fields with valid values.', 'maxtdesign-role-based-pricing'));
        }
        
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxtdesign-role-based-pricing'));
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxtdesign-role-based-pricing'));
        }
        
        $rule_data = array(
            'role_name' => $role_name,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'is_active' => 1
        );
        
        $rule_id = $this->core->create_global_rule($rule_data);
        
        if ($rule_id) {
            do_action('maxtdesign_rbp_after_global_rule_created', $rule_id, $rule_data);
            wp_send_json_success(__('Global pricing rule added successfully.', 'maxtdesign-role-based-pricing'));
        } else {
            wp_send_json_error(__('Failed to add global pricing rule.', 'maxtdesign-role-based-pricing'));
        }
    }

    public function ajax_delete_global_rule() {
        check_ajax_referer('maxtdesign_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $result = $this->core->delete_global_rule($rule_id);
        
        if ($result) {
            do_action('maxtdesign_rbp_after_global_rule_deleted', $rule_id);
            wp_send_json_success(__('Global pricing rule deleted successfully.', 'maxtdesign-role-based-pricing'));
        } else {
            wp_send_json_error(__('Failed to delete global pricing rule.', 'maxtdesign-role-based-pricing'));
        }
    }

    public function ajax_toggle_global_rule() {
        check_ajax_referer('maxtdesign_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $new_status = $this->core->toggle_global_rule_status($rule_id);
        
        if ($new_status !== false) {
            $status_text = $new_status ? __('Active', 'maxtdesign-role-based-pricing') : __('Inactive', 'maxtdesign-role-based-pricing');
            wp_send_json_success(array(
                'message' => __('Global pricing rule status updated successfully.', 'maxtdesign-role-based-pricing'),
                'new_status' => $new_status,
                'status_text' => $status_text
            ));
        } else {
            wp_send_json_error(__('Failed to update global pricing rule status.', 'maxtdesign-role-based-pricing'));
        }
    }

    /**
     * Create default global pricing rules
     */
    public function create_default_global_rules() {
        // Create default global rules only for existing roles
        $all_roles = $this->core->get_all_roles();
        $default_rules = array();
        
        // Only create rules for roles that actually exist in WordPress
        foreach ($all_roles as $role_name => $role_data) {
            // Skip roles that already have global rules
            $existing_rule = $this->core->get_global_rule($role_name);
            if (!$existing_rule) {
                $default_rules[] = array(
                    'role_name' => $role_name,
                    'discount_type' => 'percentage',
                    'discount_value' => 10.00, // Default 10% discount
                    'is_active' => 1
                );
            }
        }

        $created_count = 0;
        $skipped_count = 0;
        $error_count = 0;

        foreach ($default_rules as $rule_data) {
            // Only create if it doesn't already exist
            $existing_rule = $this->core->get_global_rule($rule_data['role_name']);
            if (!$existing_rule) {
                $result = $this->core->create_global_rule($rule_data);
                if ($result) {
                    $created_count++;
                } else {
                    $error_count++;
                }
            } else {
                $skipped_count++;
            }
        }

        // Clear cache
        $this->core->clear_all_cache();

        if ($error_count > 0) {
            return array(
                'success' => false,
                /* translators: %1$d is the number of rules created, %2$d is the number of rules skipped, %3$d is the number of rules that failed */
                'message' => sprintf(__('Created %1$d rules, skipped %2$d existing rules, but %3$d rules failed to create.', 'maxtdesign-role-based-pricing'), $created_count, $skipped_count, $error_count)
            );
        } else {
            return array(
                'success' => true,
                /* translators: %1$d is the number of rules created, %2$d is the number of rules skipped */
                'message' => sprintf(__('Successfully created %1$d default global pricing rules, skipped %2$d existing rules.', 'maxtdesign-role-based-pricing'), $created_count, $skipped_count)
            );
        }
    }

    /**
     * Render cache management section
     */
    private function render_cache_management_section() {
        $cache_health = $this->core->get_cache_health();
        $cache_logs = get_option('maxtdesign_rbp_cache_logs', array());
        
        echo '<div class="maxtdesign-rbp-cache-status">';
        echo '<h3>' . esc_html__('Cache Status', 'maxtdesign-role-based-pricing') . '</h3>';
        echo '<table class="widefat"><tbody>';
        echo '<tr><td><strong>' . esc_html__('Cache Method', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html(ucfirst($cache_health['method'])) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Object Cache Available', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . ($cache_health['object_cache_available'] ? '<span style="color: green;">' . esc_html__('Yes', 'maxtdesign-role-based-pricing') . '</span>' : '<span style="color: red;">' . esc_html__('No', 'maxtdesign-role-based-pricing') . '</span>') . '</td></tr>';
        
        // Show object cache health status
        $cache_health_status = isset($cache_health['object_cache_healthy']) ? $cache_health['object_cache_healthy'] : false;
        echo '<tr><td><strong>' . esc_html__('Object Cache Health', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . ($cache_health_status ? '<span style="color: green;">' . esc_html__('Healthy', 'maxtdesign-role-based-pricing') . '</span>' : '<span style="color: orange;">' . esc_html__('Unhealthy/Fallback Active', 'maxtdesign-role-based-pricing') . '</span>') . '</td></tr>';
        
        // Show fallback cache status
        $fallback_active = isset($cache_health['fallback_active']) ? $cache_health['fallback_active'] : false;
        echo '<tr><td><strong>' . esc_html__('Fallback Cache Active', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . ($fallback_active ? '<span style="color: orange;">' . esc_html__('Yes', 'maxtdesign-role-based-pricing') . '</span>' : '<span style="color: green;">' . esc_html__('No', 'maxtdesign-role-based-pricing') . '</span>') . '</td></tr>';
        
        echo '<tr><td><strong>' . esc_html__('Estimated Cache Entries', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($cache_health['estimated_entries']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Last Cache Clear', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($cache_health['last_cleared'] ?: esc_html__('Never', 'maxtdesign-role-based-pricing')) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
        
        echo '<div class="maxtdesign-rbp-cache-actions">';
        echo '<h3>' . esc_html__('Cache Actions', 'maxtdesign-role-based-pricing') . '</h3>';
        echo '<p>';
        echo '<button type="button" class="button" id="maxtdesign-rbp-clear-all-cache">' . esc_html__('Clear All Cache', 'maxtdesign-role-based-pricing') . '</button> ';
        echo '<button type="button" class="button" id="maxtdesign-rbp-warm-cache">' . esc_html__('Warm Cache', 'maxtdesign-role-based-pricing') . '</button> ';
        echo '<button type="button" class="button" id="maxtdesign-rbp-refresh-cache-status">' . esc_html__('Refresh Status', 'maxtdesign-role-based-pricing') . '</button>';
        echo '</p>';
        
        echo '<div class="maxtdesign-rbp-selective-cache">';
        echo '<h4>' . esc_html__('Selective Cache Clearing', 'maxtdesign-role-based-pricing') . '</h4>';
        
        // Role-based cache clearing
        $all_roles = $this->core->get_all_roles();
        echo '<p><label for="maxtdesign-rbp-clear-role">' . esc_html__('Clear cache for role:', 'maxtdesign-role-based-pricing') . '</label> ';
        echo '<select id="maxtdesign-rbp-clear-role">';
        echo '<option value="">' . esc_html__('Select a role...', 'maxtdesign-role-based-pricing') . '</option>';
        foreach ($all_roles as $role_name => $role_data) {
            echo '<option value="' . esc_attr($role_name) . '">' . esc_html($role_data['display_name']) . '</option>';
        }
        echo '</select> ';
        echo '<button type="button" class="button" id="maxtdesign-rbp-clear-role-cache">' . esc_html__('Clear Role Cache', 'maxtdesign-role-based-pricing') . '</button></p>';
        
        // Product-based cache clearing
        echo '<p><label for="maxtdesign-rbp-clear-product">' . esc_html__('Clear cache for product ID:', 'maxtdesign-role-based-pricing') . '</label> ';
        echo '<input type="number" id="maxtdesign-rbp-clear-product" placeholder="' . esc_attr__('Enter product ID', 'maxtdesign-role-based-pricing') . '" /> ';
        echo '<button type="button" class="button" id="maxtdesign-rbp-clear-product-cache">' . esc_html__('Clear Product Cache', 'maxtdesign-role-based-pricing') . '</button></p>';
        echo '</div>';
        echo '</div>';
        
        // Cache logs section
        if (!empty($cache_logs) && defined('WP_DEBUG') && WP_DEBUG) {
            echo '<div class="maxtdesign-rbp-cache-logs">';
            echo '<h3>' . esc_html__('Recent Cache Events', 'maxtdesign-role-based-pricing') . '</h3>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Time', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Event', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Details', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            $recent_logs = array_slice(array_reverse($cache_logs), 0, 10);
            foreach ($recent_logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['timestamp']) . '</td>';
                echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $log['event_type']))) . '</td>';
                echo '<td>' . esc_html(implode(', ', array_map(function($k, $v) { return $k . ': ' . $v; }, array_keys($log['data']), $log['data']))) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }
    }

    /**
     * AJAX handler for clearing all cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $this->core->clear_all_cache();
        
        wp_send_json_success(array(
            'message' => __('All cache cleared successfully.', 'maxtdesign-role-based-pricing'),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for clearing role-specific cache
     */
    public function ajax_clear_role_cache() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $role_name = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
        if (empty($role_name)) {
            wp_send_json_error(__('Role name is required.', 'maxtdesign-role-based-pricing'));
        }
        
        $this->core->clear_role_cache($role_name);
        
        wp_send_json_success(array(
            /* translators: %s is the role name */
            'message' => sprintf(__('Cache cleared for role: %s', 'maxtdesign-role-based-pricing'), $role_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for clearing product-specific cache
     */
    public function ajax_clear_product_cache() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if ($product_id <= 0) {
            wp_send_json_error(__('Valid product ID is required.', 'maxtdesign-role-based-pricing'));
        }
        
        $this->core->clear_product_cache($product_id);
        
        wp_send_json_success(array(
            /* translators: %d is the product ID */
            'message' => sprintf(__('Cache cleared for product ID: %d', 'maxtdesign-role-based-pricing'), $product_id),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for cache warming
     */
    public function ajax_warm_cache() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();
        
        // Add explicit validation, unslashing, and sanitization
        $role_names = array();
        if (isset($_POST['role_names']) && is_array($_POST['role_names'])) {
            // Unslash immediately when accessing $_POST per WordPress standards
            $role_names = array_map('sanitize_text_field', wp_unslash($_POST['role_names']));
        }
        
        $warmed_count = $this->core->warm_cache($product_ids, $role_names);
        
        wp_send_json_success(array(
            /* translators: %d is the number of cache entries created */
            'message' => sprintf(__('Cache warmed successfully. %d entries created.', 'maxtdesign-role-based-pricing'), $warmed_count),
            'warmed_count' => $warmed_count,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for getting cache health status
     */
    public function ajax_get_cache_health() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $cache_health = $this->core->get_cache_health();
        
        wp_send_json_success($cache_health);
    }

    /**
     * AJAX handler for editing global rules
     */
    public function ajax_edit_global_rule() {
        check_ajax_referer('maxtdesign_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field(wp_unslash($_POST['discount_type'])) : '';
        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        
        if ($rule_id <= 0) {
            wp_send_json_error(__('Invalid rule ID.', 'maxtdesign-role-based-pricing'));
        }
        
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxtdesign-role-based-pricing'));
        }
        
        if ($discount_value <= 0) {
            wp_send_json_error(__('Discount value must be greater than 0.', 'maxtdesign-role-based-pricing'));
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxtdesign-role-based-pricing'));
        }
        
        $update_data = array(
            'discount_type' => $discount_type,
            'discount_value' => $discount_value
        );
        
        $result = $this->core->update_global_rule($rule_id, $update_data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Global pricing rule updated successfully.', 'maxtdesign-role-based-pricing'),
                'discount_type' => $discount_type,
                'discount_value' => $discount_value
            ));
        } else {
            wp_send_json_error(__('Failed to update global pricing rule.', 'maxtdesign-role-based-pricing'));
        }
    }

    /**
     * AJAX handler for editing product-specific rules
     */
    public function ajax_edit_product_rule() {
        check_ajax_referer('maxtdesign_rbp_add_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field(wp_unslash($_POST['discount_type'])) : '';
        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        
        if ($rule_id <= 0) {
            wp_send_json_error(__('Invalid rule ID.', 'maxtdesign-role-based-pricing'));
        }
        
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxtdesign-role-based-pricing'));
        }
        
        if ($discount_value <= 0) {
            wp_send_json_error(__('Discount value must be greater than 0.', 'maxtdesign-role-based-pricing'));
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxtdesign-role-based-pricing'));
        }
        
        // Get the rule first to get the product ID for cache clearing
        $rules = $this->core->get_rules(array('id' => $rule_id));
        
        if (empty($rules)) {
            wp_send_json_error(__('Rule not found.', 'maxtdesign-role-based-pricing'));
        }
        
        $rule = $rules[0];
        
        // Update the rule using core method
        $update_data = array(
            'discount_type' => $discount_type,
            'discount_value' => $discount_value
        );
        
        // Use core method to update rule (this will handle caching and database operations)
        $result = $this->core->update_rule($rule_id, $update_data);
        
        if ($result !== false) {
            // Clear product cache
            $this->core->clear_product_cache($rule['product_id']);
            
            // Update last cache clear timestamp
            update_option('maxtdesign_rbp_last_cache_clear', current_time('mysql'));
            
            wp_send_json_success(array(
                'message' => __('Product pricing rule updated successfully.', 'maxtdesign-role-based-pricing'),
                'discount_type' => $discount_type,
                'discount_value' => $discount_value
            ));
        } else {
            wp_send_json_error(__('Failed to update product pricing rule.', 'maxtdesign-role-based-pricing'));
        }
    }

    /**
     * Render database performance monitoring section
     */
    private function render_database_performance_section() {
        $db_health = $this->core->check_database_health();
        $db_performance = $this->core->get_database_performance_stats();
        
        echo '<div class="maxtdesign-rbp-db-health">';
        echo '<h3>' . esc_html__('Database Health Status', 'maxtdesign-role-based-pricing') . '</h3>';
        
        $status_class = $db_health['status'] === 'healthy' ? 'notice-success' : 'notice-warning';
        echo '<div class="notice ' . esc_attr($status_class) . ' inline">';
        echo '<p><strong>' . esc_html__('Status:', 'maxtdesign-role-based-pricing') . '</strong> ' . esc_html(ucfirst($db_health['status'])) . '</p>';
        echo '</div>';
        
        if (!empty($db_health['issues'])) {
            echo '<h4>' . esc_html__('Issues Found:', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<ul>';
            foreach ($db_health['issues'] as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
        }
        
        if (!empty($db_health['recommendations'])) {
            echo '<h4>' . esc_html__('Recommendations:', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<ul>';
            foreach ($db_health['recommendations'] as $recommendation) {
                echo '<li>' . esc_html($recommendation) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<h4>' . esc_html__('Index Status:', 'maxtdesign-role-based-pricing') . '</h4>';
        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Table', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Index', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Status', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
        foreach ($db_health['index_status'] as $table => $indexes) {
            foreach ($indexes as $index => $exists) {
                $status = $exists ? '<span style="color: green;">' . esc_html__('Present', 'maxtdesign-role-based-pricing') . '</span>' : '<span style="color: red;">' . esc_html__('Missing', 'maxtdesign-role-based-pricing') . '</span>';
                echo '<tr><td>' . esc_html($table) . '</td><td>' . esc_html($index) . '</td><td>' . wp_kses_post($status) . '</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
        
        echo '<div class="maxtdesign-rbp-db-performance">';
        echo '<h3>' . esc_html__('Query Performance Statistics', 'maxtdesign-role-based-pricing') . '</h3>';
        echo '<table class="widefat"><tbody>';
        echo '<tr><td><strong>' . esc_html__('Total Queries Logged', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['total_queries']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Slow Queries', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['slow_queries']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Average Execution Time', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['average_execution_time']) . 's</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Slowest Query Time', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['slowest_query_time']) . 's</td></tr>';
        echo '</tbody></table>';
        
        if (!empty($db_performance['query_types'])) {
            echo '<h4>' . esc_html__('Query Types:', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<ul>';
            foreach ($db_performance['query_types'] as $type => $count) {
                echo '<li>' . esc_html(ucwords(str_replace('_', ' ', $type))) . ': ' . esc_html($count) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        
        echo '<div class="maxtdesign-rbp-db-actions">';
        echo '<h3>' . esc_html__('Database Actions', 'maxtdesign-role-based-pricing') . '</h3>';
        echo '<p>';
        echo '<button type="button" class="button" id="maxtdesign-rbp-add-db-indexes">' . esc_html__('Add Missing Indexes', 'maxtdesign-role-based-pricing') . '</button> ';
        echo '<button type="button" class="button" id="maxtdesign-rbp-refresh-db-health">' . esc_html__('Refresh Health Status', 'maxtdesign-role-based-pricing') . '</button>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * AJAX handler for getting database health status
     */
    public function ajax_get_db_health() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $db_health = $this->core->check_database_health();
        wp_send_json_success($db_health);
    }

    /**
     * AJAX handler for getting database performance statistics
     */
    public function ajax_get_db_performance() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $db_performance = $this->core->get_database_performance_stats();
        wp_send_json_success($db_performance);
    }

    /**
     * AJAX handler for adding database indexes
     */
    public function ajax_add_db_indexes() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $result = $this->core->add_database_indexes();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Database indexes added successfully.', 'maxtdesign-role-based-pricing'),
                'timestamp' => current_time('mysql')
            ));
        } else {
            wp_send_json_error(__('Failed to add database indexes. Check error logs for details.', 'maxtdesign-role-based-pricing'));
        }
    }

    /**
     * Render hook performance monitoring section
     */
    private function render_hook_performance_section() {
        $hook_stats = $this->core->get_hook_performance_stats();
        $monitoring_enabled = defined('MAXTDESIGN_RBP_PERFORMANCE_MONITORING') && MAXTDESIGN_RBP_PERFORMANCE_MONITORING;
        
        echo '<div class="maxtdesign-rbp-hook-performance">';
        echo '<h3>' . esc_html__('Performance Monitoring Status', 'maxtdesign-role-based-pricing') . '</h3>';
        
        if ($monitoring_enabled) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>' . esc_html__('Status:', 'maxtdesign-role-based-pricing') . '</strong> ' . esc_html__('Enabled', 'maxtdesign-role-based-pricing') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>' . esc_html__('Status:', 'maxtdesign-role-based-pricing') . '</strong> ' . esc_html__('Disabled', 'maxtdesign-role-based-pricing') . '</p>';
            echo '<p>' . esc_html__('To enable performance monitoring, add this to your wp-config.php:', 'maxtdesign-role-based-pricing') . '</p>';
            echo '<code>define(\'MAXTDESIGN_RBP_PERFORMANCE_MONITORING\', true);</code>';
            echo '</div>';
        }
        
        echo '<h4>' . esc_html__('Hook Execution Statistics', 'maxtdesign-role-based-pricing') . '</h4>';
        echo '<table class="widefat"><tbody>';
        echo '<tr><td><strong>' . esc_html__('Total Pages Monitored', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($hook_stats['total_pages']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Last Updated', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($hook_stats['last_updated'] ?: esc_html__('Never', 'maxtdesign-role-based-pricing')) . '</td></tr>';
        echo '</tbody></table>';
        
        if (!empty($hook_stats['most_accessed_pages'])) {
            echo '<h4>' . esc_html__('Most Accessed Pages', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Page ID', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Hook Executions', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            foreach ($hook_stats['most_accessed_pages'] as $page_id => $count) {
                $page_title = get_the_title($page_id) ?: __('Unknown Page', 'maxtdesign-role-based-pricing');
                echo '<tr><td>' . esc_html($page_id) . ' - ' . esc_html($page_title) . '</td><td>' . esc_html($count) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        
        echo '<div class="maxtdesign-rbp-hook-actions">';
        echo '<h4>' . esc_html__('Hook Performance Actions', 'maxtdesign-role-based-pricing') . '</h4>';
        echo '<p>';
        echo '<button type="button" class="button" id="maxtdesign-rbp-refresh-hook-stats">' . esc_html__('Refresh Statistics', 'maxtdesign-role-based-pricing') . '</button> ';
        echo '<button type="button" class="button" id="maxtdesign-rbp-clear-hook-stats">' . esc_html__('Clear Statistics', 'maxtdesign-role-based-pricing') . '</button>';
        echo '</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * AJAX handler for getting hook performance statistics
     */
    public function ajax_get_hook_performance() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $hook_stats = $this->core->get_hook_performance_stats();
        wp_send_json_success($hook_stats);
    }

    /**
     * AJAX handler for clearing hook performance statistics
     */
    public function ajax_clear_hook_performance() {
        check_ajax_referer('maxtdesign_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }
        
        $this->core->clear_hook_performance_stats();
        
        wp_send_json_success(array(
            'message' => __('Hook performance statistics cleared successfully.', 'maxtdesign-role-based-pricing'),
            'timestamp' => current_time('mysql')
        ));
    }
}
