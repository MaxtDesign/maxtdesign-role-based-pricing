<?php
/**
 * PHPUnit bootstrap.
 *
 * These are unit tests for the pure pricing engine and the request-scoped
 * cache state — not WordPress integration tests. Rather than spin up a full
 * WP test install, we define the handful of WordPress functions the plugin
 * touches at include / construction time so the classes load in isolation.
 * Behavioural logic is exercised directly (via reflection) in the tests.
 *
 * @package MaxtDesign_RBP
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// --- Minimal WordPress shims (existence only) -------------------------------

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return rtrim(dirname($file), '/\\') . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('add_action')) {
    function add_action() {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter() {
        return true;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook() {
        return true;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook() {
        return true;
    }
}

// Load the plugin. The main file requires the include/ classes and instantiates
// the singleton; its constructor only registers hooks, which the shims absorb.
require_once dirname(__DIR__) . '/maxtdesign-role-based-pricing.php';
