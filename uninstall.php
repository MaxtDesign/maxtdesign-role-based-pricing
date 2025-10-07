<?php
/**
 * Uninstall script for Role-Based Pricing for WooCommerce
 * 
 * This file is executed when the plugin is deleted via WordPress admin.
 * It performs a complete cleanup of all plugin data.
 *
 * @package MaxtDesign_RBP
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to delete plugins
if (!current_user_can('delete_plugins')) {
    return;
}

// Include required classes
require_once plugin_dir_path(__FILE__) . 'includes/class-core.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-frontend.php';

// Initialize core and cleanup
$core = new MaxtDesign_RBP_Core();
$core->drop_table();
$core->remove_all_custom_roles();
$core->clear_all_cache();

// Delete all plugin options
$plugin_options = array(
    'maxtdesign_rbp_version',
    'maxtdesign_rbp_cache_duration',
    'maxtdesign_rbp_display_original_price',
    'maxtdesign_rbp_settings',
    'maxtdesign_rbp_roles',
);

foreach ($plugin_options as $option) {
    delete_option($option);
}

// Clear all transients related to the plugin
global $wpdb;
// @codingStandardsIgnoreLine - Direct database query required for plugin uninstall cleanup
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_maxtdesign_rbp_%'");
// @codingStandardsIgnoreLine - Direct database query required for plugin uninstall cleanup
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_maxtdesign_rbp_%'");


// Clear any cached data
wp_cache_flush();

// Log uninstall
if (defined('WP_DEBUG') && WP_DEBUG) {
    // Debug logging removed for security compliance
}
