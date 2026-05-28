# MaxtDesign Role-Based Pricing for WooCommerce

> **Version:** 1.1.2  
> **Requires:** WordPress 6.2+, WooCommerce 7.0+, PHP 7.4+  
> **Tested with:** WordPress 7.0, WooCommerce 10.8  
> **License:** GPL v2 or later

Transform your WooCommerce store into a powerful B2B and wholesale platform with professional role-based pricing. Create membership tiers, wholesale discounts, and custom pricing for different user groups—no monthly fees, no subscriptions, no limits.

---

## 🚀 Quick Start

### Installation

1. Download the plugin from WordPress.org or upload the plugin files
2. Activate through **Plugins > Installed Plugins**
3. Ensure WooCommerce is installed and activated
4. Navigate to **WooCommerce > Role-Based Pricing**

### Basic Setup (5 Minutes)

1. **Create a Custom Role** (optional)
   - Go to **Role-Based Pricing > Manage Roles**
   - Click **Create Custom Role**
   - Enter: Name: `wholesale`, Display: `Wholesale Customer`

2. **Set a Global Pricing Rule**
   - Go to **Role-Based Pricing > Global Rules**
   - Select role: `Wholesale Customer`
   - Set discount: `20%` off all products
   - Click **Save Rule**

3. **Assign Users**
   - Go to **Users > All Users**
   - Edit user → Change role to **Wholesale Customer**
   - Save changes

**Done!** Your wholesale customers now see 20% off all products when logged in.

---

## ✨ Key Features

### Flexible Pricing Rules
- **Global Rules:** Apply discounts to all products for specific roles
- **Product-Specific Rules:** Override global rules for individual products
- **Multiple Discount Types:** Choose percentage or fixed amount discounts

### Customer Group Management
- Create up to 3 custom user roles (Wholesale, VIP, Dealer, etc.)
- Use existing WordPress roles (Customer, Subscriber, etc.)
- Unlimited users per role

### Professional Display
- Original prices shown with strikethrough
- Member prices highlighted in your theme's color
- Works with simple, variable, and grouped products
- Automatic price range calculation for variations

### Performance Optimized
- Advanced caching system (object cache + transient fallback)
- Database query optimization with proper indexing
- In-memory storage eliminates redundant calculations
- Zero performance impact on large catalogs

### Enterprise Features
- WooCommerce HPOS (High-Performance Order Storage) compatible
- WordPress Multisite ready
- Security-first design with comprehensive input validation
- Automatic cache clearing after orders
- Optional performance monitoring for developers

---

## 📚 Documentation & Support

User-facing documentation, FAQs, and the changelog live on the plugin's WordPress.org listing:
https://wordpress.org/plugins/maxtdesign-role-based-pricing/

For community help and bug reports, use the support forum:
https://wordpress.org/support/plugin/maxtdesign-role-based-pricing/

---

## 🎯 Common Use Cases

### Wholesale Pricing
Create a "Wholesale" customer group with 25% off all products. Perfect for B2B stores selling to retailers.

```
Role: Wholesale Customer
Global Rule: 25% discount
Result: All products show wholesale pricing
```

### Membership Tiers
Bronze (10%), Silver (15%), Gold (20%) membership levels with automatic pricing.

```
Role: Gold Member
Global Rule: 20% discount
Product Override: Premium items at 15% discount
```

### Volume Discounts
Dealer pricing with bulk discounts based on customer type.

```
Role: Authorized Dealer
Global Rule: 30% discount
Minimum Purchase: Set in WooCommerce settings
```

---

## 🛠️ System Requirements

**Minimum:**
- WordPress 6.2 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

**Recommended:**
- WordPress 7.0+
- WooCommerce 10.8+
- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.5+
- Object caching (Redis/Memcached) for optimal performance

**Hosting:**
- Works on shared hosting, VPS, and dedicated servers
- No special server requirements
- Compatible with popular hosts (SiteGround, Kinsta, WP Engine, etc.)

---

## 🤝 Support

### WordPress.org Support Forum
For general questions and community support:
https://wordpress.org/support/plugin/maxtdesign-role-based-pricing/

### Bug Reports
Found a bug? Please report it through:
- WordPress.org support forum with `[Bug Report]` tag
- Provide WordPress version, WooCommerce version, and steps to reproduce

### Feature Requests
Have an idea? Submit feature requests through:
- WordPress.org support forum with `[Feature Request]` tag
- Describe your use case and how it would help

---

## 💡 Pro Tips

1. **Test in Staging First:** Always test pricing rules in a staging environment before deploying to production
2. **Use Global Rules:** Set global rules for broad discounts, then override for specific products
3. **Cache Clearing:** Prices automatically update, but manual cache clear available in settings
4. **Role Assignment:** Use plugins like "User Role Editor" for bulk user role assignments
5. **Backup First:** Always backup your database before major changes

---

## 🔐 Security & Performance

### Security Features
- Comprehensive input validation and sanitization
- Prepared SQL statements prevent injection attacks
- Nonce verification on all admin actions
- Capability checks on all sensitive operations
- No external API dependencies

### Performance Features
- Smart caching with automatic object cache detection
- Database query optimization with proper indexes
- In-memory storage for request-scoped data
- Minimal database writes (cache and rules only)
- Compatible with page caching and CDNs

---

## 📋 Changelog

### 1.1.2 (Current)
- Plugin Check compliance pass — missing `translators:` comment, three unescaped admin echoes (`wp_kses_post()` wrap), and updated `phpcs:ignore` annotations on table-management queries
- Added `.distignore` so the WordPress.org build pipeline excludes dev-only files from the user-installed zip

### 1.1.1
- WordPress 7.0 "Armstrong" and WooCommerce 10.8 compatibility (tested-up-to bumps)
- Raised WooCommerce minimum to 7.0 to align with HPOS-era stores
- Fixed: `drop_table()`, `add_database_indexes()`, and `get_table_sizes()` queries used `%s` placeholders for table identifiers (invalid in `wpdb::prepare()`) — these now run correctly
- Fixed: HPOS-aware cache warming — popular-products lookup now uses `wc_get_orders()` instead of the legacy post-table join
- Hardened transient-cleanup queries with `wpdb::esc_like()` + prepared statements
- Readme corrections: install-path slug, System Requirements alignment

### 1.1.0
- **Set Price** - New discount type sets an exact price regardless of regular price
- **Variation-level rules** - Apply rules to all variations or specific variations
- **Clearer labels** - Amount Off and Set Price for better UX
- Parent rule fallback for variations

### 1.0.0
- Initial release with professional-grade features
- Global and product-specific pricing rules
- Customer group creation and management (up to 3 custom groups)
- Advanced caching system with automatic optimization
- Performance monitoring tools for developers
- Full WooCommerce HPOS compatibility
- Comprehensive admin interface
- Database optimization with proper indexing
- Enterprise-level security
- WordPress multisite compatibility

---

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
MaxtDesign Role-Based Pricing for WooCommerce
Copyright (C) 2024 MaxtDesign

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

---

## 🙏 Credits

**Developed by:** MaxtDesign  
**Website:** https://maxtdesign.com  
**Support:** https://wordpress.org/support/plugin/maxtdesign-role-based-pricing/

---

**Ready to transform your WooCommerce store?** Install the plugin and create your first pricing rule in under 5 minutes.

For detailed documentation, visit the [docs](docs/) folder or start with the [Installation Guide](docs/getting-started/installation.md).

