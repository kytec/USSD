<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class MenuManager {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get main menu items for a user (DB-first, numeric-order, deduped, top-5)
     */
    public function getMainMenu($userId = null, $userBalance = null) {
    // Try DB first
    $rows = $this->getMenuItemsFromDb($userId, $userBalance);

    // If DB returned null (error) or empty, fall back to hardcoded list
    if ($rows === null || count($rows) === 0) {
        // fallback hardcoded (keeps correct order)
        $fallback = [
            ['id'=>1,'action_value'=>'send_money','menu_number'=>'1','display_text'=>'Send Money','display_order'=>1,'is_active'=>true,'min_balance'=>1.0],
            ['id'=>2,'action_value'=>'buy_airtime_data','menu_number'=>'2','display_text'=>'Buy Airtime/Data','display_order'=>2,'is_active'=>true,'min_balance'=>1.0],
            ['id'=>3,'action_value'=>'investment','menu_number'=>'3','display_text'=>'Investment','display_order'=>3,'is_active'=>true,'min_balance'=>10.0],
            ['id'=>4,'action_value'=>'utility_payment','menu_number'=>'4','display_text'=>'Utility Payment','display_order'=>4,'is_active'=>true,'min_balance'=>1.0],
            ['id'=>5,'action_value'=>'statement','menu_number'=>'5','display_text'=>'Statement','display_order'=>5,'is_active'=>true,'min_balance'=>0.0],
        ];
        $rows = $fallback;
    }

    // Normalize numeric menu_number and display_order
    foreach ($rows as &$r) {
        $r['menu_number_normalized'] = (isset($r['menu_number']) && is_numeric($r['menu_number'])) ? (int)$r['menu_number'] : PHP_INT_MAX;
        $r['display_order_normalized'] = isset($r['display_order']) ? (int)$r['display_order'] : 0;
    }
    unset($r);

    // Sort by normalized menu_number then display_order
    usort($rows, function($a, $b) {
        if ($a['menu_number_normalized'] !== $b['menu_number_normalized']) {
            return $a['menu_number_normalized'] <=> $b['menu_number_normalized'];
        }
        return $a['display_order_normalized'] <=> $b['display_order_normalized'];
    });

    // Dedupe by action_value (keep first occurrence)
    $deduped = [];
    $seenActions = [];
    foreach ($rows as $row) {
        $actionKey = isset($row['action_value']) ? $row['action_value'] : ($row['display_text'] ?? null);
        if ($actionKey === null) { continue; }
        if (isset($seenActions[$actionKey])) { continue; }
        $seenActions[$actionKey] = true;
        $deduped[] = $row;
    }

    // Optional balance gating already applied by getMenuItemsFromDb; ensure here too
    if ($userBalance !== null) {
        $deduped = array_values(array_filter($deduped, function($it) use ($userBalance) {
            return (!isset($it['min_balance']) || (float)$it['min_balance'] <= (float)$userBalance);
        }));
    }

    // Limit to top 5 main items
    $deduped = array_slice($deduped, 0, 5);

    // Map to consistent shape expected elsewhere
    $mapped = [];
    foreach ($deduped as $r) {
        $mapped[] = [
            'id' => isset($r['id']) ? (int)$r['id'] : null,
            'name' => $r['name'] ?? $r['display_text'],
            'display_text' => $r['display_text'],
            'menu_number' => (string)($r['menu_number'] ?? $r['menu_number_normalized']),
            'action_type' => $r['action_type'] ?? null,
            'action_value' => $r['action_value'] ?? null,
            'display_order' => isset($r['display_order']) ? (int)$r['display_order'] : 0,
            'requires_auth' => isset($r['requires_auth']) ? (bool)$r['requires_auth'] : false,
            'min_balance' => isset($r['min_balance']) ? (float)$r['min_balance'] : 0.0,
            'is_active' => isset($r['is_active']) ? (bool)$r['is_active'] : true,
        ];
    }

    // Log final order for debugging
    $orderLog = array_map(function($it){ return "{$it['menu_number']}:{$it['display_text']}"; }, $mapped);
    error_log("MenuManager:getMainMenu final order -> " . implode(" | ", $orderLog));

    return $mapped;
}

/**
 * Safe DB loader for menu_items (returns array or empty array or null on fatal error).
 * Ensures it returns flat rows for top-level items only (filters by allowed actions).
 */
private function getMenuItemsFromDb($userId = null, $userBalance = null) {
    if (!$this->pdo) { return null; }
    try {
        // Check existence quickly
        $check = $this->pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'menu_items'");
        if ($check->fetchColumn() != 1) { return []; }

        // Fetch active items ordered numerically by menu_number then display_order
        $sql = "
            SELECT id, name, display_text, menu_number, action_type, action_value, display_order, is_active, requires_auth, min_balance
            FROM menu_items
            WHERE is_active = 1
            ORDER BY CASE WHEN TRY_CONVERT(INT, menu_number) IS NULL THEN 2147483647 ELSE TRY_CONVERT(INT, menu_number) END ASC, display_order ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) { return []; }

        // Keep only known top-level actions to avoid accidentally returning submenu records
        $allowedActions = ['send_money','buy_airtime_data','investment','utility_payment','statement'];
        $rows = array_values(array_filter($rows, function($row) use ($allowedActions) {
            // If action_value is null or empty, still allow (fallback by display_text), but prefer action_value filtering
            return empty($row['action_value']) ? true : in_array($row['action_value'], $allowedActions, true);
        }));

        // Optional balance filter
        if ($userBalance !== null) {
            $rows = array_values(array_filter($rows, function($row) use ($userBalance) {
                return (!isset($row['min_balance']) || (float)$row['min_balance'] <= (float)$userBalance);
            }));
        }

        return $rows;
    } catch (Throwable $e) {
        error_log("MenuManager:getMenuItemsFromDb error: " . $e->getMessage());
        return null; // signal fatal DB error so caller falls back
    }
}

/** Build menu display text ONLY for top-level items */
public function buildMenuDisplay($menuItems) {
    // Defensive: ensure array
    if (!is_array($menuItems)) { $menuItems = []; }

    // Log incoming items (for debugging)
    $incoming = array_map(function($it){ return ($it['menu_number'] ?? '?') . ':' . ($it['display_text'] ?? ''); }, $menuItems);
    error_log("MenuManager:buildMenuDisplay incoming -> " . implode(" | ", $incoming));

    $display = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n";
    if (empty($menuItems)) {
        $display .= "No menu items available.\n";
    } else {
        foreach ($menuItems as $item) {
            // show only top-level entries; ignore items that look like submenus (simple guard)
            if (isset($item['is_active']) && !$item['is_active']) continue;
            $num = $item['menu_number'] ?? ($item['menu_number_normalized'] ?? '');
            $display .= "{$num}. {$item['display_text']}\n";
        }
    }
    return $display;
}
}
?>
