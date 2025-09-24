<?php
/**
 * Core functionality for MaxT Role Based Pricing
 *
 * @package MaxT_RBP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core class combining database, pricing engine, and role management
 */
class MaxT_RBP_Core {

    private $table_name;
    private $global_table_name;
    private $cache_prefix = 'maxt_rbp_price_';
    private $cache_duration = 86400;
    private $role_prefix = 'maxt_rbp_';
    private $max_custom_roles = 3;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'maxt_rbp_rules';
        $this->global_table_name = $wpdb->prefix . 'maxt_rbp_global_rules';
    }

    // Database methods
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create product-specific rules table
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            role_name varchar(100) NOT NULL,
            product_id bigint(20) DEFAULT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY role_name (role_name),
            KEY product_id (product_id)
        ) $charset_collate;";
        
        // Create global rules table
        $global_sql = "CREATE TABLE {$this->global_table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            role_name varchar(100) NOT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY role_name (role_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result1 = dbDelta($sql);
        $result2 = dbDelta($global_sql);
        
        return $result1 && $result2;
    }

    public function drop_table() {
        global $wpdb;
        $result1 = $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        $result2 = $wpdb->query("DROP TABLE IF EXISTS {$this->global_table_name}");
        return $result1 && $result2;
    }

    public function create_rule($data) {
        global $wpdb;
        if (empty($data['role_name']) || !isset($data['discount_type']) || !isset($data['discount_value'])) {
            return false;
        }
        $rule_data = array(
            'role_name' => sanitize_text_field($data['role_name']),
            'product_id' => isset($data['product_id']) ? intval($data['product_id']) : null,
            'discount_type' => in_array($data['discount_type'], array('percentage', 'fixed')) ? $data['discount_type'] : 'percentage',
            'discount_value' => floatval($data['discount_value']),
        );
        $result = $wpdb->insert($this->table_name, $rule_data);
        return $result === false ? false : $wpdb->insert_id;
    }

    public function get_rules($args = array()) {
        global $wpdb;
        $defaults = array('role_name' => '', 'product_id' => '', 'limit' => 0, 'offset' => 0);
        $args = wp_parse_args($args, $defaults);
        $where_conditions = array();
        $where_values = array();
        if (!empty($args['role_name'])) {
            $where_conditions[] = 'role_name = %s';
            $where_values[] = $args['role_name'];
        }
        if (!empty($args['product_id'])) {
            $where_conditions[] = 'product_id = %d';
            $where_values[] = intval($args['product_id']);
        }
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $limit_clause = $args['limit'] > 0 ? 'LIMIT ' . intval($args['offset']) . ', ' . intval($args['limit']) : '';
        $sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_at DESC {$limit_clause}";
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function delete_rule($rule_id) {
        global $wpdb;
        $rule_id = intval($rule_id);
        if ($rule_id <= 0) return false;
        $result = $wpdb->delete($this->table_name, array('id' => $rule_id), array('%d'));
        return $result !== false;
    }

    // Pricing engine methods
    public function calculate_price($original_price, $product) {
        if (!is_user_logged_in() || !$original_price || $original_price <= 0 || !$product || !$product->exists()) {
            return $original_price;
        }
        $user_role = $this->get_current_user_role();
        if (!$user_role) return $original_price;
        
        // Allow administrators to see role-based pricing for testing
        // if ($user_role === 'administrator') return $original_price;
        
        $cache_key = $this->cache_prefix . $product->get_id() . '_' . $user_role . '_' . md5($original_price);
        $cached_price = get_transient($cache_key);
        
        
        // Use cached price if available (bypass cache in debug mode)
        if ($cached_price !== false && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            return floatval($cached_price);
        }
        
        $pricing_rule = $this->get_pricing_rule($product->get_id(), $user_role);
        
        
        if (!$pricing_rule) {
            set_transient($cache_key, $original_price, $this->cache_duration);
            return $original_price;
        }
        
        $discounted_price = $this->apply_discount($original_price, $pricing_rule);
        $final_price = max(0, $discounted_price);
        $final_price = apply_filters('maxt_rbp_calculate_price', $final_price, $original_price, $product, $user_role, $pricing_rule);
        $cache_duration = apply_filters('maxt_rbp_cache_duration', $this->cache_duration);
        set_transient($cache_key, $final_price, $cache_duration);
        return $final_price;
    }

    private function get_pricing_rule($product_id, $role_name) {
        // First, check for product-specific rules
        $rules = $this->get_rules(array('role_name' => $role_name, 'product_id' => $product_id));
        if (!empty($rules)) {
            return $rules[0];
        }
        
        // If no product-specific rule exists, check for global rules
        $global_rule = $this->get_global_rule($role_name);
        
        return $global_rule;
    }

    private function apply_discount($original_price, $rule) {
        if ($rule['discount_type'] === 'percentage') {
            return $original_price * (1 - ($rule['discount_value'] / 100));
        } else {
            return $original_price - $rule['discount_value'];
        }
    }

    private function get_current_user_role() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        return $user->roles[0] ?? false;
    }

    public function clear_product_cache($product_id) {
        global $wpdb;
        $pattern = $this->cache_prefix . $product_id . '_%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_' . $pattern));
    }

    public function clear_all_cache() {
        global $wpdb;
        $pattern = $this->cache_prefix . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_' . $pattern));
        
    }

    // Role management methods
    public function create_custom_role($role_name, $display_name) {
        if (empty($role_name) || empty($display_name)) {
            return new WP_Error('invalid_input', __('Role name and display name are required.', 'maxt-rbp'));
        }
        $role_name = sanitize_key($role_name);
        $full_role_name = $this->role_prefix . $role_name;
        if (get_role($full_role_name)) {
            return new WP_Error('role_exists', __('Role already exists.', 'maxt-rbp'));
        }
        if ($this->get_custom_roles_count() >= $this->max_custom_roles) {
            return new WP_Error('max_roles_reached', sprintf(__('Maximum %d custom roles allowed.', 'maxt-rbp'), $this->max_custom_roles));
        }
        if (!preg_match('/^[a-z0-9_]+$/', $role_name)) {
            return new WP_Error('invalid_role_name', __('Role name can only contain lowercase letters, numbers, and underscores.', 'maxt-rbp'));
        }
        $result = add_role($full_role_name, $display_name, array('read' => true));
        return $result === null ? new WP_Error('role_creation_failed', __('Failed to create role.', 'maxt-rbp')) : true;
    }

    public function delete_custom_role($role_name) {
        if (empty($role_name) || strpos($role_name, $this->role_prefix) !== 0) {
            return new WP_Error('not_custom_role', __('Can only delete custom roles created by this plugin.', 'maxt-rbp'));
        }
        if (!get_role($role_name)) {
            return new WP_Error('role_not_found', __('Role does not exist.', 'maxt-rbp'));
        }
        $users_with_role = get_users(array('role' => $role_name));
        if (!empty($users_with_role)) {
            return new WP_Error('role_has_users', sprintf(__('Cannot delete role. %d users are assigned to this role.', 'maxt-rbp'), count($users_with_role)));
        }
        $result = remove_role($role_name);
        return !$result ? new WP_Error('role_deletion_failed', __('Failed to delete role.', 'maxt-rbp')) : true;
    }

    public function get_all_roles() {
        global $wp_roles;
        $all_roles = array();
        $roles = $wp_roles->get_names();
        foreach ($roles as $role_name => $display_name) {
            $role_obj = get_role($role_name);
            $is_custom = strpos($role_name, $this->role_prefix) === 0;
            $user_count = count(get_users(array('role' => $role_name)));
            $all_roles[$role_name] = array(
                'name' => $role_name,
                'display_name' => $display_name,
                'is_custom' => $is_custom,
                'is_builtin' => !$is_custom,
                'user_count' => $user_count,
                'capabilities' => $role_obj ? array_keys($role_obj->capabilities) : array(),
            );
        }
        return $all_roles;
    }

    public function get_custom_roles_count() {
        $all_roles = $this->get_all_roles();
        $count = 0;
        foreach ($all_roles as $role_data) {
            if ($role_data['is_custom']) $count++;
        }
        return $count;
    }


    public function remove_all_custom_roles() {
        $custom_roles = $this->get_all_roles();
        foreach ($custom_roles as $role_name => $role_data) {
            if ($role_data['is_custom'] && $role_data['user_count'] === 0) {
                $this->delete_custom_role($role_name);
            }
        }
    }

    // Global pricing rule methods
    public function create_global_rule($data) {
        global $wpdb;
        if (empty($data['role_name']) || !isset($data['discount_type']) || !isset($data['discount_value'])) {
            return false;
        }
        
        $rule_data = array(
            'role_name' => sanitize_text_field($data['role_name']),
            'discount_type' => in_array($data['discount_type'], array('percentage', 'fixed')) ? $data['discount_type'] : 'percentage',
            'discount_value' => floatval($data['discount_value']),
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert functionality
        $sql = "INSERT INTO {$this->global_table_name} (role_name, discount_type, discount_value, is_active) 
                VALUES (%s, %s, %f, %d) 
                ON DUPLICATE KEY UPDATE 
                discount_type = VALUES(discount_type), 
                discount_value = VALUES(discount_value), 
                is_active = VALUES(is_active), 
                updated_at = CURRENT_TIMESTAMP";
        
        $result = $wpdb->query($wpdb->prepare($sql, $rule_data['role_name'], $rule_data['discount_type'], $rule_data['discount_value'], $rule_data['is_active']));
        
        if ($result === false) {
            return false;
        }
        
        // Clear all cache when global rules change
        $this->clear_all_cache();
        
        return $wpdb->insert_id ?: $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->global_table_name} WHERE role_name = %s", $rule_data['role_name']));
    }

    public function get_global_rule($role_name) {
        global $wpdb;
        
        
        $sql = "SELECT * FROM {$this->global_table_name} WHERE role_name = %s AND is_active = 1";
        return $wpdb->get_row($wpdb->prepare($sql, $role_name), ARRAY_A);
    }

    public function get_all_global_rules() {
        global $wpdb;
        $sql = "SELECT * FROM {$this->global_table_name} ORDER BY role_name ASC";
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function update_global_rule($rule_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['discount_type'])) {
            $update_data['discount_type'] = in_array($data['discount_type'], array('percentage', 'fixed')) ? $data['discount_type'] : 'percentage';
            $format[] = '%s';
        }
        
        if (isset($data['discount_value'])) {
            $update_data['discount_value'] = floatval($data['discount_value']);
            $format[] = '%f';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update($this->global_table_name, $update_data, array('id' => intval($rule_id)), $format, array('%d'));
        
        if ($result !== false) {
            $this->clear_all_cache();
        }
        
        return $result !== false;
    }

    public function delete_global_rule($rule_id) {
        global $wpdb;
        $rule_id = intval($rule_id);
        if ($rule_id <= 0) return false;
        
        $result = $wpdb->delete($this->global_table_name, array('id' => $rule_id), array('%d'));
        
        if ($result !== false) {
            $this->clear_all_cache();
        }
        
        return $result !== false;
    }

    public function toggle_global_rule_status($rule_id) {
        global $wpdb;
        $rule_id = intval($rule_id);
        if ($rule_id <= 0) return false;
        
        // Get current status and toggle it
        $current_status = $wpdb->get_var($wpdb->prepare("SELECT is_active FROM {$this->global_table_name} WHERE id = %d", $rule_id));
        if ($current_status === null) return false;
        
        $new_status = $current_status ? 0 : 1;
        $result = $wpdb->update($this->global_table_name, array('is_active' => $new_status), array('id' => $rule_id), array('%d'), array('%d'));
        
        if ($result !== false) {
            $this->clear_all_cache();
        }
        
        return $result !== false ? $new_status : false;
    }
}
