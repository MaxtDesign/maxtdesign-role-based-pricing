# MaxtDesign Role-Based Pricing for WooCommerce

> **Version:** 1.1.0  
> **Requires:** WordPress 6.2+, WooCommerce 5.0+, PHP 7.4+  
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

## 📚 Full Documentation

### Getting Started
- [Installation Guide](docs/getting-started/installation.md) - Detailed installation and system requirements
- [Initial Setup](docs/getting-started/initial-setup.md) - Complete configuration walkthrough
- [Your First Pricing Rule](docs/getting-started/first-pricing-rule.md) - Step-by-step guide
- [Common Configurations](docs/getting-started/common-configurations.md) - Real-world examples

### Features
- [Global Pricing Rules](docs/features/global-pricing-rules.md) - Site-wide discounts
- [Product-Specific Pricing](docs/features/product-specific-pricing.md) - Override global rules
- [Role Management](docs/features/role-management.md) - Create and manage customer groups
- [Frontend Display](docs/features/frontend-display.md) - How prices appear to customers
- [Sale Badge Handling](docs/features/sale-badge-handling.md) - Compatibility with sale prices

### Advanced Usage
- [Hooks and Filters](docs/advanced/hooks-and-filters.md) - Developer customization
- [Custom Integrations](docs/advanced/custom-integrations.md) - Extend functionality
- [Performance Optimization](docs/advanced/performance-optimization.md) - Speed tuning
- [Caching System](docs/advanced/caching-system.md) - How caching works
- [HPOS Compatibility](docs/advanced/hpos-compatibility.md) - High-Performance Order Storage

### For Developers
- [Architecture Overview](docs/developers/architecture-overview.md) - Plugin structure
- [Database Schema](docs/developers/database-schema.md) - Table structure and indexes
- [Code Examples](docs/developers/code-examples.md) - Practical code snippets
- [Extending the Plugin](docs/developers/extending-the-plugin.md) - Build custom features
- [Testing Guidelines](docs/developers/testing-guidelines.md) - Testing best practices

### Troubleshooting
- [Common Issues](docs/troubleshooting/common-issues.md) - Frequently encountered problems
- [Debugging Guide](docs/troubleshooting/debugging-guide.md) - Enable debug mode
- [Cache Problems](docs/troubleshooting/cache-problems.md) - Cache-related issues
- [Price Not Showing](docs/troubleshooting/price-not-showing.md) - Price display issues
- [Variable Products](docs/troubleshooting/variable-products.md) - Variation-specific issues

### Reference
- [Settings Reference](docs/reference/settings-reference.md) - All plugin settings
- [Capability Reference](docs/reference/capability-reference.md) - WordPress capabilities
- [Filter Reference](docs/reference/filter-reference.md) - All available filters
- [Action Reference](docs/reference/action-reference.md) - All available actions

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
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

**Recommended:**
- WordPress 6.9+
- WooCommerce 8.5+
- PHP 8.0+
- MySQL 8.0+ or MariaDB 10.5+
- Object caching (Redis/Memcached) for optimal performance

**Hosting:**
- Works on shared hosting, VPS, and dedicated servers
- No special server requirements
- Compatible with popular hosts (SiteGround, Kinsta, WP Engine, etc.)

---

## 🤝 Support

### Documentation
All documentation is available in the [docs](docs/) folder with detailed guides, code examples, and troubleshooting steps.

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

### 1.1.0 (Current)
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

