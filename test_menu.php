<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== Testing Menu System ===\n\n";

// Test database connection
require_once 'db_connect.php';
echo "✓ Database connected\n";

// Test menu manager
require_once 'menu_manager.php';
$menuManager = new MenuManager($conn);
echo "✓ MenuManager created\n";

// Check if menu categories exist
$stmt = $conn->query("SELECT COUNT(*) as count FROM menu_categories");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Menu categories in database: " . $result['count'] . "\n";

// Check if menu items exist
$stmt = $conn->query("SELECT COUNT(*) as count FROM menu_items");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Menu items in database: " . $result['count'] . "\n";

if ($result['count'] == 0) {
    echo "❌ No menu items found! This is why the menu isn't working.\n";
    echo "You need to run the database.sql file to create the menu items.\n";
    
    // Show what's in the menu_items table
    echo "\nChecking menu_items table structure:\n";
    try {
        $stmt = $conn->query("SELECT TOP 5 * FROM menu_items");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) {
            echo "Table is empty\n";
        } else {
            foreach ($items as $item) {
                echo "  - ID: {$item['id']}, Name: {$item['name']}, Category: {$item['category_id']}, Active: {$item['is_active']}\n";
            }
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ Menu items exist\n";
    
    // Get menu items
    $menuItems = $menuManager->getMainMenu();
    echo "Menu items retrieved: " . count($menuItems) . "\n";
    
    if (empty($menuItems)) {
        echo "❌ getMainMenu() returned empty array\n";
    } else {
        echo "✓ Menu items retrieved successfully:\n";
        foreach ($menuItems as $item) {
            echo "  - {$item['menu_number']}. {$item['display_text']}\n";
        }
        
        // Test buildMenuDisplay
        $display = $menuManager->buildMenuDisplay($menuItems);
        echo "\nMenu display:\n";
        echo "---\n";
        echo $display;
        echo "---\n";
    }
}

echo "\n=== Test Complete ===\n";
?> 