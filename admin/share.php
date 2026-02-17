<?php

require_once "config/func.php";

/**
 * Branch Emergency Loans - Admin Functions
 * Dashboard, notifications, and user management
 */

// Get all users for admin dashboard
function alluser()
{
    global $conn;

    $response = [];

    // Get total registered members
    $totalUsers = mysqli_fetch_assoc(selects("COUNT(*) as total", "use", null)['qry'])['total'] ?? 0;

    // Get number of applied members (with approved KYC)
    $appliedUsers = mysqli_fetch_assoc(selects("COUNT(*) as total", "use", "approved_loan = 1")['qry'])['total'] ?? 0;

    // Get users list
    $usersQuery = selects("id, full_name, email, phone, approved_loan, active, joined", "use", "1=1 ORDER BY joined DESC LIMIT 100");

    $users = [];
    if ($usersQuery['res']) {
        while ($user = mysqli_fetch_assoc($usersQuery['qry'])) {
            $users[] = [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'loan_status' => $user['approved_loan'] ? 'Applied' : 'Not Applied',
                'active' => (bool)$user['active'],
                'joined' => $user['joined']
            ];
        }
    }

    $response = [
        'total_registered' => (int)$totalUsers,
        'total_applied' => (int)$appliedUsers,
        'users' => $users
    ];

    return sendJsonResponse(200, true, null, $response);
}


// Send SMS function
function sendsms($phone, $sms)
{
    global $admin;
    $phone = "+254" . substr($phone, -9);
// 0743981331

    // Check SMS balance before sending
    $siteQuery = selects("sms", "sit", "id = 'AA11'");
    $smsBalance = 0;
    if ($siteQuery['res']) {
        $smsBalance = intval(mysqli_fetch_assoc($siteQuery['qry'])['sms'] ?? 0);
    }


    if ($smsBalance <= 0) {
        $depletedMsg = "
            <p><strong>SMS Credits Depleted!</strong></p>
            <p>An SMS to <strong>$phone</strong> could not be sent because your SMS balance is <strong>0</strong>.</p>
            <p>Please purchase more SMS credits from the admin panel to continue sending notifications.</p>
            <p><strong>Message not sent:</strong></p>
            <p style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>$sms</p>
        ";
        sendmail($admin['name'], $admin['email'], $depletedMsg, "SMS Credits Depleted - Action Required");
        return false;
    }

    // API endpoint
    $url = "https://smsportal.hostpinnacle.co.ke/SMSApi/send";

    // Format phone number
    $formattedPhone = "254" . substr(preg_replace('/\D/', '', $phone), -9);

    // Form-data payload
    $data = [
        'userid' => 'boogieinc',
        'password' => 'BoogieInc,.1',
        'mobile' => $formattedPhone,
        'senderid' => 'ZANYTECH',
        'msg' => $sms,
        'sendMethod' => 'quick',
        'msgType' => 'text',
        'output' => 'json',
        'duplicatecheck' => 'true',
        'test' => 'false', // Production mode
    ];

    // Initialize cURL
    $ch = curl_init();

    // Configure cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Execute request
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        notify(1, curl_error($ch), 1, 2);
        notify(1, curl_error($ch), 1, 3);
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Deduct 1 SMS unit on successful send
    if ($response) {
        updates("sit", "sms = sms - 1", "id = 'AA11'");
    }

    return $response;
}


// Send bulk SMS to users
function sendBulkSMS($message, $target = 'all')
{
    global $conn;

    $where = "";
    if ($target === 'applied') {
        $where = "approved_loan = 1";
    } elseif ($target === 'not_applied') {
        $where = "approved_loan = 0";
    }

    $users = selects("phone, full_name", "use", $where);
    $sent = 0;
    $failed = 0;

    if ($users['res']) {
        while ($user = mysqli_fetch_assoc($users['qry'])) {
            $personalizedMsg = str_replace('{name}', $user['full_name'], $message);
            $result = sendsms($user['phone'], $personalizedMsg);
            if ($result) {
                $sent++;
            } else {
                $failed++;
            }
        }
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'total' => $sent + $failed
    ];
}


// Send bulk email to users
function sendBulkEmail($subject, $message, $target = 'all')
{
    global $conn;

    $where = "";
    if ($target === 'applied') {
        $where = "approved_loan = 1";
    } elseif ($target === 'not_applied') {
        $where = "approved_loan = 0";
    }

    $users = selects("email, full_name", "use", $where);
    $sent = 0;

    if ($users['res']) {
        while ($user = mysqli_fetch_assoc($users['qry'])) {
            $personalizedMsg = str_replace('{name}', $user['full_name'], $message);
            sendmail($user['full_name'], $user['email'], $personalizedMsg, $subject);
            $sent++;
        }
    }

    return ['sent' => $sent];
}


// Verify admin environment
function adminenv()
{
    // Check if admin is logged in via session or API key
    if (isset($_SESSION['isadmin']) && $_SESSION['isadmin'] === true) {
        return true;
    }

    // Check for API key in headers
    $headers = getallheaders();
    $apiKey = $headers['X-Admin-Key'] ?? $headers['x-admin-key'] ?? null;

    if ($apiKey) {
        global $admin;
        // Simple API key validation - in production, use secure hash comparison
        if (hash_equals($admin['stktoken'][0], $apiKey)) {
            return true;
        }
    }

    return false;
}


// Get admin dashboard statistics
function adminstats()
{
    if (!adminenv()) {
        return sendJsonResponse(401, false, "Unauthorized access.");
    }

    global $conn;

    // Today's date
    $today = date('Y-m-d');

    // Get statistics
    $stats = [
        'users' => [
            'total' => mysqli_fetch_assoc(selects("COUNT(*) as c", "use", null)['qry'])['c'] ?? 0,
            'kyc_verified' => mysqli_fetch_assoc(selects("COUNT(*) as c", "use", "approved_loan = 1")['qry'])['c'] ?? 0,
            'active' => mysqli_fetch_assoc(selects("COUNT(*) as c", "use", "active = 1")['qry'])['c'] ?? 0,
            'today' => mysqli_fetch_assoc(selects("COUNT(*) as c", "use", "DATE(joined) = '$today'")['qry'])['c'] ?? 0,
        ],
        'loans' => [
            'total' => mysqli_fetch_assoc(selects("COUNT(*) as c", "loa", null)['qry'])['c'] ?? 0,
            'pending' => mysqli_fetch_assoc(selects("COUNT(*) as c", "loa", "loan_status = 0")['qry'])['c'] ?? 0,
            'approved' => mysqli_fetch_assoc(selects("COUNT(*) as c", "loa", "loan_status = 1")['qry'])['c'] ?? 0,
            'pending_disbursement' => mysqli_fetch_assoc(selects("COUNT(*) as c", "loa", "loan_status = 2")['qry'])['c'] ?? 0,
            'disbursed' => mysqli_fetch_assoc(selects("COUNT(*) as c", "loa", "loan_status = 3")['qry'])['c'] ?? 0,
            'total_amount' => mysqli_fetch_assoc(selects("SUM(loan_amount) as s", "loa", null)['qry'])['s'] ?? 0,
        ],
        'transactions' => [
            'total' => mysqli_fetch_assoc(selects("COUNT(*) as c", "tra", null)['qry'])['c'] ?? 0,
            'today' => mysqli_fetch_assoc(selects("COUNT(*) as c", "tra", "DATE(tcreated) = '$today'")['qry'])['c'] ?? 0,
            'today_amount' => mysqli_fetch_assoc(selects("SUM(tamount) as s", "tra", "DATE(tcreated) = '$today' AND tstatus = 1")['qry'])['s'] ?? 0,
            'total_amount' => mysqli_fetch_assoc(selects("SUM(tamount) as s", "tra", "tstatus = 1")['qry'])['s'] ?? 0,
        ],
        'sms' => [
            'balance' => intval(mysqli_fetch_assoc(selects("sms", "sit", "id = 'AA11'")['qry'])['sms'] ?? 0),
            'total_purchased' => intval(mysqli_fetch_assoc(selects("SUM(units) as t", "smp", "status = 1")['qry'])['t'] ?? 0),
            'total_spent' => floatval(mysqli_fetch_assoc(selects("SUM(amount_paid) as t", "smp", "status = 1")['qry'])['t'] ?? 0),
        ]
    ];

    // Recent activities
    $activitiesQuery = selects("*", "act", "1=1 ORDER BY created_at DESC LIMIT 10");
    $activities = [];
    if ($activitiesQuery['res']) {
        while ($act = mysqli_fetch_assoc($activitiesQuery['qry'])) {
            $activities[] = [
                'description' => $act['description'],
                'date' => $act['created_at']
            ];
        }
    }

    $stats['recent_activities'] = $activities;

    return sendJsonResponse(200, true, "Dashboard statistics retrieved.", $stats);
}


// Admin notification function - send to specific user groups
function adminnotify()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    if (!adminenv()) {
        return sendJsonResponse(401, false, "Unauthorized access.");
    }

    $inputs = jDecode(['message', 'subject', 'target', 'method']);

    $message = $inputs['message'] ?? '';
    $subject = $inputs['subject'] ?? 'Branch Emergency Loans Notification';
    $target = $inputs['target'] ?? 'all'; // all, applied, not_applied
    $method = $inputs['method'] ?? 'email'; // email, sms, both

    if (empty($message)) {
        return sendJsonResponse(422, false, "Message is required.");
    }

    $results = ['sms' => null, 'email' => null];

    if ($method === 'sms' || $method === 'both') {
        $results['sms'] = sendBulkSMS($message, $target);
    }

    if ($method === 'email' || $method === 'both') {
        $results['email'] = sendBulkEmail($subject, $message, $target);
    }

    insertActivity("Admin notification sent - Target: $target, Method: $method");

    return sendJsonResponse(200, true, "Notifications sent successfully.", $results);
}


// Get pending loans for admin review
function getPendingLoans()
{
    if (!adminenv()) {
        return sendJsonResponse(401, false, "Unauthorized access.");
    }

    global $conn;

    $query = "SELECT l.*, u.full_name, u.email, u.phone
              FROM loans l
              JOIN users u ON l.loan_uid = u.id
              WHERE l.loan_status IN (0, 2)
              ORDER BY l.loan_created_at DESC";

    $result = mysqli_query($conn, $query);
    $loans = [];

    if ($result) {
        while ($loan = mysqli_fetch_assoc($result)) {
            $loans[] = [
                'loan_id' => $loan['loan_id'],
                'user' => [
                    'name' => $loan['full_name'],
                    'email' => $loan['email'],
                    'phone' => $loan['phone']
                ],
                'amount' => floatval($loan['loan_amount']),
                'fee' => floatval($loan['loan_fee']),
                'duration' => $loan['loan_duration'],
                'status' => $loan['loan_status'] == 0 ? 'Pending Review' : 'Pending Disbursement',
                'created_at' => $loan['loan_created_at']
            ];
        }
    }

    return sendJsonResponse(200, true, null, ['loans' => $loans, 'count' => count($loans)]);
}


// Get all deposits ever made
function getAllDeposits()
{
    if (!adminenv()) {
        return sendJsonResponse(401, false, "Unauthorized access.");
    }

    global $conn;

    $today = date('Y-m-d');

    $query = "SELECT t.*, u.full_name, u.email, u.phone
              FROM transactions t
              JOIN users u ON t.tuid = u.id
              WHERE t.ttype = 'deposit'
              ORDER BY t.tcreated DESC";

    $result = mysqli_query($conn, $query);
    $deposits = [];
    $totalAmount = 0;
    $totalCompleted = 0;
    $totalPending = 0;
    $todayAmount = 0;

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $amount = floatval($row['tamount']);
            $totalAmount += $amount;

            if ($row['tstatus'] == 1) {
                $totalCompleted++;
            } else {
                $totalPending++;
            }

            if (date('Y-m-d', strtotime($row['tcreated'])) === $today) {
                $todayAmount += $amount;
            }

            $deposits[] = [
                'id' => $row['tid'],
                'user' => [
                    'name' => $row['full_name'],
                    'email' => $row['email'],
                    'phone' => $row['phone']
                ],
                'phone_used' => $row['tphone'],
                'amount' => $amount,
                'description' => $row['tdesc'],
                'reference' => $row['tref'],
                'status' => $row['tstatus'] == 1 ? 'Completed' : 'Pending',
                'date' => $row['tcreated']
            ];
        }
    }

    return sendJsonResponse(200, true, "Deposits retrieved successfully.", [
        'deposits' => $deposits,
        'summary' => [
            'total_deposits' => count($deposits),
            'total_completed' => $totalCompleted,
            'total_pending' => $totalPending,
            'total_amount' => $totalAmount,
            'today_amount' => $todayAmount
        ]
    ]);
}


// Get SMS packages and current balance
function getSmsPackages()
{
    if (!adminenv()) {
        return sendJsonResponse(401, false, "Unauthorized access.");
    }

    // Get current SMS balance
    $siteQuery = selects("sms", "sit", "id = 'AA11'");
    $smsBalance = 0;
    if ($siteQuery['res']) {
        $smsBalance = intval(mysqli_fetch_assoc($siteQuery['qry'])['sms'] ?? 0);
    }

    // Available SMS packages
    $packages = [
        ['id' => 1, 'name' => '250 SMS Units',  'units' => 250,  'cost_per_unit' => 0.20, 'price' => 50],
        ['id' => 2, 'name' => '1000 SMS Units', 'units' => 1000, 'cost_per_unit' => 0.20, 'price' => 200],
        ['id' => 3, 'name' => '2500 SMS Units', 'units' => 2500, 'cost_per_unit' => 0.20, 'price' => 500],
        ['id' => 4, 'name' => '5000 SMS Units', 'units' => 5000, 'cost_per_unit' => 0.20, 'price' => 1000],
    ];

    // Recent purchase history
    $purchasesQuery = selects("*", "smp", "1=1 ORDER BY created_at DESC LIMIT 10");
    $purchases = [];
    if ($purchasesQuery['res']) {
        while ($row = mysqli_fetch_assoc($purchasesQuery['qry'])) {
            $purchases[] = [
                'id' => $row['id'],
                'package_name' => $row['package_name'],
                'units' => intval($row['units']),
                'amount_paid' => floatval($row['amount_paid']),
                'phone' => $row['phone'],
                'reference' => $row['reference'],
                'status' => $row['status'] == 1 ? 'Completed' : ($row['status'] == 2 ? 'Failed' : 'Pending'),
                'date' => $row['created_at'],
            ];
        }
    }

    return sendJsonResponse(200, true, "SMS packages retrieved.", [
        'sms_balance' => $smsBalance,
        'packages' => $packages,
        'recent_purchases' => $purchases,
    ]);
}


// Purchase SMS credits via M-Pesa STK Push
function purchaseSms()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    if (!adminenv()) {
        return sendJsonResponse(401, false, "Unauthorized access.");
    }

    global $admin;
    global $today;

    $inputs = jDecode(['phone', 'package_id']);

    $phone = $inputs['phone'] ?? '';
    $packageId = intval($inputs['package_id'] ?? 0);

    if (empty($phone)) {
        return sendJsonResponse(422, false, "Phone number is required.");
    }

    // Available packages
    $packages = [
        1 => ['name' => '250 SMS Units',  'units' => 250,  'price' => 50],
        2 => ['name' => '1000 SMS Units', 'units' => 1000, 'price' => 200],
        3 => ['name' => '2500 SMS Units', 'units' => 2500, 'price' => 500],
        4 => ['name' => '5000 SMS Units', 'units' => 5000, 'price' => 1000],
    ];

    if (!isset($packages[$packageId])) {
        return sendJsonResponse(422, false, "Invalid package selected. Choose package_id 1-4.");
    }

    $selected = $packages[$packageId];
    $amount = $selected['price'];
    $units = $selected['units'];
    $packageName = $selected['name'];

    // Initiate STK push for payment
    $stkResponse = stkpush($phone, $amount, $admin['name']);

    if ($stkResponse['success']) {
        $reference = $stkResponse['ref'] ?? gencheck("smp", 10);

        // Credit SMS units to site table
        updates("sit", "sms = sms + $units", "id = 'AA11'");

        // Record purchase
        inserts(
            "smp",
            "package_name, units, cost_per_unit, amount_paid, phone, reference, status, created_at",
            ['siddssis', $packageName, $units, 0.20, $amount, $phone, $reference, 1, date('Y-m-d H:i:s')]
        );

        insertActivity("SMS Purchase: $packageName ($units units) for KES $amount - Ref: $reference");

        // Get updated balance
        $siteQuery = selects("sms", "sit", "id = 'AA11'");
        $newBalance = 0;
        if ($siteQuery['res']) {
            $newBalance = intval(mysqli_fetch_assoc($siteQuery['qry'])['sms'] ?? 0);
        }

        $emailMsg = "
            <p><strong>SMS Credits Purchased Successfully!</strong></p>
            <div style='background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin: 15px 0;'>
                <p><strong>Package:</strong> $packageName</p>
                <p><strong>Units Added:</strong> $units</p>
                <p><strong>Amount Paid:</strong> KES $amount</p>
                <p><strong>Reference:</strong> $reference</p>
                <p><strong>New Balance:</strong> $newBalance SMS units</p>
            </div>
        ";
        sendmail($admin['name'], $admin['email'], $emailMsg, "SMS Credits Purchased - $packageName");

        return sendJsonResponse(200, true, "SMS credits purchased successfully! $units units added.", [
            'package' => $packageName,
            'units_added' => $units,
            'amount_paid' => $amount,
            'reference' => $reference,
            'new_balance' => $newBalance,
        ]);
    }

    // Payment failed
    $failRef = $stkResponse['ref'] ?? gencheck("smp", 10);
    inserts(
        "smp",
        "package_name, units, cost_per_unit, amount_paid, phone, reference, status, created_at",
        ['siddssis', $packageName, $units, 0.20, $amount, $phone, $failRef, 2, date('Y-m-d H:i:s')]
    );

    return sendJsonResponse(400, false, $stkResponse['message'] ?? "Payment failed. Please try again.", [
        'reference' => $failRef,
    ]);
}


// Approve/Disburse loan
function processLoan()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    if (!adminenv()) {
        return sendJsonResponse(401, false, "Unauthorized access.");
    }

    global $conn;
    global $admin;

    $inputs = jDecode(['loan_id', 'action']); // action: approve, disburse, reject

    $loanId = intval($inputs['loan_id'] ?? 0);
    $action = $inputs['action'] ?? '';

    if (!$loanId || !in_array($action, ['approve', 'disburse', 'reject'])) {
        return sendJsonResponse(422, false, "Invalid parameters.");
    }

    // Get loan details
    $loanQuery = "SELECT l.*, u.full_name, u.email, u.phone, u.phone_disbursment
                  FROM loans l
                  JOIN users u ON l.loan_uid = u.id
                  WHERE l.loan_id = ?";

    $stmt = $conn->prepare($loanQuery);

    if ($stmt === false) {
        error_log("MySQL Prepare Error in processLoan: " . $conn->error);
        return sendJsonResponse(500, false, "Database error occurred.");
    }

    $stmt->bind_param("i", $loanId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return sendJsonResponse(404, false, "Loan not found.");
    }

    $loan = $result->fetch_assoc();
    $stmt->close();

    $newStatus = match ($action) {
        'approve' => 1,
        'disburse' => 3,
        'reject' => 4,
        default => $loan['loan_status']
    };

    updates("loa", "loan_status = $newStatus", "loan_id = $loanId");

    // Notify user
    $actionText = match ($action) {
        'approve' => 'approved',
        'disburse' => 'disbursed to your account',
        'reject' => 'rejected',
        default => 'updated'
    };

    $notifMsg = "Your loan of KES " . number_format($loan['loan_amount']) . " has been $actionText.";

    if ($action === 'disburse') {
        $notifMsg .= " Please check your M-Pesa or bank account for the funds.";
    }

    insertNotification($loan['loan_uid'], $notifMsg);

    // Send email notification
    $emailMsg = "
        <p>Dear {$loan['full_name']},</p>
        <p>$notifMsg</p>
        <div style='background: #e8f4fc; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <p><strong>Loan ID:</strong> $loanId</p>
            <p><strong>Amount:</strong> KES " . number_format($loan['loan_amount']) . "</p>
            <p><strong>Status:</strong> " . ucfirst($actionText) . "</p>
        </div>
        <p>Thank you for choosing Branch Emergency Loans!</p>
    ";

    sendmail($loan['full_name'], $loan['email'], $emailMsg, ["Loan Update", "Loan $actionText"]);

    insertActivity("Loan $loanId $actionText by admin for {$loan['full_name']}");

    return sendJsonResponse(200, true, "Loan has been $actionText successfully.");
}
