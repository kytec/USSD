-- Create the database if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'ussd_db')
BEGIN
    CREATE DATABASE ussd_db;
END
GO

USE ussd_db;
GO

-- Create users table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'users')
BEGIN
    CREATE TABLE users (
        id INT IDENTITY(1,1) PRIMARY KEY,
        phone VARCHAR(10) UNIQUE NOT NULL,
        pin VARCHAR(255) NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT GETDATE()
    );
END
GO

-- Create transactions table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'transactions')
BEGIN
    CREATE TABLE transactions (
        id INT IDENTITY(1,1) PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_phone VARCHAR(10) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        transaction_date DATETIME DEFAULT GETDATE(),
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

-- Insert a test user if not exists
IF NOT EXISTS (SELECT * FROM users WHERE phone = '0200000000')
BEGIN
    INSERT INTO users (phone, pin, balance) VALUES ('0200000000', '1234', 1000.00);
END
GO 