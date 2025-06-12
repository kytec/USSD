<?php
session_start();
require_once 'db_connect.php';
 
if (!isset($_SESSION['ussd_state'])) {
    $_SESSION['ussd_state'] = 'start';
    $_SESSION['ussd_data'] = [];
    $_SESSION['pin_attempts'] = 0;
    $_SESSION['user_id'] = 1; // This should be set based on actual user authentication
}
 
$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
 
$correctPin = '1234';
 
switch ($_SESSION['ussd_state']) {
 
    case 'start':
        switch ($input) {
            case '1':
                $_SESSION['ussd_state'] = 'pin_for_balance_check';
                $response = "Please enter your PIN code:";
                break;
            case '2':
                $_SESSION['ussd_state'] = 'enter_recipient';
                $response = "Enter recipient number:";
                break;
            case '3':
                $_SESSION['ussd_state'] = 'buy_airtime';
                header('Location: buy_airtime.php');
                exit;
            default:
                $response = "Welcome to BRASSICA-PAY Service\n\nPlease enter your choice:\n1. Check Balance\n2. Transfer Money\n3. Buy Airtime";
                break;
        }
        break;
 
    case 'pin_for_balance_check':
        if ($input == $correctPin) {
            try {
                // Get user's balance from database
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $_SESSION['ussd_state'] = 'display_balance_then_back_to_main_menu';
                    $_SESSION['pin_attempts'] = 0;
                    $response = "Your current balance is: GHS " . number_format($user['balance'], 2) . "\n\n1. Back to main menu";
                } else {
                    $response = "Error retrieving balance. Please try again later.\n\n1. Back to main menu";
                }
            } catch (PDOException $e) {
                $response = "Error retrieving balance: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $response = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $response = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN code:";
            }
        }
        break;
 
    case 'display_balance_then_back_to_main_menu':
        if ($input == '1') {
            session_destroy(); // Destroy the session to fully reset
            session_start(); // Start a new session for a clean slate
            // Re-initialize session variables as they would be at the very start
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['ussd_data'] = [];
            $_SESSION['pin_attempts'] = 0;
            $_SESSION['user_id'] = 1;
            $response = "Welcome to BRASSICA-PAY Service\n\nPlease enter your choice:\n1. Check Balance\n2. Transfer Money\n3. Buy Airtime";
        } else {
            $response = "Invalid option. Please select:\n1. Back to main menu";
        }
        break;
 
    case 'enter_recipient':
        if (preg_match('/^0[2-9][0-9]{8}$/', $input)) {
            try {
                // Insert recipient number and sender_id into transaction table
                $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $input]);
                $_SESSION['transaction_id'] = $pdo->lastInsertId();
                
                $_SESSION['ussd_data']['recipient_phone'] = $input; // Store for later use
                
                $_SESSION['ussd_state'] = 'enter_amount';
                $response = "Enter amount to transfer:";
            } catch (PDOException $e) {
                $response = "Error saving recipient. Please try again:\nEnter recipient number:";
            }
        } else {
            $response = "Invalid phone number. Please enter a valid number:";
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
                    
                    $_SESSION['ussd_state'] = 'enter_pin';
                    $response = "Enter your PIN:";
                } else {
                    $response = "Insufficient balance. Please enter a valid amount:";
                }
            } catch (PDOException $e) {
                $response = "Error checking balance. Please try again:\nEnter amount to transfer:";
            }
        } else {
            $response = "Invalid amount. Please enter a valid amount:";
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
                
                // Update PIN in users table (as per instruction)
                $stmt = $pdo->prepare("UPDATE users SET pin = ? WHERE id = ?");
                $stmt->execute([$correctPin, $_SESSION['user_id']]);
                
                // Update transaction status
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?");
                $stmt->execute([$_SESSION['transaction_id']]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transfer_success';
                $response = "Transfer successful!\n\n1. Back to main menu";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = "Error processing transfer: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $response = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $response = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:";
            }
        }
        break;
 
    case 'transfer_success':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to BRASSICA-PAY Service\n\nPlease enter your choice:\n1. Check Balance\n2. Transfer Money\n3. Buy Airtime";
        } else {
            $response = "Invalid option. Please select:\n1. Back to main menu";
        }
        break;
 
    case 'buy_airtime':
        // You may need to add further logic here for airtime purchase similar to transfer money flow
        // For now, it will just exit if the user selects it.
        header('Location: buy_airtime.php');
        exit;
 
}
 
$_SESSION['display'] = $response;
 
header('Location: index.php');
exit;
?>
 
 