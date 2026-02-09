<?php
require 'config.php';


$today =  date("Y-m-d H:i:s");
$mintoday =  date("Y-m-d");

function sendJsonResponse($statusCode, $resultcode = false, $message = null, $data = null)
{

    $resultcode ??= false;
    http_response_code($statusCode);

    if (!$message) {
        switch ($statusCode) {
            case 200:
                $message = 'OK';
                $resultcode = true;
                break;
            case 201:
                $message = 'Action was executed successfully';
                break;
            case 204:
                $message = 'No Content';
                break;
            case 400:
                $message = 'Bad Request: [' . $_SERVER['REQUEST_METHOD'] . '] is Not Allowed';
                break;
            case 401:
                $message = 'Unauthorized';
                break;
            case 403:
                $message = 'Forbidden';
                break;
            case 404:
                $message = '404 Not Found';
                break;
            case 422:
                $message = 'Unprocessable Entity Missing Parameters.';
                break;
            case 0:
                $message = 'Timed out Connection: Try again Later';
                notify(1, "Timed out Connection: Try again Later.", 0, 1);
                break;
            default:
                $message = 'Timed out Connection: Try again Later';
        }
    }

    $response = ['status' => $statusCode, 'resultcode' => $resultcode, 'msg' => $message];

    if (strstate($data)) {
        $response['data'] = $data;
    }

    $startMemory = $_SESSION['startmemory'];

    $endMemory = memory_get_usage();
    $peakMemory = memory_get_peak_usage();
    $response['memory'] = [
        'used' => formatBytes($endMemory - $startMemory),
        'peak' => formatBytes($peakMemory),
    ];

    if (isset($_SESSION['notify'])) {
        $response['info'] = $_SESSION['notify'];
    }

    unset($_SESSION);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


function jDecode($expect = null)
{

    $json = file_get_contents("php://input");
    $inputs = json_decode($json, true);

    if ($inputs === null && json_last_error() !== JSON_ERROR_NONE) {
        return sendJsonResponse(422, false, "Bad Request: Invalid JSON format");
    }

    if ($expect) {
        foreach ($expect as $key) {
            // Check if the required key is missing or empty
            if (!array_key_exists($key, $inputs) || !strstate($inputs[$key])) {
                return sendJsonResponse(422, false, "Missing Parameters", [
                    "Your_Request" => $inputs,
                    "Required" => $expect
                ]);
            }
        }
    }

    return $inputs;
}

function fne($fn)
{
    if (function_exists($fn)) {
        $fn();
    }
}


function msginf($id)
{
    $res  = [];
    if ($id == 0) {
        $res['tra'] = "Awaiting";
        $res['up'] = "Upcoming";
        $res['reg'] = "Undefined";
        $res['color'] = "orange";
        $res['inf'] = "Info";
        $res['icon'] = "<i class='fa-solid fa-circle-exclamation'></i>";
    } elseif ($id == 1) {
        $res['tra'] = "Declined";
        $res['up'] = "Unsettled";
        $res['reg'] = "Inactive";
        $res['color'] = "#e02007";
        $res['inf'] = "Error";
        $res['icon'] = "<i class='fa-solid fa-triangle-exclamation'></i>";
    } elseif ($id == 2) {
        $res['tra'] = "Confirmed";
        $res['up'] = "Accredit";
        $res['reg'] = "Active";
        $res['color'] = "#24db14";
        $res['inf'] = "Success";
        $res['icon'] = "<i class='fa-solid fa-check'></i>";
    } else {
        $res['tra'] = "Undefined";
        $res['up'] = "Unsettled";
        $res['reg'] = "Undefined";
        $res['color'] = "#ff790c";
        $res['inf'] = "Info";
        $res['icon'] = "<i class='fa-solid fa-circle-exclamation'></i>";
    }
    return $res;
}

function notify($state, $msg, $errno, $show)
{
    global $dev;
    global $admin;

    $state ??= '0'; //0=info//1=error//2=success
    $errno ??= null; //error meassage
    $show ??= 3; //1=user to see//2=admin to see//3=dev to see
    $justnow = date('F j, H:i:s A');

    if (!isset($_SESSION['notify'])) {
        $_SESSION['notify'] = [];
    }
    $notification = [
        "state" => $state,
        "color" => msginf($state)['color'],
        "msg" => $msg,
        "errno" => $errno,
        "time" => $justnow,
        "icon" => msginf($state)['icon'],
    ];

    if ($show == 1) {

        $_SESSION['notify'][] = $notification;
    } elseif ($show == 2) {
        sendmail($admin['name'], $admin['email'], $admin['name'] . " " . $msg, "#$errno");
    } else {
        sendmail($dev['name'], $dev['email'], $msg, "Error-Code->$errno");
    }

    return true;
}

function mytrim($string = null)
{
    $string = $string ? trim($string) : "";
    $string =  str_replace(["/", "#", ",", "!", "$", "?", "|", "'", "-", "_", "~", "*", "(", ")", " "], "", $string);
    if (!strstate($string)) {
        return false;
    }

    return $string;
}

function ucap($str)
{
    $capitalizedString = ucfirst(mytrim($str));
    return $capitalizedString;
}

function verifyEmail($email)
{
    // Check if the email is not empty and is a valid email address
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Perform DNS check to see if the domain has a valid MX or A record
        $domain = substr(strrchr($email, "@"), 1);
        if (checkdnsrr($domain, "MX") || checkdnsrr($domain, "A")) {
            return true;
        } else {
            return false; // Invalid domain
        }
    } else {
        return false; // Invalid email format
    }
}

function strstate($str)
{
    if ($str == '' || $str == null) {
        return false;
    }
    return true;
}

function emailtemp($msg, $uname, $sub)
{
    global $admin;

    $domain = $admin['domain'];
    $company = $admin['company'];

    // Logo URL - use absolute URL for email compatibility
    $logoUrl = $domain . '/branchloan.jpeg';

    // Professional light blue themed email template for Branch Emergency Loans
    $emailContent = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$company - $sub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f0f7ff;
            padding: 20px;
            line-height: 1.6;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 120, 180, 0.15);
        }

        .email-header {
            background: linear-gradient(135deg, #0077b6 0%, #00a8e8 50%, #48cae4 100%);
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
        }

        .logo-container {
            background: #ffffff;
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .logo {
            max-width: 55px;
            max-height: 55px;
            object-fit: contain;
            display: block;
        }

        .company-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .tagline {
            font-size: 13px;
            font-weight: 500;
            opacity: 0.95;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 8px;
        }

        .email-subject {
            font-size: 16px;
            font-weight: 600;
            margin-top: 12px;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            display: inline-block;
        }

        .email-body {
            padding: 30px 25px;
            color: #333333;
        }

        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #0077b6;
            font-weight: 500;
        }

        .message-content {
            font-size: 15px;
            color: #444444;
            margin-bottom: 25px;
            line-height: 1.8;
            background: #f8fbff;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #0077b6;
        }

        .highlight-box {
            background: linear-gradient(135deg, #e8f4fc 0%, #d4edfc 100%);
            border: 1px solid #b8dff5;
            border-radius: 8px;
            padding: 18px;
            margin: 20px 0;
            text-align: center;
        }

        .highlight-box .amount {
            font-size: 28px;
            font-weight: 700;
            color: #0077b6;
        }

        .cta-container {
            text-align: center;
            margin: 30px 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #0077b6 0%, #00a8e8 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 35px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 4px 15px rgba(0, 119, 182, 0.35);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 119, 182, 0.45);
        }

        .features-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }

        .feature-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 15px 10px;
        }

        .feature-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .feature-text {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }

        .email-footer {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #e8f4fc;
            font-size: 13px;
            color: #666666;
        }

        .footer-note {
            margin-bottom: 15px;
            padding: 15px;
            background: linear-gradient(135deg, #e8f4fc 0%, #f0f7ff 100%);
            border-radius: 8px;
            border-left: 4px solid #48cae4;
        }

        .footer-note strong {
            color: #0077b6;
        }

        .trust-badges {
            text-align: center;
            padding: 15px 0;
            background: #f8fbff;
            border-radius: 8px;
            margin: 15px 0;
        }

        .trust-badges span {
            display: inline-block;
            margin: 0 10px;
            font-size: 11px;
            color: #0077b6;
            font-weight: 600;
        }

        .copyright {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #0077b6;
            color: #ffffff;
            font-size: 12px;
            border-radius: 0 0 12px 12px;
            margin: 0 -25px -30px -25px;
        }

        .copyright a {
            color: #48cae4;
            text-decoration: none;
        }

        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }

            .email-body {
                padding: 20px 15px;
            }

            .cta-button {
                padding: 12px 28px;
                font-size: 14px;
            }

            .company-name {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class='email-wrapper'>
        <div class='email-header'>
            <div class='logo-container'>
                <img src='$logoUrl' alt='$company' class='logo' />
            </div>
            <div class='company-name'>$company</div>
            <div class='tagline'>Your Trusted Financial Partner</div>
            <div class='email-subject'>$sub</div>
        </div>

        <div class='email-body'>
            <div class='greeting'>Hello <strong>$uname</strong>,</div>

            <div class='message-content'>
                $msg
            </div>

            <div class='cta-container'>
                <a href='$domain' class='cta-button'>Access Your Account</a>
            </div>

            <div class='trust-badges'>
                <span>Secure & Confidential</span>
                <span>|</span>
                <span>Fast Approval</span>
                <span>|</span>
                <span>24/7 Support</span>
            </div>

            <div class='email-footer'>
                <div class='footer-note'>
                    <strong>Your Security Matters:</strong> We use bank-level encryption to protect your personal information. Never share your verification codes with anyone.
                </div>

                <div class='copyright'>
                    &copy; " . date('Y') . " $company. All rights reserved.<br>
                    <small>Licensed Financial Services Provider | Get Your Loan in Less Than 3 Hours</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";

    return $emailContent;
}

function sendPostRequest($url, $data, $authorizationToken = null)
{
    // Initialize cURL session
    $ch = curl_init($url);

    // Convert data array to JSON
    $payload = json_encode($data);

    // Base headers
    $headers = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ];

    // Add authorization header if token is provided
    if ($authorizationToken) {
        $headers[] = 'Authorization: Bearer ' . $authorizationToken;
    }

    // Set cURL options
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the POST request
    $response = curl_exec($ch);

    // Check for cURL errors
    if ($response === false) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }

    // Close the cURL session
    curl_close($ch);

    // Return the response
    return json_decode($response, true);
}


function send_post_request($url, $data, $authorizationToken = null, $extraHeaders = [], $asJson = true)
{
    $ch = curl_init($url);

    if ($asJson) {
        $payload = json_encode($data);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    } else {
        $payload = http_build_query($data);
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($payload)
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    // Merge in extra headers
    foreach ($extraHeaders as $key => $value) {
        if (is_string($key)) {
            $headers[] = "$key: $value";
        } else {
            $headers[] = $value;
        }
    }

    // Add Authorization header if provided
    if ($authorizationToken) {
        $headers[] = 'Authorization: Bearer ' . $authorizationToken;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error_message = 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        // documentError($error_message);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Return raw string if not JSON
        return $response;
    }

    return $result;
}


function getstkpushtoken()
{
    global $admin;

    $array = $admin['stktoken'];

    shuffle($array);
    $array = reset($array);

    return $array;
}

function generatetoken($length = 16, $cap = false)
{
    $length = strstate($length) ? $length : 16;
    $token = bin2hex(random_bytes($length));

    if ($cap) {
        $token = strtoupper($token);
    }
    return $token;
}


function inserts($tb, $tbwhat, $tbvalues)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];
    if (!$tb) {
        notify(1, "error requested fn=>inserts", 501, 3);
        return sendJsonResponse(500);
    }
    $array = [];
    $array['res'] = false;

    $values = count($tbvalues) - 1;
    $qvalues = implode(', ', array_fill(0, $values, '?'));

    $qry = "INSERT INTO $tb ($tbwhat) VALUES ($qvalues)";
    $stmt = $conn->prepare($qry);

    // Extract data types and values separately
    $dataTypes = str_split(array_shift($tbvalues));
    $stmt->bind_param(implode('', $dataTypes), ...$tbvalues);

    $array['res'] = $stmt->execute();

    // Check for errors
    if (!$array['res']) {
        $array['qry'] = $stmt->error;
        // notify(1,"Error Querring " . $array['qry'],400,3);
        //sends me a amil
    }

    // Close the statement
    $stmt->close();

    return $array;
}

function formatBytes($bytes)
{
    if ($bytes <= 0) {
        return '0 MB';
    }

    return round($bytes / 1024 / 1024, 2) . ' MB';
}


function selects($all, $tb, $tbwhere, $datatype =  2)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];
    if (!$tb) {
        // notify(1,'error requested  fn=>selects',502,3);
        return sendJsonResponse(500, "ss");
    }
    $all = !empty($all) ? $all . " " : "*";
    $datatype = !empty($datatype) ? $datatype : "2";

    $array = [];
    $array['res'] = false;
    $array['rows'] = 0;
    $array['qry'] = [];

    if (empty($tbwhere) || $tbwhere == null) {
        $tbwhere = "";
    } else {
        $tbwhere = " WHERE $tbwhere ";
    }

    $selects = "SELECT $all FROM $tb $tbwhere";
    $results = mysqli_query($conn,  $selects);
    if ($results) {
        $num = mysqli_num_rows($results);

        if ($num > 0) {
            // if ($datatype == 1) {
            //     while ($grab = mysqli_fetch_assoc($results)) {
            //         $qry[] = $grab;
            //     }
            // } else {
            //     while ($grab = mysqli_fetch_row($results)) {
            //         $qry[] = $grab;
            //     }
            // }
            $array['res'] = true;
            $array['qry'] = $results;
            $array['rows'] = $num;
        }
    } else {
        $array['qry']['data'] = mysqli_error($conn);
        // notify(1, "Error Querring " . $array['qry']['data'], 400, 3);
    }
    return $array;
}
function comboselects($query, $datatype =  2)
{
    global $conn;

    $array = [];
    $array['res'] = false;
    $array['rows'] = 0;
    $array['qry'] = [];

    if (empty($query)) {
        return $array;
    }
    $results = mysqli_query($conn,  $query);
    if ($results) {
        $num = mysqli_num_rows($results);
        if ($num > 0) {
            // if ($datatype == 1) {
            //     while ($grab = mysqli_fetch_assoc($results)) {
            //         $qry[] = $grab;
            //     }
            // } else {
            //     while ($grab = mysqli_fetch_row($results)) {
            //         $qry[] = $grab;
            //     }
            // }
            $array['res'] = true;
            // $array['qry'] = mysqli_fetch_assoc($results);
            $array['qry'] = $results;
            $array['rows'] = $num;
        }
    } else {
        $array['qry']['data'] = mysqli_error($conn);
        // notify(1,"Error Querring " . $array['qry']['data'],400,3);

    }
    return $array;
}

function table($abrv)
{
    $array = [];
    switch ($abrv) {
        case "use":
            $array['tb'] = "users";
            $array['id'] = "id";
            break;
        case "act":
            $array['tb'] = "activities";
            $array['id'] = "id";
            break;
        case "loa":
            $array['tb'] = "loans";
            $array['id'] = "loan_id";
            break;
        case "nts":
            $array['tb'] = "notifications";
            $array['id'] = "id";
            break;
        case "sit":
            $array['tb'] = "site";
            $array['id'] = "id";
            break;
        case "tra":
            $array['tb'] = "transactions";
            $array['id'] = "tid";
            break;
        case "smp":
            $array['tb'] = "sms_purchases";
            $array['id'] = "id";
            break;
    }

    return $array;
}
function check($type, $tb, $value)
{

    $array = [];

    $array["res"] = false;
    $array["qry"] = null;

    $run = selects($type, $tb, "$type = '$value'");

    if ($run['res'] === true) {
        $array["res"] = true;
        $array["qry"] = mysqli_fetch_assoc($run['qry']);
    }
    return $array;
}
function updates($tb, $tbset, $tbwhere)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];

    if (!$tb) {
        // notify(1,"error requested fn=>updates",503,3);
        return sendJsonResponse(500);
    }
    $array = [];
    $array['res'] = false;
    $array['qry'] = null;

    if (empty($tbwhere) || !isset($tbwhere)) {
        $tbwhere = "";
    } else {
        $tbwhere = " WHERE $tbwhere";
    }

    $updates = "UPDATE $tb SET $tbset $tbwhere";
    $results = mysqli_query($conn,  $updates);
    if ($results === true) {
        $array['res'] = true;
    } else {
        $array['qry'] = $results;
        // notify(1,"Error Querring " . $array['qry'],400,3);
    }
    return $array;
}

function deletes($tb, $tbwhere)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];
    if (!$tb) {
        notify(1, "error requested fn=>deletes", 504, 3);
        return sendJsonResponse(500);
    }
    $array = [];
    $array['res'] = false;

    if (empty($tbwhere) || !isset($tbwhere)) {
        $tbwhere = "";
    } else {
        $tbwhere = " WHERE $tbwhere";
    }

    $deletes = "DELETE FROM $tb $tbwhere ";
    $results = mysqli_query($conn,  $deletes);
    if ($results) {
        $array['res'] = true;
    } else {
        $array['qry'] = mysqli_error($conn);
        // notify(1,"Error Querring " . $array['qry'],400,3);

    }
    return $array;
}

function insertstrans($tid, $tuid, $tuname, $tuphone, $ttype, $tcat, $payment_type, $ref_payment, $tamount, $tstatus, $tprebalance, $tbalance, $tpredeposit, $tdeposit, $tdate, $tduedate, $trefuname, $trefuid, $tstate, $ttype_id = null)
{
    $query = [$tid, $tuid, $tuname, $tuphone, $ttype, $tcat, $payment_type, $ref_payment, $tamount, $tstatus, $tprebalance, $tbalance, $tpredeposit, $tdeposit, $tdate, $tduedate, $trefuname, $trefuid, $tstate, $ttype_id];
    $merged = array_merge(['ssssssssssssssssssss'], $query);
    return inserts("tra", "tid,tuid,tuname,tuphone,ttype,tcat,payment_type,ref_payment,tamount,tstatus,tprebalance,tbalance,tpredeposit,tdeposit,tdate,tduedate,trefuname,trefuid,tstate,ttype_id", $merged);
}

function checktoken($tb, $token, $cap = false)
{
    $array = [];

    $id = table($tb)['id'];
    if (!$tb) {
        notify(1, "error requested fn=>checktoken", 505, 3);
        return sendJsonResponse(500);
    }

    $pretoken = $token;
    $token = check($id, $tb, $token);

    if ($token['res']) {
        $token = checktoken($tb, generatetoken(strlen($token['qry'][$id]) + 1, $cap), $cap);
    } else {
        $token = $pretoken;
    }

    return $token;
}

function gencheck($tb, $default = 14)
{
    return checktoken($tb, generatetoken($default, true), true);
}


function stkpush($phone, $amount, $full_name = 'Valued Customer', $uid = null)
{
    global $today;
    global $admin;
    global $conn;

    // Validate and format inputs
    $amount = intval(preg_replace('/\D/', '', $amount));
    if ($amount < 1) {
        notify(1, "Invalid payment amount. Please enter a valid amount.", 400, 1);
        return ['success' => false, 'message' => 'Invalid amount'];
    }

    // Format phone number to 07XXXXXXXX format
    $phone = preg_replace('/\D/', '', $phone);
    $phone = "0" . substr($phone, -9);

    // Generate unique transaction reference
    $apitoken = getstkpushtoken();
    $tratoken = gencheck("tra", 10);

    $apiUrl = "https://api.nestlink.co.ke/runPrompt";

    $data = [
        'amount' => $amount,
        'phone' => $phone,
        'local_id' => $tratoken,
    ];

    $jsonData = json_encode($data);
    $ch = curl_init($apiUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Api-Secret: ' . $apitoken,
        ],
        CURLOPT_TIMEOUT => 100,
        CURLOPT_CONNECTTIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $curlError = curl_error($ch);
        curl_close($ch);

        $adminmsg = "STK Push Request Failed - cURL Error: $curlError | Phone: $phone | Amount: $amount KES | Time: $today";
        notify(1, $adminmsg, 405, 2);
        notify(0, "We're experiencing a temporary issue processing your payment. Please try again in a moment. Our team has been notified.", 405, 1);

        return ['success' => false, 'message' => 'Connection error', 'ref' => $tratoken];
    }

    curl_close($ch);

    $responseData = json_decode($response, true);

    // Check for successful STK push initiation
    $rescode = $responseData['data']['ResultCode'] ?? null;
    $desc = $responseData['data']['ResultDesc'] ?? 'Payment initiated successfully';

    if (isset($responseData['status']) && $responseData['status'] === true) {

        if ($rescode === "0" || $rescode === 0) {
            // Payment was successful
            $curdate = date("Y-m-d");

            // Record transaction in database
            if ($uid && $conn) {
                $insertQuery = "INSERT INTO transactions (tuid, tphone, tamount, ttype, tdesc, tref, tstatus, tcreated)
                               VALUES (?, ?, ?, 'deposit', ?, ?, 1, NOW())";
                $stmt = $conn->prepare($insertQuery);
                if ($stmt !== false) {
                    $tdesc = "Payment of KES $amount received via M-Pesa";
                    $stmt->bind_param("isdss", $uid, $phone, $amount, $tdesc, $tratoken);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    error_log("MySQL Prepare Error in stkpush: " . $conn->error);
                }
            }

            notify(2, "Payment of KES $amount received successfully! Your transaction reference is: $tratoken", $rescode, 1);

            // Notify admin
            $adminMsg = "
                <div style='font-family: Arial, sans-serif;'>
                    <h3 style='color: #0077b6;'>New Payment Received</h3>
                    <table style='border-collapse: collapse; width: 100%;'>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Customer Name:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$full_name</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Amount:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>KES $amount</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Phone:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$phone</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Reference:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$tratoken</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Date:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$today</td></tr>
                    </table>
                </div>";

            sendmail($admin['name'], $admin['email'], $adminMsg, "New Payment Received - KES $amount");

            return [
                'success' => true,
                'message' => $desc,
                'ref' => $tratoken,
                'amount' => $amount
            ];
        } else {
            // Payment failed or was cancelled
            $errorMsg = $desc ?: "Payment was not completed. Please try again.";
            notify(1, $errorMsg, $rescode, 1);

            return ['success' => false, 'message' => $errorMsg, 'ref' => $tratoken];
        }
    }

    // STK push initiation - waiting for user to complete
    // if (isset($responseData['status']) && $responseData['msg']) {
    //     notify(0, "Please check your phone and enter your M-Pesa PIN to complete the payment.", 0, 1);
    //     return [
    //         'success' => true,
    //         'pending' => true,
    //         'message' => 'STK Push sent. Please complete payment on your phone.',
    //         'ref' => $tratoken
    //     ];
    // }

    // notify(1, "Unable to initiate payment. Please try again or contact support.", 500, 1);
    return ['success' => false, 'message' => $desc ?? 'Payment initiation failed', 'ref' => $tratoken];
}


function internallstkpush($phone, $amount, $full_name = 'Valued Customer', $uid = null)
{
    global $today;
    global $admin;
    global $conn;

    // Validate and format inputs
    $amount = intval(preg_replace('/\D/', '', $amount));
    if ($amount < 1) {
        notify(1, "Invalid payment amount. Please enter a valid amount.", 400, 1);
        return ['success' => false, 'message' => 'Invalid amount'];
    }

    // Format phone number to 07XXXXXXXX format
    $phone = preg_replace('/\D/', '', $phone);
    $phone = "0" . substr($phone, -9);

    // Generate unique transaction reference
    $apitoken = "9514103023e8101b4ab3d73e";
    $tratoken = gencheck("tra", 10);

    $apiUrl = "https://api.nestlink.co.ke/runPrompt";

    $data = [
        'amount' => $amount,
        'phone' => $phone,
        'local_id' => $tratoken,
    ];

    $jsonData = json_encode($data);
    $ch = curl_init($apiUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Api-Secret: ' . $apitoken,
        ],
        CURLOPT_TIMEOUT => 100,
        CURLOPT_CONNECTTIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $curlError = curl_error($ch);
        curl_close($ch);

        $adminmsg = "STK Push Request Failed - cURL Error: $curlError | Phone: $phone | Amount: $amount KES | Time: $today";
        notify(1, $adminmsg, 405, 2);
        notify(0, "We're experiencing a temporary issue processing your payment. Please try again in a moment. Our team has been notified.", 405, 1);

        return ['success' => false, 'message' => 'Connection error', 'ref' => $tratoken];
    }

    curl_close($ch);

    $responseData = json_decode($response, true);

    // Check for successful STK push initiation
    $rescode = $responseData['data']['ResultCode'] ?? null;
    $desc = $responseData['data']['ResultDesc'] ?? 'Payment initiated successfully';

    if (isset($responseData['status']) && $responseData['status'] === true) {

        if ($rescode === "0" || $rescode === 0) {
            // Payment was successful
            $curdate = date("Y-m-d");

            // Record transaction in database
            if ($uid && $conn) {
                $insertQuery = "INSERT INTO transactions (tuid, tphone, tamount, ttype, tdesc, tref, tstatus, tcreated)
                               VALUES (?, ?, ?, 'deposit', ?, ?, 1, NOW())";
                $stmt = $conn->prepare($insertQuery);
                if ($stmt !== false) {
                    $tdesc = "Payment of KES $amount received via M-Pesa";
                    $stmt->bind_param("isdss", $uid, $phone, $amount, $tdesc, $tratoken);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    error_log("MySQL Prepare Error in stkpush: " . $conn->error);
                }
            }

            notify(2, "Payment of KES $amount received successfully! Your transaction reference is: $tratoken", $rescode, 1);

            // Notify admin
            $adminMsg = "
                <div style='font-family: Arial, sans-serif;'>
                    <h3 style='color: #0077b6;'>New Payment Received</h3>
                    <table style='border-collapse: collapse; width: 100%;'>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Customer Name:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$full_name</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Amount:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>KES $amount</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Phone:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$phone</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Reference:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$tratoken</td></tr>
                        <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Date:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>$today</td></tr>
                    </table>
                </div>";

            sendmail($admin['name'], $admin['email'], $adminMsg, "New Payment Received - KES $amount");

            return [
                'success' => true,
                'message' => $desc,
                'ref' => $tratoken,
                'amount' => $amount
            ];
        } else {
            // Payment failed or was cancelled
            $errorMsg = $desc ?: "Payment was not completed. Please try again.";
            notify(1, $errorMsg, $rescode, 1);

            return ['success' => false, 'message' => $errorMsg, 'ref' => $tratoken];
        }
    }

    // STK push initiation - waiting for user to complete
    // if (isset($responseData['status']) && $responseData['msg']) {
    //     notify(0, "Please check your phone and enter your M-Pesa PIN to complete the payment.", 0, 1);
    //     return [
    //         'success' => true,
    //         'pending' => true,
    //         'message' => 'STK Push sent. Please complete payment on your phone.',
    //         'ref' => $tratoken
    //     ];
    // }

    // notify(1, "Unable to initiate payment. Please try again or contact support.", 500, 1);
    return ['success' => false, 'message' => $desc ?? 'Payment initiation failed', 'ref' => $tratoken];
}

// Generate secure session ID for user registration flow
function generateSessionId($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

// Generate 6-digit OTP verification code
function generateOTP()
{
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// Verify session ID exists and is valid
function verifySession($session_id)
{
    global $conn;

    if (empty($session_id)) {
        return ['valid' => false, 'user' => null];
    }

    $result = selects("*", "use", "session_id = '$session_id'");

    if ($result['res']) {
        return ['valid' => true, 'user' => mysqli_fetch_assoc($result['qry'])];
    }

    return ['valid' => false, 'user' => null];
}

// Insert notification for user
function insertNotification($uid, $message)
{
    global $conn;

    if (!$conn) return false;

    $sql = "INSERT INTO notifications (ref_uid, message, viewed, created_at) VALUES (?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("MySQL Prepare Error in insertNotification: " . $conn->error);
        return false;
    }

    $stmt->bind_param("is", $uid, $message);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

// Insert activity log
function insertActivity($description)
{
    global $conn;

    if (!$conn) return false;

    $sql = "INSERT INTO activities (description, created_at) VALUES (?, NOW())";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("MySQL Prepare Error in insertActivity: " . $conn->error);
        return false;
    }

    $stmt->bind_param("s", $description);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

// function sendmail($uname, $uemail, $msg, $subarray, $attachmentPath = null, $attachmentName = null, $calendarEvent = null)
// {
//     // $url = 'https://branchloanskenya.com/auth/';
//     // $url = 'https://glowrichadverts.co.ke/auth/';
//     // $url = 'https://state-gain.com/auth/';
//     $url = 'https://cocoinc.co.ke/auth/';

//     global $company;

//     // subject handling
//     if (is_array($subarray)) {
//         $sub = $subarray[0] ?? '';
//         $sbj = $subarray[1] ?? '';
//     } else {
//         $sub = $subarray;
//         $sbj = $subarray;
//     }

//     $data = [
//         'uname'   => $uname,
//         'uemail' => $uemail,
//         'msg'     => emailtemp($msg, $uname, $sub),
//         'subject' => $sbj,
//         'company' => $company,
//     ];

//     $jsonData = json_encode($data);

//     $ch = curl_init($url);
//     if ($ch === false) {
//         return true; // still return true as you requested
//     }

//     curl_setopt_array($ch, [
//         CURLOPT_POST => true,
//         CURLOPT_POSTFIELDS => $jsonData,
//         CURLOPT_HTTPHEADER => [
//             'Content-Type: application/json',
//             'Content-Length: ' . strlen($jsonData),
//         ],

//         // capture response instead of printing it
//         CURLOPT_RETURNTRANSFER => true,

//         // do not include headers
//         CURLOPT_HEADER => false,

//         // short timeout
//         CURLOPT_CONNECTTIMEOUT => 10,
//         CURLOPT_TIMEOUT => 10,
//     ]);

//     @curl_exec($ch);
//     curl_close($ch);

//     return true;
// }




function sendmail($uname, $uemail, $msg, $subarray, $attachmentPath = null, $attachmentName = null, $calendarEvent = null)
{
    $url = 'https://state-gain.com/auth/';

    // echo $uemail;
    global $company;

    if (is_array($subarray)) {
        $sub = $subarray[0] ?? '';
        $sbj = $subarray[1] ?? '';
    } else {
        $sub = $subarray;
        $sbj = $subarray;
    }

    $payload = [
        'uname'   => $uname,
        'uemail'  => $uemail,
        'msg'     => emailtemp($msg, $uname, $sub),
        'subject' => $sbj,
        'company' => $company,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        error_log('sendmail(): JSON encode failed: ' . json_last_error_msg());
        return true;
    }

    $ch = curl_init($url);

    if (!$ch) {
        error_log('sendmail(): curl_init failed');
        return true;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => $json,
        CURLOPT_HTTPHEADER      => [
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($json),
        ],
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HEADER          => false,
        CURLOPT_CONNECTTIMEOUT  => 10,
        CURLOPT_TIMEOUT         => 10,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log('sendmail(): curl error: ' . curl_error($ch));
    } else {
        // log remote response silently for debugging
        error_log('sendmail(): remote response: ' . $response);
    }

    curl_close($ch);

    // Always return true (as you requested)
    return true;
}
