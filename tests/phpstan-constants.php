<?php
/**
 * Constants defined at runtime in the main plugin file (via define()) and by
 * WordPress core. Declared here so PHPStan can resolve them when analysing the
 * include/ classes in isolation. Analysis-only — never loaded at runtime.
 *
 * @package MaxtDesign_RBP
 */

define('MAXTDESIGN_RBP_VERSION', '1.1.3');
define('MAXTDESIGN_RBP_PLUGIN_FILE', __FILE__);
define('MAXTDESIGN_RBP_PLUGIN_DIR', __DIR__ . '/');
define('MAXTDESIGN_RBP_PLUGIN_URL', 'http://example.test/wp-content/plugins/maxtdesign-role-based-pricing/');
define('MAXTDESIGN_RBP_PLUGIN_BASENAME', 'maxtdesign-role-based-pricing/maxtdesign-role-based-pricing.php');
define('MAXTDESIGN_RBP_PERFORMANCE_MONITORING', false);

// WordPress core constant referenced in raw INFORMATION_SCHEMA index lookups.
if (!defined('DB_NAME')) {
    define('DB_NAME', 'wordpress');
}
