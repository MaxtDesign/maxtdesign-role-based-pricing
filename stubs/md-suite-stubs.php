<?php
/**
 * PHPStan-only stubs for the MaxtDesign suite-core API this plugin calls
 * behind class_exists()/method_exists() guards.
 *
 * This plugin is Tier-2 (wp.org standalone): it NEVER vendors suite-core, so
 * these classes are absent from the analysis environment. The stubs mirror
 * the suite-core 1.5.0 signatures (maxtdesign-suite-core/src/MdSuite_Admin.php,
 * MdSuite_Registry.php) — update them if a re-vendor-era API change lands.
 *
 * Loaded only via phpstan.neon.dist bootstrapFiles; never at runtime.
 *
 * @package MaxtDesign_RBP
 */

if (!class_exists('MdSuite_Admin')) {
    class MdSuite_Admin {
        public const MENU_SLUG = 'maxtdesign';

        public static function register_screen(string $page_slug): void {}

        public static function render_page_header(string $product_name, array $args = array()): string {
            return '';
        }

        public static function render_screen_tabs(string $page_slug, array $tabs, string $active, array $args = array()): string {
            return '';
        }

        public static function render_status_badge(string $status, string $label): string {
            return '';
        }

        public static function render_empty_state(string $title, string $message, string $action_html = ''): string {
            return '';
        }

        public static function render_notice(string $type, string $message): string {
            return '';
        }
    }
}

if (!class_exists('MdSuite_Registry')) {
    class MdSuite_Registry {
        public static function register(string $slug, array $meta = array()): void {}
    }
}
