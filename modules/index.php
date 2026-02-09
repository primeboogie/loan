<?php

require_once "modules/networking.php";

/**
 * Route Handler - Maps URL actions to functions
 *
 * Client Routes (no auth required):
 *   - basicdetails
 *   - otpverification
 *   - qualificationdetails
 *   - approvekyc
 *   - loanapply
 *   - grabaccount
 *
 * Admin Routes (requires X-Admin-Key header):
 *   - alluser
 *   - adminstats
 *   - adminnotify
 *   - getPendingLoans
 *   - processLoan
 *   - getAllDeposits
 *   - getSmsPackages
 *   - purchaseSms
 */

// Public routes - no authentication required
function unauthorized($action)
{
    $publicRoutes = [
        // Client registration flow
        'basicdetails',
        'otpverification',
        'qualificationdetails',
        'approvekyc',
        'loanapply',
        'grabaccount',

        // Public admin route
        'alluser',
    ];

    if (in_array($action, $publicRoutes)) {
        if (function_exists($action)) {
            return $action();
        }
    }

    return false;
}

// Admin routes - requires authentication
function authorized($action)
{
    $adminRoutes = [
        'adminstats',
        'adminnotify',
        'getPendingLoans',
        'processLoan',
        'getAllDeposits',
        'getSmsPackages',
        'purchaseSms',
    ];

    if (in_array($action, $adminRoutes)) {
        if (function_exists($action)) {
            return $action();
        }
    }

    return false;
}
