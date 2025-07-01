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
    case 'buy_airtime_data':
        if ($input == '#') {
            // Handle back navigation based on current step
            if (!isset($_SESSION['ussd_data']['service_step']) || $_SESSION['ussd_data']['service_step'] == 1) {
                $_SESSION['ussd_state'] = 'start';
                $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
            } else if ($_SESSION['ussd_data']['service_step'] == 2) {
                $_SESSION['ussd_data']['service_step'] = 1;
                $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
            } else if ($_SESSION['ussd_data']['service_step'] == 3) {
                $_SESSION['ussd_data']['service_step'] = 2;
                $serviceType = $_SESSION['ussd_data']['service_type'];
                $_SESSION['display'] = "Buy " . ucfirst($serviceType) . ":\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
            } else {
                // Go back to previous steps based on context
                if (isset($_SESSION['ussd_data']['for_self']) && $_SESSION['ussd_data']['for_self']) {
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $serviceType = $_SESSION['ussd_data']['service_type'];
                    $_SESSION['display'] = "Buy " . ucfirst($serviceType) . ":\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else {
                    $_SESSION['ussd_data']['service_step'] = 3;
                    $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                }
            }
        } else {
            if (!isset($_SESSION['ussd_data']['service_step']) || $input == '') {
                $_SESSION['ussd_data']['service_step'] = 1;
                $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
            } else if ($_SESSION['ussd_data']['service_step'] == 1) {
                // Choose service type
                if ($input == '1') {
                    $_SESSION['ussd_data']['service_type'] = 'airtime';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $_SESSION['display'] = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['service_type'] = 'data';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $_SESSION['display'] = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                } else {
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Buy Airtime\n2. Buy Data\n#. Back";
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
                        $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back";
                    } else {
                        $_SESSION['ussd_data']['service_step'] = 5; // Skip network and phone selection
                        $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                    }
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['for_self'] = false;
                    $_SESSION['ussd_data']['service_step'] = 3;
                    $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
                } else {
                    $serviceType = $_SESSION['ussd_data']['service_type'];
                    $_SESSION['display'] = "Invalid option. Please select:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back";
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 3) {
                // Choose network for other
                if ($input == '1') {
                    $_SESSION['ussd_data']['network'] = 'Telecel';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $_SESSION['display'] = "Enter recipient Telecel mobile number:\n#. Back";
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['network'] = 'AirtelTigo';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $_SESSION['display'] = "Enter recipient AirtelTigo mobile number:\n#. Back";
                } else if ($input == '3') {
                    $_SESSION['ussd_data']['network'] = 'MTN';
                    $_SESSION['ussd_data']['service_step'] = 4;
                    $_SESSION['display'] = "Enter recipient MTN mobile number:\n#. Back";
                } else {
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back";
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
                        $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back";
                    } else {
                        $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                    }
                } else {
                    $_SESSION['display'] = "Invalid number for $network. Please enter a valid number for $network:\n#. Back";
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 5) {
                if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                    // Enter amount for airtime
                    if (is_numeric($input) && $input > 0) {
                        $_SESSION['ussd_data']['amount'] = $input;
                        $_SESSION['ussd_data']['service_step'] = 6;
                        $_SESSION['display'] = "Confirm Airtime Purchase:\nBuy GHS " . number_format($input, 2) . " airtime for " . $_SESSION['ussd_data']['phone_number'] . ".\nEnter your PIN to confirm:\n#. Back";
                    } else {
                        $_SESSION['display'] = "Invalid amount. Please enter a valid amount (GHS):\n#. Back";
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
                        $_SESSION['display'] = "Confirm Data Purchase:\nBuy " . $bundles[$input]['size'] . " data bundle for GHS " . $bundles[$input]['price'] . " for " . $_SESSION['ussd_data']['phone_number'] . ".\nEnter your PIN to confirm:\n#. Back";
                    } else {
                        $_SESSION['display'] = "Invalid option. Please select:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back";
                    }
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 6) {
                // PIN confirmation
                $enteredPin = $input;
                $stmt = $pdo->prepare("SELECT pin, balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $amount = $_SESSION['ussd_data']['amount'];
                
                if (!$user || $enteredPin !== $correctPin) {
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $serviceName = $_SESSION['ussd_data']['service_type'];
                    $_SESSION['display'] = "Invalid PIN. " . ucfirst($serviceName) . " purchase cancelled.\n\n1. Back to main menu";
                } elseif ($user['balance'] < $amount) {
                    $_SESSION['ussd_state'] = 'transaction_success';
                    $_SESSION['display'] = "Insufficient funds. Your balance is GHS " . number_format($user['balance'], 2) . ".\n\n1. Back to main menu";
                } else {
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
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $_SESSION['ussd_state'] = 'transaction_success';
                        $serviceName = $_SESSION['ussd_data']['service_type'];
                        $_SESSION['display'] = ucfirst($serviceName) . " purchase failed: " . $e->getMessage() . "\n\n1. Back to main menu";
                    }
                }
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