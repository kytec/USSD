<?php
session_start();
require_once 'db_connect.php';

$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
$correctPin = '1234';

switch ($_SESSION['ussd_state']) {
    case 'buy_airtime':
        if (preg_match('/^0[2-9][0-9]{8}$/', $input)) {
            $_SESSION['ussd_data']['airtime_number'] = $input;
            $_SESSION['ussd_state'] = 'airtime_amount';
            $_SESSION['display'] = "Enter amount for airtime:";
        } else {
            $_SESSION['display'] = "Invalid phone number. Please enter a valid number:";
        }
        break;

    case 'airtime_amount':
        if (is_numeric($input) && $input > 0) {
            try {
                // Check if user has sufficient balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();

                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['airtime_amount'] = $input;
                    $_SESSION['ussd_state'] = 'airtime_confirm';
                    $_SESSION['display'] = "Confirm airtime purchase of GHS " . number_format($input, 2) . 
                                         " for {$_SESSION['ussd_data']['airtime_number']}\n1. Confirm\n2. Cancel";
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

    case 'airtime_confirm':
        if ($input == '1') {
            try {
                $pdo->beginTransaction();

                // Deduct amount from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$_SESSION['ussd_data']['airtime_amount'], $_SESSION['user_id']]);

                // Record airtime purchase
                $stmt = $pdo->prepare("INSERT INTO airtime_purchases (user_id, phone_number, amount, purchase_date) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['ussd_data']['airtime_number'], $_SESSION['ussd_data']['airtime_amount']]);

                $pdo->commit();

                $_SESSION['ussd_state'] = 'start';
                $_SESSION['display'] = "Airtime purchase successful!\n\n1. Back to main menu";
            } catch(PDOException $e) {
                $pdo->rollBack();
                $_SESSION['display'] = "Airtime purchase failed. Please try again later.";
            }
        } elseif ($input == '2') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Airtime purchase cancelled.\n\n1. Back to main menu";
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Confirm\n2. Cancel";
        }
        break;
}

header('Location: index.php');
exit;
?>