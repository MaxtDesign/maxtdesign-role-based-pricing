<?php
/**
 * Core functionality for WooCommerce Role-Based Pricing
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
    private $cache_method = 'transient';
    private $object_cache_duration = 86400; // 24 hours
    private $transient_cache_duration = 3600; // 1 hour

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'maxt_rbp_rules';
        $this->global_table_name = $wpdb->prefix . 'maxt_rbp_global_rules';
        
        // Initialize cache method preference
        $this->init_cache_method();
    }

    // Database methods
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create product-specific rules table with optimized indexes
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            role_name varchar(100) NOT NULL,
            product_id bigint(20) DEFAULT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_role_name (role_name),
            KEY idx_product_id (product_id),
            KEY idx_role_product (role_name, product_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Create global rules table with optimized indexes
        $global_sql = "CREATE TABLE {$this->global_table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            role_name varchar(100) NOT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_role_name (role_name),
            KEY idx_is_active (is_active),
            KEY idx_role_active (role_name, is_active),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result1 = dbDelta($sql);
        $result2 = dbDelta($global_sql);
        
        return $result1 && $result2;
    }

    public function drop_table() {
        global $wpdb;
        $result1 = $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $this->table_name));
        $result2 = $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $this->global_table_name));
        return $result1 && $result2;
    }

    /**
     * Add database indexes to existing installations
     * Handles migration-safe index addition with proper error handling
     */
    public function add_database_indexes() {
        global $wpdb;
        
        // Check if indexes already exist to prevent duplicate creation
        $version_key = 'maxt_rbp_db_indexes_version';
        $current_version = get_option($version_key, '0');
        $target_version = '1.0.0';
        
        if (version_compare($current_version, $target_version, '>=')) {
            return true; // Indexes already up to date
        }
        
        $indexes_added = 0;
        $errors = array();
        
        // Add indexes to product-specific rules table
        $product_indexes = array(
            'idx_role_name' => 'KEY idx_role_name (role_name)',
            'idx_product_id' => 'KEY idx_product_id (product_id)', 
            'idx_role_product' => 'KEY idx_role_product (role_name, product_id)',
            'idx_created_at' => 'KEY idx_created_at (created_at)'
        );
        
        foreach ($product_indexes as $index_name => $index_sql) {
            if (!$this->index_exists($this->table_name, $index_name)) {
                $result = $wpdb->query($wpdb->prepare("ALTER TABLE %s ADD %s", $this->table_name, $index_sql));
                if ($result !== false) {
                    $indexes_added++;
                } else {
                    $errors[] = "Failed to add index {$index_name} to {$this->table_name}: " . $wpdb->last_error;
                }
            }
        }
        
        // Add indexes to global rules table
        $global_indexes = array(
            'idx_is_active' => 'KEY idx_is_active (is_active)',
            'idx_role_active' => 'KEY idx_role_active (role_name, is_active)',
            'idx_created_at' => 'KEY idx_created_at (created_at)'
        );
        
        foreach ($global_indexes as $index_name => $index_sql) {
            if (!$this->index_exists($this->global_table_name, $index_name)) {
                $result = $wpdb->query($wpdb->prepare("ALTER TABLE %s ADD %s", $this->global_table_name, $index_sql));
                if ($result !== false) {
                    $indexes_added++;
                } else {
                    $errors[] = "Failed to add index {$index_name} to {$this->global_table_name}: " . $wpdb->last_error;
                }
            }
        }
        
        // Update version if successful
        if (empty($errors)) {
            update_option($version_key, $target_version);
            $this->log_database_event('indexes_added', array(
                'indexes_added' => $indexes_added,
                'version' => $target_version
            ));
        } else {
            $this->log_database_event('index_errors', array(
                'errors' => $errors,
                'indexes_added' => $indexes_added
            ));
        }
        
        return empty($errors);
    }

    /**
     * Check if a database index exists
     */
    private function index_exists($table_name, $index_name) {
        global $wpdb;
        
        $sql = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND INDEX_NAME = %s
        ", DB_NAME, $table_name, $index_name);
        
        return $wpdb->get_var($sql) > 0;
    }

    /**
     * Log database events for troubleshooting
     */
    private function log_database_event($event_type, $data) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'data' => $data
        );
        
        $logs = get_option('maxt_rbp_db_logs', array());
        $logs[] = $log_data;
        
        // Keep only last 20 log entries
        if (count($logs) > 20) {
            $logs = array_slice($logs, -20);
        }
        
        update_option('maxt_rbp_db_logs', $logs);
    }

    /**
     * Log query performance for monitoring and troubleshooting
     */
    private function log_query_performance($query_type, $sql, $execution_time, $result_count) {
        // Only log slow queries or in debug mode
        $slow_query_threshold = 0.1; // 100ms
        $enable_logging = (defined('WP_DEBUG') && WP_DEBUG) || $execution_time > $slow_query_threshold;
        
        if (!$enable_logging) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'query_type' => $query_type,
            'sql' => $sql,
            'execution_time' => round($execution_time, 4),
            'result_count' => $result_count,
            'is_slow' => $execution_time > $slow_query_threshold
        );
        
        $logs = get_option('maxt_rbp_query_logs', array());
        $logs[] = $log_data;
        
        // Keep only last 50 log entries
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('maxt_rbp_query_logs', $logs);
        
        // Log slow queries to error log
        if ($log_data['is_slow']) {
            error_log("MaxT RBP Slow Query ({$execution_time}s): {$query_type} - {$sql}");
        }
    }

    /**
     * Get database performance statistics
     */
    public function get_database_performance_stats() {
        $query_logs = get_option('maxt_rbp_query_logs', array());
        $db_logs = get_option('maxt_rbp_db_logs', array());
        
        $stats = array(
            'total_queries' => count($query_logs),
            'slow_queries' => 0,
            'average_execution_time' => 0,
            'slowest_query_time' => 0,
            'query_types' => array(),
            'recent_errors' => array()
        );
        
        if (!empty($query_logs)) {
            $total_time = 0;
            $query_type_counts = array();
            
            foreach ($query_logs as $log) {
                $total_time += $log['execution_time'];
                
                if ($log['is_slow']) {
                    $stats['slow_queries']++;
                }
                
                if ($log['execution_time'] > $stats['slowest_query_time']) {
                    $stats['slowest_query_time'] = $log['execution_time'];
                }
                
                $query_type_counts[$log['query_type']] = ($query_type_counts[$log['query_type']] ?? 0) + 1;
            }
            
            $stats['average_execution_time'] = round($total_time / count($query_logs), 4);
            $stats['query_types'] = $query_type_counts;
        }
        
        // Get recent database errors
        foreach (array_reverse($db_logs) as $log) {
            if ($log['event_type'] === 'index_errors' && count($stats['recent_errors']) < 5) {
                $stats['recent_errors'][] = $log;
            }
        }
        
        return $stats;
    }

    /**
     * Check database health and index effectiveness
     */
    public function check_database_health() {
        global $wpdb;
        
        $health = array(
            'status' => 'healthy',
            'issues' => array(),
            'recommendations' => array(),
            'index_status' => array()
        );
        
        // Check if indexes exist
        $required_indexes = array(
            $this->table_name => array('idx_role_name', 'idx_product_id', 'idx_role_product', 'idx_created_at'),
            $this->global_table_name => array('idx_role_name', 'idx_is_active', 'idx_role_active', 'idx_created_at')
        );
        
        foreach ($required_indexes as $table => $indexes) {
            $health['index_status'][$table] = array();
            foreach ($indexes as $index) {
                $exists = $this->index_exists($table, $index);
                $health['index_status'][$table][$index] = $exists;
                
                if (!$exists) {
                    $health['status'] = 'warning';
                    $health['issues'][] = "Missing index: {$index} on table {$table}";
                    $health['recommendations'][] = "Run database migration to add missing indexes";
                }
            }
        }
        
        // Check for slow queries
        $performance_stats = $this->get_database_performance_stats();
        if ($performance_stats['slow_queries'] > 0) {
            $health['status'] = 'warning';
            $health['issues'][] = "Found {$performance_stats['slow_queries']} slow queries";
            $health['recommendations'][] = "Review query logs and consider additional optimizations";
        }
        
        // Check table sizes
        $table_sizes = $this->get_table_sizes();
        foreach ($table_sizes as $table => $size) {
            if ($size > 10000) { // More than 10k rows
                $health['recommendations'][] = "Table {$table} has {$size} rows - consider archiving old data";
            }
        }
        
        return $health;
    }

    /**
     * Get table row counts
     */
    private function get_table_sizes() {
        global $wpdb;
        
        $sizes = array();
        $sizes[$this->table_name] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %s", $this->table_name));
        $sizes[$this->global_table_name] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %s", $this->global_table_name));
        
        return $sizes;
    }

    public function create_rule($data) {
        global $wpdb;
        
        // SECURITY: Enhanced input validation
        if (empty($data['role_name']) || !isset($data['discount_type']) || !isset($data['discount_value'])) {
            return false;
        }
        
        // Validate role name format
        $role_name = sanitize_text_field($data['role_name']);
        if (empty($role_name) || strlen($role_name) > 100) {
            return false;
        }
        
        // Validate discount type
        $discount_type = in_array($data['discount_type'], array('percentage', 'fixed')) ? $data['discount_type'] : 'percentage';
        
        // Validate discount value
        $discount_value = floatval($data['discount_value']);
        if ($discount_value < 0) {
            return false;
        }
        
        // Validate product ID if provided
        $product_id = null;
        if (isset($data['product_id'])) {
            $product_id = intval($data['product_id']);
            if ($product_id <= 0) {
                return false;
            }
        }
        
        $rule_data = array(
            'role_name' => $role_name,
            'product_id' => $product_id,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
        );
        
        try {
            $result = $wpdb->insert($this->table_name, $rule_data);
            
            if ($result !== false) {
                $rule_id = $wpdb->insert_id;
                
                // Clear relevant cache
                if (isset($rule_data['product_id']) && $rule_data['product_id']) {
                    $this->clear_product_cache($rule_data['product_id']);
                } else {
                    $this->clear_role_cache($rule_data['role_name']);
                }
                
                // Clear user pricing rules status cache
                $this->clear_user_pricing_rules_cache();
                
                // Update last cache clear timestamp
                update_option('maxt_rbp_last_cache_clear', current_time('mysql'));
                
                return $rule_id;
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Rule Creation Error: ' . $e->getMessage());
            }
        }
        
        return false;
    }

    public function get_rules($args = array()) {
        global $wpdb;
        $defaults = array('role_name' => '', 'product_id' => '', 'limit' => 0, 'offset' => 0);
        $args = wp_parse_args($args, $defaults);
        
        // SECURITY: Input validation and sanitization
        $args['role_name'] = sanitize_text_field($args['role_name']);
        $args['product_id'] = intval($args['product_id']);
        $args['limit'] = intval($args['limit']);
        $args['offset'] = intval($args['offset']);
        
        // Validate limits to prevent excessive queries
        if ($args['limit'] > 1000) {
            $args['limit'] = 1000;
        }
        if ($args['offset'] < 0) {
            $args['offset'] = 0;
        }
        
        $start_time = microtime(true);
        $where_conditions = array();
        $where_values = array();
        
        // Optimize query based on available parameters to leverage indexes
        if (!empty($args['role_name']) && !empty($args['product_id'])) {
            // Use compound index (role_name, product_id) for best performance
            $where_conditions[] = 'role_name = %s AND product_id = %d';
            $where_values[] = $args['role_name'];
            $where_values[] = $args['product_id'];
        } elseif (!empty($args['role_name'])) {
            // Use role_name index
            $where_conditions[] = 'role_name = %s';
            $where_values[] = $args['role_name'];
        } elseif (!empty($args['product_id'])) {
            // Use product_id index
            $where_conditions[] = 'product_id = %d';
            $where_values[] = $args['product_id'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $limit_clause = $args['limit'] > 0 ? 'LIMIT ' . $args['offset'] . ', ' . $args['limit'] : '';
        
        // Use created_at index for ordering
        $sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_at DESC {$limit_clause}";
        
        try {
            if (!empty($where_values)) {
                $sql = $wpdb->prepare($sql, $where_values);
            }
            
            $results = $wpdb->get_results($sql, ARRAY_A);
            
            // Log query performance for monitoring
            $this->log_query_performance('get_rules', $sql, microtime(true) - $start_time, count($results));
            
            return $results;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Get Rules Error: ' . $e->getMessage());
            }
            return array();
        }
    }

    public function delete_rule($rule_id) {
        global $wpdb;
        $rule_id = intval($rule_id);
        if ($rule_id <= 0) return false;
        
        // Get rule data before deletion for cache clearing
        $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $rule_id), ARRAY_A);
        
        $result = $wpdb->delete($this->table_name, array('id' => $rule_id), array('%d'));
        
        if ($result !== false && $rule) {
            // Clear relevant cache
            if (isset($rule['product_id']) && $rule['product_id']) {
                $this->clear_product_cache($rule['product_id']);
            } else {
                $this->clear_role_cache($rule['role_name']);
            }
            
            // Clear user pricing rules status cache
            $this->clear_user_pricing_rules_cache();
            
            // Update last cache clear timestamp
            update_option('maxt_rbp_last_cache_clear', current_time('mysql'));
        }
        
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
        
        $cache_key = $this->generate_cache_key($product->get_id(), $user_role, $original_price);
        $cached_price = $this->get_cache($cache_key);
        
        // Use cached price if available (bypass cache in debug mode)
        if ($cached_price !== false && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            return floatval($cached_price);
        }
        
        $pricing_rule = $this->get_pricing_rule($product->get_id(), $user_role);
        
        if (!$pricing_rule) {
            $this->set_cache($cache_key, $original_price);
            return $original_price;
        }
        
        $discounted_price = $this->apply_discount($original_price, $pricing_rule);
        $final_price = max(0, $discounted_price);
        $final_price = apply_filters('maxt_rbp_calculate_price', $final_price, $original_price, $product, $user_role, $pricing_rule);
        
        $this->set_cache($cache_key, $final_price);
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

    /**
     * Initialize cache method based on availability
     */
    private function init_cache_method() {
        $saved_method = get_option('maxt_rbp_cache_method', 'auto');
        
        if ($saved_method === 'auto') {
            // Auto-detect best cache method
            if (wp_using_ext_object_cache()) {
                $this->cache_method = 'object_cache';
            } else {
                $this->cache_method = 'transient';
            }
            // Save the detected method
            update_option('maxt_rbp_cache_method', $this->cache_method);
        } else {
            $this->cache_method = $saved_method;
        }
    }

    /**
     * Generate optimized cache key
     */
    private function generate_cache_key($product_id, $user_role, $original_price) {
        return $this->cache_prefix . $product_id . '_' . $user_role . '_' . md5($original_price);
    }

    /**
     * Get cache with comprehensive fallback hierarchy
     * IMPROVED: Added cache health checking and automatic fallback to transients
     */
    private function get_cache($cache_key) {
        try {
            if ($this->cache_method === 'object_cache') {
                // Check if object cache is healthy
                if ($this->is_object_cache_healthy()) {
                    $cached = wp_cache_get($cache_key, 'maxt_rbp');
                    if ($cached !== false) {
                        return $cached;
                    }
                }
                
                // Fallback to transients
                return get_transient($cache_key);
            } else {
                return get_transient($cache_key);
            }
        } catch (Exception $e) {
            // Log error and fallback to transients
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Cache Error: ' . $e->getMessage());
            }
            
            // Automatic fallback to transients when object cache fails
            return get_transient($cache_key);
        }
    }

    /**
     * Set cache with comprehensive fallback hierarchy
     * IMPROVED: Added cache health checking and automatic fallback mechanisms
     */
    private function set_cache($cache_key, $value) {
        try {
            if ($this->cache_method === 'object_cache') {
                // Check if object cache is healthy before attempting to set
                if ($this->is_object_cache_healthy()) {
                    $success = wp_cache_set($cache_key, $value, 'maxt_rbp', $this->object_cache_duration);
                    if (!$success) {
                        // Fallback to transients
                        set_transient($cache_key, $value, $this->transient_cache_duration);
                    }
                } else {
                    // Object cache is unhealthy, use transients directly
                    set_transient($cache_key, $value, $this->transient_cache_duration);
                }
            } else {
                set_transient($cache_key, $value, $this->transient_cache_duration);
            }
        } catch (Exception $e) {
            // Log error and fallback to transients
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Cache Error: ' . $e->getMessage());
            }
            
            // Ensure pricing functionality never breaks due to caching failures
            set_transient($cache_key, $value, $this->transient_cache_duration);
        }
    }

    /**
     * Clear product-specific cache
     */
    public function clear_product_cache($product_id) {
        $this->clear_cache_by_pattern($this->cache_prefix . $product_id . '_');
        
        // Log cache clearing event
        $this->log_cache_event('product_cleared', array('product_id' => $product_id));
    }

    /**
     * Clear role-specific cache
     */
    public function clear_role_cache($role_name) {
        $this->clear_cache_by_pattern($this->cache_prefix . '%_' . $role_name . '_');
        
        // Log cache clearing event
        $this->log_cache_event('role_cleared', array('role_name' => $role_name));
    }

    /**
     * Clear cache by product and role combination
     */
    public function clear_product_role_cache($product_id, $role_name) {
        $this->clear_cache_by_pattern($this->cache_prefix . $product_id . '_' . $role_name . '_');
        
        // Log cache clearing event
        $this->log_cache_event('product_role_cleared', array(
            'product_id' => $product_id,
            'role_name' => $role_name
        ));
    }

    /**
     * Clear all cache entries
     * IMPROVED: Added comprehensive error handling and fallback mechanisms
     */
    public function clear_all_cache() {
        try {
            $this->clear_cache_by_pattern($this->cache_prefix . '%');
            
            // Clear object cache group if available with error handling
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('maxt_rbp');
            }
            
            // Also clear user status cache
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('maxt_rbp_user_status');
            } else {
                // Fallback for shared hosting environments
                global $wpdb;
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_maxt_rbp_user_%'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_maxt_rbp_user_%'));
            }
            
            // Log cache clearing event
            $this->log_cache_event('all_cleared', array());
            
        } catch (Exception $e) {
            // Log error and continue
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Cache Clear Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Clear cache by pattern with comprehensive fallback support
     * IMPROVED: Added cache health checking and automatic fallback mechanisms
     */
    private function clear_cache_by_pattern($pattern) {
        global $wpdb;
        
        try {
            // Clear object cache if available
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('maxt_rbp');
            }
            
            // Clear transients with error handling
            $transient_pattern = '_transient_' . $pattern;
            $timeout_pattern = '_transient_timeout_' . $pattern;
            
            $result1 = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $transient_pattern
            ));
            
            $result2 = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $timeout_pattern
            ));
            
            // Check if cache operations were successful
            if ($result1 === false || $result2 === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('MaxT RBP Cache Clear Warning: Some cache entries may not have been cleared properly');
                }
            }
            
        } catch (Exception $e) {
            // Log error but ensure pricing functionality continues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Cache Clear Error: ' . $e->getMessage());
            }
            
            // Try alternative cache clearing method
            $this->fallback_cache_clear($pattern);
        }
    }

    /**
     * Log cache events for troubleshooting
     */
    private function log_cache_event($event_type, $data) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'data' => $data,
            'cache_method' => $this->cache_method
        );
        
        $logs = get_option('maxt_rbp_cache_logs', array());
        $logs[] = $log_data;
        
        // Keep only last 50 log entries
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('maxt_rbp_cache_logs', $logs);
    }

    /**
     * Get cache health status
     * IMPROVED: Added cache health checking and automatic fallback detection
     */
    public function get_cache_health() {
        $health = array(
            'method' => $this->cache_method,
            'object_cache_available' => wp_using_ext_object_cache(),
            'object_cache_preferred' => $this->cache_method === 'object_cache',
            'object_cache_healthy' => $this->is_object_cache_healthy(),
            'last_cleared' => get_option('maxt_rbp_last_cache_clear'),
            'cache_hits' => 0,
            'cache_misses' => 0,
            'estimated_entries' => $this->estimate_cache_entries(),
            'fallback_active' => $this->is_fallback_cache_active()
        );
        
        return $health;
    }

    /**
     * Check if object cache is healthy and functioning properly
     */
    private function is_object_cache_healthy() {
        if (!wp_using_ext_object_cache()) {
            return false;
        }
        
        try {
            // Test cache functionality
            $test_key = 'maxt_rbp_health_test_' . time();
            $test_value = 'healthy';
            
            $set_result = wp_cache_set($test_key, $test_value, 'maxt_rbp', 60);
            if (!$set_result) {
                return false;
            }
            
            $get_result = wp_cache_get($test_key, 'maxt_rbp');
            if ($get_result !== $test_value) {
                return false;
            }
            
            // Clean up test key
            wp_cache_delete($test_key, 'maxt_rbp');
            
            return true;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Object Cache Health Check Failed: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Check if fallback cache system is active
     */
    private function is_fallback_cache_active() {
        return $this->cache_method === 'transient' || !$this->is_object_cache_healthy();
    }

    /**
     * Fallback cache clearing method for when primary methods fail
     */
    private function fallback_cache_clear($pattern) {
        global $wpdb;
        
        try {
            // Direct database cache clearing as last resort
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $pattern
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . $pattern
            ));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Fallback Cache Clear Executed for pattern: ' . $pattern);
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MaxT RBP Fallback Cache Clear Failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Estimate number of cache entries
     */
    private function estimate_cache_entries() {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $this->cache_prefix . '%'
        ));
        
        return intval($count);
    }

    /**
     * Warm cache for frequently accessed products
     */
    public function warm_cache($product_ids = array(), $role_names = array()) {
        if (empty($product_ids)) {
            // Get top 10 most viewed products
            $product_ids = $this->get_popular_product_ids();
        }
        
        if (empty($role_names)) {
            // Get all roles with active rules
            $role_names = $this->get_active_role_names();
        }
        
        $warmed_count = 0;
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $original_price = $product->get_regular_price();
            if (!$original_price || $original_price <= 0) continue;
            
            foreach ($role_names as $role_name) {
                $cache_key = $this->generate_cache_key($product_id, $role_name, $original_price);
                
                // Check if already cached
                if ($this->get_cache($cache_key) !== false) {
                    continue;
                }
                
                // Calculate and cache the price
                $pricing_rule = $this->get_pricing_rule($product_id, $role_name);
                if ($pricing_rule) {
                    $discounted_price = $this->apply_discount($original_price, $pricing_rule);
                    $final_price = max(0, $discounted_price);
                    $this->set_cache($cache_key, $final_price);
                    $warmed_count++;
                } else {
                    $this->set_cache($cache_key, $original_price);
                    $warmed_count++;
                }
            }
        }
        
        return $warmed_count;
    }

    /**
     * Get popular product IDs for cache warming
     */
    private function get_popular_product_ids() {
        global $wpdb;
        
        // Get products with most orders (last 30 days)
        $sql = "
            SELECT p.ID, COUNT(oi.order_item_id) as order_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
            WHERE p.post_type = %s
            AND p.post_status = %s
            AND oim.meta_key = %s
            AND o.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.ID
            ORDER BY order_count DESC
            LIMIT 10
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, 'product', 'publish', '_product_id'), ARRAY_A);
        return wp_list_pluck($results, 'ID');
    }

    /**
     * Get active role names
     */
    private function get_active_role_names() {
        $global_rules = $this->get_all_global_rules();
        $active_roles = array();
        
        foreach ($global_rules as $rule) {
            if ($rule['is_active']) {
                $active_roles[] = $rule['role_name'];
            }
        }
        
        // Also include roles with product-specific rules
        $product_rules = $this->get_rules();
        foreach ($product_rules as $rule) {
            if (!in_array($rule['role_name'], $active_roles)) {
                $active_roles[] = $rule['role_name'];
            }
        }
        
        return $active_roles;
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
            /* translators: %d is the maximum number of custom roles allowed */
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
            /* translators: %d is the number of users assigned to the role */
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
        
        // Clear user pricing rules status cache
        $this->clear_user_pricing_rules_cache();
        
        // Update last cache clear timestamp
        update_option('maxt_rbp_last_cache_clear', current_time('mysql'));
        
        return $wpdb->insert_id ?: $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->global_table_name} WHERE role_name = %s", $rule_data['role_name']));
    }

    public function get_global_rule($role_name) {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Use compound index (role_name, is_active) for optimal performance
        $sql = "SELECT * FROM {$this->global_table_name} WHERE role_name = %s AND is_active = 1";
        $result = $wpdb->get_row($wpdb->prepare($sql, $role_name), ARRAY_A);
        
        // Log query performance for monitoring
        $this->log_query_performance('get_global_rule', $sql, microtime(true) - $start_time, $result ? 1 : 0);
        
        return $result;
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
            // Clear user pricing rules status cache
            $this->clear_user_pricing_rules_cache();
            // Update last cache clear timestamp
            update_option('maxt_rbp_last_cache_clear', current_time('mysql'));
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
            // Clear user pricing rules status cache
            $this->clear_user_pricing_rules_cache();
            // Update last cache clear timestamp
            update_option('maxt_rbp_last_cache_clear', current_time('mysql'));
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
            // Clear user pricing rules status cache when global rules change
            $this->clear_user_pricing_rules_cache();
        }
        
        return $result !== false ? $new_status : false;
    }

    /**
     * Clear user pricing rules status cache
     * Called when pricing rules are updated
     * IMPROVED: Added multisite support and comprehensive error handling
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
                
                // Also clear user role cache
                $role_cache_key = 'maxt_rbp_user_role_' . $site_prefix . $user_id;
                wp_cache_delete($role_cache_key, 'maxt_rbp_user_roles');
            } else {
                // Clear all user status cache with compatibility check
                if (function_exists('wp_cache_flush_group')) {
                    wp_cache_flush_group('maxt_rbp_user_status');
                    wp_cache_flush_group('maxt_rbp_user_roles');
                } else {
                    // Fallback for shared hosting environments
                    global $wpdb;
                    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_maxt_rbp_user_%'));
                    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_maxt_rbp_user_%'));
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
     * Get hook performance statistics
     */
    public function get_hook_performance_stats() {
        $hook_stats = get_transient('maxt_rbp_hook_stats');
        if (!$hook_stats) {
            return array(
                'total_pages' => 0,
                'most_accessed_pages' => array(),
                'last_updated' => null
            );
        }
        
        $stats = array(
            'total_pages' => count($hook_stats['pages'] ?? array()),
            'most_accessed_pages' => array(),
            'last_updated' => $hook_stats['last_updated'] ?? null
        );
        
        if (!empty($hook_stats['pages'])) {
            arsort($hook_stats['pages']);
            $stats['most_accessed_pages'] = array_slice($hook_stats['pages'], 0, 10, true);
        }
        
        return $stats;
    }

    /**
     * Clear hook performance statistics
     * IMPROVED: Added comprehensive cleanup to prevent database bloat
     */
    public function clear_hook_performance_stats() {
        delete_transient('maxt_rbp_hook_stats');
        
        // Also clear related performance monitoring data
        delete_option('maxt_rbp_query_logs');
        delete_option('maxt_rbp_db_logs');
        delete_option('maxt_rbp_cache_logs');
        
        // Clean up old performance monitoring transients
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name LIKE %s", '_transient_maxt_rbp_%', '%performance%'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name LIKE %s", '_transient_timeout_maxt_rbp_%', '%performance%'));
    }

    /**
     * Cleanup old performance monitoring data to prevent database bloat
     * Called automatically to maintain database health
     */
    public function cleanup_performance_data() {
        // Clean up old query logs (keep only last 100 entries)
        $query_logs = get_option('maxt_rbp_query_logs', array());
        if (count($query_logs) > 100) {
            $query_logs = array_slice($query_logs, -100);
            update_option('maxt_rbp_query_logs', $query_logs);
        }
        
        // Clean up old database logs (keep only last 50 entries)
        $db_logs = get_option('maxt_rbp_db_logs', array());
        if (count($db_logs) > 50) {
            $db_logs = array_slice($db_logs, -50);
            update_option('maxt_rbp_db_logs', $db_logs);
        }
        
        // Clean up old cache logs (keep only last 50 entries)
        $cache_logs = get_option('maxt_rbp_cache_logs', array());
        if (count($cache_logs) > 50) {
            $cache_logs = array_slice($cache_logs, -50);
            update_option('maxt_rbp_cache_logs', $cache_logs);
        }
        
        // Clean up expired performance monitoring transients
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_maxt_rbp_hook_stats'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_maxt_rbp_hook_stats'));
    }
}
