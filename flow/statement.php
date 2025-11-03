<?php
// Statement flow handler
switch ($_SESSION['ussd_state']) {
    case 'view_statement':
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
                    $transactions = array_slice($transactions, 0, 5);
                    $response = "Last 5 Transactions:\n\n";
                    $counter = 1;
                    foreach ($transactions as $transaction) {
                        $date = date('d/m/Y', strtotime($transaction['transaction_date']));
                        $response .= "{$counter}. {$transaction['type']} - GHS " . number_format($transaction['amount'], 2) . " - {$date}\n";
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
        } else {
            // No input provided - show account statement
            try {
                $transactions = [];
                try {
                    $stmt = $pdo->prepare("SELECT 'Send Money' as type, recipient_phone as details, amount, transaction_date FROM transactions WHERE sender_id = ? AND status = 'completed' ORDER BY transaction_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $sendMoney = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($sendMoney as $tx) {
                        $transactions[] = $tx;
                    }
                } catch (PDOException $e) {
                    // Database error, continue without send money transactions
                }
                
                try {
                    $stmt = $pdo->prepare("SELECT 'Airtime Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM airtime_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $airtime = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($airtime as $tx) {
                        $transactions[] = $tx;
                    }
                } catch (PDOException $e) {
                    // Database error, continue without airtime transactions
                }
                
                try {
                    $stmt = $pdo->prepare("SELECT 'Data Purchase' as type, phone_number as details, amount, purchase_date as transaction_date FROM data_purchases WHERE user_id = ? ORDER BY purchase_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($data as $tx) {
                        $transactions[] = $tx;
                    }
                } catch (PDOException $e) {
                    // Database error, continue without data transactions
                }
                
                if (empty($transactions)) {
                    $_SESSION['display'] = "No transactions found.\n\n#. Back";
                } else {
                    $display = "Account Statement:\n\n";
                    $count = 0;
                    foreach ($transactions as $tx) {
                        $count++;
                        if ($count > 10) break; // Limit to last 10 transactions
                        $date = date('d/m/Y H:i', strtotime($tx['transaction_date']));
                        $display .= "{$count}. {$tx['type']}\n   To: {$tx['details']}\n   Amount: GHS " . number_format($tx['amount'], 2) . "\n   Date: {$date}\n\n";
                    }
                    $display .= "#. Back";
                    $_SESSION['display'] = $display;
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
    default:
        // Not a Statement state, do nothing
        break;
}
return;
