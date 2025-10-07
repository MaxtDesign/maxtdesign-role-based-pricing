<?php
/**
 * Uninstall script for MaxtDesign Role-Based Pricing for WooCommerce
 * 
 * Executed when the plugin is deleted via WordPress admin.
 * Performs complete cleanup of all plugin data.
 *
 * @package MaxtDesign_RBP
 */

// Security checks
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!current_user_can('delete_plugins')) {
    return;
}

/**
 * Main uninstall function
 * Orchestrates complete cleanup of all plugin data
 */
function maxtdesign_rbp_uninstall() {
    try {
        // 1. Delete database tables
        maxtdesign_rbp_delete_tables();
        
        // 2. Delete plugin options
        maxtdesign_rbp_delete_options();
        
        // 3. Delete transients
        maxtdesign_rbp_delete_transients();
        
        // 4. Delete post meta
        maxtdesign_rbp_delete_post_meta();
        
        // 5. Delete user meta
        maxtdesign_rbp_delete_user_meta();
        
        // 6. Remove custom roles
        maxtdesign_rbp_remove_custom_roles();
        
        // 7. Clear cache
        maxtdesign_rbp_clear_cache();
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MaxtDesign RBP Uninstall Error: ' . $e->getMessage());
        }
    }
}

/**
 * Delete custom database tables
 */
function maxtdesign_rbp_delete_tables() {
    global $wpdb;
    
    $tables = array(
        $wpdb->prefix . 'maxtdesign_rbp_rules',
        $wpdb->prefix . 'maxtdesign_rbp_global_rules',
    );
    
    foreach ($tables as $table) {
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Schema change required for uninstall, table name is validated and prefixed
        $wpdb->query($sql);
    }
}

/**
 * Delete plugin options
 */
function maxtdesign_rbp_delete_options() {
    $options = array(
        'maxtdesign_rbp_version',
        'maxtdesign_rbp_cache_duration',
        'maxtdesign_rbp_display_original_price',
        'maxtdesign_rbp_settings',
        'maxtdesign_rbp_roles',
        'maxtdesign_rbp_cache_method',
        'maxtdesign_rbp_last_cache_clear',
        'maxtdesign_rbp_db_logs',
        'maxtdesign_rbp_query_logs',
        'maxtdesign_rbp_cache_logs',
        'maxtdesign_rbp_db_indexes_version',
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
}

/**
 * Delete all transients related to the plugin
 */
function maxtdesign_rbp_delete_transients() {
    global $wpdb;
    
    // Delete plugin transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_maxtdesign_rbp_') . '%'
        )
    );
    
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_maxtdesign_rbp_') . '%'
        )
    );
    
    // Delete legacy transients (old prefix)
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_maxt_rbp_') . '%'
        )
    );
    
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_maxt_rbp_') . '%'
        )
    );
}

/**
 * Delete all product meta data created by the plugin
 */
function maxtdesign_rbp_delete_post_meta() {
    global $wpdb;
    
    // Delete product-specific pricing rules meta
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like('_maxtdesign_rbp_') . '%'
        )
    );
    
    // Delete legacy meta (old prefix)
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like('_maxt_rbp_') . '%'
        )
    );
}

/**
 * Delete all user meta data created by the plugin
 */
function maxtdesign_rbp_delete_user_meta() {
    global $wpdb;
    
    // Delete user meta
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like('maxtdesign_rbp_') . '%'
        )
    );
    
    // Delete legacy user meta (old prefix)
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like('maxt_rbp_') . '%'
        )
    );
}

/**
 * Remove custom user roles created by the plugin
 */
function maxtdesign_rbp_remove_custom_roles() {
    // Get all roles
    global $wp_roles;
    
    if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }
    
    $all_roles = $wp_roles->get_names();
    
    // Remove roles with our prefix
    foreach ($all_roles as $role_slug => $role_name) {
        // Check for new prefix (maxtdesign_rbp_) and old prefix (maxt_rbp_)
        if (strpos($role_slug, 'maxtdesign_rbp_') === 0 || strpos($role_slug, 'maxt_rbp_') === 0) {
            // Only remove role if no users are assigned
            $users_with_role = get_users(array(
                'role' => $role_slug,
                'number' => 1,
            ));
            
            if (empty($users_with_role)) {
                remove_role($role_slug);
            }
        }
    }
}

/**
 * Clear all plugin cache
 */
function maxtdesign_rbp_clear_cache() {
    // Clear object cache group if available
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('maxt_rbp');
        wp_cache_flush_group('maxtdesign_rbp');
        wp_cache_flush_group('maxtdesign_rbp_user_status');
        wp_cache_flush_group('maxtdesign_rbp_user_roles');
    }
    
    // Flush entire cache
    wp_cache_flush();
}

// Execute uninstall
maxtdesign_rbp_uninstall();
