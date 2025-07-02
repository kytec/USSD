<?php
session_start();
require_once 'db_connect.php';
 
if (!isset($_SESSION['ussd_state'])) {
    $_SESSION['ussd_state'] = 'start';
    $_SESSION['ussd_data'] = [];
    $_SESSION['pin_attempts'] = 0;
    $_SESSION['user_id'] = 1; // This should be set based on actual user authentication
}

// Handle cancel button
if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
    // Clear all session data
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Start a new session
    session_start();
    
    // Set the welcome message directly
    $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
    
    // Redirect to index page
    header('Location: index.php');
    exit();
}
 
$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
 
$correctPin = '1234';
 
switch ($_SESSION['ussd_state']) {
 
    case 'start':
        switch ($input) {
            case '1':
                $_SESSION['ussd_state'] = 'select_network';
                $response = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
                break;
            case '2':
                $_SESSION['ussd_state'] = 'buy_airtime_data';
                unset($_SESSION['ussd_data']['service_step']);
                $response = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
                break;
            case '3':
                $_SESSION['ussd_state'] = 'investment';
                $response = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
                break;
            case '4':
                $_SESSION['ussd_state'] = 'utility_payment';
                $response = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n#. Back";
                break;
            case '5':
                $_SESSION['ussd_state'] = 'view_statement';
                // The statement logic is already handled in view_statement
                break;
            default:
                $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
                break;
        }
        break;
 

 
    case 'select_network':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['network'] = 'MTN';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $response = "Enter MTN MobileMoney number:\n#. Back";
                    break;
                case '2':
                    $_SESSION['ussd_data']['network'] = 'Telecel';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $response = "Enter Telecel Cash number:\n#. Back";
                    break;
                case '3':
                    $_SESSION['ussd_data']['network'] = 'AirtelTigo';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $response = "Enter AirtelTigo Cash number:\n#. Back";
                    break;
                case '4':
                    $_SESSION['ussd_data']['network'] = 'Bank';
                    $_SESSION['ussd_state'] = 'enter_bank_account';
                    $response = "Enter Bank Account Number:\n#. Back";
                    break;
                default:
                    $response = "Invalid option. Please select:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
                    break;
            }
        }
        break;
 
    case 'enter_recipient':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_network';
            $response = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
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
                    $response = "Confirm recipient: " . $input . "\n1. Confirm\n#. Back";
                } catch (PDOException $e) {
                    $response = "Error saving recipient. Please try again:\nEnter " . $network . " number:\n#. Back";
                }
            } else {
                $response = $errorMessage . "\nPlease enter a valid " . $network . " number:\n#. Back";
            }
        }
        break;
 
    case 'confirm_recipient':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'enter_amount';
            $response = "Enter amount to transfer:";
        } else if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_recipient';
            $response = "Enter " . $_SESSION['ussd_data']['network'] . " number:";
        } else {
            $response = "Invalid option. Please select:\n1. Confirm\n#. Back";
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
                    $response = "Enter reference:";
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
 
    case 'enter_reference':
        if (!empty($input)) {
            try {
                // Update reference in transaction table
                $stmt = $pdo->prepare("UPDATE transactions SET reference = ? WHERE id = ?");
                $stmt->execute([$input, $_SESSION['transaction_id']]);
                
                $_SESSION['ussd_data']['reference'] = $input; // Store for later use
                
                $_SESSION['ussd_state'] = 'enter_pin';
                $response = "Enter your PIN:";
            } catch (PDOException $e) {
                $response = "Error saving reference. Please try again:\nEnter reference:";
            }
        } else if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_amount';
            $response = "Enter amount to transfer:";
        } else {
            $response = "Reference cannot be empty. Please enter a reference:";
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
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Transfer successful!\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
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
            $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else {
            $response = "Invalid option. Please select:\n1. Back to main menu";
        }
        break;
 
    case 'buy_airtime_data':
        if ($input == '#') {
            // Handle back navigation based on current step
            if (!isset($_SESSION['ussd_data']['service_step']) || $_SESSION['ussd_data']['service_step'] == 1) {
                $_SESSION['ussd_state'] = 'start';
                $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
                header('Location: index.php');
                exit;
            } else if ($_SESSION['ussd_data']['service_step'] == 2) {
                $_SESSION['ussd_data']['service_step'] = 1;
                $response = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
            } else if ($_SESSION['ussd_data']['service_step'] == 3) {
                $_SESSION['ussd_data']['service_step'] = 2;
                $serviceType = $_SESSION['ussd_data']['service_type'];
                $response = "Buy " . ucfirst($serviceType) . ":\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
            } else {
                // Go back to previous steps based on context
                if (isset($_SESSION['ussd_data']['for_self']) && $_SESSION['ussd_data']['for_self']) {
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $serviceType = $_SESSION['ussd_data']['service_type'];
                    $response = "Buy " . ucfirst($serviceType) . ":\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else {
                    $_SESSION['ussd_data']['service_step'] = 3;
                    $response = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                }
            }
        } else {
            if (!isset($_SESSION['ussd_data']['service_step']) || $input == '') {
                $_SESSION['ussd_data']['service_step'] = 1;
                $response = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
            } else if ($_SESSION['ussd_data']['service_step'] == 1) {
                // Choose service type
                if ($input == '1') {
                    $_SESSION['ussd_data']['service_type'] = 'airtime';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $response = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['service_type'] = 'data';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $response = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else {
                    $response = "Invalid option. Please select:\n1. Buy Airtime\n2. Buy Data\n#. Back";
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 2) {
                // Choose self or other
                if ($input == '1') {
                    $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $_SESSION['ussd_data']['phone_number'] = $user ? $user['phone'] : '';
                    $_SESSION['ussd_data']['for_self'] = true;
                    
                    if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                        $_SESSION['ussd_data']['service_step'] = 5; // Skip network and phone selection
                        $response = "Enter amount to buy (GHS):\n#. Back";
                    } else {
                        $_SESSION['ussd_data']['service_step'] = 5; // Skip network and phone selection
                        $response = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                    }
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['for_self'] = false;
                    $_SESSION['ussd_data']['service_step'] = 3;
                    $response = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                } else {
                    $serviceType = $_SESSION['ussd_data']['service_type'];
                    $response = "Invalid option. Please select:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 3) {
                // Choose network for other
                if ($input == '1') {
                    $_SESSION['ussd_data']['network'] = 'Telecel';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $response = "Enter recipient Telecel mobile number:\n#. Back";
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['network'] = 'AirtelTigo';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $response = "Enter recipient AirtelTigo mobile number:\n#. Back";
                } else if ($input == '3') {
                    $_SESSION['ussd_data']['network'] = 'MTN';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $response = "Enter recipient MTN mobile number:\n#. Back";
                } else {
                    $response = "Invalid option. Please select:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 4) {
                // Enter phone number for other
                $network = $_SESSION['ussd_data']['network'];
                $valid = false;
                if ($network == 'MTN' && preg_match('/^(054|053|024|059|025|055)[0-9]{7}$/', $input)) {
                    $valid = true;
                } else if ($network == 'Telecel' && preg_match('/^(020|050)[0-9]{7}$/', $input)) {
                    $valid = true;
                } else if ($network == 'AirtelTigo' && preg_match('/^(026|056|027|057)[0-9]{7}$/', $input)) {
                    $valid = true;
                }
                
                if ($valid) {
                    $_SESSION['ussd_data']['phone_number'] = $input;
                    $_SESSION['ussd_data']['service_step'] = 5;
                    
                    if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                        $response = "Enter amount to buy (GHS):\n#. Back";
                    } else {
                        $response = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                    }
                } else {
                    $response = "Invalid number for $network. Please enter a valid number for $network:\n#. Back";
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 5) {
                if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                    // Enter amount for airtime
                    if (is_numeric($input) && $input > 0) {
                        $_SESSION['ussd_data']['amount'] = $input;
                        $_SESSION['ussd_data']['service_step'] = 6;
                        $response = "Confirm Airtime Purchase:\nBuy GHS " . number_format($input, 2) . " airtime for " . $_SESSION['ussd_data']['phone_number'] . ".\nEnter your PIN to confirm:\n#. Back";
                    } else {
                        $response = "Invalid amount. Please enter a valid amount (GHS):\n#. Back";
                    }
                } else {
                    // Select data bundle
                    $bundles = [
                        '1' => ['size' => '1GB', 'price' => 5],
                        '2' => ['size' => '2GB', 'price' => 9],
                        '3' => ['size' => '5GB', 'price' => 20],
                        '4' => ['size' => '10GB', 'price' => 35]
                    ];
                    
                    if (isset($bundles[$input])) {
                        $_SESSION['ussd_data']['data_bundle'] = $bundles[$input]['size'];
                        $_SESSION['ussd_data']['amount'] = $bundles[$input]['price'];
                        $_SESSION['ussd_data']['service_step'] = 6;
                        $response = "Confirm Data Purchase:\nBuy " . $bundles[$input]['size'] . " data bundle for GHS " . $bundles[$input]['price'] . " for " . $_SESSION['ussd_data']['phone_number'] . ".\nEnter your PIN to confirm:\n#. Back";
                    } else {
                        $response = "Invalid option. Please select:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                    }
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 6) {
                // PIN confirmation
                $enteredPin = $input;
                $stmt = $pdo->prepare("SELECT pin, balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $amount = $_SESSION['ussd_data']['amount'];
                
                if (!$user || $enteredPin !== $user['pin']) {
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $serviceName = $_SESSION['ussd_data']['service_type'];
                    $_SESSION['display'] = "Invalid PIN. " . ucfirst($serviceName) . " purchase cancelled.\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }
                
                if ($user['balance'] < $amount) {
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $_SESSION['display'] = "Insufficient funds. Your balance is GHS " . number_format($user['balance'], 2) . ".\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }
                
                // Process the transaction
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->execute([$amount, $_SESSION['user_id']]);
                    
                    if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                        $stmt = $pdo->prepare("INSERT INTO airtime_purchases (user_id, phone_number, amount, purchase_date) VALUES (?, ?, ?, GETDATE())");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $_SESSION['ussd_data']['phone_number'],
                            $amount
                        ]);
                        $successMessage = "Airtime purchase successful!";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO data_purchases (user_id, phone_number, data_bundle, amount, purchase_date) VALUES (?, ?, ?, ?, GETDATE())");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $_SESSION['ussd_data']['phone_number'],
                            $_SESSION['ussd_data']['data_bundle'],
                            $amount
                        ]);
                        $successMessage = "Data purchase successful!";
                    }
                    
                    $pdo->commit();
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $_SESSION['display'] = $successMessage . "\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $serviceName = $_SESSION['ussd_data']['service_type'];
                    $_SESSION['display'] = ucfirst($serviceName) . " purchase failed: " . $e->getMessage() . "\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }
            }
        }
        break;

    case 'buy_airtime':
        if ($input == '#') {
            // Go back to previous step or main menu depending on current step
            if (isset($_SESSION['ussd_data']['airtime_step']) && (
                ($_SESSION['ussd_data']['airtime_step'] == 2 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'self') ||
                ($_SESSION['ussd_data']['airtime_step'] == 4 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other') ||
                ($_SESSION['ussd_data']['airtime_step'] == 2 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other')
            )) {
                // Go back to self/other selection
                $_SESSION['ussd_data']['airtime_step'] = 1;
                $response = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
            } else if (isset($_SESSION['ussd_data']['airtime_step']) && $_SESSION['ussd_data']['airtime_step'] == 3 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other') {
                // Go back to network selection
                $_SESSION['ussd_data']['airtime_step'] = 2;
                $response = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
            } else {
                // Go back to main menu from any other step
                $_SESSION['ussd_state'] = 'start';
                $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
                header('Location: index.php');
                exit;
            }
        }
        if (!isset($_SESSION['ussd_data']['airtime_step']) || $input == '') {
            $_SESSION['ussd_data']['airtime_step'] = 1;
            $response = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
        } else if ($_SESSION['ussd_data']['airtime_step'] == 1) {
            if ($input == '1') {
                $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['ussd_data']['airtime_number'] = $user ? $user['phone'] : '';
                $_SESSION['ussd_data']['airtime_for'] = 'self';
                $_SESSION['ussd_data']['airtime_step'] = 2;
                $response = "Enter amount to buy (GHS):\n#. Back";
            } else if ($input == '2') {
                $_SESSION['ussd_data']['airtime_for'] = 'other';
                $_SESSION['ussd_data']['airtime_step'] = 2;
                $response = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
            } else {
                $response = "Invalid option. Please select:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
            }
        } else if ($_SESSION['ussd_data']['airtime_step'] == 2 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other') {
            if ($input == '1') {
                $_SESSION['ussd_data']['airtime_network'] = 'Telecel';
                $_SESSION['ussd_data']['airtime_step'] = 3;
                $response = "Enter recipient Telecel mobile number:\n#. Back";
            } else if ($input == '2') {
                $_SESSION['ussd_data']['airtime_network'] = 'AirtelTigo';
                $_SESSION['ussd_data']['airtime_step'] = 3;
                $response = "Enter recipient AirtelTigo mobile number:\n#. Back";
            } else if ($input == '3') {
                $_SESSION['ussd_data']['airtime_network'] = 'MTN';
                $_SESSION['ussd_data']['airtime_step'] = 3;
                $response = "Enter recipient MTN mobile number:\n#. Back";
            } else {
                $response = "Invalid option. Please select:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
            }
        } else if ($_SESSION['ussd_data']['airtime_step'] == 3 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other') {
            $network = $_SESSION['ussd_data']['airtime_network'];
            $valid = false;
            if ($network == 'MTN' && preg_match('/^(054|053|024|059|025|055)[0-9]{7}$/', $input)) {
                $valid = true;
            } else if ($network == 'Telecel' && preg_match('/^(020|050)[0-9]{7}$/', $input)) {
                $valid = true;
            } else if ($network == 'AirtelTigo' && preg_match('/^(026|056|027|057)[0-9]{7}$/', $input)) {
                $valid = true;
            }
            if ($valid) {
                $_SESSION['ussd_data']['airtime_number'] = $input;
                $_SESSION['ussd_data']['airtime_step'] = 4;
                $response = "Enter amount to buy (GHS):\n#. Back";
            } else {
                $response = "Invalid number for $network. Please enter a valid number for $network:\n#. Back";
            }
        } else if (
            ($_SESSION['ussd_data']['airtime_step'] == 2 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'self') ||
            ($_SESSION['ussd_data']['airtime_step'] == 4 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other')
        ) {
            if ($input == '#') {
                // Go back to previous step
                if ($_SESSION['ussd_data']['airtime_for'] == 'self') {
                    $_SESSION['ussd_data']['airtime_step'] = 1;
                    $response = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else {
                    $_SESSION['ussd_data']['airtime_step'] = 3;
                    $response = "Enter recipient {$_SESSION['ussd_data']['airtime_network']} mobile number:\n#. Back";
                }
            } else if (is_numeric($input) && $input > 0) {
                $_SESSION['ussd_data']['airtime_amount'] = $input;
                $_SESSION['ussd_data']['airtime_step'] = 5;
                $response = "Confirm Airtime Purchase:\nBuy GHS " . number_format($input, 2) . " airtime for " . $_SESSION['ussd_data']['airtime_number'] . ".\nEnter your PIN to confirm:\n#. Back";
            } else {
                $response = "Invalid amount. Please enter a valid amount (GHS):\n#. Back";
            }
        } else if ($_SESSION['ussd_data']['airtime_step'] == 5) {
            if ($input == '#') {
                // Go back to amount entry
                if ($_SESSION['ussd_data']['airtime_for'] == 'self') {
                    $_SESSION['ussd_data']['airtime_step'] = 2;
                    $response = "Enter amount to buy (GHS):\n#. Back";
                } else {
                    $_SESSION['ussd_data']['airtime_step'] = 4;
                    $response = "Enter amount to buy (GHS):\n#. Back";
                }
            } else {
                // User enters PIN
                $enteredPin = $input;
                $stmt = $pdo->prepare("SELECT pin, balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $amount = $_SESSION['ussd_data']['airtime_amount'];
                if (!$user || $enteredPin !== $user['pin']) {
                    $_SESSION['ussd_data']['airtime_step'] = 1;
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $_SESSION['display'] = "Invalid PIN. Airtime purchase cancelled.\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }
                if ($user['balance'] < $amount) {
                    $_SESSION['ussd_data']['airtime_step'] = 1;
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $_SESSION['display'] = "Insufficient funds. Your balance is GHS " . number_format($user['balance'], 2) . ".\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }
                // Deduct and record transaction
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->execute([$amount, $_SESSION['user_id']]);
                    $stmt = $pdo->prepare("INSERT INTO airtime_purchases (user_id, phone_number, amount, purchase_date) VALUES (?, ?, ?, GETDATE())");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $_SESSION['ussd_data']['airtime_number'],
                        $amount
                    ]);
                    $pdo->commit();
                    $_SESSION['ussd_data']['airtime_step'] = 1;
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $_SESSION['display'] = "Airtime purchase successful!\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $_SESSION['ussd_data']['airtime_step'] = 1;
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $_SESSION['display'] = "Airtime purchase failed: " . $e->getMessage() . "\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }
            }
        }
        break;

    case 'meter_topup':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else if ($input == '') {
            $response = "Enter meter number:\n#. Back";
        } else if (preg_match('/^[A-Za-z0-9]{11}$/', $input)) {
            $_SESSION['ussd_data']['meter_number'] = $input;
            $_SESSION['ussd_state'] = 'select_meter_type';
            $response = "Select Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
        } else {
            $response = "Invalid meter number. Please enter a valid 11-digit meter number:\n#. Back";
        }
        break;

    case 'select_meter_type':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'meter_topup';
            $response = "Enter meter number:\n#. Back";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['meter_type'] = 'Prepaid';
                    $_SESSION['ussd_state'] = 'enter_meter_amount';
                    $response = "Enter amount to top up:\n#. Back";
                    break;
                case '2':
                    $_SESSION['ussd_data']['meter_type'] = 'Postpaid';
                    $_SESSION['ussd_state'] = 'enter_meter_amount';
                    $response = "Enter amount to top up:\n#. Back";
                    break;
                default:
                    $response = "Invalid option. Please select:\n1. Prepaid\n2. Postpaid\n#. Back";
                    break;
            }
        }
        break;

    case 'enter_meter_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_meter_type';
            $response = "Select Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
        } else if (is_numeric($input) && $input > 0) {
            try {
                // Check if user has sufficient balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_pin_for_meter';
                    $response = "Enter your PIN to confirm meter top-up of GHS " . number_format($input, 2) . 
                               " for meter " . $_SESSION['ussd_data']['meter_number'] . 
                               " (" . $_SESSION['ussd_data']['meter_type'] . "):\n#. Back";
                } else {
                    $response = "Insufficient balance. Please enter a valid amount:\n#. Back";
                }
            } catch (PDOException $e) {
                $response = "Error processing request. Please try again:\nEnter amount to top up:\n#. Back";
            }
        } else {
            $response = "Invalid amount. Please enter a valid amount:\n#. Back";
        }
        break;

    case 'enter_pin_for_meter':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_meter_amount';
            $_SESSION['display'] = "Enter amount to top up:\n#. Back";
            header('Location: index.php');
            exit;
        } else if ($input == $correctPin) {
            try {
                $pdo->beginTransaction();

                // Deduct amount from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$_SESSION['ussd_data']['amount'], $_SESSION['user_id']]);

                // Record meter transaction
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
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['display'] = "Meter top-up failed: " . $e->getMessage();
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
                header('Location: index.php');
                exit;
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
                header('Location: index.php');
                exit;
            }
        }
        

    case 'transaction_success':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Back to main menu";
        }
        header('Location: index.php');
        exit;

    case 'investment':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_state'] = 'fixed_deposit';
                    $response = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
                    break;
                case '2':
                    $_SESSION['ussd_state'] = 'treasury_bills';
                    $response = "Enter amount to invest in Treasury Bills:\n#. Back";
                    break;
                case '3':
                    $_SESSION['ussd_state'] = 'mutual_funds';
                    $response = "Enter amount to invest in Mutual Funds:\n#. Back";
                    break;
                default:
                    $response = "Invalid option. Please select:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
                    break;
            }
        }
        break;

    case 'fixed_deposit':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $response = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
        } else if (in_array($input, ['1', '2', '3'])) {
            $durations = ['1' => 3, '2' => 6, '3' => 12];
            $interest_rates = ['1' => 5.00, '2' => 7.00, '3' => 10.00];
            
            $_SESSION['ussd_data']['fd_duration'] = $durations[$input];
            $_SESSION['ussd_data']['fd_interest_rate'] = $interest_rates[$input];
            
            $_SESSION['ussd_state'] = 'enter_fixed_deposit_amount';
            $response = "Enter amount for Fixed Deposit (Duration: " . $durations[$input] . " Months, Interest: " . $interest_rates[$input] . "% p.a.):\n#. Back";
        } else {
            $response = "Invalid option. Please select:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
        }
        break;

    case 'enter_fixed_deposit_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'fixed_deposit';
            $response = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
        } else if (is_numeric($input) && $input > 0) {
            try {
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['fd_amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_pin_for_fixed_deposit';
                    $response = "Enter your PIN to confirm Fixed Deposit of GHS " . number_format($input, 2) . " for " . $_SESSION['ussd_data']['fd_duration'] . " months at " . $_SESSION['ussd_data']['fd_interest_rate'] . "% p.a.:\n#. Back";
                } else {
                    $response = "Insufficient balance. Please enter a valid amount:\n#. Back";
                }
            } catch (PDOException $e) {
                $response = "Error checking balance. Please try again:\n#. Back";
            }
        } else {
            $response = "Invalid amount. Please enter a valid amount:\n#. Back";
        }
        break;

    case 'enter_pin_for_fixed_deposit':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_fixed_deposit_amount';
            $response = "Enter amount for Fixed Deposit (Duration: " . $_SESSION['ussd_data']['fd_duration'] . " Months, Interest: " . $_SESSION['ussd_data']['fd_interest_rate'] . "% p.a.):\n#. Back";
        } else if ($input == $correctPin) {
            try {
                $amount = $_SESSION['ussd_data']['fd_amount'];
                $duration = $_SESSION['ussd_data']['fd_duration'];
                $interest_rate = $_SESSION['ussd_data']['fd_interest_rate'];
                $maturity_date = date('Y-m-d H:i:s', strtotime("+{$duration} months"));

                $pdo->beginTransaction();
                
                // Deduct from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Insert into fixed_deposits table
                $stmt = $pdo->prepare("INSERT INTO fixed_deposits (user_id, amount, duration_months, interest_rate, maturity_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $amount,
                    $duration,
                    $interest_rate,
                    $maturity_date
                ]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Fixed Deposit successful! Maturity Date: " . date('Y-m-d', strtotime($maturity_date)) . "\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = "Error processing Fixed Deposit: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $response = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $response = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
            }
        }
        break;

    case 'treasury_bills':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $response = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
        } else if (is_numeric($input) && $input > 0) {
            try {
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['tb_amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_pin_for_treasury_bills';
                    $response = "Enter your PIN to confirm Treasury Bills investment of GHS " . number_format($input, 2) . ":\n#. Back";
                } else {
                    $response = "Insufficient balance. Please enter a valid amount:\n#. Back";
                }
            } catch (PDOException $e) {
                $response = "Error checking balance. Please try again:\n#. Back";
            }
        } else {
            $response = "Invalid amount. Please enter a valid amount:\n#. Back";
        }
        break;

    case 'enter_pin_for_treasury_bills':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'treasury_bills';
            $response = "Enter amount to invest in Treasury Bills:\n#. Back";
        } else if ($input == $correctPin) {
            try {
                $amount = $_SESSION['ussd_data']['tb_amount'];
                // For simplicity, hardcoding interest rate and maturity for Treasury Bills
                $interest_rate = 8.50; // Example annual interest rate
                $maturity_date = date('Y-m-d H:i:s', strtotime("+3 months")); // Example 3 months maturity

                $pdo->beginTransaction();
                
                // Deduct from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Insert into treasury_bills_investments table
                $stmt = $pdo->prepare("INSERT INTO treasury_bills_investments (user_id, amount, interest_rate, maturity_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $amount,
                    $interest_rate,
                    $maturity_date
                ]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Treasury Bills investment successful! Maturity Date: " . date('Y-m-d', strtotime($maturity_date)) . "\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = "Error processing Treasury Bills investment: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $response = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $response = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
            }
        }
        break;

    case 'mutual_funds':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $response = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
        } else if (is_numeric($input) && $input > 0) {
            try {
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['mf_amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_mutual_fund_name';
                    $response = "Enter name of Mutual Fund:\n#. Back";
                } else {
                    $response = "Insufficient balance. Please enter a valid amount:\n#. Back";
                }
            } catch (PDOException $e) {
                $response = "Error checking balance. Please try again:\n#. Back";
            }
        } else {
            $response = "Invalid amount. Please enter a valid amount:\n#. Back";
        }
        break;

    case 'enter_mutual_fund_name':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'mutual_funds';
            $response = "Enter amount to invest in Mutual Funds:\n#. Back";
        } else if (!empty($input)) {
            $_SESSION['ussd_data']['mf_name'] = $input;
            $_SESSION['ussd_state'] = 'enter_pin_for_mutual_funds';
            $response = "Enter your PIN to confirm Mutual Funds investment of GHS " . number_format($_SESSION['ussd_data']['mf_amount'], 2) . " in " . $input . ":\n#. Back";
        } else {
            $response = "Mutual Fund name cannot be empty. Please enter a name:\n#. Back";
        }
        break;

    case 'enter_pin_for_mutual_funds':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_mutual_fund_name';
            $response = "Enter name of Mutual Fund:\n#. Back";
        } else if ($input == $correctPin) {
            try {
                $amount = $_SESSION['ussd_data']['mf_amount'];
                $fund_name = $_SESSION['ussd_data']['mf_name'];

                $pdo->beginTransaction();
                
                // Deduct from user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                // Insert into mutual_funds_investments table
                $stmt = $pdo->prepare("INSERT INTO mutual_funds_investments (user_id, amount, fund_name) VALUES (?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $amount,
                    $fund_name
                ]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Mutual Funds investment successful!\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = "Error processing Mutual Funds investment: " . $e->getMessage() . "\n\n1. Back to main menu";
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $response = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $response = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
            }
        }
        break;

    case 'investment_success':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else {
            $response = "Invalid option. Please select:\n1. Back to main menu";
        }
        break;

    case 'utility_payment':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['utility_type'] = 'ECG';
                    $_SESSION['ussd_state'] = 'select_ecg_meter_type';
                    $response = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
                    break;
                case '2':
                    $_SESSION['ussd_data']['utility_type'] = 'Water';
                    $_SESSION['ussd_state'] = 'enter_utility_account';
                    $response = "Enter your Water account number:\n#. Back";
                    break;
                default:
                    $response = "Invalid option. Please select:\n1. ECG (Electricity)\n2. Water\n#. Back";
                    break;
            }
        }
        break;

    case 'view_statement':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement";
        } else {
            try {
                $transactions = [];
                // Get Send Money transactions
                try {
                    $stmt = $pdo->prepare("SELECT 'Send Money' as type, recipient_phone as details, amount, transaction_date FROM transactions WHERE sender_id = ? AND status = 'completed' ORDER BY transaction_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $sendMoney = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $sendMoney);
                } catch (Exception $e) {}
                // Get Airtime purchases
                try {
                    $stmt = $pdo->prepare("SELECT 'Airtime Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM airtime_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $airtime = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $airtime);
                } catch (Exception $e) {}
                // Get Data purchases
                try {
                    $stmt = $pdo->prepare("SELECT 'Data Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM data_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $data);
                } catch (Exception $e) {}
                // Get Utility payments
                try {
                    $stmt = $pdo->prepare("SELECT 'Utility Payment' as type, account_number as details, amount, payment_date as transaction_date FROM utility_payments WHERE user_id = ? ORDER BY payment_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $utility = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $utility);
                } catch (Exception $e) {}
                // Sort all transactions by date and get top 2
                if (count($transactions) > 0) {
                    usort($transactions, function($a, $b) {
                        return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
                    });
                    $transactions = array_slice($transactions, 0, 2);
                    $response = "Last 2 Transactions:\n\n";
                    $counter = 1;
                    foreach ($transactions as $transaction) {
                        $date = date('d/m/Y H:i', strtotime($transaction['transaction_date']));
                        $response .= "{$counter}. {$transaction['type']}\n";
                        $response .= "   Details: {$transaction['details']}\n";
                        $response .= "   Amount: GHS " . number_format($transaction['amount'], 2) . "\n";
                        $response .= "   Date: {$date}\n";
                        if ($counter < count($transactions)) {
                            $response .= "\n";
                        }
                        $counter++;
                    }
                    $response .= "\n#. Back";
                } else {
                    $response = "No transactions found.\n\n#. Back";
                }
            } catch (PDOException $e) {
                $response = "Error retrieving transactions. Please try again later.\n\n#. Back";
            }
        }
        break;

    case 'select_ecg_meter_type':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'utility_payment';
            $response = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n#. Back";
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['meter_type'] = 'Prepaid';
                    $_SESSION['ussd_state'] = 'enter_utility_account';
                    $response = "Enter your ECG meter number:\n#. Back";
                    break;
                case '2':
                    $_SESSION['ussd_data']['meter_type'] = 'Postpaid';
                    $_SESSION['ussd_state'] = 'enter_utility_account';
                    $response = "Enter your ECG meter number:\n#. Back";
                    break;
                default:
                    $response = "Invalid option. Please select:\n1. Prepaid\n2. Postpaid\n#. Back";
                    break;
            }
        }
        break;

    case 'enter_utility_account':
        if ($input == '#') {
            // Handle back navigation based on utility type
            if (isset($_SESSION['ussd_data']['utility_type']) && $_SESSION['ussd_data']['utility_type'] == 'ECG') {
                $_SESSION['ussd_state'] = 'select_ecg_meter_type';
                $response = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
            } else {
                $_SESSION['ussd_state'] = 'utility_payment';
                $response = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n#. Back";
            }
        } else if (preg_match('/^[A-Za-z0-9]{8,15}$/', $input)) {
            $_SESSION['ussd_data']['utility_account'] = $input;
            $_SESSION['ussd_state'] = 'enter_utility_amount';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $response = "Enter amount to pay for $utilityType:\n#. Back";
        } else {
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $response = "Invalid account number. Please enter a valid $utilityType account number (8-15 characters):\n#. Back";
        }
        break;

    case 'enter_utility_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_utility_account';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $response = "Enter your $utilityType account number:\n#. Back";
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
                    $response = "Confirm $utilityType payment:\nAccount: $account";
                    if (isset($_SESSION['ussd_data']['meter_type'])) {
                        $response .= " (" . $_SESSION['ussd_data']['meter_type'] . ")";
                    }
                    $response .= "\nAmount: GHS " . number_format($input, 2) . "\n\nEnter your PIN to confirm:\n#. Back";
                } else {
                    $response = "Insufficient balance. Please enter a valid amount:\n#. Back";
                }
            } catch (PDOException $e) {
                $response = "Error checking balance. Please try again:\n#. Back";
            }
        } else {
            $response = "Invalid amount. Please enter a valid amount:\n#. Back";
        }
        break;

    case 'enter_pin_for_utility':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_utility_amount';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $response = "Enter amount to pay for $utilityType:\n#. Back";
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
                $stmt = $pdo->prepare("INSERT INTO utility_payments (user_id, utility_type, account_number, meter_type, amount, payment_date) VALUES (?, ?, ?, ?, ?, GETDATE())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $utilityType,
                    $account,
                    isset($_SESSION['ussd_data']['meter_type']) ? $_SESSION['ussd_data']['meter_type'] : null,
                    $amount
                ]);
                
                $pdo->commit();
                
                $_SESSION['ussd_state'] = 'transaction_success';
                $successMessage = "$utilityType payment successful!\nAccount: $account";
                if (isset($_SESSION['ussd_data']['meter_type'])) {
                    $successMessage .= " (" . $_SESSION['ussd_data']['meter_type'] . ")";
                }
                $successMessage .= "\nAmount: GHS " . number_format($amount, 2) . "\n\n1. Back to main menu";
                $_SESSION['display'] = $successMessage;
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Utility payment failed: " . $e->getMessage() . "\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $response = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
            }
        }
        break;

    case 'enter_bank_account':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_network';
            $response = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
        } else if (preg_match('/^[0-9]{10,20}$/', $input)) {
            $_SESSION['ussd_data']['bank_account'] = $input;
            $_SESSION['ussd_state'] = 'enter_bank_amount';
            $response = "Enter amount to send to bank account:\n#. Back";
        } else {
            $response = "Invalid bank account number. Please enter a valid account number (10-20 digits):\n#. Back";
        }
        break;

    case 'enter_bank_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_bank_account';
            $response = "Enter Bank Account Number:\n#. Back";
        } else if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['bank_amount'] = $input;
            $_SESSION['ussd_state'] = 'confirm_bank_transfer';
            $response = "Confirm transfer of GHS " . number_format($input, 2) . " to account " . $_SESSION['ussd_data']['bank_account'] . ":\n1. Confirm\n#. Back";
        } else {
            $response = "Invalid amount. Please enter a valid amount:\n#. Back";
        }
        break;

    case 'confirm_bank_transfer':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_bank_amount';
            $response = "Enter amount to send to bank account:\n#. Back";
        } else if ($input == '1') {
            $_SESSION['ussd_state'] = 'transaction_success';
            $_SESSION['display'] = "Bank transfer successful!\nAccount: " . $_SESSION['ussd_data']['bank_account'] . "\nAmount: GHS " . number_format($_SESSION['ussd_data']['bank_amount'], 2) . "\n\n1. Back to main menu";
            header('Location: index.php');
            exit;
        } else {
            $response = "Invalid option. Please select:\n1. Confirm\n#. Back";
        }
        break;
 
}
 
$_SESSION['display'] = $response;
 
header('Location: index.php');
exit;

function callNetworkProviderAPI($data) {
    // This is a placeholder for the actual API call to the network provider
    // You'll need to implement this based on the provider's API documentation
    try {
        // Example API call structure
        $apiUrl = "https://network-provider-api.com/meter-topup";
        $apiKey = "your-api-key";
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'reference' => $result['reference'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'API Error: ' . $response
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Connection Error: ' . $e->getMessage()
        ];
    }
}
?>
