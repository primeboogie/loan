<?php
require 'config.php';

date_default_timezone_set('Africa/Nairobi');

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
    $logoUrl = $domain . '/images/logo.png';

    // Compact, modern email template
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
            background-color: #f5f5f5;
            padding: 15px;
            line-height: 1.6;
        }

        .email-wrapper {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #a24cd2 0%, #c469cc 100%);
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }

        .logo-container {
            background: #ffffff;
            width: 60px;
            height: 60px;
            margin: 0 auto 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .logo {
            max-width: 50px;
            max-height: 50px;
            object-fit: contain;
            display: block;
        }

        .company-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .email-subject {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.95;
        }

        .email-body {
            padding: 25px;
            color: #333333;
        }

        .greeting {
            font-size: 15px;
            margin-bottom: 18px;
            color: #555555;
        }

        .message-content {
            font-size: 14px;
            color: #555555;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .cta-container {
            text-align: center;
            margin: 25px 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #a24cd2, #c469cc);
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 3px 8px rgba(162, 76, 210, 0.3);
            transition: transform 0.2s ease;
        }

        .cta-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(162, 76, 210, 0.4);
        }

        .email-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #888888;
        }

        .footer-note {
            margin-bottom: 12px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 3px solid #a24cd2;
        }

        .footer-links {
            margin-top: 15px;
            font-size: 11px;
            color: #999999;
        }

        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }

            .email-body {
                padding: 20px 15px;
            }

            .cta-button {
                padding: 10px 24px;
                font-size: 13px;
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

            <div class='email-footer'>
                <div class='footer-note'>
                    <strong>Security Reminder:</strong> Never share your login credentials with anyone.
                </div>

                <div style='margin-top: 12px;'>
                    © " . date('Y') . " $company. All rights reserved.
                </div>

                <div class='footer-links'>
                    This email was sent to you as a member of $company.<br>
                    If you have any questions, please contact our support team.
                </div>
            </div>
        </div>
    </div>
</body>
</html>";

    return $emailContent;
}

// <div class='email-subject'>$sub</div>
// <div class='company-name'>$company</div>
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




function generateICS($eventDetails)
{
    $eventName = $eventDetails['name'];
    $eventDescription = $eventDetails['description'];
    $eventStart = $eventDetails['start']; // Format: YYYYMMDDTHHMMSSZ
    $eventEnd = $eventDetails['end']; // Format: YYYYMMDDTHHMMSSZ
    $eventLocation = $eventDetails['location'];

    $icsContent = "BEGIN:VCALENDAR
    VERSION:2.0
    BEGIN:VEVENT
    SUMMARY:$eventName
    DESCRIPTION:$eventDescription
    DTSTART:$eventStart
    DTEND:$eventEnd
    LOCATION:$eventLocation
    END:VEVENT
    END:VCALENDAR";

    return $icsContent;
}

// Example event details
$eventDetails = [
    'name' => 'Meeting with Client',
    'description' => 'Discuss project requirements and timelines.',
    'start' => '20220317T090000Z', // Example: March 17, 2022, 09:00 AM (UTC)
    'end' => '20220317T100000Z',   // Example: March 17, 2022, 10:00 AM (UTC)
    'location' => '123 Main St, City'
];

// $icsContent = generateICS($eventDetails);

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



function memory()
{
    $startMemory = memory_get_usage();

    $allusers = alluser();
    // $query  = "SELECT * FROM users";
    // $allusers = comboselects($query);

    // while ($new = mysqli_fetch_assoc($allusers['qry'])) {
    //     $data[] = [
    //         "uid" => $new['uid'],
    //         "username" => $new['uname'],
    //     ];
    // }

    $endMemory = memory_get_usage();
    $peakMemory = memory_get_peak_usage();

    $array['memory'] = [
        'used' => formatBytes($endMemory - $startMemory),
        'peak' => formatBytes($peakMemory),
        // 'data' => $allusers,
        // 'data' => $data,
        // 'data' =>  [],
    ];

    sendJsonResponse(200, true, null, $array);
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


function comboselectsold($query, $datatype =  2)
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
        case "aff":
            $array['tb'] = "affiliatefee";
            $array['id'] = "cid";
            break;
        case "bal":
            $array['tb'] = "balances";
            $array['id'] = "buid";
            break;
        case "cou":
            $array['tb'] = "countrys";
            $array['id'] = "cid";
            break;
        case "ses":
            $array['tb'] = "session";
            $array['id'] = "sid";
            break;
        case "sit":
            $array['tb'] = "site";
            $array['id'] = "sid";
            break;
        case "spi":
            $array['tb'] = "spin_records";
            $array['id'] = "s_id";
            break;
        case "tra":
            $array['tb'] = "transactions";
            $array['id'] = "tid";
            break;
        case "use":
            $array['tb'] = "users";
            $array['id'] = "uid";
            break;
        case "pym":
            $array['tb'] = "payment_method";
            $array['id'] = "tid";
            break;
        case "pyp":
            $array['tb'] = "payment_procedure";
            $array['id'] = "pid";
            break;
        case "user":
            $array['tb'] = "userteam";
            $array['id'] = "id";
            break;
        case "soc":
            $array['tb'] = "social_videos";
            $array['id'] = "id";
            break;
        case "act":
            $array['tb'] = "activities";
            $array['id'] = "id";
            break;
        case "flu":
            $array['tb'] = "fluttercredentials";
            $array['id'] = "fid";
            break;
        case "qui":
            $array['tb'] = "quizzes";
            $array['id'] = "qid";
            break;
        case "wit":
            $array['tb'] = "withdrawalcharges";
            $array['id'] = "wid";
            break;
        case "acd":
            $array['tb'] = "activite_date";
            $array['id'] = "aid";
            break;
        case "trw":
            $array['tb'] = "transactionwebhooks";
            $array['id'] = "wid";
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

function login()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(400);
    }
    $inputs = jDecode();

    $errors = false;

    if (!isset($inputs['username']) || !mytrim($inputs['username'])) {
        notify(1, "Username required", 506, 1);
        $errors = true;
    }

    if (!isset($inputs['password']) || !mytrim($inputs['password'])) {
        notify(1, "Password required", 508, 1);
        $errors = true;
    }

    if ($errors) {
        return sendJsonResponse(422);
    }

    $uname = Ucap(mytrim($inputs['username']));
    $password = $inputs['password'];

    $confirm = selects("*", "use", "uname = '$uname'", 1);
    if (!$confirm['res']) {
        notify(1, "Username not found", 515, 1);
        return sendJsonResponse(403);
    }
    $confirmData = mysqli_fetch_assoc($confirm['qry']);
    if ($confirmData['active'] != 1) {
        notify(1, "Account is Suspended Please Contact Your Upline", 516, 1);
        return sendJsonResponse(403);
    }

    $hashpass = $confirmData['upass'];
    if (password_verify($password, $hashpass)) {

        $uid = $confirmData['uid'];

        $today =  date("Y-m-d H:i:s");
        deletes("ses", "sexpiry <= '$today'");
        $confirmsessions = selects("*", "ses", "suid = '$uid' and sexpiry >= '$today' LIMIT 1", 1);

        if ($confirmsessions['res']) {
            $stoken = mysqli_fetch_assoc($confirmsessions['qry'])['stoken'];
            $msg = "Login Was Successful Dear $uname";
            notify(2, $msg, 519, 1);
            $_SESSION['suid'] = $uid;
            data();
            $result =
                [
                    "access_token" => $stoken,
                    "user_data" => [
                        "userdetails" => $_SESSION['query']['data'],
                        "balances" => $_SESSION['query']['conv'],
                        "fee" => $_SESSION['query']['fee'],

                    ]
                ];

            return sendJsonResponse(200, true, null, $result);
        } else {
            $stoken = generatetoken(82);
            $ssid = gencheck("ses");

            $thirtyMinutes = date("Y-m-d H:i:s", strtotime("+1 hoursc"));

            $session = inserts("ses", "sid,suid,stoken,sexpiry", ['ssss', $ssid, $uid, $stoken, $thirtyMinutes]);
            if ($session) {
                $msg = "Login Was Successful Dear $uname";
                notify(2, $msg, 520, 1);
                $_SESSION['suid'] = $uid;
                data();
                $result =
                    [
                        "access_token" => $stoken,
                        "user_data" => [
                            "userdetails" => $_SESSION['query']['data'],
                            "balances" => $_SESSION['query']['conv'],
                            "fee" => $_SESSION['query']['fee'],
                        ]
                    ];

                return sendJsonResponse(200, true, null, $result);
            }
        }
    } else {
        notify(1, "Invalid Password", 517, 1);
        return sendJsonResponse(403);
    }
}




function sessioned()
{
    if (isset($_SESSION['suid']) && isset($_SESSION['query'])) {
        return true;
    }
    sendJsonResponse(403);
}

function auths()
{
    $response = [];
    $response['env'] = False;
    $response['status'] = false;
    $token = mytrim(isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null);
    $today =  date("Y-m-d H:i:s");

    $confirmsessions = selects("*", "ses", "stoken = '$token' and sexpiry >= '$today' LIMIT 1", 1);

    if ($confirmsessions['res']) {
        $_SESSION['suid'] = mysqli_fetch_assoc($confirmsessions['qry'])['suid'];
        data();
        if (isset($_SESSION['query'])) {
            $response['status'] = true;
            $response['actual'] = $_SESSION['query']['conv']['actbals'];
            $response['cid'] = $_SESSION['query']['data']['cid'];
            $response['lastupdate'] = adminsite()['lastupdate'];
            if ($_SESSION['query']['data']['status'] == 2) {
                $response['env'] = true;
            }
        }
    }

    return $response;
}


function auth()
{
    $confirm = auths();
    if ($confirm['status']) {
        return sendJsonResponse(200, true, null, $confirm);
    } else {
        return sendJsonResponse(401, false, null, $_COOKIE);
    }
}


function freeuser()
{
    $inputs = jDecode();

    if (!isset($inputs['username'])) {
        sendJsonResponse(404);
    }
    $uname = Ucap(mytrim($inputs['username']));
    $freeuser = check("uname", "use", $uname);
    if ($freeuser['res']) {
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(404);
    }
}


function freeemail()
{
    $inputs = jDecode();

    if (!isset($inputs['email'])) {
        sendJsonResponse(404);
    }
    $email = mytrim($inputs['email']);
    $freeuser = check("uemail", "use", $email);
    if ($freeuser['res']) {
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(404);
    }
}

function freephone()
{
    $inputs = jDecode();

    if (!isset($inputs['phone'])) {
        sendJsonResponse(404);
    }
    $phone = mytrim($inputs['phone']);
    $freeuser = check("uphone", "use", $phone);
    if ($freeuser['res']) {
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(404);
    }
}

function stkpush()
{
    $inputs = jDecode();

    if (!isset($inputs['amount']) || !isset($inputs['phone'])) {
        sendJsonResponse(404);
    }
    $amount = mytrim($inputs['amount']);
    $phone = "0" . substr(preg_replace('/\D/', '', $inputs['phone']), -9);


    $array = [];
    $apitoken = getstkpushtoken();
    global $today;
    global $admin;

    $tratoken = checktoken("tra", generatetoken(4, true), true);


    if (sessioned()) {

        $uid = $_SESSION['suid'];
        $apiUrl = "https://api.nestlink.co.ke/runPrompt";

        $data = [
            'amount' => $amount,
            'phone' => $phone,
            'local_id' => $tratoken, // Your UNIQUE Tranaction id from your table
        ];
        // 'local_id' => $tratoken, // Your UNIQUE Tranaction id from your table

        $jsonData = json_encode($data);
        $ch = curl_init($apiUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Api-Secret: ' . $apitoken,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            // notify(1,'cURL error: ' . curl_error($ch),405, 3);
            $adminmsg = "We are Experiencing  an issue requesting/receving Safaricom STK-PUSH Currently You'll be notified on the next Email as 
        The Support-Team is working on it $today Many-Regards";
            notify(1, $adminmsg, 405, 2);
            notify(0, "Request Was not sent Please hold as we resolve this issue Kind Regards", 405, 1);
            return $array;
        }

        curl_close($ch);

        $response = json_decode($response, true);

        $rescode = $response['data']['ResultCode'] ?? null;
        $desc = $response['data']['ResultDesc']  ?? null;


        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $l1 = $data['l1'];
        $uplineid = $data['uplineid'];

        $uname = $data['uname'];

        $prebalance = $bal['balance'];
        $predeposit = $bal['deposit'];

        // sendJsonResponse(200, true, null, $response);
        if ($response['status'] === true && isset($response['data']) && $response['data']['ResultCode'] === "0") {


            $upbal = updates("bal", "deposit = deposit + '$amount'", "buid='$uid'");

            if ($upbal['res'] == true) {
                data();

                // $newdata = $_SESSION['query']['data']; 
                $newbal = $_SESSION['query']['bal'];

                $balance = $newbal['balance'];
                $deposit = $newbal['deposit'];

                $instra =   insertstrans($tratoken, $uid, $uname, $phone, "Account Deposit", "7", 'KDOE', `NULL`, $amount, '2', $prebalance, $balance, $predeposit, $deposit, $today, $today, $l1, $uplineid, 2);

                if ($instra['res'] === false) {
                    notify(1, "qry->stkpush", 407, 3);
                }
            } else {
                notify(1, "qry->stkpush ", 408, 3);
            }

            $curdate = date("Y-m-d");
            $totaldip = mysqli_fetch_assoc(selects("SUM(tamount)", "tra", "tcat = '7' AND tstatus = '2' AND tdate like '%$curdate%'", 1)['qry'])[0] ?? "1";
            $totalwith = mysqli_fetch_assoc(selects("SUM(tamount)", "tra", "tcat = '3' AND tdate like '%$curdate%'", 1)['qry'])[0] ?? "1";
            $amount = $amount . " KES";
            notify(2, $desc, "$rescode", 1);
            $msg = " Confirmed New-Deposit;
            <ul>
            <li>Name => $uname</li>
            <li>Upline => $l1</li>
            <li>Amount => $amount</li>
            <li>Phone => $phone</li>
            <li>Total Deposit => $totaldip</li>
            <li>Total Withdrawal => $totalwith</li>
            </ul>
            You'll Be Notified On the Next Transaction, Deposit Approved Worth $amount";
            $subject = "New Deposit Approved";

            sendmail($admin['name'], $admin['email'], $msg, $subject);

            $array['desc'] = $desc;
            $array['res'] = true;
            unset($array['qry']);
        } else {
            $instra  = inserts(
                "tra",
                "tid,tuid,tuname,ttype,tcat,tamount,tstatus,tprebalance,tbalance,tpredeposit,tdeposit,tdate,tduedate,trefuname,trefuid,payment_type,ref_payment",
                ['sssssisiiiissssss', $tratoken, $uid, $uname, "Deposit", '7', $amount, 1, $prebalance, $prebalance, $predeposit, $predeposit, $today, $today, $uname, $uid, 'KDOE', `NULL`]
            );
            notify(0, $desc, "stk->$desc", 1);
            $array['qry'] = $desc;
            $array['code'] = $rescode;
        }
    }

    return sendJsonResponse(200, true, null);
}

function sendmail($uname, $uemail, $msg, $subarray, $attachmentPath = null, $attachmentName = null, $calendarEvent = null)
{
    $url = 'https://super-qash.com/auth/';
    // $url = 'https://cocoinc.co.ke/auth/';


    $sub = $subarray;
    $sbj = $subarray;
    global $company;

    if (is_array($subarray)) {
        $sub = $subarray[0];
        $sbj = $subarray[1];
    }

    $data = [
        'uname' => $uname,
        'uemail' => $uemail,
        'msg' => emailtemp($msg, $uname, $sub),
        'subject' => $sbj,
        'company' => $company,
    ];


    $jsonData = json_encode($data);

    $ch = curl_init($url);
    if ($ch === false) {
        error_log('Failed to initialize cURL');
        return;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Don't wait for the response
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);

    // Set a longer timeout and enable verbose output
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout in seconds
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w')); // Output verbose info to stderr

    // Execute the request
    $result = curl_exec($ch);

    // Check for errors
    // if ($result === false) {
    //     error_log('cURL error: ' . curl_error($ch));
    // } else {
    //     // error_log('Request successful');
    // }

    curl_close($ch);


    // global $admin;


    // $emails = getemails();

    // $thost = $emails['thost'];
    // $tuser = $emails['tuser'];
    // $tpass = $emails['tpass'];
    // $tfrom = $emails['tuser'];

    // $attachmentPath = !empty($attachmentPath) ? $attachmentPath : null;
    // $attachmentName = !empty($attachmentName) ? $attachmentName : null;

    // $mail = new PHPMailer(true);

    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    // $mail->isSMTP();
    // $mail->SMTPAuth = true;
    // $mail->Host = $thost;
    // $mail->Port = 587;

    // $mail->Username = $tuser;
    // $mail->Password = $tpass;

    // $mail->setFrom($tfrom, $admin['company']);
    // $mail->addAddress($uemail, $uname);
    // // $mail->addReplyTo($admin['email'], $admin['company']);

    // $mail->Subject = $subject;
    // $mail->isHTML(true);
    // $mail->Body = emailtemp($msg);

    // // Check if an attachment is provided
    // if ($attachmentPath !== null) {
    //     $mail->addAttachment($attachmentPath, $attachmentName);
    // }

    // // Add Google Calendar event attachment
    // if ($calendarEvent !== null) {
    //     $mail->addStringAttachment($calendarEvent, 'event.ics', 'base64', 'text/calendar');
    // }

    // try {
    //     $mail->send();
    // } catch (Exception $e) {
    //     // Handle the exception (you can log it or show an error message)
    //     error_log("Mailer Error: " . $mail->ErrorInfo);
    //     // Optionally, you can set a flag or add a message for further processing
    //     echo $errorMessage = "Mailer Error: " . $mail->ErrorInfo;
    // }
    // return true;
}



function data()
{
    if (isset($_SESSION['suid'])) {

        $uid = $_SESSION['suid'];

        $dataq = "SELECT 
                u.*, 
                b.*, 
                c.*, 
                e.*, 
                w.*,
                u.active AS useractive, 
                u.subscription AS usersubscription, 
                e.active AS feeactive, 
                w.active AS tarrifactive, 
                u.l1 AS uplineid, 
                u.l2 AS l2id, 
                u.l3 AS l3id, 
                p.uname AS upline,
                ll.uname AS upline2,
                lll.uname AS upline3
            FROM users u 
            INNER JOIN balances b ON u.uid = b.buid 
            LEFT JOIN users p ON u.l1 = p.uid 
            LEFT JOIN users ll ON u.l2 = ll.uid 
            LEFT JOIN users lll ON u.l3 = lll.uid 
            INNER JOIN countrys c ON u.default_currency = c.cid 
            LEFT JOIN affiliatefee e ON u.default_currency = e.cid AND e.active = true 
            LEFT JOIN withdrawalcharges w ON u.default_currency = w.wcid AND w.active = true 
            WHERE u.uid = '$uid' AND u.active = true ORDER BY w.tariff ASC LIMIT 2;
            ";
        $userdata = comboselects($dataq, 1);

        if ($userdata['rows'] > 0) {
            // $dataquery2 = $dataquery;

            $totalq = "SELECT SUM(tamount) FROM transactions WHERE tuid = '$uid' AND tcat = 3 AND tstatus = 2";
            $totalquery = mysqli_fetch_assoc(comboselects($totalq, 1)['qry']);

            $pendingq = "SELECT SUM(tamount) FROM transactions WHERE tuid = '$uid' AND tcat = 3 AND tstatus = 0";
            $pendingquery = mysqli_fetch_assoc(comboselects($pendingq, 1)['qry']);

            $admin = adminsite();

            // sendJsonResponse(200, true, "", $dataquery);
            $tariff = [];
            $dataquery = mysqli_fetch_assoc(comboselects($dataq, 1)['qry']);

            // print_r($dataquery);
            if ($userdata['rows'] > 1) {
                while ($key = mysqli_fetch_assoc($userdata['qry'])) {
                    $tariff[] = [
                        'wid' => $key['wid'],
                        'wcid' => $key['wcid'],
                        'min_brackets' => conv($dataquery['crate'], $key['min_brackets'], true, false),
                        'max_brackets' => conv($dataquery['crate'], $key['max_brackets'], true, false),
                        'tariff' => conv($dataquery['crate'], $key['tariff'], true, false),
                    ];
                }
            }



            $dailybonus = datadailybonus($dataquery['uid']);

            $admintarget = floatval($admin['target']);
            $lastupdate = $admin['lastupdate'];
            $dailyprogress = $dailybonus['activel1'];
            $dailyrequired = $dailybonus['required'];
            $dataprogress = $admintarget - $dailyrequired;
            $dailystatus = $dailyrequired <= 0;
            $percent =  (string)floatval(($dataprogress / $admintarget) * 100);


            $userdata = [
                'uname' => $dataquery['uname'],
                'email' => $dataquery['uemail'],
                'phone' => $dataquery['uphone'],
                'status' => $dataquery['ustatus'],
                'account_status' => $dataquery['ustatus'] == 2 ? true : false,
                'l1' => $dataquery['upline'],
                'upline' => $dataquery['upline'],
                'l2' => $dataquery['upline2'],
                'l3' => $dataquery['upline3'],
                'uplineid' => $dataquery['uplineid'] ?? 'NONE',
                'l2id' => $dataquery['l2id'] ?? 'NONE',
                'l3id' => $dataquery['l3id'] ?? 'NONE',
                'active' => (int)$dataquery['useractive'],
                'country' => $dataquery['cname'],
                'cid' => $dataquery['default_currency'],
                'subscription' => $dataquery['subscription'],

                'abrv' => $dataquery['cuabrv'],
                'dial' => $dataquery['ccall'],
                'rate' => $dataquery['crate'],
                'ccurrency' => $dataquery['ccurrency'],
                'join' => $dataquery['ujoin'],
                'accactive' => $dataquery['accactive'],
                'grouplink' => $admin['slink'],
                'customer_care' => $admin['scare1'],
                'lastupdate' => $lastupdate,
                'isadmin' => $dataquery['isadmin'] == 1 ? true : false,
            ];

            $blacklist = [
                // 'Kian254',
                // 'Iragena',
                // 'Prime',
            ];
            if (in_array($dataquery['uname'], $blacklist)) {
                $userdata['grouplink'] = false;
            }


            $userbal = [
                'profit' => $dataquery['profit'],
                'balance' => $dataquery['balance'],
                'deposit' => $dataquery['deposit'],
                'youtube' => $dataquery['youtube'],
                'tiktok' => $dataquery['tiktok'],
                'trivia' => $dataquery['trivia'],
                'welcome' => $dataquery['welcome'],
                'spin' => $dataquery['spin'],
                'netflix' => $dataquery['meme'],
                'totalwithdrawal' => $totalquery[0] ?? 0,
                'nowithdrawal' => count($totalquery),
                'pendingwithdrawal' => $pendingquery[0] ?? 0,
                'target' => floatval($admin['target']),
                'reward' => floatval($admin['reward']),
            ];

            $required = $dataquery['creg'] - $dataquery['deposit'];

            $actbal = $required >= 0 ? $required : 0;

            $profit = $dataquery['profit'];

            if ($profit == $dataquery['cbonus']) {
                $profit = round(conv($dataquery['crate'], $dataquery['profit']), 0);
            } else {
                $profit = conv($dataquery['crate'], $dataquery['profit'], true, true);
            }

            $conv = [
                'profit' => $profit,
                'actbals' => $actbal,
                'actbal' => round(conv($dataquery['crate'], $actbal), 0),
                'expense' => round(conv($dataquery['crate'], $dataquery['creg']), 0),
                'balance' => conv($dataquery['crate'], $dataquery['balance'], true, true),
                // 'bonus' => conv($dataquery['crate'],$dataquery['cbonus'], true,false),
                'bonus' => round(conv($dataquery['crate'], $dataquery['cbonus']), 0),
                'deposit' => conv($dataquery['crate'], $dataquery['deposit'], true, true),
                'youtube' => conv($dataquery['crate'], $dataquery['youtube'], true, true),
                'tiktok' => conv($dataquery['crate'], $dataquery['tiktok'], true, true),
                'ads' => conv($dataquery['crate'], $dataquery['ads'], true, true),
                'trivia' => conv($dataquery['crate'], $dataquery['trivia'], true, true),
                'welcome' => conv($dataquery['crate'], $dataquery['welcome'], true, true),
                'spin' => conv($dataquery['crate'], $dataquery['spin'], true, true),
                'netflix' => round(conv($dataquery['crate'], $dataquery['meme'], true, false), 0),
                'totalwithdrawal' => conv($dataquery['crate'], $totalquery ?? 0, true, true),
                'pendingwithdrawal' => round(conv($dataquery['crate'], $pendingquery ?? 0, true, false)),
                'target' => floatval($admin['target']),
                'reward' => conv($dataquery['crate'], $admin['reward'], true, true),
                'percent' => round($percent),
                'progress' => floatval($dailyprogress),
                'remaining' => floatval($dailyrequired),
                'dailystatus' => $dailystatus,
            ];

            $fee = [
                // ! decide obn these Default Cuurency
                'reg' => floatval($dataquery['creg']) ?? 1293.16,
                'fl1' => floatval($dataquery['fl1']) ?? 646.58,
                'fl2' => floatval($dataquery['fl2']) ?? 323.29,
                'fl3' => floatval($dataquery['fl3']) ?? 129.32,
                'min_with' => floatval(conv($dataquery['crate'], $dataquery['min_with'], true, false)) ?? 12,
                'charges' => floatval($dataquery['charges']) ?? 129.32,
                'tariff' => $tariff,
            ];
            $_SESSION['query']['uid'] = $dataquery['uid'];
            $_SESSION['query']['upass'] = $dataquery['upass'];
            $_SESSION['query']['data'] = $userdata;
            $_SESSION['query']['bal'] = $userbal;
            $_SESSION['query']['conv'] = $conv;
            $_SESSION['query']['fee'] = $fee;
        } else {
            unset($_SESSION['suid']);
            unset($_SESSION['query']);
            return sendJsonResponse(403);
        }
    } else {
        notify(1, "No Session Available Please Login", 403, 1);
        sendJsonResponse(403);
    }
}


function userdata()
{
    if (sessioned()) {
        $senddata = [];
        $senddata['userdetails'] = $_SESSION['query']['data'];
        $senddata['balances'] = $_SESSION['query']['conv'];
        $senddata['fee'] = $_SESSION['query']['fee'];
        sendJsonResponse(200, true, null, $senddata);
    }
}



function adminsite()
{
    $response = [];

    $adminq = "SELECT * FROM site LIMIT 1";
    $adminquery = mysqli_fetch_assoc(comboselects($adminq, 1)['qry']);

    return $adminquery;
}

function currencyupdate()
{
    $inputs = jDecode();

    $ccurrency = $inputs['ccurrency'];
    $crate = $inputs['crate'];

    $query = updates('cou', "ccurrency = '$ccurrency', crate = '$crate'", "ccurrency = '$ccurrency'");
    if ($query['res']) {
        sendJsonResponse(200, true);
    } else {
        sendJsonResponse(201);
    }
}



function conv($cRate, $amount, $convert = true, $comma = false)
{

    $cRate = floatval($cRate);
    $amount = floatval($amount);

    if ($convert) {
        $amount *= $cRate;
    } else {
        $amount /= $cRate;
    }
    if ($comma) {
        return max(0, number_format($amount, 2));
    } else {
        return max(0, round($amount, 2));
    }
}


function activateaccount($notify = true)
{
    if (sessioned()) {

        // notify(2,"Hello $accname Please Wait till 2 PM to activate your Account Kind Regards",2,1);
        // return sendJsonResponse(200);

        global $company;

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];
        global $domain;
        global $today;
        $admin  = false;

        $accountstatus = $data['status'];
        $accrate = $data['rate'];
        $accname = $data['uname'];
        $accphone = $data['phone'];
        $accemail = $data['email'];
        $accccurrency = $data['ccurrency'];
        if ($accountstatus == 2) {
            $msg = "Account Already Activated";
            notify(0, $msg, 200, 1);
            return sendJsonResponse(200, true, null, $accountstatus);
        }


        $uid = $_SESSION['suid'];
        $deposit = $bal['deposit'];
        $balance = $bal['balance'];
        $reg = $fee['reg'];

        $l1 = $data['l1'];
        $uplineid = $data['uplineid'] ?? 0;
        $l2 = $data['l2'];
        $l3 = $data['l3'];

        if (isset($_SESSION['admin']) && $_SESSION['admin'] == true) {
            $admin = true;

            if ($deposit < $reg) {
                $req = $reg - $deposit;
                updates("bal", "deposit = deposit + '$req'", "buid = '$uid'");
                data();
                $data = $_SESSION['query']['data'];
                $bal = $_SESSION['query']['bal'];
                $fee = $_SESSION['query']['fee'];

                $deposit = $bal['deposit'];
                $balance = $bal['balance'];
            }
        }

        if ($deposit >= $reg) {
            $myupdate = updates("bal", "deposit = deposit - '$reg'", "buid = '$uid'");
            if ($myupdate['res']) {
                $activateacc = updates("use", "ustatus = 2, accactive = '$today'", "uid = '$uid'");
                $token = gencheck("tra", 8);
                $curdeposit = $deposit - $reg;
                insertstrans($token, $uid, $accname, $accphone, "Account Activation", "6", 'NONE', `NULL`, $reg, '2', $balance, $balance, $deposit, $curdeposit, $today, $today, $l1, $uplineid, 2);

                if ($activateacc['res']) {
                    $sbj = "Welcome to $company Connections ";
                    $msg = "
                    Dear $accname,<br>

                        Welcome aboard!<br><br>

                        We’re excited to have you as a part of the $company Connections family. Your journey to unlocking a world of earning opportunities starts now!<br><br>

                        Here’s what awaits you:<br><br>

                        1. <strong>💥 Welcome Bonus:</strong> Enjoy a bonus as soon as you activate your account.<br>
                        2. <strong>💫 Affiliate Marketing:</strong> Earn commissions by inviting friends and family, with rewards across three levels.<br>
                        3. <strong>🪙 Daily Bonuses:</strong> Meet your daily targets and earn rewards every day.<br>
                        4. <strong>📀 YouTube Videos:</strong> Get paid to watch premium educational YouTube content.<br>
                        5. <strong>📽️ TikTok Videos:</strong> Have fun watching TikTok videos and earn as you go.<br>
                        6. <strong>🎰 Spin and Win:</strong> Spin the wheel for a chance to win exciting prizes instantly.<br>
                        7. <strong>🧮 Trivia Questions:</strong> Challenge your mind with trivia and earn rewards.<br>
                        8. <strong>📈 Forex Trading:</strong> Learn and earn through forex trading with our guided resources.<br><br>

                        To access your dashboard and start exploring these features, simply log in here: <a href='https://earn-power.com'>earn-power.com</a><br><br>

                        If you need any assistance, our 24/7 customer service team is always ready to help.<br><br>

                        Welcome again, and here’s to your success with $company Connections!<br><br>

                        Best regards,<br>  
                        The $company Connections Team<br>  
                        Powered by ZanyTech Co. Ltd<br>
                        ";

                    if ($notify) {
                        sendmail($accname, $accemail, $msg, $sbj);
                    }
                    //  l1 is paid
                    $confiml1 = others($l1);
                    if ($confiml1['res']) {

                        $l1ebuid = $confiml1['query']['uid'];
                        $l1email = $confiml1['query']['data']['email'];
                        $l1phone = $confiml1['query']['data']['phone'];
                        $l1default_currency = $confiml1['query']['data']['default_currency'];


                        $prebalance = $confiml1['query']['bal']['balance'];
                        $predeposit = $confiml1['query']['bal']['deposit'];

                        $l1fee = $fee['fl1'];
                        $l1feeconv = $accccurrency . " " . round(conv($accrate, $fee['fl1']));

                        $accupdate = updates("bal", "balance = balance + '$l1fee', profit = profit + '$l1fee', way1 = way1 + '$l1fee'", "buid = '$l1ebuid'");
                        if ($accupdate['res']) {

                            $confiml1 = others($l1);
                            $curbalance = $confiml1['query']['bal']['balance'];
                            $curdeposit = $confiml1['query']['bal']['deposit'];

                            $token = gencheck("tra", 8);
                            insertstrans($token, $l1ebuid, $l1, $l1phone, "Level 1 Earnings", "2", 'NONE', `NULL`, $l1fee, '2', $prebalance, $curbalance, $predeposit, $curdeposit, $today, $today, $accname, $uid, 2);

                            $sbj = "Level-1 Earnings";
                            $msg = "
                             <strong>You've Just Earned $l1feeconv from $accname</strong>  <br>

                                Visit your dashboard to see your earnings and work smarter for more .

                                <a href='$domain'>  Login Here</a> <br>

                                Keep up the great work and continue earning big with ZanyTech! 
                            ";
                            if ($l1default_currency == 'KEST') {

                                $l1sms = "Great news! 
You have Just Earned $l1feeconv from $accname 
Login Here to check dashboard: $domain 
Keep Earning with EarnPower!";
                                sendsms($l1phone, $l1sms);
                            }
                            sendmail($l1, $l1email, $msg, $sbj);
                        }
                    }
                    //  l2 is paid
                    $confiml2 = others($l2);
                    if ($confiml2['res']) {

                        $l2ebuid = $confiml2['query']['uid'];
                        $l2email = $confiml2['query']['data']['email'];
                        $l2phone = $confiml2['query']['data']['phone'];

                        $prebalance = $confiml2['query']['bal']['balance'];
                        $predeposit = $confiml2['query']['bal']['deposit'];

                        $l2fee = $fee['fl2'];
                        $l2feeconv = $accccurrency . " " . round(conv($accrate, $fee['fl2']));

                        $accupdate = updates("bal", "balance = balance + '$l2fee', profit = profit + '$l2fee', way1 = way1 + '$l2fee'", "buid = '$l2ebuid'");
                        $confiml2 = others($l2);

                        $curbalance = $confiml2['query']['bal']['balance'];
                        $curdeposit = $confiml2['query']['bal']['deposit'];

                        $token = gencheck("tra", 8);
                        insertstrans($token, $l2ebuid, $l2, $l2phone, "Level 2 Earnings", "2", 'NONE', `NULL`, $l2fee, '2', $prebalance, $curbalance, $predeposit, $curdeposit, $today, $today, $accname, $uid, 2);

                        if ($accupdate['res']) {
                            $sbj = "Level-2 Earnings";
                            $msg = "
                             <strong>   You've Just Earned $l2feeconv from $l1</strong>  <br>

                                Visit your dashboard to see your earnings and work smarter for more .

                                <a href='$domain'> Login Here</a> <br>

                                Keep up the great work and continue earning big with ZanyTech! 
                            ";
                            sendmail($l2, $l2email, $msg, $sbj);
                        }
                    }

                    $confiml3 = others($l3);
                    if ($confiml3['res']) {

                        $l3ebuid = $confiml3['query']['uid'];
                        $l3email = $confiml3['query']['data']['email'];
                        $l3phone = $confiml3['query']['data']['phone'];


                        $prebalance = $confiml3['query']['bal']['balance'];
                        $predeposit = $confiml3['query']['bal']['deposit'];


                        $l3fee = $fee['fl3'];
                        $l3feeconv = $accccurrency . " " . round(conv($accrate, $fee['fl3']));

                        $accupdate = updates("bal", "balance = balance + '$l3fee', profit = profit + '$l3fee', way1 = way1 + '$l3fee'", "buid = '$l3ebuid'");

                        $confiml3 = others($l3);

                        $curbalance = $confiml3['query']['bal']['balance'];
                        $curdeposit = $confiml3['query']['bal']['deposit'];

                        $token = gencheck("tra", 8);
                        insertstrans($token, $l3ebuid, $l3, $l3phone, "Level 3 Earnings", "2", 'NONE', `NULL`, $l3fee, '2', $prebalance, $curbalance, $predeposit, $curdeposit, $today, $today, $accname, $uid, 2);

                        if ($accupdate['res']) {
                            $sbj = "Level-3 Earnings";
                            $msg = "
                             <strong> You've Just Earned $l3feeconv from $l2</strong>  <br>

                                Visit your dashboard to see your earnings and work smarter for moree .

                                <a href='$domain'> Login Here</a> <br>

                                Keep up the great work and continue earning big with ZanyTech! 
                            ";
                            sendmail($l3, $l3email, $msg, $sbj);
                        }
                    }
                    // if($admin){
                    //     updates("bal","deposit = deposit - '$reg'","buid = '$uid'");

                    // }
                    notify(2, "Fantastic News! 🎉 🎉  Account Acctivated Successfully for $accname", 200, 1);
                    return sendJsonResponse(200);
                } else {
                    $msg = "Failed To Activate Account, Please Try Again";
                    notify(0, $msg, 200, 1);
                    notify(0, $msg, 200, 3);
                    return sendJsonResponse(500, false, null, $accountstatus);
                }
            }
        } else {
            $msg = "You Have insufficient Funds Account, Please Recharge Your Account To Activate";
            notify(0, $msg, 403, 1);
            return sendJsonResponse(403, false, null, $accountstatus);
        }
    }
}


function others($uid = null)
{

    $response = [];
    $response['res'] = false;
    if ($uid) {

        $dataq = "SELECT u.*, u.active AS useractive,b.*, c.*, e.*, e.active AS feeactive, u.l1 AS upline FROM users u 
    INNER JOIN balances b 
    ON u.uid = b.buid 
    INNER JOIN countrys c 
    ON u.default_currency = c.cid 
    LEFT JOIN affiliatefee e
    ON u.default_currency = e.cid AND e.active = true
    WHERE (u.uid = '$uid' OR u.uname = '$uid') AND u.active = true";

        $dataquery = mysqli_fetch_assoc(comboselects($dataq, 1)['qry']);

        if ($dataquery['res']) {
            $dataquery = mysqli_fetch_assoc($dataquery['qry']);
            $uid = $dataquery['uid'];

            $userdata = [
                'uname' => $dataquery['uname'],
                'email' => $dataquery['uemail'],
                'phone' => $dataquery['uphone'],
                'status' => $dataquery['ustatus'],
                'upline' => $dataquery['upline'],
                'l1' => $dataquery['l1'],
                'l2' => $dataquery['l2'],
                'l3' => $dataquery['l3'],
                'active' => (int)$dataquery['useractive'],
                'country' => $dataquery['cname'],
                'abrv' => $dataquery['cuabrv'],
                'dial' => $dataquery['ccall'],
                'rate' => $dataquery['crate'],
                'ccurrency' => $dataquery['ccurrency'],
                'default_currency' => $dataquery['default_currency'],
                'join' => $dataquery['ujoin'],
            ];

            $userbal = [
                'profit' => floatval($dataquery['profit']),
                'balance' => floatval($dataquery['balance']),
                'deposit' => floatval($dataquery['deposit']),
                'youtube' => floatval($dataquery['youtube']),
                'tiktok' => floatval($dataquery['tiktok']),
                'trivia' => floatval($dataquery['trivia']),
                'welcome' => floatval($dataquery['welcome']),
                'spin' => floatval($dataquery['spin']),
                'bonus' => floatval($dataquery['cbonus']),

            ];
            $fee = [
                'reg' => floatval($dataquery['creg']) ?? 1293.16,
                'fl1' => floatval($dataquery['fl1']) ?? 646.58,
                'fl2' => floatval($dataquery['fl2']) ?? 323.29,
                'fl3' => floatval($dataquery['fl3']) ?? 129.32,
                'min_with' => floatval($dataquery['min_with']) ?? 12,
                'charges' => floatval($dataquery['charges']) ?? 129.32,
            ];
            $response['query']['uid'] = $dataquery['uid'];
            $response['query']['data'] = $userdata;
            $response['query']['bal'] = $userbal;
            $response['query']['fee'] = $fee;
            $response['res'] = true;
        }
    }
    return $response;
}



function updatepassword()
{

    if (sessioned()) {

        $inputs = jDecode();
        $errors = false;


        if (!isset($inputs['curpassword'])) {
            notify(1, "Old Password required", 508, 1);
            $errors = true;
        }

        if (!isset($inputs['newpassword'])) {
            notify(1, "New Password is required", 508, 1);
            $errors = true;
        }

        if (!isset($inputs['repassword'])) {
            notify(1, "Confirm Password is required", 508, 1);
            $errors = true;
        }
        $curpassword = $inputs['curpassword'] ?? null;
        $newpassword = $inputs['newpassword'] ?? null;
        $repassword = $inputs['repassword'] ?? null;

        if ($newpassword !== $repassword) {
            $msg = "Your New Password Din't Match the Confirmed Password";
            notify(1, $msg, 510, 1);
            $errors = true;
        }

        if ($errors) {
            return sendJsonResponse(422);
        }

        $hashpass = $_SESSION['query']['upass'];
        if (password_verify($curpassword, $hashpass)) {


            $hashpass = password_hash($newpassword, PASSWORD_DEFAULT);
            $uid = $_SESSION['suid'];

            $uppass = updates("use", "upass = '$hashpass'", "uid = '$uid'");

            if ($uppass['res']) {
                $msg = "Password Updated Successfully";
                notify(2, $msg, 201, 1);
                return sendJsonResponse(200);
            }
        } else {
            $msg = "Old Password is Incorrect";
            notify(1, $msg, 510, 1);
            return sendJsonResponse(403);
        }
    }
}


function newpasswords($sys = null)
{

    $uemail = jDecode()['email'] ?? null;

    if ($uemail) {

        $response = [];

        $query = selects("*", "use", "uemail = '$uemail' AND active = true", 1);
        if (!$query['res']) {
            notify(0, "Sorry, we couldn't find the email you typed. Please enter your registered email.", 404, 1);
            return sendJsonResponse(404);
        }
        if (!verifyEmail($uemail)) {
            notify(0, "Email Extension Not Found", 403, 1);
            return sendJsonResponse(403);
        }

        $queryData = mysqli_fetch_assoc($query['qry']);
        if (isset($queryData['uid'])) {

            $uid = $queryData['uid'];
            $uname = $queryData['uname'];
            $uemail = $queryData['uemail'];

            $response['res'] = false;

            $_SESSION['suid'] = $uid;

            $new = $sys ?: generatetoken(5, true);

            $hashed =  password_hash($new, PASSWORD_DEFAULT);

            if (updates("use", "upass = '$hashed'", "uid = '$uid'")['res']) {
                $response['res'] = true;
                $response['hashed'] = $new;

                $subject  = "New Password";
                $msg = "
                    Hi $uname, <br>
            Your new password has been generated. You may use it to log in and change your preferred password:

            <ul>
                <li>Username: <strong>$uname</strong></li>
                <li>Password: <strong>$new</strong></li>
            </ul>

            ";
                sendmail($uname, $uemail, $msg, $subject);
                notify(2, "We've sent you a new password to your registered email. Kindly check your inbox or Spam Folder", 200, 1);
                sendJsonResponse(200, true);
            }

            return sendJsonResponse(200, true, null, $response);
        } else {
            notify(1, "Account Couldnt Be Found", 404, 1);
            return sendJsonResponse(404);
        }
    } else {
        return sendJsonResponse(403);
    }
}


function giveOutRandId()
{
    $response = [
        "Total Users" => 0,
        "Already Updated" => 0,
        "Completed Updates" => 0,
        "Total Errors" => 0,
    ];

    $allUser = selects("*", "use", "", 1);
    if ($allUser['res']) {
        $response['Total Users'] = $allUser['rows'];
        while ($data = mysqli_fetch_assoc($allUser['qry'])) {
            if (strlen($data['randid']) < 18) {

                $randId = checkrandtoken("use", generatetoken("32", false));
                $uid = $data['uid'];
                $confirm = updates("use", "randid = '$randId'", "uid = '$uid'");
                if ($confirm['res']) {
                    $response['Completed Updates']++;
                } else {
                    $response['Total Errors']++;
                }
            } else {
                $response['Already Updated']++;
            }
        }
    }
    sendJsonResponse(200, true, null, $response);
}

function checkrandtoken($tb, $token, $cap = false)
{
    $array = [];

    $id = "randid";
    if (!$tb) {
        notify(1, "error requested fn=>checktoken", 505, 3);
        return sendJsonResponse(500);
    }

    $pretoken = $token;
    $token = check($id, $tb, $token);

    if ($token['res']) {
        $token = checkrandtoken($tb, generatetoken(strlen($token['qry'][$id]) + 1, $cap), $cap);
    } else {
        $token = $pretoken;
    }

    return $token;
}

function updaterates()
{

    $inputs = jDecode(['cid', 'creg', 'fl1', 'fl2', 'fl3']);

    $id = $inputs['cid'];
    $creg = $inputs['creg'];
    $fl1 = $inputs['fl1'];
    $fl2 = $inputs['fl2'];
    $fl3 = $inputs['fl3'];

    $confirm  =  check($id, "aff", $id)['res'];

    if ($confirm) {
        if (updates("aff", "creg = '$creg', fl1 = '$fl1', fl2 = '$fl2', fl3 = '$fl3'", "cid = '$id'")['res']) {
            notify(1, "Updated Succefully", 2, 1);
            sendJsonResponse(200);
        } else {
            notify(1, "Updated Succefully", 2, 1);
        }
    }

    sendJsonResponse(500);
}

//  trasck trancsction /////////////////



/**
 * Extract transaction details from MTN Mobile Money SMS
 * 
 * @param string $json The JSON string containing the SMS data
 * @return array|null Extracted transaction details or null if parsing fails
 */
// function mtnTrack($json)
// {
//     // Decode JSON
//     $data = json_decode($json, true);
//     if (!$data) {
//         return null;
//     }

//     // Initialize result array with default values
//     $result = [
//         'provider' => 'MTN',
//         'amount' => null,
//         'sender_name' => null,
//         'sender_phone' => null,
//         'receiver_phone' => null,
//         'transaction_id' => null,
//         'financial_transaction_id' => null,
//         'external_transaction_id' => null,
//         'timestamp' => null,
//         'message_timestamp' => null,
//         'message_id' => null,
//         'balance' => null,
//         'fee' => null,
//         'till_number' => null,
//         'webhook_id' => null,
//         'sim_number' => null,
//         'raw_message' => null
//     ];

//     // Extract common data
//     if (isset($data['timestamp'])) {
//         $result['timestamp'] = $data['timestamp'];
//     }

//     if (isset($data['webhookId'])) {
//         $result['webhook_id'] = $data['webhookId'];
//     }

//     if (isset($data['payload']['messageId'])) {
//         $result['message_id'] = $data['payload']['messageId'];
//     }

//     if (isset($data['payload']['simNumber'])) {
//         $result['sim_number'] = $data['payload']['simNumber'];
//     }

//     if (isset($data['payload']['phoneNumber'])) {
//         $result['receiver_phone'] = $data['payload']['phoneNumber'];
//     }

//     if (isset($data['payload']['receivedAt'])) {
//         $result['message_timestamp'] = $data['payload']['receivedAt'];
//     }

//     if (isset($data['payload']['message'])) {
//         $message = $data['payload']['message'];
//         $result['raw_message'] = $message;

//         // Extract amount
//         if (preg_match('/received (\d+) UGX/', $message, $matches)) {
//             $result['amount'] = (int)$matches[1];
//         }

//         // Extract sender name and phone
//         if (preg_match('/from (.*?) \((\d+)\)/', $message, $matches)) {
//             $result['sender_name'] = $matches[1];
//             $result['sender_phone'] = $matches[2];
//         }

//         // Extract transaction timestamp from the message
//         if (preg_match('/at (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $message, $matches)) {
//             $result['transaction_timestamp'] = $matches[1];
//         }

//         // Extract till number
//         if (preg_match('/Till:(\d+)/', $message, $matches)) {
//             $result['till_number'] = $matches[1];
//         }

//         // Extract balance
//         if (preg_match('/Your new balance: (\d+) UGX/', $message, $matches)) {
//             $result['balance'] = (int)$matches[1];
//         }

//         // Extract fee
//         if (preg_match('/Fee was (\d+) UGX/', $message, $matches)) {
//             $result['fee'] = (int)$matches[1];
//         }

//         // Extract financial transaction ID
//         if (preg_match('/Financial Transaction Id: (\d+)/', $message, $matches)) {
//             $result['financial_transaction_id'] = $matches[1];
//         }

//         // Extract external transaction ID
//         if (preg_match('/External Transaction Id: (.+?)\./', $message, $matches)) {
//             $result['external_transaction_id'] = $matches[1];
//         }
//     }

//     return $result;
// }

function mtnTrack($json)
{
    $data = json_decode($json, true);
    if (!$data) {
        return null;
    }

    $result = [
        'provider' => 'MTN',
        'amount' => null,
        'sender_name' => null,
        'sender_phone' => null,
        'receiver_phone' => null,
        'transaction_id' => null,
        'financial_transaction_id' => null,
        'external_transaction_id' => null,
        'timestamp' => null,
        'message_timestamp' => null,
        'message_id' => null,
        'balance' => null,
        'fee' => null,
        'till_number' => null,
        'webhook_id' => null,
        'sim_number' => null,
        'raw_message' => null
    ];

    if (isset($data['timestamp'])) {
        $result['timestamp'] = $data['timestamp'];
    }

    if (isset($data['webhookId'])) {
        $result['webhook_id'] = $data['webhookId'];
    }

    if (isset($data['payload']['messageId'])) {
        $result['message_id'] = $data['payload']['messageId'];
    }

    if (isset($data['payload']['simNumber'])) {
        $result['sim_number'] = $data['payload']['simNumber'];
    }

    if (isset($data['payload']['phoneNumber'])) {
        $result['receiver_phone'] = $data['payload']['phoneNumber'];
    }

    if (isset($data['payload']['receivedAt'])) {
        $result['message_timestamp'] = $data['payload']['receivedAt'];
    }

    if (isset($data['payload']['message'])) {
        $message = $data['payload']['message'];
        $result['raw_message'] = $message;

        // Extract amount
        if (preg_match('/received UGX\s*([\d,]+)/i', $message, $matches)) {
            $result['amount'] = (int)str_replace(',', '', $matches[1]);
        }

        // Extract sender name and phone
        if (preg_match('/from\s*\((.*?)\)\s*(\d{9,12})/i', $message, $matches)) {
            $result['sender_name'] = trim($matches[1]);
            $result['sender_phone'] = trim($matches[2]);
        }

        // Extract balance
        if (preg_match('/balance:\s*([\d,]+\.\d{1,2})/i', $message, $matches)) {
            $result['balance'] = (float)str_replace(',', '', $matches[1]);
        }

        // Extract transaction ID
        if (preg_match('/Transaction ID:\s*(\d+)/i', $message, $matches)) {
            $result['transaction_id'] = trim($matches[1]);
        }
    }

    return $result;
}


function MTNSSD($json)
{
    // Decode JSON
    $data = json_decode($json, true);
    if (!$data) {
        return null;
    }

    // Initialize result array with default values
    $result = [
        'provider' => 'MTN',
        'amount' => null,
        'sender_name' => null,
        'sender_phone' => null,
        'receiver_phone' => null,
        'transaction_id' => null,
        'financial_transaction_id' => null,
        'external_transaction_id' => null,
        'timestamp' => null,
        'message_timestamp' => null,
        'message_id' => null,
        'balance' => null,
        'fee' => null,
        'till_number' => null,
        'webhook_id' => null,
        'sim_number' => null,
        'raw_message' => null
    ];

    // Extract common data
    if (isset($data['timestamp'])) {
        $result['timestamp'] = $data['timestamp'];
    }

    if (isset($data['webhookId'])) {
        $result['webhook_id'] = $data['webhookId'];
    }

    if (isset($data['payload']['messageId'])) {
        $result['message_id'] = $data['payload']['messageId'];
    }

    if (isset($data['payload']['simNumber'])) {
        $result['sim_number'] = $data['payload']['simNumber'];
    }

    if (isset($data['payload']['phoneNumber'])) {
        $result['receiver_phone'] = $data['payload']['phoneNumber'];
    }

    if (isset($data['payload']['receivedAt'])) {
        $result['message_timestamp'] = $data['payload']['receivedAt'];
    }

    if (isset($data['payload']['message'])) {
        $message = $data['payload']['message'];
        $result['raw_message'] = $message;

        // Extract amount (SSP currency)
        if (preg_match('/received (\d+\.\d{2}) SSP/', $message, $matches)) {
            $result['amount'] = (float)$matches[1];
        }

        // Extract sender name and phone
        if (preg_match('/from (.*?) \((\d+)\)/', $message, $matches)) {
            $result['sender_name'] = trim($matches[1]);
            $result['sender_phone'] = $matches[2];
        }

        // Extract transaction timestamp from the message
        if (preg_match('/on\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $message, $matches)) {
            $result['transaction_timestamp'] = $matches[1];
        }

        // Extract balance (SSP currency)
        if (preg_match('/New balance:(\d+\.\d{2}) SSP/', $message, $matches)) {
            $result['balance'] = (float)$matches[1];
        }

        // Extract transaction ID
        if (preg_match('/Transaction Id: (\d+)\./', $message, $matches)) {
            $result['transaction_id'] = $matches[1];
            $result['financial_transaction_id'] = $matches[1]; // Assuming this is the same as transaction_id in these messages
        }

        // Extract reason if present (though samples show empty reasons)
        if (preg_match('/Reason: ([^\.]+)\./', $message, $matches)) {
            $result['reason'] = trim($matches[1]);
        }
    }
    return $result;
}


// ENGLISH 
// function MTNCAMEROON($json)
// {
//     // Decode JSON
//     $data = json_decode($json, true);
//     if (!$data) {
//         return null;
//     }

//     // Initialize result array with default values
//     $result = [
//         'provider' => 'MTN',
//         'amount' => null,
//         'sender_name' => null,
//         'sender_phone' => null,
//         'receiver_phone' => null,
//         'transaction_id' => null,
//         'financial_transaction_id' => null,
//         'external_transaction_id' => null,
//         'timestamp' => null,
//         'message_timestamp' => null,
//         'message_id' => null,
//         'balance' => null,
//         'fee' => null,
//         'till_number' => null,
//         'webhook_id' => null,
//         'sim_number' => null,
//         'raw_message' => null
//     ];

//     // Extract common data
//     if (isset($data['timestamp'])) {
//         $result['timestamp'] = $data['timestamp'];
//     }

//     if (isset($data['webhookId'])) {
//         $result['webhook_id'] = $data['webhookId'];
//     }

//     if (isset($data['payload']['messageId'])) {
//         $result['message_id'] = $data['payload']['messageId'];
//     }

//     if (isset($data['payload']['simNumber'])) {
//         $result['sim_number'] = $data['payload']['simNumber'];
//     }

//     if (isset($data['payload']['phoneNumber'])) {
//         $result['receiver_phone'] = $data['payload']['phoneNumber'];
//     }

//     if (isset($data['payload']['receivedAt'])) {
//         $result['message_timestamp'] = $data['payload']['receivedAt'];
//     }

//     if (isset($data['payload']['message'])) {
//         $message = $data['payload']['message'];
//         $result['raw_message'] = $message;

//         // Extract amount (XAF currency)
//         if (preg_match('/received (\d+) XAF/', $message, $matches)) {
//             $result['amount'] = (int)$matches[1];
//         }

//         // Extract sender name and phone
//         if (preg_match('/from (.*?) \((\d+)\)/', $message, $matches)) {
//             $result['sender_name'] = trim($matches[1]);
//             $result['sender_phone'] = $matches[2];
//         }

//         // Extract transaction timestamp from the message
//         if (preg_match('/at (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $message, $matches)) {
//             $result['timestamp'] = $matches[1]; // Using the transaction timestamp as main timestamp
//         }

//         // Extract balance (XAF currency)
//         if (preg_match('/Your new balance: (\d+) XAF/', $message, $matches)) {
//             $result['balance'] = (int)$matches[1];
//         }

//         // Extract fee
//         if (preg_match('/Fee was (\d+) XAF/', $message, $matches)) {
//             $result['fee'] = (int)$matches[1];
//         }

//         // Extract financial transaction ID
//         if (preg_match('/Financial Transaction Id: (\d+)\./', $message, $matches)) {
//             $result['financial_transaction_id'] = $matches[1];
//             $result['transaction_id'] = $matches[1]; // Using financial transaction ID as transaction ID
//         }

//         // Extract external transaction ID
//         if (preg_match('/External Transaction Id: ([^\.]+)\./', $message, $matches)) {
//             $result['external_transaction_id'] = trim($matches[1]) == '-' ? null : trim($matches[1]);
//         }
//     }

//     return $result;
// }



// FRENCH

function MTNCAMEROON($json)
{
    // Decode JSON
    $data = json_decode($json, true);
    if (!$data) {
        return null;
    }

    // Initialize result array with default values
    $result = [
        'provider' => 'MTN',
        'amount' => null,
        'sender_name' => null,
        'sender_phone' => null,
        'receiver_phone' => null,
        'transaction_id' => null,
        'financial_transaction_id' => null,
        'external_transaction_id' => null,
        'timestamp' => null,
        'message_timestamp' => null,
        'message_id' => null,
        'balance' => null,
        'fee' => null,
        'till_number' => null,
        'webhook_id' => null,
        'sim_number' => null,
        'raw_message' => null
    ];

    // Extract common data
    if (isset($data['timestamp'])) {
        $result['timestamp'] = $data['timestamp'];
    }

    if (isset($data['webhookId'])) {
        $result['webhook_id'] = $data['webhookId'];
    }

    if (isset($data['payload']['messageId'])) {
        $result['message_id'] = $data['payload']['messageId'];
    }

    if (isset($data['payload']['simNumber'])) {
        $result['sim_number'] = $data['payload']['simNumber'];
    }

    if (isset($data['payload']['phoneNumber'])) {
        $result['receiver_phone'] = $data['payload']['phoneNumber'];
    }

    if (isset($data['payload']['receivedAt'])) {
        $result['message_timestamp'] = $data['payload']['receivedAt'];
    }

    if (isset($data['payload']['message'])) {
        $message = $data['payload']['message'];
        $result['raw_message'] = $message;

        // Extract amount
        if (preg_match('/Vous avez recu (\d+) XAF/', $message, $matches)) {
            $result['amount'] = (int)$matches[1];
        }

        // Extract sender name and phone
        if (preg_match('/de (.*?) \((\d+)\)/', $message, $matches)) {
            $result['sender_name'] = trim($matches[1]);
            $result['sender_phone'] = $matches[2];
        }

        // Extract transaction timestamp
        if (preg_match('/a (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $message, $matches)) {
            $result['timestamp'] = $matches[1];
        }

        // Extract balance
        if (preg_match('/Votre nouveau solde : (\d+) XAF/', $message, $matches)) {
            $result['balance'] = (int)$matches[1];
        }

        // Extract fee
        if (preg_match('/Les frais etaient de (\d+) XAF/', $message, $matches)) {
            $result['fee'] = (int)$matches[1];
        }

        // Extract financial transaction ID
        if (preg_match('/L\'identifiant de la transaction financiere : (\d+)/', $message, $matches)) {
            $result['financial_transaction_id'] = $matches[1];
            $result['transaction_id'] = $matches[1]; // also transaction_id
        }

        // Extract external transaction ID
        if (preg_match('/Identifiant de la transaction externe : ([^\.]+)/', $message, $matches)) {
            $result['external_transaction_id'] = trim($matches[1]) == '-' ? null : trim($matches[1]);
        }

        // Extract financial transaction ID
        if (preg_match("/L['’]identifiant de la transaction financiere ?: (\d+)/u", $message, $matches)) {
            $result['financial_transaction_id'] = $matches[1];
            $result['transaction_id'] = $matches[1];
        }

        // Fallback if transaction_id is still null
        if (!$result['transaction_id']) {
            if (!empty($result['message_id'])) {
                $result['transaction_id'] = $result['message_id']; // fallback to messageId
            } else {
                $result['transaction_id'] = uniqid("mtn_", true); // generate unique id if nothing found
            }
        }
    }

    return $result;
}


function ORANGEMONEY($json)
{
    // Decode JSON
    $data = json_decode($json, true);
    if (!$data) {
        return null;
    }

    // Initialize result array with default values
    $result = [
        'provider' => 'MTN',
        'amount' => null,
        'sender_name' => null,
        'sender_phone' => null,
        'receiver_phone' => null,
        'transaction_id' => null,
        'financial_transaction_id' => null,
        'external_transaction_id' => null,
        'timestamp' => null,
        'message_timestamp' => null,
        'message_id' => null,
        'balance' => null,
        'fee' => null,
        'till_number' => null,
        'webhook_id' => null,
        'sim_number' => null,
        'raw_message' => null
    ];

    // Extract common data
    if (isset($data['timestamp'])) {
        $result['timestamp'] = $data['timestamp'];
    }

    if (isset($data['webhookId'])) {
        $result['webhook_id'] = $data['webhookId'];
    }

    if (isset($data['payload']['messageId'])) {
        $result['message_id'] = $data['payload']['messageId'];
    }

    if (isset($data['payload']['simNumber'])) {
        $result['sim_number'] = $data['payload']['simNumber'];
    }

    if (isset($data['payload']['phoneNumber'])) {
        $result['receiver_phone'] = $data['payload']['phoneNumber'];
    }

    if (isset($data['payload']['receivedAt'])) {
        $result['message_timestamp'] = $data['payload']['receivedAt'];
    }

    if (isset($data['payload']['message'])) {
        $message = $data['payload']['message'];
        $result['raw_message'] = $message;

        // Extract amount (FCFA currency)
        if (preg_match('/Transaction amount: (\d+(?:\.\d+)?) FCFA/', $message, $matches)) {
            $result['amount'] = (float)$matches[1];
        }

        // Extract sender name and phone
        if (preg_match('/from (\d+) ([^ ]+(?: [^ ]+)*) to/', $message, $matches)) {
            $result['sender_phone'] = $matches[1];
            $result['sender_name'] = trim($matches[2]);
        }

        // Extract receiver phone (if not already set from payload)
        if (!$result['receiver_phone'] && preg_match('/to (\d+) ([^,]+)/', $message, $matches)) {
            $result['receiver_phone'] = $matches[1];
        }

        // Extract transaction ID
        if (preg_match('/Transaction ID: ([^,]+)/', $message, $matches)) {
            $result['transaction_id'] = trim($matches[1]);
            $result['financial_transaction_id'] = trim($matches[1]);
        }

        // Extract balance (FCFA currency)
        if (preg_match('/New balance: (\d+(?:\.\d+)?) FCFA/', $message, $matches)) {
            $result['balance'] = (float)$matches[1];
        }

        // Extract fee (using Charges + Commission)
        if (preg_match('/Charges: (\d+(?:\.\d+)?) FCFA.*Commission: (\d+(?:\.\d+)?) FCFA/', $message, $matches)) {
            $result['fee'] = (float)$matches[1] + (float)$matches[2];
        }
    }

    return $result;
}
/**
 * Extract transaction details from Airtel Money SMS
 * 
 * @param string $json The JSON string containing the SMS data
 * @return array|null Extracted transaction details or null if parsing fails
 */
function airtelTrack($json)
{
    // Decode JSON
    $data = json_decode($json, true);
    if (!$data) {
        return null;
    }

    // Initialize result array with default values
    $result = [
        'provider' => 'Airtel',
        'amount' => null,
        'sender_phone' => null,
        'receiver_phone' => null,
        'transaction_id' => null,
        'timestamp' => null,
        'message_timestamp' => null,
        'message_id' => null,
        'balance' => null,
        'reference' => null,
        'webhook_id' => null,
        'sim_number' => null,
        'raw_message' => null
    ];

    // Extract common data
    if (isset($data['timestamp'])) {
        $result['timestamp'] = $data['timestamp'];
    }

    if (isset($data['webhookId'])) {
        $result['webhook_id'] = $data['webhookId'];
    }

    if (isset($data['payload']['messageId'])) {
        $result['message_id'] = $data['payload']['messageId'];
    }

    if (isset($data['payload']['simNumber'])) {
        $result['sim_number'] = $data['payload']['simNumber'];
    }

    if (isset($data['payload']['phoneNumber'])) {
        $result['receiver_phone'] = $data['payload']['phoneNumber'];
    }

    if (isset($data['payload']['receivedAt'])) {
        $result['message_timestamp'] = $data['payload']['receivedAt'];
    }

    if (isset($data['payload']['message'])) {
        $message = $data['payload']['message'];
        $result['raw_message'] = $message;

        // Extract transaction ID
        if (preg_match('/TID(\d+)/', $message, $matches)) {
            $result['transaction_id'] = $matches[1];
        }

        if (preg_match('/UGX ([\d,]+)/', $message, $matches)) {
            $result['amount'] = (int)str_replace(',', '', $matches[1]);
        }

        // Extract sender phone
        if (preg_match('/from (\d+)/', $message, $matches)) {
            $result['sender_phone'] = $matches[1];
        }

        // Extract reference
        if (preg_match('/reference([a-zA-Z0-9]+)/', $message, $matches)) {
            $result['reference'] = $matches[1];
        }

        // Extract balance (handle comma formatting)
        if (preg_match('/Bal.*?UGX ([0-9,]+)/', $message, $matches)) {
            $result['balance'] = (int)str_replace(',', '', $matches[1]);
        }
    }

    return $result;
}

/**
 * Unified function to track mobile money transactions from both providers
 * 
 * @param string $json The JSON string containing the SMS data
 * @return array|null Extracted transaction details or null if parsing fails
 */
function trackTransaction()
{

    // Capture request details
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true); // Convert JSON string to an array

    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'url' => $_SERVER['REQUEST_URI'],
        'ip' => $_SERVER['REMOTE_ADDR'],
        'headers' => getallheaders(),
        'body' => $data ?? $rawBody // If JSON decode fails, keep raw body
    ];

    $logFile = __DIR__ . '/../callbackurl/report.txt';
    $logEntry = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    if (!$data || !isset($data['payload']['phoneNumber'])) {
        return null;
    }

    // Determine provider based on phoneNumber
    $phoneNumber = $data['payload']['phoneNumber'];

    if ($phoneNumber === 'MTNMobMoney') {

        $logmtn = __DIR__ . '/../callbackurl/mtn.txt';
        file_put_contents($logmtn, $logEntry, FILE_APPEND);
        return mtnTrack($rawBody);
    } else if ($phoneNumber === 'AirtelMoney') {

        $logairtel = __DIR__ . '/../callbackurl/airtel.txt';
        file_put_contents($logairtel, $logEntry, FILE_APPEND);
        return airtelTrack($rawBody);
    } else if ($phoneNumber === 'MTN MoMo') {

        $logairtel = __DIR__ . '/../callbackurl/airtel.txt';
        file_put_contents($logairtel, $logEntry, FILE_APPEND);
        return MTNSSD($rawBody);
    } else if ($phoneNumber === 'MobileMoney') {

        $logairtel = __DIR__ . '/../callbackurl/airtel.txt';
        file_put_contents($logairtel, $logEntry, FILE_APPEND);
        return MTNCAMEROON($rawBody);
    } else if ($phoneNumber === 'OrangeMoney') {

        $logairtel = __DIR__ . '/../callbackurl/airtel.txt';
        file_put_contents($logairtel, $logEntry, FILE_APPEND);
        return ORANGEMONEY($rawBody);
    } else {
        // Unknown provider
        return null;
    }
}


// print_r(trackTransaction());

//  create an insertion fumctions 
// automatically cheks and upadtes balance from transactions
//  be cautions on currency rates 

function addTransaction()
{

    $trackTransaction = trackTransaction();
    global $today;

    $logFile = __DIR__ . '/../callbackurl/array.txt';
    $logEntry = json_encode($trackTransaction, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    $wid = gencheck("trw", 18);

    $provider = $trackTransaction['provider'] ?? null;

    $transaction_id = $trackTransaction['transaction_id']
        ?? $trackTransaction['financial_transaction_id']
        ?? null;

    $amount = $trackTransaction['amount'] ?? null;

    $sender_name = $trackTransaction['sender_name'] ?? "None";

    $sender_phone = $trackTransaction['sender_phone'] ?? null;

    $balance = $trackTransaction['balance'] ?? null;

    // Fix: You had double $ in front of trackTransaction['till_number']
    // Also default to 0 if missing
    $till_number = $trackTransaction['till_number'] ?? 0;

    $timestamp = $today;

    $raw_message = $trackTransaction['raw_message'] ?? null;

    $message_id = $trackTransaction['message_id'] ?? null;

    $date = $today;


    $insert = inserts(
        "trw",
        "wid,provider,transaction_id,amount,sender_name,sender_phone,balance,till_number,timestamp,raw_message,message_id,date",
        ['ssssssssssis', $wid, $provider, $transaction_id, $amount, $sender_name, $sender_phone, $balance, $till_number, $timestamp, $raw_message, $message_id, $date]
    );

    if ($insert['res']) {
        notify(2, "Payment Successfully Recorded", 200, 1);
        sendJsonResponse(200, true, "Payment Recorded", [$trackTransaction, $insert]);
    } else {
        notify(1, "Payment Already Recorded", 200, 1);
        sendJsonResponse(200, false, "Payment Already Exit", [$trackTransaction, $insert]);
    }


    // print_r($insert);

    // sendmail("SMS", "primemarkboogie@gmail.com", $trackTransaction, "SMS");
};
