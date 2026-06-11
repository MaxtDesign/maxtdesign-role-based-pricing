=== MaxtDesign Role-Based Pricing for WooCommerce ===
Contributors: slaacr
Donate link: https://github.com/sponsors/MaxtDesign
Tags: woocommerce, pricing, wholesale, discounts, membership
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.1.3
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 10.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free role-based pricing for WooCommerce. Create customer groups and apply percentage off, amount off, or exact set-price rules across your catalog.

== Description ==

Transform your WooCommerce store with role-based pricing. This free plugin lets you create price rules for different customer groups using percentage, amount-off, or set-price rules — no subscriptions, no upsells, and no complex setup.

**Perfect for:**
* Wholesale and B2B businesses with volume pricing
* Membership sites with tier pricing levels
* Multi-tier customer loyalty programs
* Professional service pricing structures
* Stores offering bulk discounts and group rates

**Why Choose This Plugin:**
* **Completely Free** - No subscriptions, upsells, or hidden limits
* **Flexible Pricing Rules** - Percentage off, amount off, or set price per role
* **Built-in Performance Optimization** - Advanced caching prevents slowdowns
* **No External Dependencies** - Self-contained with no API requirements
* **Professional Grade** - Enterprise-level features without enterprise costs

**Key Features:**
* **Role-Based Pricing Rules** - Set global rules; product-level overrides supported
* **Three Discount Types** - Percentage off, amount off, or set exact price
* **Customer Group Management** - Create up to 3 custom user roles
* **Optimized Performance** - Built-in caching with automatic optimization
* **WooCommerce HPOS Compatible** - Full support for High-Performance Order Storage

**Discount Types Explained:**
* **Percentage** - Subtract X% from the price (e.g. 20% off $100 = $80)
* **Amount Off** - Subtract a fixed amount (e.g. $10 off $50 = $40)
* **Set Price** - Replace the price with an exact amount (e.g. set to $35 regardless of regular price). Ideal for variation-specific wholesale prices.

**How Membership Pricing Works:**
1. Create customer groups or use existing WordPress roles
2. Set global pricing rules that apply to all products
3. Override global rules with product-specific tier pricing when needed
4. Customers see their group pricing automatically when logged in
5. Original prices shown with strikethrough for transparency

**Professional Performance Features:**
* **Smart Caching System** - Automatic cache detection with object cache and transient fallbacks
* **Performance Monitoring** - Optional detailed tracking for optimization (developer tool)
* **Database Optimization** - Indexed tables for lightning-fast queries
* **Security First Design** - Comprehensive input validation and sanitization
* **Error-Proof Operation** - Graceful handling ensures pricing never breaks
* **Multisite Ready** - Works seamlessly in WordPress multisite environments

**Competitive Advantages:**
Free alternative to paid pricing plugins. No subscriptions, no usage caps, and no external API dependencies.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/maxtdesign-role-based-pricing` directory, or install through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce is installed and activated (required)
4. Navigate to WooCommerce > Role-Based Pricing to configure your pricing rules
5. Create customer groups or use existing WordPress roles
6. Set up global pricing rules or optional product-specific overrides

**System Requirements:**
* WordPress 6.2 or higher
* WooCommerce 7.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher

== Frequently Asked Questions ==

= How do I set up membership pricing tiers? =

Navigate to WooCommerce > Role-Based Pricing, create custom roles for each tier (Bronze, Silver, Gold), then set percentage off, amount off, or exact set price for each membership level. Members automatically see their tier pricing when logged in.

= Can I offer discounts based on customer groups? =

Yes. Create a role (e.g., Wholesale) and assign a percentage off, amount off, or set price. You can also set product-specific overrides when needed.

= Does it work for customer groups and price levels? =

Yes. You can use existing WordPress roles or create up to 3 custom roles. Each role can have its own discount, and product-level overrides are supported.

= How do I create volume discounts for wholesale customers? =

Create a "Wholesale" customer group, assign a discount (e.g., 25% off, $5 off, or set price to $50), and assign wholesale customers to this role. They'll automatically see discounted prices on all products.

= What's the performance impact on my store? =

The plugin is optimized for high performance with advanced caching, indexed database queries, and smart hook loading. Most stores see no noticeable impact, and some see improvements due to efficient pricing calculations.

= Is it compatible with variable products and variations? =

Yes. You can apply rules to all variations or to individual variations. Use "Set Price" to give a specific variation an exact price (e.g. $35) regardless of its regular price. Use "Amount Off" to subtract a fixed amount, or "Percentage" for percentage discounts.

= Can I override global pricing for specific products? =

Absolutely. Product-specific pricing rules always override global rules. For variable products, you can apply rules to all variations or choose a specific variation. Variation-specific rules override parent rules.

= Does it work with other WooCommerce plugins? =

Yes, it's designed for maximum compatibility using standard WooCommerce hooks and WordPress coding standards. Works alongside most WooCommerce extensions.

= Can I customize the member price color? =

Yes! By default, member prices use your theme's color. To customize, go to Appearance > Customize > Additional CSS and add: `.maxtdesign-rbp-price .maxtdesign-rbp-member { color: #your-color !important; }`. See CUSTOMIZATION-GUIDE.md for examples.

= How does it compare to subscription-based pricing plugins? =

This plugin is completely free, with no recurring fees and no external API requirements.

= Is technical support included? =

Community support is available through the WordPress.org forums.

== Screenshots ==

1. **Admin Dashboard** - Role management and pricing rules overview
2. **Shop Page** - Frontend display showing member pricing
3. **Product Page** - Single product with role-based pricing
4. **Variable Product** - Variations with role-based pricing
5. **Cart Page** - Cart totals reflecting role-based discounts

== Changelog ==

= 1.1.3 =
* Changed: plugin homepage (Plugin URI) now points to the official plugin page at maxtdesign.com/plugins/role-based-pricing
* Changed: donate link standardized to github.com/sponsors/MaxtDesign

= 1.1.2 =
* Fixed: missing `translators:` comment on a `Variation #%d` placeholder string in the product meta box
* Fixed: three admin-side `echo` statements (status badge, variation cell, variation column header) now pass through `wp_kses_post()` for explicit-by-construction safety. No behaviour change — values were already internally built — but the safety guarantee is now local to each echo, satisfying Plugin Check
* Hardened: `phpcs:ignore` annotations on the table-management queries fixed in 1.1.1 now also list `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` and `PluginCheck.Security.DirectDB.UnescapedDBParameter`. Table identifiers are still hardcoded class properties; only the lint-suppression metadata changed
* `error_log()` calls (all already gated behind `WP_DEBUG`) carry explicit `phpcs:ignore` annotations documenting why they're retained
* Added `.distignore` so the WordPress.org build pipeline excludes dev-only files (`.claude/`, `.git/`, `tools/`, `node_modules/`, `package.json`, `CHANGELOG.md`, etc.) from the user-installed zip, on top of the existing `tools/prepare-svn.sh` allow-list

= 1.1.1 =
* **WordPress 7.0 "Armstrong" compatibility** - Tested up to WordPress 7.0
* **WooCommerce 10.8 compatibility** - Tested up to WooCommerce 10.8; minimum WooCommerce raised to 7.0 to align with HPOS support
* Fixed: `drop_table()`, `add_database_indexes()`, and `get_table_sizes()` queries used `%s` placeholders for table identifiers, which is invalid in `wpdb::prepare()` — these now run correctly so database health and index migration work as intended
* Fixed: Cache warming on HPOS stores — popular-products lookup now uses `wc_get_orders()` instead of the legacy post-table join, so the "Warm Cache" admin action works whether you have HPOS enabled or not
* Hardened: Transient-cleanup queries now use `wpdb::esc_like()` + prepared statements
* Readme: corrected the install-path slug and aligned System Requirements with the plugin header

= 1.1.0 =
* **Set Price** - New discount type sets an exact price (e.g. $35) regardless of regular price
* **Variation-level rules** - Apply rules to all variations or specific variations on variable products
* **Improved labels** - "Amount Off" (subtract $X) and "Set Price" (exact price) for clearer UX
* Parent rule fallback: variations inherit parent product rules when no variation-specific rule exists
* Works with global rules and product-specific rules

= 1.0.0 =
* Initial release (free)
* Global and product-specific pricing rules
* Create up to 3 custom user roles
* Advanced caching system with automatic optimization
* WooCommerce HPOS compatibility
* Multisite compatible
* Zero external dependencies

== Upgrade Notice ==

= 1.1.2 =
Plugin Check compliance pass: missing translators comment, three unescaped admin echoes, and lint-suppression cleanup. No behaviour changes.

= 1.1.1 =
WordPress 7.0 and WooCommerce 10.8 compatibility audit. Fixes broken table-management and HPOS-aware cache warming. Recommended for everyone.

= 1.1.0 =
New Set Price option for exact pricing, variation-level rules, and clearer discount type labels. Upgrade for free.

= 1.0.0 =
Launch version of Role-Based Pricing for WooCommerce. Professional membership pricing, wholesale discounts, and customer group management without monthly fees.