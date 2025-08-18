-- =====================================================
-- USSD MENU REORDERING SCRIPTS
-- =====================================================
-- This script provides multiple methods to safely reorder menu options
-- without breaking submenu links or functionality.
-- =====================================================

-- =====================================================
-- METHOD 1: REORDER MAIN MENU OPTIONS
-- =====================================================

-- View current main menu order
SELECT 
    id,
    code,
    label,
    menu_number,
    display_order,
    action_type,
    action_value
FROM menu_nodes 
WHERE parent_id IS NULL 
ORDER BY display_order ASC;

-- Reorder main menu options by updating display_order
-- Example: Move "Send Money" to position 1, "Buy Airtime/Data" to position 2, etc.

-- Option A: Update individual items
UPDATE menu_nodes 
SET display_order = 1 
WHERE code = 'send_money' AND parent_id IS NULL;

UPDATE menu_nodes 
SET display_order = 2 
WHERE code = 'buy_airtime_data' AND parent_id IS NULL;

UPDATE menu_nodes 
SET display_order = 3 
WHERE code = 'investment' AND parent_id IS NULL;

UPDATE menu_nodes 
SET display_order = 4 
WHERE code = 'utility_payment' AND parent_id IS NULL;

UPDATE menu_nodes 
SET display_order = 5 
WHERE code = 'statement' AND parent_id IS NULL;

-- Option B: Bulk reorder using CASE statement
UPDATE menu_nodes 
SET display_order = CASE 
    WHEN code = 'send_money' THEN 1
    WHEN code = 'buy_airtime_data' THEN 2
    WHEN code = 'investment' THEN 3
    WHEN code = 'utility_payment' THEN 4
    WHEN code = 'statement' THEN 5
    ELSE display_order
END
WHERE parent_id IS NULL;

-- =====================================================
-- METHOD 2: REORDER SUBMENU OPTIONS
-- =====================================================

-- View current submenu order for a specific main menu
-- Example: View Send Money submenus
SELECT 
    ms.id,
    ms.code,
    ms.label,
    ms.menu_number,
    ms.display_order,
    ms.action_type,
    ms.action_value,
    mn.label as parent_menu
FROM menu_subnodes ms
JOIN menu_nodes mn ON ms.parent_node_id = mn.id
WHERE mn.code = 'send_money'
ORDER BY ms.display_order ASC;

-- Reorder submenu options for Send Money
UPDATE menu_subnodes 
SET display_order = CASE 
    WHEN code = 'bank_transfer' THEN 1
    WHEN code = 'mobile_money' THEN 2
    WHEN code = 'card_payment' THEN 3
    ELSE display_order
END
WHERE parent_node_id = (SELECT id FROM menu_nodes WHERE code = 'send_money' AND parent_id IS NULL);

-- Reorder submenu options for Buy Airtime/Data
UPDATE menu_subnodes 
SET display_order = CASE 
    WHEN code = 'airtime' THEN 1
    WHEN code = 'data_bundle' THEN 2
    WHEN code = 'data_plan' THEN 3
    ELSE display_order
END
WHERE parent_node_id = (SELECT id FROM menu_nodes WHERE code = 'buy_airtime_data' AND parent_id IS NULL);

-- =====================================================
-- METHOD 3: ADVANCED REORDERING WITH ROW_NUMBER()
-- =====================================================

-- This method automatically assigns sequential display_order values
-- based on a custom ordering you specify

-- Reorder main menu using ROW_NUMBER() and custom priority
WITH MenuOrder AS (
    SELECT 
        id,
        code,
        ROW_NUMBER() OVER (ORDER BY 
            CASE code
                WHEN 'send_money' THEN 1
                WHEN 'buy_airtime_data' THEN 2
                WHEN 'investment' THEN 3
                WHEN 'utility_payment' THEN 4
                WHEN 'statement' THEN 5
                ELSE 999
            END
        ) as new_order
    FROM menu_nodes 
    WHERE parent_id IS NULL
)
UPDATE menu_nodes 
SET display_order = mo.new_order
FROM menu_nodes mn
JOIN MenuOrder mo ON mn.id = mo.id;

-- =====================================================
-- METHOD 4: SWAP TWO MENU ITEMS
-- =====================================================

-- Swap positions of two main menu items
-- Example: Swap "Send Money" and "Buy Airtime/Data"

-- Step 1: Temporarily set one to a high number
UPDATE menu_nodes 
SET display_order = 999 
WHERE code = 'send_money' AND parent_id IS NULL;

-- Step 2: Move the other to the first position
UPDATE menu_nodes 
SET display_order = 1 
WHERE code = 'buy_airtime_data' AND parent_id IS NULL;

-- Step 3: Move the first to the second position
UPDATE menu_nodes 
SET display_order = 2 
WHERE code = 'send_money' AND parent_id IS NULL;

-- =====================================================
-- METHOD 5: RESET AND REBUILD ALL DISPLAY ORDERS
-- =====================================================

-- Reset all main menu display orders to sequential numbers
WITH OrderedMenus AS (
    SELECT 
        id,
        ROW_NUMBER() OVER (ORDER BY 
            CASE code
                WHEN 'send_money' THEN 1
                WHEN 'buy_airtime_data' THEN 2
                WHEN 'investment' THEN 3
                WHEN 'utility_payment' THEN 4
                WHEN 'statement' THEN 5
                ELSE 999
            END
        ) as new_order
    FROM menu_nodes 
    WHERE parent_id IS NULL
)
UPDATE menu_nodes 
SET display_order = om.new_order
FROM menu_nodes mn
JOIN OrderedMenus om ON mn.id = om.id;

-- Reset all submenu display orders to sequential numbers
WITH OrderedSubmenus AS (
    SELECT 
        ms.id,
        ROW_NUMBER() OVER (PARTITION BY ms.parent_node_id ORDER BY 
            CASE ms.code
                -- Send Money submenus
                WHEN 'bank_transfer' THEN 1
                WHEN 'mobile_money' THEN 2
                WHEN 'card_payment' THEN 3
                -- Buy Airtime/Data submenus
                WHEN 'airtime' THEN 1
                WHEN 'data_bundle' THEN 2
                WHEN 'data_plan' THEN 3
                -- Investment submenus
                WHEN 'savings' THEN 1
                WHEN 'fixed_deposit' THEN 2
                WHEN 'mutual_fund' THEN 3
                -- Utility Payment submenus
                WHEN 'electricity' THEN 1
                WHEN 'water' THEN 2
                WHEN 'gas' THEN 3
                ELSE 999
            END
        ) as new_order
    FROM menu_subnodes ms
    JOIN menu_nodes mn ON ms.parent_node_id = mn.id
)
UPDATE menu_subnodes 
SET display_order = os.new_order
FROM menu_subnodes ms
JOIN OrderedSubmenus os ON ms.id = os.id;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Verify main menu order after reordering
SELECT 
    display_order,
    code,
    label,
    menu_number,
    action_type,
    action_value
FROM menu_nodes 
WHERE parent_id IS NULL 
ORDER BY display_order ASC;

-- Verify submenu order for each main menu
SELECT 
    mn.code as main_menu,
    mn.label as main_menu_label,
    ms.display_order,
    ms.code as submenu_code,
    ms.label as submenu_label,
    ms.menu_number,
    ms.action_type
FROM menu_nodes mn
LEFT JOIN menu_subnodes ms ON mn.id = ms.parent_node_id
WHERE mn.parent_id IS NULL 
ORDER BY mn.display_order ASC, ms.display_order ASC;

-- =====================================================
-- SAFETY CHECKS
-- =====================================================

-- Check for duplicate display_order values
SELECT 
    'main_menu' as menu_type,
    display_order,
    COUNT(*) as count,
    STRING_AGG(code, ', ') as codes
FROM menu_nodes 
WHERE parent_id IS NULL 
GROUP BY display_order 
HAVING COUNT(*) > 1

UNION ALL

SELECT 
    'submenu' as menu_type,
    display_order,
    COUNT(*) as count,
    STRING_AGG(code, ', ') as codes
FROM menu_subnodes 
GROUP BY display_order, parent_node_id
HAVING COUNT(*) > 1;

-- Check for gaps in display_order
SELECT 
    'main_menu' as menu_type,
    display_order,
    code,
    label
FROM menu_nodes 
WHERE parent_id IS NULL 
ORDER BY display_order;

-- =====================================================
-- ROLLBACK SCRIPT (if needed)
-- =====================================================

-- If you need to rollback to original order, you can restore from backup
-- or use this script to reset to a known good state

-- Example: Reset to original order
/*
UPDATE menu_nodes 
SET display_order = CASE 
    WHEN code = 'send_money' THEN 1
    WHEN code = 'buy_airtime_data' THEN 2
    WHEN code = 'investment' THEN 3
    WHEN code = 'utility_payment' THEN 4
    WHEN code = 'statement' THEN 5
    ELSE display_order
END
WHERE parent_id IS NULL;
*/

-- =====================================================
-- USAGE INSTRUCTIONS
-- =====================================================

/*
1. BACKUP YOUR DATABASE BEFORE RUNNING THESE SCRIPTS
2. Choose the method that best fits your needs:
   - Method 1: Simple individual updates
   - Method 2: Submenu reordering
   - Method 3: Advanced with ROW_NUMBER()
   - Method 4: Swap specific items
   - Method 5: Complete reset and rebuild

3. Always run verification queries after reordering
4. Test the USSD flow to ensure everything works correctly
5. The submenu links will remain intact because they use 'code' 
   and 'action_value' for linking, not display_order

6. If you need to reorder frequently, consider creating a stored procedure
   or using the admin interface in admin_menu.php
*/ 