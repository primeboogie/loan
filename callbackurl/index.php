<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

header("Content-Type: application/json");

// Get the raw POST body
$stkCallbackResponse = file_get_contents('php://input');

// Log it for debugging
$logFile = "stkboogie_power.json";
file_put_contents($logFile, $stkCallbackResponse . PHP_EOL, FILE_APPEND);

// Decode JSON
$callbackContent = json_decode($stkCallbackResponse, true);

require dirname(__DIR__) . "/config/func.php";

// Validate body
if (!isset($callbackContent)) {
    echo json_encode(["error" => "Invalid payload structure"]);
    exit;
}

$body = $callbackContent;

// Extract required fields
$api_key = $body['api_key'] ?? null;
$local_id = $body['local_id'] ?? null;
$paid = $body['paid'] ?? false;
$result_code = $body['result_code'] ?? null;
$result = $body['result'] ?? [];

$amount = $result['amount'] ?? null;
$ref_code = $result['ref_code'] ?? null; // This acts like MpesaReceiptNumber
$msg = $result['msg'] ?? null;

// Only update if payment was successful
if ($paid === true && $result_code === 0) {
    updates("tra", "ref_payment = '$ref_code'", "tid = '$local_id'");
    echo json_encode([
        "status" => "success",
        "message" => "Payment recorded successfully",
        "local_id" => $local_id,
        "receipt" => $ref_code
    ]);
} else {
    echo json_encode([
        "status" => "failed",
        "message" => $msg ?? "Payment not successful",
        "local_id" => $local_id,
        "result_code" => $result_code
    ]);
}
