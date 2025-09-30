<?php
// USSD Service Helper Functions

function validatePhoneNumber($number, $network = null) {
    // Ghana mobile number validation (basic)
    $number = preg_replace('/\D/', '', $number); // Remove non-digits
    if (strlen($number) == 12 && substr($number, 0, 3) == '233') {
        $number = '0' . substr($number, 3);
    }
    if (strlen($number) != 10 || $number[0] != '0') {
        return false;
    }
    // Optionally check network prefix
    if ($network) {
        $prefixes = [
            'MTN' => ['024', '054', '055', '059', '025'],
            'Telecel' => ['020', '050'],
            'AirtelTigo' => ['026', '056', '027', '057']
        ];
        $valid = false;
        foreach ($prefixes[$network] ?? [] as $prefix) {
            if (strpos($number, $prefix) === 0) {
                $valid = true;
                break;
            }
        }
        if (!$valid) return false;
    }
    return $number;
}

function validateAmount($amount) {
    return is_numeric($amount) && $amount > 0;
}

function fetchRecipientDetails($number, $network = null) {
    // Demo/mock recipient lookup
    $demoRecipients = [
        '0241234567' => ['name' => 'John Doe', 'network' => 'MTN'],
        '0551234567' => ['name' => 'Jane Smith', 'network' => 'MTN'],
        '0201234567' => ['name' => 'Kwame Mensah', 'network' => 'Telecel'],
        '0501234567' => ['name' => 'Ama Serwaa', 'network' => 'Telecel'],
        '0261234567' => ['name' => 'Kofi Annan', 'network' => 'AirtelTigo'],
        '0571234567' => ['name' => 'Abena Poku', 'network' => 'AirtelTigo'],
    ];
    if (isset($demoRecipients[$number]) && (!$network || $demoRecipients[$number]['network'] === $network)) {
        return ['success' => true, 'name' => $demoRecipients[$number]['name']];
    }
    return ['success' => false, 'name' => ''];
}

function insertTransaction($pdo, $senderId, $recipient, $amount, $status = 'pending') {
    $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, recipient_phone, amount, status, transaction_date) VALUES (?, ?, ?, ?, GETDATE())");
    $stmt->execute([$senderId, $recipient, $amount, $status]);
    return $pdo->lastInsertId();
}

// ✅ Fix: prevent redeclaration
if (!function_exists('db_validateUtilityRecipient')) {
    function db_validateUtilityRecipient($pdo, $number, $utility) {
        if ($utility === 'ECG' || $utility === 'Water') {
            $stmt = $pdo->prepare("SELECT TOP 1 * FROM utility_payments WHERE account_number = ? AND utility_type = ?");
            $stmt->execute([$number, $utility]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'success' => true,
                    'name' => $row['account_number'],
                    'meter_type' => $row['meter_type'] ?? null
                ];
            } else {
                return ['success' => false];
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM utility_accounts WHERE account_number = ? AND utility_type = ?");
            $stmt->execute([$number, $utility]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'success' => true,
                    'name' => $row['name'],
                    'package' => $row['package'] ?? null,
                    'amt' => $row['amt'] ?? null
                ];
            } else {
                return ['success' => false];
            }
        }
    }
}
