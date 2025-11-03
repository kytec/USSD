<?php
// Send Money flow handler
switch ($_SESSION['ussd_state']) {
    case 'send_money':
        // Initial state - show network selection
        $_SESSION['ussd_state'] = 'select_network';
        $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
        header('Location: index.php');
        exit;
        
    case 'select_network':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            // Use database menu instead of hardcoded menu
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
                    $_SESSION['ussd_data']['network'] = 'MTN';
                    break;
                case '2':
                    $_SESSION['ussd_data']['network'] = 'Telecel';
                    break;
                case '3':
                    $_SESSION['ussd_data']['network'] = 'AirtelTigo';
                    break;
                case '4':
                    $_SESSION['ussd_state'] = 'select_bank';
                    $_SESSION['display'] = "Select Bank:\n1. GCB\n2. Ecobank\n3. GTBank\n4. Prudential Bank\n5. UBA\n#. Back";
                    header('Location: index.php');
                    exit;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
                    header('Location: index.php');
                    exit;
            }
            if (!empty($_SESSION['ussd_data']['network'])) {
                $_SESSION['ussd_state'] = 'enter_recipient';
                $_SESSION['display'] = "Enter {$_SESSION['ussd_data']['network']} number:\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            // No input provided - show the network selection menu
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'select_bank':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $banks = [
                '1' => 'GCB',
                '2' => 'Ecobank',
                '3' => 'GT Bank',
                '4' => 'Prudential',
                '5' => 'UBA'
            ];
            if (isset($banks[$input])) {
                $_SESSION['ussd_data']['bank_name'] = $banks[$input];
                $_SESSION['ussd_state'] = 'enter_bank_account_number';
                $_SESSION['display'] = "Enter Account Number:\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Invalid option. Please select:\n1. GCB\n2. Ecobank\n3. GTBank\n4. Prudential Bank\n5. UBA\n#. Back";
                header('Location: index.php');
                exit;
            }
        }
        break;
    case 'enter_bank_account_number':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_bank';
            $_SESSION['display'] = "Select Bank:\n1. GCB\n2. Ecobank\n3. GTBank\n4. Prudential Bank\n5. UBA\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $dummy_accounts = [
                ['name' => 'Kwaku Frimpong', 'account' => '11005674893412', 'bank' => 'GCB'],
                ['name' => 'Adwoa Mensah', 'account' => '23116783452611', 'bank' => 'GCB'],
                ['name' => 'Kofi Agyekum', 'account' => '11230007658713', 'bank' => 'GT Bank'],
                ['name' => 'Chris Frank', 'account' => '140071225014', 'bank' => 'GT Bank'],
                ['name' => 'Eunice Dede', 'account' => '2435680003452', 'bank' => 'Prudential'],
                ['name' => 'Ella Dzifa', 'account' => '14557869023', 'bank' => 'Prudential'],
                ['name' => 'Christabel Acquah', 'account' => '32467589223', 'bank' => 'Ecobank'],
                ['name' => 'John Ofori', 'account' => '22456178920', 'bank' => 'Ecobank'],
                ['name' => 'Gideon Yartey', 'account' => '770113425672', 'bank' => 'UBA'],
                ['name' => 'Erica Martins', 'account' => '223145678890', 'bank' => 'UBA'],
            ];
            $found = null;
            foreach ($dummy_accounts as $acc) {
                if ($acc['account'] === $input && $acc['bank'] === $_SESSION['ussd_data']['bank_name']) {
                    $found = $acc;
                    break;
                }
            }
            if ($found) {
                $_SESSION['ussd_data']['bank_account'] = $found['account'];
                $_SESSION['ussd_data']['bank_account_name'] = $found['name'];
                $_SESSION['ussd_state'] = 'confirm_bank_recipient';
                $_SESSION['display'] = "Send to: {$found['name']}\nAccount: {$found['account']}\nBank: {$found['bank']}\n1. Confirm\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Account not found for selected bank. Please enter a valid account number:\n#. Back";
                header('Location: index.php');
                exit;
            }
        }
        break;
    case 'confirm_bank_recipient':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_bank_account_number';
            $_SESSION['display'] = "Enter Account Number:\n#. Back";
            header('Location: index.php');
            exit;
        } else if ($input == '1') {
            $_SESSION['ussd_state'] = 'enter_bank_amount_alt';
            $_SESSION['display'] = "Enter amount to send to bank account:\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'enter_bank_amount_alt':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'confirm_bank_recipient';
            $_SESSION['display'] = "Send to: {$_SESSION['ussd_data']['bank_account_name']}\nAccount: {$_SESSION['ussd_data']['bank_account']}\nBank: {$_SESSION['ussd_data']['bank_name']}\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        } else if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['bank_amount'] = $input;
            // Record the bank transfer transaction
            $senderId = $_SESSION['user_id'];
            $bankAccount = $_SESSION['ussd_data']['bank_account'];
            $amount = $input;
            $recipientName = $_SESSION['ussd_data']['bank_account_name'];
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()))")
                ->execute([$senderId, $bankAccount, $amount]);
            $_SESSION['ussd_state'] = 'transaction_success';
            $_SESSION['display'] = "Bank transfer initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;

        
    case 'enter_recipient':
        if (empty($_SESSION['ussd_data']['network'])) {
            unset($_SESSION['ussd_data']['recipient']);
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Please select a network first:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
            header('Location: index.php');
            exit;
        }
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $normalizedInput = preg_replace('/[\s-]/', '', $input);
            if (preg_match('/^233([0-9]{9})$/', $normalizedInput, $matches)) {
                $normalizedInput = '0' . $matches[1];
            }
            if (!preg_match('/^0[0-9]{9}$/', $normalizedInput)) {
                $_SESSION['display'] = "Invalid number format. Please enter a valid 10-digit number starting with 0:\n#. Back";
                header('Location: index.php');
                exit;
            }
            $network = $_SESSION['ussd_data']['network'];
            $valid = false;
            if ($network == 'MTN' && preg_match('/^(054|053|024|059|025|055)[0-9]{7}$/', $normalizedInput)) {
                $valid = true;
            } else if ($network == 'Telecel' && preg_match('/^(020|050)[0-9]{7}$/', $normalizedInput)) {
                $valid = true;
            } else if ($network == 'AirtelTigo' && preg_match('/^(026|056|027|057)[0-9]{7}$/', $normalizedInput)) {
                $valid = true;
            }
            if ($valid) {
                $_SESSION['ussd_data']['recipient'] = $normalizedInput;
                $_SESSION['ussd_state'] = 'enter_amount';
                $_SESSION['display'] = "Enter amount to send to {$normalizedInput}:\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Invalid number for $network. Please enter a valid number for $network:\n#. Back";
                header('Location: index.php');
                exit;
            }
        }
        break;
    case 'enter_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_recipient';
            $_SESSION['display'] = "Enter {$_SESSION['ussd_data']['network']} number:\n#. Back";
            header('Location: index.php');
            exit;
        } else if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['amount'] = $input;
            $demoRecipients = [
                '0241234567' => ['name' => 'John Doe', 'network' => 'MTN'],
                '0551234567' => ['name' => 'Jane Smith', 'network' => 'MTN'],
                '0201234567' => ['name' => 'Kwame Mensah', 'network' => 'Telecel'],
                '0501234567' => ['name' => 'Ama Serwaa', 'network' => 'Telecel'],
                '0261234567' => ['name' => 'Kofi Annan', 'network' => 'AirtelTigo'],
                '0571234567' => ['name' => 'Abena Poku', 'network' => 'AirtelTigo'],
            ];
            $recipient = $_SESSION['ussd_data']['recipient'];
            $network = $_SESSION['ussd_data']['network'];
            if (isset($demoRecipients[$recipient]) && $demoRecipients[$recipient]['network'] === $network) {
                $_SESSION['ussd_data']['recipient_name'] = $demoRecipients[$recipient]['name'];
                $_SESSION['ussd_state'] = 'confirm_recipient';
                $_SESSION['display'] = "Send to: {$demoRecipients[$recipient]['name']}\nNumber: {$recipient}\nAmount: GHS " . number_format($input, 2) . "\n1. Confirm\n0. Cancel";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Unable to verify recipient. Please check the number and try again.\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'confirm_recipient':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'enter_reference';
            $_SESSION['display'] = "Enter transaction reference:\n#. Back";
            header('Location: index.php');
            exit;
        } else if ($input == '0' || $input == '#') {
            $_SESSION['ussd_state'] = 'enter_amount';
            $_SESSION['display'] = "Enter amount to send to {$_SESSION['ussd_data']['recipient']}:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'enter_reference':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'confirm_recipient';
            $recipientName = $_SESSION['ussd_data']['recipient_name'] ?? $_SESSION['ussd_data']['recipient'];
            $_SESSION['display'] = "Send to: $recipientName\nNumber: {$_SESSION['ussd_data']['recipient']}\nAmount: GHS " . number_format($_SESSION['ussd_data']['amount'], 2) . "\n1. Confirm\n0. Cancel";
            header('Location: index.php');
            exit;
        } else if (!empty($input)) {
            // Store the reference and create the transaction
            $_SESSION['ussd_data']['reference'] = $input;
            $senderId = $_SESSION['user_id'];
            $recipient = $_SESSION['ussd_data']['recipient'];
            $amount = $_SESSION['ussd_data']['amount'];
            $reference = $input;
            $recipientName = $_SESSION['ussd_data']['recipient_name'] ?? $recipient;
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                ->execute([$senderId, $recipient, $amount, $reference]);
            $_SESSION['ussd_state'] = 'transaction_success';
            $_SESSION['display'] = "Transaction initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Please enter a reference:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'confirm_transfer':
        // This state is for legacy/compatibility; you may want to redirect to transaction_success or handle as needed
        $_SESSION['ussd_state'] = 'transaction_success';
        $_SESSION['display'] = "Processing your transfer. You will receive a prompt shortly.";
        header('Location: index.php');
        exit;
        break;
    case 'transaction_success':
        // End USSD session immediately - no further options
        // Clear all session data to allow fresh start
        $_SESSION = array();
        session_destroy();
        session_start();
        
        // Set final message without any options
        $_SESSION['ussd_state'] = 'session_ended';
        $_SESSION['display'] = "Transaction initiated! A PIN confirmation request has been sent to your phone.";
        
        header('Location: index.php');
        exit;
        break;
    default:
        // Not a Send Money state, do nothing
        break;
}
return;
