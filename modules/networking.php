<?php
require_once "admin/networking.php";

/**
 * Branch Emergency Loans - Client Registration & Loan Application Module
 * Step-by-step registration and loan processing functions
 */

// Step 1: Basic Details Registration - Initial user signup
function basicdetails()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    global $conn;
    global $today;
    global $admin;

    $inputs = jDecode(['fullname', 'email', 'phone', 'nin']);

    $errors = false;

    // Validate Full Name
    $full_name = trim($inputs['fullname'] ?? '');
    if (strlen($full_name) < 3) {
        notify(1, "Please enter your full name (at least 3 characters).", 506, 1);
        $errors = true;
    }

    // Validate Email
    $email = strtolower(trim($inputs['email'] ?? ''));
    if (!verifyEmail($email)) {
        notify(1, "Please provide a valid email address.", 507, 1);
        $errors = true;
    }

    // Validate Phone Number (Kenyan format)
    $phone = preg_replace('/\D/', '', $inputs['phone'] ?? '');
    if (strlen($phone) < 9 || strlen($phone) > 12) {
        notify(1, "Please enter a valid Kenyan phone number.", 508, 1);
        $errors = true;
    }

    // Validate NIN (National ID Number - 8-9 digits for Kenya)
    $nin = preg_replace('/\D/', '', $inputs['nin'] ?? '');
    if (strlen($nin) < 7 || strlen($nin) > 9) {
        notify(1, "Please enter a valid National ID Number.", 509, 1);
        $errors = true;
    }

    // Format phone number with country code
    $dial = "+254";
    $formattedPhone = $dial . substr($phone, -9);

    // Check for existing records
    if (check("email", "use", $email)['res']) {
        notify(1, "This email is already registered. Please login or use a different email.", 510, 1);
        $errors = true;
    }

    if (check("phone", "use", $formattedPhone)['res']) {
        notify(1, "This phone number is already registered.", 511, 1);
        $errors = true;
    }

    if (check("nin", "use", $nin)['res']) {
        notify(1, "This National ID is already associated with an account.", 512, 1);
        $errors = true;
    }

    if ($errors) {
        return sendJsonResponse(422);
    }

    // Generate unique session ID for this registration flow
    $session_id = generateSessionId(32);

    // Generate verification code
    $verification_code = generateOTP();

    // Insert new user record
    $sql = "INSERT INTO users (full_name, email, phone, nin, active, approved_loan, joined, isadmin, verification_code, session_id)
            VALUES (?, ?, ?, ?, 1, 0, NOW(), 0, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        notify(1, "Database error. Please try again later.", 500, 1);
        error_log("MySQL Prepare Error: " . $conn->error . " | SQL: " . $sql);
        return sendJsonResponse(500, false, "Database error occurred.");
    }

    $stmt->bind_param("ssssss", $full_name, $email, $formattedPhone, $nin, $verification_code, $session_id);

    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        $stmt->close();

        // Send welcome email with verification code
        $emailMsg = "
            <p>Welcome to <strong>Branch Emergency Loans</strong>! We're thrilled to have you join our community of satisfied customers.</p>

            <div style='background: linear-gradient(135deg, #e8f4fc 0%, #d4edfc 100%); border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0;'>
                <p style='margin: 0; color: #666;'>Your Verification Code:</p>
                <p style='font-size: 32px; font-weight: bold; color: #0077b6; letter-spacing: 5px; margin: 10px 0;'>$verification_code</p>
                <p style='margin: 0; font-size: 12px; color: #888;'>This code expires in 30 minutes</p>
            </div>

            <p>With Branch Emergency Loans, you can access:</p>
            <ul style='color: #444; line-height: 2;'>
                <li><strong>Instant Loans</strong> from KES 5,000 to KES 1,000,000</li>
                <li><strong>Fast Approval</strong> - Get your loan in less than 3 hours</li>
                <li><strong>Flexible Repayment</strong> - Choose from 1 to 24 months</li>
                <li><strong>No Collateral Required</strong> - Your trust is enough</li>
            </ul>

            <p style='color: #0077b6; font-weight: 600;'>Complete your verification to check your eligible loan amount!</p>
        ";

        sendmail($full_name, $email, $emailMsg, ["Welcome to Branch Emergency Loans", "Verify Your Account"]);

        // Add notification
        insertNotification($userId, "Welcome to Branch Emergency Loans! Complete your profile to access instant loans up to KES 1,000,000.");

        // Log activity
        insertActivity("New user registration: $full_name ($email)");

        notify(2, "Registration successful! Please check your email for the verification code.", 0, 1);

        return sendJsonResponse(201, true, "Registration successful! Check your email for verification.", [
            'session_id' => $session_id,
            // 'user_id' => $userId,
            'email' => $email
        ]);
    }

    $stmt->close();
    notify(1, "Registration failed. Please try again.", 500, 1);
    return sendJsonResponse(500);
}


// Step 2: OTP Verification
function otpverification()
{
    global $conn;
    global $admin;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Resend OTP
        $inputs = jDecode(['session_id']);
        $session_id = $inputs['session_id'] ?? '';

        $sessionCheck = verifySession($session_id);
        if (!$sessionCheck['valid']) {
            notify(1, "Invalid session. Please start the registration process again.", 401, 1);
            return sendJsonResponse(401);
        }

        $user = $sessionCheck['user'];
        $newOTP = generateOTP();

        // Update verification code
        updates("use", "verification_code = '$newOTP'", "id = '{$user['id']}'");

        // Send new OTP email
        $emailMsg = "
            <p>You requested a new verification code for your Branch Emergency Loans account.</p>

            <div style='background: linear-gradient(135deg, #e8f4fc 0%, #d4edfc 100%); border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0;'>
                <p style='margin: 0; color: #666;'>Your New Verification Code:</p>
                <p style='font-size: 32px; font-weight: bold; color: #0077b6; letter-spacing: 5px; margin: 10px 0;'>$newOTP</p>
                <p style='margin: 0; font-size: 12px; color: #888;'>This code expires in 30 minutes</p>
            </div>

            <p>If you didn't request this code, please ignore this email or contact our support team.</p>
        ";

        sendmail($user['full_name'], $user['email'], $emailMsg, ["Verification Code", "Your New OTP"]);

        notify(2, "A new verification code has been sent to your email.", 0, 1);
        return sendJsonResponse(200, true, "New verification code sent to your email.");
    }

    // Verify OTP (POST request)
    $inputs = jDecode(['session_id', 'otp']);
    $session_id = $inputs['session_id'] ?? '';
    $otp = $inputs['otp'] ?? '';

    $sessionCheck = verifySession($session_id);
    if (!$sessionCheck['valid']) {
        notify(1, "Invalid session. Please start the registration process again.", 401, 1);
        return sendJsonResponse(401, false, "s", $inputs);
    }

    $user = $sessionCheck['user'];

    if ($user['verification_code'] !== $otp) {
        notify(1, "Invalid verification code. Please check and try again.", 400, 1);
        return sendJsonResponse(400, false, "Invalid verification code.");
    }

    // Clear verification code after successful verification
    updates("use", "verification_code = NULL, active = 1", "id = '{$user['id']}'");

    insertNotification($user['id'], "Your account has been verified successfully! You're one step closer to accessing your emergency loan.");

    notify(2, "Account verified successfully! Continue to complete your profile.", 0, 1);
    return sendJsonResponse(200, true, "Account verified successfully!", [
        'session_id' => $session_id,
        'verified' => true
    ]);
}


// Step 3: Qualification Details - Complete user profile
function qualificationdetails()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    global $conn;
    global $admin;

    $inputs = jDecode(['session_id', 'age', 'residential', 'occupation', 'next_kin', 'phone_disbursment',  'current_salary']);

    $session_id = $inputs['session_id'] ?? '';

    $sessionCheck = verifySession($session_id);
    if (!$sessionCheck['valid']) {
        notify(1, "Session expired. Please login again to continue.", 401, 1);
        return sendJsonResponse(401);
    }

    $user = $sessionCheck['user'];
    $userId = $user['id'];

    // Validate required fields
    $age = intval($inputs['age'] ?? 0);
    if ($age < 18 || $age > 70) {
        notify(1, "You must be between 18 and 70 years old to apply for a loan.", 400, 1);
        return sendJsonResponse(422);
    }

    $residential = trim($inputs['residential'] ?? '');
    $occupation = trim($inputs['occupation'] ?? '');
    $next_kin = trim($inputs['next_kin'] ?? '');
    $phone_disbursment = preg_replace('/\D/', '', $inputs['phone_disbursment'] ?? '');
    $bank_account = trim($inputs['bank_account'] ?? 'N/A');
    $bank_number = trim($inputs['bank_number'] ?? 'N/A');
    $current_salary = floatval($inputs['current_salary'] ?? 0);

    // Format disbursement phone
    if (!empty($phone_disbursment)) {
        $phone_disbursment = "+254" . substr($phone_disbursment, -9);
    }

    // Update user profile
    $sql = "UPDATE users SET
        age = ?,
        residential = ?,
        occupation = ?,
        next_kin = ?,
        phone_disbursment = ?,
        bank_account = ?,
        bank_number = ?,
        current_salary = ?
        WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        notify(1, "Database error. Please try again later.", 500, 1);
        error_log("MySQL Prepare Error: " . $conn->error . " | SQL: " . $sql);
        return sendJsonResponse(500, false, "Database error occurred.");
    }

    $stmt->bind_param("issssssdi", $age, $residential, $occupation, $next_kin, $phone_disbursment, $bank_account, $bank_number, $current_salary, $userId);

    // Calculate pre-qualified loan amount based on salary
    $minLoan = max(7000, $current_salary * 0.1);
    $maxLoan = min(1000000, $current_salary * 8);

    if ($stmt->execute()) {
        $stmt->close();

        // Send congratulations email
        $emailMsg = "
            <p><strong>Congratulations!</strong> Your profile has been updated successfully.</p>

            <div style='background: linear-gradient(135deg, #e8f4fc 0%, #d4edfc 100%); border-radius: 10px; padding: 25px; text-align: center; margin: 20px 0;'>
                <p style='margin: 0 0 10px 0; color: #666; font-size: 14px;'>Based on your profile, you are pre-qualified for:</p>
                <p style='font-size: 28px; font-weight: bold; color: #0077b6; margin: 0;'>KES " . number_format($minLoan) . " - " . number_format($maxLoan) . "</p>
                <p style='margin: 15px 0 0 0; font-size: 12px; color: #888;'>Final amount subject to verification</p>
            </div>

            <p style='color: #444;'>To receive your loan disbursement, please complete the <strong>KYC verification</strong> by paying a small verification fee of <strong>KES 99</strong>.</p>

            <p style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>
                <strong>Why the verification fee?</strong><br>
                <span style='font-size: 13px;'>This one-time fee covers identity verification, document processing, and ensures we serve only serious applicants. It helps us maintain fast processing times and secure your loan.</span>
            </p>
        ";

        sendmail($user['full_name'], $user['email'], $emailMsg, ["Pre-Qualification Complete!", "You're Eligible for a Loan"]);

        // Send congratulations SMS
        sendsms($user['phone'], "Congratulations {$user['full_name']}! You're pre-qualified for a loan of KES " . number_format($minLoan) . " - KES " . number_format($maxLoan) . ". Complete KYC verification (KES 99) to proceed. - Branch Emergency Loans");

        // Add notification
        insertNotification($userId, "Great news! You're pre-qualified for a loan of KES " . number_format($minLoan) . " - " . number_format($maxLoan) . ". Complete KYC verification to proceed.");

        // Log activity
        insertActivity("Profile completed for user: {$user['full_name']} - Pre-qualified for KES " . number_format($minLoan) . " - " . number_format($maxLoan));

        notify(2, "Profile updated! You're pre-qualified for KES " . number_format($minLoan) . " - " . number_format($maxLoan) . ".", 0, 1);

        return sendJsonResponse(200, true, "Profile updated successfully!", [
            'session_id' => $session_id,
            'pre_qualified' => true,
            'min_loan' => $minLoan,
            'max_loan' => $maxLoan,
            'next_step' => 'kyc_verification'
        ]);
    }

    $stmt->close();
    notify(1, "Failed to update profile. Please try again.", 500, 1);
    return sendJsonResponse(500);
}


// Step 4: KYC Verification Payment (KES 99)
function approvekyc()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    global $conn;
    global $admin;

    $inputs = jDecode(['phone', 'session_id']);

    $session_id = $inputs['session_id'] ?? '';
    $phone = $inputs['phone'] ?? '';

    $sessionCheck = verifySession($session_id);
    if (!$sessionCheck['valid']) {
        notify(1, "Session expired. Please login again.", 401, 1);
        return sendJsonResponse(401);
    }

    $user = $sessionCheck['user'];
    $userId = $user['id'];
    $full_name = $user['full_name'];

    // KYC verification fee
    $amount = 99;

    // Initiate STK push
    $stkResponse = stkpush($phone, $amount, $full_name, $userId);

    if ($stkResponse['success']) {
        if (isset($stkResponse['pending']) && $stkResponse['pending']) {
            // STK push sent, waiting for user to complete
            return sendJsonResponse(200, true, "Please check your phone and enter your M-Pesa PIN to complete the verification payment.", [
                'session_id' => $session_id,
                'ref' => $stkResponse['ref'],
                'status' => 'pending'
            ]);
        }

        // Payment successful
        updates("use", "approved_loan = 1", "id = '$userId'");

        // Send confirmation email
        $emailMsg = "
            <p><strong>Excellent!</strong> Your KYC verification payment has been received.</p>

            <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; border: 1px solid #28a745;'>
                <p style='font-size: 18px; color: #155724; margin: 0;'>Payment Confirmed</p>
                <p style='font-size: 24px; font-weight: bold; color: #155724; margin: 10px 0;'>KES 99.00</p>
                <p style='font-size: 12px; color: #155724; margin: 0;'>Reference: {$stkResponse['ref']}</p>
            </div>

            <p style='color: #444;'>Your account is now <strong>fully verified</strong>! You can now apply for loans and receive instant disbursement to your registered phone number or bank account.</p>

            <div style='background: #e8f4fc; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <p style='margin: 0; font-weight: 600; color: #0077b6;'>What's Next?</p>
                <ul style='margin: 10px 0; padding-left: 20px; color: #444;'>
                    <li>Apply for your emergency loan</li>
                    <li>Choose your preferred repayment period</li>
                    <li>Receive funds in less than 3 hours!</li>
                </ul>
            </div>
        ";

        sendmail($full_name, $user['email'], $emailMsg, ["KYC Verification Complete!", "You're Ready to Apply"]);

        // Send KYC success SMS with loan range
        $current_salary = floatval($user['current_salary'] ?? 0);
        $minLoan = max(2000, $current_salary * 0.5);
        $maxLoan = min(1000000, $current_salary * 3);
        sendsms($user['phone'], "KYC Verified! $full_name, you can now apply for loans from KES " . number_format($minLoan) . " to KES " . number_format($maxLoan) . ". Apply now and get funds in less than 3 hours! - Branch Emergency Loans");

        insertNotification($userId, "KYC verification successful! You're now eligible to apply for loans up to KES " . number_format($maxLoan) . ". Apply now and receive funds in less than 3 hours!");

        insertActivity("KYC verification completed for: $full_name (User ID: $userId)");

        notify(2, "KYC verification successful! You can now apply for your loan.", 0, 1);

        return sendJsonResponse(200, true, "Verification complete! You're ready to apply for a loan.", [
            'session_id' => $session_id,
            'verified' => true,
            'ref' => $stkResponse['ref'],
            'next_step' => 'loan_application'
        ]);
    }

    // Payment failed
    notify(1, $stkResponse['message'] ?? "Payment failed. Please try again.", 400, 1);
    return sendJsonResponse(400, false, $stkResponse['message'] ?? "Payment failed.");
}


// Step 5: Loan Application
function loanapply()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    global $conn;
    global $admin;

    $inputs = jDecode(['amount_requested', 'duration', 'phone', 'session_id']);

    $session_id = $inputs['session_id'] ?? '';
    $amount_requested = floatval($inputs['amount_requested'] ?? 0);
    $duration = intval($inputs['duration'] ?? 1); // months
    $phone = $inputs['phone'] ?? '';

    $sessionCheck = verifySession($session_id);
    if (!$sessionCheck['valid']) {
        notify(1, "Session expired. Please login again.", 401, 1);
        return sendJsonResponse(401);
    }

    $user = $sessionCheck['user'];
    $userId = $user['id'];
    $full_name = $user['full_name'];

    // Check if user has completed KYC
    if (!$user['approved_loan']) {
        notify(1, "Please complete KYC verification before applying for a loan.", 400, 1);
        return sendJsonResponse(400, false, "KYC verification required.");
    }

    // Calculate pre-qualified loan amount based on salary
    $current_salary = floatval($user['current_salary'] ?? 0);
    $minLoan = max(7000, $current_salary * 0.1);
    $maxLoan = min(1000000, $current_salary * 8);

    // Validate loan amount against pre-qualified range
    if ($amount_requested < $minLoan || $amount_requested > $maxLoan) {
        notify(1, "Loan amount must be between KES " . number_format($minLoan) . " and KES " . number_format($maxLoan) . ".", 400, 1);
        return sendJsonResponse(422);
    }

    // Validate duration (1-24 months)
    if ($duration < 1 || $duration > 24) {
        notify(1, "Loan duration must be between 1 and 24 months.", 400, 1);
        return sendJsonResponse(422);
    }

    // Calculate loan processing fee (2% of loan amount, minimum KES 100)
    $processingFee = max(100, $amount_requested * 0.01);

    // Calculate total loan fee (5% interest)
    $interestRate = 0.05 * $duration; // 5% per month
    $loanFee = $amount_requested * $interestRate;

    // Initiate processing fee payment via STK push
    $stkResponse = stkpush($phone, $processingFee, $full_name, $userId);

    if ($stkResponse['success']) {
        if (isset($stkResponse['pending']) && $stkResponse['pending']) {
            return sendJsonResponse(200, true, "Please check your phone to complete the processing fee payment of KES " . number_format($processingFee) . ".", [
                'session_id' => $session_id,
                'ref' => $stkResponse['ref'],
                'processing_fee' => $processingFee,
                'status' => 'pending'
            ]);
        }

        // Payment successful - create loan record
        $sql = "INSERT INTO loans (loan_uid, loan_amount, loan_fee, loan_duration, loan_status, loan_created_at)
                VALUES (?, ?, ?, ?, 2, NOW())";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            notify(1, "Failed to create loan record. Please contact support.", 500, 1);
            error_log("MySQL Prepare Error: " . $conn->error . " | SQL: " . $sql);
            return sendJsonResponse(500, false, "Database error occurred.");
        }

        $stmt->bind_param("iddi", $userId, $amount_requested, $loanFee, $duration);
        $stmt->execute();
        $loanId = $conn->insert_id;
        $stmt->close();

        // Calculate monthly repayment
        $totalRepayment = $amount_requested + $loanFee;
        $monthlyRepayment = $totalRepayment / $duration;

        // Send loan confirmation email
        $emailMsg = "
            <p><strong>Congratulations, $full_name!</strong></p>
            <p>Your loan application has been <strong>approved</strong> and is now pending disbursement.</p>

            <div style='background: linear-gradient(135deg, #e8f4fc 0%, #d4edfc 100%); border-radius: 10px; padding: 20px; margin: 20px 0;'>
                <h3 style='color: #0077b6; margin: 0 0 15px 0; text-align: center;'>Loan Summary</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ccc;'>Loan Amount:</td><td style='padding: 10px; border-bottom: 1px solid #ccc; text-align: right; font-weight: bold;'>KES " . number_format($amount_requested) . "</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ccc;'>Interest & Fees:</td><td style='padding: 10px; border-bottom: 1px solid #ccc; text-align: right;'>KES " . number_format($loanFee) . "</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ccc;'>Duration:</td><td style='padding: 10px; border-bottom: 1px solid #ccc; text-align: right;'>$duration Month(s)</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ccc;'>Monthly Payment:</td><td style='padding: 10px; border-bottom: 1px solid #ccc; text-align: right;'>KES " . number_format($monthlyRepayment, 2) . "</td></tr>
                    <tr style='background: #0077b6; color: white;'><td style='padding: 12px; font-weight: bold;'>Total Repayment:</td><td style='padding: 12px; text-align: right; font-weight: bold;'>KES " . number_format($totalRepayment) . "</td></tr>
                </table>
            </div>

            <div style='background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin: 15px 0;'>
                <p style='margin: 0; color: #155724;'><strong>Disbursement Notice:</strong> Your loan of KES " . number_format($amount_requested) . " will be disbursed to your registered M-Pesa number or bank account within <strong>3 hours</strong>.</p>
            </div>

            <p style='color: #666; font-size: 13px;'>Reference: {$stkResponse['ref']} | Loan ID: $loanId</p>
        ";

        sendmail($full_name, $user['email'], $emailMsg, ["Loan Approved!", "Your Loan is Being Processed"]);

        // Add notification
        insertNotification($userId, "Your loan of KES " . number_format($amount_requested) . " has been approved! Disbursement will be completed within 3 hours. Loan ID: $loanId");

        // Log activity
        insertActivity("Loan application approved - User: $full_name, Amount: KES " . number_format($amount_requested) . ", Duration: $duration months");

        // Notify admin
        $adminMsg = "
            <h3>New Loan Application Approved</h3>
            <p><strong>Customer:</strong> $full_name</p>
            <p><strong>Loan Amount:</strong> KES " . number_format($amount_requested) . "</p>
            <p><strong>Duration:</strong> $duration months</p>
            <p><strong>Processing Fee Paid:</strong> KES " . number_format($processingFee) . "</p>
            <p><strong>Status:</strong> Pending Disbursement</p>
            <p><strong>Loan ID:</strong> $loanId</p>
        ";
        // sendmail($admin['name'], $admin['email'], $adminMsg, "New Loan Application - KES " . number_format($amount_requested));

        notify(2, "Loan approved! KES " . number_format($amount_requested) . " will be disbursed within 3 hours.", 0, 1);

        return sendJsonResponse(200, true, "Loan approved! Your funds will be disbursed within 3 hours.", [
            'session_id' => $session_id,
            'loan_id' => $loanId,
            'loan_amount' => $amount_requested,
            'loan_fee' => $loanFee,
            'duration' => $duration,
            'monthly_payment' => $monthlyRepayment,
            'total_repayment' => $totalRepayment,
            'status' => 'pending_disbursement',
            'ref' => $stkResponse['ref']
        ]);
    }

    // Payment failed
    notify(1, $stkResponse['message'] ?? "Processing fee payment failed. Please try again.", 400, 1);
    return sendJsonResponse(400, false, $stkResponse['message'] ?? "Payment failed.");
}


// Account Details - Get user account information
function grabaccount()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    global $conn;

    $inputs = jDecode(['nin', 'email']);

    $nin = preg_replace('/\D/', '', $inputs['nin'] ?? '');
    $email = strtolower(trim($inputs['email'] ?? ''));

    // Validate inputs
    if (empty($nin) || empty($email)) {
        notify(1, "Please provide both your National ID and email address.", 400, 1);
        return sendJsonResponse(422);
    }

    // Find user by NIN and email combination
    $sql = "SELECT * FROM users WHERE nin = ? AND email = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        notify(1, "Database error. Please try again later.", 500, 1);
        error_log("MySQL Prepare Error: " . $conn->error . " | SQL: " . $sql);
        return sendJsonResponse(500, false, "Database error occurred.");
    }

    $stmt->bind_param("ss", $nin, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        notify(1, "No account found with the provided details. Please check and try again.", 404, 1);
        return sendJsonResponse(404, false, "Account not found.");
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    $userId = $user['id'];

    // Get user's loans
    $loans = [];
    $loansSql = "SELECT * FROM loans WHERE loan_uid = ? ORDER BY loan_created_at DESC";
    $loansQuery = $conn->prepare($loansSql);

    if ($loansQuery !== false) {
        $loansQuery->bind_param("i", $userId);
        $loansQuery->execute();
        $loansResult = $loansQuery->get_result();

        while ($loan = $loansResult->fetch_assoc()) {
            $statusText = match ((int)$loan['loan_status']) {
                0 => 'Pending Review',
                1 => 'Approved',
                2 => 'Pending Disbursement',
                3 => 'Disbursed',
                4 => 'Fully Paid',
                default => 'Unknown'
            };

            $loans[] = [
                'loan_id' => $loan['loan_id'],
                'amount' => floatval($loan['loan_amount']),
                'fee' => floatval($loan['loan_fee']),
                'duration' => $loan['loan_duration'],
                'status' => $statusText,
                'status_code' => $loan['loan_status'],
                'created_at' => $loan['loan_created_at']
            ];
        }
        $loansQuery->close();
    }

    // Get recent transactions
    $transactions = [];
    $transSql = "SELECT * FROM transactions WHERE tuid = ? ORDER BY tcreated DESC LIMIT 10";
    $transQuery = $conn->prepare($transSql);

    if ($transQuery !== false) {
        $transQuery->bind_param("i", $userId);
        $transQuery->execute();
        $transResult = $transQuery->get_result();

        while ($trans = $transResult->fetch_assoc()) {
            $transactions[] = [
                'ref' => $trans['tref'],
                'amount' => floatval($trans['tamount']),
                'type' => $trans['ttype'],
                'description' => $trans['tdesc'],
                'status' => $trans['tstatus'] == 1 ? 'Completed' : 'Pending',
                'date' => $trans['tcreated']
            ];
        }
        $transQuery->close();
    }

    // Get unread notifications
    $notifications = [];
    $notifSql = "SELECT * FROM notifications WHERE ref_uid = ? AND viewed = 0 ORDER BY created_at DESC LIMIT 5";
    $notifQuery = $conn->prepare($notifSql);

    if ($notifQuery !== false) {
        $notifQuery->bind_param("i", $userId);
        $notifQuery->execute();
        $notifResult = $notifQuery->get_result();

        while ($notif = $notifResult->fetch_assoc()) {
            $notifications[] = [
                'id' => $notif['id'],
                'message' => $notif['message'],
                'date' => $notif['created_at']
            ];
        }
        $notifQuery->close();
    }

    // Generate new session ID for this login
    $newSessionId = generateSessionId(32);
    updates("use", "session_id = '$newSessionId'", "id = '$userId'");

    notify(2, "Account retrieved successfully. Welcome back, {$user['full_name']}!", 0, 1);

    return sendJsonResponse(200, true, "Account retrieved successfully.", [
        'session_id' => $newSessionId,
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'kyc_verified' => (bool)$user['approved_loan'],
            'joined' => $user['joined']
        ],
        'loans' => $loans,
        'transactions' => $transactions,
        'notifications' => $notifications,
        'loan_summary' => [
            'total_loans' => count($loans),
            'active_loans' => count(array_filter($loans, fn($l) => in_array($l['status_code'], [1, 2, 3]))),
            'total_borrowed' => array_sum(array_column($loans, 'amount'))
        ]
    ]);
}
