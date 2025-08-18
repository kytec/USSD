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
     * Get main menu items for a user (from DB if available, else fallback)
     */
    public function getMainMenu($userId = null, $userBalance = null) {
        // Prefer flat menu_items table updates if present (admin uses this)
        $legacyItems = $this->getMenuItemsFromDb($userId, $userBalance);
        if ($legacyItems !== null && count($legacyItems) > 0) {
            return $legacyItems;
        }
        // Fall back to hierarchical nodes if configured
        $dbItems = $this->getMenuNodesFromDb($userId, $userBalance);
        if ($dbItems !== null && count($dbItems) > 0) {
            return $dbItems;
        }
        
        // Fallback simple hardcoded menu items
        $menuItems = [
            [
                'id' => 1,
                'name' => 'Send Money',
                'display_text' => 'Send Money',
                'menu_number' => '1',
                'action_type' => 'function',
                'action_value' => 'send_money',
                'display_order' => 1,
                'requires_auth' => true,
                'min_balance' => 1.00,
                'user_type' => 'all',
                'is_active' => true
            ],
            [
                'id' => 2,
                'name' => 'Buy Airtime/Data',
                'display_text' => 'Buy Airtime/Data',
                'menu_number' => '2',
                'action_type' => 'function',
                'action_value' => 'buy_airtime_data',
                'display_order' => 2,
                'requires_auth' => true,
                'min_balance' => 1.00,
                'user_type' => 'all',
                'is_active' => true
            ],
            [
                'id' => 3,
                'name' => 'Investment',
                'display_text' => 'Investment',
                'menu_number' => '3',
                'action_type' => 'function',
                'action_value' => 'investment',
                'display_order' => 3,
                'requires_auth' => true,
                'min_balance' => 10.00,
                'user_type' => 'all',
                'is_active' => true
            ],
            [
                'id' => 4,
                'name' => 'Utility Payment',
                'display_text' => 'Utility Payment',
                'menu_number' => '4',
                'action_type' => 'function',
                'action_value' => 'utility_payment',
                'display_order' => 4,
                'requires_auth' => true,
                'min_balance' => 1.00,
                'user_type' => 'all',
                'is_active' => true
            ],
            [
                'id' => 5,
                'name' => 'Statement',
                'display_text' => 'Statement',
                'menu_number' => '5',
                'action_type' => 'function',
                'action_value' => 'statement',
                'display_order' => 5,
                'requires_auth' => true,
                'min_balance' => 0.00,
                'user_type' => 'all',
                'is_active' => true
            ]
        ];
        
        // Filter by balance if provided
        if ($userBalance !== null) {
            $menuItems = array_filter($menuItems, function($item) use ($userBalance) {
                return $item['min_balance'] <= $userBalance;
            });
        }
        
        return array_values($menuItems);
    }

    /**
     * Fallback to legacy menu_items table if menu_nodes is not set up
     */
    private function getMenuItemsFromDb($userId = null, $userBalance = null) {
        if (!$this->pdo) {
            return null;
        }
        try {
            // Check tables exist
            $stmt = $this->pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'menu_items'");
            if ($stmt->fetchColumn() != 1) {
                return null;
            }
            // Determine main category id if table exists
            $mainCategoryId = 1;
            try {
                $hasCategories = $this->pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'menu_categories'");
                if ($hasCategories && $hasCategories->fetchColumn() == 1) {
                    $catStmt = $this->pdo->prepare("SELECT TOP 1 id FROM menu_categories WHERE name = 'Main Menu'");
                    $catStmt->execute();
                    $catId = $catStmt->fetchColumn();
                    if ($catId) { $mainCategoryId = (int)$catId; }
                }
            } catch (Throwable $e) {
                // ignore and default to 1
            }
            // Fetch active items ordered by numeric menu_number then display_order
            $sql = "SELECT id, name, display_text, menu_number, action_type, action_value, display_order, is_active, requires_auth, min_balance, user_type, category_id
                    FROM menu_items 
                    WHERE (category_id = ? OR category_id IS NULL) AND is_active = 1
                    ORDER BY CASE WHEN TRY_CONVERT(INT, menu_number) IS NULL THEN 2147483647 ELSE TRY_CONVERT(INT, menu_number) END ASC, display_order ASC";
            $stmt = $this->pdo->prepare($sql);
            // If category id is not sensible, pass null to not filter by category
            $categoryFilter = $mainCategoryId > 0 ? $mainCategoryId : null;
            $stmt->execute([$categoryFilter]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return [];
            }
            // Filter to known top-level actions if too many rows (avoid including submenu-like rows)
            $allowedActions = ['send_money','buy_airtime_data','investment','utility_payment','statement'];
            $rows = array_values(array_filter($rows, function($row) use ($allowedActions) {
                return in_array($row['action_value'], $allowedActions, true);
            }));
            // Optional balance filter
            if ($userBalance !== null) {
                $rows = array_values(array_filter($rows, function($row) use ($userBalance) {
                    return (float)$row['min_balance'] <= (float)$userBalance;
                }));
            }
            // Dedupe by action_value to avoid duplicates if multiple records exist
            $deduped = [];
            $seen = [];
            foreach ($rows as $row) {
                $key = $row['action_value'];
                if (!isset($seen[$key])) {
                    $deduped[] = $row;
                    $seen[$key] = true;
                }
            }
            // Limit to top 5 items (main menu)
            $rows = array_slice($deduped, 0, 5);
            // Map to common structure
            $mapped = [];
            foreach ($rows as $row) {
                $mapped[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'] ?? $row['display_text'],
                    'display_text' => $row['display_text'],
                    'menu_number' => (string)$row['menu_number'],
                    'action_type' => $row['action_type'],
                    'action_value' => $row['action_value'],
                    'display_order' => (int)$row['display_order'],
                    'requires_auth' => isset($row['requires_auth']) ? (bool)$row['requires_auth'] : false,
                    'min_balance' => isset($row['min_balance']) ? (float)$row['min_balance'] : 0.0,
                    'user_type' => $row['user_type'] ?? 'all',
                    'is_active' => (bool)$row['is_active']
                ];
            }
            return $mapped;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get submenu nodes for a specific parent menu
     */
    public function getSubmenuNodes($parentNodeCode, $userId = null, $userBalance = null) {
        if (!$this->pdo) {
            return null;
        }
        
        try {
            // Ensure tables exist
            $stmt = $this->pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'menu_nodes'");
            if ($stmt->fetchColumn() != 1) {
                return null;
            }
            
            $stmt = $this->pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'menu_subnodes'");
            if ($stmt->fetchColumn() != 1) {
                return null;
            }
            
            // Get parent node id
            $parentStmt = $this->pdo->prepare("SELECT id FROM menu_nodes WHERE code = ?");
            $parentStmt->execute([$parentNodeCode]);
            $parentId = $parentStmt->fetchColumn();
            
            if (!$parentId) {
                return null;
            }
            
            // Fetch active submenu nodes ordered by numeric menu_number then display_order
            $sql = "SELECT id, code, label, menu_number, action_type, action_value, is_active, display_order, metadata
                    FROM menu_subnodes 
                    WHERE parent_node_id = ? AND is_active = 1 
                    ORDER BY CASE WHEN TRY_CONVERT(INT, menu_number) IS NULL THEN 2147483647 ELSE TRY_CONVERT(INT, menu_number) END ASC, display_order ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$parentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!$rows) {
                return [];
            }
            
            // Map to consistent format
            $mapped = [];
            foreach ($rows as $index => $row) {
                $mapped[] = [
                    'id' => (int)$row['id'],
                    'code' => $row['code'],
                    'name' => $row['label'],
                    'display_text' => $row['label'],
                    'menu_number' => (string)$row['menu_number'],
                    'action_type' => $row['action_type'],
                    'action_value' => $row['action_value'],
                    'display_order' => (int)$row['display_order'],
                    'is_active' => (bool)$row['is_active'],
                    'metadata' => $row['metadata'] ? json_decode($row['metadata'], true) : null
                ];
            }
            
            return $mapped;
            
        } catch (Throwable $e) {
            error_log("Error getting submenu nodes: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get menu node by code (main menu or submenu)
     */
    public function getMenuNodeByCode($code, $userId = null, $userBalance = null) {
        if (!$this->pdo) {
            return null;
        }
        
        try {
            // First check main menu nodes
            $stmt = $this->pdo->prepare("SELECT id, code, label, menu_number, action_type, action_value, requires_auth, min_balance, is_active, display_order
                                        FROM menu_nodes WHERE code = ? AND is_active = 1");
            $stmt->execute([$code]);
            $mainNode = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mainNode) {
                // Check balance requirement
                if ($userBalance !== null && (float)$mainNode['min_balance'] > (float)$userBalance) {
                    return null;
                }
                
                return [
                    'id' => (int)$mainNode['id'],
                    'code' => $mainNode['code'],
                    'name' => $mainNode['label'],
                    'display_text' => $mainNode['label'],
                    'menu_number' => (string)$mainNode['menu_number'],
                    'action_type' => $mainNode['action_type'],
                    'action_value' => $mainNode['action_value'],
                    'display_order' => (int)$mainNode['display_order'],
                    'requires_auth' => (bool)$mainNode['requires_auth'],
                    'min_balance' => (float)$mainNode['min_balance'],
                    'is_active' => (bool)$mainNode['is_active'],
                    'node_type' => 'main'
                ];
            }
            
            // Check submenu nodes
            $stmt = $this->pdo->prepare("SELECT id, code, label, menu_number, action_type, action_value, is_active, display_order, metadata
                                        FROM menu_subnodes WHERE code = ? AND is_active = 1");
            $stmt->execute([$code]);
            $subNode = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subNode) {
                return [
                    'id' => (int)$subNode['id'],
                    'code' => $subNode['code'],
                    'name' => $subNode['label'],
                    'display_text' => $subNode['label'],
                    'menu_number' => (string)$subNode['menu_number'],
                    'action_type' => $subNode['action_type'],
                    'action_value' => $subNode['action_value'],
                    'display_order' => (int)$subNode['display_order'],
                    'is_active' => (bool)$subNode['is_active'],
                    'metadata' => $subNode['metadata'] ? json_decode($subNode['metadata'], true) : null,
                    'node_type' => 'submenu'
                ];
            }
            
            return null;
            
        } catch (Throwable $e) {
            error_log("Error getting menu node by code: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build submenu display text
     */
    public function buildSubmenuDisplay($submenuItems, $parentMenuName = '') {
        $display = "";
        if (!empty($parentMenuName)) {
            $display .= "{$parentMenuName}:\n";
        }
        
        if (empty($submenuItems)) {
            $display .= "No options available.\n";
        } else {
            foreach ($submenuItems as $item) {
                $display .= "{$item['menu_number']}. {$item['display_text']}\n";
            }
        }
        
        $display .= "#. Back\n";
        return $display;
    }

    /**
     * Get complete menu hierarchy for a user
     */
    public function getMenuHierarchy($userId = null, $userBalance = null) {
        $mainMenu = $this->getMainMenu($userId, $userBalance);
        $hierarchy = [];
        
        foreach ($mainMenu as $mainItem) {
            $mainItemCode = $this->getMenuCodeFromActionValue($mainItem['action_value']);
            if ($mainItemCode) {
                $submenus = $this->getSubmenuNodes($mainItemCode, $userId, $userBalance);
                $hierarchy[] = [
                    'main_menu' => $mainItem,
                    'submenus' => $submenus ?: []
                ];
            } else {
                $hierarchy[] = [
                    'main_menu' => $mainItem,
                    'submenus' => []
                ];
            }
        }
        
        return $hierarchy;
    }

    /**
     * Helper method to extract menu code from action value
     */
    private function getMenuCodeFromActionValue($actionValue) {
        $codeMap = [
            'send_money' => 'send_money',
            'buy_airtime_data' => 'buy_airtime_data',
            'investment' => 'investment',
            'utility_payment' => 'utility_payment'
        ];
        
        return $codeMap[$actionValue] ?? null;
    }

    private function getMenuNodesFromDb($userId = null, $userBalance = null) {
        if (!$this->pdo) {
            return null;
        }
        try {
            // Ensure table exists
            $stmt = $this->pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'menu_nodes'");
            if ($stmt->fetchColumn() != 1) {
                return null;
            }
            // Get root id
            $rootStmt = $this->pdo->prepare("SELECT id FROM menu_nodes WHERE code = 'root'");
            $rootStmt->execute();
            $rootId = $rootStmt->fetchColumn();
            if (!$rootId) {
                return null;
            }
            // Fetch active children of root ordered by numeric menu_number then display_order
            $sql = "SELECT id, label, menu_number, action_type, action_value, requires_auth, min_balance, is_active, display_order
                    FROM menu_nodes WHERE parent_id = ? AND is_active = 1 
                    ORDER BY CASE WHEN TRY_CONVERT(INT, menu_number) IS NULL THEN 2147483647 ELSE TRY_CONVERT(INT, menu_number) END ASC, display_order ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$rootId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return [];
            }
            // Apply simple balance gating if provided
            if ($userBalance !== null) {
                $rows = array_values(array_filter($rows, function($row) use ($userBalance) {
                    return (float)$row['min_balance'] <= (float)$userBalance;
                }));
            }
            // Map to same shape as old API
            $mapped = [];
            foreach ($rows as $index => $row) {
                $mapped[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['label'],
                    'display_text' => $row['label'],
                    'menu_number' => (string)$row['menu_number'],
                    'action_type' => $row['action_type'],
                    'action_value' => $row['action_value'],
                    'display_order' => (int)$row['display_order'],
                    'requires_auth' => (bool)$row['requires_auth'],
                    'min_balance' => (float)$row['min_balance'],
                    'user_type' => 'all',
                    'is_active' => (bool)$row['is_active']
                ];
            }
            return $mapped;
        } catch (
            Throwable $e
        ) {
            return null;
        }
    }
    
    /** Build menu display text */
    public function buildMenuDisplay($menuItems) {
        $display = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n";
        if (empty($menuItems)) {
            $display .= "No menu items available.\n";
        } else {
            foreach ($menuItems as $item) {
                $display .= "{$item['menu_number']}. {$item['display_text']}\n";
            }
        }
        return $display;
    }
    
    /** Get menu item by number */
    public function getMenuItemByNumber($menuNumber, $userId = null, $userBalance = null) {
        $menuItems = $this->getMainMenu($userId, $userBalance);
        foreach ($menuItems as $item) {
            if ($item['menu_number'] == $menuNumber && $item['is_active']) {
                return $item;
            }
        }
        return null;
    }

    /** Get menu item by number with automatic reordering support */
    public function getMenuItemByNumberRobust($menuNumber, $userId = null, $userBalance = null) {
        if (!$this->pdo) {
            return $this->getMenuItemByNumber($menuNumber, $userId, $userBalance);
        }
        
        try {
            // 1) Prefer legacy menu_items by menu_number (admin changes live here)
            $allowedActions = ['send_money','buy_airtime_data','investment','utility_payment','statement'];
            $placeholders = implode(',', array_fill(0, count($allowedActions), '?'));
            $sql = "SELECT TOP 1 id, name, display_text, menu_number, action_type, action_value, display_order, requires_auth, min_balance, is_active
                    FROM menu_items
                    WHERE is_active = 1 AND menu_number = ? AND action_value IN ($placeholders)
                    ORDER BY display_order ASC, id ASC";
            $params = array_merge([(string)$menuNumber], $allowedActions);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($userBalance !== null && isset($row['min_balance']) && (float)$row['min_balance'] > (float)$userBalance) {
                    return null;
                }
                return [
                    'id' => (int)$row['id'],
                    'name' => $row['name'] ?? $row['display_text'],
                    'display_text' => $row['display_text'],
                    'menu_number' => (string)$row['menu_number'],
                    'action_type' => $row['action_type'],
                    'action_value' => $row['action_value'],
                    'display_order' => (int)$row['display_order'],
                    'requires_auth' => isset($row['requires_auth']) ? (bool)$row['requires_auth'] : false,
                    'min_balance' => isset($row['min_balance']) ? (float)$row['min_balance'] : 0.0,
                    'user_type' => 'all',
                    'is_active' => (bool)$row['is_active']
                ];
            }
            
            // 2) Fallback to hierarchical menu_nodes by number if configured
            $stmt = $this->pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'menu_nodes'");
            if ($stmt->fetchColumn() == 1) {
                $rootStmt = $this->pdo->prepare("SELECT id FROM menu_nodes WHERE code = 'root'");
                $rootStmt->execute();
                $rootId = $rootStmt->fetchColumn();
                if ($rootId) {
                    $sql = "SELECT id, label, menu_number, action_type, action_value, requires_auth, min_balance, is_active, display_order
                            FROM menu_nodes 
                            WHERE parent_id = ? AND menu_number = ? AND is_active = 1 
                            ORDER BY display_order ASC";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$rootId, $menuNumber]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        if ($userBalance !== null && (float)$row['min_balance'] > (float)$userBalance) {
                            return null;
                        }
                        return [
                            'id' => (int)$row['id'],
                            'name' => $row['label'],
                            'display_text' => $row['label'],
                            'menu_number' => (string)$row['menu_number'],
                            'action_type' => $row['action_type'],
                            'action_value' => $row['action_value'],
                            'display_order' => (int)$row['display_order'],
                            'requires_auth' => (bool)$row['requires_auth'],
                            'min_balance' => (float)$row['min_balance'],
                            'user_type' => 'all',
                            'is_active' => (bool)$row['is_active']
                        ];
                    }
                }
            }
            
            return null;
            
        } catch (Throwable $e) {
            error_log("Error in getMenuItemByNumberRobust: " . $e->getMessage());
            return $this->getMenuItemByNumber($menuNumber, $userId, $userBalance);
        }
    }
    
    /** Get submenu item by number */
    public function getSubmenuItemByNumber($menuNumber, $submenuItems) {
        foreach ($submenuItems as $item) {
            if ($item['menu_number'] == $menuNumber && $item['is_active']) {
                return $item;
            }
        }
        return null;
    }
    
    /** Log menu usage (no-op for now) */
    public function logMenuUsage($menuItemId, $userId = null, $sessionId = null) {
        return true;
    }
}
?>
