<?php
require_once "admin/networking.php";

function userandbalance()
{
    $query =
        "
    -- 1. Select all users from the `users` table where there's no corresponding `buid` in the `balances` table
SELECT u.uid 
FROM users u
LEFT JOIN balances b ON u.uid = b.buid
WHERE b.buid IS NULL;

-- 2. Insert the missing `uid` values into the `balances` table
INSERT INTO balances (buid)
SELECT u.uid 
FROM users u
LEFT JOIN balances b ON u.uid = b.buid
WHERE b.buid IS NULL;

// update balances to zero fo active

UPDATE balances b
INNER JOIN users u 
ON b.buid = u.uid 
SET b.deposit = 0
WHERE u.ustatus = 2 

SELECT u.uid, u.uname, b.balance  AS Current_Balances 
FROM `balances` b
INNER JOIN users u
ON u.uid = b.buid 
WHERE buid NOT IN ('2C71D953AA','AC8630F55F','C93BE60D42','8F3EE8EC56') 
AND b.balance > 500 AND u.ucountryid = 'KEST'  ORDER BY b.balance DESC LIMIT 403

-- u.uid, u.uname, b.balance
-- SUM(b.balance)

payament for per country 

SELECT pm.pid, pm.method_name, pp.step_no, pp.description 
FROM `payment_method` pm 
LEFT JOIN payment_procedure pp 
ON pm.pid = pp.pmethod_id 
WHERE pm.cid = '61EE' 
ORDER BY `pm`.`method_name` ASC, `pp`.`step_no` ASC;


    ";
}

function register()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(403);
    }

    $inputs = jDecode(['username', 'email', 'password', 'repassword', 'phone']);

    $errors = false;

    if (!isset($inputs['username']) || !mytrim($inputs['username'])) {
        notify(1, "Username required", 506, 1);
        $errors = true;
    }

    ## Errors here
    if (!isset($inputs['email']) || !mytrim($inputs['email']) || !verifyEmail($inputs['email']) || strlen($inputs['email']) <= 16) {
        notify(1, "Invalid Email ADDRESS", 507, 1);
        $errors = true;
    }

    if (!isset($inputs['password'])) {
        notify(1, "Password required", 508, 1);
        $errors = true;
    }

    if (!isset($inputs['repassword'])) {
        notify(1, "Confirm Password is required", 508, 1);
        $errors = true;
    }

    if (!isset($inputs['phone']) || !mytrim($inputs['phone'])) {
        notify(1, "Phone required", 509, 1);
        $errors = true;
    }

    global $today;

    $uname = Ucap(mytrim($inputs['username']));
    $uemail = mytrim($inputs['email']);
    $password = $inputs['password'];
    $repassword = $inputs['repassword'] ?? null;
    $ucountry = $inputs['country'];
    $default_currency = 'KEST';
    $parent_id = NULL;

    // consider 

    $uphone = mytrim(ltrim($inputs['phone'], '0'));

    if ($password !== $repassword) {
        $msg = "Your Password Din't Match";
        notify(1, $msg, 510, 1);
        $errors = true;
    }


    if (check("uname", "use", $uname)['res']) {
        $msg = "Username alredy Taken";
        notify(1, $msg, 510, 1);
        $errors = true;
    }

    if (check("uemail", "use", $uemail)['res']) {
        $msg = "Email Already taken";
        notify(1, $msg, 511, 1);
        $errors = true;
    }



    $l1 = isset($inputs['upline']) ? Ucap(mytrim($inputs['upline'])) : NULL;

    if ($l1) {
        if ($uname == $inputs['upline']) {
            $msg = "Username Cant Be the Same as Upline";
            notify(1, $msg, 510, 1);
            $errors = true;
        }
    } else {
        $l1 = "StateGain";
    }


    $confirmupline = selects("uid", "use", "uname = '$l1'", 1);

    if ($confirmupline['res']) {
        $parent_id = mysqli_fetch_assoc($confirmupline['qry'])['uid'];
    }

    $hashpass = password_hash($password, PASSWORD_DEFAULT);
    $uid = gencheck("use", 5);

    $l1q = grabupline($parent_id);

    $l1 = $l1q['l1'];
    $l2 = $l1q['l2'];
    $l3 = $l1q['l3'];
    // sendJsonResponse(200,true,null,$l1q);
    $dial = "+254";

    $checkCountry = selects("*", "cou", "cid = '$ucountry'", 1);


    if (!$checkCountry['res']) {
        $ucountry  = 'KEST';
    } else {
        $dial = mysqli_fetch_assoc($checkCountry['qry'])['ccall'];
    }

    if (!check("cid", "aff", $ucountry)['res']) {
        $ucountry  = 'KEST';
    }

    $uphone = $dial . substr($uphone, -9);

    if (check("uphone", "use", $uphone)['res']) {
        $msg = "Phone-Number Already taken please Provide different one ";
        notify(1, $msg, 512, 1);
        $errors = true;
    }

    $fee = selects("*", "aff", "cid = '$ucountry'", 1);
    if ($fee['res']) {
        $feeData = mysqli_fetch_assoc($fee['qry']);
        $default_currency = $feeData['cid'];
        $profit = $feeData['cbonus'];
    } else {
        $profit = 0;
    }

    if ($errors) {
        return sendJsonResponse(422);
    }

    $randId = checkrandtoken("use", generatetoken("32", false));

    $userq = inserts(
        "use",
        "uid,randid,uname,uemail,uphone,upass,ucountryid,l1,l2,l3,ujoin,default_currency",
        ['ssssssssssss', $uid, $randId, $uname, $uemail, $uphone, $hashpass, $ucountry, $l1, $l2, $l3, $today, $default_currency]
    );
    $balq = inserts("bal", "buid, profit", ['ss', $uid, $profit]);

    if ($userq['res'] && $balq['res']) {
        $msg = "User Account Created Successfully";
        notify(2, $msg, 513, 1);
    } else {
        $msg = "Error in Creating User Account";
        notify(1, $msg, 514, 1);
        sendJsonResponse(500);
    }

    $randid = gencheck("user");
    $parent_id = $parent_id ?? NULL;
    inserts("user", "id,parent_id,child_id", ['sss', $randid, $parent_id, $uid]);



    return sendJsonResponse(201, true);
}



function flutterwave()
{
    if (sessioned()) {
        // notify(1,"Paymeny Request Not Available Please Contact ")

        $inputs = jdecode();

        $amount = $inputs['amount'];

        $response = [
            "status" => false
        ];

        $flutterurl = "https://api.flutterwave.com/v3/payments";

        global $flutterkey;

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];


        $uid = $_SESSION['suid'];
        $accrate = $data['rate'];
        $accname = $data['uname'];
        $accphone = $data['phone'];
        $accemail = $data['email'];
        $acccurrency = $data['ccurrency'];



        $systemamount = conv($accrate, $amount, false);

        global $today;

        $threedays = date("Y-m-d", strtotime("$today - 3 days"));

        deletes("tra", "tstatus = 0 and tdate <= '$threedays'");


        $token = generatetoken(8);

        // $transid = insertstrans($token,$uid,$uname,$phone,"Account Withdrawal",'3','NONE',
        // `NULL`,$requested,0,$balance,$curbalance,$deposit,$curdeposit,$today,$today,$upline,$uplineid,'1');


    }
}


function requestpayment()
{
    if (sessioned()) {
        $inputs = jdecode();
        global $today;
        global $admin;
        global $domain;


        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $default_currency = $data['cid'];


        $confirmflutter = selects("*", "pym", "cid = '$default_currency' and ptype = 2", 1);

        if (!$confirmflutter['res']) {
            notify(1, "Sorry We had An issue fetching your payament Link, please Contact Upline", 403, 1);
            sendJsonResponse(403);
        }
        $flutterdata = mysqli_fetch_assoc($confirmflutter['qry']);
        $payment_id = $flutterdata['pid'];

        $amount = floatval($inputs['amount']);

        $response = [
            "status" => false,
            "link" => null
        ];

        $flutterurl = "https://api.flutterwave.com/v3/payments";


        $uid = $_SESSION['suid'];
        $accrate = $data['rate'];
        $country = $data['country'];
        $accname = $data['uname'];
        $accphone = $data['phone'];
        $accemail = $data['email'];
        $acccurrency = $data['ccurrency'];
        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        // $upline = $data['upline'];

        $grabkey = comboselects(" SELECT * FROM fluttercredentials WHERE fstatus = true ORDER BY fhit ASC LIMIT 1", 1);

        if (!$grabkey['res']) {
            notify(1, "Sorry We had An issue fetching your payament Link, please Contact Upline", 500, 1);
            notify(1, "Hello CEO Payments Link aren't Available, while $accname is Requesting for $country", 500, 2);
            // notify(1,"Hello CEO Payments Link aren't Available, while $accname is Requesting for $country",500,3);
            return sendJsonResponse(500);
        }

        $flutterkey = mysqli_fetch_assoc($grabkey['qry']);

        $fkey = $flutterkey['fkey'];
        $secret_live = $flutterkey['secret_live'];
        $fredirecturl = $flutterkey['fredirecturl'];


        $systemamount = conv($accrate, $amount, false);
        $token = gencheck("tra", 20);

        $transid = insertstrans(
            $token,
            $uid,
            $accname,
            $accphone,
            "Requested Deposit",
            7,
            $payment_id,
            `NULL`,
            $systemamount,
            0,
            $balance,
            $balance,
            $deposit,
            $deposit,
            $today,
            $today,
            $accname,
            $uid,
            2,
            null
        );
        // sendJsonResponse(200,true,"",$secret_live);

        if (!$transid['res']) {
            notify(1, "Sorry We had An issue processing your deposit again in the next few 3 hours", 500, 1);
            return sendJsonResponse(500);
        } else {
        }
        // notify("1","Please Contact Your Upline $upline, to complete Your Payment Request of $amount $acccurrency, Kind Regards",1,1);
        // return sendJsonResponse(statusCode: 403);

        $data = [
            "tx_ref" => $token,
            "amount" => $amount,
            "currency" => $acccurrency,
            "redirect_url" =>  $domain,
            "customer" => [
                "email" => $accemail,
                "name" => $admin['company'],
                "phonenumber" => $accphone
            ]
        ];

        $flutterurlresponse = sendPostRequest($flutterurl, $data, $secret_live);

        if ($flutterurlresponse['status'] == 'success') {
            $response['link'] = $flutterurlresponse['data']['link'];
            $response['status'] = true;
        } else {
            // if($payment_id !== 'KDOE'){
            //     updates("pym","pstatus = 0","pid = '$payment_id'");
            // }
            notify(1, " Sorry Payment Method for $country is faulty Plaese try Again later", 403, 2);
            // notify(1," Sorry Payment Method for $country is faulty Plaese try Again later",403,3);
            sendJsonResponse(403);
        }
        return sendJsonResponse(200, true, null, $response);
    }
}


function getemails()
{
    $pass =  "+@p,P$+j;Up.";
    $array = [
        "1" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown1@crownwave.org",
            "tpass" => $pass,
        ],
        "2" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown2@crownwave.org",
            "tpass" => $pass,
        ],
        "3" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown3@crownwave.org",
            "tpass" => $pass,
        ],
        "4" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crow4@crownwave.org",
            "tpass" => $pass,
        ],
        "5" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown5@crownwave.org",
            "tpass" => $pass,
        ],
        "6" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown1@crownwave.org",
            "tpass" => $pass,
        ],
        "7" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown2@crownwave.org",
            "tpass" => $pass,
        ],
        "8" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown3@crownwave.org",
            "tpass" => $pass,
        ],
        "9" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crow4@crownwave.org",
            "tpass" => $pass,
        ],
        "10" => [
            "thost" => "mail.crownwave.org",
            "tuser" => "crown5@crownwave.org",
            "tpass" => $pass,
        ]
    ];
    shuffle($array);
    $array = reset($array);
    return $array;
}


function grabupline($uname = NULL)
{
    $default = "657E6E3EEB"; // STATEGAIN


    // ! GRAB UID
    $response = [
        'l1' => $default, // STATEGAIN
        'l2' => "EC8C8FCDE7", // RICKYPRO
        'l3' => "379C1F9B95" // NYACORYA
    ];

    $l1q = selects("uid, l1, l2", "use", "uid = '$uname' OR uname = '$uname'", 1);

    if ($l1q['res']) {
        $l1qData = mysqli_fetch_assoc($l1q['qry']);
        $response['l1'] = $l1qData['uid'];
        $response['l2'] = $l1qData['l1'];
        $response['l3'] = $l1qData['l2'];
    }

    $response['default'] = $default;

    return $response;
}


function upgradeupline()
{
    $inputs = jDecode(['uid', 'upline']);

    if (!isset($inputs['uid']) || !isset($inputs['upline'])) {
        sendJsonResponse(422);
    }

    $uid = $inputs['uid'];
    $upline = $inputs['upline'];

    $_SESSION['suid'] = $uid;

    data();

    $data = $_SESSION['query']['data'];
    $bal = $_SESSION['query']['bal'];
    $fee = $_SESSION['query']['fee'];
    $regfee = $fee['reg'];

    $ustatus = $data['status'];
    $uname = $data['uname'];

    if ($ustatus == 2) {
        deactivateuser($uid);
        changeupline($uname, $upline);
        updates("bal", "deposit = '$regfee'", "buid = '$uid'");
        data();
        activateaccount(false);
        updates("bal", "deposit = 0", "buid = '$uid'");
        return sendJsonResponse(200, true);
    }


    if (changeupline($uname, $upline)) {
        return sendJsonResponse(200, true);
    } else {
        notify(0, "Unable to Update Upline for $uname", 200, 1);
        return sendJsonResponse(500);
    }
}

function changeupline($uname, $upline = null)
{
    if ($upline) {
        $alluplines = grabupline($upline);
    } else {
        $alluplines = grabupline(NULL);
    }
    $l1 = $alluplines['l1'];
    $l2 = $alluplines['l2'];
    $l3 = $alluplines['l3'];

    $uplineq = updates("use", "l1 = '$l1', l2 = '$l2', l3 = '$l3'", "uname = '$uname'");

    if ($uplineq['res']) {
        notify(2, "Upline Has been Changed Successfully for $uname", 200, 1);
        return true;
    } else {
        return false;
    }
}


function compenstaion()
{

    $query  = "SELECT a.*, c.cname,c.ccurrency, c.crate, a.creg FROM affiliatefee a
                LEFT JOIN countrys c
                ON a.cid = c.cid
    ";
    $runquery = comboselects($query, 1);
    $country = [];

    // Fetch all results into array for nested iteration
    $runqueryData = [];
    while ($row = mysqli_fetch_assoc($runquery['qry'])) {
        $runqueryData[] = $row;
    }

    foreach ($runqueryData as $items) {

        // updates("aff","creg = '$creg',fl1 = '$fl1',fl2 = '$fl2', fl3 = '$fl3', cbonus = '$cbonus'","cid = '$cid'");

        foreach ($runqueryData  as $item) {

            $freg = conv($item['crate'], $items['creg'], true);
            $fl1 = conv($item['crate'], $items['fl1'], true);
            $fl2 = conv($item['crate'], $items['fl2'], true);
            $fl3 = conv($item['crate'], $items['fl3'], true);

            $country[$item['cname']][] = [
                'country' => $items['cname'],
                'creg' => $freg,
                'Reg-KES' => $items['creg'],
                "L1" => $fl1,
                "L2" => $fl2,
                "L3" => $fl3,
                "currency" => $item['ccurrency']
            ];
        }
    }

    sendJsonResponse(200, true, null, $country);
}


function soloupdate()
{
    if (sessioned()) {
        $inputs  = jDecode();

        $email = isset($inputs['uemail']) ? $inputs['uemail'] : null;
        $phone = isset($inputs['phone']) ? $inputs['phone'] : null;
        $uid = $_SESSION['suid'];
        if ($email && $phone && verifyEmail($email)) {
            $myquery = updates("use", "uemail = '$email', uphone = '$phone', emailed = true ", "uid = '$uid'");
            if ($myquery['res']) {
                notify(2, "Account Updated Successfully", 200, 1);
                sendJsonResponse(200, true);
            } else {
                notify(2, "Your Records already taken plaese Try Another", 200, 1);
                notify(1, "Error" . $myquery['qry'], 403, 3);
                sendJsonResponse(403, false, "Zii");
            }
        }
    }
}

function GrabActivityDate($aid)
{
    $response = [
        'res' => false,
        'dates' => []
    ];

    // Example: selects("column", "table", "condition", limit)
    $query = selects("adate", "acd", "aid = '$aid' AND astatus = true", 1);

    $queryData = mysqli_fetch_assoc($query['qry']);
    if ($query['res'] && !empty($queryData['adate'])) {
        $decoded = json_decode($queryData['adate'], true);

        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['dates'])) {
            $response['dates'] = $decoded['dates'];

            //  i wanna loop through the dates and check if the date is today
            $today = date("D");
            foreach ($response['dates'] as $key => $date) {
                if (substr($date, 0, 3) == $today) {
                    $response['res'] = true;
                }
            }
        }
    }

    return $response;
}



function populatetrivia()
{
    if (sessioned()) {

        global $mintoday;
        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA22");

        if ($GrabActivityDate['res'] || $accactive) {

            if (!weektrivia()['res']) {


                $quizq = selects("*", "qui", "", 1);
                $response = [];

                if ($quizq['res']) {
                    $i = 1;
                    $quizqData = [];
                    while ($row = mysqli_fetch_assoc($quizq['qry'])) {
                        $quizqData[] = $row;
                    }
                    shuffle($quizqData);
                    foreach ($quizqData as $data) {
                        $question = [
                            'No' => $i++,
                            'Question' => $data['q1'],
                            'A1' => $data['qa'],
                            'A2' => $data['qb'],
                            'A3' => $data['qc'],
                            'A4' => $data['qd'],
                            'qid' => $data['qid'],
                        ];
                        $response[] = $question;
                        if ($i > 10) {
                            break;
                        }
                    }
                }
                sendJsonResponse(200, true, null, $response);
            } else {
                notify(2, "You Have Already Answerd Todays Challange", 200, 1);
                sendJsonResponse(403);
            }
        } else {
            notify(2, "Trivia Questions Are Available On " . $GrabActivityDate['dates'][0], 200, 1);

            sendJsonResponse(403, false, "", $GrabActivityDate['dates'][0]);
        }
    }
}



function answerdquiz()
{
    if (sessioned()) {
        $inputs = jDecode()['answers'];
        $totalquiz =  10;

        global $mintoday;
        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA22");

        if ($GrabActivityDate['res'] || $accactive) {

            if (!weektrivia()['res']) {

                $data = $_SESSION['query']['data'];
                $bal = $_SESSION['query']['bal'];
                $fee = $_SESSION['query']['fee'];

                $uid = $_SESSION['suid'];
                $accrate = $data['rate'];
                $accname = $data['uname'];
                $accphone = $data['phone'];
                $accemail = $data['email'];
                $accccurrency = $data['ccurrency'];

                $balance = $bal['balance'];
                $deposit = $bal['deposit'];

                $qquiz = selects("*", "qui", "", 1);


                if ($qquiz['res']) {

                    $answersMap = [];

                    foreach ($inputs as $postanswer) {
                        $answersMap[$postanswer['qid']] = $postanswer['answer'];
                    }

                    $marks = 0;

                    while ($quiz = mysqli_fetch_assoc($qquiz['qry'])) {
                        $qid = $quiz['qid'];
                        $correctAnswer = trim(strtolower($quiz['qanswer']));

                        if (isset($answersMap[$qid])) {
                            $submittedAnswer = trim(strtolower($answersMap[$qid]));

                            if ($correctAnswer === $submittedAnswer) {
                                $marks++;
                            }
                        }
                    }

                    $money = $marks * 2;

                    $convert = $accccurrency . " " . conv($accrate, $money, true);

                    $querybal = updates("bal", "balance = balance + '$money', profit = profit + '$money',  trivia = trivia + '$money'", "buid = '$uid'");
                    if ($querybal['res']) {

                        data();
                        $curbalance = $_SESSION['query']['bal']['balance'];
                        $curdeposit = $_SESSION['query']['bal']['deposit'];

                        $tid = generatetoken(8);

                        $today =  date("Y-m-d H:i:s");

                        $querytrans = insertstrans(
                            $tid,
                            $uid,
                            $accname,
                            $accphone,
                            "Trivia Earnings",
                            9,
                            "NONE",
                            `NULL`,
                            $money,
                            2,
                            $balance,
                            $curbalance,
                            $deposit,
                            $curdeposit,
                            $today,
                            $today,
                            $accname,
                            $uid,
                            2
                        );
                        if (!$querytrans['res']) {
                            notify(1, "Error in Updating the Transactions for trivia", 403, 3);
                            sendJsonResponse(statusCode: 403);
                        }
                    } else {
                        notify(1, "Please try again in the next 45 mins", 403, 1);
                        notify(1, "Error in Updating the balance for trivia", 403, 3);
                        sendJsonResponse(403);
                    }

                    $sbj = "Trivia Earnings.";
                    $msg = "
                <strong>Greatest news </strong>
                <br>
                <br>
                Congrats $accname!   You’ve just earned <strong> $convert </strong> by acing today’s trivia challenge!
                <br>
                <br>

                Your reward has been added to your account. Keep up the great work and keep winning!
                <br>
                <br>
                
                Best,  
                <br>
                The Super Qash Connections
                <br>
                   &nbsp;&nbsp;&nbsp;&nbsp; Powered by: <strong>ZanyTech Co. Ltd</strong>
                    ";
                    if ($marks <= 0) {
                        $statment = "Please Try Again Later";
                    } else {
                        $statment = "Congrats You Earned $convert";
                        sendmail($accname, $accemail, $msg, $sbj);
                    }
                    notify(2, "$statment,  You Scored $marks/$totalquiz.", 200, 1);
                }

                sendJsonResponse(200, true, null, $marks);
            } else {
                notify(2, "You Have Already Answerd Todays Challange", 200, 1);
                sendJsonResponse(403);
            }
        } else {
            notify(2, "Trivia Questions Are Available On Thursdays", 200, 1);
            sendJsonResponse(403);
        }
    }
}



function weektrivia()
{
    if (sessioned()) {
        $response = [];
        $response['res'] = false;

        $uid = $_SESSION['suid'];
        $today = date("Y-m-d");
        $query = selects("*", "tra", "tcat = '9' AND tuid = '$uid' AND tdate like '%$today%' ", 1);
        if ($query['res']) {
            $response['res'] = true;
        }
        return $response;
    }
}

function affilatefee()
{
    $dataquery = comboselects("
    SELECT c.cname, c.cid, e.min_with, e.charges, c.crate  FROM countrys c
    LEFT JOIN affiliatefee e
    ON c.cid = e.cid 
    ", 1);

    if ($dataquery['res']) {

        while ($data = mysqli_fetch_assoc($dataquery['qry'])) {
            $fee = [
                'cid' => $data['cid'],
                'Country' => $data['cname'],
                'Crate' => $data['crate'],
                // 'fl1' => $data['fl1'] ?? 646.58,
                // 'fl2' => $data['fl2'] ?? 323.29,
                // 'fl3' => $data['fl3'] ?? 129.32,
                'min_with' => conv($data['crate'], $data['min_with'], true),
                'charges' => conv($data['crate'], $data['charges'], true)
            ];
            $response[] = $fee;
        }
        sendJsonResponse(200, true, null, $response);
    }
}


function populateyoutube()
{
    if (sessioned()) {

        global $mintoday;
        global $today;

        $response  = [
            "dates" => [],
            "videos" => [],
        ];

        $uid = $_SESSION['suid'];
        $uname = $_SESSION['query']['data']['uname'];
        $crate = $_SESSION['query']['data']['rate'];

        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA44");

        $minustoday = date("Y-m-d H:i:s", strtotime("-1 day"));

        $response['dates'] = $GrabActivityDate['dates'];


        if ($GrabActivityDate['res'] || $accactive) {

            $vidq = selects("*", "soc", " categories = 'Youtube' AND sdate like '%$mintoday%'", 1);



            $vidqData = [];
            while ($row = mysqli_fetch_assoc($vidq['qry'])) {
                $vidqData[] = $row;
            }

            if ($vidq['res'] && count($vidqData) == 4) {

                $i = 1;
                // shuffle($vidqData);
                foreach ($vidqData as $data) {
                    $videos = [
                        'No' => $i++,
                        'v_id' => $data['id'],
                        'v_url' => $data['url'],
                        'v_price' => conv($crate, $data['price'], true),
                        'v_category' => $data['categories'],
                        'v_date' => $data['sdate'],
                        'v_status' => weekyoutube($data['id'])['res']
                    ];
                    $response['videos'][] = $videos;
                    if ($i >= 5) {
                        break;
                    }
                }
            } else {
                updates("soc", "sdate = '$minustoday'", "categories = 'Youtube'");
                $vidq = selects("*", "soc", " categories = 'Youtube'", 1);
                $i = 0;
                $vidqData2 = [];
                while ($row = mysqli_fetch_assoc($vidq['qry'])) {
                    $vidqData2[] = $row;
                }
                shuffle($vidqData2);
                foreach ($vidqData2 as $data) {
                    $id = $data['id'];
                    updates("soc", "sdate = '$today'", "id = '$id'");
                    $i++;
                    if ($i >= 4) {
                        break;
                    }
                }
                populateyoutube();
            }

            sendJsonResponse(200, true, null, $response);
        } else {
            notify(2, "Youtube Challenge Not Scheduled  On " . $GrabActivityDate['dates'][0], 200, 1);
            sendJsonResponse(404, true, "", $response);
        }
    }
}


function weekyoutube($videoid)
{
    if (sessioned()) {

        global $mintoday;
        global $today;

        $uid = $_SESSION['suid'];
        $uname = $_SESSION['query']['data']['uname'];
        $crate = $_SESSION['query']['data']['rate'];

        $response = [];
        $response['res'] = false;

        $uid = $_SESSION['suid'];
        $today = date("Y-m-d");
        $query = selects("*", "tra", "tcat = '10' AND tuid = '$uid' AND tdate like '%$today%' AND ttype_id = '$videoid'", 1);
        if ($query['res']) {
            $response['res'] = true;
        }
        return $response;
    }
}

function payyoutube()
{
    if (sessioned()) {
        $inputs = jDecode();

        // notify(0,"Please Try Again Later",404,1);
        // sendJsonResponse(404);

        $vid = $inputs['vid'] ?? null;


        global $mintoday;
        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA44");

        if ($GrabActivityDate['res'] || $accactive) {


            if (!weekyoutube($vid)['res']) {

                $data = $_SESSION['query']['data'];
                $bal = $_SESSION['query']['bal'];

                $uid = $_SESSION['suid'];
                $accrate = $data['rate'];
                $accname = $data['uname'];
                $accphone = $data['phone'];
                $accemail = $data['email'];
                $accccurrency = $data['ccurrency'];

                $balance = $bal['balance'];
                $deposit = $bal['deposit'];

                $youtubeq = selects("*", "soc", "categories = 'Youtube' AND id = '$vid'", 1);

                if ($youtubeq['res']) {
                    $vprice = mysqli_fetch_assoc($youtubeq['qry'])['price'];


                    $convert = $accccurrency . " " . conv($accrate, $vprice, true);

                    $querybal = updates("bal", "profit = profit + '$vprice',  youtube = youtube + '$vprice'", "buid = '$uid'");
                    if ($querybal['res']) {

                        data();
                        $curbalance = $_SESSION['query']['bal']['balance'];
                        $curdeposit = $_SESSION['query']['bal']['deposit'];

                        $tid = generatetoken(8);

                        $today =  date("Y-m-d H:i:s");

                        $querytrans = insertstrans(
                            $tid,
                            $uid,
                            $accname,
                            $accphone,
                            "YouTube Earnings",
                            10,
                            "NONE",
                            `NULL`,
                            $vprice,
                            2,
                            $balance,
                            $curbalance,
                            $deposit,
                            $curdeposit,
                            $today,
                            $today,
                            $accname,
                            $uid,
                            2,
                            $vid
                        );
                        if (!$querytrans['res']) {
                            notify(1, "Error in Updating the Transactions for YouTube", 403, 3);
                            sendJsonResponse(403);
                        }
                    } else {
                        notify(1, "Please try again in the next 45 mins", 403, 1);
                        notify(1, "Error in Updating the balance for YouTube", 403, 3);
                        sendJsonResponse(403);
                    }

                    $sbj = "YouTube Earnings.";
                    $msg = "
                <strong>Wololooo!  </strong>
                <br>
                <br>
                You’ve just earned $convert a reward by watching premium educational videos on
                 YouTube through our platform! Your curiosity and commitment to learning are truly paying off.
                <br>
                <br>

               Your account has been credited—keep the momentum going and discover more ways to 
               learn and earn with Super Qash Connections!
                <br>
                <br>
                
                Cheers,  
                <br>
                The Super Qash Connections
                <br>
                   &nbsp;&nbsp;&nbsp;&nbsp; Powered by: <strong>ZanyTech Co. Ltd</strong>
                    ";

                    sendmail($accname, $accemail, $msg, $sbj);

                    notify(2, "Successfully Recieved YouTube Earnings Worth $convert", 200, 1);
                    return sendJsonResponse(200);
                } else {
                    // notify(1,"Video Link wasent Found for $vid",300,1);
                    // notify(1,"Video Link wasent Found for $vid",300,3);
                    return sendJsonResponse(404);
                }
            } else {
                notify(2, "This Video has already been watch and paid Kind Regards", 200, 1);
                sendJsonResponse(404);
            }
        } else {
            notify(2, "YouTube Earnings Are Available On Wednesday And Saturdays", 200, 1);
            sendJsonResponse(404);
        }
    }
}


function weekadds($videoid)
{

    if (sessioned()) {

        global $mintoday;
        global $today;

        $uid = $_SESSION['suid'];
        $uname = $_SESSION['query']['data']['uname'];
        $crate = $_SESSION['query']['data']['rate'];
        $accccurrency = $_SESSION['query']['data']['ccurrency'];


        $response = [];
        $response['res'] = false;

        $uid = $_SESSION['suid'];

        $today = date("Y-m-d");
        $query = selects("*", "tra", "tcat = '15' AND tuid = '$uid' AND tdate like '%$today%' AND ttype_id = '$videoid'", 1);
        if ($query['res']) {
            $queryData = mysqli_fetch_assoc($query['qry']);
            $response['res'] = true;
            $response['data'] = $queryData['trefuid'];
            $response['paid'] = $accccurrency .  " " . conv($crate, $queryData['tamount'], true, true);
        }
        return $response;
    }
}

function populateads()
{
    if (sessioned()) {

        global $mintoday;
        global $today;

        $uid = $_SESSION['suid'];
        $uname = $_SESSION['query']['data']['uname'];
        $crate = $_SESSION['query']['data']['rate'];

        $minustoday = date("Y-m-d H:i:s", strtotime("-1 day"));
        global $mintoday;
        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA11");

        if ($GrabActivityDate['res'] || $accactive) {

            $vidq = selects("*", "soc", " categories = 'ads' AND sdate like '%$mintoday%'", 1);

            $response = [];

            $vidqData = [];
            while ($row = mysqli_fetch_assoc($vidq['qry'])) {
                $vidqData[] = $row;
            }

            if ($vidq['res'] && count($vidqData) == 5) {

                $i = 1;
                // shuffle($vidqData);
                foreach ($vidqData as $data) {
                    $videos = [
                        'No' => $i++,
                        'v_id' => $data['id'],
                        'v_name' => $data['name'],
                        'v_url' => "https://super.boogiecoin.com/modules/networking/" . $data['url'],
                        // 'v_price' => conv($crate,$data['price'],true),
                        'v_category' => $data['categories'],
                        'v_date' => $data['sdate'],
                        'v_status' => weekadds($data['id'])
                    ];
                    $response[] = $videos;
                    if ($i >= 6) {
                        break;
                    }
                }
            } else {
                updates("soc", "sdate = '$minustoday'", "categories = 'ads'");
                $vidq = selects("*", "soc", " categories = 'ads'", 1);
                $i = 0;
                $vidqData2 = [];
                while ($row = mysqli_fetch_assoc($vidq['qry'])) {
                    $vidqData2[] = $row;
                }
                shuffle($vidqData2);
                foreach ($vidqData2 as $data) {
                    $id = $data['id'];
                    updates("soc", "sdate = '$today'", "id = '$id'");
                    $i++;
                    if ($i >= 5) {
                        break;
                    }
                }
                populateyoutube();
            }
            sendJsonResponse(200, true, null, $response);
        } else {
            notify(2, "Ads Challenge Not Scheduled For " . $GrabActivityDate['dates'][0], 200, 1);
            sendJsonResponse(403);
        }
    }
}


function payAds()
{
    if (sessioned()) {
        $inputs = jDecode(['addId']);

        $decodecd = [
            "liked" => false,
            "message" => "",
        ];

        $addId = $inputs['addId'] ?? null;

        if (!$addId) {
            notify(1, "Please Try Again Later", 403, 1);
            sendJsonResponse(403);
        }

        $amount = 0;

        if (isset($inputs['liked'])) {
            $amount += 2;  // Add 2 to the current amount
            $decodecd['liked'] = true;
        }

        if (isset($inputs['message'])) {
            if (strlen($inputs['message']) > 0) {

                $amount += 3;  // Add 3 to the current amount
                $decodecd['message'] = $inputs['message'];
            }
        }

        $decodecd = json_encode($decodecd);

        global $mintoday;
        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA11");

        if ($GrabActivityDate['res'] || $accactive) {

            if (!weekadds($addId)['res']) {

                $data = $_SESSION['query']['data'];
                $bal = $_SESSION['query']['bal'];
                $fee = $_SESSION['query']['fee'];

                $uid = $_SESSION['suid'];
                $accrate = $data['rate'];
                $accname = $data['uname'];
                $accphone = $data['phone'];
                $accemail = $data['email'];
                $accccurrency = $data['ccurrency'];

                $balance = $bal['balance'];
                $deposit = $bal['deposit'];

                $youtubeq = selects("*", "soc", "categories = 'ads' AND id = '$addId'", 1);

                if ($youtubeq['res']) {
                    $vprice = $amount;


                    $convert = $accccurrency . " " . conv($accrate, $vprice, true);

                    $querybal = updates("bal", "profit = profit + '$vprice',  ads = ads + '$vprice'", "buid = '$uid'");
                    if ($querybal['res']) {

                        data();
                        $curbalance = $_SESSION['query']['bal']['balance'];
                        $curdeposit = $_SESSION['query']['bal']['deposit'];

                        $tid = generatetoken(8);

                        $today =  date("Y-m-d H:i:s");

                        $querytrans = insertstrans(
                            $tid,
                            $uid,
                            $accname,
                            $accphone,
                            "Ads Promotion",
                            15,
                            "NONE",
                            `NULL`,
                            $vprice,
                            2,
                            $balance,
                            $curbalance,
                            $deposit,
                            $curdeposit,
                            $today,
                            $today,
                            $accname,
                            $decodecd,
                            2,
                            $addId
                        );
                        if (!$querytrans['res']) {
                            notify(1, "Error in Updating the Transactions for Ads", 403, 3);
                            sendJsonResponse(403);
                        }
                    } else {
                        notify(1, "Please try again in the next 45 mins", 403, 1);
                        notify(1, "Error in Updating the balance for Ads", 403, 3);
                        sendJsonResponse(403);
                    }

                    $sbj = "Ads Promotion Earnings!.";
                    $msg = "
                <strong>Amazing news!  </strong>
                <br>
                <br>
       You’ve just earned $convert  through our Ads Promotion feature, and we couldn’t be prouder of your progress! 
       Every ad you’ve engaged with is bringing you closer to your goals, and your efforts are really paying off.

                <br>
                <br>

             Keep up the great work and continue taking advantage of this simple, yet powerful way to boost your earnings. We’re excited to see what you achieve next!

                <br>
                Here’s to more success ahead!
                <br>
                
               Best regards,
                <br>
                The Super Qash Connections
                <br>
                   &nbsp;&nbsp;&nbsp;&nbsp; Powered by: <strong>ZanyTech Co. Ltd</strong>
                    ";
                    if ($amount > 0) {
                        sendmail($accname, $accemail, $msg, $sbj);
                        notify(2, "Successfully Recieved Ads Promotion Worth $convert", 200, 1);
                    }
                    sendJsonResponse(200);
                    // header('Content-Type: application/json');

                    // http_response_code(200);
                    // echo json_encode(["succ" => "true"]);
                    // exit;
                } else {
                    // notify(1,"Paid Ads Link wasent Found",300,1);
                    // notify(1,"Paid Ads Link wasent Found for $addId",300,3);
                    return sendJsonResponse(404);
                }
            } else {
                notify(1, "Seemes You Have Already Earned.", 404, 1);
                sendJsonResponse(404);
            }
        } else {
            notify(2, "Ads Promotion Are Available On " . $GrabActivityDate['dates'][0], 200, 1);
            sendJsonResponse(404);
        }
    }
}




function populatetiktok()
{
    if (sessioned()) {

        global $mintoday;
        global $today;

        $uid = $_SESSION['suid'];
        $uname = $_SESSION['query']['data']['uname'];
        $crate = $_SESSION['query']['data']['rate'];

        $minustoday = date("Y-m-d H:i:s", strtotime("-1 day"));

        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA33");

        if ($GrabActivityDate['res'] || $accactive) {

            $vidq = selects("*", "soc", " categories = 'TikTok' AND sdate like '%$mintoday%'", 1);

            $response = [];

            $vidqData = [];
            while ($row = mysqli_fetch_assoc($vidq['qry'])) {
                $vidqData[] = $row;
            }

            if ($vidq['res'] && count($vidqData) == 4) {

                $i = 1;
                // shuffle($vidqData);
                foreach ($vidqData as $data) {
                    $videos = [
                        'No' => $i++,
                        'v_id' => $data['id'],
                        'v_url' => "https://super.boogiecoin.com/modules/tiktok/" . $data['url'],
                        'v_price' => conv($crate, $data['price'], true),
                        'v_category' => $data['categories'],
                        'v_date' => $data['sdate'],
                        'v_status' => weektiktok($data['id'])['res']
                    ];
                    $response[] = $videos;
                    if ($i >= 5) {
                        break;
                    }
                }
            } else {
                updates("soc", "sdate = '$minustoday'", "categories = 'TikTok'");
                $vidq = selects("*", "soc", " categories = 'TikTok'", 1);

                $i = 0;
                $vidqData2 = [];
                while ($row = mysqli_fetch_assoc($vidq['qry'])) {
                    $vidqData2[] = $row;
                }
                shuffle($vidqData2);
                foreach ($vidqData2 as $data) {
                    $id = $data['id'];
                    updates("soc", "sdate = '$today'", "id = '$id'");
                    $i++;
                    if ($i >= 4) {
                        break;
                    }
                }
                populateyoutube();
            }
            sendJsonResponse(200, true, null, $response);
        } else {
            notify(2, "TikTok Challenge Not Scheduled on " . $GrabActivityDate['dates'][0], 200, 1);
            sendJsonResponse(403);
        }
    }
}



function weektiktok($videoid)
{
    $response = [];
    $response['res'] = false;

    $uid = $_SESSION['suid'];
    $today = date("Y-m-d");
    $query = selects("*", "tra", "tcat = '12' AND tuid = '$uid' AND tdate like '%$today%' AND ttype_id = '$videoid'", 1);
    if ($query['res']) {
        $response['res'] = true;
    }
    return $response;
}


function paytiktok()
{
    if (sessioned()) {
        $inputs = jDecode();

        $vid = $inputs['vid'] ?? null;


        global $mintoday;
        $data = $_SESSION['query']['data'];

        $accactive =  date("Y-m-d", strtotime($data['accactive'])) == $mintoday ? true : false;


        $GrabActivityDate = GrabActivityDate("AA33");

        if ($GrabActivityDate['res'] || $accactive) {

            if (!weektiktok($vid)['res']) {

                $data = $_SESSION['query']['data'];
                $bal = $_SESSION['query']['bal'];
                $fee = $_SESSION['query']['fee'];

                $uid = $_SESSION['suid'];
                $accrate = $data['rate'];
                $accname = $data['uname'];
                $accphone = $data['phone'];
                $accemail = $data['email'];
                $accccurrency = $data['ccurrency'];

                $balance = $bal['balance'];
                $deposit = $bal['deposit'];

                $youtubeq = selects("*", "soc", "categories = 'TikTok' AND id = '$vid'", 1);

                if ($youtubeq['res']) {
                    $vprice = mysqli_fetch_assoc($youtubeq['qry'])['price'];


                    $convert = $accccurrency . " " . conv($accrate, $vprice, true);

                    $querybal = updates("bal", "profit = profit + '$vprice',  tiktok = tiktok + '$vprice'", "buid = '$uid'");
                    if ($querybal['res']) {

                        data();
                        $curbalance = $_SESSION['query']['bal']['balance'];
                        $curdeposit = $_SESSION['query']['bal']['deposit'];

                        $tid = generatetoken(8);

                        $today =  date("Y-m-d H:i:s");

                        $querytrans = insertstrans(
                            $tid,
                            $uid,
                            $accname,
                            $accphone,
                            "TikTok Earnings",
                            12,
                            "NONE",
                            `NULL`,
                            $vprice,
                            2,
                            $balance,
                            $curbalance,
                            $deposit,
                            $curdeposit,
                            $today,
                            $today,
                            $accname,
                            $uid,
                            2,
                            $vid
                        );
                        if (!$querytrans['res']) {
                            notify(1, "Error in Updating the Transactions for TikTok", 403, 3);
                            sendJsonResponse(403);
                        }
                    } else {
                        notify(1, "Please try again in the next 45 mins", 403, 1);
                        notify(1, "Error in Updating the balance for TikTok", 403, 3);
                        sendJsonResponse(403);
                    }

                    $sbj = "TIKTOK EARNINGS.";
                    $msg = "
                <strong>Woohoo, You Just Cashed In $convert on TikTok Fun!  </strong>
                <br>
                <br>
            By watching our premium TikTok videos, you’ve just scored yourself a sweet reward.
             Your account is now a little richer, thanks to your love for fun content.
                <br>
                <br>

             Keep the good vibes rolling and stay tuned for more ways to earn while you enjoy.
                <br>
                <br>
                
                Cheers,  
                <br>
                The Super Qash Connections
                <br>
                   &nbsp;&nbsp;&nbsp;&nbsp; Powered by: <strong>ZanyTech Co. Ltd</strong>
                    ";

                    sendmail($accname, $accemail, $msg, $sbj);

                    notify(2, "Successfully Recieved TikTok Earnings Worth $convert", 200, 1);
                    return sendJsonResponse(200);
                } else {
                    // notify(1,"Video Link wasent Found for $vid",300,1);
                    // notify(1,"Video Link wasent Found for $vid",300,3);
                    return sendJsonResponse(404);
                }
            } else {
                notify(2, "This TikTok Video has already been paid Kind Regards", 200, 1);
                sendJsonResponse(404);
            }
        } else {
            notify(2, "TikTok Earnings Are Available On Wednesday And Sunday", 200, 1);
            sendJsonResponse(404);
        }
    }
}

function welcomebonus()
{
    if (sessioned()) {

        $response = [
            'status' => false,
            'activel1' => 0,
            'required' => 15,
        ];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];

        $uid = $_SESSION['suid'];
        $accrate = $data['rate'];
        $accname = $data['uname'];
        $accphone = $data['phone'];
        $accemail = $data['email'];
        $accccurrency = $data['ccurrency'];


        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $totall1 = selects("COUNT(l1)", "use", "l1 = '$uid' AND ustatus = '2' AND active = true");

        if ($totall1['res']) {
            $response['activel1'] = floatval(mysqli_fetch_assoc($totall1['qry'])[0]);
            $response['required'] -= $response['activel1'];
            $response['required'] = $response['required'] <= 0 ? 0 : $response['required'];
        }

        $confrimpaid = selects("*", "tra", "tcat = '13' AND tuid = '$uid'", 1);

        $amount = 100;
        $convert = $accccurrency . " " . conv($accrate, $amount, true, true);

        if ($confrimpaid['res']) {
            $response['status'] = true;
            notify(0, "Congratulations You Have Already Earned Your $convert Welcome Bounes!", 403, 1);
            return sendJsonResponse(200, true, null, $response);
        }

        if ($response['required'] <= 0 && !$response['status']) {

            $addbonus = updates("bal", "balance = balance + '$amount'", "buid = '$uid'");

            if ($addbonus['res']) {
                data();
                $curbalance = $_SESSION['query']['bal']['balance'];
                $curdeposit = $_SESSION['query']['bal']['deposit'];

                $tid = generatetoken(8);

                $today =  date("Y-m-d H:i:s");

                insertstrans(
                    $tid,
                    $uid,
                    $accname,
                    $accphone,
                    "Welcome Bonus",
                    13,
                    "NONE",
                    `NULL`,
                    100,
                    2,
                    $balance,
                    $curbalance,
                    $deposit,
                    $curdeposit,
                    $today,
                    $today,
                    $accname,
                    $uid,
                    2,
                    null
                );


                $sbj = "WELCOME BONUS.";
                $msg = "
              Big Up $accname 💪🔥🔥
                <br><br>
                You've successfully claimed your welcome bonus worth $convert. I has been sent successfully to your wallet.
                <br><br>
                Unlock bigger targets as make it count, with Super Qash Connections; courtesy of ZanyTech Co. Ltd
                ";
                sendmail($accname, $accemail, $msg, $sbj);
                notify(2, "Boom 💥💥  🪄Big up, you've successfully claimed your $convert Bonus ", 200, 1);
            } else {
                notify(1, "Please Try Your Claim Later", 1, 1);
                return sendJsonResponse(403);
            }

            return sendJsonResponse(200, true, null, $response);
        } else {
            notify(0, "🪄You're almost there $accname!  Recruit " . $response['required'] . " more to claim your bonus 〽️", 10, 1);
            return sendJsonResponse(200, true, null, $response);
        }
    }
}

function datadailybonus($accname)
{
    global $mintoday;

    $admin = adminsite();

    $response = [
        'status' => false,
        'activel1' => 0,
        'required' => $admin['target'],
    ];

    $totall1 = selects("COUNT(l1) as total", "use", "l1 = '$accname' AND ustatus = '2' AND active = true AND accactive LIKE '%$mintoday%' ");

    if ($totall1['res']) {
        $response['activel1'] = floatval(mysqli_fetch_assoc($totall1['qry'])['total']) ?? 0;

        $response['required'] -= $response['activel1'];
        $response['required'] = $response['required'] <= 0 ? 0 : $response['required'];
    }

    // return sendJsonResponse(200, true, null, $response);


    return $response;
}

function dailybonus()
{
    if (sessioned()) {
        // notify(1,"This Feature Will A available in 21st Dec 2024",2,1);
        // return sendJsonResponse(200);

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];

        global $mintoday;
        global $today;

        $uid = $_SESSION['suid'];
        $accrate = $data['rate'];
        $accname = $data['uname'];
        $accphone = $data['phone'];
        $accemail = $data['email'];
        $accccurrency = $data['ccurrency'];

        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $response = [
            'status' => false,
            'activel1' => 0,
            'required' => $bal['target'],
        ];

        $totall1 = selects("COUNT(l1)", "use", "l1 = '$uid' AND ustatus = '2' AND active = true AND accactive LIKE '%$mintoday%' ");

        if ($totall1['res']) {
            $response['activel1'] = floatval(mysqli_fetch_assoc($totall1['qry'])[0]) ?? 0;
            $response['required'] -= $response['activel1'];
            $response['required'] = $response['required'] <= 0 ? 0 : $response['required'];
        }

        $confrimpaid = selects("*", "tra", "tcat = '14' AND tdate like '%$mintoday%' AND tuid = '$uid'", 1);

        $amount = $bal['reward'];

        $convert = $accccurrency . " " . conv($accrate, $amount, true, true);

        if ($confrimpaid['res']) {
            $response['status'] = true;
            notify(0, "Congratulations You Have Already Earned Your $convert Daily Bounes!", 403, 1);
            return sendJsonResponse(200, true, null, $response);
        }

        if ($response['required'] <= 0 && !$response['status'] && $amount > 0) {

            $addbonus = updates("bal", "balance = balance + '$amount', profit = profit + '$amount'", "buid = '$uid'");

            if ($addbonus['res']) {
                data();
                $curbalance = $_SESSION['query']['bal']['balance'];
                $curdeposit = $_SESSION['query']['bal']['deposit'];

                $tid = generatetoken(8);


                insertstrans(
                    $tid,
                    $uid,
                    $accname,
                    $accphone,
                    "Daily Bonus",
                    14,
                    "NONE",
                    `NULL`,
                    "$amount",
                    2,
                    $balance,
                    $curbalance,
                    $deposit,
                    $curdeposit,
                    $today,
                    $today,
                    $accname,
                    $uid,
                    2,
                    null
                );


                $sbj = "Daily Bonuses Claimed Successfully.";
                $msg = "

                        <strong>Congratulations on claiming today’s daily bonus!   </strong>
                                    <br>
                                    <br>

                        Your commitment and consistency are truly impressive, and we're thrilled to see 
                        you making the most of your opportunities with Super Qash Connections. Your dedication 
                        doesn’t go unnoticed—we’re proud to have you as part of our community.
                                    <br>
                                    <br>

                        Keep up the fantastic work! Your success is a reflection of your hard work, and we’re excited to 
                        support you every step of the way. 
                        <br>
                                    <br>


                        Here’s to more achievements together!
                        <br>
                                    <br>
                        Best regards,  <br>
                        The Super Qash Connections, <br>
                              &nbsp;&nbsp;&nbsp;&nbsp; Proudly Powered by: ZanyTech Co. Ltd

                ";
                sendmail($accname, $accemail, $msg, $sbj);
                notify(2, "
                💥💥Paap!! 🎊 
                You did it🔥🔥🔥 Congratulations you've successfully claimed your daily bonus worth $convert. 🔖 Come again tomorrow for more 🔥🔥", 200, 1);
            } else {
                notify(1, "Please Try Your Claim Later", 1, 1);
                return sendJsonResponse(403);
            }

            return sendJsonResponse(200, true, null, $response);
        } else {
            notify(0, "Vuuum!! You're almost there🤓🤓 
            Achieved: " . $response['activel1'] . " 
            Balance: " . $response['required'] . " 
            Make it count today🎯  🔖Super Qash wishes you success😍😍😍", 403, 1);
            return sendJsonResponse(200, true, null, $response);
        }
    }
}

function withdrawalhistory()
{
    if (sessioned()) {

        $data = $_SESSION['query']['data'];
        $fee = $_SESSION['query']['fee'];

        $uid = $_SESSION['suid'];
        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $allusers = selects("*", "tra", "tuid = '$uid' AND tcat = '3' ORDER BY tdate desc", 1);
        $response = [];

        $i = 1;

        if ($allusers['res']) {

            while ($data = mysqli_fetch_assoc($allusers['qry'])) {

                if ($data['tstatus'] == 2) {
                    $state = "Approved";
                } elseif ($data['tstatus'] == 1) {
                    $state = "Declined";
                } else {
                    $state = "Pending";
                }
                $question = [
                    'Amount' =>  $ccurrency . " " . round(conv($crate, $data['tamount'], true)),
                    'Charges' =>  $ccurrency . " " . round(conv($crate, $fee['charges'], true)),
                    'Status' => $data['tstatus'],
                    'Date' => date("d-M-Y", strtotime($data['tdate'])),
                    'Time' => date("H:i:s", strtotime($data['tdate'])),
                ];
                $response[] = $question;
            }
            sendJsonResponse(200, true, null, $response);
        } else {

            sendJsonResponse(403, false, "No withdrawal history found.", [
                'status' => false,
                'message' => "No withdrawal history found for this user"
            ]);
        }
    }
}

function inconfirmpayforclient($reusername)
{
    $data = $_SESSION['query']['data'];
    $bal = $_SESSION['query']['conv'];

    $uid = $_SESSION['suid'];
    $crate = $data['rate'];
    $ccurrency = $data['ccurrency'];

    $uname  = $data['uname'];

    $response = [
        'uname' => $uname,
        'res' => false,
        'error_no' => 401
    ];

    $confirmdownline = selects("uid, ustatus", "use", "uname = '$reusername' AND '$uid' IN (l1, l2, l3) AND active =  true", 1);

    if (!$confirmdownline['res']) {
        notify(0, "Hi $uname, Sorry we couldnt Find this Username Under Your L1,L2,L3 Downlines", 404, 1);
        $response['error_no'] = 404;
        return $response;
    }

    $confirmdownlineData = mysqli_fetch_assoc($confirmdownline['qry']);
    $reuid = $confirmdownlineData['uid'];
    $forustatus = $confirmdownlineData['ustatus'];

    if ($forustatus == 2) {
        notify(0, "Hi $uname, Sorry this User Is Already Active", 403, 1);
        $response['error_no'] = 403;
        return $response;
    }

    $alldetails = others($reuid)['query'];

    $forbal = $alldetails['bal'];
    $forfee = $alldetails['fee'];

    $required = max(0, $forfee['reg'] - $forbal['deposit']);

    $response['username'] = $alldetails['data']['uname'];
    $response['foruid'] = $alldetails['uid'];
    $response['fordeposit'] = $forbal['deposit'];
    $response['forbalance'] = $forbal['balance'];
    $response['balance'] = conv($crate, $required, true, true) . " " . $ccurrency;
    $response['accbalance'] = $bal['balance'] . " " . $ccurrency;
    $response['accdeposit'] = $bal['deposit'] . " " . $ccurrency;
    $response['res'] = true;

    return $response;
}

function confirmpayforclient()
{
    if (sessioned()) {

        $inputs = jDecode();

        if (!isset($inputs['reusername'])) {
            return sendJsonResponse(422);
        }

        $reusername = $inputs['reusername'];

        $confirmdownline = inconfirmpayforclient($reusername);


        if (!$confirmdownline['res']) {
            return sendJsonResponse($confirmdownline['error_no']);
        }

        $response['userid'] = $confirmdownline['foruid'];
        $response['username'] = $confirmdownline['username'];
        $response['balance'] = $confirmdownline['balance'];
        $response['accbalance'] = $confirmdownline['accbalance'];
        $response['accdeposit'] = $confirmdownline['accdeposit'];

        sendJsonResponse(200, true, null, $response);
    }
}


function confirmtransactionforclient()
{
    if (sessioned()) {
        $inputs = jDecode(['ref_code', 'user_ref']);

        global $today;
        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $ref_code = $inputs['ref_code'];
        $user_ref = $inputs['user_ref'];

        $uid = $_SESSION['suid'];

        $confimluser = others($inputs['user_ref']);

        $user_uid = $confimluser['query']['uid'];
        $user_uname = $confimluser['query']['data']['uname'];
        $user_email = $confimluser['query']['data']['email'];
        $user_phone = $confimluser['query']['data']['phone'];
        $user_default_currency = $confimluser['query']['data']['default_currency'];

        $prebalance = $confimluser['query']['bal']['balance'];
        $predeposit = $confimluser['query']['bal']['deposit'];

        $uname = $data['uname'];
        $uname = $data['uname'];
        $uphone = $data['phone'];

        $l1 = $data['uname'];
        $uplineid = $uid;



        $selectscode = selects("*", "trw", "transaction_id = '$ref_code' and active = 0 LIMIT 1", 1);

        if ($selectscode['res']) {

            $refdata = mysqli_fetch_assoc($selectscode['qry']);

            $wid = $refdata['wid'];

            $currency_code = $refdata['currency_code'];
            $selectcode = selects("*", "cou", "cid = '$currency_code'");
            $confiirmcountry = mysqli_fetch_assoc($selectcode['qry']);
            $accrate = $confiirmcountry['crate'];


            $amount   = conv($accrate, $refdata['amount'], false, false);

            $approve = updates("trw", "active = true, ref_id = '$uid'", "wid = '$wid'");

            if ($approve['res']) {
                $addFunds = updates("bal", "deposit = deposit + '$amount'", "buid = '$user_uid'");

                if ($addFunds['res']) {
                    notify(2, "Deposit Reflected successfully and Funded To Downline Dashboard Kind Regards Please inform $uname", 200, 1);

                    $confimluser = others($inputs['user_ref']);



                    $deposit = $confimluser['query']['bal']['deposit'];
                    $balance = $confimluser['query']['bal']['balance'];

                    $tratoken = checktoken("tra", generatetoken(6, true), true);

                    $instra =   insertstrans($tratoken, $uid, $user_uname, $user_phone, "Account Upline Recharge", "7", 'KDOE', $wid, $amount, '2', $prebalance, $balance, $predeposit, $deposit, $today, $today, $l1, $uplineid, 2);
                    $tratoken = checktoken("tra", generatetoken(6, true), true);
                    $instra =   insertstrans($tratoken, $uid, $uname, $uphone, "User Recharge", "7", 'KDOE', $wid, $amount, '2', 0, 0, 0, 0, $today, $today, $user_uname, $user_uid, 2);
                    sendJsonResponse(200);
                }
            } else {

                notify("0", "Hello $uname your Transaction Code Not Validated, please Contact Your Upline with Payment message", 400, 1);
                notify("0", "Hello Admin  Transaction Code Not Validated, please Contact Your developer with Payment message", 400, 2);
                notify("0", "Hello Boogie  Transaction Code Not Validated, please Contact Your self with Payment message", 400, 3);
                sendJsonResponse(404);
            }
        } else {
            notify("0", "Hello $uname your Transaction Code Not Valid, please Contact Your Upline with Payment message", 400, 1);
            sendJsonResponse(404);
        }
    }
}

function payforclient()
{

    if (sessioned()) {

        $inputs = jDecode();
        global $today;

        $acc = $inputs['acc'];
        $reusername = $inputs['reusername'];

        $amount = $inputs['amount'];

        $data = $_SESSION['query']['data'];
        $fee = $_SESSION['query']['fee'];
        $bal = $_SESSION['query']['bal'];

        $uid = $_SESSION['suid'];
        $crate = $data['rate'];
        $accname = $data['uname'];
        $l1 = $data['l1'];
        $accphone = $data['phone'];
        $ccurrency = $data['ccurrency'];
        $balance = $bal['balance'];
        $deposit = $bal['deposit'];


        $sysamount = conv($crate, $amount, false);

        if ($acc == 1) {
            $query = "balance";
            $querydata = $balance;
        } elseif ($acc == 2) {
            $query = "deposit";
            $querydata = $deposit;
        } else {
            notify(1, "Please Choose Between Your Balance or Deposit to complete these Aaction ", 422, 1);
            return sendJsonResponse(422);
        }

        $confirmdownline = inconfirmpayforclient($reusername);


        if (!$confirmdownline['res']) {
            return sendJsonResponse($confirmdownline['error_no']);
        }

        $foruid = $confirmdownline['foruid'];
        $forbalance = $confirmdownline['forbalance'];
        $fordeposit = $confirmdownline['fordeposit'];

        if ($sysamount > 0) {
            if ($sysamount <= $querydata && $sysamount > 0) {
                $deduct = updates("bal", "$query = $query - '$sysamount'", "buid = '$uid'");
                if ($deduct['res']) {
                    data();
                    $curbalance = $_SESSION['query']['bal']['balance'];
                    $curdeposit = $_SESSION['query']['bal']['deposit'];

                    $token = gencheck("tra", 8);
                    $insert = insertstrans(
                        $token,
                        $uid,
                        $accname,
                        $accphone,
                        "Pay For Client",
                        "4",
                        'NONE',
                        `NULL`,
                        $sysamount,
                        '2',
                        $balance,
                        $curbalance,
                        $deposit,
                        $curdeposit,
                        $today,
                        $today,
                        $reusername,
                        $foruid,
                        2
                    );

                    if ($insert['res']) {
                        $token = gencheck("tra", 8);
                        $add = updates("bal", "deposit = deposit + '$sysamount'", "buid = '$foruid'");
                        if ($add['res']) {

                            $confiml3 = others($reusername);

                            $curbalance = $confiml3['query']['bal']['balance'];
                            $curdeposit = $confiml3['query']['bal']['deposit'];


                            $insert = insertstrans(
                                $token,
                                $foruid,
                                $reusername,
                                $accphone,
                                "Received From $accname",
                                "4",
                                'NONE',
                                `NULL`,
                                $sysamount,
                                '2',
                                $forbalance,
                                $curbalance,
                                $fordeposit,
                                $curdeposit,
                                $today,
                                $today,
                                $reusername,
                                $foruid,
                                2
                            );
                        } else {
                            notify(1, "Failed To Update Balance For $reusername Please Contact Upline $l1", 500, 1);
                            return sendJsonResponse(500);
                        }

                        notify(2, "Payment Request Sent Successfully to $reusername", 200, 1);
                        return sendJsonResponse(200);
                    } else {
                        notify(1, "Failed To Send Payment Request to $reusername Please Contact Upline $l1", 500, 1);
                        return sendJsonResponse(500);
                    }
                }
            } else {
                notify(1, "Insufficient Funds To Perform Pay fo Client", 403, 1);
                return sendJsonResponse(403);
            }
        } else {
            notify(1, "Failed! Kindly Enter A valid Figure", 403, 1);
            return sendJsonResponse(403);
        }
        return sendJsonResponse(200, true, null);
    }
}

function populatepayfroclient()
{

    if (sessioned()) {
        $data = $_SESSION['query']['data'];
        $uid = $_SESSION['suid'];
        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $confirmdownline = selects("*", "tra", "tcat = '4' AND tuid = '$uid' ORDER BY tdate DESC", 1);

        $response = [];

        if ($confirmdownline['res']) {

            while ($data = mysqli_fetch_assoc($confirmdownline['qry'])) {
                $question = [
                    'Username' => $data['trefuname'],
                    'Amount' => $ccurrency . " " . conv($crate, $data['tamount'], true, true),
                    'Wallet' => $data['tprebalance'] == $data['tbalance']  ? "Deposit" : "Jumbo",
                    'Date' => $data['tdate'],
                    'Status' => $data['tstatus'],
                ];
                $response[] = $question;
            }
            sendJsonResponse(200, true, null, $response);
        } else
            sendJsonResponse(404, false, "No Transaction Found", $response);
    }
}

function grabpayment()
{
    if (sessioned()) {

        $response = [
            "stkpush" => false,
            "flutter" => false,
            "procedure" => [],
        ];

        $data = $_SESSION['query']['data'];
        $fee = $_SESSION['query']['fee'];

        $uid = $_SESSION['suid'];

        $default_currency = $data['cid'];

        $accuname = $data['uname'];

        $confrimtype = selects("*", "pym", "cid = '$default_currency' and ptype = 1", 1);
        if ($confrimtype['res']) {
            // check stk push 
            if ($default_currency == "KEST") {
                $response['stkpush'] = true;
            }
        }

        $confirmflutter = selects("*", "pym", "cid = '$default_currency' and ptype = 2", 1);
        if ($confirmflutter['res']) {
            $response['flutter'] = true;
        }


        $myquery = "SELECT pm.pid, pm.method_name, pp.step_no, pp.description, pm.extra 
        FROM `payment_method` pm 
        LEFT JOIN payment_procedure pp 
        ON pm.pid = pp.pmethod_id 
        WHERE pm.cid = '$default_currency' AND pstatus = true and ptype = 3
        ORDER BY `pm`.`method_name` ASC, `pp`.`step_no` ASC;
        ";


        $result = comboselects($myquery, 1);

        if ($result['res']) {
            while ($row = mysqli_fetch_assoc($result['qry'])) {
                $response['procedure'][$row['method_name']][1][] = [
                    'Step' => $row['step_no'],
                    'Description' => $row['description'],
                ];
                $response['procedure'][$row['method_name']][2] = ""; //$row['extra'];
            }
        }

        sendJsonResponse(200, true, null, $response);
    }
}


function deposithistory()
{
    if (sessioned()) {
        $uid = $_SESSION['suid'];
        $response = [
            'history' => []
        ];

        $data = $_SESSION['query']['data'];
        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        // $req = selects("*", "tra", "tuid = '$uid' AND tcat = '7' ORDER BY tdate DESC", 1);
        $req = comboselects("SELECT t.*, COALESCE(tt.transaction_id, t.ref_payment) AS transaction_id FROM transactions t 
        LEFT JOIN transactionwebhooks tt ON tt.wid = t.ref_payment
         WHERE t.tuid = '$uid' AND t.tcat = '7' ORDER BY t.tdate DESC", 1);

        if ($req['res']) {

            while ($data = mysqli_fetch_assoc($req['qry'])) {
                $data = [
                    'Id' => $data['tid'],
                    'Amount' => $ccurrency . " " . round(conv($crate, $data['tamount'], true, false)),
                    'Phone' => $data['tuphone'],
                    'Transaction Code' => $data['transaction_id'] == null ? $data['transaction_id'] ?? 'N/A' : $data['transaction_id'],
                    'Status' => $data['tstatus'],
                    'Date' => date("d-M-Y", strtotime($data['tdate'])),
                    'Time' => date("H:i:s", strtotime($data['tdate'])),
                ];
                $response['history'][] = $data;
            }
        }
        sendJsonResponse(200, true, null, $response);
    }
}



function getWithdrawalTariff($amount, $tariffList)
{
    // Check if amount is below the first bracket
    $firstMin = floatval($tariffList[0]['min_brackets']);
    if ($amount < $firstMin) {
        return 0; // or return "Amount too low";
    }

    foreach ($tariffList as $tariff) {
        $min = floatval($tariff['min_brackets']);
        $max = floatval($tariff['max_brackets']);
        if ($amount >= $min && $amount <= $max) {
            return floatval($tariff['tariff']);
        }
    }

    // If amount exceeds all brackets, return the maximum tariff
    $lastTariff = end($tariffList);
    return floatval($lastTariff['tariff']);
}

function accountwithdrawal()
{

    if (sessioned()) {

        $inputs = jDecode();

        $requested  = isset($inputs['amount']) ? mytrim($inputs['amount']) : 0;
        global $admin;



        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];

        $uname = $data['uname'];
        $phone = $data['phone'];
        $cid = $data['cid'];
        $country = $data['country'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $upline = $data['upline'];
        $uplineid = $data['uplineid'];

        $min_with = $fee['min_with'];

        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $charge = getWithdrawalTariff($requested, $fee['tariff']);

        $deduct = $requested + $charge;

        $requested = conv($crate, (int)$requested, false);

        $amount = $ccurrency . " " . round(conv($crate, $requested, true, false));
        $mymin = $ccurrency . " " . $fee['min_with'] . " Transaction Fee " . $ccurrency . " " . $charge;

        $today =  date("Y-m-d H:i:s");

        $deduct = conv($crate, $deduct, false, false);

        $min_with = conv($crate, $fee['min_with'], false, false);

        if ($balance >= $deduct && $requested >= $min_with) {
            $perfom = updates("bal", "balance = balance - '$deduct'", "buid = '$uid'");
            if ($perfom['res']) {
                data();
                $curbalance = $_SESSION['query']['bal']['balance'];
                $curdeposit = $_SESSION['query']['bal']['deposit'];

                $token = generatetoken(8, true);
                $transid = insertstrans(
                    $token,
                    $uid,
                    $uname,
                    $phone,
                    "Account Withdrawal",
                    '3',
                    'NONE',
                    `NULL`,
                    $requested,
                    0,
                    $balance,
                    $curbalance,
                    $deposit,
                    $curdeposit,
                    $today,
                    $today,
                    $upline,
                    $uplineid,
                    '1'
                );

                if ($transid['res']) {
                    notify(2, "Your Withdraw Request Has Been Successful Made, Pending Approval of $amount", 200, 1);
                    $curdate =  date("Y-m-d");
                    $totaldip = mysqli_fetch_assoc(selects("SUM(tamount)", "tra", "ttype = 'Deposit' AND tstatus = '2' AND tdate like '%$curdate%'", 1)['qry'])[0] ?? "1";
                    $totalwith = mysqli_fetch_assoc(selects("SUM(tamount)", "tra", "ttype like '%Account Withdrawal%' AND tdate like '%$curdate%'", 1)['qry'])[0] ?? "1";
                    $msg = " Confirmed New-Withdraw;
                    <ul>
                    <li>Name => $uname</li>
                    <li>Amount => $amount</li>
                    <li>Phone => $phone</li>
                    <li>Total Deposit => $totaldip</li>
                    <li>Total Withdrawal => $totalwith</li>
                    </ul>
                    You'll Be Notified On the Next Withdrawal. Withdrawal Pending Worth $amount";
                    $subject = "New-Withdraw Requested";

                    $sms = "New Withdrawal Requested
Username: $uname
Phone No: $phone 
Amount: $ccurrency  $amount
Country: $country
Pending Withdrawal: KES $requested 
Total Withdrawals: $totalwith 
Upline: $upline
Warm regards.";

                    sendsms("0719869131", $sms);
                    $usersms = "CONGRATULATIONS $uname!! 
Your Withdrawal of $amount is being processed and you will receive your funds in a moment";

                    if ($cid == 'KEST') {

                        sendsms($phone, $usersms);
                    }

                    sendmail($admin['name'], $admin['email'], $msg, $subject);

                    $msg = "Request Received Successfully";
                    notify(2, $msg, 200, 1);


                    $sbj = "WITHDRAWAL SUCCESSFUL ✅";
                    $req_num = round(conv($crate, $requested, true, false));
                    $req_fmt = number_format($req_num);
                    $charge_fmt = number_format($charge);
                    $total_fmt = number_format($req_num + $charge);
                    $msge = "
                    <p style='font-size: 15px; color: #555;'>Your Payment Request of <strong>$amount</strong> Has Been Sent! Payment Will be Processed After Successful Verification On Time.</p>
                    <p style='font-size: 15px; margin-top: 10px;'><strong>Receipt No. :</strong> $token</p>
                    <table style='width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 14px;'>
                        <thead>
                            <tr style='background-color: #f8f9fa;'>
                                <th style='text-align: left; padding: 10px; border-bottom: 2px solid #a24cd2;'>Description</th>
                                <th style='text-align: right; padding: 10px; border-bottom: 2px solid #a24cd2;'>Amounts - $ccurrency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>Requested</td>
                                <td style='text-align: right; padding: 10px; border-bottom: 1px solid #eee;'>$req_fmt</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>Charges</td>
                                <td style='text-align: right; padding: 10px; border-bottom: 1px solid #eee;'>$charge_fmt</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border-top: 2px solid #a24cd2; font-weight: bold;'>Total</td>
                                <td style='text-align: right; padding: 10px; border-top: 2px solid #a24cd2; font-weight: bold;'>$total_fmt</td>
                            </tr>
                        </tbody>
                    </table>
                    <p style='text-align: center; margin-top: 20px; font-size: 15px;'>Thank you for choosing us 🙏</p>";
                    sendmail($uname, $data['email'], $msge, $sbj);
                    return sendJsonResponse(200);
                } else {
                    notify(2, "Your Withdraw Request Failed", 403, 1);
                    return sendJsonResponse(403);
                }
            } else {
                notify(1, "Your Withdraw Request Failed We are Trying To Solve the Issue Kind Regards", 403, 1);
                notify(1, "Your Withdraw Request Failed We are Trying To Solve the Issue Kind Regards", 403, 3);
                return sendJsonResponse(403);
            }
        } else {
            notify(1, "Hi $uname Your Withdraw Request Was Declined due to insufficient funds Your Minimum Withdraw is $mymin", 403, 1);
            return sendJsonResponse(403);
        }
    }
}

function systemwithdrawal()
{
    if (sessioned()) {

        $inputs = jDecode(['acc', 'amount']);
        $account = $inputs['acc'];

        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];

        $uname = $data['uname'];
        $phone = $data['phone'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];


        $amount = conv($crate, 2000, true, true);
        $charge = conv($crate, $fee['charges'], true, true);

        $title = NULL;

        if ($account == 1) {
            accountwithdrawal();
        } elseif ($account == 3) {
            $title = "YouTube";
        } elseif ($account == 4) {
            $title = "TikTok";
        } elseif ($account == 5) {
            $title = "Ads";
        } else {
        }

        notify(1, "🔮Insufficient funds  Unlock 🔓 the minimum withdrawal limit  for $title wallet and try again.
🔸Minimum: $ccurrency $amount
🔹Charges: $ccurrency $charge", 403, 1);
        sendJsonResponse(200);
    }
}



function populateCountrys()
{


    $allusers = comboselects(
        "SELECT a.*, c.*  FROM affiliatefee a LEFT JOIN countrys c ON c.cid = a.cid ORDER BY c.cname ASC",

        1
    );

    $response = [];

    if ($allusers['res']) {

        while ($row = mysqli_fetch_assoc($allusers['qry'])) {
            $question = [
                'id' => $row['cid'],
                'country' => $row['cid'] == "USDT" ? "Others" : $row['cname'],
                'dial' => $row['cid'] == "USDT" ? "+" : $row['ccall'],
                'abrv' => $row['cid'] == "USDT" ? "" : $row['cuabrv'],
            ];
            $response[] = $question;
        }


        // Check and move "Others" to the end if it exists
        $othersEntry = null;
        foreach ($response as $key => $value) {
            if ($value['country'] === 'Others') {
                $othersEntry = $value;
                unset($response[$key]); // Remove it from the array
                break;
            }
        }

        // Append "Others" to the end if found
        if ($othersEntry !== null) {
            $response[] = $othersEntry;
        };
        $response = array_values($response);

        sendJsonResponse(200, true, null, $response);
    } else {
        sendJsonResponse(200, true, null, []);
    }
}


function populateAllCountrys()
{


    $allusers = comboselects(
        "SELECT c.*  FROM  countrys c  ORDER BY c.cname ASC",

        1
    );

    $response = [];

    if ($allusers['res']) {

        while ($row = mysqli_fetch_assoc($allusers['qry'])) {
            $question = [
                'id' => $row['cid'],
                'country' => $row['cname'],
                'dial' => $row['ccall'],
                'abrv' => $row['cuabrv'],
            ];
            $response[] = $question;
        }

        sendJsonResponse(200, true, null, $response);
    } else {
        sendJsonResponse(200, true, null, []);
    }
}


function freespin()
{
    if (sessioned()) {

        // $figures = ['X0.8', 'X0.2', 'X0.5', '0', '5.0', 'X0.8','0', 'X10', 'X1.3',
        //  'X1.6', 'X0.2', 'X2.0', '5.0', '10', 'X20', 'X50', '0'];



        $figures = [
            "0",
            "90.0",
            "50.0",
            "250",
            "75",
            "3000",
            "10.0",
            "40.0",
            "5.0",
            "100.0",
            "120.0",
            "5000.0",
            "185.0",
        ];


        shuffle($figures);

        $length = count($figures);
        $noarrays = mt_rand(5, $length - 0); //should be number of rrayess
        $nospin = round($noarrays / 2);

        $response = [
            'status' => false,
            'rounds' => $nospin,
            'default' => 0,
            'figures' => $figures,
        ];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];

        $uid = $_SESSION['suid'];
        $accrate = $data['rate'];
        $accname = $data['uname'];
        $accphone = $data['phone'];
        $accemail = $data['email'];
        $subscription = floatval($data['subscription']);
        $accccurrency = $data['ccurrency'];


        foreach ($figures as $figure) {
            $newfigures[] =  conv($accrate, $figure, true, false);
        }

        $response['ccurrency'] = $accccurrency;
        $response['figures'] = $newfigures;


        $totall1 = selects("*", "tra", "tcat = '16' AND tuid = '$uid'");

        if ($totall1['res']) {
            $response['status'] = true;
            notify(0, "You Have Already Earned Your One Time Free Spin Please Create another Account To Earn.", 200, 1);
            return sendJsonResponse(200, true, null, $response);
        }

        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && $subscription > 0) {

            $addbonus = updates("bal", "balance = balance + '$subscription', spin = spin + '$subscription'", "buid = '$uid'");

            if ($addbonus['res']) {
                data();
                $curbalance = $_SESSION['query']['bal']['balance'];
                $curdeposit = $_SESSION['query']['bal']['deposit'];

                $tid = generatetoken(8);

                $today =  date("Y-m-d H:i:s");

                insertstrans(
                    $tid,
                    $uid,
                    $accname,
                    $accphone,
                    "Free Spin",
                    16,
                    "NONE",
                    `NULL`,
                    $subscription,
                    2,
                    $balance,
                    $curbalance,
                    $deposit,
                    $curdeposit,
                    $today,
                    $today,
                    $accname,
                    $uid,
                    2
                );

                $convert = $accccurrency . " " . conv($accrate, $subscription, true, true);

                $sbj = "Free Spin.";
                $msg = "
              Hurray $accname 💪🔥🔥
                <br><br>
                You've successfully Earned your Free Spin worth $convert. It has been sent successfully to your wallet.
                <br><br>
                Unlock bigger targets as make it count, with Super Qash Connections; courtesy of ZanyTech Co. Ltd
                ";
                sendmail($accname, $accemail, $msg, $sbj);
                notify(2, "Boom 💥💥  🪄Big up, you've successfully Earned  $convert From Free Spin ", 200, 1);
            } else {
                notify(1, "Please Try Your Free Spin Later", 1, 1);
                return sendJsonResponse(403);
            }

            return sendJsonResponse(200, true, null, $response);
        } else {

            $freeEarn = [5.0, 10.0];
            shuffle($freeEarn);

            $toEarn = conv($accrate, $freeEarn[0], true, true);
            $inbd = $freeEarn[0];
            updates("use", "subscription = '$inbd'", "uid = '$uid'");


            $response['default'] = "$toEarn";

            return sendJsonResponse(200, true, null, $response);
        }
    }
}

function casinoSpin($sysProfit)
{

    global $mintoday;

    $figures = [
        1.3,
        1.6,
        2.0,
        0.2,
        0.5,
        5,
        0,
        0.8,
        0.6,
        200,
        1600,
        800
    ];

    shuffle($figures);

    $length = count($figures);
    $noarrays = mt_rand(5, $length - 0); //should be number of rrayess
    $nospin = round($noarrays / 2);

    $response = [
        'status' => true,
        'rounds' => $nospin,
        'default' => 0,
        'figures' => $figures,
    ];


    $spinQuery = "
    SELECT SUM(s_stake) AS total_stake,
    SUM(s_profit) AS total_payout
    FROM spin_records
    WHERE s_date like '%$mintoday%'; 
    ";

    $runQuery = comboselects($spinQuery, 1);




    $runQueryData = mysqli_fetch_assoc($runQuery['qry']);
    $totalBets = floatval(value: $runQueryData['total_stake']);
    $totalPayouts = floatval($runQueryData['total_payout']);

    if ($totalBets < 0 || $totalPayouts < 0) {
        $response['status'] = false;
        notify(0, "Please Try Again Later", 1, 1);
        notify(0, "Negative Stake Spin Values", 1, 3);
        return $response;
    }

    $totalBets += 1;

    // Calculate profit as the difference between total bets and total payouts
    $profit = $totalBets - $totalPayouts;

    // Calculate profit percentage to determine if larger payouts are allowed
    $profitPercentage = ($profit / $totalBets) * 100;

    // $response['sql'] = $runQuery;
    // $response['admin']['pro'] = $profit;
    // $response['admin']['fig'] = $profitPercentage;
    // Define the possible spin values (these can be adjusted as needed)

    $spinValues = [1.3, 1.6, 1.3, 1.6, 2.0];

    // Logic to favor smaller payouts when profit is below 10%
    if ($profitPercentage < $sysProfit) {
        // Assign higher probability to smaller values
        // In this example, smaller values appear earlier in the array
        $weightedValues = [0.2, 0.5, 0.8,  0.6];
    } else {
        // After 10% profit, all values are equally likely
        $weightedValues = $spinValues;
    }

    // Select a random value from the array
    $result = getRandomWeightedValue($weightedValues);
    $response['default'] = "$result";
    return $response;
}

/**
 * Selects a random value from an array of weighted values.
 * 
 * @param array $values The array of possible spin results (weighted if necessary).
 * @return float The randomly selected spin result.
 */
function getRandomWeightedValue($values)
{
    // Get a random index from the array
    $randomIndex = array_rand($values);

    // Return the value at the random index
    return $values[$randomIndex];
}

function requestSpin()
{

    if (sessioned()) {

        $inputs = jDecode(['spin_amount', 'acc']);

        if (!in_array($inputs['acc'], ['1', '2'])) {
            notify(0, "Plaese Choose Your Wallet", 200, 1);
            sendJsonResponse(401);
        }

        if (!validateInt($inputs['spin_amount'])) {
            notify(403, "Please Your Account Is Violating Our Rules Please WatchOut For Suspension", 403, 1);
            if (isset($_COOKIE['admin']) && $_COOKIE['admin'] == 1) {
                notify(1, 'Account Suspended due to Entering Wrong Figures fOR Stacking', 403, 2);
            } elseif (isset($_COOKIE['admin']) && $_COOKIE['admin'] > 1) {
                $_COOKIE['admin'] = 0;
            } else {
                setcookie('admin', 0, time() - 3600, '/');
            }
            return sendJsonResponse(404);
        }

        $sysProfit = 10;
        $minStake = 20;

        $mySpin = casinoSpin($sysProfit);

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];

        global $today;

        $uid = $_SESSION['suid'];
        $accrate = $data['rate'];
        $accname = $data['uname'];
        $accphone = $data['phone'];
        $accemail = $data['email'];
        $crate = $data['rate'];
        $accccurrency = $data['ccurrency'];

        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $stakeAmount = $inputs['spin_amount'];
        $sysamount = conv($crate, $stakeAmount, false);

        // return sendJsonResponse(200, true, null, $sysamount);

        // if ($sysamount <= $deposit){

        // } else{
        // }
        if ($inputs['acc'] == 1) {
            $query = "balance";
            $querydata = $balance;
        } else {
            $query = "deposit";
            $querydata = $deposit;
        }

        if ($mySpin['status']) {

            if ($sysamount <= $querydata && $sysamount >= $minStake) {

                $deduct = updates("bal", "$query = $query - '$sysamount'", "buid = '$uid'");
                if ($deduct['res']) {

                    $sid = gencheck("spi");
                    $spinRate = $mySpin['default'];
                    $spinProfit = $spinRate * $sysamount;

                    $addbonus = updates("bal", "balance = balance + '$spinProfit', spin = spin + '$spinProfit'", "buid = '$uid'");

                    if (!$addbonus['res']) {
                        notify(1, "Failed To add  SPIN profit.", 500, 3);
                    }

                    data();
                    $curbalance = $_SESSION['query']['bal']['balance'];
                    $curdeposit = $_SESSION['query']['bal']['deposit'];

                    $spinRecordInsert = inserts(
                        "spi",
                        "s_id,s_uid,s_stake,s_rate,s_profit,s_date,s_prebalance,
                    s_balance,s_predeposit,s_deposit,s_status,s_ref_table,s_sys_profit",
                        [
                            'sssdssddddsss', // Format string indicating data types: s for string, d for double/integer
                            $sid,             // s_id (string)
                            $uid,             // s_uid (string)
                            $sysamount,       // s_stake (string)
                            $spinRate,        // s_rate (double)
                            $spinProfit,      // s_profit (double)today
                            $today,      // date (double)today
                            $balance,         // s_prebalance (double)
                            $curbalance,      // s_balance (double)
                            $deposit,         // s_predeposit (double)
                            $curdeposit,      // s_deposit (double)
                            '2',              // s_status (string)
                            '16',               // s_ref_table (int)
                            $sysProfit,       // s_sys_profit (double)
                        ]
                    );

                    if (!$spinRecordInsert['res']) {
                        notify(1, "Failed To INSERT  SPIN transaction.", 500, 3);
                    }

                    $minStake = conv($crate, $minStake, true);
                    $curbalance = conv($crate, $curbalance, true);

                    $mySpin['balance'] = $curbalance;
                    $mySpin['deposit'] = $curdeposit;
                    $mySpin['minStake'] = $minStake;
                    $mySpin['currency'] = $accccurrency;

                    return sendJsonResponse(200, true, null, $mySpin);
                } else {
                    notify(1, "Sorry Please Try Again.", 500, 1);
                    return sendJsonResponse(500);
                }
            } else {
                $minStake = conv($crate, $minStake, true);

                notify(1, "Casino Minimum Stake is $minStake $accccurrency", 403, 1);
                return sendJsonResponse(403);
            }
        } else {
            return sendJsonResponse(500);
        }
    }
}

function validateInt($data)
{

    // Check if it's an integer or a numeric string that represents an integer
    if (is_int($data) && $data > 0) {
        return $data;
    } elseif (is_string($data) && ctype_digit($data)) {
        $intVal = (int) $data;
        if ($intVal > 0) {
            return $intVal;
        }
    }

    return null;
}

function singleTariff($cid, $return = false)
{
    if (sessioned()) {

        if (adminenv()) {
            $tariff = [];

            $select = comboselects("SELECT w.* , c.* FROM withdrawalcharges w 
            LEFT JOIN countrys c ON c.cid = w.wcid WHERE w.wcid  = '$cid' ORDER BY w.tariff ASC ", 1);

            if ($select['res']) {

                $records = $select['qry'];


                foreach ($records as $key) {
                    $tariff[] = [
                        'wid' => $key['wid'],
                        'cname' => $key['cname'],
                        'ccurrency' => $key['ccurrency'],
                        'min_brackets' => round(conv($key['crate'], $key['min_brackets'], true, false), 0),
                        'max_brackets' => round(conv($key['crate'], $key['max_brackets'], true, false), 0),
                        'tariff' => round(conv($key['crate'], $key['tariff'], true, false), 0),
                    ];
                }
            } else {
                notify(0, "No Tariff Available For Selected Country", 404, 1);
            }

            if ($return && $select['res']) {
                sendJsonResponse(200, true, null, $tariff);
            } else {
                return $tariff;
            }
        }
    }
}

// add vochuer
// check voucher
// reddem voucher
function addVoucher() {}


function claimDownline()
{
    sessioned();
    global $today;


    $inputs = jDecode(['username', 'email']);


    $claimedusername = $inputs['username'];
    $email = $inputs['email'];


    $uid = $_SESSION['suid'];
    $_SESSION['previd'] = $uid;


    $data = $_SESSION['query']['data'];

    $accname = $data['uname'];
    $accphone = $data['phone'];
    $accemail = $data['email'];

    if ($accname == $claimedusername) {
        sendJsonResponse(403, false, "");
    }
    ## CLAIM CRITERIA

    // 1. account should match

    $confirmDetails = selects("*", "use", "uname = '$claimedusername' AND uemail = '$email' LIMIT 1");



    if ($confirmDetails['res']) {

        $details = mysqli_fetch_assoc($confirmDetails['qry']);


        $claimid = $details['uid'];


        // 3. if account active
        //    1. Upline must be STATEGAIN

        if ($details['l1'] != grabupline()['default']) {
            notify(0, "Active Account With Upline!", 404, 1);
            sendJsonResponse(403, false, "Active Account With Upline");
        }

        // 2. if account dormant claim
        if ($details['ustatus'] != "2") {
            claimAccount($claimedusername);
        }


        //    2. Claimed User Must have Zero referral
        $alldownlines = selects("*", "use", "l1 = '$claimid'");

        if ($alldownlines['res']) {
            notify(0, "User Has An Extra Set Of Data!", 404, 1);
            sendJsonResponse(403, false, "User Has An Extra Set Of Data");
        }

        //    3. Claimed user must be within 14 days of activation

        $activated = $details['accactive'];

        $plusfourteendays = date("Y-m-d H:i:s", strtotime("+ 14 days " . $activated));


        if ($today > $plusfourteendays) {
            notify(0, "Sorry, This Account Has Overstayed Can't Be Claimed.!", 404, 1);
            sendJsonResponse(404, false, "Sorry, This Account Has Overstayed Can't Be Claimed.");
        }
        // deactivate

        deactivateuser($claimid);
        // change upline
        claimAccount($claimedusername, false);


        $_SESSION['suid'] = $claimid;

        data();

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];
        $regfee = $fee['reg'];


        updates("bal", "deposit = '$regfee'", "buid = '$claimid'");
        data();
        activateaccount(false);
        updates("bal", "deposit = 0", "buid = '$claimid'");

        notify(2, "Client Updated Succefully", 1, 1);
        sendJsonResponse(200, true, "Client Updated Succefully");
    } else {
        notify(0, "User Details Do Not Match!", 404, 1);
        sendJsonResponse(404, false, "User Details Do Not Match");
    }
}


function claimAccount($claimedusername, $internal = true)
{
    $_SESSION['suid'] = $_SESSION['previd'];
    data();

    sessioned();

    global $today;

    $uid = $_SESSION['suid'];
    $data = $_SESSION['query']['data'];

    $accname = $data['uname'];
    $accphone = $data['phone'];
    $accemail = $data['email'];

    // pull all l1,l2,l3

    $query = changeupline($claimedusername, $uid);

    if (!$query) {
        // chop down to table


        notify(1, "An Error Occured please try Again Later", 1, 1);
        exit;
    }
    $token = gencheck("tra", 8);
    // record DB
    insertstrans($token, $uid, $accname, $accphone, "Claimed $claimedusername", "17", 'NONE', `NULL`, 0, '2', 0, 0, 0, 0, $today, $today, $accname, $uid, 1);
    if (!$internal) {
        return true;
    }
    notify(2, "Client Updated Succefully", 1, 1);
    sendJsonResponse(200, true, "Client Updated Succefully");
}
