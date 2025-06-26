<?php
session_start();
require_once 'db_connect.php';

$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
$correctPin = '1234';

// Initialize data state if not set
if (!isset($_SESSION['data_state'])) {
    $_SESSION['data_state'] = 'select_option';
    $_SESSION['data_data'] = [];
    $_SESSION['pin_attempts'] = 0;
}

// Data bundles configuration
$dataBundles = [
    '1' => ['size' => '1GB', 'price' => 5],
    '2' => ['size' => '2GB', 'price' => 9],
    '3' => ['size' => '5GB', 'price' => 20],
    '4' => ['size' => '10GB', 'price' => 35]
];

switch ($_SESSION['data_state']) {
    case 'select_option':
        switch ($input) {
            case '1':
                // For Self (My MTN No.)
                try {
                    $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $_SESSION['data_data']['phone_number'] = $user ? $user['phone'] : '';
                    $_SESSION['data_data']['for_self'] = true;
                    $_SESSION['data_state'] = 'select_bundle';
                    $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                } catch (PDOException $e) {
                    $_SESSION['display'] = "Error retrieving user data. Please try again.\n#. Back";
                }
                break;
            case '2':
                // For Other
                $_SESSION['data_data']['for_self'] = false;
                $_SESSION['data_state'] = 'select_network';
                $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                break;
            case '#':
                // Go back to main menu
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
                header('Location: index.php');
                exit;
            default:
                $_SESSION['display'] = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back\n\nInvalid option. Please select 1 or 2:";
                break;
        }
        break;

    case 'select_network':
        if ($input == '#') {
            $_SESSION['data_state'] = 'select_option';
            $_SESSION['display'] = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['data_data']['network'] = 'Telecel';
                    $_SESSION['data_state'] = 'enter_phone';
                    $_SESSION['display'] = "Enter recipient Telecel mobile number:\n#. Back";
                    break;
                case '2':
                    $_SESSION['data_data']['network'] = 'AirtelTigo';
                    $_SESSION['data_state'] = 'enter_phone';
                    $_SESSION['display'] = "Enter recipient AirtelTigo mobile number:\n#. Back";
                    break;
                case '3':
                    $_SESSION['data_data']['network'] = 'MTN';
                    $_SESSION['data_state'] = 'enter_phone';
                    $_SESSION['display'] = "Enter recipient MTN mobile number:\n#. Back";
                    break;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                    break;
            }
        }
        break;

    case 'enter_phone':
        if ($input == '#') {
            $_SESSION['data_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
        } else {
            $network = $_SESSION['data_data']['network'];
            $valid = false;
            
            // Validate phone number based on network
            if ($network == 'MTN' && preg_match('/^(054|053|024|059|025|055)[0-9]{7}$/', $input)) {
                $valid = true;
            } else if ($network == 'Telecel' && preg_match('/^(020|050)[0-9]{7}$/', $input)) {
                $valid = true;
            } else if ($network == 'AirtelTigo' && preg_match('/^(026|056|027|057)[0-9]{7}$/', $input)) {
                $valid = true;
            }
            
            if ($valid) {
                $_SESSION['data_data']['phone_number'] = $input;
                $_SESSION['data_state'] = 'select_bundle';
                $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
            } else {
                $_SESSION['display'] = "Invalid number for $network. Please enter a valid $network number:\n#. Back";
            }
        }
        break;

    case 'select_bundle':
        if ($input == '#') {
            if ($_SESSION['data_data']['for_self']) {
                $_SESSION['data_state'] = 'select_option';
                $_SESSION['display'] = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
            } else {
                $_SESSION['data_state'] = 'enter_phone';
                $network = $_SESSION['data_data']['network'];
                $_SESSION['display'] = "Enter recipient $network mobile number:\n#. Back";
            }
        } else if (isset($dataBundles[$input])) {
            $bundle = $dataBundles[$input];
            $_SESSION['data_data']['bundle_size'] = $bundle['size'];
            $_SESSION['data_data']['amount'] = $bundle['price'];
            
            try {
                // Check if user has sufficient balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $bundle['price']) {
                    $_SESSION['data_state'] = 'confirm_purchase';
                    $phone = $_SESSION['data_data']['phone_number'];
                    $_SESSION['display'] = "Confirm Data Purchase:\nBuy " . $bundle['size'] . " data bundle for GHS " . $bundle['price'] . " for $phone.\n\nEnter your PIN to confirm:\n#. Back";
                } else {
                    $_SESSION['display'] = "Insufficient balance. Your balance is GHS " . number_format($user['balance'], 2) . ".\nRequired: GHS " . $bundle['price'] . "\n\nSelect Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\nSelect Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
            }
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
        }
        break;

    case 'confirm_purchase':
        if ($input == '#') {
            $_SESSION['data_state'] = 'select_bundle';
            $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
        } else if ($input == $correctPin) {
            try {
                $amount = $_SESSION['data_data']['amount'];
                $phone = $_SESSION['data_data']['phone_number'];
                $bundleSize = $_SESSION['data_data']['bundle_size'];
                
                // Check balance again before processing
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user || $user['balance'] < $amount) {
                    $_SESSION['data_state'] = 'purchase_complete';
                    $_SESSION['display'] = "Insufficient funds. Data purchase cancelled.\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }

                $pdo->beginTransaction();
                
                // Deduct amount from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Record data purchase
                $stmt = $pdo->prepare("INSERT INTO data_purchases (user_id, phone_number, data_bundle, amount, purchase_date) VALUES (?, ?, ?, ?, GETDATE())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $phone,
                    $bundleSize,
                    $amount
                ]);
                
                $pdo->commit();
                
                $_SESSION['data_state'] = 'purchase_complete';
                $_SESSION['display'] = "Data purchase successful!\nBundle: $bundleSize\nAmount: GHS " . number_format($amount, 2) . "\nRecipient: $phone\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['data_state'] = 'purchase_complete';
                $_SESSION['display'] = "Data purchase failed: " . $e->getMessage() . "\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['data_state'] = 'purchase_complete';
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
            }
        }
        break;

    case 'purchase_complete':
        if ($input == '1') {
            // Clear data session data and return to main menu
            unset($_SESSION['data_state']);
            unset($_SESSION['data_data']);
            $_SESSION['pin_attempts'] = 0;
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Back to main menu";
        }
        break;

    default:
        $_SESSION['data_state'] = 'select_option';
        $_SESSION['display'] = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
        break;
}

header('Location: index.php');
exit;
?> 