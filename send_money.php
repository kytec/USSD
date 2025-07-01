<?php
session_start();
require_once 'db_connect.php';

// Handle cancel button
if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $_SESSION = array();
    session_destroy();
    session_start();
    $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
    header('Location: index.php');
    exit();
}

$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
$correctPin = '1234';

switch ($_SESSION['ussd_state']) {
    case 'send_money':
        if ($input == '') {
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. AirtelTigo Cash\n3. Telecel Cash\n#. Back";
        } else {
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. AirtelTigo Cash\n3. Telecel Cash\n#. Back";
        }
        break;

    case 'select_network provider':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['network'] = 'MTN';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $_SESSION['display'] = "Enter MTN MobileMoney number:\n#. Back";
                    break;
                case '2':
                    $_SESSION['ussd_data']['network'] = 'AirtelTigo';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $_SESSION['display'] = "Enter AirtelTigo Cash number:\n#. Back";
                    break;
                case '3':
                    $_SESSION['ussd_data']['network'] = 'Telecel';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $_SESSION['display'] = "Enter Telecel Cash number:\n#. Back";
                    break;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. MTN MobileMoney\n2. AirtelTigo Cash\n3. Telecel Cash\n#. Back";
                    break;
            }
        }
        break;

    case 'enter_recipient':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. AirtelTigo Cash\n3. Telecel Cash\n#. Back";
        } else {
            $isValidNumber = false;
            $errorMessage = "";
            $network = $_SESSION['ussd_data']['network'];

            switch ($network) {
                case 'MTN':
                    if (preg_match('/^(054|053|055|024|023|025|059)[0-9]{7}$/', $input)) {
                        $isValidNumber = true;
                    } else {
                        $errorMessage = "Invalid MTN MobileMoney number.";
                    }
                    break;
                case 'AirtelTigo':
                    if (preg_match('/^(027|026|056|057)[0-9]{7}$/', $input)) {
                        $isValidNumber = true;
                    } else {
                        $errorMessage = "Invalid AirtelTigo Cash number.";
                    }
                    break;
                case 'Telecel':
                    if (preg_match('/^(020|050)[0-9]{7}$/', $input)) {
                        $isValidNumber = true;
                    } else {
                        $errorMessage = "Invalid Telecel Cash number.";
                    }
                    break;
                default:
                    $errorMessage = "Invalid network selected.";
                    break;
            }

            if ($isValidNumber) {
                try {
                    // Insert recipient number and sender_id into transaction table
                    $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, network) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $input, $network]);
                    $_SESSION['transaction_id'] = $pdo->lastInsertId();
                    $_SESSION['ussd_data']['recipient_phone'] = $input; // Store for later use
                    $_SESSION['ussd_state'] = 'confirm_recipient';
                    $_SESSION['display'] = "Confirm recipient: " . $input . "\n1. Confirm\n#. Back";
                } catch (PDOException $e) {
                    $_SESSION['display'] = "Error saving recipient. Please try again:\nEnter " . $network . " number:\n#. Back";
                }
            } else {
                $_SESSION['display'] = $errorMessage . "\nPlease enter a valid " . $network . " number:\n#. Back";
            }
        }
        break;

    case 'confirm_recipient':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'enter_amount';
            $_SESSION['display'] = "Enter amount to transfer:";
        } else if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_recipient';
            $_SESSION['display'] = "Enter " . $_SESSION['ussd_data']['network'] . " number:\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Confirm\n#. Back";
        }
        break;

    case 'enter_amount':
        if (is_numeric($input) && $input > 0) {
            try {
                // Check if user has sufficient balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    // Update amount in transaction table
                    $stmt = $pdo->prepare("UPDATE transactions SET amount = ? WHERE id = ?");
                    $stmt->execute([$input, $_SESSION['transaction_id']]);
                    
                    $_SESSION['ussd_data']['amount'] = $input; // Store for later use
                    
                    $_SESSION['ussd_state'] = 'enter_reference';
                    $_SESSION['display'] = "Enter reference:";
                } else {
                    $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:";
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\nEnter amount to transfer:";
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:";
        }
        break;

    case 'enter_reference':
        if (!empty($input)) {
            try {
                // Update reference in transaction table
                $stmt = $pdo->prepare("UPDATE transactions SET reference = ? WHERE id = ?");
                $stmt->execute([$input, $_SESSION['transaction_id']]);
                
                $_SESSION['ussd_data']['reference'] = $input; // Store for later use
                
                $_SESSION['ussd_state'] = 'enter_pin';
                $_SESSION['display'] = "Enter your PIN:";
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error saving reference. Please try again:\nEnter reference:";
            }
        } else if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_amount';
            $_SESSION['display'] = "Enter amount to transfer:";
        } else {
            $_SESSION['display'] = "Reference cannot be empty. Please enter a reference:";
        }
        break;

    case 'enter_pin':
        if ($input == $correctPin) {
            try {
                $amount = $_SESSION['ussd_data']['amount'];
                $recipient_phone = $_SESSION['ussd_data']['recipient_phone'];
                
                $pdo->beginTransaction();
                
                // Update sender's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Update recipient's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE phone = ?");
                $stmt->execute([$amount, $recipient_phone]);
                
                // Update transaction status
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?");
                $stmt->execute([$_SESSION['transaction_id']]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Transfer successful!\n\n1. Back to main menu";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['display'] = "Error processing transfer: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:";
            }
        }
        break;

    case 'transaction_success':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Back to main menu";
        }
        break;
}

header('Location: index.php');
exit;
?>