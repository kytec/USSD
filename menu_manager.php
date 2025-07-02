<?php
require_once 'db_connect.php';

class MenuManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get main menu items for a user
     */
    public function getMainMenu($userId = null, $userBalance = 0) {
        $sql = "
            SELECT 
                mi.id,
                mi.name,
                mi.display_text,
                mi.menu_number,
                mi.action_type,
                mi.action_value,
                mi.display_order,
                mi.requires_auth,
                mi.min_balance,
                mi.user_type,
                mi.is_active
            FROM menu_items mi
            WHERE mi.is_active = 1
            AND mi.category_id = 1
            AND mi.min_balance <= ?
            ORDER BY mi.display_order ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userBalance]);
        
        $menuItems = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Check user-specific preferences if user is logged in
            if ($userId) {
                $userPref = $this->getUserMenuPreference($userId, $row['id']);
                if ($userPref && !$userPref['is_visible']) {
                    continue; // Skip this menu item for this user
                }
            }
            
            $menuItems[] = $row;
        }
        
        return $menuItems;
    }
    
    /**
     * Get user-specific menu preference
     */
    private function getUserMenuPreference($userId, $menuItemId) {
        $sql = "SELECT * FROM user_menu_preferences WHERE user_id = ? AND menu_item_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $menuItemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Build menu display text
     */
    public function buildMenuDisplay($menuItems) {
        $display = "Welcome to BRASSICA-PAY USSD Service\n\nPlease enter your choice:\n";
        
        foreach ($menuItems as $item) {
            $display .= "{$item['menu_number']}. {$item['display_text']}\n";
        }
        
        return $display;
    }
    
    /**
     * Get menu item by number
     */
    public function getMenuItemByNumber($menuNumber) {
        $sql = "SELECT * FROM menu_items WHERE menu_number = ? AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$menuNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log menu usage for analytics
     */
    public function logMenuUsage($menuItemId, $userId = null, $sessionId = null) {
        $sql = "
            INSERT INTO menu_usage_logs (user_id, menu_item_id, session_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId, $menuItemId, $sessionId, $ipAddress, $userAgent]);
    }
    
    /**
     * Add new menu item
     */
    public function addMenuItem($data) {
        $sql = "
            INSERT INTO menu_items (
                category_id, name, display_text, menu_number, action_type, 
                action_value, display_order, requires_auth, min_balance, user_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['category_id'], 
            $data['name'], 
            $data['display_text'], 
            $data['menu_number'], 
            $data['action_type'], 
            $data['action_value'], 
            $data['display_order'], 
            $data['requires_auth'], 
            $data['min_balance'], 
            $data['user_type']
        ]);
    }
    
    /**
     * Update menu item
     */
    public function updateMenuItem($id, $data) {
        $sql = "
            UPDATE menu_items SET 
                category_id = ?, name = ?, display_text = ?, menu_number = ?, 
                action_type = ?, action_value = ?, display_order = ?, 
                requires_auth = ?, min_balance = ?, user_type = ?, 
                is_active = ?, updated_at = GETDATE()
            WHERE id = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['category_id'], 
            $data['name'], 
            $data['display_text'], 
            $data['menu_number'], 
            $data['action_type'], 
            $data['action_value'], 
            $data['display_order'], 
            $data['requires_auth'], 
            $data['min_balance'], 
            $data['user_type'],
            $data['is_active'],
            $id
        ]);
    }
    
    /**
     * Delete menu item
     */
    public function deleteMenuItem($id) {
        $sql = "DELETE FROM menu_items WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Get menu usage statistics
     */
    public function getMenuUsageStats($days = 30) {
        $sql = "
            SELECT 
                mi.name,
                mi.display_text,
                COUNT(*) as usage_count
            FROM menu_usage_logs mul
            JOIN menu_items mi ON mul.menu_item_id = mi.id
            WHERE mul.accessed_at >= DATEADD(day, -?, GETDATE())
            GROUP BY mi.id, mi.name, mi.display_text
            ORDER BY usage_count DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);
        
        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Set user menu preference
     */
    public function setUserMenuPreference($userId, $menuItemId, $isVisible, $displayOrder = 0) {
        // Check if preference exists
        $existing = $this->getUserMenuPreference($userId, $menuItemId);
        
        if ($existing) {
            // Update existing preference
            $sql = "
                UPDATE user_menu_preferences 
                SET is_visible = ?, display_order = ?, updated_at = GETDATE()
                WHERE user_id = ? AND menu_item_id = ?
            ";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$isVisible, $displayOrder, $userId, $menuItemId]);
        } else {
            // Insert new preference
            $sql = "
                INSERT INTO user_menu_preferences (user_id, menu_item_id, is_visible, display_order)
                VALUES (?, ?, ?, ?)
            ";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId, $menuItemId, $isVisible, $displayOrder]);
        }
    }
}
?>
