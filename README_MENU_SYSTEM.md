# USSD Database-Driven Menu System

## Overview

This USSD application now supports a **database-driven menu system** that allows you to manage menu options dynamically without touching the code. The menu system is flexible, scalable, and provides advanced features like user-specific menus, analytics, and conditional display.

## Features

### ✅ Dynamic Menu Management
- Add, edit, or remove menu items through the database
- Change menu text and options without code changes
- Reorder menu items easily

### ✅ User-Specific Menus
- Different users can see different menu options
- Hide/show menu items based on user preferences
- Support for premium vs basic user types

### ✅ Conditional Display
- Show menu items based on user balance
- Require authentication for specific options
- Minimum balance requirements per menu item

### ✅ Analytics & Tracking
- Track which menu items are most used
- Monitor user behavior and preferences
- Usage statistics and reporting

### ✅ Multi-language Support Ready
- Menu text stored in database
- Easy to add multiple language support
- Centralized text management

## Database Structure

### Tables Created

1. **`menu_categories`** - Menu categories (Main Menu, Sub-menus, etc.)
2. **`menu_items`** - Individual menu options with their properties
3. **`user_menu_preferences`** - User-specific menu customizations
4. **`menu_usage_logs`** - Analytics and usage tracking

### Key Fields in `menu_items`

| Field | Description | Example |
|-------|-------------|---------|
| `name` | Internal name | "Send Money" |
| `display_text` | Text shown to users | "Send Money" |
| `menu_number` | Menu option number | "1" |
| `action_type` | Type of action | "function", "page", "external" |
| `action_value` | What happens when selected | "send_money" |
| `min_balance` | Minimum balance required | 1.00 |
| `user_type` | User type restriction | "all", "premium", "basic" |
| `is_active` | Whether menu item is visible | 1 (true) |

## How to Use

### 1. Setup Database

Run the updated `database.sql` file to create the menu tables:

```sql
-- The database.sql file now includes menu management tables
-- Run this in your SQL Server Management Studio or phpMyAdmin
```

### 2. Access Admin Interface

Visit `admin_menu.php` in your browser to manage menus:

```
http://localhost/USSD/admin_menu.php
```

### 3. Add New Menu Items

#### Through Admin Interface:
1. Go to `admin_menu.php`
2. Fill in the "Add New Menu Item" form
3. Click "Add Menu Item"

#### Through Code:
```php
require_once 'menu_manager.php';
$menuManager = new MenuManager($conn);

$newItem = [
    'category_id' => 1,
    'name' => 'New Service',
    'display_text' => 'New Service',
    'menu_number' => '6',
    'action_type' => 'function',
    'action_value' => 'new_service',
    'display_order' => 6,
    'requires_auth' => 1,
    'min_balance' => 5.00,
    'user_type' => 'all'
];

$menuManager->addMenuItem($newItem);
```

### 4. Example: Adding a "Help" Menu

```php
$helpItem = [
    'category_id' => 1,
    'name' => 'Help & Support',
    'display_text' => 'Help & Support',
    'menu_number' => '6',
    'action_type' => 'function',
    'action_value' => 'help_support',
    'display_order' => 6,
    'requires_auth' => 0, // No login required
    'min_balance' => 0.00,
    'user_type' => 'all'
];

$menuManager->addMenuItem($helpItem);
```

### 5. Example: Premium-Only Menu

```php
$premiumItem = [
    'category_id' => 1,
    'name' => 'Premium Services',
    'display_text' => 'Premium Services',
    'menu_number' => '7',
    'action_type' => 'function',
    'action_value' => 'premium_services',
    'display_order' => 7,
    'requires_auth' => 1,
    'min_balance' => 50.00, // Requires GHS 50 minimum
    'user_type' => 'premium' // Only premium users
];

$menuManager->addMenuItem($premiumItem);
```

## Advanced Features

### User-Specific Menu Preferences

Hide specific menu items for certain users:

```php
// Hide "Investment" menu for user ID 123
$menuManager->setUserMenuPreference(123, 3, 0); // 0 = hidden

// Show "Premium Services" for user ID 456
$menuManager->setUserMenuPreference(456, 7, 1); // 1 = visible
```

### Menu Analytics

Get usage statistics:

```php
// Get last 30 days usage
$stats = $menuManager->getMenuUsageStats(30);

foreach ($stats as $stat) {
    echo "{$stat['display_text']}: {$stat['usage_count']} times\n";
}
```

### Conditional Menu Display

The system automatically:
- Hides menu items if user balance is below `min_balance`
- Respects user type restrictions (`premium`, `basic`, `all`)
- Checks user-specific preferences
- Only shows active menu items

## Integration with Existing Code

The system is designed to work seamlessly with your existing USSD flow:

1. **`index.php`** - Now uses database-driven menu
2. **`process.php`** - Handles menu selections dynamically
3. **All existing functionality** - Remains unchanged

### Menu Action Types

- **`function`** - Calls existing PHP functions (send_money, buy_airtime_data, etc.)
- **`page`** - Redirects to specific pages
- **`external`** - Calls external APIs or URLs

## Benefits

### For Developers:
- ✅ No code changes needed for menu updates
- ✅ Centralized menu management
- ✅ Easy to add new features
- ✅ Better code organization

### For Business:
- ✅ Quick menu updates without development
- ✅ A/B testing different menu layouts
- ✅ User behavior analytics
- ✅ Personalized user experience
- ✅ Reduced development costs

### For Users:
- ✅ Personalized menu options
- ✅ Relevant services based on balance
- ✅ Better user experience
- ✅ Faster access to frequently used services

## Troubleshooting

### Menu Not Showing
1. Check if `is_active = 1` in database
2. Verify user has sufficient balance (`min_balance`)
3. Check user type restrictions
4. Ensure menu number is unique

### Menu Order Issues
1. Update `display_order` field
2. Lower numbers appear first
3. Check for duplicate order numbers

### Action Not Working
1. Verify `action_value` matches existing functions
2. Check `action_type` is correct
3. Ensure function exists in `process.php`

## Future Enhancements

- Multi-language support
- Menu templates
- Scheduled menu changes
- Advanced analytics dashboard
- Menu A/B testing
- User feedback system

## Files Modified/Created

- ✅ `database.sql` - Added menu management tables
- ✅ `menu_manager.php` - New menu management class
- ✅ `admin_menu.php` - Admin interface for menu management
- ✅ `index.php` - Updated to use database-driven menu
- ✅ `process.php` - Updated to handle dynamic menu selections
- ✅ `add_menu_example.php` - Example usage
- ✅ `README_MENU_SYSTEM.md` - This documentation

---

**The database-driven menu system is now fully integrated and ready to use!** 🎉 