<?php
// Test script to verify USSD flow
session_start();

echo "Testing USSD Flow...\n\n";

// Test 1: Initial state
echo "Test 1: Initial state\n";
if (!isset($_SESSION['ussd_state'])) {
    echo "✓ Session state not set initially\n";
} else {
    echo "✗ Session state already set: " . $_SESSION['ussd_state'] . "\n";
}

// Test 2: Simulate selecting option 1 (Send Money)
echo "\nTest 2: Selecting option 1 (Send Money)\n";
$_POST['ussd_input'] = '1';
include 'process.php';

if (isset($_SESSION['ussd_state']) && $_SESSION['ussd_state'] == 'select_network') {
    echo "✓ Successfully moved to select_network state\n";
} else {
    echo "✗ Failed to move to select_network state. Current state: " . ($_SESSION['ussd_state'] ?? 'not set') . "\n";
}

if (isset($_SESSION['display']) && strpos($_SESSION['display'], 'Select Network:') !== false) {
    echo "✓ Display message updated correctly\n";
} else {
    echo "✗ Display message not updated correctly\n";
}

// Test 3: Simulate selecting option 2 (Buy Airtime/Data)
echo "\nTest 3: Selecting option 2 (Buy Airtime/Data)\n";
session_destroy();
session_start();
$_POST['ussd_input'] = '2';
include 'process.php';

if (isset($_SESSION['ussd_state']) && $_SESSION['ussd_state'] == 'buy_airtime_data') {
    echo "✓ Successfully moved to buy_airtime_data state\n";
} else {
    echo "✗ Failed to move to buy_airtime_data state. Current state: " . ($_SESSION['ussd_state'] ?? 'not set') . "\n";
}

// Test 4: Simulate selecting option 5 (Statement)
echo "\nTest 4: Selecting option 5 (Statement)\n";
session_destroy();
session_start();
$_POST['ussd_input'] = '5';
include 'process.php';

if (isset($_SESSION['ussd_state']) && $_SESSION['ussd_state'] == 'view_statement') {
    echo "✓ Successfully moved to view_statement state\n";
} else {
    echo "✗ Failed to move to view_statement state. Current state: " . ($_SESSION['ussd_state'] ?? 'not set') . "\n";
}

echo "\nFlow test completed!\n";
?> 