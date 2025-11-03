<?php
/**
 * Cron Job Script for Transaction Expiry Check
 * This script should be run every 30 seconds to check for expired transactions
 * 
 * To set up the cron job:
 * 1. Open Windows Task Scheduler
 * 2. Create a new task
 * 3. Set trigger to repeat every 30 seconds
 * 4. Set action to run this PHP script
 * 
 * Or use a web-based cron service to call this script via HTTP every 30 seconds
 */

// Set timezone
date_default_timezone_set('UTC');

// Include the expiry check functionality
require_once 'transaction_expiry.php';

// Log the start of the cron job
$startTime = microtime(true);
$logMessage = "[" . date('Y-m-d H:i:s') . "] Starting transaction expiry check\n";

// Execute the expiry check
$result = checkTransactionExpiry($pdo);

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

// Log the results
$logMessage .= "[" . date('Y-m-d H:i:s') . "] Expiry check completed\n";
$logMessage .= "  - Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
$logMessage .= "  - Expired Count: " . $result['expired_count'] . "\n";
$logMessage .= "  - Execution Time: {$executionTime}ms\n";

if (!empty($result['errors'])) {
    $logMessage .= "  - Errors: " . implode(', ', $result['errors']) . "\n";
}

// Write to log file
file_put_contents('cron_expiry_log.txt', $logMessage, FILE_APPEND | LOCK_EX);

// If called via HTTP, return JSON response
if (isset($_SERVER['HTTP_HOST'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $result['success'],
        'expired_count' => $result['expired_count'],
        'execution_time_ms' => $executionTime,
        'timestamp' => date('Y-m-d H:i:s'),
        'errors' => $result['errors'] ?? []
    ]);
} else {
    // If called from command line, output to console
    echo $logMessage;
}

// Exit with appropriate code
exit($result['success'] ? 0 : 1);
?>
