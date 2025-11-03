<?php
/**
 * Transaction Expiry Management
 * Handles checking and updating expired transactions
 */

require_once 'db_connect.php';

class TransactionExpiryManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check for expired transactions and update their status
     * @return array Results of the expiry check
     */
    public function checkExpiredTransactions() {
        try {
            // Find all pending transactions that have passed their expiry time
            $stmt = $this->pdo->prepare("
                SELECT id, sender_id, recipient_phone, amount, transaction_date, expiry_time 
                FROM transactions 
                WHERE status = 'pending' 
                AND expiry_time IS NOT NULL 
                AND expiry_time < GETDATE()
            ");
            $stmt->execute();
            $expiredTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $expiredCount = 0;
            $errors = [];
            
            if (!empty($expiredTransactions)) {
                // Update expired transactions to 'expired' status
                $updateStmt = $this->pdo->prepare("
                    UPDATE transactions 
                    SET status = 'expired' 
                    WHERE status = 'pending' 
                    AND expiry_time IS NOT NULL 
                    AND expiry_time < GETDATE()
                ");
                
                if ($updateStmt->execute()) {
                    $expiredCount = $updateStmt->rowCount();
                    
                    // Log expired transactions
                    $this->logExpiredTransactions($expiredTransactions);
                } else {
                    $errors[] = "Failed to update expired transactions";
                }
            }
            
            return [
                'success' => true,
                'expired_count' => $expiredCount,
                'expired_transactions' => $expiredTransactions,
                'errors' => $errors,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage(),
                'expired_count' => 0,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Log expired transactions for audit purposes
     * @param array $expiredTransactions
     */
    private function logExpiredTransactions($expiredTransactions) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] " . count($expiredTransactions) . " transactions expired\n";
        
        foreach ($expiredTransactions as $transaction) {
            $logMessage .= "  - Transaction ID: {$transaction['id']}, Amount: {$transaction['amount']}, Recipient: {$transaction['recipient_phone']}\n";
        }
        
        // Log to file (optional)
        error_log($logMessage, 3, 'expired_transactions.log');
    }
    
    /**
     * Get statistics about pending and expired transactions
     * @return array Transaction statistics
     */
    public function getTransactionStats() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM transactions 
                WHERE transaction_date >= DATEADD(day, -1, GETDATE())
                GROUP BY status
            ");
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}

// Function to be called from other scripts
function checkTransactionExpiry($pdo) {
    $expiryManager = new TransactionExpiryManager($pdo);
    return $expiryManager->checkExpiredTransactions();
}

// Function to get transaction statistics
function getTransactionStats($pdo) {
    $expiryManager = new TransactionExpiryManager($pdo);
    return $expiryManager->getTransactionStats();
}

// If this script is run directly (not included), execute the expiry check
if (basename($_SERVER['PHP_SELF']) === 'transaction_expiry.php') {
    $result = checkTransactionExpiry($pdo);
    
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        echo "Transaction Expiry Check Results:\n";
        echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        echo "Expired Count: " . $result['expired_count'] . "\n";
        echo "Timestamp: " . $result['timestamp'] . "\n";
        
        if (!empty($result['errors'])) {
            echo "Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "- " . $error . "\n";
            }
        }
    }
}
?>
