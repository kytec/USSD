<?php
// Include investment API helper functions
$apiFile = __DIR__ . '/investment_api.php';
if (file_exists($apiFile)) {
    require_once $apiFile;
} else {
    die("Investment API file not found: " . $apiFile);
}

// Investment flow handler
switch ($_SESSION['ussd_state']) {
    case 'investment':
        // Initial state - ask for investment account number
        $_SESSION['ussd_data']['investment_step'] = 1;
        $_SESSION['ussd_state'] = 'enter_investment_account';
        $_SESSION['display'] = "Enter your Investment Account Number:\n#. Back";
        header('Location: index.php');
        exit;
        
    case 'enter_investment_account':
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
            // Validate investment account
            $validation = validateInvestmentAccount($input);
            if ($validation['exists']) {
                $_SESSION['ussd_data']['investment_account'] = $input;
                $_SESSION['ussd_state'] = 'investment_input';
                $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back";
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['display'] = "Account not found. Please enter a valid account number:\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            // No input provided - show account input prompt
            $_SESSION['display'] = "Enter your Investment Account Number:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
        
    case 'investment_input':
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
                    $_SESSION['ussd_state'] = 'fixed_deposit';
                    $_SESSION['display'] = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
                    header('Location: index.php');
                    exit;
                case '2':
                    $_SESSION['ussd_state'] = 'treasury_bills';
                    $_SESSION['display'] = "Enter amount to invest in Treasury Bills:\n#. Back";
                    header('Location: index.php');
                    exit;
                case '3':
                    $_SESSION['ussd_state'] = 'mutual_funds';
                    $_SESSION['display'] = "Enter amount to invest in Mutual Funds:\n#. Back";
                    header('Location: index.php');
                    exit;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:";
                    header('Location: index.php');
                    exit;
            }
        } else {
            // No input provided - show the investment options menu
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'fixed_deposit':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back";
            header('Location: index.php');
            exit;
        } else if (in_array($input, ['1', '2', '3'])) {
            $durations = ['1' => 3, '2' => 6, '3' => 12];
            $interest_rates = ['1' => 5.00, '2' => 7.00, '3' => 10.00];
            $_SESSION['ussd_data']['fd_duration'] = $durations[$input];
            $_SESSION['ussd_data']['fd_interest_rate'] = $interest_rates[$input];
            $_SESSION['ussd_state'] = 'enter_fixed_deposit_amount';
            $response = "Enter amount for Fixed Deposit (Duration: " . $durations[$input] . " Months, Interest: " . $interest_rates[$input] . "% p.a.):\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
            header('Location: index.php');
            exit;
        }
        break;
    case 'enter_fixed_deposit_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'fixed_deposit';
            $_SESSION['display'] = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
            header('Location: index.php');
            exit;
        } else if (is_numeric($input) && $input > 0) {
            $duration = $_SESSION['ussd_data']['fd_duration'];
            $rate = $_SESSION['ussd_data']['fd_interest_rate'];
            $amount = $input;
            $_SESSION['ussd_data']['fd_amount'] = $amount;
            $_SESSION['ussd_state'] = 'confirm_fixed_deposit';
            $_SESSION['display'] = "Confirm Fixed Deposit:\nDuration: {$duration} Months\nInterest: {$rate}% p.a.\nAmount: GHS " . number_format($amount, 2) . "\n1. Confirm\n0. Cancel";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'confirm_fixed_deposit':
        if ($input == '1') {
            $amount = $_SESSION['ussd_data']['fd_amount'];
            $duration = $_SESSION['ussd_data']['fd_duration'];
            $rate = $_SESSION['ussd_data']['fd_interest_rate'];
            // Record the fixed deposit investment
            $senderId = $_SESSION['user_id'];
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                ->execute([$senderId, $_SESSION['ussd_data']['investment_account'], $amount, 'Fixed Deposit Investment - Account: ' . $_SESSION['ussd_data']['investment_account']]);
            
            $_SESSION['ussd_state'] = 'investment_success';
            $_SESSION['display'] = "Fixed Deposit investment initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['ussd_state'] = 'fixed_deposit';
            $_SESSION['display'] = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
            header('Location: index.php');
            exit;
        }
        break;
    case 'treasury_bills':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back";
            header('Location: index.php');
            exit;
        } else if (is_numeric($input) && $input > 0) {
            $amount = $input;
            $_SESSION['ussd_data']['tb_amount'] = $amount;
            $_SESSION['ussd_state'] = 'confirm_treasury_bills';
            $_SESSION['display'] = "Confirm Treasury Bills Investment:\nAmount: GHS " . number_format($amount, 2) . "\n1. Confirm\n0. Cancel";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'confirm_treasury_bills':
        if ($input == '1') {
            $amount = $_SESSION['ussd_data']['tb_amount'];
            // Record the treasury bills investment
            $senderId = $_SESSION['user_id'];
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                ->execute([$senderId, $_SESSION['ussd_data']['investment_account'], $amount, 'Treasury Bills Investment - Account: ' . $_SESSION['ussd_data']['investment_account']]);
            
            $_SESSION['ussd_state'] = 'investment_success';
            $_SESSION['display'] = "Treasury Bills investment initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['ussd_state'] = 'treasury_bills';
            $_SESSION['display'] = "Enter amount to invest in Treasury Bills:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'mutual_funds':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back";
            header('Location: index.php');
            exit;
        } else if (is_numeric($input) && $input > 0) {
            $amount = $input;
            $_SESSION['ussd_data']['mf_amount'] = $amount;
            $_SESSION['ussd_state'] = 'confirm_mutual_funds';
            $_SESSION['display'] = "Confirm Mutual Funds Investment:\nAmount: GHS " . number_format($amount, 2) . "\n1. Confirm\n0. Cancel";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'confirm_mutual_funds':
        if ($input == '1') {
            $amount = $_SESSION['ussd_data']['mf_amount'];
            // Record the mutual funds investment
            $senderId = $_SESSION['user_id'];
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                ->execute([$senderId, $_SESSION['ussd_data']['investment_account'], $amount, 'Mutual Funds Investment - Account: ' . $_SESSION['ussd_data']['investment_account']]);
            
            $_SESSION['ussd_state'] = 'investment_success';
            $_SESSION['display'] = "Mutual Funds investment initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['ussd_state'] = 'mutual_funds';
            $_SESSION['display'] = "Enter amount to invest in Mutual Funds:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'enter_mutual_fund_name':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'mutual_funds';
            $_SESSION['display'] = "Enter amount to invest in Mutual Funds:\n#. Back";
            header('Location: index.php');
            exit;
        } else if (!empty($input)) {
            $_SESSION['ussd_data']['mf_name'] = $input;
            
            // Record the mutual funds investment directly
            $senderId = $_SESSION['user_id'];
            $amount = $_SESSION['ussd_data']['mf_amount'];
            
            $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                ->execute([$senderId, $_SESSION['ussd_data']['investment_account'], $amount, 'Mutual Funds Investment - Account: ' . $_SESSION['ussd_data']['investment_account']]);
            
            $_SESSION['ussd_state'] = 'investment_success';
            $_SESSION['display'] = "Mutual Funds investment initiated! A PIN confirmation request has been sent to your phone.";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Mutual Fund name cannot be empty. Please enter a name:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
    case 'investment_success':
        // Clear session and end USSD session
        $_SESSION = array();
        session_destroy();
        session_start();
        $_SESSION['ussd_state'] = 'session_ended';
        $_SESSION['display'] = "Investment initiated! A PIN confirmation request has been sent to your phone.";
        header('Location: index.php');
        exit;
        break;
    default:
        // Not an Investment state, do nothing
        break;
}
return;
