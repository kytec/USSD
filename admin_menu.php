<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
echo "DEBUG: Start of admin_menu.php<br>";

session_start();
echo "DEBUG: After session_start()<br>";
require_once 'db_connect.php';
echo "DEBUG: After db_connect.php<br>";
require_once 'menu_manager.php';
echo "DEBUG: After menu_manager.php<br>";

$menuManager = new MenuManager($conn);
echo "DEBUG: After creating MenuManager<br>";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $data = [
                    'category_id' => 1, // Main menu
                    'name' => $_POST['name'],
                    'display_text' => $_POST['display_text'],
                    'menu_number' => $_POST['menu_number'],
                    'action_type' => $_POST['action_type'],
                    'action_value' => $_POST['action_value'],
                    'display_order' => $_POST['display_order'],
                    'requires_auth' => isset($_POST['requires_auth']) ? 1 : 0,
                    'min_balance' => $_POST['min_balance'],
                    'user_type' => $_POST['user_type']
                ];
                $menuManager->addMenuItem($data);
                $message = "Menu item added successfully!";
                break;
                
            case 'update':
                $data = [
                    'category_id' => 1,
                    'name' => $_POST['name'],
                    'display_text' => $_POST['display_text'],
                    'menu_number' => $_POST['menu_number'],
                    'action_type' => $_POST['action_type'],
                    'action_value' => $_POST['action_value'],
                    'display_order' => $_POST['display_order'],
                    'requires_auth' => isset($_POST['requires_auth']) ? 1 : 0,
                    'min_balance' => $_POST['min_balance'],
                    'user_type' => $_POST['user_type'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                $menuManager->updateMenuItem($_POST['id'], $data);
                $message = "Menu item updated successfully!";
                break;
                
            case 'delete':
                $menuManager->deleteMenuItem($_POST['id']);
                $message = "Menu item deleted successfully!";
                break;
        }
    }
}

// Get current menu items
$sql = "SELECT * FROM menu_items WHERE category_id = 1 ORDER BY display_order ASC";
$stmt = $conn->query($sql);
$menuItems = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $menuItems[] = $row;
}

$menuItems = $menuManager->getMainMenu();
echo "DEBUG: After getMainMenu<br>";

// Get usage statistics
$usageStats = $menuManager->getMenuUsageStats(30);

echo "<pre>DEBUG: menuItems = " . print_r($menuItems, true) . "</pre>";
echo "DEBUG: Before echoing menu<br>";
echo $menuManager->buildMenuDisplay($menuItems);
echo "DEBUG: After echoing menu<br>";

echo "DEBUG: Before HTML output<br>";
if (empty($menuItems)) {
    echo "DEBUG: menuItems is empty<br>";
}
?>
