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
    case 'investment':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
        } else {
            if ($input == '') {
                $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
            } else {
                switch ($input) {
                    case '1':
                        $_SESSION['ussd_data']['investment_type'] = 'Fixed Deposit';
                        $_SESSION['ussd_state'] = 'investment_amount';
                        $_SESSION['display'] = "Enter investment amount for Fixed Deposit (Min: GHS 100):";
                        break;
                    case '2':
                        $_SESSION['ussd_data']['investment_type'] = 'Treasury Bills';
                        $_SESSION['ussd_state'] = 'investment_amount';
                        $_SESSION['display'] = "Enter investment amount for Treasury Bills (Min: GHS 500):";
                        break;
                    case '3':
                        $_SESSION['ussd_data']['investment_type'] = 'Mutual Funds';
                        $_SESSION['ussd_state'] = 'investment_amount';
                        $_SESSION['display'] = "Enter investment amount for Mutual Funds (Min: GHS 50):";
                        break;
                    default:
                        $_SESSION['display'] = "Invalid option. Please select:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
                        break;
                }
            }
        }
        break;

    case 'investment_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
        } else if (is_numeric($input) && $input > 0) {
            $investment_type = $_SESSION['ussd_data']['investment_type'];
            $min_amount = 0;
            
            switch ($investment_type) {
                case 'Fixed Deposit':
                    $min_amount = 100;
                    break;
                case 'Treasury Bills':
                    $min_amount = 500;
                    break;
                case 'Mutual Funds':
                    $min_amount = 50;
                    break;
            }
            
            if ($input >= $min_amount) {
                try {
                    // Check if user has sufficient balance
                    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && $user['balance'] >= $input) {
                        $_SESSION['ussd_data']['investment_amount'] = $input;
                        $_SESSION['ussd_state'] = 'investment_confirm';
                        $_SESSION['display'] = "Confirm Investment:\nType: $investment_type\nAmount: GHS " . number_format($input, 2) . "\nEnter your PIN to confirm:";
                    } else {
                        $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:";
                    }
                } catch (PDOException $e) {
                    $_SESSION['display'] = "Error checking balance. Please try again:";
                }
            } else {
                $_SESSION['display'] = "Minimum investment amount for $investment_type is GHS $min_amount. Please enter a valid amount:";
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:";
        }
        break;

    case 'investment_confirm':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment_amount';
            $investment_type = $_SESSION['ussd_data']['investment_type'];
            $min_amount = $investment_type == 'Fixed Deposit' ? 100 : ($investment_type == 'Treasury Bills' ? 500 : 50);
            $_SESSION['display'] = "Enter investment amount for $investment_type (Min: GHS $min_amount):";
        } else if ($input == $correctPin) {
            try {
                $amount = $_SESSION['ussd_data']['investment_amount'];
                $investment_type = $_SESSION['ussd_data']['investment_type'];
                
                $pdo->beginTransaction();
                
                // Deduct from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Calculate maturity date based on investment type
                $maturity_date = '';
                switch ($investment_type) {
                    case 'Fixed Deposit':
                        $maturity_date = date('Y-m-d', strtotime('+1 year'));
                        break;
                    case 'Treasury Bills':
                        $maturity_date = date('Y-m-d', strtotime('+91 days'));
                        break;
                    case 'Mutual Funds':
                        $maturity_date = date('Y-m-d', strtotime('+6 months'));
                        break;
                }
                
                // Insert into investments table (you may need to create this table)
                $stmt = $pdo->prepare("INSERT INTO investments (user_id, investment_type, amount, maturity_date, created_at) VALUES (?, ?, ?, ?, GETDATE())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $investment_type,
                    $amount,
                    $maturity_date
                ]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "$investment_type investment successful! Maturity Date: " . date('Y-m-d', strtotime($maturity_date)) . "\n\n1. Back to main menu";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Investment failed: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.\n\n1. Back to main menu";
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