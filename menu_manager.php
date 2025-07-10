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
     * Get main menu items for a user
     */
    public function getMainMenu($userId = null, $userBalance = null) {
        // Simple hardcoded menu items to avoid database dependency
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
     * Get user-specific menu preference
     */
    private function getUserMenuPreference($userId, $menuItemId) {
        // Simple implementation without database - return null (no preferences)
        return null;
    }
    
    /**
     * Build menu display text
     */
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
    
    /**
     * Get menu item by number
     */
    public function getMenuItemByNumber($menuNumber) {
        $menuItems = $this->getMainMenu();
        foreach ($menuItems as $item) {
            if ($item['menu_number'] == $menuNumber && $item['is_active']) {
                return $item;
            }
        }
        return null;
    }
    
    /**
     * Log menu usage for analytics
     */
    public function logMenuUsage($menuItemId, $userId = null, $sessionId = null) {
        // Simple logging without database - just return true
        return true;
    }
    
    // Database-dependent functions removed for simplicity
    // The menu system now works with hardcoded menu items
}
?>
