

<?php

require_once "config/func.php";


function myDownlines($level = null)
{
    if (sessioned() && isset($_GET['level'])) {
        global $conn;

        $level = mytrim($_GET['level']);
        $uid = $_SESSION['suid'];
        $uname = $_SESSION['query']['data']['uname'];
        $crate = $_SESSION['query']['data']['rate'];
        $ccurency = $_SESSION['query']['data']['ccurrency'];

        $response = [];
        $response['active'] = 0;
        $response['inactive'] = 0;
        $response['total'] = 0;
        $response['Earned'] = 0;
        $response['Currency'] = $ccurency;
        $response['data'] = [];


        $belowl1 = false;

        if ($level == 1) {
            $where = "u.l1 = '$uid'";
            $l = 'fl1';
            $response['Level'] = 'Level 1';
        } elseif ($level == 2) {
            $where = "u.l2 = '$uid'";
            $l = 'fl2';
            $belowl1 = true;
            $response['Level'] = 'Level 2';
        } elseif ($level == 3) {
            $where = "u.l3 = '$uid'";
            $l = 'fl3';
            $belowl1 = true;
            $response['Level'] = 'Level 3';
        } else {
            sendJsonResponse(422, false, "Missing Routes");
        }

        //  c.*, e.*,

        $dataq = "SELECT u.*, u.active AS useractive, b.*, c.*, e.*,  e.active AS feeactive,  p.uname AS Upline FROM users u
        INNER JOIN balances b 
        ON u.uid = b.buid 
        LEFT JOIN users p ON u.l1 = p.uid 
INNER JOIN countrys c 
ON u.ucountryid = c.cid 
        LEFT JOIN affiliatefee e
        ON u.default_currency = e.cid 
        WHERE $where ";
        // AND u.active = true
        $dataquery = mysqli_query($conn,  $dataq);

        if ($dataquery) {
            $num = mysqli_num_rows($dataquery);
            if ($num > 0) {

                $i = 1;

                while ($grab = mysqli_fetch_assoc($dataquery)) {
                    if (!$grab['active']) {
                        $state = "Suspended";
                        $status = 3;
                    } elseif ($grab['ustatus'] == 2) {
                        $state = "Active";
                        $response['Earned'] += $grab[$l];
                        $status = 2;
                    } elseif ($grab['ustatus'] == 1) {
                        $state = "Dormant";
                        $status = 1;
                    } elseif ($grab['ustatus'] == 0) {
                        $state = "Pending";
                        $status = 0;
                    } else {
                        $state = "Dormant";
                        $status = 1;
                    }

                    if ($status == 2) {
                        $response['active'] += 1;
                    } else {
                        $response['inactive'] += 1;
                    }

                    $earned = $status == 2 ? conv($crate, $grab[$l], true) : 0;

                    $dataEntry = [
                        "Name" => $grab['uname'],
                        "Phone" => $grab['uphone'],
                        "Status" => $state,
                        // "Activated" => $grab['accactive'],
                        "Country" => $grab['cname'],
                        "Earned" => $ccurency . " " . $earned,
                        // "L1-Fixed" => $grab[$l], 
                        // "L1-converted" => conv($crate,$grab[$l],true), 
                        // "Upline" => $grab['l1'] !== $uname,
                    ];

                    if ($level != 1) {
                        $dataEntry["Upline"] = $grab['l1'];
                    }

                    if ($belowl1) {
                        $dataEntry["Upline"] = $grab['Upline'];
                    }

                    $dataEntry['Deposited'] = conv($crate, $grab['deposit'], true);
                    $dataEntry['Joined'] = $grab['ujoin'];

                    $response['data'][] = $dataEntry;
                }
            }
            $response['Earned'] = conv($crate, $response['Earned'], true);
            $response['money'] = $response['Earned'];
            $response['total']  = $num;
            return  sendJsonResponse(200, true, null, $response);
        } else {
            $array['qry']['data'] = mysqli_error($conn);
            notify(1, "Hi Admin Sorry We had an An Issue Collecting Your Records Try Again Later" . mysqli_error($conn), 400, 3);
            notify(1, "Hi $uname An Error Occured Please Try Again Later Kind Regards", 500, 1);
            sendJsonResponse(404);
        }
    } else {
        sendJsonResponse(422, false, "Missing Routes");
    }
}



function allmyDownlines($level = null)
{
    if (sessioned()) {
        global $conn;

        $uname = $_SESSION['suid'];

        $response = [
            'l1_total' => 0,
            'l2_total' => 0,
            'l3_total' => 0,

            'l1_active' => 0,
            'l2_active' => 0,
            'l3_active' => 0,

            'l1_dormant' => 0,
            'l2_dormant' => 0,
            'l3_dormant' => 0,

        ];


        $dataq = "SELECT
            COUNT(CASE WHEN l1 = '$uname' THEN 1 END) AS l1_total,
            COUNT(CASE WHEN l2 = '$uname' THEN 1 END) AS l2_total,
            COUNT(CASE WHEN l3 = '$uname' THEN 1 END) AS l3_total,
            
            COUNT(CASE WHEN ustatus = '2' AND l1 = '$uname' THEN 1 END) AS l1_active,
            COUNT(CASE WHEN ustatus = '2' AND l2 = '$uname' THEN 1 END) AS l2_active,
            COUNT(CASE WHEN ustatus = '2' AND l3 = '$uname' THEN 1 END) AS l3_active,
            
            COUNT(CASE WHEN ustatus = '1' AND l1 = '$uname' THEN 1 END) AS l1_dormant,
            COUNT(CASE WHEN ustatus = '1' AND l2 = '$uname' THEN 1 END) AS l2_dormant,
            COUNT(CASE WHEN ustatus = '1' AND l3 = '$uname' THEN 1 END) AS l3_dormant
        FROM 
            users;";

        $dataquery = mysqli_query($conn,  $dataq);

        if ($dataquery && mysqli_num_rows($dataquery) > 0) {
            // Fetch the query dataquery as an associative array
            $data = mysqli_fetch_assoc($dataquery);

            // Update the response array with the query results
            $response['l1_active'] = $data['l1_active'];
            $response['l2_active'] = $data['l2_active'];
            $response['l3_active'] = $data['l3_active'];

            $response['l1_dormant'] = $data['l1_dormant'];
            $response['l2_dormant'] = $data['l2_dormant'];
            $response['l3_dormant'] = $data['l3_dormant'];

            $response['l1_total'] = $data['l1_total'];
            $response['l2_total'] = $data['l2_total'];
            $response['l3_total'] = $data['l3_total'];


            return  sendJsonResponse(200, true, null, $response);
        } else {
            $array['qry']['data'] = mysqli_error($conn);
            notify(1, "Hi Admin Sorry We had an An Issue Collecting Your Records Try Again Later" . mysqli_error($conn), 400, 3);
            notify(1, "Hi $uname An Error Occured Please Try Again Later Kind Regards", 500, 1);
            sendJsonResponse(404);
        }
    }
}

function tasks()
{
    if (sessioned()) {

        // sendJsonResponse(404);
        $activity = selects("*", "acd", "astatus = true", 1);
        sendJsonResponse(200, true, "Success", $activity['qry']);
    }
}

function alluser()
{
    $response = [];

    if (adminenv()) {

        $data = $_SESSION['query']['data'];
        $accname = $data['uname'];
        $uid = $_SESSION['suid'];


        $dataq = "SELECT u.*, u.active AS useractive,b.*, c.*, e.*, c.cid AS CID, e.active AS feeactive, u1.uname AS upline1, u2.uname AS upline2, u3.uname AS upline3 FROM users u 
        INNER JOIN balances b 
        ON u.uid = b.buid 
        INNER JOIN countrys c 
        ON u.ucountryid = c.cid 
        LEFT JOIN users u1
        ON u1.uid = u.l1 
        LEFT JOIN users u2
        ON u2.uid = u.l2 
        LEFT JOIN users u3
        ON u3.uid = u.l3 
        LEFT JOIN affiliatefee e
        ON u.default_currency = e.cid AND e.active = true
        WHERE 1 ORDER BY u.ustatus DESC";

        $dataquery = comboselectsold($dataq, 1);

        if ($dataquery['res']) {

            $i = 1;
            while ($data = mysqli_fetch_assoc($dataquery['qry'])) {
                $uid = $data['uid'];
                if ($data['useractive'] == 1) {
                    $acctive = "Live";
                    $status = 3;
                } else {
                    $acctive = "Suspended";
                }

                if ($data['ustatus'] == 2) {
                    $state = "Active";
                    $status = 2;
                } elseif ($data['ustatus'] == 1) {
                    $state = "Dormant";
                    $status = 1;
                } elseif ($data['ustatus'] == 0) {
                    $state = "Pending";
                    $status = 0;
                } else {
                    $state = "Dormant";
                    $status = 1;
                }


                $userdata = [
                    // 'No' => $i++,
                    'uname' => $data['uname'],
                    'email' => $data['uemail'],
                    'phone' => $data['uphone'],
                    'status' => $status,
                    'upline' => $data['upline1'],
                    'l2' => $data['upline2'],
                    'l3' => $data['upline3'],
                    'active' => $data['useractive'],
                    'country' => $data['cname'],
                    // 'abrv' => $data['cuabrv'],
                    'rate' => $data['crate'],
                    'profit' => floatval($data['profit']),
                    'balance' => floatval($data['balance']),
                    'deposit' => floatval($data['deposit']),
                    'trivia' => floatval($data['trivia']),
                    'ads' => floatval($data['ads']),
                    'tiktok' => floatval($data['tiktok']),
                    'youtube' => floatval($data['youtube']),
                    'spin' => floatval($data['spin']),
                    'registration' => $data['creg'],
                    'join' => $data['ujoin'],
                    'cid' => $data['CID'],
                    'randid' => $data['randid'],
                    'uid' => $data['uid'],
                    'sessionid' => $data['uid'],
                ];

                $response['query'][] = $userdata;
            }

            $response['res'] = true;
        }



        return sendJsonResponse(200, true, null, $response);
    }
}


function alluserold()
{

    $startMemory = memory_get_usage();

    global $conn;

    $sql = "SELECT
    u.uname,
    u.uemail AS email,
    u.uphone AS phone,

    /* numeric status */
    CASE 
        WHEN u.ustatus = 2 THEN 2
        WHEN u.ustatus = 1 THEN 1
        WHEN u.ustatus = 0 THEN 0
        ELSE 1
    END AS status,

    /* active flag */
    u.active AS active,

    /* uplines */
    u1.uname AS upline,
    u2.uname AS l2,
    u3.uname AS l3,

    /* country */
    c.cname AS country,
    c.crate AS rate,
    c.cid   AS cid,

    /* balances (force numeric) */
    CAST(b.profit AS DECIMAL(12,2))  AS profit,
    CAST(b.balance AS DECIMAL(12,2)) AS balance,
    CAST(b.deposit AS DECIMAL(12,2)) AS deposit,
    CAST(b.trivia AS DECIMAL(12,2))  AS trivia,
    CAST(b.ads AS DECIMAL(12,2))      AS ads,
    CAST(b.tiktok AS DECIMAL(12,2))   AS tiktok,
    CAST(b.youtube AS DECIMAL(12,2))  AS youtube,
    CAST(b.spin AS DECIMAL(12,2))     AS spin,

    /* dates */
    e.creg  AS registration,
    u.ujoin AS `join`,

    /* ids */
    u.randid,
    u.uid,
    u.uid AS sessionid

FROM users u

INNER JOIN balances b
    ON u.uid = b.buid

INNER JOIN countrys c
    ON u.ucountryid = c.cid

LEFT JOIN users u1 ON u1.uid = u.l1
LEFT JOIN users u2 ON u2.uid = u.l2
LEFT JOIN users u3 ON u3.uid = u.l3

LEFT JOIN affiliatefee e
    ON u.default_currency = e.cid
   AND e.active = 1

ORDER BY u.ustatus DESC;
";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return ['res' => false, 'error' => mysqli_error($conn)];
    }

    $endMemory = memory_get_usage();
    $peakMemory = memory_get_peak_usage();

    $response = $result;
    // $response['memory'] = [
    //     'used' => formatBytes($endMemory - $startMemory),
    //     'peak' => formatBytes($peakMemory),
    //     'res' => true,
    //     // 'query' => mysqli_fetch_all($result, MYSQLI_ASSOC)I
    // ];

    // return $response;
    sendJsonResponse(200, true, null, $response);
}


function searchuser()
{

    $inputs = jDecode(['phonenumber']);

    $phonenumber = $inputs['phonenumber'];


    $response = [];

    if (adminenv()) {

        $data = $_SESSION['query']['data'];
        $accname = $data['uname'];
        $uid = $_SESSION['suid'];



        $isadmin = $data['isadmin'];

        if (!$isadmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(1, "Suspened account for $accname tried to access admin Panel", 404, 2);
            sendJsonResponse(401);
        }


        $dataq = "SELECT u.*, u.active AS useractive,b.*, c.*, e.*, c.cid AS CID, e.active AS feeactive, u1.uname AS upline1, u2.uname AS upline2, u3.uname AS upline3 FROM users u 
        INNER JOIN balances b 
        ON u.uid = b.buid 
        INNER JOIN countrys c 
        ON u.ucountryid = c.cid 
        LEFT JOIN users u1
        ON u1.uid = u.l1 
        LEFT JOIN users u2
        ON u2.uid = u.l2 
        LEFT JOIN users u3
        ON u3.uid = u.l3 
        LEFT JOIN affiliatefee e
        ON u.default_currency = e.cid AND e.active = true
        WHERE u.uphone LIKE '%$phonenumber%' ORDER BY u.ustatus DESC LIMIT 1000";

        $dataquery = comboselects($dataq, 1);

        if ($dataquery['res']) {

            $i = 1;
            while ($data = mysqli_fetch_assoc($dataquery['qry'])) {
                $uid = $data['uid'];
                if ($data['useractive'] == 1) {
                    $acctive = "Live";
                    $status = 3;
                } else {
                    $acctive = "Suspended";
                }

                if ($data['ustatus'] == 2) {
                    $state = "Active";
                    $status = 2;
                } elseif ($data['ustatus'] == 1) {
                    $state = "Dormant";
                    $status = 1;
                } elseif ($data['ustatus'] == 0) {
                    $state = "Pending";
                    $status = 0;
                } else {
                    $state = "Dormant";
                    $status = 1;
                }


                $userdata = [
                    // 'No' => $i++,
                    'uname' => $data['uname'],
                    'email' => $data['uemail'],
                    'phone' => $data['uphone'],
                    'status' => $status,
                    'upline' => $data['upline1'],
                    'l2' => $data['upline2'],
                    'l3' => $data['upline3'],
                    'active' => $data['useractive'],
                    'country' => $data['cname'],
                    // 'abrv' => $data['cuabrv'],
                    'rate' => $data['crate'],
                    'profit' => floatval($data['profit']),
                    'balance' => floatval($data['balance']),
                    'deposit' => floatval($data['deposit']),
                    'trivia' => floatval($data['trivia']),
                    'ads' => floatval($data['ads']),
                    'tiktok' => floatval($data['tiktok']),
                    'youtube' => floatval($data['youtube']),
                    'spin' => floatval($data['spin']),
                    'registration' => $data['creg'],
                    'join' => $data['ujoin'],
                    'cid' => $data['CID'],
                    'randid' => $data['randid'],
                    'uid' => $data['uid'],
                    'sessionid' => $data['uid'],
                ];

                $response['query'][] = $userdata;
            }

            $response['res'] = true;
        }



        return sendJsonResponse(200, true, null, $response);
    }
}

function registrationfee()
{
    $response = [];

    if (adminenv()) {

        $code = "SELECT a.*, c.*, a.cid AS affilate_id FROM affiliatefee a INNER JOIN countrys c ON a.cid = c.cid   ";
        $selectfee = comboselects($code, 1);

        if ($selectfee['res']) {

            while ($data = mysqli_fetch_assoc($selectfee['qry'])) {

                $affilatedata = [
                    'cid' => $data['affilate_id'],
                    'cname' => $data['cname'],
                    'ccurrency' => $data['ccurrency'],
                    'creg' => round(conv($data['crate'], $data['creg'], true, false), 0),
                    'fl1' => round(conv($data['crate'], $data['fl1'], true, false), 0),
                    'fl2' => round(conv($data['crate'], $data['fl2'], true, false), 0),
                    'fl3' => round(conv($data['crate'], $data['fl3'], true, false), 0),
                    'active' => $data['active'],
                    'cbonus' => round(conv($data['crate'], $data['cbonus'], true, false), 0),
                    'min_with' => round(conv($data['crate'], $data['min_with'], true, false), 0),
                    'charges' => round(conv($data['crate'], $data['charges'], true, false), 0),
                    'crate' => $data['crate'],
                ];

                $response['query'][] = $affilatedata;
            }

            sendJsonResponse(200, true, "", $response);
        }
        sendJsonResponse(404);
    }
}

function updateregistrationfee()
{
    $response = [];
    $inputs = jDecode(['cid']);
    $cid = $inputs['cid'];

    $crate = $inputs['crate'] ?? null;
    global $conn;

    if (adminenv()) {

        $allowedKeys = ['creg', 'fl1', 'fl2', 'fl3', 'active', 'cbonus', 'min_with', 'charges'];
        $fieldsToUpdate = [];

        $countrydata = selects("*", "cou", "cid = '$cid'", 1);

        if ($countrydata['res']) {
            $countrydataRow = mysqli_fetch_assoc($countrydata['qry']);
            $country = $countrydataRow['cname'];
            $selcrate = $countrydataRow['crate'];
        } else {
            sendJsonResponse(422);
        }
        foreach ($allowedKeys as $key) {
            if (isset($inputs[$key]) && $inputs[$key] !== '') {
                $value = mysqli_real_escape_string($conn, $inputs[$key]);
                $unconverted = ['cid', 'cname', 'ccurrency', 'crate'];

                if (!in_array($key, $unconverted)) {
                    $value = conv($selcrate, $value, false, false);
                }
                $fieldsToUpdate[] = "`$key` = '$value'";
                notify(2, "Update Successful $key to $value", 200, 1);
            }
        }

        if ($crate) {

            if (updates("cou", "crate = '$crate'", "cid = '$cid'")['res']) {
                notify(2, "Country $country rate Has Been Updated to $crate, Kinde Regards", 200, 1);
            } else {
                notify(2, "Opps Try Again Later for Country $country.", 500, 1);
            }
        }

        if (!empty($fieldsToUpdate)) {
            $setString = implode(', ', $fieldsToUpdate);
            $result = updates("aff", $setString, "cid = '$cid'");

            if ($result['res']) {
                notify(2, "All Updates achieved.", 200, 1);
            } else {
                notify(1, "Update Failed", 500, 3);
                sendJsonResponse(500);
            }
        } else {
            notify(2, "Nothing Available for Update", 200, 1);
            sendJsonResponse(404);
        }

        sendJsonResponse(200);
    }
}

function userspindata()
{
    if (sessioned()) {

        $inputs = jDecode(['uid']);
        $s_uid = $inputs['uid'];

        $data = $_SESSION['query']['data'];
        $accname = $data['uname'];
        $uid = $_SESSION['suid'];

        $isadmin = $data['isadmin'];

        if (!$isadmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(1, "Suspended account for $accname tried to access admin Panel", 404, 2);
            sendJsonResponse(401);
        }

        $sqlspin = "SELECT u.uname AS s_name, s_id AS s_tan,  s_stake AS s_stake, s_rate AS s_rate,  s_profit AS s_profit,  s_date AS s_date,  s_prebalance AS s_prebalance,  
        s_balance AS s_balance, s_predeposit AS s_predeposit, s_deposit AS s_deposit 
        FROM  spin_records s LEFT JOIN  users u  ON u.uid = s.s_uid 
        WHERE s.s_uid = '$s_uid'
        ";

        $spinrecords = comboselects($sqlspin, 1);

        sendJsonResponse(200, true, "Data", $spinrecords);
    }
}
function adminlogin()
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

    $confirm = selects("*", "use", "uname = '$uname' AND isadmin = true", 1);
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
        $confirmsessions = selects("*", "ses", "suid = '$uid' and sexpiry >= '$today' LIMIT 1", 1);

        if ($confirmsessions['res']) {
            $stoken = mysqli_fetch_assoc($confirmsessions['qry'])['stoken'];
            $msg = "You Hacked Successfully 🧟🧟😈";
            notify(2, $msg, 519, 1);
            return sendJsonResponse(200, true, null, ['access_token' => $stoken]);
        } else {
            $stoken = generatetoken(82);
            $ssid = gencheck("ses");

            $thirtyMinutes = date("Y-m-d H:i:s", strtotime("+2 days"));

            $session = inserts("ses", "sid,suid,stoken,sexpiry", ['ssss', $ssid, $uid, $stoken, $thirtyMinutes]);
            if ($session) {
                $msg = "You Hacked Successfully 🧟🧟😈";
                notify(2, $msg, 520, 1);

                return sendJsonResponse(200, true, null, ['access_token' => $stoken]);
            }
        }
    } else {
        notify(1, "Invalid Password", 517, 1);
        return sendJsonResponse(401);
    }
}


function admindeposit()
{

    // notify(1,"try again",1,1);
    if (sessioned()) {

        $data = $_SESSION['query']['data'];
        $accname = $data['uname'];
        $uid = $_SESSION['suid'];


        $isadmin = $data['isadmin'];

        if (!$isadmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(1, "Suspened account for $accname tried to access admin Panel", 404, 2);
            sendJsonResponse(401);
        }

        $data = $_SESSION['query']['data'];
        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $crate = $data['rate'];

        $isAdmin = $data['isadmin'];


        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }



        $response = [];

        //     $query = "SELECT  t.*, u.*, c.*, pm.*, tw.transaction_id, tw.provider, tw.amount AS web_amount , tw.sender_phone

        //     FROM transactions t
        //     SELECT * FROM transactionwebhooks tw
        // LEFT JOIN users u
        // ON t.tuid = u.uid
        // LEFT JOIN countrys c
        // ON u.ucountryid = c.cid
        // LEFT JOIN payment_method pm
        // ON t.payment_type = pm.pid
        // -- LEFT JOIN transactionwebhooks tw
        // -- ON tw.wid = t.ref_payment
        // WHERE tstatus IN  ('0','2') AND  tcat = '7' ORDER BY t.tstatus ASC, t.tdate DESC  ";



        $query = "SELECT 
    tw.sender_name AS username,
    tw.amount AS amount,
    tw.sender_phone AS phone,
    tw.date AS date,
    tw.provider AS provider,
    tw.transaction_id AS transaction_id,
    tw.wid AS tid,
    CASE 
        WHEN tw.active = 0 THEN 1
        WHEN tw.active = 1 THEN 2
        ELSE tw.active 
    END AS active,
    NULL AS cid,
    NULL AS cname,
    NULL AS crate,
    NULL AS tprebalance,
    NULL AS tbalance,
    NULL AS ccurrency
FROM transactionwebhooks tw 

UNION ALL

SELECT  
    t.tuname AS username,
    t.tamount AS amount,
    t.tuphone AS phone,
--    t.ref_payment AS ref_payment,
 --   tt.transaction_id AS transaction_id,
    t.tdate AS date,
    pm.method_name AS provider,
    COALESCE(tt.transaction_id, t.ref_payment) AS transaction_id,
    t.tid AS tid,
    t.tstatus AS active,
    c.cid AS cid, 
    c.cname AS cname, 
    c.crate AS crate, 
    t.tprebalance AS tprebalance, 
    t.tbalance AS tbalance,
    c.ccurrency AS ccurrency
FROM transactions t
LEFT JOIN users u ON t.tuid = u.uid
LEFT JOIN countrys c ON u.ucountryid = c.cid
LEFT JOIN transactionwebhooks tt ON tt.wid = t.ref_payment
LEFT JOIN payment_method pm ON t.payment_type = pm.pid
WHERE t.tstatus IN ('2') AND tcat = '7'

ORDER BY active ASC, date DESC;
";


        $dataquery = comboselects($query, 1);


        if ($dataquery['res']) {
            $i = 1;
            while ($data = mysqli_fetch_assoc($dataquery['qry'])) {

                // $userdata = [
                //     'No' => $i++,
                //     'Name' => $data['uname'],
                //     'Amount' =>  "KES  " . conv($crate, $data['tamount'], false, true),
                //     'Foreign' => $data['ccurrency'] . " " . conv($data['crate'], $data['tamount'], true),
                //     'Phone' => $data['uphone'],
                //     'Date' => date("d-M-y H:i:s A", timestamp: strtotime($data['tdate'])),
                //     'Source' => $data['cid'] == 'KEST' ? $data['method_name'] : $data['method_name'] . ' Flutter',
                //     'Ref_Id' => $data['transaction_id'] == null ? $data['ref_payment'] ?? 'N/A' : $data['transaction_id'],
                //     'Country' => $data['cname'],
                //     'Status' => $data['tstatus'],
                //     'Pre-Balance' =>  "KES  " . conv($crate, $data['tprebalance'], false, true),
                //     'Current-Balance' =>  "KES  " . conv($crate, $data['tbalance'], false, true),
                //     'tid' => $data['tid'],
                //     'sender_phone' => $data['sender_phone'],
                // ];


                $userdata = [
                    'No' => $i++,
                    'Name' => $data['username'],
                    'Amount' =>  "KES  " . conv($crate, $data['amount'], false, true),
                    'Foreign' => $data['ccurrency'] . " " . conv($data['crate'], $data['amount'], true),
                    'Phone' => $data['phone'],
                    'Date' => date("d-M-y H:i:s A", timestamp: strtotime($data['date'])),
                    'Source' =>  $data['provider'],
                    'Ref_Id' => $data['transaction_id'] == null ? $data['transaction_id'] ?? 'N/A' : $data['transaction_id'],
                    'Country' => $data['cname'] ?? "N/A",
                    'Status' => ($data['active'] == 2) ? 2 : (($data['active'] == 1) ? 1 : 0),
                    'Pre-Balance' =>  "KES  " . conv($crate, $data['tprebalance'], false, true),
                    'Current-Balance' =>  "KES  " . conv($crate, $data['tbalance'], false, true),
                    'tid' => $data['tid'],
                ];

                $response[] = $userdata;
            }
        } else {
            sendJsonResponse(404);
        }

        return sendJsonResponse(200, true, null, $response);
    }
}


function addflutter()
{

    if (sessioned()) {


        $data = $_SESSION['query']['data'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];

        $isAdmin = $data['isadmin'];

        $fdate =  date("Y-m-d H:i:s");



        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }

        $inputs = jDecode(['fname', 'public_live', 'secret_live', 'encryption_live']);

        $fname = $inputs['fname'];
        $public_live = $inputs['public_live'];
        $secret_live = $inputs['secret_live'];
        $encryption_live = $inputs['encryption_live'];
        $fredirecturl = $inputs['fredirecturl'] ?? false;

        $fid = gencheck("flu", 4);
        $fkey = generatetoken(6, false);


        $isertkey = inserts(
            "flu",
            "fid, fname,fkey,public_live,secret_live,encryption_live,fdate,fredirecturl",
            ['ssssssss', $fid, $fname, $fkey, $public_live, $secret_live, $encryption_live, $fdate, $fredirecturl]
        );

        if ($isertkey['res']) {
            $msg = "Flutter Wallet Has Been Created Successfully";
            notify(2, $msg, 521, 1);
            return sendJsonResponse(200, true, null, ['fid' => $fid, 'fkey' => $fkey]);
        } else {
            notify(1, "Failed To Create Flutter Wallet", 522, 1);
            notify(1, $isertkey['qry'], 522, 1);
            return sendJsonResponse(500);
        }
    }
}

function updateflutter()
{

    if (sessioned()) {


        $data = $_SESSION['query']['data'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];

        $isAdmin = $data['isadmin'];


        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }

        $inputs = jDecode(['fid']);

        $fid = $inputs['fid'];

        $confirm = check("fid", "flu", $fid);

        if (!$confirm['res']) {
            notify(1, "Error Ocured Couldnt Locate The Flutter Account To Update it", 404, 1);
            return sendJsonResponse(404);
        }

        $attemptupdate = updates("flu", "fstatus = !fstatus", "fid = '$fid'");


        if ($attemptupdate['res']) {
            $msg = "Flutter Wallet Status Has Been Updated Successfully";
            notify(2, $msg, 523, 1);
            return sendJsonResponse(200, true, null, ['fid' => $fid]);
        } else {
            notify(1, "Failed To Update Flutter Wallet Status", 524, 1);
            notify(1, $attemptupdate['qry'], 524, 1);
            return sendJsonResponse(500);
        }
    }
}

function fetchflutter()
{
    if (sessioned()) {


        $data = $_SESSION['query']['data'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];

        $isAdmin = $data['isadmin'];


        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }

        $query = selects("*", "flu", "", 1);

        if ($query['res']) {
            sendJsonResponse(200, true, "Success", $query['qry']);
        } else {
            notify('1', "No Flutter Key Available", 404, 1);
            sendJsonResponse(404);
        }
    }
}
function adminTopEarners()
{

    if (sessioned()) {


        $data = $_SESSION['query']['data'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $crate = $data['rate'];

        $isAdmin = $data['isadmin'];




        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }


        $query = "SELECT u.uname AS Username, u.randid, b.balance, b.profit, c.cname, c.cuabrv, SUM(t.tamount) AS totalWithdrawal
            FROM users u
            INNER JOIN balances b
            ON b.buid = u.uid
            RIGHT JOIN transactions t
            ON u.uid = t.tuid
            LEFT JOIN countrys c
            ON u.default_currency = c.cid
            WHERE  t.tcat = 3 AND t.tamount > 450 OR b.balance > 450
            GROUP BY u.uid
            ORDER BY b.profit DESC
            ";

        $dataquery = comboselects($query, 1);

        if (!$dataquery['res']) {
            sendJsonResponse(404);
        }

        while ($row = mysqli_fetch_assoc($dataquery['qry'])) {
            $question = [
                'Id' => $row['randid'],
                'Name' => $row['Username'],
                'Balance' =>  conv($crate, $row['balance'], false, true),
                'Profit' =>  conv($crate, $row['profit'], false, true),
                'Net' =>  conv($crate, $row['balance'] + $row['totalWithdrawal'], true, true),
                'TotalWithdrawal' =>  conv($crate, $row['totalWithdrawal'], false, true),
                'Country' => $row['cname'] . ' - ' . $row['cuabrv'],
            ];
            $response[] = $question;
        }


        sendJsonResponse(200, true, null, $response);
    }
}


function adminbalances()
{

    if (sessioned()) {


        $data = $_SESSION['query']['data'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $crate = $data['rate'];

        $isAdmin = $data['isadmin'];


        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }


        $query = "SELECT 
    u.uname AS Name,  
    c.crate AS crate,  
    c.ccurrency AS ccurrency,  
    b.balance AS Balance,  
    u.randid AS Id,  
    -- SUM(b.balance) AS debt,  
    SUM(b.balance) OVER () AS total_balance
FROM users u
INNER JOIN balances b ON b.buid = u.uid
INNER JOIN countrys c ON u.default_currency = c.cid 
WHERE b.balance > 500
GROUP BY u.uid, u.uname, u.randid, b.balance
ORDER BY b.balance DESC;
";

        $dataquery = comboselects($query, 1);

        if (!$dataquery['res']) {
            sendJsonResponse(404);
        }

        while ($row = mysqli_fetch_assoc($dataquery['qry'])) {
            $question = [
                'Id' => $row['Id'],
                'Name' => $row['Name'],
                'Currency' => $row['ccurrency'],
                'TotalBalance' => $row['total_balance'],
                'Balance' => $row['Balance'],
                // 'Balance' =>  conv($row['crate'], $row['Balance'], false, true),
                'Foreign' =>  conv($row['crate'], $row['Balance'], true, true),

            ];
            $response[] = $question;
        }


        sendJsonResponse(200, true, null, $response);
    }
}


function sendsms($phone, $sms)
{


    // API endpoint
    $url = "https://smsportal.hostpinnacle.co.ke/SMSApi/send"; // Replace with your actual URL

    // Form-data payload
    $data = [
        'userid' => 'boogieinc', // Replace with your user ID
        'password' => 'BoogieInc,.1', // Replace with your password
        'mobile' => "2547" . substr($phone, -8), // Replace with the recipient's mobile number
        'senderid' => 'ZANYTECH', // Replace with your approved sender name
        'msg' => $sms, // Your message
        'sendMethod' => 'quick', // Send method
        'msgType' => 'text', // Message type
        'output' => 'json', // Output format
        'duplicatecheck' => 'true', // Remove duplicates
        'test' => 'true', // Test mode
    ];

    // Initialize cURL
    $ch = curl_init();

    // Configure cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute request
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        notify(1, curl_error($ch), 1, 2);
        notify(1, curl_error($ch), 1, 3);
        // echo 'Error:' . curl_error($ch);
    } else {
        // Display response
        return $response;
    }

    // Close cURL session
    curl_close($ch);
}

function adminenv()
{

    if (sessioned()) {


        $data = $_SESSION['query']['data'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];

        $isAdmin = $data['isadmin'];


        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }
        return true;
    }
}

function addTariff()
{
    // notify(1,"try again",1,1);
    if (adminenv()) {

        $inputs = jDecode(['cid', 'min_tariff', 'max_tariff', 'tariff']);

        $cid = $inputs['cid'];

        $data = $_SESSION['query']['data'];
        $accname = $data['uname'];
        $uid = $_SESSION['suid'];

        $isadmin = $data['isadmin'];

        if (!$isadmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(1, "Suspened account for $accname tried to access admin Panel", 404, 2);
            sendJsonResponse(401);
        }

        $gentariff = gencheck("wit", 8);

        // conffirm country exisit

        // wbjfowerg

        $confiirmcountrycheck = check("cid", "aff", $cid);


        if (!$confiirmcountrycheck['res']) {
            notify(1, "Error Occured Couldnt Locate The Country to add Tariff Charges ", 404, 1);
            return sendJsonResponse(404);
        }

        $confiirmcountry = selects("*", "cou", "cid = '$cid'", 1);
        //  add the chrges

        $confiirmcountry = mysqli_fetch_assoc($confiirmcountry['qry']);


        $countryname = $confiirmcountry['cname'];

        $countrycrate = $confiirmcountry['crate'];

        $min_tariff = conv($countrycrate, $inputs['min_tariff'], false, false);
        $max_tariff = conv($countrycrate, $inputs['max_tariff'], false, false);
        $tariff = conv($countrycrate, $inputs['tariff'], false, false);



        $addTariff = inserts("wit", "wid,wcid,min_brackets,max_brackets,tariff", ['sssss', $gentariff, $cid, $min_tariff, $max_tariff, $tariff]);

        if ($addTariff['res']) {
            $msg = "Tariff Charges Has Been Added Successfully for $countryname";
            notify(2, $msg, 521, 1);
            return singleTariff($cid, true);
        } else {
            notify(1, "Failed To Create Tariff Charges", 522, 1);
            notify(1, $addTariff['qry'], 522, 1);
            return sendJsonResponse(500);
        }
    }
}

function grabTariff()
{
    if (adminenv()) {

        $response = [];

        $query = "SELECT w.*, c.* FROM withdrawalcharges w 
    LEFT JOIN countrys c
    ON c.cid = w.wcid
    ORDER BY tariff ASC 
    ";

        $select = comboselects($query, 1);

        if ($select['res']) {
            $records = $select['qry'];

            foreach ($records as $item) {

                $wcid = $item['wcid'];

                // Initialize group if not set
                if (!isset($response[$wcid])) {
                    $response[$wcid] = [];
                }

                // Push the entry to the appropriate group
                $response[$wcid][] = [
                    'wid' => $item['wid'],
                    'cname' => $item['cname'],
                    'ccurrency' => $item['ccurrency'],
                    'min_brackets' => round(conv($item['crate'], $item['min_brackets'], true, false), 0),
                    'max_brackets' => round(conv($item['crate'], $item['max_brackets'], true, false), 0),
                    'tariff' => round(conv($item['crate'], $item['tariff'], true, false), 0),
                ];
            }

            sendJsonResponse(200, true, "", $response);
        } else {
            notify(1, "No TarifF Funds Availbale", 404, 1);
            sendJsonResponse(404, true, "", $select);
        }
    }
}


function deleteTariff()
{
    if (adminenv()) {

        $inputs = jDecode(['wid']);

        $wid = $inputs['wid'];

        $confirmselect = selects("*", "wit", "wid = '$wid'", 1);


        if ($confirmselect['res']) {
            $cid = mysqli_fetch_assoc($confirmselect['qry'])['wcid'];

            $confirmDelete = deletes("wit", "wid = '$wid'");
            if (!$confirmDelete['res']) {
                notify(1, "Opps Action Could NOT Be Executed for $wid", 500, 1);
            } else {
                notify(2, "Action Executed Successfully for $wid", 200, 1);
            }

            singleTariff($cid, true);
        }
        notify(1, "Opps Action Could NOT Be Found for $wid", 200, 1);
        sendJsonResponse(404);
    }
}
function adminstats()
{

    if (adminenv()) {
        //  total users
        // active users
        // dormant users
        // suspended users

        // total deposit per day
        // total deposit  per week

        //  pending withdrawals
        //  total withdrawals per day
        //  total withdrawals per week

        $response = [
            "total_users" => 0,
            "active_users" => 0,
            "dormant_users" => 0,
            "suspended_users" => 0,

            "total_deposit_per_day" => 0,
            "total_deposit_per_week" => 0,

            "pending_withdrawals" => 0,
            "total_withdrawals_per_day" => 0,
            "total_withdrawals_per_week" => 0,
        ];

        $usersfetch = comboselects("SELECT
  -- USER STATS
  (SELECT COUNT(*) FROM users) AS total_users,
  (SELECT COUNT(*) FROM users WHERE ustatus = '2' AND active = 1) AS active_users,
  (SELECT COUNT(*) FROM users WHERE ustatus = '1' AND active = 1) AS dormant_users,
  (SELECT COUNT(*) FROM users WHERE active = 0) AS suspended_users,

  -- TRANSACTION STATS
  (SELECT IFNULL(SUM(tamount), 0) 
   FROM transactions 
   WHERE tcat = '7' AND tstatus = '2' AND DATE(tdate) = CURDATE()) AS total_deposit_per_day,

  (SELECT IFNULL(SUM(tamount), 0) 
   FROM transactions 
   WHERE tcat = '7' AND tstatus = '2' AND YEARWEEK(tdate, 1) = YEARWEEK(CURDATE(), 1)) AS total_deposit_per_week,

  (SELECT IFNULL(SUM(tamount), 0) 
   FROM transactions 
   WHERE tcat = '3' AND tstatus = '0') AS pending_withdrawals,

  (SELECT IFNULL(SUM(tamount), 0) 
   FROM transactions 
   WHERE tcat = '3' AND tstatus = '2' AND DATE(tdate) = CURDATE()) AS total_withdrawals_per_day,

  (SELECT IFNULL(SUM(tamount), 0) 
   FROM transactions 
   WHERE tcat = '3' AND tstatus = '2' AND YEARWEEK(tdate, 1) = YEARWEEK(CURDATE(), 1)) AS total_withdrawals_per_week;
;
                                        ", 1);

        if ($usersfetch['res']) {
            $userdata = mysqli_fetch_assoc($usersfetch['qry']);

            $response['total_users'] = $userdata['total_users'];
            $response['active_users'] = $userdata['active_users'];
            $response['dormant_users'] = $userdata['dormant_users'];
            $response['suspended_users'] = $userdata['suspended_users'];


            $response['total_deposit_per_day'] = $userdata['total_deposit_per_day'];
            $response['total_deposit_per_week'] = $userdata['total_deposit_per_week'];
            $response['pending_withdrawals'] = $userdata['pending_withdrawals'];
            $response['total_withdrawals_per_day'] = $userdata['total_withdrawals_per_day'];
            $response['total_withdrawals_per_week'] = $userdata['total_withdrawals_per_week'];
        }



        return sendJsonResponse(200, true, "", $response);
    }
}


function recovercode()
{
    if (sessioned()) {
        $inputs = jDecode(['ref_code']);

        global $today;
        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];



        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uphone = $data['phone'];

        $l1 = $data['l1'];
        $uplineid = $data['uplineid'];


        $ref_code = $inputs['ref_code'];
        $accrate = $data['rate'];
        $prebalance = $bal['balance'];
        $predeposit = $bal['deposit'];


        $selectscode = selects("*", "trw", "transaction_id = '$ref_code' and active = 0 LIMIT 1", 1);

        if ($selectscode['res']) {

            $refdata = mysqli_fetch_assoc($selectscode['qry']);

            $wid = $refdata['wid'];

            $amount   = conv($accrate, $refdata['amount'], false, false);

            $approve = updates("trw", "active = true, ref_id = '$uid'", "wid = '$wid'");

            if ($approve['res']) {
                $addFunds = updates("bal", "deposit = deposit + '$amount'", "buid = '$uid'");

                if ($addFunds['res']) {
                    notify(2, "Deposit Reflected successfully and Funded To Your Dashboard Kind Regards $uname", 200, 1);

                    data();

                    $bal = $_SESSION['query']['bal'];

                    $deposit = $bal['deposit'];
                    $balance = $bal['balance'];

                    $tratoken = checktoken("tra", generatetoken(6, true), true);

                    $instra =   insertstrans($tratoken, $uid, $uname, $uphone, "Account Deposit", "7", 'KDOE', $wid, $amount, '2', $prebalance, $balance, $predeposit, $deposit, $today, $today, $l1, $uplineid, 2);
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


function manualpayload()
{
    if (adminenv()) {
        // $inputs = jDecode(['event', 'payload', 'webhookId']);


        addTransaction();
    }
}


function updatewhatsappgroup()
{
    if (adminenv()) {
        $inputs = jDecode(['link']);

        $link = $inputs['link'];

        $UpdateLink = updates("sit", "slink = '$link'", "sid = 'AA11'");

        if ($UpdateLink['res']) {
            notify(2, "Whatsapp Group Link Updated", 200, 1);
            sendJsonResponse(200);
        } else {
            notify(0, "Please Try Again Later", 500, 1);
            sendJsonResponse(500);
        }
    }
}



function updatedailyBonus()
{
    if (adminenv()) {
        $inputs = jDecode(['target', 'reward']);


        $reward = $inputs['reward'];
        $target = $inputs['target'];

        $UpdateLink = updates("sit", "reward = '$reward', target = '$target'", "sid = 'AA11'");

        if ($UpdateLink['res']) {
            notify(2, "Daily Bonus Updated", 200, 1);
            sendJsonResponse(200);
        } else {
            notify(0, "Please Try Again Later", 500, 1);
            sendJsonResponse(500);
        }
    }
}




function allPaymentProcedure()
{
    if (adminenv()) {

        $query = "SELECT pm.*, c.cname, c.ccall, c.ccurrency FROM payment_method pm
        LEFT JOIN countrys c ON pm.cid = c.cid
        ORDER BY pm.cid ASC ";

        $allpayament = comboselects($query, 1);

        if ($allpayament['res']) {
            $reposne = [];
            while ($payment = mysqli_fetch_assoc($allpayament['qry'])) {

                $payment_type = $payment['ptype'];

                switch ($payment_type) {
                    case '0':
                        $payment_type = "None";
                        break;
                    case '1':
                        $payment_type = "Stk-Push";
                        break;
                    case '2':
                        $payment_type = "Flutter-Wave";
                        break;
                    case '3':
                        $payment_type = "Manual-Procedure";
                        break;
                    default:
                        $payment_type = "Unknown";
                }

                $response['pid'] = $payment['pid'];
                $response['country_name'] = $payment['cname'];
                $response['payment_type'] = $payment_type;
                $response['method_name'] = $payment['method_name'];
                $response['helpline'] = $payment['extra'];
                $response['ptype'] = $payment['ptype'];
                $response['status'] = $payment['pstatus'] == 1 ? true : false;
                $reposne[] = $response;
            }
            sendJsonResponse(200, true, "Payment Procedures Found", $reposne);
        } else {
            notify(1, "No Payment Procedures Found", 404, 1);
            sendJsonResponse(404);
        }
    }
}


function singlePaymentProcedure()
{
    if (adminenv()) {

        $inputs = jDecode(['pid']);

        $pid = $inputs['pid'];

        $query = "SELECT pp.* FROM payment_procedure pp
        WHERE pp.pmethod_id = '$pid' ORDER BY pp.step_no ASC";

        $allpayament = comboselects($query, 1);

        if ($allpayament['res']) {
            $reposne = [];
            while ($payment = mysqli_fetch_assoc($allpayament['qry'])) {

                $response['pid'] = $payment['pid'];
                $response['step_no'] = $payment['step_no'];
                $response['description'] = $payment['description'];

                $reposne[] = $response;
            }
            sendJsonResponse(200, true, "Procedures Found", $reposne);
        } else {
            notify(1, "No Procedures Found", 404, 1);
            sendJsonResponse(404);
        }
    }
}


function addPaymentMethod()
{
    if (adminenv()) {
        $inputs = jDecode(['cid', 'type', 'title']);

        $cid = $inputs['cid'];
        $type = $inputs['type'];
        $title = $inputs['title'];

        $checkcid = check("cid", "cou", $cid);

        if (!$checkcid['res']) {
            notify(1, "Country Not Found", 404, 1);
            return sendJsonResponse(404);
        }

        $genid = gencheck("pym", 3);

        $insermethod  = inserts("pym", "pid, cid, ptype, method_name", ['ssss', $genid, $cid, $type, $title]);

        if ($insermethod['res']) {
            notify(2, "Payment Method Added Successfully", 200, 1);
            sendJsonResponse(200, true, "Payment Method Added Successfully", ['pid' => $genid]);
        } else {
            notify(0, "Failed to Add Payment Method", 500, 1);
            sendJsonResponse(500);
        }
    }
}

function updatePaymentMethod()
{
    if (adminenv()) {
        $inputs = jDecode(['pid', 'type']);

        $pid = $inputs['pid'];
        $type = $inputs['type'];

        $checkpid = check("pid", "pym", $pid);

        if (!$checkpid['res']) {
            notify(1, "Payment Method Not Found", 404, 1);
            return sendJsonResponse(404);
        }
        $updateMethod = updates("pym", "ptype = '$type'", "pid = '$pid'");

        if ($updateMethod['res']) {
            notify(2, "Payment Method Updated Successfully", 200, 1);
            sendJsonResponse(200, true, "Payment Method Updated Successfully", ['pid' => $pid]);
        } else {
            notify(0, "Failed to Update Payment Method", 500, 1);
            sendJsonResponse(500);
        }
    }
}

function statusPaymentMethod()
{
    if (adminenv()) {
        $inputs = jDecode(['pid']);

        $pid = $inputs['pid'];

        $checkpid = check("pid", "pym", $pid);

        if (!$checkpid['res']) {
            notify(1, "Payment Method Not Found", 404, 1);
            return sendJsonResponse(404);
        }

        $updateMethod = updates("pym", "pstatus = !pstatus", "pid = '$pid'");

        if ($updateMethod['res']) {
            notify(2, "Payment Method Status Updated Successfully", 200, 1);
            sendJsonResponse(200, true, "Payment Method Status Updated Successfully");
        } else {
            notify(0, "Failed to Update Payment Method Status", 500, 1);
            sendJsonResponse(500);
        }
    }
}
function deletePaymentMethod()
{
    if (adminenv()) {

        $inputs = jDecode(['pid']);

        $pid = $inputs['pid'];

        $checkpid = check("pid", "pym", $pid);

        if (!$checkpid['res']) {
            notify(1, "Payment Method Not Found", 404, 1);
            return sendJsonResponse(404);
        }

        // Check if there are procedures associated with this payment method
        $checkProcedures = selects("*", "pyp", "pmethod_id = '$pid'", 1);

        if ($checkProcedures['res']) {
            notify(1, "Cannot delete payment method with existing procedures", 400, 1);
            return sendJsonResponse(403);
        }

        $deleteMethod = deletes("pym", "pid = '$pid'");

        if ($deleteMethod['res']) {
            notify(2, "Payment Method Deleted Successfully", 200, 1);
            sendJsonResponse(200, true, "Payment Method Deleted Successfully");
        } else {
            notify(0, "Failed to Delete Payment Method", 500, 1);
            sendJsonResponse(500);
        }
    }
}


function addPaymentProcedure()
{
    if (adminenv()) {

        $inputs = jDecode(['pmid',  'description']);

        $pmid = $inputs['pmid'];
        $step_no = $inputs['step_no'] ?? 50;
        $description = $inputs['description'];

        $genpmid = gencheck("pyp", 5);

        $insertProcedure = inserts("pyp", "pid, pmethod_id, step_no, description", ['ssis', $genpmid, $pmid, $step_no, $description]);


        if ($insertProcedure['res']) {
            if ((!empty($inputs['step_no']))) {
                // If step_no is provided, update the steps accordingly
                updateStepsPaymentProcedure($pmid, $genpmid, $step_no);
            } else {
                // If step_no is not provided, just update the steps without specific step_no
                updateStepsPaymentProcedure($pmid);
            }
            notify(2, "Payment Procedure Added Successfully", 200, 1);
            sendJsonResponse(200);
        } else {
            notify(0, "Failed to Add Payment Procedure", 500, 1);
            sendJsonResponse(500);
        }
    }
}

function deletePaymentProcedure()
{
    if (adminenv()) {

        $inputs = jDecode(['pid']);

        $pid = $inputs['pid'];

        $checkProcedure = selects("*", "pyp", "pid = '$pid'", 1);

        if (!$checkProcedure['res']) {
            notify(1, "Payment Procedure Not Found", 404, 1);
            return sendJsonResponse(404);
        }

        $deleteProcedure = deletes("pyp", "pid = '$pid'");

        if ($deleteProcedure['res']) {
            $pmid = mysqli_fetch_assoc($checkProcedure['qry'])['pmethod_id'];
            // Update the steps after deletion
            updateStepsPaymentProcedure($pmid);
            notify(2, "Payment Procedure Deleted Successfully", 200, 1);
            sendJsonResponse(200);
        } else {
            notify(0, "Failed to Delete Payment Procedure", 500, 1);
            sendJsonResponse(500);
        }
    }
}

function updatePaymentProcedure()
{
    if (adminenv()) {

        $inputs = jDecode(['pid', 'step_no', 'description']);

        $pid = $inputs['pid'];
        $step_no = $inputs['step_no'];
        $description = $inputs['description'];

        $checkProcedure = selects("*", "pyp", "pid = '$pid'", 1);

        if (!$checkProcedure['res']) {
            notify(1, "Payment Procedure Not Found", 404, 1);
            return sendJsonResponse(404);
        }

        $updateProcedure = updates("pyp", "step_no = '$step_no', description = '$description'", "pid = '$pid'");

        if ($updateProcedure['res']) {
            $pmid = mysqli_fetch_assoc($checkProcedure['qry'])['pmethod_id'];
            updateStepsPaymentProcedure($pmid, $pid, $step_no);

            notify(2, "Payment Procedure Updated Successfully", 200, 1);
            sendJsonResponse(200);
        } else {
            notify(0, "Failed to Update Payment Procedure", 500, 1);
            sendJsonResponse(500);
        }
    }
}


function updateStepsPaymentProcedure($pmid, $requestedpid = null, $requestedstep_no = null)
// function updateStepsPaymentProcedure()
{
    // $inputs = jDecode(['pmid', 'requestedpid', 'requestedstep_no']);

    // $pmid = $inputs['pmid'];
    // $requestedpid = $inputs['requestedpid'] ?? null;
    // $requestedstep_no = (int)($inputs['requestedstep_no'] ?? 0);

    // Fetch all steps
    $query = "SELECT pp.* FROM payment_procedure pp
        WHERE pp.pmethod_id = '$pmid' ORDER BY pp.step_no ASC";

    $allpayament = comboselects($query, 1);

    if (!$allpayament['res']) {
        notify(0, "No payment procedures found", 404, 3);
        return;
    }

    $steps = $allpayament['qry'];

    // Remove the requested step from the list if being repositioned
    $repositioned = null;
    if ($requestedpid && $requestedstep_no > 0) {
        foreach ($steps as $index => $step) {
            if ($step['pid'] === $requestedpid) {
                $repositioned = $step;
                unset($steps[$index]);
                break;
            }
        }

        // Re-index array
        $steps = array_values($steps);

        // Insert it at the requested position
        array_splice($steps, $requestedstep_no - 1, 0, [$repositioned]);
    }

    // Renumber everything
    $step_no = 1;
    foreach ($steps as $step) {
        $pid = $step['pid'];
        $description = $step['description'];

        $update = updates("pyp", "step_no = '$step_no', description = '$description'", "pid = '$pid'");
        if (!$update['res']) {
            notify(0, "Failed to update step $pid", 500, 3);
            sendJsonResponse(500);
        }
        $step_no++;
    }

    // sendJsonResponse(200, true, "Step No Updated", ['steps' => count($steps)]);
}


function countryanaysis()
{

    $query = "
    SELECT 
        c.cid, 
        c.cname, 
        SUM(CASE WHEN u.ustatus = 2 THEN 1 ELSE 0 END) AS totalactive,
        SUM(CASE WHEN u.ustatus = 1 THEN 1 ELSE 0 END) AS totalinactive,
        COUNT(u.uid) AS totalusers
    FROM affiliatefee a
    INNER JOIN users u 
        ON u.default_currency = a.cid
    INNER JOIN countrys c 
        ON u.default_currency = c.cid
    GROUP BY c.cid, c.cname
    ORDER BY totalactive DESC
";



    $runQuery = comboselects($query, 1);

    $response = $runQuery['qry'];

    sendJsonResponse(200, true, "", $response);
}
