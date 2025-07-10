<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
echo "DEBUG: session started\n";
require_once 'menu_manager.php';
echo "DEBUG: menu_manager.php included\n";

echo "=== Testing Simple Menu System ===\n\n";

// Test menu manager without database
$menuManager = new MenuManager(null);
echo "✓ MenuManager created without database\n";

// Test getting menu items
$menuItems = $menuManager->getMainMenu();
echo "DEBUG: getMainMenu() called\n";
echo "Menu items found: " . count($menuItems) . "\n";

if (empty($menuItems)) {
    echo "❌ No menu items found!\n";
} else {
    echo "✓ Menu items found:\n";
    foreach ($menuItems as $item) {
        echo "  - {$item['menu_number']}. {$item['display_text']}\n";
    }
    
    // Test buildMenuDisplay
    $display = $menuManager->buildMenuDisplay($menuItems);
    echo "\nMenu display:\n";
    echo "---\n";
    echo $display;
    echo "---\n";
    
    // Test menu item lookup
    $testItem = $menuManager->getMenuItemByNumber('1');
    if ($testItem) {
        echo "✓ Found menu item '1': {$testItem['display_text']}\n";
    } else {
        echo "❌ Could not find menu item '1'\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "The menu system should now work without database!\n";
?> 