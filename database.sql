-- Create the database if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'USSDServiceDB')
BEGIN
    CREATE DATABASE USSDServiceDB;
END
GO

USE USSDServiceDB;
GO

-- Create menu_categories table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'menu_categories')
BEGIN
    CREATE TABLE menu_categories (
        id INT IDENTITY(1,1) PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active BIT DEFAULT 1,
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    );
END
GO

-- Create menu_items table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'menu_items')
BEGIN
    CREATE TABLE menu_items (
        id INT IDENTITY(1,1) PRIMARY KEY,
        category_id INT NULL,
        name VARCHAR(100) NOT NULL,
        display_text VARCHAR(200) NOT NULL,
        menu_number VARCHAR(10) NOT NULL,
        action_type VARCHAR(50) NOT NULL, -- 'page', 'function', 'external'
        action_value VARCHAR(200) NOT NULL, -- page name, function name, or external URL
        display_order INT NOT NULL DEFAULT 0,
        is_active BIT DEFAULT 1,
        requires_auth BIT DEFAULT 0,
        min_balance DECIMAL(10,2) DEFAULT 0.00,
        user_type VARCHAR(20) DEFAULT 'all', -- 'all', 'premium', 'basic'
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_MenuItems_Categories FOREIGN KEY (category_id) REFERENCES menu_categories(id)
    );
END
GO

-- Create user_menu_preferences table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'user_menu_preferences')
BEGIN
    CREATE TABLE user_menu_preferences (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        menu_item_id INT NOT NULL,
        is_visible BIT DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_UserMenuPrefs_Users FOREIGN KEY (user_id) REFERENCES users(id),
        CONSTRAINT FK_UserMenuPrefs_MenuItems FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
    );
END
GO

-- Create menu_usage_logs table for analytics
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'menu_usage_logs')
BEGIN
    CREATE TABLE menu_usage_logs (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NULL,
        menu_item_id INT NOT NULL,
        session_id VARCHAR(100) NULL,
        accessed_at DATETIME DEFAULT GETDATE(),
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        CONSTRAINT FK_MenuUsageLogs_Users FOREIGN KEY (user_id) REFERENCES users(id),
        CONSTRAINT FK_MenuUsageLogs_MenuItems FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
    );
END
GO

-- Insert default menu categories
IF NOT EXISTS (SELECT * FROM menu_categories WHERE name = 'Main Menu')
BEGIN
    INSERT INTO menu_categories (name, display_order) VALUES ('Main Menu', 1);
END
GO

-- Insert default menu items
IF NOT EXISTS (SELECT * FROM menu_items WHERE name = 'Send Money')
BEGIN
    INSERT INTO menu_items (category_id, name, display_text, menu_number, action_type, action_value, display_order, requires_auth, min_balance) 
    VALUES (1, 'Send Money', 'Send Money', '1', 'function', 'send_money', 1, 1, 1.00);
END
GO

IF NOT EXISTS (SELECT * FROM menu_items WHERE name = 'Buy Airtime/Data')
BEGIN
    INSERT INTO menu_items (category_id, name, display_text, menu_number, action_type, action_value, display_order, requires_auth, min_balance) 
    VALUES (1, 'Buy Airtime/Data', 'Buy Airtime/Data', '2', 'function', 'buy_airtime_data', 2, 1, 1.00);
END
GO

IF NOT EXISTS (SELECT * FROM menu_items WHERE name = 'Investment')
BEGIN
    INSERT INTO menu_items (category_id, name, display_text, menu_number, action_type, action_value, display_order, requires_auth, min_balance) 
    VALUES (1, 'Investment', 'Investment', '3', 'function', 'investment', 3, 1, 10.00);
END
GO

IF NOT EXISTS (SELECT * FROM menu_items WHERE name = 'Utility Payment')
BEGIN
    INSERT INTO menu_items (category_id, name, display_text, menu_number, action_type, action_value, display_order, requires_auth, min_balance) 
    VALUES (1, 'Utility Payment', 'Utility Payment', '4', 'function', 'utility_payment', 4, 1, 1.00);
END
GO

IF NOT EXISTS (SELECT * FROM menu_items WHERE name = 'Statement')
BEGIN
    INSERT INTO menu_items (category_id, name, display_text, menu_number, action_type, action_value, display_order, requires_auth, min_balance) 
    VALUES (1, 'Statement', 'Statement', '5', 'function', 'statement', 5, 1, 0.00);
END
GO

-- Create users table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'users')
BEGIN
    CREATE TABLE users (
        id INT IDENTITY(1,1) PRIMARY KEY,
        phone VARCHAR(10) UNIQUE NOT NULL,
        pin VARCHAR(255) NOT NULL,
        balance DECIMAL(10,2) DEFAULT 900.00,
        created_at DATETIME DEFAULT GETDATE()
    );
END
GO

-- Create transactions table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'transactions')
BEGIN
    CREATE TABLE transactions (
        id INT IDENTITY(1,1) PRIMARY KEY,
        sender_id INT NULL,
        recipient_phone VARCHAR(10) NOT NULL,
        amount DECIMAL(10,2) NULL,
        transaction_date DATETIME DEFAULT GETDATE(),
        status VARCHAR(20) DEFAULT 'pending',
        CONSTRAINT FK_Transactions_Users FOREIGN KEY (sender_id) REFERENCES users(id)
    );
END
GO

-- Create airtime_purchases table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'airtime_purchases')
BEGIN
    CREATE TABLE airtime_purchases (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        phone_number VARCHAR(10) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        purchase_date DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_AirtimePurchases_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Create data_purchases table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'data_purchases')
BEGIN
    CREATE TABLE data_purchases (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        phone_number VARCHAR(10) NOT NULL,
        data_bundle VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        purchase_date DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_DataPurchases_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Create meter_transactions table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'meter_transactions')
BEGIN
    CREATE TABLE meter_transactions (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        meter_number VARCHAR(20) NOT NULL,
        meter_type VARCHAR(10) NOT NULL, -- 'Prepaid' or 'Postpaid'
        amount DECIMAL(10,2) NOT NULL,
        transaction_date DATETIME DEFAULT GETDATE(),
        status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
        provider_reference VARCHAR(50) NULL, -- Reference from network provider
        provider_response TEXT NULL, -- Response from network provider
        CONSTRAINT FK_MeterTransactions_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Create fixed_deposits table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'fixed_deposits')
BEGIN
    CREATE TABLE fixed_deposits (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        duration_months INT NOT NULL,
        interest_rate DECIMAL(5,2) NOT NULL,
        start_date DATETIME DEFAULT GETDATE(),
        maturity_date DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        CONSTRAINT FK_FixedDeposits_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Create treasury_bills_investments table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'treasury_bills_investments')
BEGIN
    CREATE TABLE treasury_bills_investments (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        purchase_date DATETIME DEFAULT GETDATE(),
        maturity_date DATETIME NOT NULL,
        interest_rate DECIMAL(5,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        CONSTRAINT FK_TreasuryBills_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Create mutual_funds_investments table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'mutual_funds_investments')
BEGIN
    CREATE TABLE mutual_funds_investments (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        investment_date DATETIME DEFAULT GETDATE(),
        fund_name VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        CONSTRAINT FK_MutualFunds_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Create utility_payments table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'utility_payments')
BEGIN
    CREATE TABLE utility_payments (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        utility_type VARCHAR(20) NOT NULL, -- 'ECG' or 'Water'
        account_number VARCHAR(20) NOT NULL,
        meter_type VARCHAR(10) NULL, -- 'Prepaid' or 'Postpaid' for ECG
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATETIME DEFAULT GETDATE(),
        status VARCHAR(20) DEFAULT 'completed',
        CONSTRAINT FK_UtilityPayments_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Add meter_type column to existing utility_payments table if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('utility_payments') AND name = 'meter_type')
BEGIN
    ALTER TABLE utility_payments ADD meter_type VARCHAR(10) NULL;
END
GO

-- Insert a test user if not exists
IF NOT EXISTS (SELECT * FROM users WHERE phone = '0200000000')
BEGIN
    INSERT INTO users (phone, pin, balance) VALUES ('0200000000', '1234', 900.00);
END
GO 

-- Menu nodes table for hierarchical, admin-manageable menus
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'menu_nodes')
BEGIN
    CREATE TABLE menu_nodes (
        id INT IDENTITY(1,1) PRIMARY KEY,
        parent_id INT NULL,
        code VARCHAR(64) NOT NULL UNIQUE,
        label VARCHAR(200) NOT NULL,
        menu_number VARCHAR(5) NOT NULL,
        action_type VARCHAR(20) NOT NULL,       -- navigate, function, external
        action_value VARCHAR(200) NULL,
        requires_auth BIT DEFAULT 0,
        min_balance DECIMAL(10,2) DEFAULT 0.00,
        is_active BIT DEFAULT 1,
        display_order INT NOT NULL DEFAULT 0,
        metadata NVARCHAR(MAX) NULL,
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE()
    );
END
GO

-- Seed main menu nodes (root level)
IF NOT EXISTS (SELECT * FROM menu_nodes WHERE code = 'root')
BEGIN
    INSERT INTO menu_nodes (parent_id, code, label, menu_number, action_type, action_value, display_order)
    VALUES (NULL, 'root', 'Main Menu', '0', 'navigate', NULL, 0);
END
GO

DECLARE @rootId INT;
SELECT @rootId = id FROM menu_nodes WHERE code = 'root';

-- Send Money
IF NOT EXISTS (SELECT * FROM menu_nodes WHERE code = 'send_money')
BEGIN
    INSERT INTO menu_nodes (parent_id, code, label, menu_number, action_type, action_value, display_order, requires_auth, min_balance)
    VALUES (@rootId, 'send_money', 'Send Money', '1', 'function', 'send_money', 1, 1, 1.00);
END
GO
-- Buy Airtime/Data
IF NOT EXISTS (SELECT * FROM menu_nodes WHERE code = 'buy_airtime_data')
BEGIN
    INSERT INTO menu_nodes (parent_id, code, label, menu_number, action_type, action_value, display_order, requires_auth, min_balance)
    VALUES (@rootId, 'buy_airtime_data', 'Buy Airtime/Data', '2', 'function', 'buy_airtime_data', 2, 1, 1.00);
END
GO
-- Investment
IF NOT EXISTS (SELECT * FROM menu_nodes WHERE code = 'investment')
BEGIN
    INSERT INTO menu_nodes (parent_id, code, label, menu_number, action_type, action_value, display_order, requires_auth, min_balance)
    VALUES (@rootId, 'investment', 'Investment', '3', 'function', 'investment', 3, 1, 10.00);
END
GO
-- Utility Payment
IF NOT EXISTS (SELECT * FROM menu_nodes WHERE code = 'utility_payment')
BEGIN
    INSERT INTO menu_nodes (parent_id, code, label, menu_number, action_type, action_value, display_order, requires_auth, min_balance)
    VALUES (@rootId, 'utility_payment', 'Utility Payment', '4', 'function', 'utility_payment', 4, 1, 1.00);
END
GO
-- Statement
IF NOT EXISTS (SELECT * FROM menu_nodes WHERE code = 'statement')
BEGIN
    INSERT INTO menu_nodes (parent_id, code, label, menu_number, action_type, action_value, display_order)
    VALUES (@rootId, 'statement', 'Statement', '5', 'function', 'statement', 5);
END
GO 

-- Create submenu table (children of menu_nodes)
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'menu_subnodes')
BEGIN
    CREATE TABLE menu_subnodes (
        id INT IDENTITY(1,1) PRIMARY KEY,
        parent_node_id INT NOT NULL,                 -- FK to menu_nodes(id)
        code VARCHAR(64) NOT NULL UNIQUE,
        label VARCHAR(200) NOT NULL,
        menu_number VARCHAR(5) NOT NULL,
        action_type VARCHAR(20) NOT NULL,           -- 'navigate','state','external'
        action_value VARCHAR(200) NULL,
        is_active BIT DEFAULT 1,
        display_order INT NOT NULL DEFAULT 0,
        metadata NVARCHAR(MAX) NULL,                -- JSON: e.g., {"network":"MTN","next_display":"Enter MTN MobileMoney number:\n#. Back"}
        created_at DATETIME DEFAULT GETDATE(),
        updated_at DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_SubNodes_Parent FOREIGN KEY (parent_node_id) REFERENCES menu_nodes(id)
    );
END
GO

-- Seed submenus for 'send_money'
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'sm_mtn')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='send_money'), 'sm_mtn', 'MTN MobileMoney', '1', 'state', 'enter_recipient', 1, '{"network":"MTN","next_display":"Enter MTN MobileMoney number:\n#. Back"}');
END
GO
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'sm_telecel')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='send_money'), 'sm_telecel', 'Telecel Cash', '2', 'state', 'enter_recipient', 2, '{"network":"Telecel","next_display":"Enter Telecel Cash number:\n#. Back"}');
END
GO
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'sm_airtel')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='send_money'), 'sm_airtel', 'AirtelTigo Cash', '3', 'state', 'enter_recipient', 3, '{"network":"AirtelTigo","next_display":"Enter AirtelTigo Cash number:\n#. Back"}');
END
GO
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'sm_bank')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='send_money'), 'sm_bank', 'Bank Account', '4', 'state', 'select_bank', 4, '{"next_display":"Select Bank:\n1. GCB\n2. Ecobank\n3. GTBank\n4. Prudential Bank\n5. UBA\n#. Back"}');
END
GO

-- Seed submenus for 'buy_airtime_data'
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'bad_airtime')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='buy_airtime_data'), 'bad_airtime', 'Buy Airtime', '1', 'state', 'buy_airtime_data', 1, '{"service_type":"airtime","service_step":1,"next_display":"Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back"}');
END
GO
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'bad_data')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='buy_airtime_data'), 'bad_data', 'Buy Data', '2', 'state', 'buy_airtime_data', 2, '{"service_type":"data","service_step":1,"next_display":"Buy Airtime/Data:\n1. Buy Airtime\n2. Buy Data\n#. Back"}');
END
GO

-- Seed submenus for 'investment'
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'inv_fd')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='investment'), 'inv_fd', 'Fixed Deposit', '1', 'state', 'fixed_deposit', 1, '{"next_display":"Fixed Deposit Options:\n1. 3 Months (5% p.a.)\n2. 6 Months (7% p.a.)\n3. 12 Months (10% p.a.)\n#. Back\n\nSelect duration:"}');
END
GO
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'inv_tbills')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='investment'), 'inv_tbills', 'Treasury Bills', '2', 'state', 'treasury_bills', 2, '{"next_display":"Enter amount to invest in Treasury Bills:\n#. Back"}');
END
GO
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'inv_mutual')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='investment'), 'inv_mutual', 'Mutual Funds', '3', 'state', 'mutual_funds', 3, '{"next_display":"Enter amount to invest in Mutual Funds:\n#. Back"}');
END
GO

-- Seed submenus for 'utility_payment'
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'utl_ecg')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='utility_payment'), 'utl_ecg', 'ECG (Electricity)', '1', 'state', 'select_ecg_meter_type', 1, '{"next_display":"Select ECG Meter Type:\n1. Prepaid\n2. Postpaid\n#. Back"}');
END
GO
IF NOT EXISTS (SELECT 1 FROM menu_subnodes WHERE code = 'utl_water')
BEGIN
    INSERT INTO menu_subnodes (parent_node_id, code, label, menu_number, action_type, action_value, display_order, metadata)
    VALUES ((SELECT id FROM menu_nodes WHERE code='utility_payment'), 'utl_water', 'Water', '2', 'state', 'enter_utility_account', 2, '{"utility_type":"Water","next_display":"Enter your Water account number:\n#. Back"}');
END
GO 