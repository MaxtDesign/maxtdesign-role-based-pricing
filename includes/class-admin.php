<?php
/**
 * Admin interface for Role-Based Pricing for WooCommerce.
 *
 * Suite-conformant admin (nav + UI handoffs, 2026-07-17):
 * - One screen, slug `md-rbp`, mounted under the MaxtDesign suite menu when
 *   suite-core is active (opportunistic — this wp.org plugin never vendors
 *   suite-core), falling back to the WooCommerce submenu otherwise.
 * - Permanent redirect shim from the legacy `maxtdesign-role-pricing` slug.
 * - All mutations are POST-redirect-GET via admin-post.php with nonce +
 *   capability checks; flash notices travel as query codes, never raw text.
 * - No jQuery, no AJAX CRUD, no native confirm()/prompt(): destructive
 *   buttons carry the `data-md-suite-confirm` dialog contract (styled dialog
 *   when suite JS is present; server-side guards are the real protection).
 * - Product metabox rules are edited as fields inside the product form and
 *   persisted on `save_post_product` (the metabox-native PRG equivalent).
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

    /**
     * Suite-conformant page slug.
     */
    const PAGE_SLUG = 'md-rbp';

    /**
     * Legacy page slug (pre-suite). Redirected forever; bookmarks are forever.
     */
    const LEGACY_PAGE_SLUG = 'maxtdesign-role-pricing';

    /**
     * Core instance.
     *
     * @var MaxtDesign_RBP_Core
     */
    private $core;

    /**
     * Hook suffix returned by add_submenu_page(); assets gate on exact equality.
     *
     * @var string|false
     */
    private $hook_suffix = false;

    public function __construct($core) {
        $this->core = $core;
    }

    public function init() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'legacy_slug_redirect'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_filter('plugin_action_links_' . MAXTDESIGN_RBP_PLUGIN_BASENAME, array($this, 'plugin_action_links'));

        // Product metabox (deliberately stays on the product editor — nav handoff §4.7).
        add_action('add_meta_boxes', array($this, 'add_product_meta_box'));
        add_action('save_post_product', array($this, 'save_product_meta_box'), 10, 2);

        // PRG mutation endpoints (admin-post.php). Every handler: cap + nonce + redirect.
        add_action('admin_post_maxtdesign_rbp_create_role', array($this, 'handle_create_role'));
        add_action('admin_post_maxtdesign_rbp_delete_role', array($this, 'handle_delete_role'));
        add_action('admin_post_maxtdesign_rbp_save_global_rule', array($this, 'handle_save_global_rule'));
        add_action('admin_post_maxtdesign_rbp_toggle_global_rule', array($this, 'handle_toggle_global_rule'));
        add_action('admin_post_maxtdesign_rbp_delete_global_rule', array($this, 'handle_delete_global_rule'));
        add_action('admin_post_maxtdesign_rbp_create_default_rules', array($this, 'handle_create_default_rules'));
        add_action('admin_post_maxtdesign_rbp_clear_cache', array($this, 'handle_clear_cache'));
        add_action('admin_post_maxtdesign_rbp_warm_cache', array($this, 'handle_warm_cache'));
        add_action('admin_post_maxtdesign_rbp_add_db_indexes', array($this, 'handle_add_db_indexes'));
        add_action('admin_post_maxtdesign_rbp_clear_hook_stats', array($this, 'handle_clear_hook_stats'));
    }

    /* ---------------------------------------------------------------------
     * Menu, suite registration, redirect shim, assets.
     * ------------------------------------------------------------------- */

    /**
     * Register the single admin screen.
     *
     * Opportunistic suite mounting: when suite-core is active its parent menu
     * exists and we mount under it; on a site without the suite (any wp.org
     * install in the wild) we stay a WooCommerce submenu. class_exists() runs
     * here — inside a hook, long after plugins_loaded — never at include time
     * (suite-core loader-negotiation contract).
     */
    public function register_menu() {
        $suite_present = class_exists('MdSuite_Admin');
        $parent        = $suite_present ? \MdSuite_Admin::MENU_SLUG : 'woocommerce';

        $this->hook_suffix = add_submenu_page(
            $parent,
            __('MaxtDesign Role-Based Pricing', 'maxtdesign-role-based-pricing'),
            __('Role-Based Pricing', 'maxtdesign-role-based-pricing'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'settings_page')
        );

        if ($suite_present && method_exists('MdSuite_Admin', 'register_screen')) {
            \MdSuite_Admin::register_screen(self::PAGE_SLUG);
        }

        if (class_exists('MdSuite_Registry')) {
            \MdSuite_Registry::register('maxtdesign-role-based-pricing', array(
                'version'      => MAXTDESIGN_RBP_VERSION,
                'capabilities' => array('role-pricing', 'global-rules', 'product-rules', 'custom-roles'),
            ));
        }
    }

    /**
     * Permanent redirect shim from the legacy page slug, args preserved.
     */
    public function legacy_slug_redirect() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect, no state change.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (self::LEGACY_PAGE_SLUG !== $page) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect, no state change.
        $args = wp_unslash($_GET);
        unset($args['page']);
        $args = array_map('sanitize_text_field', $args);

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=' . self::PAGE_SLUG)));
        exit;
    }

    /**
     * Plugins-screen Settings link (nav handoff §4.5).
     *
     * @param array $links Existing action links.
     * @return array
     */
    public function plugin_action_links($links) {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('Settings', 'maxtdesign-role-based-pricing') . '</a>'
        );
        return $links;
    }

    /**
     * Enqueue the plugin's own admin CSS only on its own screen, and only when
     * suite-core is absent (when present, the registered suite stylesheet
     * styles the shared class shapes — Tier-2 contract, UI handoff §7.1).
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function admin_assets($hook) {
        if (false === $this->hook_suffix || $hook !== $this->hook_suffix) {
            return;
        }
        if (class_exists('MdSuite_Admin')) {
            return;
        }
        wp_enqueue_style('maxtdesign-rbp-admin', MAXTDESIGN_RBP_PLUGIN_URL . 'assets/css/admin.css', array(), MAXTDESIGN_RBP_VERSION);
    }

    /* ---------------------------------------------------------------------
     * Flash-notice dictionary (PRG transport is a query code, never text).
     * ------------------------------------------------------------------- */

    /**
     * Notice dictionary: code => [type, message]. %d is filled from the
     * maxtdesign_rbp_count query arg where present.
     *
     * @return array<string,array{0:string,1:string}>
     */
    private function notice_map() {
        return array(
            'role_created'        => array('success', __('Role created successfully.', 'maxtdesign-role-based-pricing')),
            'role_deleted'        => array('success', __('Role deleted successfully.', 'maxtdesign-role-based-pricing')),
            'role_error'          => array('error', __('The role could not be created or deleted. It may already exist, still have users assigned, or the limit of custom roles may be reached.', 'maxtdesign-role-based-pricing')),
            'rule_saved'          => array('success', __('Global pricing rule saved successfully.', 'maxtdesign-role-based-pricing')),
            'rule_deleted'        => array('success', __('Global pricing rule deleted successfully.', 'maxtdesign-role-based-pricing')),
            'rule_toggled'        => array('success', __('Global pricing rule status updated.', 'maxtdesign-role-based-pricing')),
            'rule_error'          => array('error', __('The pricing rule could not be saved. Check the discount type and value and try again.', 'maxtdesign-role-based-pricing')),
            /* translators: %d is the number of default rules created */
            'defaults_created'    => array('success', __('Created %d default global pricing rules for roles that had none.', 'maxtdesign-role-based-pricing')),
            'cache_cleared'       => array('success', __('All pricing caches cleared.', 'maxtdesign-role-based-pricing')),
            /* translators: %d is the number of cache entries created */
            'cache_warmed'        => array('success', __('Cache warmed: %d entries created from recent orders.', 'maxtdesign-role-based-pricing')),
            'indexes_added'       => array('success', __('Database indexes verified and added where missing.', 'maxtdesign-role-based-pricing')),
            'indexes_error'       => array('error', __('Some database indexes could not be added. Details were written to the plugin debug log.', 'maxtdesign-role-based-pricing')),
            'hook_stats_cleared'  => array('success', __('Hook performance statistics cleared.', 'maxtdesign-role-based-pricing')),
            'denied'              => array('error', __('Security check failed. Please try again from the settings screen.', 'maxtdesign-role-based-pricing')),
        );
    }

    /**
     * Render the flash notice for the current request, if any.
     */
    private function render_flash_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a PRG flash code.
        $code = isset($_GET['maxtdesign_rbp_notice']) ? sanitize_key(wp_unslash($_GET['maxtdesign_rbp_notice'])) : '';
        if ('' === $code) {
            return;
        }
        $map = $this->notice_map();
        if (!isset($map[$code])) {
            return;
        }
        list($type, $message) = $map[$code];
        if (false !== strpos($message, '%d')) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display arg.
            $count   = isset($_GET['maxtdesign_rbp_count']) ? absint(wp_unslash($_GET['maxtdesign_rbp_count'])) : 0;
            $message = sprintf($message, $count);
        }
        if (method_exists('MdSuite_Admin', 'render_notice')) {
            $suite_type = 'success' === $type ? 'success' : ('error' === $type ? 'error' : 'info');
            echo wp_kses_post(\MdSuite_Admin::render_notice($suite_type, $message));
            return;
        }
        echo '<div class="notice notice-' . esc_attr($type) . ' md-suite-notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Redirect back to the settings screen with a flash code. Ends the request.
     *
     * @param string $code  Notice code from notice_map().
     * @param int    $count Optional %d value for count-bearing notices.
     */
    private function redirect_with_notice($code, $count = 0) {
        $url = add_query_arg(
            array('maxtdesign_rbp_notice' => $code),
            admin_url('admin.php?page=' . self::PAGE_SLUG)
        );
        if ($count > 0) {
            $url = add_query_arg('maxtdesign_rbp_count', absint($count), $url);
        }
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Shared guard for every admin-post handler.
     *
     * @param string $nonce_action Nonce action to verify.
     */
    private function verify_admin_post($nonce_action) {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($nonce, $nonce_action)) {
            $this->redirect_with_notice('denied');
        }
    }

    /* ---------------------------------------------------------------------
     * admin-post handlers (all PRG).
     * ------------------------------------------------------------------- */

    public function handle_create_role() {
        $this->verify_admin_post('maxtdesign_rbp_create_role');
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('denied');
        }
        $role_name    = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
        $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $result       = $this->core->create_custom_role($role_name, $display_name);
        $this->redirect_with_notice(is_wp_error($result) ? 'role_error' : 'role_created');
    }

    public function handle_delete_role() {
        $this->verify_admin_post('maxtdesign_rbp_delete_role');
        $role_name = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
        $result    = $this->core->delete_custom_role($role_name);
        $this->redirect_with_notice(is_wp_error($result) ? 'role_error' : 'role_deleted');
    }

    public function handle_save_global_rule() {
        $this->verify_admin_post('maxtdesign_rbp_save_global_rule');

        $rule_id        = isset($_POST['rule_id']) ? absint(wp_unslash($_POST['rule_id'])) : 0;
        $role_name      = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';
        $discount_type  = isset($_POST['discount_type']) ? sanitize_text_field(wp_unslash($_POST['discount_type'])) : '';
        $discount_value = isset($_POST['discount_value']) ? floatval(wp_unslash($_POST['discount_value'])) : 0;

        if (!$this->is_valid_discount($discount_type, $discount_value)) {
            $this->redirect_with_notice('rule_error');
        }

        if ($rule_id > 0) {
            $result = $this->core->update_global_rule($rule_id, array(
                'discount_type'  => $discount_type,
                'discount_value' => $discount_value,
            ));
        } else {
            if ('' === $role_name) {
                $this->redirect_with_notice('rule_error');
            }
            $result = $this->core->create_global_rule(array(
                'role_name'      => $role_name,
                'discount_type'  => $discount_type,
                'discount_value' => $discount_value,
                'is_active'      => 1,
            ));
            if ($result) {
                do_action('maxtdesign_rbp_after_global_rule_created', $result, array(
                    'role_name'      => $role_name,
                    'discount_type'  => $discount_type,
                    'discount_value' => $discount_value,
                    'is_active'      => 1,
                ));
            }
        }

        $this->redirect_with_notice($result ? 'rule_saved' : 'rule_error');
    }

    public function handle_toggle_global_rule() {
        $this->verify_admin_post('maxtdesign_rbp_toggle_global_rule');
        $rule_id = isset($_POST['rule_id']) ? absint(wp_unslash($_POST['rule_id'])) : 0;
        $result  = $this->core->toggle_global_rule_status($rule_id);
        $this->redirect_with_notice(false !== $result ? 'rule_toggled' : 'rule_error');
    }

    public function handle_delete_global_rule() {
        $this->verify_admin_post('maxtdesign_rbp_delete_global_rule');
        $rule_id = isset($_POST['rule_id']) ? absint(wp_unslash($_POST['rule_id'])) : 0;
        $result  = $this->core->delete_global_rule($rule_id);
        if ($result) {
            do_action('maxtdesign_rbp_after_global_rule_deleted', $rule_id);
        }
        $this->redirect_with_notice($result ? 'rule_deleted' : 'rule_error');
    }

    public function handle_create_default_rules() {
        $this->verify_admin_post('maxtdesign_rbp_create_default_rules');
        $created = $this->create_default_global_rules();
        $this->redirect_with_notice('defaults_created', $created);
    }

    public function handle_clear_cache() {
        $this->verify_admin_post('maxtdesign_rbp_clear_cache');
        $this->core->clear_all_cache();
        $this->redirect_with_notice('cache_cleared');
    }

    public function handle_warm_cache() {
        $this->verify_admin_post('maxtdesign_rbp_warm_cache');
        $warmed = (int) $this->core->warm_cache();
        $this->redirect_with_notice('cache_warmed', $warmed);
    }

    public function handle_add_db_indexes() {
        $this->verify_admin_post('maxtdesign_rbp_add_db_indexes');
        $result = $this->core->add_database_indexes();
        $this->redirect_with_notice($result ? 'indexes_added' : 'indexes_error');
    }

    public function handle_clear_hook_stats() {
        $this->verify_admin_post('maxtdesign_rbp_clear_hook_stats');
        $this->core->clear_hook_performance_stats();
        $this->redirect_with_notice('hook_stats_cleared');
    }

    /**
     * Shared discount validation (mirrors core-side rules).
     *
     * @param string $type  Discount type.
     * @param float  $value Discount value.
     * @return bool
     */
    private function is_valid_discount($type, $value) {
        if (!in_array($type, array('percentage', 'fixed', 'fixed_price'), true)) {
            return false;
        }
        if ('fixed_price' === $type) {
            return $value >= 0;
        }
        if ($value <= 0) {
            return false;
        }
        if ('percentage' === $type && $value > 100) {
            return false;
        }
        return true;
    }

    /* ---------------------------------------------------------------------
     * Settings screen.
     * ------------------------------------------------------------------- */

    public function settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'maxtdesign-role-based-pricing'));
        }

        echo '<div class="wrap md-rbp-wrap">';

        $this->render_page_header();
        $this->render_flash_notice();

        // Tab dispatch. One default tab today; the ?tab= scaffold exists so a
        // Pro add-on injects tabs via the filter instead of new menu entries.
        $tabs = apply_filters('maxtdesign_rbp_admin_tabs', array(
            'settings' => __('Settings', 'maxtdesign-role-based-pricing'),
        ));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
        $active = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';
        if (!isset($tabs[$active])) {
            $active = 'settings';
        }

        if (count($tabs) > 1) {
            $this->render_tabs($tabs, $active);
        }

        if ('settings' === $active) {
            $this->render_roles_card();
            $this->render_global_rules_card();
            $this->render_product_rules_card();
            $this->render_cache_card();
            $this->render_performance_section();
        } else {
            // Render a Pro-injected tab. Fires only for tab slugs added via
            // the maxtdesign_rbp_admin_tabs filter.
            do_action('maxtdesign_rbp_render_tab_' . $active);
        }

        echo '</div>';
    }

    /**
     * Page header: suite helper when present, byte-identical fallback otherwise.
     */
    private function render_page_header() {
        if (method_exists('MdSuite_Admin', 'render_page_header')) {
            echo wp_kses_post(\MdSuite_Admin::render_page_header(
                __('Role-Based Pricing', 'maxtdesign-role-based-pricing'),
                array('version' => MAXTDESIGN_RBP_VERSION)
            ));
            return;
        }
        echo '<header class="md-suite-page-header">'
            . '<span class="md-suite-page-header__brand">MaxtDesign</span>'
            . '<span class="md-suite-page-header__sep" aria-hidden="true">/</span>'
            . '<h1 class="md-suite-page-header__title">' . esc_html__('Role-Based Pricing', 'maxtdesign-role-based-pricing') . '</h1>'
            . '<div class="md-suite-page-header__meta">' . wp_kses_post($this->badge('neutral', 'v' . MAXTDESIGN_RBP_VERSION)) . '</div>'
            . '</header>';
    }

    /**
     * Screen tabs: suite helper when present, byte-identical fallback otherwise.
     *
     * @param array  $tabs   slug => label.
     * @param string $active Active slug.
     */
    private function render_tabs($tabs, $active) {
        if (method_exists('MdSuite_Admin', 'render_screen_tabs')) {
            echo wp_kses_post(\MdSuite_Admin::render_screen_tabs(self::PAGE_SLUG, $tabs, $active));
            return;
        }
        echo '<nav class="md-suite-tabs nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $is_active = ((string) $slug === $active);
            echo '<a class="nav-tab' . ($is_active ? ' nav-tab-active' : '') . '"' . ($is_active ? ' aria-current="page"' : '')
                . ' href="' . esc_url(admin_url('admin.php?page=' . rawurlencode(self::PAGE_SLUG) . '&tab=' . rawurlencode((string) $slug))) . '">'
                . esc_html((string) $label) . '</a>';
        }
        echo '</nav>';
    }

    /**
     * Status badge markup (suite class shape; styled by suite CSS when present,
     * by the plugin's fallback stylesheet otherwise, harmless plain text elsewhere).
     *
     * @param string $status good|warn|bad|neutral|brand.
     * @param string $label  Badge text.
     * @return string
     */
    private function badge($status, $label) {
        if (method_exists('MdSuite_Admin', 'render_status_badge')) {
            return \MdSuite_Admin::render_status_badge($status, $label);
        }
        $allowed = array('good', 'warn', 'bad', 'neutral', 'brand');
        if (!in_array($status, $allowed, true)) {
            $status = 'neutral';
        }
        return '<span class="md-suite-badge md-suite-badge--' . esc_attr($status) . '">' . esc_html($label) . '</span>';
    }

    /**
     * Open/close a suite card (markup identical to MdSuite_Admin::render_card()).
     * Emitted directly because card bodies here are large streamed tables.
     *
     * @param string $title Card title.
     */
    private function card_open($title) {
        echo '<section class="md-suite-card"><h2 class="md-suite-card__title">' . esc_html($title) . '</h2><div class="md-suite-card__body">';
    }

    private function card_close() {
        echo '</div></section>';
    }

    /**
     * Designed empty state (byte-identical to MdSuite_Admin::render_empty_state()).
     *
     * @param string $title   Bold first line.
     * @param string $message Muted explanation with the "what to do next".
     */
    private function empty_state($title, $message) {
        if (method_exists('MdSuite_Admin', 'render_empty_state')) {
            echo wp_kses_post(\MdSuite_Admin::render_empty_state($title, $message));
            return;
        }
        echo '<div class="md-suite-empty"><p class="md-suite-empty__title">' . esc_html($title) . '</p><p class="md-suite-empty__message">' . esc_html($message) . '</p></div>';
    }

    /**
     * Small inline admin-post form for a row action.
     *
     * @param string $action  admin-post action name.
     * @param array  $fields  Hidden field name => value.
     * @param string $label   Button label.
     * @param string $confirm Optional data-md-suite-confirm message (marks destructive).
     */
    private function row_action_form($action, $fields, $label, $confirm = '') {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="md-rbp-row-action">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '" />';
        foreach ($fields as $name => $value) {
            echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        }
        wp_nonce_field($action);
        $confirm_attr = '' !== $confirm ? ' data-md-suite-confirm="' . esc_attr($confirm) . '"' : '';
        echo '<button type="submit" class="button button-small"' . $confirm_attr . '>' . esc_html($label) . '</button>';
        echo '</form>';
    }

    /* ---------------------------------------------------------------------
     * Cards.
     * ------------------------------------------------------------------- */

    private function render_roles_card() {
        $all_roles = $this->core->get_all_roles();

        $this->card_open(__('Roles', 'maxtdesign-role-based-pricing'));

        echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Roles', 'maxtdesign-role-based-pricing') . '">';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>'
            . '<th>' . esc_html__('Role Name', 'maxtdesign-role-based-pricing') . '</th>'
            . '<th>' . esc_html__('Display Name', 'maxtdesign-role-based-pricing') . '</th>'
            . '<th>' . esc_html__('Type', 'maxtdesign-role-based-pricing') . '</th>'
            . '<th>' . esc_html__('Users', 'maxtdesign-role-based-pricing') . '</th>'
            . '<th>' . esc_html__('Actions', 'maxtdesign-role-based-pricing') . '</th>'
            . '</tr></thead><tbody>';
        foreach ($all_roles as $role_name => $role_data) {
            echo '<tr><td><code>' . esc_html($role_name) . '</code></td>'
                . '<td>' . esc_html($role_data['display_name']) . '</td>'
                . '<td>' . wp_kses_post($this->badge($role_data['is_custom'] ? 'brand' : 'neutral', $role_data['is_custom'] ? __('Custom', 'maxtdesign-role-based-pricing') : __('Built-in', 'maxtdesign-role-based-pricing'))) . '</td>'
                . '<td>' . esc_html($role_data['user_count']) . '</td><td>';
            if ($role_data['is_custom'] && 0 === $role_data['user_count']) {
                $this->row_action_form(
                    'maxtdesign_rbp_delete_role',
                    array('role_name' => $role_name),
                    __('Delete', 'maxtdesign-role-based-pricing'),
                    __('Delete this custom role? Pricing rules that reference it stop applying.', 'maxtdesign-role-based-pricing')
                );
            } else {
                echo '<span class="description">' . esc_html__('No actions available', 'maxtdesign-role-based-pricing') . '</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';

        $this->render_role_creation_form();
        $this->card_close();
    }

    private function render_role_creation_form() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if ($this->core->get_custom_roles_count() >= 3) {
            /* translators: %d is the maximum number of custom roles allowed */
            echo '<p>' . sprintf(esc_html__('Maximum %d custom roles reached.', 'maxtdesign-role-based-pricing'), 3) . '</p>';
            return;
        }
        ?>
        <h3><?php esc_html_e('Create New Role', 'maxtdesign-role-based-pricing'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="maxtdesign-rbp-role-form">
            <input type="hidden" name="action" value="maxtdesign_rbp_create_role" />
            <?php wp_nonce_field('maxtdesign_rbp_create_role'); ?>
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
            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Create Role', 'maxtdesign-role-based-pricing'); ?></button></p>
        </form>
        <?php
    }

    private function render_global_rules_card() {
        $global_rules = $this->core->get_all_global_rules();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only edit-state selection.
        $editing_id   = isset($_GET['edit_global_rule']) ? absint(wp_unslash($_GET['edit_global_rule'])) : 0;
        $editing_rule = null;
        if ($editing_id > 0) {
            foreach ($global_rules as $rule) {
                if ((int) $rule['id'] === $editing_id) {
                    $editing_rule = $rule;
                    break;
                }
            }
        }

        $this->card_open(__('Global Pricing Rules', 'maxtdesign-role-based-pricing'));
        echo '<p>' . esc_html__('Global pricing rules apply to all products by default. Product-specific rules will override global rules.', 'maxtdesign-role-based-pricing') . '</p>';

        if (empty($global_rules)) {
            $this->empty_state(
                __('No global pricing rules yet', 'maxtdesign-role-based-pricing'),
                __('Add a rule below to give a role a storewide discount, or use "Create Global Rules for All Roles" to start every role at 10% off.', 'maxtdesign-role-based-pricing')
            );
        } else {
            echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Global Pricing Rules', 'maxtdesign-role-based-pricing') . '">';
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>'
                . '<th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Status', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Actions', 'maxtdesign-role-based-pricing') . '</th>'
                . '</tr></thead><tbody>';
            foreach ($global_rules as $rule) {
                list($discount_type_display, $discount_value_display) = $this->get_discount_display($rule);
                echo '<tr>'
                    . '<td>' . esc_html($this->get_role_display_name($rule['role_name'])) . '</td>'
                    . '<td>' . esc_html($discount_type_display) . '</td>'
                    . '<td>' . wp_kses_post($discount_value_display) . '</td>'
                    . '<td>' . wp_kses_post($this->badge($rule['is_active'] ? 'good' : 'neutral', $rule['is_active'] ? __('Active', 'maxtdesign-role-based-pricing') : __('Inactive', 'maxtdesign-role-based-pricing'))) . '</td>'
                    . '<td class="md-rbp-actions">';
                echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('page' => self::PAGE_SLUG, 'edit_global_rule' => (int) $rule['id']), admin_url('admin.php')) . '#maxtdesign-rbp-global-rule-form') . '">' . esc_html__('Edit', 'maxtdesign-role-based-pricing') . '</a> ';
                $this->row_action_form(
                    'maxtdesign_rbp_toggle_global_rule',
                    array('rule_id' => (int) $rule['id']),
                    $rule['is_active'] ? __('Deactivate', 'maxtdesign-role-based-pricing') : __('Activate', 'maxtdesign-role-based-pricing')
                );
                $this->row_action_form(
                    'maxtdesign_rbp_delete_global_rule',
                    array('rule_id' => (int) $rule['id']),
                    __('Delete', 'maxtdesign-role-based-pricing'),
                    __('Delete this global pricing rule? Customers in this role return to regular prices.', 'maxtdesign-role-based-pricing')
                );
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        $this->render_global_rule_form($editing_rule);

        // Bulk helper: seed a default rule for every role that has none.
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="md-rbp-row-action">';
        echo '<input type="hidden" name="action" value="maxtdesign_rbp_create_default_rules" />';
        wp_nonce_field('maxtdesign_rbp_create_default_rules');
        echo '<p><button type="submit" class="button">' . esc_html__('Create Global Rules for All Roles', 'maxtdesign-role-based-pricing') . '</button> '
            . '<span class="description">' . esc_html__('Adds a 10% rule for every role that has no global rule yet.', 'maxtdesign-role-based-pricing') . '</span></p>';
        echo '</form>';

        $this->card_close();
    }

    /**
     * Add/Edit form for a global rule. Editing reuses the same form, prefilled,
     * with the rule id carried in a hidden field (kills the old jQuery modal).
     *
     * @param array|null $editing_rule Rule row being edited, or null for add mode.
     */
    private function render_global_rule_form($editing_rule) {
        $is_edit = is_array($editing_rule);

        $role_options = array();
        if (!$is_edit) {
            $used_roles = array();
            foreach ($this->core->get_all_global_rules() as $rule) {
                if (1 === (int) $rule['is_active']) {
                    $used_roles[] = $rule['role_name'];
                }
            }
            foreach ($this->core->get_all_roles() as $role_name => $role_data) {
                if (!in_array($role_name, $used_roles, true)) {
                    $role_options[$role_name] = $role_data['display_name'];
                }
            }
            if (empty($role_options)) {
                echo '<p>' . esc_html__('All available roles already have global pricing rules.', 'maxtdesign-role-based-pricing') . '</p>';
                return;
            }
        }

        $type  = $is_edit ? $editing_rule['discount_type'] : 'percentage';
        $value = $is_edit ? $editing_rule['discount_value'] : '';
        ?>
        <h3 id="maxtdesign-rbp-global-rule-form"><?php echo $is_edit ? esc_html__('Edit Global Pricing Rule', 'maxtdesign-role-based-pricing') : esc_html__('Add Global Pricing Rule', 'maxtdesign-role-based-pricing'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="maxtdesign-rbp-global-form">
            <input type="hidden" name="action" value="maxtdesign_rbp_save_global_rule" />
            <?php wp_nonce_field('maxtdesign_rbp_save_global_rule'); ?>
            <?php if ($is_edit) : ?>
                <input type="hidden" name="rule_id" value="<?php echo esc_attr((string) (int) $editing_rule['id']); ?>" />
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="global_role_name"><?php esc_html_e('User Role', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td>
                        <?php if ($is_edit) : ?>
                            <strong><?php echo esc_html($this->get_role_display_name($editing_rule['role_name'])); ?></strong>
                            <p class="description"><?php esc_html_e('Role name cannot be changed.', 'maxtdesign-role-based-pricing'); ?></p>
                        <?php else : ?>
                            <select id="global_role_name" name="role_name" required>
                                <option value=""><?php esc_html_e('Select a role...', 'maxtdesign-role-based-pricing'); ?></option>
                                <?php foreach ($role_options as $value_slug => $label) : ?>
                                    <option value="<?php echo esc_attr($value_slug); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select the user role for this global pricing rule.', 'maxtdesign-role-based-pricing'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="global_discount_type"><?php esc_html_e('Discount Type', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td>
                        <select id="global_discount_type" name="discount_type" required>
                            <option value="percentage" <?php selected($type, 'percentage'); ?>><?php esc_html_e('Percentage', 'maxtdesign-role-based-pricing'); ?></option>
                            <option value="fixed" <?php selected($type, 'fixed'); ?>><?php esc_html_e('Amount Off', 'maxtdesign-role-based-pricing'); ?></option>
                            <option value="fixed_price" <?php selected($type, 'fixed_price'); ?>><?php esc_html_e('Set Price', 'maxtdesign-role-based-pricing'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Set Price uses an exact price for all products. Use with caution.', 'maxtdesign-role-based-pricing'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="global_discount_value"><?php esc_html_e('Discount Value', 'maxtdesign-role-based-pricing'); ?></label></th>
                    <td>
                        <input type="number" id="global_discount_value" name="discount_value" step="0.01" min="0" value="<?php echo esc_attr($value); ?>" required />
                        <p class="description"><?php esc_html_e('Enter the discount value (percentage or amount).', 'maxtdesign-role-based-pricing'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__('Update Rule', 'maxtdesign-role-based-pricing') : esc_html__('Add Global Rule', 'maxtdesign-role-based-pricing'); ?></button>
                <?php if ($is_edit) : ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>"><?php esc_html_e('Cancel', 'maxtdesign-role-based-pricing'); ?></a>
                <?php endif; ?>
            </p>
        </form>
        <?php
    }

    private function render_product_rules_card() {
        $all_rules = $this->core->get_rules();

        $this->card_open(__('Product-Specific Rules', 'maxtdesign-role-based-pricing'));
        if (empty($all_rules)) {
            $this->empty_state(
                __('No product-specific rules yet', 'maxtdesign-role-based-pricing'),
                __('Product rules override global rules for individual products. Add them from the Role-Based Pricing box on any product edit screen.', 'maxtdesign-role-based-pricing')
            );
        } else {
            echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Product-Specific Rules', 'maxtdesign-role-based-pricing') . '">';
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>'
                . '<th>' . esc_html__('Product', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Created', 'maxtdesign-role-based-pricing') . '</th>'
                . '</tr></thead><tbody>';
            foreach ($all_rules as $rule) {
                $product      = wc_get_product($rule['product_id']);
                $product_name = $product ? $product->get_name() : __('Product not found', 'maxtdesign-role-based-pricing');
                list($discount_type_display, $discount_value_display) = $this->get_discount_display($rule);
                $edit_link = $product ? get_edit_post_link($product->is_type('variation') ? $product->get_parent_id() : $rule['product_id']) : '';
                echo '<tr><td>' . ($edit_link ? '<a href="' . esc_url($edit_link) . '">' . esc_html($product_name) . '</a>' : esc_html($product_name)) . '</td>'
                    . '<td>' . esc_html($this->get_role_display_name($rule['role_name'])) . '</td>'
                    . '<td>' . esc_html($discount_type_display) . '</td>'
                    . '<td>' . wp_kses_post($discount_value_display) . '</td>'
                    . '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($rule['created_at']))) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        $this->card_close();
    }

    private function render_cache_card() {
        $cache_health = $this->core->get_cache_health();

        $this->card_open(__('Cache', 'maxtdesign-role-based-pricing'));

        echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Cache Status', 'maxtdesign-role-based-pricing') . '">';
        echo '<table class="widefat"><tbody>';
        echo '<tr><td><strong>' . esc_html__('Cache Method', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html(ucfirst($cache_health['method'])) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Object Cache Available', 'maxtdesign-role-based-pricing') . '</strong></td><td>'
            . wp_kses_post($this->badge($cache_health['object_cache_available'] ? 'good' : 'neutral', $cache_health['object_cache_available'] ? __('Yes', 'maxtdesign-role-based-pricing') : __('No', 'maxtdesign-role-based-pricing'))) . '</td></tr>';
        $healthy = !empty($cache_health['object_cache_healthy']);
        echo '<tr><td><strong>' . esc_html__('Object Cache Health', 'maxtdesign-role-based-pricing') . '</strong></td><td>'
            . wp_kses_post($this->badge($healthy ? 'good' : 'warn', $healthy ? __('Healthy', 'maxtdesign-role-based-pricing') : __('Unhealthy / fallback active', 'maxtdesign-role-based-pricing'))) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Estimated Cache Entries', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($cache_health['estimated_entries']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Last Cache Clear', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($cache_health['last_cleared'] ? $cache_health['last_cleared'] : __('Never', 'maxtdesign-role-based-pricing')) . '</td></tr>';
        echo '</tbody></table></div>';

        $this->row_action_form(
            'maxtdesign_rbp_clear_cache',
            array(),
            __('Clear All Cache', 'maxtdesign-role-based-pricing'),
            __('Clear every cached role price? Prices recalculate on the next visit.', 'maxtdesign-role-based-pricing')
        );
        $this->row_action_form(
            'maxtdesign_rbp_warm_cache',
            array(),
            __('Warm Cache', 'maxtdesign-role-based-pricing')
        );

        $this->card_close();
    }

    private function render_performance_section() {
        if (!defined('MAXTDESIGN_RBP_PERFORMANCE_MONITORING') || !MAXTDESIGN_RBP_PERFORMANCE_MONITORING) {
            $this->card_open(__('Performance Monitoring', 'maxtdesign-role-based-pricing'));
            echo '<p>' . esc_html__('Performance monitoring is currently disabled to optimize plugin performance.', 'maxtdesign-role-based-pricing') . '</p>';
            echo '<p><strong>' . esc_html__('To enable detailed performance statistics:', 'maxtdesign-role-based-pricing') . '</strong></p>';
            echo '<ol><li>' . esc_html__('Add this line to your wp-config.php file:', 'maxtdesign-role-based-pricing') . '</li>';
            echo '<li><code>define(\'MAXTDESIGN_RBP_PERFORMANCE_MONITORING\', true);</code></li>';
            echo '<li>' . esc_html__('Save the file and reload this page', 'maxtdesign-role-based-pricing') . '</li></ol>';
            echo '<p><em>' . esc_html__('Note: Performance monitoring is intended for development and troubleshooting purposes.', 'maxtdesign-role-based-pricing') . '</em></p>';
            $this->card_close();
            return;
        }

        $db_health      = $this->core->check_database_health();
        $db_performance = $this->core->get_database_performance_stats();
        $hook_stats     = $this->core->get_hook_performance_stats();

        $this->card_open(__('Database Performance', 'maxtdesign-role-based-pricing'));
        echo '<p><strong>' . esc_html__('Status:', 'maxtdesign-role-based-pricing') . '</strong> '
            . wp_kses_post($this->badge('healthy' === $db_health['status'] ? 'good' : 'warn', ucfirst($db_health['status']))) . '</p>';
        if (!empty($db_health['issues'])) {
            echo '<ul>';
            foreach ($db_health['issues'] as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
        }
        echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Index Status', 'maxtdesign-role-based-pricing') . '">';
        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Table', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Index', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Status', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
        foreach ($db_health['index_status'] as $table => $indexes) {
            foreach ($indexes as $index => $exists) {
                echo '<tr><td>' . esc_html($table) . '</td><td>' . esc_html($index) . '</td><td>'
                    . wp_kses_post($this->badge($exists ? 'good' : 'bad', $exists ? __('Present', 'maxtdesign-role-based-pricing') : __('Missing', 'maxtdesign-role-based-pricing'))) . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
        echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Query Performance', 'maxtdesign-role-based-pricing') . '">';
        echo '<table class="widefat"><tbody>'
            . '<tr><td><strong>' . esc_html__('Total Queries Logged', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['total_queries']) . '</td></tr>'
            . '<tr><td><strong>' . esc_html__('Slow Queries', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['slow_queries']) . '</td></tr>'
            . '<tr><td><strong>' . esc_html__('Average Execution Time', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['average_execution_time']) . 's</td></tr>'
            . '<tr><td><strong>' . esc_html__('Slowest Query Time', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($db_performance['slowest_query_time']) . 's</td></tr>'
            . '</tbody></table></div>';
        $this->row_action_form('maxtdesign_rbp_add_db_indexes', array(), __('Add Missing Indexes', 'maxtdesign-role-based-pricing'));
        $this->card_close();

        $this->card_open(__('Hook Performance', 'maxtdesign-role-based-pricing'));
        echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Hook Statistics', 'maxtdesign-role-based-pricing') . '">';
        echo '<table class="widefat"><tbody>'
            . '<tr><td><strong>' . esc_html__('Total Pages Monitored', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($hook_stats['total_pages']) . '</td></tr>'
            . '<tr><td><strong>' . esc_html__('Last Updated', 'maxtdesign-role-based-pricing') . '</strong></td><td>' . esc_html($hook_stats['last_updated'] ? $hook_stats['last_updated'] : __('Never', 'maxtdesign-role-based-pricing')) . '</td></tr>'
            . '</tbody></table></div>';
        if (!empty($hook_stats['most_accessed_pages'])) {
            echo '<div class="md-suite-table-region" role="region" tabindex="0" aria-label="' . esc_attr__('Most Accessed Pages', 'maxtdesign-role-based-pricing') . '">';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Page', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Hook Executions', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            foreach ($hook_stats['most_accessed_pages'] as $page_id => $count) {
                $page_title = get_the_title($page_id);
                $page_title = $page_title ? $page_title : __('Unknown Page', 'maxtdesign-role-based-pricing');
                echo '<tr><td>' . esc_html($page_id) . ' - ' . esc_html($page_title) . '</td><td>' . esc_html($count) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        $this->row_action_form(
            'maxtdesign_rbp_clear_hook_stats',
            array(),
            __('Clear Statistics', 'maxtdesign-role-based-pricing'),
            __('Clear all recorded hook performance statistics?', 'maxtdesign-role-based-pricing')
        );
        $this->card_close();
    }

    /* ---------------------------------------------------------------------
     * Product metabox (product editor; persists on save_post_product).
     * ------------------------------------------------------------------- */

    public function add_product_meta_box() {
        add_meta_box('maxtdesign-rbp-pricing', __('Role-Based Pricing', 'maxtdesign-role-based-pricing'), array($this, 'product_meta_box_content'), 'product', 'normal', 'high');
    }

    public function product_meta_box_content($post) {
        $product_id = $post->ID;
        $product    = wc_get_product($product_id);
        if (!$product || !$product->exists()) {
            echo '<p>' . esc_html__('Product not found.', 'maxtdesign-role-based-pricing') . '</p>';
            return;
        }

        wp_nonce_field('maxtdesign_rbp_metabox', 'maxtdesign_rbp_metabox_nonce');

        $product_ids     = array($product_id);
        $is_variable     = $product->is_type('variable');
        $variations_data = array();
        if ($is_variable) {
            foreach ($product->get_available_variations() as $v) {
                $vid = isset($v['variation_id']) ? $v['variation_id'] : 0;
                if ($vid) {
                    $product_ids[]         = $vid;
                    $var_product           = wc_get_product($vid);
                    /* translators: %d is the variation ID */
                    $variations_data[$vid] = $var_product ? $var_product->get_name() : sprintf(__('Variation #%d', 'maxtdesign-role-based-pricing'), $vid);
                }
            }
        }
        $existing_rules = $this->core->get_rules(array('product_ids' => $product_ids));
        $global_rules   = $this->core->get_all_global_rules();

        echo '<div class="maxtdesign-rbp-product-meta">';
        echo '<p class="description">' . esc_html__('Changes below are applied when you update the product.', 'maxtdesign-role-based-pricing') . '</p>';

        // Global rules that apply to this product by default.
        $active_global_rules = array_filter($global_rules, function ($rule) {
            return $rule['is_active'];
        });
        if (!empty($active_global_rules)) {
            echo '<h4>' . esc_html__('Global Pricing Rules (Apply by Default)', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th><th>' . esc_html__('Status', 'maxtdesign-role-based-pricing') . '</th></tr></thead><tbody>';
            foreach ($active_global_rules as $rule) {
                $has_override = false;
                foreach ($existing_rules as $existing_rule) {
                    if ($existing_rule['role_name'] === $rule['role_name']) {
                        $has_override = true;
                        break;
                    }
                }
                list($discount_type_display, $discount_value_display) = $this->get_discount_display($rule);
                echo '<tr><td>' . esc_html($this->get_role_display_name($rule['role_name'])) . '</td>'
                    . '<td>' . esc_html($discount_type_display) . '</td>'
                    . '<td>' . wp_kses_post($discount_value_display) . '</td>'
                    . '<td>' . wp_kses_post($this->badge($has_override ? 'warn' : 'good', $has_override ? __('Overridden', 'maxtdesign-role-based-pricing') : __('Active', 'maxtdesign-role-based-pricing'))) . '</td></tr>';
            }
            echo '</tbody></table><br>';
        }

        // Product-specific rules: editable inline, applied on product update.
        if (!empty($existing_rules)) {
            echo '<h4>' . esc_html__('Product-Specific Pricing Rules (Override Global)', 'maxtdesign-role-based-pricing') . '</h4>';
            echo '<table class="widefat"><thead><tr>'
                . '<th>' . esc_html__('Role', 'maxtdesign-role-based-pricing') . '</th>'
                . ($is_variable ? '<th>' . esc_html__('Applies To', 'maxtdesign-role-based-pricing') . '</th>' : '')
                . '<th>' . esc_html__('Discount Type', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Discount Value', 'maxtdesign-role-based-pricing') . '</th>'
                . '<th>' . esc_html__('Remove', 'maxtdesign-role-based-pricing') . '</th>'
                . '</tr></thead><tbody>';
            foreach ($existing_rules as $rule) {
                $rid  = (int) $rule['id'];
                $name = 'maxtdesign_rbp_rule[' . $rid . ']';
                echo '<tr><td>' . esc_html($this->get_role_display_name($rule['role_name'])) . '</td>';
                if ($is_variable) {
                    echo '<td>' . esc_html($rule['product_id'] == $product_id ? __('All variations', 'maxtdesign-role-based-pricing') : ($variations_data[$rule['product_id']] ?? '#' . $rule['product_id'])) . '</td>';
                }
                echo '<td><select name="' . esc_attr($name . '[discount_type]') . '">'
                    . '<option value="percentage" ' . selected($rule['discount_type'], 'percentage', false) . '>' . esc_html__('Percentage', 'maxtdesign-role-based-pricing') . '</option>'
                    . '<option value="fixed" ' . selected($rule['discount_type'], 'fixed', false) . '>' . esc_html__('Amount Off', 'maxtdesign-role-based-pricing') . '</option>'
                    . '<option value="fixed_price" ' . selected($rule['discount_type'], 'fixed_price', false) . '>' . esc_html__('Set Price', 'maxtdesign-role-based-pricing') . '</option>'
                    . '</select></td>';
                echo '<td><input type="number" step="0.01" min="0" name="' . esc_attr($name . '[discount_value]') . '" value="' . esc_attr($rule['discount_value']) . '" /></td>';
                echo '<td><label><input type="checkbox" name="' . esc_attr($name . '[delete]') . '" value="1" /> ' . esc_html__('Delete', 'maxtdesign-role-based-pricing') . '</label></td>';
                echo '</tr>';
            }
            echo '</tbody></table><br>';
        }

        // Add-new block.
        $all_roles    = $this->core->get_all_roles();
        $role_options = array('' => __('Select a role...', 'maxtdesign-role-based-pricing'));
        foreach ($all_roles as $role_name => $role_data) {
            $role_options[$role_name] = $role_data['display_name'];
        }

        echo '<h4>' . esc_html__('Add New Pricing Rule', 'maxtdesign-role-based-pricing') . '</h4>';
        if ($is_variable) {
            $target_options = array($product_id => __('All variations', 'maxtdesign-role-based-pricing'));
            foreach ($variations_data as $vid => $vname) {
                $target_options[$vid] = $vname;
            }
            woocommerce_wp_select(array('id' => 'maxtdesign_rbp_new_target', 'name' => 'maxtdesign_rbp_new[target]', 'label' => __('Apply to', 'maxtdesign-role-based-pricing'), 'options' => $target_options, 'desc_tip' => true, 'description' => __('Apply this rule to all variations or a specific variation.', 'maxtdesign-role-based-pricing')));
        }
        woocommerce_wp_select(array('id' => 'maxtdesign_rbp_new_role', 'name' => 'maxtdesign_rbp_new[role_name]', 'label' => __('User Role', 'maxtdesign-role-based-pricing'), 'options' => $role_options, 'desc_tip' => true, 'description' => __('Select the user role for this pricing rule.', 'maxtdesign-role-based-pricing')));
        woocommerce_wp_select(array('id' => 'maxtdesign_rbp_new_type', 'name' => 'maxtdesign_rbp_new[discount_type]', 'label' => __('Discount Type', 'maxtdesign-role-based-pricing'), 'options' => array('percentage' => __('Percentage', 'maxtdesign-role-based-pricing'), 'fixed' => __('Amount Off', 'maxtdesign-role-based-pricing'), 'fixed_price' => __('Set Price', 'maxtdesign-role-based-pricing')), 'desc_tip' => true, 'description' => __('Set Price uses an exact price regardless of regular price.', 'maxtdesign-role-based-pricing')));
        woocommerce_wp_text_input(array('id' => 'maxtdesign_rbp_new_value', 'name' => 'maxtdesign_rbp_new[discount_value]', 'label' => __('Discount Value', 'maxtdesign-role-based-pricing'), 'type' => 'number', 'custom_attributes' => array('step' => '0.01', 'min' => '0'), 'desc_tip' => true, 'description' => __('Percentage off, amount to subtract, or exact price (for Set Price). Leave the role unselected to skip.', 'maxtdesign-role-based-pricing')));

        echo '</div>';
    }

    /**
     * Persist metabox rule edits when the product saves. PRG comes for free:
     * the product editor already redirects after save.
     *
     * @param int     $post_id Product ID.
     * @param WP_Post $post    Post object.
     */
    public function save_product_meta_box($post_id, $post) {
        $nonce = isset($_POST['maxtdesign_rbp_metabox_nonce']) ? sanitize_text_field(wp_unslash($_POST['maxtdesign_rbp_metabox_nonce'])) : '';
        if ('' === $nonce || !wp_verify_nonce($nonce, 'maxtdesign_rbp_metabox')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || !current_user_can('manage_woocommerce') || !current_user_can('edit_post', $post_id)) {
            return;
        }

        // Updates + deletes for existing rules.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.
        $edits = isset($_POST['maxtdesign_rbp_rule']) && is_array($_POST['maxtdesign_rbp_rule']) ? wp_unslash($_POST['maxtdesign_rbp_rule']) : array();
        foreach ($edits as $rule_id => $fields) {
            $rule_id = absint($rule_id);
            if ($rule_id <= 0 || !is_array($fields)) {
                continue;
            }
            if (!empty($fields['delete'])) {
                $rule_rows = $this->core->get_rules(array('id' => $rule_id));
                $rule_row  = !empty($rule_rows) ? $rule_rows[0] : null;
                if ($this->core->delete_rule($rule_id)) {
                    do_action('maxtdesign_rbp_after_rule_deleted', $rule_id, $rule_row);
                }
                continue;
            }
            $type  = isset($fields['discount_type']) ? sanitize_text_field($fields['discount_type']) : '';
            $value = isset($fields['discount_value']) ? floatval($fields['discount_value']) : 0;
            if ($this->is_valid_discount($type, $value)) {
                $this->core->update_rule($rule_id, array(
                    'discount_type'  => $type,
                    'discount_value' => $value,
                ));
            }
        }

        // Add-new rule (skipped unless a role was chosen).
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.
        $new = isset($_POST['maxtdesign_rbp_new']) && is_array($_POST['maxtdesign_rbp_new']) ? wp_unslash($_POST['maxtdesign_rbp_new']) : array();
        $role = isset($new['role_name']) ? sanitize_text_field($new['role_name']) : '';
        if ('' !== $role) {
            $type   = isset($new['discount_type']) ? sanitize_text_field($new['discount_type']) : '';
            $value  = isset($new['discount_value']) ? floatval($new['discount_value']) : 0;
            $target = isset($new['target']) ? absint($new['target']) : $post_id;
            if ($target <= 0) {
                $target = $post_id;
            }
            if ($this->is_valid_discount($type, $value) && wc_get_product($target)) {
                $rule_data = array(
                    'role_name'      => $role,
                    'product_id'     => $target,
                    'discount_type'  => $type,
                    'discount_value' => $value,
                );
                $rule_id = $this->core->create_rule($rule_data);
                if ($rule_id) {
                    do_action('maxtdesign_rbp_after_rule_created', $rule_id, $rule_data);
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     * Shared display helpers + default-rule seeding.
     * ------------------------------------------------------------------- */

    /**
     * Get display strings for a pricing rule's discount type and value.
     *
     * @param array $rule Rule with discount_type and discount_value.
     * @return array [type_display, value_display]
     */
    private function get_discount_display($rule) {
        $type  = $rule['discount_type'] ?? 'percentage';
        $value = $rule['discount_value'] ?? 0;
        if ('fixed_price' === $type) {
            return array(__('Set Price', 'maxtdesign-role-based-pricing'), wc_price($value));
        }
        if ('percentage' === $type) {
            return array(__('Percentage', 'maxtdesign-role-based-pricing'), $value . '%');
        }
        return array(__('Amount Off', 'maxtdesign-role-based-pricing'), wc_price($value));
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

    /**
     * Seed a default 10% global rule for every role that has none.
     *
     * @return int Number of rules created.
     */
    public function create_default_global_rules() {
        $created = 0;
        foreach ($this->core->get_all_roles() as $role_name => $role_data) {
            if ($this->core->get_global_rule($role_name)) {
                continue;
            }
            $result = $this->core->create_global_rule(array(
                'role_name'      => $role_name,
                'discount_type'  => 'percentage',
                'discount_value' => 10.00,
                'is_active'      => 1,
            ));
            if ($result) {
                $created++;
            }
        }
        $this->core->clear_all_cache();
        return $created;
    }
}
