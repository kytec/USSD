<?php
// Buy Airtime/Data flow handler
switch ($_SESSION['ussd_state']) {
    case 'buy_airtime_data':
        // Initial state - show service selection and transition to input state
        $_SESSION['ussd_data']['service_step'] = 1;
        $_SESSION['ussd_state'] = 'buy_airtime_data_input';
        $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
        header('Location: index.php');
        exit;
        
    case 'buy_airtime_data_input':
        if ($input == '#') {
            if (!isset($_SESSION['ussd_data']['service_step']) || $_SESSION['ussd_data']['service_step'] == 1) {
                $_SESSION['ussd_state'] = 'start';
                // Use database menu instead of hardcoded menu
                $menuManager = $GLOBALS['menuManager'];
                $userId = $GLOBALS['userId'];
                $userBalance = $GLOBALS['userBalance'];
                $menuItems = $menuManager->getMainMenu($userId, $userBalance);
                $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
                header('Location: index.php');
                exit;
            } else if ($_SESSION['ussd_data']['service_step'] == 2) {
                $_SESSION['ussd_data']['service_step'] = 1;
                $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
                header('Location: index.php');
                exit;
            } else if ($_SESSION['ussd_data']['service_step'] == 3) {
                $_SESSION['ussd_data']['service_step'] = 2;
                $serviceType = $_SESSION['ussd_data']['service_type'];
                $response = "Buy " . ucfirst($serviceType) . ":\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
            } else {
                if (isset($_SESSION['ussd_data']['for_self']) && $_SESSION['ussd_data']['for_self']) {
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $serviceType = $_SESSION['ussd_data']['service_type'];
                    $response = "Buy " . ucfirst($serviceType) . ":\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else {
                    $_SESSION['ussd_data']['service_step'] = 3;
                    $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                header('Location: index.php');
                exit;
                }
            }
        } else {
            if ($input == '') {
                $_SESSION['ussd_data']['service_step'] = 1;
                $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
                header('Location: index.php');
                exit;
            } else if ($_SESSION['ussd_data']['service_step'] == 1) {
                if ($input == '1') {
                    $_SESSION['ussd_data']['service_type'] = 'airtime';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $_SESSION['display'] = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                    header('Location: index.php');
                    exit;
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['service_type'] = 'data';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $_SESSION['display'] = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                    header('Location: index.php');
                    exit;
                } else {
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Buy Airtime\n2. Buy Data\n#. Back";
                    header('Location: index.php');
                    exit;
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 2) {
                if ($input == '1') {
                    $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $_SESSION['ussd_data']['phone_number'] = $user ? $user['phone'] : '';
                    $_SESSION['ussd_data']['for_self'] = true;
                    if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                        $_SESSION['ussd_data']['service_step'] = 5;
                        $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
                    } else {
                        $_SESSION['ussd_data']['service_step'] = 5;
                        $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back"; header('Location: index.php'); exit;
                    }
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['for_self'] = false;
                    $_SESSION['ussd_data']['service_step'] = 3;
                    $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                header('Location: index.php');
                exit;
                } else {
                    $serviceType = $_SESSION['ussd_data']['service_type'];
                    $_SESSION['display'] = "Invalid option. Please select:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 3) {
                if ($input == '1') {
                    $_SESSION['ussd_data']['network'] = 'Telecel';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $_SESSION['display'] = "Enter recipient Telecel mobile number:\n#. Back"; header('Location: index.php'); exit;
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['network'] = 'AirtelTigo';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $_SESSION['display'] = "Enter recipient AirtelTigo mobile number:\n#. Back"; header('Location: index.php'); exit;
                } else if ($input == '3') {
                    $_SESSION['ussd_data']['network'] = 'MTN';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $_SESSION['display'] = "Enter recipient MTN mobile number:\n#. Back"; header('Location: index.php'); exit;
                } else {
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back"; header('Location: index.php'); exit;
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 4) {
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
                        $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
                    } else {
                        $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back"; header('Location: index.php'); exit;
                    }
                } else {
                    $_SESSION['display'] = "Invalid number for $network. Please enter a valid number for $network:\n#. Back"; header('Location: index.php'); exit;
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 5) {
                if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                    if (is_numeric($input) && $input > 0) {
                        $_SESSION['ussd_data']['amount'] = $input;
                        
                        // Record the airtime purchase transaction
                        $senderId = $_SESSION['user_id'];
                        $phoneNumber = $_SESSION['ussd_data']['phone_number'];
                        $amount = $input;
                        
                        $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                            ->execute([$senderId, $phoneNumber, $amount, 'Airtime Purchase']);
                        
                        $_SESSION['ussd_state'] = 'buy_airtime_data_success';
                        $_SESSION['display'] = "Airtime purchase initiated! A PIN confirmation request has been sent to your phone.";
                        header('Location: index.php');
                        exit;
                    } else {
                        $_SESSION['display'] = "Invalid amount. Please enter a valid amount (GHS):\n#. Back"; header('Location: index.php'); exit;
                    }
                } else {
                    $bundles = [
                        '1' => ['size' => '1GB', 'price' => 5],
                        '2' => ['size' => '2GB', 'price' => 9],
                        '3' => ['size' => '5GB', 'price' => 20],
                        '4' => ['size' => '10GB', 'price' => 35]
                    ];
                    if (isset($bundles[$input])) {
                        $_SESSION['ussd_data']['data_bundle'] = $bundles[$input]['size'];
                        $_SESSION['ussd_data']['amount'] = $bundles[$input]['price'];
                        
                        // Record the data purchase transaction
                        $senderId = $_SESSION['user_id'];
                        $phoneNumber = $_SESSION['ussd_data']['phone_number'];
                        $amount = $bundles[$input]['price'];
                        $bundleSize = $bundles[$input]['size'];
                        
                        $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date, expiry_time, reference) VALUES (?, ?, ?, 'pending', GETDATE(), DATEADD(minute, 1, GETDATE()), ?)")
                            ->execute([$senderId, $phoneNumber, $amount, 'Data Bundle Purchase - ' . $bundleSize]);
                        
                        $_SESSION['ussd_state'] = 'buy_airtime_data_success';
                        $_SESSION['display'] = "Data bundle purchase initiated! A PIN confirmation request has been sent to your phone.";
                        header('Location: index.php');
                        exit;
                    } else {
                        $_SESSION['display'] = "Invalid option. Please select:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back"; header('Location: index.php'); exit;
                    }
                }
            }
        }
        break;
        
    case 'buy_airtime_data_success':
        // Clear session and end USSD session
        $_SESSION = array();
        session_destroy();
        session_start();
        $_SESSION['ussd_state'] = 'session_ended';
        $_SESSION['display'] = "Transaction initiated! A PIN confirmation request has been sent to your phone.";
        header('Location: index.php');
        exit;
        break;
        
    case 'buy_airtime':
        // (If you want to support legacy buy_airtime state, you can copy the logic here as well)
        break;
    default:
        // Not a Buy Airtime/Data state, do nothing
        break;
}
return;
