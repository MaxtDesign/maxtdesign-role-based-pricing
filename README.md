# MaxT Role Based Pricing

A lightweight WooCommerce plugin that provides role-based pricing with percentage or fixed amount discounts.

## Features

- **Role-Based Pricing**: Set different prices for different user roles
- **Flexible Discounts**: Support for both percentage and fixed amount discounts
- **Custom Roles**: Create up to 3 custom user roles for pricing
- **Product-Specific Rules**: Apply pricing rules to individual products
- **Caching**: Efficient caching system for better performance
- **Native WooCommerce UI**: Uses WooCommerce's native admin interface
- **Frontend Display**: Shows strikethrough original prices with member pricing

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

1. Download the plugin files
2. Upload the `maxt-rbp` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **WooCommerce > MaxT Role Pricing** to configure

## Usage

### Creating Custom Roles

1. Go to **WooCommerce > MaxT Role Pricing**
2. In the "Role Management" section, fill out the role creation form:
   - **Role Name**: Enter a name (e.g., "premium")
   - **Display Name**: Enter a display name (e.g., "Premium Customer")
3. Click "Create Role"

### Setting Up Pricing Rules

#### Method 1: Product Editor
1. Edit any WooCommerce product
2. Scroll down to the "Role-Based Pricing" meta box
3. Select a role, discount type, and discount value
4. Click "Add Pricing Rule"

#### Method 2: Settings Page
1. Go to **WooCommerce > MaxT Role Pricing**
2. View the "Pricing Rules Overview" section
3. Rules are managed through individual product pages

### Discount Types

- **Percentage**: Enter a percentage (e.g., 15 for 15% off)
- **Fixed Amount**: Enter a fixed amount (e.g., 10 for $10 off)

### Frontend Display

For logged-in users with applicable roles, prices will display as:
- ~~$50.00~~ **$42.50** (for 15% discount)
- ~~$50.00~~ **$40.00** (for $10 fixed discount)

## Default Roles

The plugin creates two default custom roles on activation:
- `maxt_rbp_wholesale` - Wholesale Customer
- `maxt_rbp_vip` - VIP Customer

## File Structure

```
maxt-rbp/
├── maxt-rbp.php              # Main plugin file
├── uninstall.php             # Uninstall script
├── README.md                 # This file
├── assets/
│   └── css/
│       └── frontend.css      # Frontend styling
└── includes/
    ├── class-core.php        # Core functionality (database, pricing, roles)
    ├── class-admin.php       # Admin interface
    └── class-frontend.php    # Frontend display
```

## Database

The plugin creates one custom table:
- `wp_maxt_rbp_rules` - Stores pricing rules

## Caching

- Pricing calculations are cached for 24 hours
- Cache is automatically cleared when rules are updated
- Cache prefix: `maxt_rbp_price_`

## Hooks and Filters

### Actions
- `maxt_rbp_after_rule_created` - After a pricing rule is created
- `maxt_rbp_after_rule_deleted` - After a pricing rule is deleted

### Filters
- `maxt_rbp_calculate_price` - Modify calculated price
- `maxt_rbp_cache_duration` - Modify cache duration (default: 86400 seconds)

## Uninstall

When the plugin is deleted:
- All custom tables are removed
- All pricing rules are deleted
- All custom roles are removed
- All cached data is cleared
- All plugin options are deleted

## Support

For support and feature requests, please visit the [GitHub repository](https://github.com/[username]/maxt-rbp).

## License

GPL v2 or later - see LICENSE file for details.

## Changelog

### 1.0.0
- Initial release
- Role-based pricing functionality
- Custom role creation
- Product-specific pricing rules
- Frontend price display with strikethrough
- Admin interface with native WooCommerce UI
- Caching system for performance
- Complete uninstall functionality