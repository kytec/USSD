<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USSD Simulator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
        }
        .ussd-container {
            background: #f0f0f0;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .ussd-display {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            min-height: 100px;
            border-radius: 5px;
            white-space: pre-line;
        }
        .ussd-input {
            display: flex;
            gap: 10px;
        }
        input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="ussd-container">
        <h2>USSD Simulator</h2>
        <div class="ussd-display" id="display">
            <?php
            // When on start state, always rebuild the main menu from DB to reflect latest changes
            if (isset($_SESSION['ussd_state']) && $_SESSION['ussd_state'] === 'start') {
                require_once 'db_connect.php';
                require_once 'menu_manager.php';
                $menuManager = new MenuManager($pdo);
                $userBalance = 900.00; // Default balance for demo
                $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $menuItems = $menuManager->getMainMenu($userId, $userBalance);
                $menuDisplay = $menuManager->buildMenuDisplay($menuItems);
                echo htmlspecialchars($menuDisplay);
            } elseif (isset($_SESSION['display'])) {
                echo htmlspecialchars($_SESSION['display']);
            } else {
                // Initial load: build main menu from DB
                require_once 'db_connect.php';
                require_once 'menu_manager.php';
                $menuManager = new MenuManager($pdo);
                $userBalance = 900.00; // Default balance for demo
                $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $menuItems = $menuManager->getMainMenu($userId, $userBalance);
                $menuDisplay = $menuManager->buildMenuDisplay($menuItems);
                echo htmlspecialchars($menuDisplay);
            }
            ?>
        </div>
        <form action="process.php" method="POST" class="ussd-input">
            <input type="text" name="ussd_input" placeholder="Enter your choice">
            <button type="submit">Send</button>
            <button type="submit" name="action" value="cancel">Cancel</button>
        </form>
    </div>
</body>
</html>
