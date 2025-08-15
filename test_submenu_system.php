<?php
/**
 * Test file for the new submenu system
 * This demonstrates how the MenuManager now handles both main menus and submenus
 */

require_once 'db_connect.php';
require_once 'menu_manager.php';

echo "<h1>USSD Submenu System Test</h1>\n";

try {
    $menuManager = new MenuManager($pdo);
    
    echo "<h2>1. Main Menu Items</h2>\n";
    $mainMenu = $menuManager->getMainMenu();
    echo "<pre>";
    print_r($mainMenu);
    echo "</pre>";
    
    echo "<h2>2. Send Money Submenus</h2>\n";
    $sendMoneySubmenus = $menuManager->getSubmenuNodes('send_money');
    if ($sendMoneySubmenus) {
        echo "<pre>";
        print_r($sendMoneySubmenus);
        echo "</pre>";
        
        echo "<h3>Send Money Submenu Display:</h3>\n";
        echo "<pre>" . $menuManager->buildSubmenuDisplay($sendMoneySubmenus, 'Send Money') . "</pre>";
    } else {
        echo "<p>No submenus found for 'send_money'</p>\n";
    }
    
    echo "<h2>3. Buy Airtime/Data Submenus</h2>\n";
    $buyAirtimeSubmenus = $menuManager->getSubmenuNodes('buy_airtime_data');
    if ($buyAirtimeSubmenus) {
        echo "<pre>";
        print_r($buyAirtimeSubmenus);
        echo "</pre>";
        
        echo "<h3>Buy Airtime/Data Submenu Display:</h3>\n";
        echo "<pre>" . $menuManager->buildSubmenuDisplay($buyAirtimeSubmenus, 'Buy Airtime/Data') . "</pre>";
    } else {
        echo "<p>No submenus found for 'buy_airtime_data'</p>\n";
    }
    
    echo "<h2>4. Investment Submenus</h2>\n";
    $investmentSubmenus = $menuManager->getSubmenuNodes('investment');
    if ($investmentSubmenus) {
        echo "<pre>";
        print_r($investmentSubmenus);
        echo "</pre>";
        
        echo "<h3>Investment Submenu Display:</h3>\n";
        echo "<pre>" . $menuManager->buildSubmenuDisplay($investmentSubmenus, 'Investment') . "</pre>";
    } else {
        echo "<p>No submenus found for 'investment'</p>\n";
    }
    
    echo "<h2>5. Utility Payment Submenus</h2>\n";
    $utilitySubmenus = $menuManager->getSubmenuNodes('utility_payment');
    if ($utilitySubmenus) {
        echo "<pre>";
        print_r($utilitySubmenus);
        echo "</pre>";
        
        echo "<h3>Utility Payment Submenu Display:</h3>\n";
        echo "<pre>" . $menuManager->buildSubmenuDisplay($utilitySubmenus, 'Utility Payment') . "</pre>";
    } else {
        echo "<p>No submenus found for 'utility_payment'</p>\n";
    }
    
    echo "<h2>6. Complete Menu Hierarchy</h2>\n";
    $hierarchy = $menuManager->getMenuHierarchy();
    echo "<pre>";
    print_r($hierarchy);
    echo "</pre>";
    
    echo "<h2>7. Test Menu Node by Code</h2>\n";
    $mtnNode = $menuManager->getMenuNodeByCode('sm_mtn');
    if ($mtnNode) {
        echo "<h3>MTN MobileMoney Node:</h3>\n";
        echo "<pre>";
        print_r($mtnNode);
        echo "</pre>";
    } else {
        echo "<p>MTN node not found</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Make sure your database is set up with the menu tables from database.sql</p>\n";
}
?>

<h2>How to Test the Submenu System</h2>
<ol>
    <li><strong>Database Setup:</strong> Make sure you've run the database.sql file to create the menu tables</li>
    <li><strong>Test Main Menu:</strong> Visit index.php to see the main menu</li>
    <li><strong>Test Submenu Navigation:</strong> Select a main menu item that has submenus (like "Send Money")</li>
    <li><strong>Submenu Selection:</strong> Choose a submenu option (like "MTN MobileMoney")</li>
    <li><strong>Back Navigation:</strong> Use "#" to go back to previous menus</li>
</ol>

<h2>What's New</h2>
<ul>
    <li>✅ <strong>Dynamic Submenu Loading:</strong> Submenus are now loaded from the database</li>
    <li>✅ <strong>Metadata Support:</strong> Submenus can have custom display text and behavior</li>
    <li>✅ <strong>Hierarchical Navigation:</strong> Complete menu tree support</li>
    <li>✅ <strong>Flexible Action Types:</strong> Support for state, function, and external actions</li>
    <li>✅ <strong>Session Management:</strong> Proper state tracking for submenu navigation</li>
</ul> 