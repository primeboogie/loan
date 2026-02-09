# Branch Emergency Loans

**Kenya's Fastest Loan Application Platform - Get Your Loan in Less Than 3 Hours!**

A complete loan management system with M-Pesa STK Push integration, email notifications, and admin dashboard.

---

## Table of Contents

- [Features](#features)
- [Client Flow](#client-flow)
- [API Routes](#api-routes)
- [Database Schema](#database-schema)
- [Installation](#installation)
- [Configuration](#configuration)

---

## Features

- **Instant Loans**: KES 5,000 to KES 1,000,000
- **Fast Approval**: Get your loan in less than 3 hours
- **M-Pesa Integration**: STK Push for payments
- **Email Notifications**: Professional light blue themed emails
- **SMS Notifications**: Bulk SMS capabilities
- **Admin Dashboard**: Complete loan and user management
- **Flexible Repayment**: 1 to 24 months duration

---

## Client Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENT REGISTRATION FLOW                      │
└─────────────────────────────────────────────────────────────────────┘

Step 1: BASIC REGISTRATION
    │
    │  POST /api/basicdetails
    │  ├── fullname, email, phone, nin
    │  └── Returns: session_id, user_id
    │
    ▼
Step 2: OTP VERIFICATION
    │
    │  POST /api/otpverification (verify)
    │  GET  /api/otpverification (resend)
    │  ├── session_id, otp
    │  └── Returns: verified status
    │
    ▼
Step 3: QUALIFICATION DETAILS
    │
    │  POST /api/qualificationdetails
    │  ├── session_id, age, residential, occupation
    │  ├── next_kin, phone_disbursment, bank_account
    │  ├── bank_number, current_salary
    │  └── Returns: pre_qualified amount (min/max)
    │
    ▼
Step 4: KYC VERIFICATION (KES 99)
    │
    │  POST /api/approvekyc
    │  ├── session_id, phone
    │  ├── M-Pesa STK Push sent
    │  └── Returns: verified status, ref
    │
    ▼
Step 5: LOAN APPLICATION
    │
    │  POST /api/loanapply
    │  ├── session_id, amount_requested, duration, phone
    │  ├── Processing fee: 2% (min KES 100)
    │  └── Returns: loan_id, monthly_payment, total_repayment
    │
    ▼
    ✓ LOAN APPROVED - Disbursement within 3 hours!
```

---

## API Routes

### Base URL
```
http://localhost/branchkenya
```

---

### Client Endpoints

#### 1. Basic Registration
```http
POST /api/basicdetails
Content-Type: application/json
```

**Request Body:**
```json
{
    "fullname": "John Kamau Mwangi",
    "email": "john.kamau@example.com",
    "phone": "0712345678",
    "nin": "12345678"
}
```

**Success Response (201):**
```json
{
    "status": 201,
    "resultcode": true,
    "msg": "Registration successful! Check your email for verification.",
    "data": {
        "session_id": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
        "user_id": 1,
        "email": "john.kamau@example.com"
    }
}
```

---

#### 2. OTP Verification

**Verify OTP:**
```http
POST /api/otpverification
Content-Type: application/json
```

**Request Body:**
```json
{
    "session_id": "your_session_id",
    "otp": "123456"
}
```

**Resend OTP:**
```http
GET /api/otpverification
Content-Type: application/json

Body: { "session_id": "your_session_id" }
```

**Success Response (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "Account verified successfully!",
    "data": {
        "session_id": "your_session_id",
        "verified": true
    }
}
```

---

#### 3. Qualification Details
```http
POST /api/qualificationdetails
Content-Type: application/json
```

**Request Body:**
```json
{
    "session_id": "your_session_id",
    "age": 30,
    "residential": "Westlands, Nairobi",
    "occupation": "Software Developer",
    "next_kin": "Jane Wanjiku - 0723456789",
    "phone_disbursment": "0712345678",
    "bank_account": "Equity Bank",
    "bank_number": "0123456789012",
    "current_salary": 85000
}
```

**Success Response (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "Profile updated successfully!",
    "data": {
        "session_id": "your_session_id",
        "pre_qualified": true,
        "min_loan": 42500,
        "max_loan": 255000,
        "next_step": "kyc_verification"
    }
}
```

**Loan Calculation:**
- Minimum Loan: MAX(2,000, salary × 0.5)
- Maximum Loan: MIN(1,000,000, salary × 3)

---

#### 4. KYC Verification Payment
```http
POST /api/approvekyc
Content-Type: application/json
```

**Request Body:**
```json
{
    "session_id": "your_session_id",
    "phone": "0712345678"
}
```

**STK Push Initiated (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "Please check your phone and enter your M-Pesa PIN to complete the verification payment.",
    "data": {
        "session_id": "your_session_id",
        "ref": "TXN1234ABCD",
        "status": "pending"
    }
}
```

**Payment Successful (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "Verification complete! You're ready to apply for a loan.",
    "data": {
        "session_id": "your_session_id",
        "verified": true,
        "ref": "TXN1234ABCD",
        "next_step": "loan_application"
    }
}
```

---

#### 5. Loan Application
```http
POST /api/loanapply
Content-Type: application/json
```

**Request Body:**
```json
{
    "session_id": "your_session_id",
    "amount_requested": 50000,
    "duration": 3,
    "phone": "0712345678"
}
```

**Success Response (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "Loan approved! Your funds will be disbursed within 3 hours.",
    "data": {
        "session_id": "your_session_id",
        "loan_id": 1,
        "loan_amount": 50000,
        "loan_fee": 7500,
        "duration": 3,
        "monthly_payment": 19166.67,
        "total_repayment": 57500,
        "status": "pending_disbursement",
        "ref": "TXN5678EFGH"
    }
}
```

**Fee Calculation:**
- Processing Fee: MAX(100, amount × 0.02) - Paid via STK Push
- Interest Rate: 5% per month × duration
- Total Repayment: loan_amount + (loan_amount × 0.05 × duration)

---

#### 6. Login / Get Account
```http
POST /api/grabaccount
Content-Type: application/json
```

**Request Body:**
```json
{
    "nin": "12345678",
    "email": "john.kamau@example.com"
}
```

**Success Response (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "Account retrieved successfully.",
    "data": {
        "session_id": "new_session_id",
        "user": {
            "id": 1,
            "full_name": "John Kamau Mwangi",
            "email": "john.kamau@example.com",
            "phone": "+254712345678",
            "kyc_verified": true,
            "joined": "2024-01-15 10:30:00"
        },
        "loans": [...],
        "transactions": [...],
        "notifications": [...],
        "loan_summary": {
            "total_loans": 1,
            "active_loans": 1,
            "total_borrowed": 50000
        }
    }
}
```

---

### Admin Endpoints

All admin endpoints require the `X-Admin-Key` header.

```http
X-Admin-Key: your_admin_api_key
```

#### Get All Users
```http
GET /api/alluser
```

#### Get Dashboard Statistics
```http
GET /api/adminstats
X-Admin-Key: your_admin_api_key
```

#### Get Pending Loans
```http
GET /api/getPendingLoans
X-Admin-Key: your_admin_api_key
```

#### Process Loan
```http
POST /api/processLoan
X-Admin-Key: your_admin_api_key
Content-Type: application/json

{
    "loan_id": 1,
    "action": "disburse"  // approve, disburse, reject
}
```

#### Send Bulk Notifications
```http
POST /api/adminnotify
X-Admin-Key: your_admin_api_key
Content-Type: application/json

{
    "message": "Dear {name}, your loan is ready!",
    "subject": "Loan Update",
    "target": "all",      // all, applied, not_applied
    "method": "both"      // email, sms, both
}
```

#### Get All Deposits
```http
GET /api/getAllDeposits
X-Admin-Key: your_admin_api_key
```

**Success Response (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "Deposits retrieved successfully.",
    "data": {
        "deposits": [
            {
                "id": 1,
                "user": {
                    "name": "John Kamau Mwangi",
                    "email": "john.kamau@example.com",
                    "phone": "+254712345678"
                },
                "phone_used": "+254712345678",
                "amount": 99,
                "description": "KYC Verification Fee",
                "reference": "TXN1234ABCD",
                "status": "Completed",
                "date": "2024-01-15 10:45:00"
            }
        ],
        "summary": {
            "total_deposits": 150,
            "total_completed": 140,
            "total_pending": 10,
            "total_amount": 250000,
            "today_amount": 5000
        }
    }
}
```

#### Get SMS Packages & Balance
```http
GET /api/getSmsPackages
X-Admin-Key: your_admin_api_key
```

**Success Response (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "SMS packages retrieved.",
    "data": {
        "sms_balance": 50,
        "packages": [
            {"id": 1, "name": "250 SMS Units", "units": 250, "cost_per_unit": 0.20, "price": 50},
            {"id": 2, "name": "1000 SMS Units", "units": 1000, "cost_per_unit": 0.20, "price": 200},
            {"id": 3, "name": "2500 SMS Units", "units": 2500, "cost_per_unit": 0.20, "price": 500},
            {"id": 4, "name": "5000 SMS Units", "units": 5000, "cost_per_unit": 0.20, "price": 1000}
        ],
        "recent_purchases": []
    }
}
```

#### Purchase SMS Credits
```http
POST /api/purchaseSms
X-Admin-Key: your_admin_api_key
Content-Type: application/json

{
    "phone": "0712345678",
    "package_id": 2
}
```

**Success Response (200):**
```json
{
    "status": 200,
    "resultcode": true,
    "msg": "SMS credits purchased successfully! 1000 units added.",
    "data": {
        "package": "1000 SMS Units",
        "units_added": 1000,
        "amount_paid": 200,
        "reference": "TXN_ABCD1234",
        "new_balance": 1050
    }
}
```

**SMS Packages:**

| Package | Units | Cost/Unit | Price (KES) |
|---------|-------|-----------|-------------|
| 250 SMS Units | 250 | 0.20 | 50 |
| 1000 SMS Units | 1,000 | 0.20 | 200 |
| 2500 SMS Units | 2,500 | 0.20 | 500 |
| 5000 SMS Units | 5,000 | 0.20 | 1000 |

---

## Database Schema

### Users Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| full_name | VARCHAR(255) | User's full name |
| email | VARCHAR(255) | Email address (unique) |
| phone | VARCHAR(20) | Phone number with country code |
| nin | VARCHAR(9) | National ID Number |
| age | INT | User's age |
| residential | VARCHAR(255) | Residential address |
| occupation | VARCHAR(255) | Occupation/Employer |
| next_kin | VARCHAR(255) | Next of kin details |
| phone_disbursment | VARCHAR(20) | M-Pesa disbursement number |
| bank_account | VARCHAR(100) | Bank name |
| bank_number | VARCHAR(50) | Bank account number |
| current_salary | DECIMAL(15,2) | Monthly salary |
| active | TINYINT(1) | Account active status |
| approved_loan | TINYINT(1) | KYC verified status |
| joined | DATETIME | Registration date |
| isadmin | TINYINT(1) | Admin flag |
| verification_code | VARCHAR(100) | OTP code |
| session_id | VARCHAR(255) | Session token |

### Transactions Table
| Column | Type | Description |
|--------|------|-------------|
| tid | INT | Primary key |
| tuid | INT | User ID (FK) |
| tphone | VARCHAR(20) | Transaction phone |
| tamount | DECIMAL(15,2) | Amount |
| ttype | VARCHAR(50) | Type (deposit/withdrawal) |
| tdesc | TEXT | Description |
| tref | VARCHAR(100) | Transaction reference |
| tstatus | TINYINT(1) | Status (0=pending, 1=complete) |
| tcreated | DATETIME | Created date |

### Loans Table
| Column | Type | Description |
|--------|------|-------------|
| loan_id | INT | Primary key |
| loan_uid | INT | User ID (FK) |
| loan_amount | DECIMAL(15,2) | Loan amount |
| loan_fee | DECIMAL(15,2) | Interest/fees |
| loan_duration | INT | Duration in months |
| loan_status | TINYINT | Status code |
| loan_created_at | DATETIME | Created date |

**Loan Status Codes:**
- 0 = Pending Review
- 1 = Approved
- 2 = Pending Disbursement
- 3 = Disbursed
- 4 = Fully Paid / Rejected

### Notifications Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| ref_uid | INT | User ID (FK) |
| message | TEXT | Notification message |
| viewed | TINYINT(1) | Read status |
| created_at | DATETIME | Created date |

### Activities Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| description | TEXT | Activity description |
| created_at | DATETIME | Created date |

### Site Table
| Column | Type | Description |
|--------|------|-------------|
| id | VARCHAR(255) | Primary key (default: 'AA11') |
| sms | INT | Current SMS credit balance |

### SMS Purchases Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| package_name | VARCHAR(100) | Package name purchased |
| units | INT | Number of SMS units |
| cost_per_unit | DECIMAL(10,2) | Cost per unit (KES 0.20) |
| amount_paid | DECIMAL(15,2) | Total amount paid |
| phone | VARCHAR(20) | Phone used for payment |
| reference | VARCHAR(100) | Transaction reference |
| status | TINYINT(1) | 0=pending, 1=completed, 2=failed |
| created_at | DATETIME | Purchase date |

---

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-repo/branchkenya.git
   cd branchkenya
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp config/.env.example config/.env
   # Edit .env with your credentials
   ```

4. **Import database**
   ```bash
   mysql -u root -p < database.sql
   ```

5. **Configure web server**
   - Point document root to project folder
   - Ensure mod_rewrite is enabled

---

## Configuration

Create a `.env` file in the `config/` directory:

```env
# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=your_password
DB_NAME=branchkenya

# Admin
adminname=Admin
adminemail=admin@yourdomain.com
company=Branch Emergency Loans
domain=https://yourdomain.com
backend=https://api.yourdomain.com

# Developer
devname=Developer
devemail=dev@yourdomain.com
```

---

## Postman Collection

Import the `Branch_Emergency_Loans.postman_collection.json` file into Postman to test all API endpoints.

---

## Support

For support or inquiries, contact:
- Email: support@branchemergencyloans.co.ke
- Phone: +254 700 000 000

---

**Branch Emergency Loans** - Your Trusted Financial Partner

*Get Your Loan in Less Than 3 Hours!*
