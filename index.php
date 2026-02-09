<?php

/**
 * Branch Emergency Loans - API Router
 * Main entry point for all API requests
 *
 * Usage:
 *   GET/POST /index.php?action=basicdetails
 *   GET/POST /api/basicdetails (with .htaccess rewrite)
 */

// CORS Headers - Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Key, x-admin-key");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Start memory tracking for response
// Include router
include "modules/index.php";
unset($_SESSION);
$_SESSION['startmemory'] = memory_get_usage();

// Get action from query parameter or URL path
$action = '';

// Check for ?action= parameter
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $action = $_GET['action'];
}

// Check for URL path routing (e.g., /api/basicdetails)
if (empty($action) && isset($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
    $segments = explode('/', $path);
    $action = end($segments);
}

// Alternative: Check REQUEST_URI for clean URLs
if (empty($action)) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = dirname($_SERVER['SCRIPT_NAME']);

    // Remove base path and query string
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = str_replace($basePath, '', $path);
    $path = trim($path, '/');

    // Remove index.php if present
    $path = str_replace('index.php', '', $path);
    $path = trim($path, '/');

    if (!empty($path)) {
        $segments = explode('/', $path);
        // Skip 'api' prefix if present
        if ($segments[0] === 'api' && isset($segments[1])) {
            $action = $segments[1];
        } else {
            $action = $segments[0];
        }
    }
}

// Sanitize action - only allow alphanumeric and underscore
$action = preg_replace('/[^a-zA-Z0-9_]/', '', $action);

// No action specified
// if (empty($action)) {
//     sendJsonResponse(200, true, "Branch Emergency Loans API v1.0", [
//         "message" => "Welcome to Branch Emergency Loans API",
//         "tagline" => "Get Your Loan in Less Than 3 Hours!",
//         "endpoints" => [
//             "POST /api/basicdetails" => "Register new user",
//             "POST /api/otpverification" => "Verify OTP",
//             "POST /api/qualificationdetails" => "Complete profile",
//             "POST /api/approvekyc" => "KYC verification payment",
//             "POST /api/loanapply" => "Apply for loan",
//             "POST /api/grabaccount" => "Login / Get account",
//             "GET /api/alluser" => "Get all users (Admin)",
//             "GET /api/adminstats" => "Dashboard stats (Admin)",
//             "GET /api/getAllDeposits" => "Get all deposits (Admin)",
//             "GET /api/getSmsPackages" => "Get SMS packages & balance (Admin)",
//             "POST /api/purchaseSms" => "Purchase SMS credits (Admin)",
//         ],
//         "documentation" => "See README.md for full API documentation"
//     ]);
// }

// Try public routes first
$result = unauthorized($action);

// If public route didn't handle it, try admin routes
if ($result === false) {
    $result = authorized($action);
}

// If still not handled, return 404
if ($result === false) {
    sendJsonResponse(404, false, "Endpoint not found: $action", [
        "available_endpoints" => [
            "basicdetails",
            "otpverification",
            "qualificationdetails",
            "approvekyc",
            "loanapply",
            "grabaccount",
            "alluser",
            "adminstats",
            "adminnotify",
            "getPendingLoans",
            "processLoan",
            "getAllDeposits",
            "getSmsPackages",
            "purchaseSms"
        ]
    ]);
}

// Clean up session
unset($_SESSION);
