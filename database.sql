-- Create the database if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'USSDServiceDB')
BEGIN
    CREATE DATABASE USSDServiceDB;
END
GO

USE USSDServiceDB;
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
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATETIME DEFAULT GETDATE(),
        status VARCHAR(20) DEFAULT 'completed',
        CONSTRAINT FK_UtilityPayments_Users FOREIGN KEY (user_id) REFERENCES users(id)
    );
END
GO

-- Insert a test user if not exists
IF NOT EXISTS (SELECT * FROM users WHERE phone = '0200000000')
BEGIN
    INSERT INTO users (phone, pin, balance) VALUES ('0200000000', '1234', 900.00);
END
GO 