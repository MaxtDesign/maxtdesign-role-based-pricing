<?php
/**
 * Uninstall script for MaxT Role Based Pricing
 * 
 * This file is executed when the plugin is deleted via WordPress admin.
 * It performs a complete cleanup of all plugin data.
 *
 * @package MaxT_RBP
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
$core = new MaxT_RBP_Core();
$core->drop_table();
$core->remove_all_custom_roles();
$core->clear_all_cache();

// Delete all plugin options
$plugin_options = array(
    'maxt_rbp_version',
    'maxt_rbp_cache_duration',
    'maxt_rbp_display_original_price',
    'maxt_rbp_settings',
    'maxt_rbp_roles',
);

foreach ($plugin_options as $option) {
    delete_option($option);
}

// Clear all transients related to the plugin
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_maxt_rbp_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_maxt_rbp_%'");


// Clear any cached data
wp_cache_flush();

// Log uninstall
error_log('MaxT Role Based Pricing plugin uninstalled - all data cleaned up');
