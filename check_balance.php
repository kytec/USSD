<?php
session_start();
require_once 'db_connect.php';

$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
$correctPin = '1234';

if ($_SESSION['ussd_state'] === 'pin_input') {
    if ($input == $correctPin) {
        try {
            // Get user's balance from database
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['ussd_state'] = 'check_balance';
                $_SESSION['pin_attempts'] = 0;
                $_SESSION['display'] = "Your current balance is: GHS " . number_format($user['balance'], 2) . "\n\n1. Back to main menu";
            } else {
                $_SESSION['display'] = "Error retrieving balance. Please try again later.";
            }
        } catch(PDOException $e) {
            $_SESSION['display'] = "System error. Please try again later.";
        }
    } else {
        $_SESSION['pin_attempts']++;
        if ($_SESSION['pin_attempts'] >= 3) {
            $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.";
            session_destroy();
        } else {
            $remainingAttempts = 3 - $_SESSION['pin_attempts'];
            $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN code:";
        }
    }
} elseif ($_SESSION['ussd_state'] === 'check_balance') {
    if ($input == '1') {
        $_SESSION['ussd_state'] = 'start';
        $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Check Balance\n2. Transfer Money\n3. Buy Airtime";
    } else {
        $_SESSION['display'] = "Invalid option. Please select:\n1. Back to main menu";
    }
}

header('Location: index.php');
exit;
?>