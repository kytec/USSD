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
    $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
    
    // Redirect to index page
    header('Location: index.php');
    exit();
}
 
$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';
 
// Main menu routing
switch ($_SESSION['ussd_state']) {
    case 'start':
        switch ($input) {
            case '1':
                $_SESSION['ussd_state'] = 'send_money';
                header('Location: send_money.php');
                exit;
            case '2':
                $_SESSION['ussd_state'] = 'buy_airtime_data';
                header('Location: buy_airtime_data.php');
                exit;
            case '3':
                $_SESSION['ussd_state'] = 'meter_topup';
                header('Location: meter_topup.php');
                exit;
            case '4':
                $_SESSION['ussd_state'] = 'investment';
                header('Location: investment.php');
                exit;
            case '5':
                $_SESSION['ussd_state'] = 'utility_payment';
                header('Location: utility_payment.php');
                exit;
            default:
                $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
                header('Location: index.php');
                exit;
        }
        
    
    case 'transaction_success':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
        } else {
            $_SESSION['display'] = "Invalid option. Please select:\n1. Back to main menu";
        }
        header('Location: index.php');
        exit;
        
    default:
        // If somehow we get here with an unhandled state, go back to start
        $_SESSION['ussd_state'] = 'start';
        $_SESSION['display'] = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n1. Send Money\n2. Buy Airtime/Data\n3. Meter Top-up\n4. Investment\n5. Utility Payment";
        header('Location: index.php');
        exit;
}
?>