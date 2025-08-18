<?php
require_once 'db_connect.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
 
// Always include and initialize menu manager and user info
require_once 'menu_manager.php';
$menuManager = new MenuManager($pdo); // Use DB-backed menu manager
$userBalance = 900.00; // Default balance for demo
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
 
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
    
    // Initialize session variables
    $_SESSION['ussd_state'] = 'start';
    $_SESSION['ussd_data'] = [];
    $_SESSION['pin_attempts'] = 0;
    $_SESSION['user_id'] = 1; // This should be set based on actual user authentication
    
    // Set the welcome message using simple menu
    $menuItems = $menuManager->getMainMenu($userId, $userBalance);
    $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
    
    // Redirect to index page
    header('Location: index.php');
    exit();
}
 
$input = isset($_POST['ussd_input']) ? trim($_POST['ussd_input']) : '';
 
$correctPin = '1234';
 
// Only process input if not empty
if ($input === '' && (!isset($_POST['action']) || $_POST['action'] !== 'cancel')) {
    $_SESSION['display'] = "Please enter a choice before pressing Send.";
    header('Location: index.php');
    exit;
}
 
switch ($_SESSION['ussd_state']) {
 
    case 'start':
        $menuItem = $menuManager->getMenuItemByNumberRobust($input, $userId, $userBalance);
        
        if ($menuItem) {
            // Log menu usage
            $menuManager->logMenuUsage($menuItem['id'], $userId, session_id());
            
            // Check if this menu has submenus
            $submenus = $menuManager->getSubmenuNodes($menuItem['action_value'], $userId, $userBalance);
            
            if ($submenus && count($submenus) > 0) {
                // Store submenu data and show submenu
                $_SESSION['current_submenus'] = $submenus;
                $_SESSION['current_main_menu'] = $menuItem;
                $_SESSION['ussd_state'] = 'submenu_selection';
                $_SESSION['display'] = $menuManager->buildSubmenuDisplay($submenus, $menuItem['display_text']);
                    header('Location: index.php');
                    exit;
            } else {
                // No submenus, handle directly based on action_type and action_value
                switch ($menuItem['action_value']) {
                case 'statement':
                    $_SESSION['ussd_state'] = 'view_statement';
                    // The statement logic is already handled in view_statement
                    break;
                default:
                    // Handle external actions or custom functions
                    $_SESSION['display'] = "Processing {$menuItem['display_text']}...\n\n1. Back to main menu";
                    header('Location: index.php');
                    exit;
                }
            }
        } else {
            // Invalid menu selection - show main menu
            $menuItems = $menuManager->getMainMenu($userId, $userBalance);
            $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
            header('Location: index.php');
            exit;
        }
        break;
 
    case 'submenu_selection':
        if ($input == '#') {
            // Back to main menu
            $_SESSION['ussd_state'] = 'start';
            $menuItems = $menuManager->getMainMenu($userId, $userBalance);
            $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
            header('Location: index.php');
            exit;
        } else {
            // Find selected submenu item using helper method
            $selectedSubmenu = $menuManager->getSubmenuItemByNumber($input, $_SESSION['current_submenus']);
            
            if ($selectedSubmenu) {
                // Log submenu usage
                $menuManager->logMenuUsage($selectedSubmenu['id'], $userId, session_id());
                
                // Handle submenu action based on action_type and action_value
                switch ($selectedSubmenu['action_type']) {
                    case 'state':
                        // Navigate to specific USSD state
                        $_SESSION['ussd_state'] = $selectedSubmenu['action_value'];
                        
                        // Use metadata if available for custom display
                        if ($selectedSubmenu['metadata'] && isset($selectedSubmenu['metadata']['next_display'])) {
                            $_SESSION['display'] = $selectedSubmenu['metadata']['next_display'];
                        } else {
                            // Default display based on action_value
                            switch ($selectedSubmenu['action_value']) {
                                case 'enter_recipient':
                                    $network = $selectedSubmenu['metadata']['network'] ?? 'Mobile Money';
                                    $_SESSION['display'] = "Enter {$network} number:\n#. Back";
                                    break;
                                case 'select_bank':
                                    $_SESSION['display'] = "Select Bank:\n1. GCB\n2. Ecobank\n3. GTBank\n4. Prudential Bank\n5. UBA\n#. Back";
                                    break;
                                case 'buy_airtime_data':
                                    $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back";
                                    break;
                                case 'fixed_deposit':
                                    $_SESSION['display'] = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:";
                                    break;
                                case 'treasury_bills':
                                    $_SESSION['display'] = "Enter amount to invest in Treasury Bills:\n#. Back";
                                    break;
                                case 'mutual_funds':
                                    $_SESSION['display'] = "Enter amount to invest in Mutual Funds:\n#. Back";
                                    break;
                                case 'select_ecg_meter_type':
                                    $_SESSION['display'] = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back";
                                    break;
                                case 'enter_utility_account':
                                    $utilityType = $selectedSubmenu['metadata']['utility_type'] ?? 'Utility';
                                    $_SESSION['display'] = "Enter your {$utilityType} account number:\n#. Back";
                                    break;
                                default:
                                    $_SESSION['display'] = "Processing {$selectedSubmenu['display_text']}...\n\n1. Back to main menu";
                                    break;
                            }
                        }
                        
                        // Store submenu data for context
                        $_SESSION['ussd_data']['selected_submenu'] = $selectedSubmenu;
                        $_SESSION['ussd_data']['parent_menu'] = $_SESSION['current_main_menu'];
                        
                        header('Location: index.php');
                        exit;
                        
                    case 'function':
                        // Call specific function
                        $_SESSION['ussd_state'] = 'function_call';
                        $_SESSION['ussd_data']['function_name'] = $selectedSubmenu['action_value'];
                        $_SESSION['ussd_data']['selected_submenu'] = $selectedSubmenu;
                        $_SESSION['display'] = "Processing {$selectedSubmenu['display_text']}...\n\n1. Back to main menu";
                        header('Location: index.php');
                        exit;
                        
                    case 'external':
                        // Handle external actions
                        $_SESSION['ussd_state'] = 'external_action';
                        $_SESSION['ussd_data']['external_url'] = $selectedSubmenu['action_value'];
                        $_SESSION['ussd_data']['selected_submenu'] = $selectedSubmenu;
                        $_SESSION['display'] = "Redirecting to {$selectedSubmenu['display_text']}...\n\n1. Back to main menu";
                        header('Location: index.php');
                        exit;
                        
                    default:
                        $_SESSION['display'] = "Processing {$selectedSubmenu['display_text']}...\n\n1. Back to main menu";
                        header('Location: index.php');
                        exit;
                }
            } else {
                // Invalid submenu selection
                $_SESSION['display'] = "Invalid option. Please select:\n" . $menuManager->buildSubmenuDisplay($_SESSION['current_submenus'], $_SESSION['current_main_menu']['display_text']);
                header('Location: index.php');
                exit;
            }
        }
        break;

    case 'function_call':
        if ($input == '#') {
            // Back to submenu
            $_SESSION['ussd_state'] = 'submenu_selection';
            $_SESSION['display'] = $menuManager->buildSubmenuDisplay($_SESSION['current_submenus'], $_SESSION['current_main_menu']['display_text']);
            header('Location: index.php');
            exit;
        } else {
            // Handle function call based on stored function name
            $functionName = $_SESSION['ussd_data']['function_name'];
            $selectedSubmenu = $_SESSION['ussd_data']['selected_submenu'];
            
            // You can add specific function handling here
            $_SESSION['display'] = "Function '{$functionName}' executed successfully!\n\n1. Back to main menu";
            header('Location: index.php');
            exit;
        }
        break;

    case 'external_action':
        if ($input == '#') {
            // Back to submenu
            $_SESSION['ussd_state'] = 'submenu_selection';
            $_SESSION['display'] = $menuManager->buildSubmenuDisplay($_SESSION['current_submenus'], $_SESSION['current_main_menu']['display_text']);
            header('Location: index.php');
            exit;
        } else {
            // Handle external action
            $externalUrl = $_SESSION['ussd_data']['external_url'];
            $selectedSubmenu = $_SESSION['ussd_data']['selected_submenu'];
            
            // You can add external API calls here
            $_SESSION['display'] = "External action '{$selectedSubmenu['display_text']}' completed!\n\n1. Back to main menu";
            header('Location: index.php');
            exit;
        }
        break;
 
    case 'select_network':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $menuItems = $menuManager->getMainMenu($userId, $userBalance);
            $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
            header('Location: index.php');
            exit;
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['network'] = 'MTN';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $_SESSION['display'] = "Enter MTN MobileMoney number:\n#. Back";
                    header('Location: index.php');
                    exit;
                case '2':
                    $_SESSION['ussd_data']['network'] = 'Telecel';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $_SESSION['display'] = "Enter Telecel Cash number:\n#. Back";
                    header('Location: index.php');
                    exit;
                case '3':
                    $_SESSION['ussd_data']['network'] = 'AirtelTigo';
                    $_SESSION['ussd_state'] = 'enter_recipient';
                    $_SESSION['display'] = "Enter AirtelTigo Cash number:\n#. Back";
                    header('Location: index.php');
                    exit;
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
            // Dummy data
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
            $_SESSION['ussd_state'] = 'enter_bank_pin';
            $_SESSION['display'] = "Enter your PIN to confirm transfer:\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;

    case 'enter_bank_pin':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_bank_amount_alt';
            $_SESSION['display'] = "Enter amount to send to bank account:\n#. Back";
            header('Location: index.php');
            exit;
        } else if ($input == $correctPin) {
            $_SESSION['ussd_state'] = 'transaction_success';
            $_SESSION['display'] = "Bank transfer successful!\nAccount: {$_SESSION['ussd_data']['bank_account']}\nAmount: GHS " . number_format($_SESSION['ussd_data']['bank_amount'], 2) . "\n\n1. Back to main menu";
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
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back";
                header('Location: index.php');
                exit;
            }
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
                $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back"; header('Location: index.php'); exit;
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
                    $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back"; header('Location: index.php'); exit;
                }
            }
        } else {
            if (!isset($_SESSION['ussd_data']['service_step']) || $input == '') {
                $_SESSION['ussd_data']['service_step'] = 1;
                $_SESSION['display'] = "Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back"; header('Location: index.php'); exit;
            } else if ($_SESSION['ussd_data']['service_step'] == 1) {
                // Choose service type
                if ($input == '1') {
                    $_SESSION['ussd_data']['service_type'] = 'airtime';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $_SESSION['display'] = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['service_type'] = 'data';
                    $_SESSION['ussd_data']['service_step'] = 2;
                    $_SESSION['display'] = "Buy Data:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
                } else {
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Buy Airtime\n2. Buy Data\n#. Back"; header('Location: index.php'); exit;
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
                        $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
                    } else {
                        $_SESSION['ussd_data']['service_step'] = 5; // Skip network and phone selection
                        $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back"; header('Location: index.php'); exit;
                    }
                } else if ($input == '2') {
                    $_SESSION['ussd_data']['for_self'] = false;
                    $_SESSION['ussd_data']['service_step'] = 3;
                    $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back"; header('Location: index.php'); exit;
                } else {
                    $serviceType = $_SESSION['ussd_data']['service_type'];
                    $_SESSION['display'] = "Invalid option. Please select:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 3) {
                // Choose network for other
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
                        $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
                    } else {
                        $_SESSION['display'] = "Select Data Bundle:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back"; header('Location: index.php'); exit;
                    }
                } else {
                    $_SESSION['display'] = "Invalid number for $network. Please enter a valid number for $network:\n#. Back"; header('Location: index.php'); exit;
                }
            } else if ($_SESSION['ussd_data']['service_step'] == 5) {
                if ($_SESSION['ussd_data']['service_type'] == 'airtime') {
                    // Enter amount for airtime
                    if (is_numeric($input) && $input > 0) {
                        $_SESSION['ussd_data']['amount'] = $input;
                        $_SESSION['ussd_data']['service_step'] = 6;
                        $response = "Confirm Airtime Purchase:\nBuy GHS " . number_format($input, 2) . " airtime for " . $_SESSION['ussd_data']['phone_number'] . ".\nEnter your PIN to confirm:\n#. Back";
                    } else {
                        $_SESSION['display'] = "Invalid amount. Please enter a valid amount (GHS):\n#. Back"; header('Location: index.php'); exit;
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
                        $_SESSION['display'] = "Invalid option. Please select:\n1. 1GB - GHS 5\n2. 2GB - GHS 9\n3. 5GB - GHS 20\n4. 10GB - GHS 35\n#. Back"; header('Location: index.php'); exit;
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
                $_SESSION['display'] = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
            } else if (isset($_SESSION['ussd_data']['airtime_step']) && $_SESSION['ussd_data']['airtime_step'] == 3 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other') {
                // Go back to network selection
                $_SESSION['ussd_data']['airtime_step'] = 2;
                $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back"; header('Location: index.php'); exit;
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
            $_SESSION['display'] = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
        } else if ($_SESSION['ussd_data']['airtime_step'] == 1) {
            if ($input == '1') {
                $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['ussd_data']['airtime_number'] = $user ? $user['phone'] : '';
                $_SESSION['ussd_data']['airtime_for'] = 'self';
                $_SESSION['ussd_data']['airtime_step'] = 2;
                $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
            } else if ($input == '2') {
                $_SESSION['ussd_data']['airtime_for'] = 'other';
                $_SESSION['ussd_data']['airtime_step'] = 2;
                $_SESSION['display'] = "Select Network:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back"; header('Location: index.php'); exit;
            } else {
                $_SESSION['display'] = "Invalid option. Please select:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
            }
        } else if ($_SESSION['ussd_data']['airtime_step'] == 2 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other') {
            if ($input == '1') {
                $_SESSION['ussd_data']['airtime_network'] = 'Telecel';
                $_SESSION['ussd_data']['airtime_step'] = 3;
                $_SESSION['display'] = "Enter recipient Telecel mobile number:\n#. Back"; header('Location: index.php'); exit;
            } else if ($input == '2') {
                $_SESSION['ussd_data']['airtime_network'] = 'AirtelTigo';
                $_SESSION['ussd_data']['airtime_step'] = 3;
                $_SESSION['display'] = "Enter recipient AirtelTigo mobile number:\n#. Back"; header('Location: index.php'); exit;
            } else if ($input == '3') {
                $_SESSION['ussd_data']['airtime_network'] = 'MTN';
                $_SESSION['ussd_data']['airtime_step'] = 3;
                $_SESSION['display'] = "Enter recipient MTN mobile number:\n#. Back"; header('Location: index.php'); exit;
            } else {
                $_SESSION['display'] = "Invalid option. Please select:\n1. Telecel\n2. AirtelTigo\n3. MTN\n#. Back"; header('Location: index.php'); exit;
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
                $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
            } else {
                $_SESSION['display'] = "Invalid number for $network. Please enter a valid number for $network:\n#. Back"; header('Location: index.php'); exit;
            }
        } else if (
            ($_SESSION['ussd_data']['airtime_step'] == 2 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'self') ||
            ($_SESSION['ussd_data']['airtime_step'] == 4 && isset($_SESSION['ussd_data']['airtime_for']) && $_SESSION['ussd_data']['airtime_for'] == 'other')
        ) {
            if ($input == '#') {
                // Go back to previous step
                if ($_SESSION['ussd_data']['airtime_for'] == 'self') {
                    $_SESSION['ussd_data']['airtime_step'] = 1;
                    $_SESSION['display'] = "Buy Airtime:\n1. For Self (My MTN No.)\n2. For Other (Another MTN No or Other Network.)\n#. Back"; header('Location: index.php'); exit;
                } else {
                    $_SESSION['ussd_data']['airtime_step'] = 3;
                    $_SESSION['display'] = "Enter recipient {$_SESSION['ussd_data']['airtime_network']} mobile number:\n#. Back"; header('Location: index.php'); exit;
                }
            } else if (is_numeric($input) && $input > 0) {
                $_SESSION['ussd_data']['airtime_amount'] = $input;
                $_SESSION['ussd_data']['airtime_step'] = 5;
                $response = "Confirm Airtime Purchase:\nBuy GHS " . number_format($input, 2) . " airtime for " . $_SESSION['ussd_data']['airtime_number'] . ".\nEnter your PIN to confirm:\n#. Back";
            } else {
                $_SESSION['display'] = "Invalid amount. Please enter a valid amount (GHS):\n#. Back"; header('Location: index.php'); exit;
            }
        } else if ($_SESSION['ussd_data']['airtime_step'] == 5) {
            if ($input == '#') {
                // Go back to amount entry
                if ($_SESSION['ussd_data']['airtime_for'] == 'self') {
                    $_SESSION['ussd_data']['airtime_step'] = 2;
                    $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
                } else {
                    $_SESSION['ussd_data']['airtime_step'] = 4;
                    $_SESSION['display'] = "Enter amount to buy (GHS):\n#. Back"; header('Location: index.php'); exit;
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
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement"; header('Location: index.php'); exit;
        } else if ($input == '') {
            $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
        } else if (preg_match('/^[A-Za-z0-9]{11}$/', $input)) {
            $_SESSION['ussd_data']['meter_number'] = $input;
            $_SESSION['ussd_state'] = 'select_meter_type';
            $_SESSION['display'] = "Select Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
        } else {
            $_SESSION['display'] = "Invalid meter number. Please enter a valid 11-digit meter number:\n#. Back"; header('Location: index.php'); exit;
        }
        break;

    case 'select_meter_type':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'meter_topup';
            $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['meter_type'] = 'Prepaid';
                    $_SESSION['ussd_state'] = 'enter_meter_amount';
                    $_SESSION['display'] = "Enter amount to top up:\n#. Back"; header('Location: index.php'); exit;
                    break;
                case '2':
                    $_SESSION['ussd_data']['meter_type'] = 'Postpaid';
                    $_SESSION['ussd_state'] = 'enter_meter_amount';
                    $_SESSION['display'] = "Enter amount to top up:\n#. Back"; header('Location: index.php'); exit;
                    break;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
                    break;
            }
        }
        break;

    case 'enter_meter_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_meter_number';
            $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
        } else if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['meter_amount'] = $input;
            $_SESSION['ussd_state'] = 'confirm_meter_topup';
            $response = "Enter your PIN to confirm meter top-up of GHS " . number_format($input, 2) .
                " for meter {$_SESSION['ussd_data']['meter_number']} ({$_SESSION['ussd_data']['meter_type']})\nName: {$_SESSION['ussd_data']['meter_name']}\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
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

                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$_SESSION['ussd_data']['amount'], $_SESSION['user_id']]);

                // Record meter transaction with completed status
                $stmt = $pdo->prepare("INSERT INTO meter_transactions (user_id, meter_number, meter_type, amount, status, transaction_date) VALUES (?, ?, ?, ?, 'completed', GETDATE())");
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
        break;

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
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement"; header('Location: index.php'); exit;
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_state'] = 'fixed_deposit';
                    $_SESSION['display'] = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:"; header('Location: index.php'); exit;
                case '2':
                    $_SESSION['ussd_state'] = 'treasury_bills';
                    $_SESSION['display'] = "Enter amount to invest in Treasury Bills:\n#. Back"; header('Location: index.php'); exit;
                case '3':
                    $_SESSION['ussd_state'] = 'mutual_funds';
                    $_SESSION['display'] = "Enter amount to invest in Mutual Funds:\n#. Back"; header('Location: index.php'); exit;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:"; header('Location: index.php'); exit;
            }
        }
        break;

    case 'fixed_deposit':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:"; header('Location: index.php'); exit;
        } else if (in_array($input, ['1', '2', '3'])) {
            $durations = ['1' => 3, '2' => 6, '3' => 12];
            $interest_rates = ['1' => 5.00, '2' => 7.00, '3' => 10.00];
            
            $_SESSION['ussd_data']['fd_duration'] = $durations[$input];
            $_SESSION['ussd_data']['fd_interest_rate'] = $interest_rates[$input];
            
            $_SESSION['ussd_state'] = 'enter_fixed_deposit_amount';
            $response = "Enter amount for Fixed Deposit (Duration: " . $durations[$input] . " Months, Interest: " . $interest_rates[$input] . "% p.a.):\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:"; header('Location: index.php'); exit;
        }
        break;

    case 'enter_fixed_deposit_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'fixed_deposit';
            $_SESSION['display'] = "Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:"; header('Location: index.php'); exit;
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
                    $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\n#. Back"; header('Location: index.php'); exit;
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
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
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated."; header('Location: index.php'); exit;
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;

    case 'treasury_bills':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:"; header('Location: index.php'); exit;
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
                    $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\n#. Back"; header('Location: index.php'); exit;
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
        }
        break;

    case 'enter_pin_for_treasury_bills':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'treasury_bills';
            $_SESSION['display'] = "Enter amount to invest in Treasury Bills:\n#. Back"; header('Location: index.php'); exit;
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
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated."; header('Location: index.php'); exit;
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;

    case 'mutual_funds':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'investment';
            $_SESSION['display'] = "Investment Options:\n1. Fixed Deposit\n2. Treasury Bills\n3. Mutual Funds\n#. Back\n\nSelect an option:"; header('Location: index.php'); exit;
        } else if (is_numeric($input) && $input > 0) {
            try {
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['mf_amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_mutual_fund_name';
                    $_SESSION['display'] = "Enter name of Mutual Fund:\n#. Back"; header('Location: index.php'); exit;
                } else {
                    $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\n#. Back"; header('Location: index.php'); exit;
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
        }
        break;

    case 'enter_mutual_fund_name':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'mutual_funds';
            $_SESSION['display'] = "Enter amount to invest in Mutual Funds:\n#. Back"; header('Location: index.php'); exit;
        } else if (!empty($input)) {
            $_SESSION['ussd_data']['mf_name'] = $input;
            $_SESSION['ussd_state'] = 'enter_pin_for_mutual_funds';
            $response = "Enter your PIN to confirm Mutual Funds investment of GHS " . number_format($_SESSION['ussd_data']['mf_amount'], 2) . " in " . $input . ":\n#. Back";
        } else {
            $_SESSION['display'] = "Mutual Fund name cannot be empty. Please enter a name:\n#. Back"; header('Location: index.php'); exit;
        }
        break;

    case 'enter_pin_for_mutual_funds':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_mutual_fund_name';
            $_SESSION['display'] = "Enter name of Mutual Fund:\n#. Back"; header('Location: index.php'); exit;
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
                $_SESSION['display'] = "Too many incorrect attempts. Your session has been terminated."; header('Location: index.php'); exit;
                session_destroy();
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;

    case 'investment_success':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement"; header('Location: index.php'); exit;
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Back to main menu"; header('Location: index.php'); exit;
        }
        break;

    case 'utility_payment':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Investment\n4. Utility Payment\n5. Statement"; header('Location: index.php'); exit;
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['utility_type'] = 'ECG';
                    $_SESSION['ussd_state'] = 'select_ecg_meter_type';
                    $_SESSION['display'] = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
                    break;
                case '2':
                    $_SESSION['ussd_data']['utility_type'] = 'Water';
                    $_SESSION['ussd_state'] = 'enter_utility_account';
                    $_SESSION['display'] = "Enter your Water account number:\n#. Back"; header('Location: index.php'); exit;
                    break;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. ECG (Electricity)\n2. Water\n#. Back"; header('Location: index.php'); exit;
                    break;
            }
        }
        break;

    case 'view_statement':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'start';
            $menuItems = $menuManager->getMainMenu($userId, $userBalance);
            $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
            header('Location: index.php');
            exit;
        } else {
            try {
                $transactions = [];
                try {
                    $stmt = $pdo->prepare("SELECT 'Send Money' as type, recipient_phone as details, amount, transaction_date FROM transactions WHERE sender_id = ? AND status = 'completed' ORDER BY transaction_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $sendMoney = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $sendMoney);
                } catch (Exception $e) {}
                try {
                    $stmt = $pdo->prepare("SELECT 'Airtime Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM airtime_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $airtime = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $airtime);
                } catch (Exception $e) {}
                try {
                    $stmt = $pdo->prepare("SELECT 'Data Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM data_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $data);
                } catch (Exception $e) {}
                try {
                    $stmt = $pdo->prepare("SELECT 'Utility Payment' as type, account_number as details, amount, payment_date as transaction_date FROM utility_payments WHERE user_id = ? ORDER BY payment_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $utility = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $transactions = array_merge($transactions, $utility);
                } catch (Exception $e) {}

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
                    $_SESSION['display'] = $response;
                } else {
                    $_SESSION['display'] = "No transactions found.\n\n#. Back";
                }
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error retrieving transactions. Please try again later.\n\n#. Back";
                header('Location: index.php');
                exit;
            }
        }
        break;

    case 'select_ecg_meter_type':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'utility_payment';
            $_SESSION['display'] = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n#. Back"; header('Location: index.php'); exit;
        } else {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_data']['meter_type'] = 'Prepaid';
                    $_SESSION['ussd_state'] = 'enter_meter_number';
                    $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
                    break;
                case '2':
                    $_SESSION['ussd_data']['meter_type'] = 'PostPaid';
                    $_SESSION['ussd_state'] = 'enter_meter_number';
                    $_SESSION['display'] = "Enter meter number:\n#. Back"; header('Location: index.php'); exit;
                    break;
                default:
                    $_SESSION['display'] = "Invalid option. Please select:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
                    break;
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
                $_SESSION['ussd_state'] = 'enter_meter_amount';
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
            $response = "Enter your PIN to confirm meter top-up of GHS " . number_format($input, 2) .
                " for meter {$_SESSION['ussd_data']['meter_number']} ({$_SESSION['ussd_data']['meter_type']})\nName: {$_SESSION['ussd_data']['meter_name']}\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
        }
        break;
    case 'confirm_meter_topup':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_meter_amount';
            $_SESSION['display'] = "Enter amount to top up:\n#. Back"; header('Location: index.php'); exit;
        } else if ($input == $correctPin) {
            // Insert ECG transaction into utility_payments table
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO utility_payments (user_id, utility_type, account_number, meter_type, amount, payment_date) VALUES (?, ?, ?, ?, ?, GETDATE())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    'ECG',
                    $_SESSION['ussd_data']['meter_number'],
                    $_SESSION['ussd_data']['meter_type'],
                    $_SESSION['ussd_data']['meter_amount']
                ]);
                $pdo->commit();
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Meter top-up successful!\nName: {$_SESSION['ussd_data']['meter_name']}\nMeter: {$_SESSION['ussd_data']['meter_number']}\nType: {$_SESSION['ussd_data']['meter_type']}\nAmount: GHS " . number_format($_SESSION['ussd_data']['meter_amount'], 2) . "\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['ussd_state'] = 'transaction_success';
                $_SESSION['display'] = "Meter top-up failed: " . $e->getMessage() . "\n\n1. Back to main menu";
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
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;

    case 'enter_utility_account':
        if ($input == '#') {
            // Handle back navigation based on utility type
            if (isset($_SESSION['ussd_data']['utility_type']) && $_SESSION['ussd_data']['utility_type'] == 'ECG') {
                $_SESSION['ussd_state'] = 'select_ecg_meter_type';
                $_SESSION['display'] = "Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back"; header('Location: index.php'); exit;
            } else {
                $_SESSION['ussd_state'] = 'utility_payment';
                $_SESSION['display'] = "Utility Payment:\n1. ECG (Electricity)\n2. Water\n#. Back"; header('Location: index.php'); exit;
            }
        } else if (preg_match('/^[A-Za-z0-9]{8,15}$/', $input)) {
            $_SESSION['ussd_data']['utility_account'] = $input;
            $_SESSION['ussd_state'] = 'enter_utility_amount';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Enter amount to pay for $utilityType:\n#. Back"; header('Location: index.php'); exit;
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
            try {
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['utility_amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_pin_for_utility';
                    $utilityType = $_SESSION['ussd_data']['utility_type'];
                    $account = $_SESSION['ussd_data']['utility_account'];
                    $_SESSION['display'] = "Confirm $utilityType payment:\nAccount: $account"; header('Location: index.php'); exit;
                    if (isset($_SESSION['ussd_data']['meter_type'])) {
                        $response .= " (" . $_SESSION['ussd_data']['meter_type'] . ")";
                    }
                    $response .= "\nAmount: GHS " . number_format($input, 2) . "\n\nEnter your PIN to confirm:\n#. Back";
                } else {
                    $_SESSION['display'] = "Insufficient balance. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\n#. Back"; header('Location: index.php'); exit;
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
        }
        break;

    case 'enter_pin_for_utility':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_utility_amount';
            $utilityType = $_SESSION['ussd_data']['utility_type'];
            $_SESSION['display'] = "Enter amount to pay for $utilityType:\n#. Back"; header('Location: index.php'); exit;
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
                $_SESSION['display'] = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN:\n#. Back"; header('Location: index.php'); exit;
            }
        }
        break;

    case 'enter_bank_account':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back"; header('Location: index.php'); exit;
        } else if (preg_match('/^[0-9]{10,20}$/', $input)) {
            $_SESSION['ussd_data']['bank_account'] = $input;
            $_SESSION['ussd_state'] = 'enter_bank_amount_alt';
            $_SESSION['display'] = "Enter amount to send to bank account:\n#. Back"; header('Location: index.php'); exit;
        } else {
            $_SESSION['display'] = "Invalid bank account number. Please enter a valid account number (10-20 digits):\n#. Back"; header('Location: index.php'); exit;
        }
        break;

    case 'enter_bank_amount_alt':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_bank_account';
            $_SESSION['display'] = "Enter Bank Account Number:\n#. Back"; header('Location: index.php'); exit;
        } else if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['bank_amount'] = $input;
            $_SESSION['ussd_state'] = 'confirm_bank_transfer';
            $response = "Confirm transfer of GHS " . number_format($input, 2) . " to account " . $_SESSION['ussd_data']['bank_account'] . ":\n1. Confirm\n#. Back";
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back"; header('Location: index.php'); exit;
        }
        break;

    case 'confirm_bank_transfer':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_bank_amount_alt';
            $_SESSION['display'] = "Enter amount to send to bank account:\n#. Back"; header('Location: index.php'); exit;
        } else if ($input == '1') {
            $_SESSION['ussd_state'] = 'transaction_success';
            $_SESSION['display'] = "Bank transfer successful!\nAccount: " . $_SESSION['ussd_data']['bank_account'] . "\nAmount: GHS " . number_format($_SESSION['ussd_data']['bank_amount'], 2) . "\n\n1. Back to main menu";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Confirm\n#. Back"; header('Location: index.php'); exit;
        }
        break;
 
    case 'enter_recipient_number':
        if ($input == '#') {
            // Go back to previous menu
        } else {
            $_SESSION['ussd_data']['recipient_number'] = $input;
            $_SESSION['ussd_state'] = 'enter_amount';
            $_SESSION['display'] = "Enter amount to send:\n#. Back";
            header('Location: index.php');
            exit();
        }
        break;
 
    case 'enter_recipient':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'select_network';
            $_SESSION['display'] = "Select Network:\n1. MTN MobileMoney\n2. Telecel Cash\n3. AirtelTigo Cash\n4. Bank Account\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            // Optionally, add phone number validation here
            $_SESSION['ussd_data']['recipient_number'] = $input;
            $_SESSION['ussd_state'] = 'enter_amount';
            $_SESSION['display'] = "Enter amount to send:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;
 
    case 'enter_amount':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_recipient';
            $network = $_SESSION['ussd_data']['network'];
            $_SESSION['display'] = "Enter {$network} number:\n#. Back";
            header('Location: index.php');
            exit;
        } else if (is_numeric($input) && $input > 0) {
            try {
                // Check if user has sufficient balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['balance'] >= $input) {
                    $_SESSION['ussd_data']['amount'] = $input;
                    $_SESSION['ussd_state'] = 'enter_reference';
                    $_SESSION['display'] = "Enter reference for this transfer:\n#. Back";
                    header('Location: index.php');
                    exit;
                } else {
                    $_SESSION['display'] = "Insufficient balance. Your balance is GHS " . number_format($user['balance'], 2) . ".\nPlease enter a valid amount:\n#. Back";
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                $_SESSION['display'] = "Error checking balance. Please try again:\nEnter amount to send:\n#. Back";
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['display'] = "Invalid amount. Please enter a valid amount:\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;

    case 'enter_reference':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_amount';
            $_SESSION['display'] = "Enter amount to send:\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['ussd_data']['reference'] = $input;
            $_SESSION['ussd_state'] = 'confirm_transfer';
            $network = $_SESSION['ussd_data']['network'];
            $recipient = $_SESSION['ussd_data']['recipient_number'];
            $amount = $_SESSION['ussd_data']['amount'];
            $reference = $_SESSION['ussd_data']['reference'];
            $_SESSION['display'] = "Confirm Transfer:\nTo: {$recipient}\nNetwork: {$network}\nAmount: GHS " . number_format($amount, 2) . "\nReference: {$reference}\n\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;

    case 'confirm_transfer':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'enter_reference';
            $_SESSION['display'] = "Enter reference for this transfer:\n#. Back";
            header('Location: index.php');
            exit;
        } else if ($input == '1') {
            $_SESSION['ussd_state'] = 'enter_pin';
            $_SESSION['display'] = "Enter your PIN to confirm transfer:\n#. Back";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        }
        break;

    case 'enter_pin':
        if ($input == '#') {
            $_SESSION['ussd_state'] = 'confirm_transfer';
            $network = $_SESSION['ussd_data']['network'];
            $recipient = $_SESSION['ussd_data']['recipient_number'];
            $amount = $_SESSION['ussd_data']['amount'];
            $reference = $_SESSION['ussd_data']['reference'];
            $_SESSION['display'] = "Confirm Transfer:\nTo: {$recipient}\nNetwork: {$network}\nAmount: GHS " . number_format($amount, 2) . "\nReference: {$reference}\n\n1. Confirm\n#. Back";
            header('Location: index.php');
            exit;
        } else if ($input == $correctPin) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$_SESSION['ussd_data']['amount'], $_SESSION['user_id']]);
                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE phone = ?");
                $stmt->execute([$_SESSION['ussd_data']['amount'], $_SESSION['ussd_data']['recipient_number']]);
                // Insert transfer with completed status
                $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, reference, status, transaction_date) VALUES (?, ?, ?, ?, 'completed', GETDATE())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['ussd_data']['recipient_number'],
                    $_SESSION['ussd_data']['amount'],
                    $_SESSION['ussd_data']['reference']
                ]);
                $pdo->commit();
                $_SESSION['ussd_state'] = 'transaction_success';
                $network = $_SESSION['ussd_data']['network'];
                $recipient = $_SESSION['ussd_data']['recipient_number'];
                $amount = $_SESSION['ussd_data']['amount'];
                $reference = $_SESSION['ussd_data']['reference'];
                $_SESSION['display'] = "Transfer successful!\nTo: {$recipient}\nNetwork: {$network}\nAmount: GHS " . number_format($amount, 2) . "\nReference: {$reference}\n\n1. Back to main menu";
                header('Location: index.php');
                exit;
            } catch(PDOException $e) {
                $pdo->rollBack();
                $_SESSION['display'] = "Transaction failed. Please try again later.";
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Wrong PIN provided. Returning to main menu.\n\n1. Back to main menu";
            header('Location: index.php');
            exit;
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

$stmt = $pdo->prepare("INSERT INTO menu_usage_logs (user_id, menu_item_id, session_id, accessed_at, ip_address, user_agent) VALUES (?, ?, ?, GETDATE(), ?, ?)");
$stmt->execute([1, 1, session_id(), '127.0.0.1', 'TestAgent']);
echo "Inserted!";

date_default_timezone_set('Africa/Accra'); // or your timezone
$now = date('Y-m-d H:i:s');
?>
