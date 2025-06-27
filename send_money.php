<?php
session_start();
require_once 'db_connect.php';

$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
$correctPin = '1234';

switch ($_SESSION['ussd_state']) {
    case 'transfer_money':
        if (preg_match('/^0[2-9][0-9]{8}$/', $input)) {
            $_SESSION['ussd_data']['recipient'] = $input;
            $_SESSION['ussd_state'] = 'transfer_amount';
            $_SESSION['display'] = "Enter amount to transfer:";
        } else {
            $_SESSION['display'] = "Invalid phone number. Please enter a valid number:";
        }
        break;

    case 'transfer_amount':
        if (is_numeric($input) && $input > 0) {
            try {
                // Check if user has sufficient balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();

                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['amount'] = $input;
                    $_SESSION['ussd_state'] = 'transfer_pin_input';
                    $_SESSION['pin_attempts'] = 0;
                    $_SESSION['display'] = "Please enter your PIN code to confirm the transfer:";
                } else {
                    $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:";
                }
            } catch(PDOException $e) {
                $_SESSION['display'] = "System error. Please try again later.";
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:";
        }
        break;

    case 'transfer_pin_input':
        if ($input == $correctPin) {
            try {
                $pdo->beginTransaction();

                // Deduct amount from sender
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$_SESSION['ussd_data']['amount'], $_SESSION['user_id']]);

                // Add amount to recipient
                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE phone = ?");
                $stmt->execute([$_SESSION['ussd_data']['amount'], $_SESSION['ussd_data']['recipient']]);

                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, transaction_date) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['ussd_data']['recipient'], $_SESSION['ussd_data']['amount']]);

                $pdo->commit();

                $_SESSION['ussd_state'] = 'start';
                $_SESSION['display'] = "You have successfully transferred GHS " . number_format($_SESSION['ussd_data']['amount'], 2) . 
                                     " to {$_SESSION['ussd_data']['recipient']}!\n\n1. Back to main menu";
            } catch(PDOException $e) {
                $pdo->rollBack();
                $_SESSION['display'] = "Transaction failed. Please try again later.";
            }
        } else {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Wrong PIN provided. Returning to main menu.\n\n1. Back to main menu";
        }
        break;
}

header('Location: index.php');
exit;
?>