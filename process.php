<?php
session_start();


if (!isset($_SESSION['ussd_state'])) {
    $_SESSION['ussd_state'] = 'start';
    $_SESSION['ussd_data'] = [];
    $_SESSION['pin_attempts'] = 0; 
}


$input = isset($_POST['ussd_input']) ? $_POST['ussd_input'] : '';

$correctPin = '1234';



switch ($_SESSION['ussd_state']) {
    
    case 'check_balance':
        if ($input == '1') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Welcome to USSD Service\n\nPlease enter your choice:\n1. Check Balance\n2. Transfer Money\n3. Buy Airtime";
        } else {
            $response = "Invalid option. Please select:\n1. Back to main menu";
        }
        break;


    case 'start':
        switch ($input) {
            case '1':
                $_SESSION['ussd_state'] = 'pin_input'; 
                $response = "Please enter your PIN code:";
                break;
            case '2':
                $_SESSION['ussd_state'] = 'transfer_money';
                $response = "Enter recipient's phone number:";
                break;
            case '3':
                $_SESSION['ussd_state'] = 'buy_airtime';
                $response = "Enter phone number for airtime:";
                break;
            default:
                $response = "Invalid option. Please try again:\n1. Check Balance\n2. Transfer Money\n3. Buy Airtime";
        }
        break;

    case 'pin_input':
        if ($input == $correctPin) {
            $_SESSION['ussd_state'] = 'check_balance'; 
            $_SESSION['pin_attempts'] = 0; 
            $response = "Your current balance is: GHS 100.00\n\n1. Back to main menu";
        } else {
            $_SESSION['pin_attempts']++; 
            if ($_SESSION['pin_attempts'] >= 3) {
               
                $response = "Too many incorrect attempts. Your session has been terminated.";
                session_destroy(); 
            } else {
                $remainingAttempts = 3 - $_SESSION['pin_attempts'];
                $response = "Invalid PIN. You have {$remainingAttempts} attempts remaining.\nPlease enter your PIN code:";
            }
        }
        break;

    case 'transfer_money':
        if (preg_match('/^0[2-9][0-9]{8}$/', $input)) {
            $_SESSION['ussd_data']['recipient'] = $input;
            $_SESSION['ussd_state'] = 'transfer_amount';
            $response = "Enter amount to transfer:";
        } else {
            $response = "Invalid phone number. Please enter a valid number:";
        }
        break;

    case 'transfer_amount':
        if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['amount'] = $input;
            $_SESSION['ussd_state'] = 'transfer_pin_input'; 
            $_SESSION['pin_attempts'] = 0; 
            $response = "Please enter your PIN code to confirm the transfer:";
        } else {
            $response = "Invalid amount. Please enter a valid amount:";
        }
        break;

    case 'transfer_pin_input':
        if ($input == $correctPin) {
          
            $amount = $_SESSION['ussd_data']['amount'];
            $recipient = $_SESSION['ussd_data']['recipient'];
            $_SESSION['ussd_state'] = 'start'; // Reset state to start
            $response = "You have successfully transferred GHS {$amount} to {$recipient}!\n\n1. Back to main menu";
        } else {
           
            $_SESSION['ussd_state'] = 'start'; 
            $response = "Wrong PIN provided. Returning to main menu.\n\n1. Back to main menu";
        }
        break;

    case 'airtime_amount':
        if (is_numeric($input) && $input > 0) {
            $_SESSION['ussd_data']['airtime_amount'] = $input;
            $_SESSION['ussd_state'] = 'airtime_confirm';
            $response = "Confirm airtime purchase of GHS {$input} for {$_SESSION['ussd_data']['airtime_number']}\n1. Confirm\n2. Cancel";
        } else {
            $response = "Invalid amount. Please enter a valid amount:";
        }
        break;

    case 'airtime_confirm':
        if ($input == '1') {
            
            $_SESSION['ussd_state'] = 'start';
            $response = "Airtime purchase successful!\n\n1. Back to main menu";
        } elseif ($input == '2') {
            $_SESSION['ussd_state'] = 'start';
            $response = "Airtime purchase cancelled.\n\n1. Back to main menu";
        } else {
            $response = "Invalid option. Please select:\n1. Confirm\n2. Cancel";
        }
        break;

   

}

// Update the display
$_SESSION['display'] = $response;

// Redirect back to the main page
header('Location: index.php');
exit;
?>