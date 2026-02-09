-- Database structure for BranchKenya
-- Generated from README.md specification

CREATE DATABASE IF NOT EXISTS branchkenya;
USE branchkenya;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    nin VARCHAR(20) NOT NULL,
    age INT DEFAULT NULL,
    residential VARCHAR(255) DEFAULT NULL,
    occupation VARCHAR(255) DEFAULT NULL,
    next_kin VARCHAR(255) DEFAULT NULL,
    phone_disbursment VARCHAR(20) DEFAULT NULL,
    bank_account VARCHAR(100) DEFAULT NULL,
    bank_number VARCHAR(50) DEFAULT NULL,
    current_salary DECIMAL(15, 2) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    approved_loan TINYINT(1) DEFAULT 0,
    joined DATETIME DEFAULT CURRENT_TIMESTAMP,
    isadmin TINYINT(1) DEFAULT 0,
    verification_code VARCHAR(100) DEFAULT NULL,
    session_id VARCHAR(255) DEFAULT NULL
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    tid INT AUTO_INCREMENT PRIMARY KEY,
    tuid INT NOT NULL,
    tphone VARCHAR(20),
    tamount DECIMAL(15, 2) NOT NULL,
    ttype VARCHAR(50) NOT NULL,
    tdesc TEXT,
    tref VARCHAR(100),
    tstatus TINYINT(1) DEFAULT 0,
    tcreated DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tuid) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_uid INT NOT NULL,
    message TEXT NOT NULL,
    viewed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ref_uid) REFERENCES users(id) ON DELETE CASCADE
);

-- Loans table
CREATE TABLE IF NOT EXISTS loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_uid INT NOT NULL,
    loan_amount DECIMAL(15, 2) NOT NULL,
    loan_fee DECIMAL(15, 2) DEFAULT 0,
    loan_duration INT COMMENT 'Duration in days/months',
    loan_status TINYINT(1) DEFAULT 0 COMMENT '0=pending, 1=approved, 2=pending_disbursement',
    loan_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_uid) REFERENCES users(id) ON DELETE CASCADE
);

-- Activities table
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description TEXT NOT NULL COMMENT 'Activity type: Loan, Deposit, etc.',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Site configuration table
CREATE TABLE IF NOT EXISTS site (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    sms INT NOT NULL DEFAULT 0
);

INSERT INTO site (id, sms) VALUES ('AA11', 50);

-- SMS Purchases table
CREATE TABLE IF NOT EXISTS sms_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(100) NOT NULL,
    units INT NOT NULL,
    cost_per_unit DECIMAL(10, 2) NOT NULL DEFAULT 0.20,
    amount_paid DECIMAL(15, 2) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    reference VARCHAR(100) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=completed, 2=failed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Add indexes for better query performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_transactions_tuid ON transactions(tuid);
CREATE INDEX idx_transactions_tref ON transactions(tref);
CREATE INDEX idx_notifications_ref_uid ON notifications(ref_uid);
CREATE INDEX idx_loans_loan_uid ON loans(loan_uid);
CREATE INDEX idx_loans_status ON loans(loan_status);
CREATE INDEX idx_sms_purchases_ref ON sms_purchases(reference);
CREATE INDEX idx_sms_purchases_status ON sms_purchases(status);
