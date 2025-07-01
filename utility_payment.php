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
    case 'utility_payment':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
        } else {
            if ($input == '') {
                $_SESSION['display'] = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n3. Statement\n#. Back";
            } else {
                switch ($input) {
                    case '1':
                        $_SESSION['ussd_data']['utility_type'] = 'ECG';
                        $_SESSION['ussd_state'] = 'enter_utility_account';
                        $_SESSION['display'] = "Enter your ECG account number:\n#. Back";
                        break;
                    case '2':
                        $_SESSION['ussd_data']['utility_type'] = 'Water';
                        $_SESSION['ussd_state'] = 'enter_utility_account';
                        $_SESSION['display'] = "Enter your Water account number:\n#. Back";
                        break;
                    case '3':
                        $_SESSION['ussd_state'] = 'view_statement';
                        try {
                            $transactions = [];
                            
                            // Try to get transactions - start with most likely to exist
                            // Get Send Money transactions
                            try {
                                $stmt = $pdo->prepare("SELECT 'Send Money' as type, recipient_phone as details, amount, transaction_date FROM transactions WHERE sender_id = ? AND status = 'completed' ORDER BY transaction_date DESC");
                                $stmt->execute([$_SESSION['user_id']]);
                                $sendMoney = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $transactions = array_merge($transactions, $sendMoney);
                            } catch (Exception $e) {
                                // Table might not exist or have data, continue
                            }
                            
                            // Get Airtime purchases
                            try {
                                $stmt = $pdo->prepare("SELECT 'Airtime Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM airtime_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                                $stmt->execute([$_SESSION['user_id']]);
                                $airtime = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $transactions = array_merge($transactions, $airtime);
                            } catch (Exception $e) {
                                // Continue if table doesn't exist
                            }
                            
                            // Get Data purchases  
                            try {
                                $stmt = $pdo->prepare("SELECT 'Data Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM data_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                                $stmt->execute([$_SESSION['user_id']]);
                                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $transactions = array_merge($transactions, $data);
                            } catch (Exception $e) {
                                // Continue if table doesn't exist
                            }
                            
                            // Get Utility payments
                            try {
                                $stmt = $pdo->prepare("SELECT 'Utility Payment' as type, account_number as details, amount, payment_date as transaction_date FROM utility_payments WHERE user_id = ? ORDER BY payment_date DESC");
                                $stmt->execute([$_SESSION['user_id']]);
                                $utility = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $transactions = array_merge($transactions, $utility);
                            } catch (Exception $e) {
                                // Continue if table doesn't exist
                            }
                            
                            // Sort all transactions by date and get top 2
                            if (count($transactions) > 0) {
                                usort($transactions, function($a, $b) {
                                    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
                                });
                                $transactions = array_slice($transactions, 0, 2);
                                
                                $_SESSION['display'] = "Last 2 Transactions:\n\n";
                                $counter = 1;
                                foreach ($transactions as $transaction) {
                                    $date = date('d/m/Y H:i', strtotime($transaction['transaction_date']));
                                    $_SESSION['display'] .= "{$counter}. {$transaction['type']}\n";
                                    $_SESSION['display'] .= "   Details: {$transaction['details']}\n";
                                    $_SESSION['display'] .= "   Amount: GHS " . number_format($transaction['amount'], 2) . "\n";
                                    $_SESSION['display'] .= "   Date: {$date}\n";
                                    if ($counter < count($transactions)) {
                                        $_SESSION['display'] .= "\n";
                                    }
                                    $counter++;
                                }
                                $_SESSION['display'] .= "\n#. Back";
                            } else {
                                $_SESSION['display'] = "No transactions found.\n\n#. Back";
                            }
                        } catch (PDOException $e) {
                            $_SESSION['display'] = "Error retrieving transactions. Please try again later.\n\n#. Back";
                        }
                        break;
                    default:
                        $_SESSION['display'] = "Invalid option. Please select:\n1. ECG (Electricity)\n2. Water\n3. Statement\n#. Back";
                        break;
                }
            }
        }
        break;

    case 'view_statement':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'utility_payment';
            $_SESSION['display'] = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n3. Statement\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid input. Please press #. to go back";
        }
        break;

    case 'enter_utility_account':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'utility_payment';
            $_SESSION['display'] = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n3. Statement\n#. Back";
        } else if (preg_match('/^[A-Za-z0-9]{8,15}$/', $input)) {
            $_SESSION['ussd_data']['utility_account'] = $input;
            $_SESSION['ussd_state'] = 'enter_utility_amount';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Enter amount to pay for $utilityType:\n#. Back";
        } else {
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Invalid account number. Please enter a valid $utilityType account number (8-15 characters):\n#. Back";
        }
        break;

    case 'enter_utility_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_utility_account';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Enter your $utilityType account number:\n#. Back";
        } else if (is_numeric($input) && $input > 0) {
            try {
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['utility_amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_pin_for_utility';
                    $utilityType = $_SESSION['ussd_data']['utility_type'];
                    $account = $_SESSION['ussd_data']['utility_account'];
                    $_SESSION['display'] = "Confirm $utilityType payment:\nAccount: $account\nAmount: GHS " . number_format($input, 2) . "\n\nEnter your PIN to confirm:\n#. Back";
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

    case 'enter_pin_for_utility':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_utility_amount';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Enter amount to pay for $utilityType:\n#. Back";
        } else if ($input == $correctPin) {
            try {
                $amount = $_SESSION['ussd_data']['utility_amount'];
                $utilityType = $_SESSION['ussd_data']['utility_type'];
                $account = $_SESSION['ussd_data']['utility_account'];

                $pdo->beginTransaction();
                
                // Deduct from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Insert into utility_payments table
                $stmt = $pdo->prepare("INSERT INTO utility_payments (user_id, utility_type, account_number, amount, payment_date) VALUES (?, ?, ?, ?, GETDATE())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $utilityType,
                    $account,
                    $amount
                ]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "$utilityType payment successful!\nAccount: $account\nAmount: GHS " . number_format($amount, 2) . "\n\n1. Back to main menu";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Utility payment failed: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.\n\n1. Back to main menu";
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
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