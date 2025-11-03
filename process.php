<?php
session_set_cookie_params(['path' => '/USSD2/USSD']);
session_start();
require_once 'db_connect.php';
require_once 'menu_manager.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
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
    session_destroy();
    session_start();
    $_SESSION['ussd_state'] = 'start';
    $_SESSION['ussd_data'] = [];
    $_SESSION['pin_attempts'] = 0;
    $_SESSION['user_id'] = 1; // This should be set based on actual user authentication
    $menuItems = $menuManager->getMainMenu($userId, $userBalance);
    $_SESSION['display'] = $menuManager->buildMenuDisplay($menuItems);
    header('Location: index.php');
    exit();
}
 
$input = isset($_POST['ussd_input']) ? trim($_POST['ussd_input']) : '';
$correctPin = '1234';
 
// Only process input if not empty (but allow empty input for initial state transitions)
if ($input === '' && $_SESSION['ussd_state'] === 'start' && (!isset($_POST['action']) || $_POST['action'] !== 'cancel')) {
    $_SESSION['display'] = "Please enter a choice before pressing Send.";
    header('Location: index.php');
    exit;
}
 
// Routing based on current ussd_state
switch ($_SESSION['ussd_state']) {
    case 'start':
        if (!empty($input)) {
            switch ($input) {
                case '1':
                    $_SESSION['ussd_state'] = 'send_money';
                    break;
                case '2':
                    $_SESSION['ussd_state'] = 'buy_airtime_data';
                    break;
                case '3':
                    $_SESSION['ussd_state'] = 'investment';
                    break;
                case '4':
                    $_SESSION['ussd_state'] = 'utility_payment';
                    break;
                case '5':
                    $_SESSION['ussd_state'] = 'view_statement';
                    break;
                default:
                    $_SESSION['display'] = "Invalid option. Please select a valid menu option.";
                    header('Location: index.php');
                    exit;
            }
            // Continue processing to the new state without redirect
        } else {
            // No input provided, show error
            $_SESSION['display'] = "Please enter a choice before pressing Send.";
            header('Location: index.php');
            exit;
        }
        break;

    case 'send_money':
        // Make sure variables are available in flow files
        $GLOBALS['menuManager'] = $menuManager;
        $GLOBALS['userId'] = $userId;
        $GLOBALS['userBalance'] = $userBalance;
        require_once 'flow/send_money.php';
        break;
 
    case 'select_network':
    case 'select_bank':
    case 'enter_bank_account_number':
    case 'confirm_bank_recipient':
    case 'enter_bank_amount_alt':
        case 'enter_recipient':
    case 'enter_amount':
    case 'confirm_recipient':
    case 'enter_reference':
    case 'confirm_transfer':
    case 'transaction_success':
        require_once 'flow/send_money.php';
            break;
            
    case 'session_ended':
        // Session has ended - no further processing needed
        // The display message is already set, just show it
            break;
            
    case 'view_statement':
        require_once 'flow/statement.php';
            break;
 
    case 'buy_airtime_data':
        // Make sure variables are available in flow files
        $GLOBALS['menuManager'] = $menuManager;
        $GLOBALS['userId'] = $userId;
        $GLOBALS['userBalance'] = $userBalance;
        require_once 'flow/buy_airtime_data.php';
        break;

    case 'buy_airtime_data_input':
    case 'buy_airtime_data_success':
    case 'buy_airtime':
        require_once 'flow/buy_airtime_data.php';
        break;

    case 'investment':
        // Make sure variables are available in flow files
        $GLOBALS['menuManager'] = $menuManager;
        $GLOBALS['userId'] = $userId;
        $GLOBALS['userBalance'] = $userBalance;
        require_once 'flow/investment.php';
        break;

    case 'enter_investment_account':
    case 'investment_input':
    case 'fixed_deposit':
    case 'enter_fixed_deposit_amount':
    case 'confirm_fixed_deposit':
    case 'treasury_bills':
    case 'confirm_treasury_bills':
    case 'mutual_funds':
    case 'confirm_mutual_funds':
    case 'enter_mutual_fund_name':
    case 'investment_success':
        require_once 'flow/investment.php';
        break;

    case 'utility_payment':
    case 'utility_payment_input':
        // Make sure variables are available in flow files
        $GLOBALS['menuManager'] = $menuManager;
        $GLOBALS['userId'] = $userId;
        $GLOBALS['userBalance'] = $userBalance;
        require_once 'flow/utility_payment.php';
        break;

    // Utility Payment sub-states
    case 'select_ecg_meter_type':
    case 'enter_utility_account':
    case 'enter_dstv_smartcard':
    case 'enter_gotv_iuc':
    case 'enter_meter_number':
    case 'enter_ecg_meter_amount':
    case 'confirm_meter_topup':
    case 'enter_ecg_amount':
    case 'confirm_ecg_payment':
    case 'enter_water_amount':
    case 'confirm_water_payment':
    case 'enter_dstv_amount':
    case 'confirm_dstv_payment':
    case 'enter_gotv_amount':
    case 'confirm_gotv_payment':
    case 'utility_payment_success':
        // Make sure variables are available in flow files
        $GLOBALS['menuManager'] = $menuManager;
        $GLOBALS['userId'] = $userId;
        $GLOBALS['userBalance'] = $userBalance;
        require_once 'flow/utility_payment.php';
        break;

                default:
        $_SESSION['display'] = "Invalid state. Please restart.";
                header('Location: index.php');
                exit;
            }
 
                header('Location: index.php');
                exit;