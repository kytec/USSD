<?php
// Utility Payment flow handler
switch ($_SESSION['ussd_state']) {
    case 'utility_payment':
        // Clear any previous utility payment data to start fresh
        unset($_SESSION['ussd_data']['utility_type']);
        unset($_SESSION['ussd_data']['meter_type']);
        unset($_SESSION['ussd_data']['meter_number']);
        unset($_SESSION['ussd_data']['account_number']);
        unset($_SESSION['ussd_data']['amount']);
        
        // Initial state - show utility payment options menu
        $_SESSION['ussd_state'] = 'utility_payment_input';
        $_SESSION['display'] = "Utility Payment Options:\n1. ECG (Electricity)\n2. Water\n3. DSTV\n4. GOTV\n#. Back";
        header('Location: index.php');
        exit;
        
    case 'utility_payment_input':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $menuManager = $GLOBALS['menuManager'];
            $userId = $GLOBALS['userId'];
            $userBalance = $GLOBALS['userBalance'];
            $menuItems = $menuManager->getMainMenu($userId, $userBalance);
            $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
            header('Location: index.php');
            exit;
        } elseif (!empty($input)) {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['utility_type'] = 'ECG';
                    $_SESSION['ussd_state'] = 'select_ecg_meter_type';
                    $_SESSION['display'] = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
                    header('Location: index.php');
                    exit;
                case '2':
                    $_SESSION['ussd_data']['utility_type'] = 'Water';
                    $_SESSION['ussd_state'] = 'enter_utility_account';
                    $_SESSION['display'] = "Enter your Water account number:\n#. Back";
                    header('Location: index.php');
                    exit;
                case '3':
                    $_SESSION['ussd_data']['utility_type'] = 'DSTV';
                    $_SESSION['ussd_state'] = 'enter_dstv_smartcard';
                    $_SESSION['display'] = "Enter the smart card number:\n#. Back";
                    header('Location: index.php');
                    exit;
                case '4':
                    $_SESSION['ussd_data']['utility_type'] = 'GOTV';
                    $_SESSION['ussd_state'] = 'enter_gotv_iuc';
                    $_SESSION['display'] = "Enter the IUC number:\n#. Back";
                    header('Location: index.php');
                    exit;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. ECG (Electricity)\n2. Water\n3. DSTV\n4. GOTV\n#. Back";
                    header('Location: index.php');
                    exit;
            }
        } else {
            // No input provided - show the utility payment options menu
            $_SESSION['display'] = "Utility Payment Options:\n1. ECG (Electricity)\n2. Water\n3. DSTV\n4. GOTV\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'select_ecg_meter_type':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'utility_payment_input';
            $_SESSION['display'] = "Utility Payment Options:\n1. ECG (Electricity)\n2. Water\n3. DSTV\n4. GOTV\n#. Back"; header('Location: index.php'); exit;
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['meter_type'] = 'Prepaid';
                    $_SESSION['ussd_state'] = 'enter_meter_number';
                    $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
                case '2':
                    $_SESSION['ussd_data']['meter_type'] = 'PostPaid';
                    $_SESSION['ussd_state'] = 'enter_meter_number';
                    $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;
    case 'enter_meter_number':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_ecg_meter_type';
            $_SESSION['display'] = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
        } else {
            $dummy_meters = [
                ['meter' => 'AB1234567', 'name' => 'John Doe', 'type' => 'Prepaid'],
                ['meter' => 'CD2345678', 'name' => 'Jane Smith', 'type' => 'PostPaid'],
                ['meter' => 'EF3456789', 'name' => 'Alice Johnson', 'type' => 'PostPaid'],
                ['meter' => 'GH4567890', 'name' => 'Bob Brown', 'type' => 'Prepaid'],
                ['meter' => 'IJ5678901', 'name' => 'Charlie Davis', 'type' => 'PostPaid'],
                ['meter' => 'KL6789012', 'name' => 'Emily Clark', 'type' => 'Prepaid'],
                ['meter' => 'MN7890123', 'name' => 'David Wilson', 'type' => 'Prepaid'],
                ['meter' => 'OP8901234', 'name' => 'Sarah Taylor', 'type' => 'Prepaid'],
                ['meter' => 'QR9012345', 'name' => 'Michael Lee', 'type' => 'PostPaid'],
                ['meter' => 'ST0123456', 'name' => 'Jessica white', 'type' => 'PostPaid'],
            ];
            $found = null;
            foreach ($dummy_meters as $meter) {
                if ($meter['meter'] === $input && strtolower($meter['type']) === strtolower($_SESSION['ussd_data']['meter_type'])) {
                    $found = $meter;
                    break;
                }
            }
            if ($found) {
                $_SESSION['ussd_data']['meter_number'] = $found['meter'];
                $_SESSION['ussd_data']['meter_name'] = $found['name'];
                $_SESSION['ussd_state'] = 'enter_ecg_meter_amount';
                $_SESSION['display'] = "Enter amount to top up:\n#. Back"; header('Location: index.php'); exit;
            } else {
                $_SESSION['display'] = "Invalid meter number or meter type. Please enter a valid meter number:\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;
    case 'enter_ecg_meter_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_meter_number';
            $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
        } else if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['meter_amount'] = $input;
            $_SESSION['ussd_state'] = 'confirm_meter_topup';
            $_SESSION['display'] = "Confirm ECG Meter Top-up:\nMeter: {$_SESSION['ussd_data']['meter_number']}\nName: {$_SESSION['ussd_data']['meter_name']}\nType: {$_SESSION['ussd_data']['meter_type']}\nAmount: GHS " . number_format($input, 2) . "\n\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
        }
        break;
    case 'confirm_meter_topup':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_ecg_meter_amount';
            $_SESSION['display'] = "Enter amount to top up:\n#. Back"; header('Location: index.php'); exit;
        } else if ($input == '1') {
            // Record the utility payment transaction
            $senderId = $_SESSION['user_id'];
            $meterNumber = $_SESSION['ussd_data']['meter_number'];
            $amount = $_SESSION['ussd_data']['meter_amount'];
            $meterName = $_SESSION['ussd_data']['meter_name'];
            $meterType = $_SESSION['ussd_data']['meter_type'];
            
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                ->execute([$senderId, $meterNumber, $amount, 'ECG Meter Top-up']);
            
            $_SESSION['ussd_state'] = 'utility_payment_success';
            $_SESSION['display'] = "ECG meter top-up initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['pin_attempts']++;
            if ($_SESSION['pin_attempts'] >= 3) {
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated.\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;
    case 'enter_utility_account':
        if ($input == '#') {
            if (isset($_SESSION['ussd_data']['utility_type']) && $_SESSION['ussd_data']['utility_type'] == 'ECG') {
                $_SESSION['ussd_state'] = 'select_ecg_meter_type';
                $_SESSION['display'] = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
            } else {
                $_SESSION['ussd_state'] = 'utility_payment_input';
                $_SESSION['display'] = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n#. Back"; header('Location: index.php'); exit;
            }
        } else if (preg_match('/^[A-Za-z0-9]{8,15}$/', $input)) {
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $recipientInfo = api_validateUtilityRecipient($input, $utilityType);
            if ($recipientInfo['success']) {
                $_SESSION['ussd_data']['utility_account'] = $input;
                $_SESSION['ussd_data']['utility_recipient_name'] = $recipientInfo['name'];
                $_SESSION['ussd_data']['utility_recipient_phone'] = $recipientInfo['phone'] ?? '';
                $_SESSION['ussd_state'] = 'enter_utility_amount';
                $_SESSION['display'] = "Enter amount to pay for $utilityType:\n#. Back"; header('Location: index.php'); exit;
            } else {
                $_SESSION['display'] = "Invalid account number. Please enter a valid $utilityType account number:\n#. Back"; header('Location: index.php'); exit;
            }
        } else {
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Invalid account number. Please enter a valid $utilityType account number (8-15 characters):\n#. Back"; header('Location: index.php'); exit;
        }
        break;
    case 'enter_utility_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_utility_account';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Enter your $utilityType account number:\n#. Back"; header('Location: index.php'); exit;
        } else if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['utility_amount'] = $input;
            $_SESSION['ussd_state'] = 'confirm_utility_payment';
            $name = $_SESSION['ussd_data']['utility_recipient_name'];
            $phone = $_SESSION['ussd_data']['utility_recipient_phone'];
            $account = $_SESSION['ussd_data']['utility_account'];
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Confirm $utilityType payment:\nName: $name\nAccount: $account" . ($phone ? "\nPhone: $phone" : "") . "\nAmount: GHS $input\n1. Confirm\n0. Cancel";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
        }
        break;
    case 'confirm_utility_payment':
        if ($input == '1') {
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $account = $_SESSION['ussd_data']['utility_account'];
            $amount = $_SESSION['ussd_data']['utility_amount'];
            $recipientName = $_SESSION['ussd_data']['utility_recipient_name'] ?? $account;
            $_SESSION['ussd_state'] = 'utility_payment_initiated';
            $_SESSION['display'] = "Transaction initiated!\n     A PIN confirmation request has been sent to your phone.\n     Please check your notifications to complete the transfer of GHS " . number_format($amount, 2) . " to $recipientName.\n     1. Back to main menu";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['ussd_state'] = 'utility_payment_input';
            $_SESSION['display'] = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n3. DSTV\n4. GOTV\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'utility_payment_initiated':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $menuManager = $GLOBALS['menuManager'];
            $userId = $GLOBALS['userId'];
            $userBalance = $GLOBALS['userBalance'];
            $menuItems = $menuManager->getMainMenu($userId, $userBalance);
            $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "1. Back to main menu";
            header('Location: index.php');
            exit;
        }
        break;
    case 'enter_pin_for_utility':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_utility_amount';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Enter amount to pay for $utilityType:\n#. Back";
            header('Location: index.php');
            exit;
        } else if ($input == $correctPin) {
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $account = $_SESSION['ussd_data']['utility_account'];
            $amount = $_SESSION['ussd_data']['utility_amount'];
            $name = $_SESSION['ussd_data']['utility_recipient_name'] ?? $account;
            $meterType = $_SESSION['ussd_data']['meter_type'] ?? '';
            $_SESSION['ussd_state'] = 'utility_payment_success';
            $successMessage = "Meter top-up successful!\nName: $name\nMeter: $account";
            if ($meterType) $successMessage .= "\nType: $meterType";
            $successMessage .= "\nAmount: GHS " . number_format($amount, 2) . "\n\n1. Back to main menu";
            $_SESSION['display'] = $successMessage;
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid PIN. Please try again:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'utility_payment_success':
        // Clear session and end USSD session
        $_SESSION = array();
        session_destroy();
        session_start();
        $_SESSION['ussd_state'] = 'session_ended';
        $_SESSION['display'] = "Utility payment initiated! A PIN confirmation request has been sent to your phone.";
        header('Location: index.php');
        exit;
        break;
    // Add DSTV, GOTV, and other utility sub-flows as needed
    case 'enter_gotv_iuc':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'utility_payment_input';
            $_SESSION['display'] = "Utility Payment Options:\n1. ECG (Electricity)\n2. Water\n3. DSTV\n4. GOTV\n#. Back";
            header('Location: index.php');
            exit;
        } elseif (!empty($input)) {
            // Validate IUC number (basic validation)
            if (is_numeric($input) && strlen($input) >= 8) {
                $_SESSION['ussd_data']['gotv_iuc'] = $input;
                $_SESSION['ussd_state'] = 'enter_gotv_amount';
                $_SESSION['display'] = "Enter amount to pay for GOTV (GHS):\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Invalid IUC number. Please enter a valid IUC number:\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            // No input provided - show IUC input prompt
            $_SESSION['display'] = "Enter the IUC number:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
        
    case 'enter_gotv_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_gotv_iuc';
            $_SESSION['display'] = "Enter the IUC number:\n#. Back";
            header('Location: index.php');
            exit;
        } elseif (!empty($input)) {
            // Validate amount
            if (is_numeric($input) && $input > 0) {
                $_SESSION['ussd_data']['amount'] = $input;
                $_SESSION['ussd_state'] = 'confirm_gotv_payment';
                $_SESSION['display'] = "Confirm GOTV Payment:\nIUC: {$_SESSION['ussd_data']['gotv_iuc']}\nAmount: GHS " . number_format($input, 2) . "\n\n1. Confirm\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Invalid amount. Please enter a valid amount (GHS):\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            // No input provided - show amount input prompt
            $_SESSION['display'] = "Enter amount to pay for GOTV (GHS):\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
        
    case 'confirm_gotv_payment':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_gotv_amount';
            $_SESSION['display'] = "Enter amount to pay for GOTV (GHS):\n#. Back";
            header('Location: index.php');
            exit;
        } elseif ($input == '1') {
            // Process GOTV payment
            $_SESSION['ussd_state'] = 'utility_payment_success';
            $_SESSION['display'] = "GOTV payment initiated successfully!\nAmount: GHS " . number_format($_SESSION['ussd_data']['amount'], 2) . "\nIUC: {$_SESSION['ussd_data']['gotv_iuc']}\n\n1. Back to main menu";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
        
    case 'enter_dstv_smartcard':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'utility_payment_input';
            $_SESSION['display'] = "Utility Payment Options:\n1. ECG (Electricity)\n2. Water\n3. DSTV\n4. GOTV\n#. Back";
            header('Location: index.php');
            exit;
        } elseif (!empty($input)) {
            // Validate smart card number (basic validation)
            if (is_numeric($input) && strlen($input) >= 8) {
                $_SESSION['ussd_data']['dstv_smartcard'] = $input;
                $_SESSION['ussd_state'] = 'enter_dstv_amount';
                $_SESSION['display'] = "Enter amount to pay for DSTV (GHS):\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Invalid smart card number. Please enter a valid smart card number:\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            // No input provided - show smart card input prompt
            $_SESSION['display'] = "Enter the smart card number:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
        
    case 'enter_dstv_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_dstv_smartcard';
            $_SESSION['display'] = "Enter the smart card number:\n#. Back";
            header('Location: index.php');
            exit;
        } elseif (!empty($input)) {
            // Validate amount
            if (is_numeric($input) && $input > 0) {
                $_SESSION['ussd_data']['amount'] = $input;
                $_SESSION['ussd_state'] = 'confirm_dstv_payment';
                $_SESSION['display'] = "Confirm DSTV Payment:\nSmart Card: {$_SESSION['ussd_data']['dstv_smartcard']}\nAmount: GHS " . number_format($input, 2) . "\n\n1. Confirm\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Invalid amount. Please enter a valid amount (GHS):\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            // No input provided - show amount input prompt
            $_SESSION['display'] = "Enter amount to pay for DSTV (GHS):\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
        
    case 'confirm_dstv_payment':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_dstv_amount';
            $_SESSION['display'] = "Enter amount to pay for DSTV (GHS):\n#. Back";
            header('Location: index.php');
            exit;
        } elseif ($input == '1') {
            // Record the DSTV payment transaction
            $senderId = $_SESSION['user_id'];
            $smartcard = $_SESSION['ussd_data']['dstv_smartcard'];
            $amount = $_SESSION['ussd_data']['amount'];
            
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                ->execute([$senderId, $smartcard, $amount, 'DSTV Payment']);
            
            $_SESSION['ussd_state'] = 'utility_payment_success';
            $_SESSION['display'] = "DSTV payment initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
        
    default:
        // Not a Utility Payment state, do nothing
        break;
}
return;
