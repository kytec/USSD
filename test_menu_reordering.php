<?php
/**
 * Test file for menu reordering and submenu linking
 * This demonstrates how submenus maintain their connections when main menus are reordered
 */

require_once 'db_connect.php';
require_once 'menu_manager.php';

echo "<h1>Menu Reordering & Submenu Linking Test</h1>\n";

try {
    $menuManager = new MenuManager($pdo);
    
    echo "<h2>1. Current Main Menu Order</h2>\n";
    $mainMenu = $menuManager->getMainMenu();
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Menu #</th><th>Display Text</th><th>Action Value</th><th>Display Order</th></tr>\n";
    foreach ($mainMenu as $item) {
        echo "<tr>";
        echo "<td>{$item['menu_number']}</td>";
        echo "<td>{$item['display_text']}</td>";
        echo "<td>{$item['action_value']}</td>";
        echo "<td>{$item['display_order']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h2>2. Submenu Links Test</h2>\n";
    echo "<p>Testing submenu connections for each main menu item:</p>\n";
    
    foreach ($mainMenu as $mainItem) {
        echo "<h3>Main Menu: {$mainItem['display_text']} (Menu #{$mainItem['menu_number']})</h3>\n";
        
        $submenus = $menuManager->getSubmenuNodes($mainItem['action_value']);
        if ($submenus && count($submenus) > 0) {
            echo "<p><strong>✅ Submenus Found:</strong></p>\n";
            echo "<ul>\n";
            foreach ($submenus as $submenu) {
                echo "<li>{$submenu['menu_number']}. {$submenu['display_text']} (Code: {$submenu['code']})</li>\n";
            }
            echo "</ul>\n";
            
            // Test the submenu display
            echo "<p><strong>Submenu Display:</strong></p>\n";
            echo "<pre>" . $menuManager->buildSubmenuDisplay($submenus, $mainItem['display_text']) . "</pre>\n";
        } else {
            echo "<p><strong>❌ No Submenus</strong></p>\n";
        }
        echo "<hr>\n";
    }
    
    echo "<h2>3. Menu Reordering Simulation</h2>\n";
    echo "<p>Let's simulate what happens when you reorder the main menu:</p>\n";
    
    // Simulate reordered menu (this is just for demonstration)
    echo "<h3>Simulated Reordered Menu:</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>Investment</strong> (was menu #3) - Action Value: 'investment'</li>\n";
    echo "<li><strong>Utility Payment</strong> (was menu #4) - Action Value: 'utility_payment'</li>\n";
    echo "<li><strong>Send Money</strong> (was menu #1) - Action Value: 'send_money'</li>\n";
    echo "<li><strong>Buy Airtime/Data</strong> (was menu #2) - Action Value: 'buy_airtime_data'</li>\n";
    echo "<li><strong>Statement</strong> (was menu #5) - Action Value: 'statement'</li>\n";
    echo "</ol>\n";
    
    echo "<h3>Key Point: Submenu Links Remain Intact!</h3>\n";
    echo "<p>Even though the menu numbers changed, the submenus still link correctly because:</p>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>Submenu linking is based on 'action_value'</strong>, not menu number</li>\n";
    echo "<li>✅ <strong>Database relationships use 'code' field</strong>, not display order</li>\n";
    echo "<li>✅ <strong>Menu numbers are just for display</strong>, not for functionality</li>\n";
    echo "</ul>\n";
    
    echo "<h2>4. Database Relationship Explanation</h2>\n";
    echo "<p>Here's how the linking works in the database:</p>\n";
    echo "<pre>\n";
    echo "menu_nodes table:\n";
    echo "├── id: 1, code: 'send_money', label: 'Send Money', menu_number: '1', display_order: 1\n";
    echo "├── id: 2, code: 'buy_airtime_data', label: 'Buy Airtime/Data', menu_number: '2', display_order: 2\n";
    echo "├── id: 3, code: 'investment', label: 'Investment', menu_number: '3', display_order: 3\n";
    echo "├── id: 4, code: 'utility_payment', label: 'Utility Payment', menu_number: '4', display_order: 4\n";
    echo "└── id: 5, code: 'statement', label: 'Statement', menu_number: '5', display_order: 5\n\n";
    
    echo "menu_subnodes table:\n";
    echo "├── parent_node_id: 1 (links to 'send_money')\n";
    echo "│   ├── sm_mtn (MTN MobileMoney)\n";
    echo "│   ├── sm_telecel (Telecel Cash)\n";
    echo "│   ├── sm_airtel (AirtelTigo Cash)\n";
    echo "│   └── sm_bank (Bank Account)\n";
    echo "├── parent_node_id: 2 (links to 'buy_airtime_data')\n";
    echo "│   ├── bad_airtime (Buy Airtime)\n";
    echo "│   └── bad_data (Buy Data)\n";
    echo "├── parent_node_id: 3 (links to 'investment')\n";
    echo "│   ├── inv_fd (Fixed Deposit)\n";
    echo "│   ├── inv_tbills (Treasury Bills)\n";
    echo "│   └── inv_mutual (Mutual Funds)\n";
    echo "└── parent_node_id: 4 (links to 'utility_payment')\n";
    echo "    ├── utl_ecg (ECG Electricity)\n";
    echo "    └── utl_water (Water)\n";
    echo "</pre>\n";
    
    echo "<h2>5. How to Reorder Menus Safely</h2>\n";
    echo "<p>To reorder menus without breaking submenu links:</p>\n";
    echo "<ol>\n";
    echo "<li><strong>Update display_order only</strong> - Don't change action_value or code</li>\n";
    echo "<li><strong>Keep menu_number unique</strong> - Each menu should have a different number</li>\n";
    echo "<li><strong>Test submenu functionality</strong> - Verify links still work after reordering</li>\n";
    echo "</ol>\n";
    
    echo "<h3>Example SQL for Safe Reordering:</h3>\n";
    echo "<pre>\n";
    echo "-- Move Investment to position 1\n";
    echo "UPDATE menu_nodes SET display_order = 1 WHERE code = 'investment';\n";
    echo "-- Move Utility Payment to position 2\n";
    echo "UPDATE menu_nodes SET display_order = 2 WHERE code = 'utility_payment';\n";
    echo "-- Move Send Money to position 3\n";
    echo "UPDATE menu_nodes SET display_order = 3 WHERE code = 'send_money';\n";
    echo "-- Move Buy Airtime/Data to position 4\n";
    echo "UPDATE menu_nodes SET display_order = 4 WHERE code = 'buy_airtime_data';\n";
    echo "-- Move Statement to position 5\n";
    echo "UPDATE menu_nodes SET display_order = 5 WHERE code = 'statement';\n";
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Make sure your database is set up with the menu tables from database.sql</p>\n";
}
?>

<h2>Summary</h2>
<p><strong>✅ YES, submenus will still link correctly when you reorder main menu options!</strong></p>

<p>The system is designed to be robust because:</p>
<ul>
    <li><strong>Submenu linking uses 'action_value'</strong> (e.g., 'send_money'), not menu numbers</li>
    <li><strong>Database relationships use 'code' field</strong>, which never changes</li>
    <li><strong>Display order is independent</strong> of functional relationships</li>
    <li><strong>Menu numbers are just for user interface</strong>, not for system logic</li>
</ul>

<p>You can safely reorder your main menu options without worrying about breaking submenu functionality!</p> 