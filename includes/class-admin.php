<?php
/**
 * Admin interface for MaxT Role Based Pricing
 *
 * @package MaxT_RBP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for managing role-based pricing
 */
class MaxT_RBP_Admin {

    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_product_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_ajax_maxt_rbp_add_rule', array($this, 'ajax_add_rule'));
        add_action('wp_ajax_maxt_rbp_delete_rule', array($this, 'ajax_delete_rule'));
        add_action('wp_ajax_maxt_rbp_add_global_rule', array($this, 'ajax_add_global_rule'));
        add_action('wp_ajax_maxt_rbp_delete_global_rule', array($this, 'ajax_delete_global_rule'));
        add_action('wp_ajax_maxt_rbp_toggle_global_rule', array($this, 'ajax_toggle_global_rule'));
        add_action('wp_ajax_maxt_rbp_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_maxt_rbp_clear_role_cache', array($this, 'ajax_clear_role_cache'));
        add_action('wp_ajax_maxt_rbp_clear_product_cache', array($this, 'ajax_clear_product_cache'));
        add_action('wp_ajax_maxt_rbp_warm_cache', array($this, 'ajax_warm_cache'));
        add_action('wp_ajax_maxt_rbp_get_cache_health', array($this, 'ajax_get_cache_health'));
        add_action('wp_ajax_maxt_rbp_edit_global_rule', array($this, 'ajax_edit_global_rule'));
        add_action('wp_ajax_maxt_rbp_edit_product_rule', array($this, 'ajax_edit_product_rule'));
    }

    public function add_admin_menu() {
        add_submenu_page('woocommerce', __('MaxT Role Pricing', 'maxt-rbp'), __('MaxT Role Pricing', 'maxt-rbp'), 'manage_woocommerce', 'maxt-role-pricing', array($this, 'settings_page'));
    }

    public function add_product_meta_box() {
        add_meta_box('maxt-rbp-pricing', __('Role-Based Pricing', 'maxt-rbp'), array($this, 'product_meta_box_content'), 'product', 'normal', 'high');
    }

    public function product_meta_box_content($post) {
        $product_id = $post->ID;
        $existing_rules = $this->core->get_rules(array('product_id' => $product_id));
        $global_rules = $this->core->get_all_global_rules();
        
        echo '<div class="maxt-rbp-product-meta">';
        
        // Show global rules that apply to this product
        $active_global_rules = array_filter($global_rules, function($rule) { return $rule['is_active']; });
        if (!empty($active_global_rules)) {
            echo '<h4>' . esc_html__('Global Pricing Rules (Apply by Default)', 'maxt-rbp') . '</h4>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Role', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Type', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Value', 'maxt-rbp') . '</th><th>' . esc_html__('Status', 'maxt-rbp') . '</th></tr></thead><tbody>';
            foreach ($active_global_rules as $rule) {
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxt-rbp') : __('Fixed Amount', 'maxt-rbp');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                
                // Check if there's a product-specific override
                $has_override = false;
                foreach ($existing_rules as $existing_rule) {
                    if ($existing_rule['role_name'] === $rule['role_name']) {
                        $has_override = true;
                        break;
                    }
                }
                
                $status_display = $has_override ? '<span style="color: orange;">' . __('Overridden', 'maxt-rbp') . '</span>' : '<span style="color: green;">' . __('Active', 'maxt-rbp') . '</span>';
                
                echo '<tr><td>' . esc_html($role_display_name) . '</td><td>' . esc_html($discount_type_display) . '</td><td>' . $discount_value_display . '</td><td>' . $status_display . '</td></tr>';
            }
            echo '</tbody></table><br>';
        }
        
        // Show product-specific rules
        if (!empty($existing_rules)) {
            echo '<h4>' . esc_html__('Product-Specific Pricing Rules (Override Global)', 'maxt-rbp') . '</h4>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Role', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Type', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Value', 'maxt-rbp') . '</th><th>' . esc_html__('Actions', 'maxt-rbp') . '</th></tr></thead><tbody>';
            foreach ($existing_rules as $rule) {
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxt-rbp') : __('Fixed Amount', 'maxt-rbp');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                echo '<tr><td>' . esc_html($role_display_name) . '</td><td>' . esc_html($discount_type_display) . '</td><td>' . $discount_value_display . '</td><td>';
                echo '<button type="button" class="button button-small maxt-rbp-edit-product-rule" data-rule-id="' . esc_attr($rule['id']) . '" data-role-name="' . esc_attr($rule['role_name']) . '" data-discount-type="' . esc_attr($rule['discount_type']) . '" data-discount-value="' . esc_attr($rule['discount_value']) . '">' . esc_html__('Edit', 'maxt-rbp') . '</button> ';
                echo '<a href="#" class="button button-small maxt-rbp-delete-rule" data-rule-id="' . esc_attr($rule['id']) . '">' . esc_html__('Delete', 'maxt-rbp') . '</a>';
                echo '</td></tr>';
            }
            echo '</tbody></table><br>';
        }
        
        echo '<h4>' . esc_html__('Add New Pricing Rule', 'maxt-rbp') . '</h4>';
        
        $all_roles = $this->core->get_all_roles();
        $role_options = array('' => __('Select a role...', 'maxt-rbp'));
        foreach ($all_roles as $role_name => $role_data) {
            $role_options[$role_name] = $role_data['display_name'];
        }
        foreach ($existing_rules as $rule) {
            unset($role_options[$rule['role_name']]);
        }
        
        if (empty($role_options) || count($role_options) === 1) {
            echo '<p>' . esc_html__('All available roles already have pricing rules for this product.', 'maxt-rbp') . '</p>';
        } else {
            woocommerce_wp_select(array('id' => 'maxt_rbp_role_name', 'label' => __('User Role', 'maxt-rbp'), 'options' => $role_options, 'desc_tip' => true, 'description' => __('Select the user role for this pricing rule.', 'maxt-rbp')));
            woocommerce_wp_select(array('id' => 'maxt_rbp_discount_type', 'label' => __('Discount Type', 'maxt-rbp'), 'options' => array('percentage' => __('Percentage', 'maxt-rbp'), 'fixed' => __('Fixed Amount', 'maxt-rbp')), 'desc_tip' => true, 'description' => __('Choose whether to apply a percentage or fixed amount discount.', 'maxt-rbp')));
            woocommerce_wp_text_input(array('id' => 'maxt_rbp_discount_value', 'label' => __('Discount Value', 'maxt-rbp'), 'type' => 'number', 'custom_attributes' => array('step' => '0.01', 'min' => '0'), 'desc_tip' => true, 'description' => __('Enter the discount value (percentage or amount).', 'maxt-rbp')));
            echo '<p class="form-field"><button type="button" class="button button-primary" id="maxt-rbp-add-rule">' . esc_html__('Add Pricing Rule', 'maxt-rbp') . '</button></p>';
        }
        
        echo '</div>';
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#maxt-rbp-add-rule').on('click', function() {
                var roleName = $('#maxt_rbp_role_name').val();
                var discountType = $('#maxt_rbp_discount_type').val();
                var discountValue = $('#maxt_rbp_discount_value').val();
                if (!roleName || !discountType || !discountValue) {
                    alert('<?php esc_js(__('Please fill in all fields.', 'maxt-rbp')); ?>');
                    return;
                }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_add_rule',
                        product_id: <?php echo intval($product_id); ?>,
                        role_name: roleName,
                        discount_type: discountType,
                        discount_value: discountValue,
                        nonce: '<?php echo wp_create_nonce('maxt_rbp_add_rule'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || '<?php esc_js(__('Error adding rule.', 'maxt-rbp')); ?>');
                        }
                    }
                });
            });
            $('.maxt-rbp-delete-rule').on('click', function(e) {
                e.preventDefault();
                if (!confirm('<?php esc_js(__('Are you sure you want to delete this pricing rule?', 'maxt-rbp')); ?>')) return;
                var ruleId = $(this).data('rule-id');
                var $row = $(this).closest('tr');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_delete_rule',
                        rule_id: ruleId,
                        nonce: '<?php echo wp_create_nonce('maxt_rbp_delete_rule'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(function() { $(this).remove(); });
                        } else {
                            alert(response.data || '<?php esc_js(__('Error deleting rule.', 'maxt-rbp')); ?>');
                        }
                    }
                });
            });
            
            // Edit Product Rule functionality
            $('.maxt-rbp-edit-product-rule').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var ruleId = $button.data('rule-id');
                var roleName = $button.data('role-name');
                var discountType = $button.data('discount-type');
                var discountValue = $button.data('discount-value');
                
                // Create a simple prompt-based edit (since we're in product meta box)
                var newDiscountType = prompt('<?php esc_js(__('Discount Type (percentage or fixed):', 'maxt-rbp')); ?>', discountType);
                if (newDiscountType === null) return; // User cancelled
                
                if (!['percentage', 'fixed'].includes(newDiscountType)) {
                    alert('<?php esc_js(__('Invalid discount type. Please enter "percentage" or "fixed".', 'maxt-rbp')); ?>');
                    return;
                }
                
                var newDiscountValue = prompt('<?php esc_js(__('Discount Value:', 'maxt-rbp')); ?>', discountValue);
                if (newDiscountValue === null) return; // User cancelled
                
                newDiscountValue = parseFloat(newDiscountValue);
                if (isNaN(newDiscountValue) || newDiscountValue <= 0) {
                    alert('<?php esc_js(__('Please enter a valid discount value greater than 0.', 'maxt-rbp')); ?>');
                    return;
                }
                
                if (newDiscountType === 'percentage' && newDiscountValue > 100) {
                    alert('<?php esc_js(__('Percentage discount cannot exceed 100%.', 'maxt-rbp')); ?>');
                    return;
                }
                
                // Send AJAX request to update the rule
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_edit_product_rule',
                        rule_id: ruleId,
                        discount_type: newDiscountType,
                        discount_value: newDiscountValue,
                        nonce: '<?php echo wp_create_nonce('maxt_rbp_add_rule'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // Reload to show updated data
                        } else {
                            alert(response.data || '<?php esc_js(__('Error updating product rule.', 'maxt-rbp')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_js(__('Error updating product rule.', 'maxt-rbp')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function settings_page() {
        // Handle cache clearing
        if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field($_GET['_wpnonce']);
            if (wp_verify_nonce($nonce, 'clear_cache') && current_user_can('manage_woocommerce')) {
                $this->core->clear_all_cache();
                echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared successfully.', 'maxt-rbp') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxt-rbp') . '</p></div>';
            }
        }
        
        // Handle cache warming
        if (isset($_GET['action']) && $_GET['action'] === 'warm_cache' && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field($_GET['_wpnonce']);
            if (wp_verify_nonce($nonce, 'warm_cache') && current_user_can('manage_woocommerce')) {
                $warmed_count = $this->core->warm_cache();
                echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Cache warmed successfully. %d entries created.', 'maxt-rbp'), $warmed_count) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxt-rbp') . '</p></div>';
            }
        }
        
        // Handle creating default global rules
        if (isset($_GET['action']) && $_GET['action'] === 'create_default_global_rules' && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field($_GET['_wpnonce']);
            if (wp_verify_nonce($nonce, 'create_default_global_rules') && current_user_can('manage_woocommerce')) {
                $result = $this->create_default_global_rules();
                if ($result['success']) {
                    echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxt-rbp') . '</p></div>';
            }
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete_role' && isset($_GET['role']) && isset($_GET['_wpnonce'])) {
            $role_name = sanitize_text_field($_GET['role']);
            $nonce = sanitize_text_field($_GET['_wpnonce']);
            if (wp_verify_nonce($nonce, 'delete_role_' . $role_name) && current_user_can('manage_woocommerce')) {
                $result = $this->core->delete_custom_role($role_name);
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Role deleted successfully.', 'maxt-rbp') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'maxt-rbp') . '</p></div>';
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

        echo '<div class="wrap"><h1>' . esc_html__('MaxT Role Pricing Settings', 'maxt-rbp') . '</h1>';
        
        // Enhanced Cache Management Section
        echo '<div class="maxt-rbp-settings-section"><h2>' . esc_html__('Cache Management', 'maxt-rbp') . '</h2>';
        $this->render_cache_management_section();
        echo '</div>';
        
        // Add create default global rules button
        echo '<p><a href="?page=maxt-role-pricing&action=create_default_global_rules&_wpnonce=' . wp_create_nonce('create_default_global_rules') . '" class="button button-primary">' . esc_html__('Create Global Rules for All Roles', 'maxt-rbp') . '</a></p>';
        
        // Global Pricing Rules Section
        echo '<div class="maxt-rbp-settings-section"><h2>' . esc_html__('Global Pricing Rules', 'maxt-rbp') . '</h2>';
        echo '<p>' . esc_html__('Global pricing rules apply to all products by default. Product-specific rules will override global rules.', 'maxt-rbp') . '</p>';
        
        $global_rules = $this->core->get_all_global_rules();
        if (!empty($global_rules)) {
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__('Role', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Type', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Value', 'maxt-rbp') . '</th><th>' . esc_html__('Status', 'maxt-rbp') . '</th><th>' . esc_html__('Actions', 'maxt-rbp') . '</th></tr></thead><tbody>';
            foreach ($global_rules as $rule) {
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxt-rbp') : __('Fixed Amount', 'maxt-rbp');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                $status_display = $rule['is_active'] ? '<span style="color: green;">' . __('Active', 'maxt-rbp') . '</span>' : '<span style="color: red;">' . __('Inactive', 'maxt-rbp') . '</span>';
                
                echo '<tr>';
                echo '<td>' . esc_html($role_display_name) . '</td>';
                echo '<td>' . esc_html($discount_type_display) . '</td>';
                echo '<td>' . $discount_value_display . '</td>';
                echo '<td>' . $status_display . '</td>';
                echo '<td>';
                echo '<button type="button" class="button button-small maxt-rbp-edit-global-rule" data-rule-id="' . esc_attr($rule['id']) . '" data-role-name="' . esc_attr($rule['role_name']) . '" data-discount-type="' . esc_attr($rule['discount_type']) . '" data-discount-value="' . esc_attr($rule['discount_value']) . '">' . esc_html__('Edit', 'maxt-rbp') . '</button> ';
                echo '<button type="button" class="button button-small maxt-rbp-toggle-global-rule" data-rule-id="' . esc_attr($rule['id']) . '" data-current-status="' . esc_attr($rule['is_active']) . '">';
                echo $rule['is_active'] ? esc_html__('Deactivate', 'maxt-rbp') : esc_html__('Activate', 'maxt-rbp');
                echo '</button> ';
                echo '<button type="button" class="button button-small maxt-rbp-delete-global-rule" data-rule-id="' . esc_attr($rule['id']) . '">' . esc_html__('Delete', 'maxt-rbp') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table><br>';
        } else {
            echo '<p>' . esc_html__('No global pricing rules have been created yet.', 'maxt-rbp') . '</p>';
        }
        
        echo '<h3>' . esc_html__('Add Global Pricing Rule', 'maxt-rbp') . '</h3>';
        $this->render_global_rule_form();
        echo '</div>';
        
        // Add edit modal
        $this->render_edit_modal();
        
        echo '<div class="maxt-rbp-settings-section"><h2>' . esc_html__('Role Management', 'maxt-rbp') . '</h2>';
        
        $all_roles = $this->core->get_all_roles();
        echo '<h3>' . esc_html__('Current Roles', 'maxt-rbp') . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__('Role Name', 'maxt-rbp') . '</th><th>' . esc_html__('Display Name', 'maxt-rbp') . '</th><th>' . esc_html__('Type', 'maxt-rbp') . '</th><th>' . esc_html__('Users', 'maxt-rbp') . '</th><th>' . esc_html__('Actions', 'maxt-rbp') . '</th></tr></thead><tbody>';
        foreach ($all_roles as $role_name => $role_data) {
            echo '<tr><td><code>' . esc_html($role_name) . '</code></td><td>' . esc_html($role_data['display_name']) . '</td><td>' . ($role_data['is_custom'] ? '<span class="dashicons dashicons-admin-users"></span> ' . esc_html__('Custom', 'maxt-rbp') : '<span class="dashicons dashicons-wordpress"></span> ' . esc_html__('Built-in', 'maxt-rbp')) . '</td><td>' . esc_html($role_data['user_count']) . '</td><td>';
            if ($role_data['is_custom'] && $role_data['user_count'] === 0) {
                echo '<a href="?page=maxt-role-pricing&action=delete_role&role=' . urlencode($role_name) . '&_wpnonce=' . wp_create_nonce('delete_role_' . $role_name) . '" class="button button-small" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this role?', 'maxt-rbp')) . '\')">' . esc_html__('Delete', 'maxt-rbp') . '</a>';
            } else {
                echo '<span class="description">' . esc_html__('No actions available', 'maxt-rbp') . '</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        
        echo '<h3>' . esc_html__('Create New Role', 'maxt-rbp') . '</h3>';
        echo $this->render_role_creation_form();
        echo '</div>';
        
        $all_rules = $this->core->get_rules();
        echo '<div class="maxt-rbp-settings-section"><h2>' . esc_html__('Pricing Rules Overview', 'maxt-rbp') . '</h2>';
        if (empty($all_rules)) {
            echo '<p>' . esc_html__('No pricing rules have been created yet.', 'maxt-rbp') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__('Product', 'maxt-rbp') . '</th><th>' . esc_html__('Role', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Type', 'maxt-rbp') . '</th><th>' . esc_html__('Discount Value', 'maxt-rbp') . '</th><th>' . esc_html__('Created', 'maxt-rbp') . '</th></tr></thead><tbody>';
            foreach ($all_rules as $rule) {
                $product = wc_get_product($rule['product_id']);
                $product_name = $product ? $product->get_name() : __('Product not found', 'maxt-rbp');
                $role_display_name = $this->get_role_display_name($rule['role_name']);
                $discount_type_display = $rule['discount_type'] === 'percentage' ? __('Percentage', 'maxt-rbp') : __('Fixed Amount', 'maxt-rbp');
                $discount_value_display = $rule['discount_type'] === 'percentage' ? $rule['discount_value'] . '%' : wc_price($rule['discount_value']);
                echo '<tr><td>' . esc_html($product_name) . '</td><td>' . esc_html($role_display_name) . '</td><td>' . esc_html($discount_type_display) . '</td><td>' . $discount_value_display . '</td><td>' . esc_html(date_i18n(get_option('date_format'), strtotime($rule['created_at']))) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';
        
        // Add JavaScript for global rules and cache management
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Global rule form submission
            $('#maxt-rbp-global-rule-form').on('submit', function(e) {
                e.preventDefault();
                
                var roleName = $('#global_role_name').val();
                var discountType = $('#global_discount_type').val();
                var discountValue = $('#global_discount_value').val();
                
                if (!roleName || !discountType || !discountValue) {
                    alert('<?php esc_js(__('Please fill in all fields.', 'maxt-rbp')); ?>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_add_global_rule',
                        role_name: roleName,
                        discount_type: discountType,
                        discount_value: discountValue,
                        nonce: '<?php echo wp_create_nonce('maxt_rbp_global_rule'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || '<?php esc_js(__('Error adding global rule.', 'maxt-rbp')); ?>');
                        }
                    }
                });
            });
            
            // Delete global rule
            $('.maxt-rbp-delete-global-rule').on('click', function(e) {
                e.preventDefault();
                if (!confirm('<?php esc_js(__('Are you sure you want to delete this global pricing rule?', 'maxt-rbp')); ?>')) return;
                
                var ruleId = $(this).data('rule-id');
                var $row = $(this).closest('tr');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_delete_global_rule',
                        rule_id: ruleId,
                        nonce: '<?php echo wp_create_nonce('maxt_rbp_global_rule'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(function() { $(this).remove(); });
                        } else {
                            alert(response.data || '<?php esc_js(__('Error deleting global rule.', 'maxt-rbp')); ?>');
                        }
                    }
                });
            });
            
            // Toggle global rule status
            $('.maxt-rbp-toggle-global-rule').on('click', function(e) {
                e.preventDefault();
                
                var ruleId = $(this).data('rule-id');
                var $button = $(this);
                var $row = $button.closest('tr');
                var $statusCell = $row.find('td:nth-child(4)');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_toggle_global_rule',
                        rule_id: ruleId,
                        nonce: '<?php echo wp_create_nonce('maxt_rbp_global_rule'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var newStatus = response.data.new_status;
                            var statusText = response.data.status_text;
                            var buttonText = newStatus ? '<?php esc_js(__('Deactivate', 'maxt-rbp')); ?>' : '<?php esc_js(__('Activate', 'maxt-rbp')); ?>';
                            
                            $statusCell.html('<span style="color: ' + (newStatus ? 'green' : 'red') + ';">' + statusText + '</span>');
                            $button.text(buttonText).data('current-status', newStatus);
                        } else {
                            alert(response.data || '<?php esc_js(__('Error updating global rule status.', 'maxt-rbp')); ?>');
                        }
                    }
                });
            });
            
            // Edit Global Rule functionality
            $('.maxt-rbp-edit-global-rule').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var ruleId = $button.data('rule-id');
                var roleName = $button.data('role-name');
                var discountType = $button.data('discount-type');
                var discountValue = $button.data('discount-value');
                
                // Populate modal form
                $('#edit_rule_id').val(ruleId);
                $('#edit_role_name').val(roleName);
                $('#edit_discount_type').val(discountType);
                $('#edit_discount_value').val(discountValue);
                
                // Show modal
                $('#maxt-rbp-edit-modal').show();
            });
            
            // Close modal when clicking X or Cancel
            $('.maxt-rbp-modal-close, #maxt-rbp-cancel-edit').on('click', function() {
                $('#maxt-rbp-edit-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(e) {
                if (e.target.id === 'maxt-rbp-edit-modal') {
                    $('#maxt-rbp-edit-modal').hide();
                }
            });
            
            // Handle edit form submission
            $('#maxt-rbp-edit-form').on('submit', function(e) {
                e.preventDefault();
                
                var ruleId = $('#edit_rule_id').val();
                var discountType = $('#edit_discount_type').val();
                var discountValue = $('#edit_discount_value').val();
                
                if (!discountType || !discountValue) {
                    alert('<?php esc_js(__('Please fill in all fields.', 'maxt-rbp')); ?>');
                    return;
                }
                
                var $submitButton = $(this).find('button[type="submit"]');
                var originalText = $submitButton.text();
                $submitButton.prop('disabled', true).text('<?php esc_js(__('Updating...', 'maxt-rbp')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_edit_global_rule',
                        rule_id: ruleId,
                        discount_type: discountType,
                        discount_value: discountValue,
                        nonce: '<?php echo wp_create_nonce('maxt_rbp_global_rule'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Close modal
                            $('#maxt-rbp-edit-modal').hide();
                            
                            // Reload page to show updated data
                            location.reload();
                        } else {
                            alert(response.data || '<?php esc_js(__('Error updating global rule.', 'maxt-rbp')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_js(__('Error updating global rule.', 'maxt-rbp')); ?>');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Cache Management JavaScript
            var cacheNonce = '<?php echo wp_create_nonce('maxt_rbp_cache_action'); ?>';
            
            // Clear all cache
            $('#maxt-rbp-clear-all-cache').on('click', function() {
                if (!confirm('<?php esc_js(__('Are you sure you want to clear all cache?', 'maxt-rbp')); ?>')) return;
                
                var $button = $(this);
                $button.prop('disabled', true).text('<?php esc_js(__('Clearing...', 'maxt-rbp')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_clear_cache',
                        nonce: cacheNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showCacheMessage(response.data.message, 'success');
                            updateCacheStatus();
                        } else {
                            showCacheMessage(response.data || '<?php esc_js(__('Error clearing cache.', 'maxt-rbp')); ?>', 'error');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_js(__('Clear All Cache', 'maxt-rbp')); ?>');
                    }
                });
            });
            
            // Warm cache
            $('#maxt-rbp-warm-cache').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('<?php esc_js(__('Warming...', 'maxt-rbp')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_warm_cache',
                        nonce: cacheNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showCacheMessage(response.data.message, 'success');
                            updateCacheStatus();
                        } else {
                            showCacheMessage(response.data || '<?php esc_js(__('Error warming cache.', 'maxt-rbp')); ?>', 'error');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_js(__('Warm Cache', 'maxt-rbp')); ?>');
                    }
                });
            });
            
            // Clear role cache
            $('#maxt-rbp-clear-role-cache').on('click', function() {
                var roleName = $('#maxt-rbp-clear-role').val();
                if (!roleName) {
                    alert('<?php esc_js(__('Please select a role.', 'maxt-rbp')); ?>');
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('<?php esc_js(__('Clearing...', 'maxt-rbp')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_clear_role_cache',
                        role_name: roleName,
                        nonce: cacheNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showCacheMessage(response.data.message, 'success');
                            updateCacheStatus();
                        } else {
                            showCacheMessage(response.data || '<?php esc_js(__('Error clearing role cache.', 'maxt-rbp')); ?>', 'error');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_js(__('Clear Role Cache', 'maxt-rbp')); ?>');
                    }
                });
            });
            
            // Clear product cache
            $('#maxt-rbp-clear-product-cache').on('click', function() {
                var productId = $('#maxt-rbp-clear-product').val();
                if (!productId) {
                    alert('<?php esc_js(__('Please enter a product ID.', 'maxt-rbp')); ?>');
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('<?php esc_js(__('Clearing...', 'maxt-rbp')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'maxt_rbp_clear_product_cache',
                        product_id: productId,
                        nonce: cacheNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showCacheMessage(response.data.message, 'success');
                            updateCacheStatus();
                        } else {
                            showCacheMessage(response.data || '<?php esc_js(__('Error clearing product cache.', 'maxt-rbp')); ?>', 'error');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_js(__('Clear Product Cache', 'maxt-rbp')); ?>');
                    }
                });
            });
            
            // Refresh cache status
            $('#maxt-rbp-refresh-cache-status').on('click', function() {
                updateCacheStatus();
            });
            
            // Helper functions
            function showCacheMessage(message, type) {
                var className = type === 'success' ? 'notice-success' : 'notice-error';
                var notice = '<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>';
                $('.maxt-rbp-cache-actions').before(notice);
                
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
                        action: 'maxt_rbp_get_cache_health',
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
        </script>
        <?php
    }

    private function render_role_creation_form() {
        if ($this->core->get_custom_roles_count() >= 3) {
            return '<p>' . sprintf(__('Maximum %d custom roles reached.', 'maxt-rbp'), 3) . '</p>';
        }
        ob_start();
        ?>
        <form method="post" action="" class="maxt-rbp-role-form">
            <?php wp_nonce_field('maxt_rbp_create_role', 'maxt_rbp_role_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="role_name"><?php esc_html_e('Role Name', 'maxt-rbp'); ?></label></th>
                    <td><input type="text" id="role_name" name="role_name" class="regular-text" placeholder="<?php esc_attr_e('e.g., premium', 'maxt-rbp'); ?>" required />
                    <p class="description"><?php esc_html_e('Lowercase letters, numbers, and underscores only. Will be prefixed with "maxt_rbp_".', 'maxt-rbp'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="display_name"><?php esc_html_e('Display Name', 'maxt-rbp'); ?></label></th>
                    <td><input type="text" id="display_name" name="display_name" class="regular-text" placeholder="<?php esc_attr_e('e.g., Premium Customer', 'maxt-rbp'); ?>" required />
                    <p class="description"><?php esc_html_e('Human-readable name for the role.', 'maxt-rbp'); ?></p></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="create_role" class="button button-primary" value="<?php esc_attr_e('Create Role', 'maxt-rbp'); ?>" /></p>
        </form>
        <?php
        return ob_get_clean();
    }

    private function handle_role_creation() {
        if (!isset($_POST['create_role']) || !isset($_POST['maxt_rbp_role_nonce'])) {
            return array('success' => false, 'message' => '');
        }
        if (!wp_verify_nonce($_POST['maxt_rbp_role_nonce'], 'maxt_rbp_create_role')) {
            return array('success' => false, 'message' => __('Security check failed.', 'maxt-rbp'));
        }
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => __('Insufficient permissions.', 'maxt-rbp'));
        }
        $role_name = sanitize_text_field($_POST['role_name']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $result = $this->core->create_custom_role($role_name, $display_name);
        if (is_wp_error($result)) {
            return array('success' => false, 'message' => $result->get_error_message());
        }
        return array('success' => true, 'message' => __('Role created successfully.', 'maxt-rbp'));
    }

    public function ajax_add_rule() {
        check_ajax_referer('maxt_rbp_add_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        $product_id = intval($_POST['product_id']);
        $role_name = sanitize_text_field($_POST['role_name']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        
        if (empty($role_name) || empty($discount_type) || $discount_value <= 0) {
            wp_send_json_error(__('Please fill in all fields with valid values.', 'maxt-rbp'));
        }
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxt-rbp'));
        }
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxt-rbp'));
        }
        
        $rule_data = array('role_name' => $role_name, 'product_id' => $product_id, 'discount_type' => $discount_type, 'discount_value' => $discount_value);
        $rule_id = $this->core->create_rule($rule_data);
        
        if ($rule_id) {
            $this->core->clear_product_cache($product_id);
            do_action('maxt_rbp_after_rule_created', $rule_id, $rule_data);
            wp_send_json_success(__('Pricing rule added successfully.', 'maxt-rbp'));
        } else {
            wp_send_json_error(__('Failed to add pricing rule. Please check your input values.', 'maxt-rbp'));
        }
    }

    public function ajax_delete_rule() {
        check_ajax_referer('maxt_rbp_delete_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        $rule_id = intval($_POST['rule_id']);
        $rule = $this->core->get_rules(array('id' => $rule_id));
        $rule = !empty($rule) ? $rule[0] : null;
        
        $result = $this->core->delete_rule($rule_id);
        
        if ($result && $rule) {
            $this->core->clear_product_cache($rule['product_id']);
            do_action('maxt_rbp_after_rule_deleted', $rule_id, $rule);
            wp_send_json_success(__('Pricing rule deleted successfully.', 'maxt-rbp'));
        } else {
            wp_send_json_error(__('Failed to delete pricing rule. Rule may not exist.', 'maxt-rbp'));
        }
    }

    public function admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php', 'woocommerce_page_maxt-role-pricing'))) {
            wp_enqueue_script('jquery');
            
            // Enqueue admin CSS for cache management page
            if ($hook === 'woocommerce_page_maxt-role-pricing') {
                wp_enqueue_style('maxt-rbp-admin', MAXT_RBP_PLUGIN_URL . 'assets/css/admin.css', array(), MAXT_RBP_VERSION);
            }
        }
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
        
        $role_options = array('' => __('Select a role...', 'maxt-rbp'));
        foreach ($all_roles as $role_name => $role_data) {
            if (!in_array($role_name, $used_roles)) {
                $role_options[$role_name] = $role_data['display_name'];
            }
        }
        
        if (count($role_options) === 1) {
            echo '<p>' . esc_html__('All available roles already have global pricing rules.', 'maxt-rbp') . '</p>';
            return;
        }
        
        ob_start();
        ?>
        <form id="maxt-rbp-global-rule-form" class="maxt-rbp-global-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="global_role_name"><?php esc_html_e('User Role', 'maxt-rbp'); ?></label></th>
                    <td>
                        <select id="global_role_name" name="role_name" required>
                            <?php foreach ($role_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the user role for this global pricing rule.', 'maxt-rbp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="global_discount_type"><?php esc_html_e('Discount Type', 'maxt-rbp'); ?></label></th>
                    <td>
                        <select id="global_discount_type" name="discount_type" required>
                            <option value="percentage"><?php esc_html_e('Percentage', 'maxt-rbp'); ?></option>
                            <option value="fixed"><?php esc_html_e('Fixed Amount', 'maxt-rbp'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose whether to apply a percentage or fixed amount discount.', 'maxt-rbp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="global_discount_value"><?php esc_html_e('Discount Value', 'maxt-rbp'); ?></label></th>
                    <td>
                        <input type="number" id="global_discount_value" name="discount_value" step="0.01" min="0" required />
                        <p class="description"><?php esc_html_e('Enter the discount value (percentage or amount).', 'maxt-rbp'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Add Global Rule', 'maxt-rbp'); ?>" />
            </p>
        </form>
        <?php
        echo ob_get_clean();
    }

    /**
     * Render edit modal for global rules
     */
    private function render_edit_modal() {
        ?>
        <div id="maxt-rbp-edit-modal" class="maxt-rbp-modal" style="display: none;">
            <div class="maxt-rbp-modal-content">
                <div class="maxt-rbp-modal-header">
                    <h3><?php esc_html_e('Edit Global Pricing Rule', 'maxt-rbp'); ?></h3>
                    <span class="maxt-rbp-modal-close">&times;</span>
                </div>
                <div class="maxt-rbp-modal-body">
                    <form id="maxt-rbp-edit-form">
                        <input type="hidden" id="edit_rule_id" name="rule_id" />
                        
                        <div class="maxt-rbp-form-group">
                            <label for="edit_role_name"><?php esc_html_e('User Role', 'maxt-rbp'); ?></label>
                            <input type="text" id="edit_role_name" name="role_name" readonly class="maxt-rbp-readonly" />
                            <p class="description"><?php esc_html_e('Role name cannot be changed.', 'maxt-rbp'); ?></p>
                        </div>
                        
                        <div class="maxt-rbp-form-group">
                            <label for="edit_discount_type"><?php esc_html_e('Discount Type', 'maxt-rbp'); ?></label>
                            <select id="edit_discount_type" name="discount_type" required>
                                <option value="percentage"><?php esc_html_e('Percentage', 'maxt-rbp'); ?></option>
                                <option value="fixed"><?php esc_html_e('Fixed Amount', 'maxt-rbp'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose whether to apply a percentage or fixed amount discount.', 'maxt-rbp'); ?></p>
                        </div>
                        
                        <div class="maxt-rbp-form-group">
                            <label for="edit_discount_value"><?php esc_html_e('Discount Value', 'maxt-rbp'); ?></label>
                            <input type="number" id="edit_discount_value" name="discount_value" step="0.01" min="0" required />
                            <p class="description"><?php esc_html_e('Enter the discount value (percentage or amount).', 'maxt-rbp'); ?></p>
                        </div>
                        
                        <div class="maxt-rbp-modal-footer">
                            <button type="button" class="button" id="maxt-rbp-cancel-edit"><?php esc_html_e('Cancel', 'maxt-rbp'); ?></button>
                            <button type="submit" class="button button-primary"><?php esc_html_e('Update Rule', 'maxt-rbp'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_add_global_rule() {
        check_ajax_referer('maxt_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        
        if (empty($role_name) || empty($discount_type) || $discount_value <= 0) {
            wp_send_json_error(__('Please fill in all fields with valid values.', 'maxt-rbp'));
        }
        
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxt-rbp'));
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxt-rbp'));
        }
        
        $rule_data = array(
            'role_name' => $role_name,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'is_active' => 1
        );
        
        $rule_id = $this->core->create_global_rule($rule_data);
        
        if ($rule_id) {
            do_action('maxt_rbp_after_global_rule_created', $rule_id, $rule_data);
            wp_send_json_success(__('Global pricing rule added successfully.', 'maxt-rbp'));
        } else {
            wp_send_json_error(__('Failed to add global pricing rule.', 'maxt-rbp'));
        }
    }

    public function ajax_delete_global_rule() {
        check_ajax_referer('maxt_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $rule_id = intval($_POST['rule_id']);
        $result = $this->core->delete_global_rule($rule_id);
        
        if ($result) {
            do_action('maxt_rbp_after_global_rule_deleted', $rule_id);
            wp_send_json_success(__('Global pricing rule deleted successfully.', 'maxt-rbp'));
        } else {
            wp_send_json_error(__('Failed to delete global pricing rule.', 'maxt-rbp'));
        }
    }

    public function ajax_toggle_global_rule() {
        check_ajax_referer('maxt_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $rule_id = intval($_POST['rule_id']);
        $new_status = $this->core->toggle_global_rule_status($rule_id);
        
        if ($new_status !== false) {
            $status_text = $new_status ? __('Active', 'maxt-rbp') : __('Inactive', 'maxt-rbp');
            wp_send_json_success(array(
                'message' => __('Global pricing rule status updated successfully.', 'maxt-rbp'),
                'new_status' => $new_status,
                'status_text' => $status_text
            ));
        } else {
            wp_send_json_error(__('Failed to update global pricing rule status.', 'maxt-rbp'));
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
                'message' => sprintf(__('Created %d rules, skipped %d existing rules, but %d rules failed to create.', 'maxt-rbp'), $created_count, $skipped_count, $error_count)
            );
        } else {
            return array(
                'success' => true,
                'message' => sprintf(__('Successfully created %d default global pricing rules, skipped %d existing rules.', 'maxt-rbp'), $created_count, $skipped_count)
            );
        }
    }

    /**
     * Render cache management section
     */
    private function render_cache_management_section() {
        $cache_health = $this->core->get_cache_health();
        $cache_logs = get_option('maxt_rbp_cache_logs', array());
        
        echo '<div class="maxt-rbp-cache-status">';
        echo '<h3>' . esc_html__('Cache Status', 'maxt-rbp') . '</h3>';
        echo '<table class="widefat"><tbody>';
        echo '<tr><td><strong>' . esc_html__('Cache Method', 'maxt-rbp') . '</strong></td><td>' . esc_html(ucfirst($cache_health['method'])) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Object Cache Available', 'maxt-rbp') . '</strong></td><td>' . ($cache_health['object_cache_available'] ? '<span style="color: green;">' . esc_html__('Yes', 'maxt-rbp') . '</span>' : '<span style="color: red;">' . esc_html__('No', 'maxt-rbp') . '</span>') . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Estimated Cache Entries', 'maxt-rbp') . '</strong></td><td>' . esc_html($cache_health['estimated_entries']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Last Cache Clear', 'maxt-rbp') . '</strong></td><td>' . esc_html($cache_health['last_cleared'] ?: esc_html__('Never', 'maxt-rbp')) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
        
        echo '<div class="maxt-rbp-cache-actions">';
        echo '<h3>' . esc_html__('Cache Actions', 'maxt-rbp') . '</h3>';
        echo '<p>';
        echo '<button type="button" class="button" id="maxt-rbp-clear-all-cache">' . esc_html__('Clear All Cache', 'maxt-rbp') . '</button> ';
        echo '<button type="button" class="button" id="maxt-rbp-warm-cache">' . esc_html__('Warm Cache', 'maxt-rbp') . '</button> ';
        echo '<button type="button" class="button" id="maxt-rbp-refresh-cache-status">' . esc_html__('Refresh Status', 'maxt-rbp') . '</button>';
        echo '</p>';
        
        echo '<div class="maxt-rbp-selective-cache">';
        echo '<h4>' . esc_html__('Selective Cache Clearing', 'maxt-rbp') . '</h4>';
        
        // Role-based cache clearing
        $all_roles = $this->core->get_all_roles();
        echo '<p><label for="maxt-rbp-clear-role">' . esc_html__('Clear cache for role:', 'maxt-rbp') . '</label> ';
        echo '<select id="maxt-rbp-clear-role">';
        echo '<option value="">' . esc_html__('Select a role...', 'maxt-rbp') . '</option>';
        foreach ($all_roles as $role_name => $role_data) {
            echo '<option value="' . esc_attr($role_name) . '">' . esc_html($role_data['display_name']) . '</option>';
        }
        echo '</select> ';
        echo '<button type="button" class="button" id="maxt-rbp-clear-role-cache">' . esc_html__('Clear Role Cache', 'maxt-rbp') . '</button></p>';
        
        // Product-based cache clearing
        echo '<p><label for="maxt-rbp-clear-product">' . esc_html__('Clear cache for product ID:', 'maxt-rbp') . '</label> ';
        echo '<input type="number" id="maxt-rbp-clear-product" placeholder="' . esc_attr__('Enter product ID', 'maxt-rbp') . '" /> ';
        echo '<button type="button" class="button" id="maxt-rbp-clear-product-cache">' . esc_html__('Clear Product Cache', 'maxt-rbp') . '</button></p>';
        echo '</div>';
        echo '</div>';
        
        // Cache logs section
        if (!empty($cache_logs) && defined('WP_DEBUG') && WP_DEBUG) {
            echo '<div class="maxt-rbp-cache-logs">';
            echo '<h3>' . esc_html__('Recent Cache Events', 'maxt-rbp') . '</h3>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Time', 'maxt-rbp') . '</th><th>' . esc_html__('Event', 'maxt-rbp') . '</th><th>' . esc_html__('Details', 'maxt-rbp') . '</th></tr></thead><tbody>';
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
        check_ajax_referer('maxt_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $this->core->clear_all_cache();
        
        wp_send_json_success(array(
            'message' => __('All cache cleared successfully.', 'maxt-rbp'),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for clearing role-specific cache
     */
    public function ajax_clear_role_cache() {
        check_ajax_referer('maxt_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        if (empty($role_name)) {
            wp_send_json_error(__('Role name is required.', 'maxt-rbp'));
        }
        
        $this->core->clear_role_cache($role_name);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cache cleared for role: %s', 'maxt-rbp'), $role_name),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for clearing product-specific cache
     */
    public function ajax_clear_product_cache() {
        check_ajax_referer('maxt_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $product_id = intval($_POST['product_id']);
        if ($product_id <= 0) {
            wp_send_json_error(__('Valid product ID is required.', 'maxt-rbp'));
        }
        
        $this->core->clear_product_cache($product_id);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cache cleared for product ID: %d', 'maxt-rbp'), $product_id),
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for cache warming
     */
    public function ajax_warm_cache() {
        check_ajax_referer('maxt_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();
        $role_names = isset($_POST['role_names']) ? array_map('sanitize_text_field', (array)$_POST['role_names']) : array();
        
        $warmed_count = $this->core->warm_cache($product_ids, $role_names);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cache warmed successfully. %d entries created.', 'maxt-rbp'), $warmed_count),
            'warmed_count' => $warmed_count,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX handler for getting cache health status
     */
    public function ajax_get_cache_health() {
        check_ajax_referer('maxt_rbp_cache_action', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $cache_health = $this->core->get_cache_health();
        
        wp_send_json_success($cache_health);
    }

    /**
     * AJAX handler for editing global rules
     */
    public function ajax_edit_global_rule() {
        check_ajax_referer('maxt_rbp_global_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $rule_id = intval($_POST['rule_id']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        
        if ($rule_id <= 0) {
            wp_send_json_error(__('Invalid rule ID.', 'maxt-rbp'));
        }
        
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxt-rbp'));
        }
        
        if ($discount_value <= 0) {
            wp_send_json_error(__('Discount value must be greater than 0.', 'maxt-rbp'));
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxt-rbp'));
        }
        
        $update_data = array(
            'discount_type' => $discount_type,
            'discount_value' => $discount_value
        );
        
        $result = $this->core->update_global_rule($rule_id, $update_data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Global pricing rule updated successfully.', 'maxt-rbp'),
                'discount_type' => $discount_type,
                'discount_value' => $discount_value
            ));
        } else {
            wp_send_json_error(__('Failed to update global pricing rule.', 'maxt-rbp'));
        }
    }

    /**
     * AJAX handler for editing product-specific rules
     */
    public function ajax_edit_product_rule() {
        check_ajax_referer('maxt_rbp_add_rule', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'maxt-rbp'));
        }
        
        $rule_id = intval($_POST['rule_id']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        
        if ($rule_id <= 0) {
            wp_send_json_error(__('Invalid rule ID.', 'maxt-rbp'));
        }
        
        if (!in_array($discount_type, array('percentage', 'fixed'))) {
            wp_send_json_error(__('Invalid discount type.', 'maxt-rbp'));
        }
        
        if ($discount_value <= 0) {
            wp_send_json_error(__('Discount value must be greater than 0.', 'maxt-rbp'));
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            wp_send_json_error(__('Percentage discount cannot exceed 100%.', 'maxt-rbp'));
        }
        
        // Get the rule first to get the product ID for cache clearing
        global $wpdb;
        $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}maxt_rbp_rules WHERE id = %d", $rule_id), ARRAY_A);
        
        if (!$rule) {
            wp_send_json_error(__('Rule not found.', 'maxt-rbp'));
        }
        
        // Update the rule
        $update_data = array(
            'discount_type' => $discount_type,
            'discount_value' => $discount_value
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'maxt_rbp_rules',
            $update_data,
            array('id' => $rule_id),
            array('%s', '%f'),
            array('%d')
        );
        
        if ($result !== false) {
            // Clear product cache
            $this->core->clear_product_cache($rule['product_id']);
            
            // Update last cache clear timestamp
            update_option('maxt_rbp_last_cache_clear', current_time('mysql'));
            
            wp_send_json_success(array(
                'message' => __('Product pricing rule updated successfully.', 'maxt-rbp'),
                'discount_type' => $discount_type,
                'discount_value' => $discount_value
            ));
        } else {
            wp_send_json_error(__('Failed to update product pricing rule.', 'maxt-rbp'));
        }
    }
}
