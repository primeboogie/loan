<?php

// Always send CORS headers before anything else
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, authorization");

// file_put_contents("cors.log", "METHOD:  " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);


// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit();
}


if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } else {
        $authHeader = null;
    }
} else {
    $authHeader = null;
}

// Extract the Bearer token if present
if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $accessToken = $matches[1];
} else {
    $accessToken = null;
}

// If no Bearer token was found in the Authorization header, check the cookies
if ($accessToken === null && isset($_COOKIE['access_token'])) {
    $accessToken = $_COOKIE['access_token'];
}

$_COOKIE['access_token'] = $accessToken;

include "modules/index.php";

// notify(0,"Hey Our System are Currently Under Maintenance Try Again Later Kind Regards",404,1);
// sendJsonResponse(0);
// sleep(2);


// $sleepTimes = [35, 26, 31, 50, 27];
// $selectedTime = $sleepTimes[array_rand($sleepTimes)];
// sleep($selectedTime);

unset($_SESSION);

$_SESSION['startmemory'] =  memory_get_usage();
$action = isset($_GET['action']) ? $_GET['action'] : '';

unauthorized($action);

sendJsonResponse(401, false, "null", $_COOKIE);
