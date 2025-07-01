<?php
session_start();
require_once 'db_connect.php';

// Initialize session if not set
if (!isset($_SESSION['ussd_state'])) {
    $_SESSION['ussd_state'] = 'start';
    $_SESSION['ussd_data'] = [];
    $_SESSION['pin_attempts'] = 0;
    $_SESSION['user_id'] = 1;
}

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
    case 'meter_topup':
        if ($input == '') {
            $_SESSION['display'] = "Enter meter number:\n#. Back";
        } else if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
        } else if (preg_match('/^[A-Za-z0-9]{11}$/', $input)) {
            $_SESSION['ussd_data']['meter_number'] = $input;
            $_SESSION['ussd_state'] = 'select_meter_type';
            $_SESSION['display'] = "Select Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid meter number. Please enter a valid 11-digit meter number:\n#. Back";
        }
        break;

    case 'select_meter_type':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'meter_topup';
            $_SESSION['display'] = "Enter meter number:\n#. Back";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['meter_type'] = 'Prepaid';
                    $_SESSION['ussd_state'] = 'enter_meter_amount';
                    $_SESSION['display'] = "Enter amount to top up:\n#. Back";
                    break;
                case '2':
                    $_SESSION['ussd_data']['meter_type'] = 'Postpaid';
                    $_SESSION['ussd_state'] = 'enter_meter_amount';
                    $_SESSION['display'] = "Enter amount to top up:\n#. Back";
                    break;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Prepaid\n2. Postpaid\n#. Back";
                    break;
            }
        }
        break;

    case 'enter_meter_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_meter_type';
            $_SESSION['display'] = "Select Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
        } else if (is_numeric($input) && $input > 0) {
            try {
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_pin_for_meter';
                    $_SESSION['display'] = "Enter your PIN to confirm meter top-up of GHS " . number_format($input, 2) .
                        " for meter " . $_SESSION['ussd_data']['meter_number'] .
                        " (" . $_SESSION['ussd_data']['meter_type'] . "):\n#. Back";
                } else {
                    $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:\n#. Back";
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\n#. Back";
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
        }
        break;

    case 'enter_pin_for_meter':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_meter_amount';
            $_SESSION['display'] = "Enter amount to top up:\n#. Back";
        } else if ($input == $correctPin) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$_SESSION['ussd_data']['amount'], $_SESSION['user_id']]);
                $stmt = $pdo->prepare("INSERT INTO meter_transactions (user_id, meter_number, meter_type, amount, transaction_date) VALUES (?, ?, ?, ?, GETDATE())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['ussd_data']['meter_number'],
                    $_SESSION['ussd_data']['meter_type'],
                    $_SESSION['ussd_data']['amount']
                ]);
                $pdo->commit();
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Meter top-up successful!\n\n1. Back to main menu";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['display'] = "Meter top-up failed: " . $e->getMessage();
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
            }
        }
        break;
}

header('Location: index.php');
exit; 
