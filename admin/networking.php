<?php

require_once "share.php";


function suspendaccount()
{
    $inputs = jDecode();

    if (!isset($inputs['uid'])) {
        sendJsonResponse(404);
    }



    $uid = $inputs['uid'];
    $suspension = updates("use", "active = !active ", "uid = '$uid'");

    if ($suspension['res']) {
        $msg = "Account has been Updated Successfully";
        notify(2, $msg, 518, 1);
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(500);
    }
}

function jsondeactivate()
{
    $inputs = jDecode();

    if (!isset($inputs['uid'])) {
        sendJsonResponse(404);
    }

    $uid = $inputs['uid'];

    if (deactivateuser($uid)) {
        notify(2, "Account Deactivated Successfully ", 200, 1);
        return sendJsonResponse(200);
    } else {
        notify(2, "Account Already  Inactive  ", 200, 1);
        return   sendJsonResponse(400);
    }
}

function deactivateuser($uid)
{

    $_SESSION['suid'] = $uid;

    data();

    $data = $_SESSION['query']['data'];
    $fee = $_SESSION['query']['fee'];

    $l1 = $data['uplineid'];
    $l2 = $data['l2id'];
    $l3 = $data['l3id'];

    $ustatus = $data['status'];

    $l1fee = $fee['fl1'];
    $l2fee = $fee['fl2'];
    $l3fee = $fee['fl3'];

    if ($ustatus == 2) {
        updates("bal", "profit = profit - '$l1fee', balance = balance - '$l1fee'", "buid = '$l1'");
        updates("bal", "profit = profit - '$l2fee', balance = balance - '$l2fee'", "buid = '$l2'");
        updates("bal", "profit = profit - '$l3fee', balance = balance - '$l3fee'", "buid = '$l3'");

        updates("use", "ustatus = 1", "uid = '$uid'");

        return true;
    } else {

        return false;
    }
}
// Doctail
function adminwithdrawals()
{

    $tstatus = jDecode(['tstatus'])['tstatus'] ?? 2;

    $response = [];
    $response['query']  = [];

    $query = "SELECT t.*, u.*, c.* FROM transactions t
    LEFT JOIN users u
    ON t.tuid = u.uid
    LEFT JOIN countrys c
    ON u.ucountryid = c.cid
    WHERE tstatus = '$tstatus' AND tcat = '3' ORDER BY tdate DESC";

    $dataquery = comboselects($query, 1);

    if ($dataquery['res']) {
        $i = 1;
        while ($data = mysqli_fetch_assoc($dataquery['qry'])) {

            $userdata = [
                'No' => $i++,
                'Name' => $data['uname'],
                'Amount' => "KES " . $data['tamount'],
                'Foreign' => $data['ccurrency'] . " " . conv($data['crate'], $data['tamount'], true),
                'Phone' => $data['uphone'],
                'Country' => $data['cname'],
                'Rate' => $data['crate'],
                'Pre-Balance' => $data['tprebalance'],
                'Current-Balance' => $data['tbalance'],
                'Date' => $data['tdate'],
                'tid' => $data['tid'],
            ];

            $response['query'][] = $userdata;
        }

        $response['res'] = true;
        return sendJsonResponse(200, true, null, $response);
    } else {
        // notify(1, $response['query'], 500, 3);
    }


    return sendJsonResponse(404);
}




function bonus()
{
    $inputs = jDecode();

    $cid = $inputs['CID'];
    $creg = $inputs['reg'];
    $fl1 = $inputs['L1'];
    $fl2 = $inputs['L2'];
    $fl3 = $inputs['L3'];
    $Bonus = $inputs['Bonus'];

    $query = inserts('aff', 'cid,creg,fL1,fL2,fL3,cbonus', ['ssssss', $cid, $creg, $fl1, $fl2, $fl3, $Bonus]);
    if ($query['res']) {
        sendJsonResponse(200, true);
    } else {
        sendJsonResponse(400);
    }
}

function returndefault()
{
    $query = selects("*", "use", "l1 != 'Earnpower' AND l2 != 'Earnpower' AND l3 != 'Earnpower'", 1);

    $myruns1 = 0;
    $myruns2 = 0;

    while ($item = mysqli_fetch_assoc($query['qry'])) {
        $uid = $item['uid'];

        if ($item['l1'] == $item['l2']) {
            if (updates("use", "l2 = 'Nyacorya', l3 = 'Earnpower'", "uid = '$uid'")['res']) {
                $myruns1 += 1;
            }
        } elseif ($item['l2'] == $item['l3']) {
            if (updates("use", "l3 = 'Nyacorya'", "uid = '$uid'")['res']) {
                $myruns2 += 1;
            }
        }
    }

    return sendJsonResponse(200, true, "Updated l2 => $myruns1 l3 => $myruns2 Records.", $query);
}


function accurtemoney($uid, $level)
{

    global $conn;

    $_SESSION['suid'] = $uid;
    data();
    // if(isset($_SESSION['query'])){
    //     sendJsonResponse(403,false,null,[$_SESSION, $uid, $level]);
    // }

    $uname = $_SESSION['query']['data']['uname'];
    $bonus = $_SESSION['query']['bal']['bonus'];

    $response = [];

    $response['totalwithdrawal'] = $_SESSION['query']['bal']['totalwithdrawal'];
    $response['currentbalance'] = $_SESSION['query']['bal']['balance'];
    $response['nowithdrawal'] = $_SESSION['query']['bal']['nowithdrawal'];
    $response['rate'] = $_SESSION['query']['data']['rate'];
    $response['charges'] = $_SESSION['query']['fee']['charges'];

    $response['active'] = 0;
    $response['inactive'] = 0;
    $response['total'] = 0;
    $response['Earned'] = 0;
    $response['bonus'] = $bonus;

    if ($level == 1) {
        $where = "l1 = '$uname'";
        $l = 'fl1';
    } elseif ($level == 2) {
        $where = "l2 = '$uname'";
        $l = 'fl2';
    } elseif ($level == 3) {
        $where = "l3 = '$uname'";
        $l = 'fl3';
    } else {
        sendJsonResponse(422, false, "Missing Routes level");
    }


    $dataq = "SELECT u.*, u.active AS useractive,b.*, c.*, e.*, e.active AS feeactive, u.l1 AS upline FROM users u 
    INNER JOIN balances b 
    ON u.uid = b.buid 
    INNER JOIN countrys c 
    ON u.ucountryid = c.cid 
    LEFT JOIN affiliatefee e
    ON u.default_currency = e.cid 
    WHERE $where ";

    $dataquery = mysqli_query($conn,  $dataq);

    if ($dataquery) {
        $num = mysqli_num_rows($dataquery);
        if ($num > 0) {


            while ($grab = mysqli_fetch_assoc($dataquery)) {
                if (!$grab['active']) {
                    $state = 3;
                } elseif ($grab['ustatus'] == 2) {
                    $state = 2;
                    $response['Earned'] += $grab[$l];
                } elseif ($grab['ustatus'] == 1) {
                    $state = 1;
                } elseif ($grab['ustatus'] == 0) {
                    $state = 0;
                } else {
                    $state = 1;
                }

                if ($state == 2) {
                    $response['active'] += 1;
                } else {
                    $response['inactive'] += 1;
                }
            }
        }
        $response['money'] = $response['Earned'];
        $response['total']  = $num;
    }
    unset($_SESSION);
    return $response;
}



function updateaccurtemoney()
{
    $allusers = selects("*", "use", "ustatus = 2 AND active = true", 1);
    $response = [];

    if ($allusers['res']) {
        while ($data = mysqli_fetch_assoc($allusers['qry'])) {
            $uid = $data['uid'];

            $myl1 = accurtemoney($uid, 1);
            $myl2 = accurtemoney($uid, 2)['money'];
            $myl3 = accurtemoney($uid, 3)['money'];

            $bonus = $myl1['bonus'];

            $balance = $myl1['money'] + $myl2 + $myl3;
            $profit = $balance + $bonus;

            $nowithdraw = $myl1['nowithdrawal'];
            $charges = $myl1['charges'];
            $currentbalance = $myl1['currentbalance'];
            $totalcharges = $charges * $nowithdraw;
            $totalwithdraw = $myl1['totalwithdrawal'] == 0 ? 0 : $myl1['totalwithdrawal'] + $totalcharges;
            $actualbalance = $balance - $totalwithdraw;


            $response[$data['uname']] = [
                'bonus' => $bonus,
                'profit' => $profit,
                'balance' => $balance,
                'totalwithdraw' => $totalwithdraw,
                'nowithdraw' => $nowithdraw,
                'charges' => $charges,
                'totalcharges' => $totalcharges,
                'currentbalance' => $currentbalance,
                'actualbalance' => $actualbalance,
            ];

            updates("bal", "profit = '$profit', balance = '$actualbalance', deposit = 0, way1 = '$profit'", "buid = '$uid'");
        }
    }
    sendJsonResponse(200, true, null, $response);
}


function updatetrans()
{
    $inputs = jDecode();

    if ($inputs) {
        $tid = $inputs['tid'] ?? null;
        $value = $inputs['value'] ?? null;

        if ($tid && $value) {
            if (updates("tra", "tstatus = '$value'", "tid = '$tid'")['res']) {

                notify(2, "Amount Updated Succefully", 200, 1);
                sendJsonResponse(200, true);
            } else {
                notify(2, "Sorry We had an issue Updating The records", 200, 1);
                sendJsonResponse(400);
            }
        }
        notify(2, "Missing Body Parameter", 200, 1);
        sendJsonResponse(400);
    }
}

function jaddtrans()
{
    $inputs = jDecode();

    if (!isset($inputs['uid']) || !isset($inputs['payment_id']) || !isset($inputs['amount']) || !isset($inputs['datetime']) || !isset($inputs['phone_no'])) {
        sendJsonResponse(404);
    }
    if (sessioned()) {

        global $today;

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        $fee = $_SESSION['query']['fee'];

        $uid = $_SESSION['suid'];
        $l1 = $data['l1'];
        $uplineid = $data['uplineid'];
        $accname = $data['uname'];
        $accphone = $data['phone'];

        $uid = $inputs['uid'];
        $payment_id = $inputs['payment_id'];
        $amount = $inputs['amount'];
        $accphone = $inputs['phone_no'];

        $inputDateTime = $inputs['datetime'];

        $dateTime = new DateTime($inputDateTime);

        $datetime = $dateTime->format('Y-m-d H:i:s');

        $selectowner = selects("tuname", "tra", "ref_payment = '$payment_id'", 1);

        if ($selectowner['res']) {
            $owner = mysqli_fetch_assoc($selectowner['qry'])[0];
            notify(2, "Payment ID Already Used By $owner", 200, 1);
            return sendJsonResponse(400);
        }

        $deposit = $bal['deposit'];
        $balance = $bal['balance'];

        $prebalance = $bal['balance'];
        $predeposit = $bal['deposit'];

        $token = gencheck("tra");

        $qryins =   insertstrans($token, $uid, $accname, $accphone, "Account Deposit", "7", 'KDOE', $payment_id, $amount, '2', $prebalance, $balance, $predeposit, $deposit, $datetime, $datetime, $l1, $uplineid, 2);
        if ($qryins['res']) {
            notify(2, "Transaction Added Successfully", 200, 1);
            return sendJsonResponse(200);
        } else {
            notify(2, "Sorry We had an issue Adding The transaction", 200, 1);
            return sendJsonResponse(500);
        }
    }
}

function adminupdate()
{
    $inputs  = jDecode();
    $action = isset($inputs['acc']) ? $inputs['acc'] : null;

    $uid = isset($inputs['uid']) ? $inputs['uid'] : null;

    if (!check("uid", "use", $uid)['res']) {
        $uid = null;
    }

    if ($action == 5 && $uid) {
        return suspendaccount();
    }

    $_SESSION['admin'] = true;
    if ($uid) {
        $_SESSION['suid'] = $uid;
        data();
    }


    if ($action == 1 && $uid) {
        //  Updating User Details
        $username = isset($inputs['username']) ? $inputs['username'] : null;
        $email = isset($inputs['email']) ? $inputs['email'] : null;
        $phone = isset($inputs['phone']) ? $inputs['phone'] : null;
        $cid = isset($inputs['countryid']) ? $inputs['countryid'] : 'USDT';

        $default_currency = 'KEST';
        $phone = substr($phone, -9);

        $fee = selects("*", "aff", "cid = '$cid'", 1);

        if ($fee['res']) {
            $default_currency = mysqli_fetch_assoc($fee['qry'])['cid'];
            $selectdial = selects("*", "cou", "cid = '$cid'", 1);
            if ($selectdial['res']) {
                $country = mysqli_fetch_assoc($selectdial['qry'])['ccall'];
                $phone = $country . substr($phone, -9);
            }
        }

        if ($email && $username && $phone && $cid) {
            if (updates("use", "uname = '$username', uemail = '$email', uphone = '$phone', ucountryid = '$cid', default_currency = '$default_currency'", "uid = '$uid'")) {
                notify(2, "Account Updated Successfully", 200, 1);
                if (isset($inputs['password']) && strlen($inputs['password']) >= 4) {
                    $password = $inputs['password'];
                    newpasswords($password);
                    return sendJsonResponse(200, true);
                }
                return sendJsonResponse(200, true);
            } else {
                notify(2, "Sorry We had an issue Updating The records", 200, 1);
                sendJsonResponse(400);
            }
        } else {
            notify(2, "Missing Body Parameter", 200, 1);
            sendJsonResponse(400);
        }
    } elseif ($action == 2 && $uid) {
        //  Updating Balances
        $profit = isset($inputs['profit']) ? $inputs['profit'] : 0;
        $balance = isset($inputs['balance']) ? $inputs['balance'] : 0;
        $deposit = isset($inputs['deposit']) ? $inputs['deposit'] : 0;
        $ads = isset($inputs['ads']) ? $inputs['ads'] : 0;
        $tiktok = isset($inputs['tiktok']) ? $inputs['tiktok'] : 0;
        $youtube = isset($inputs['youtube']) ? $inputs['youtube'] : 0;
        $trivia = isset($inputs['trivia']) ? $inputs['trivia'] : 0;
        $spin = isset($inputs['spin']) ? $inputs['spin'] : 0;


        if (updates("bal", "profit = '$profit', balance = '$balance', deposit = '$deposit', ads = '$ads', youtube = '$youtube', tiktok = '$tiktok', spin = '$spin', trivia = '$trivia'", "buid = '$uid'")) {
            notify(2, "Account Updated Successfully", 200, 1);
            sendJsonResponse(200, true);
        } else {
            notify(2, "Sorry We had an issue Updating The records", 200, 1);
            sendJsonResponse(400);
        }
    } elseif ($action == 3 && $uid) {
        // login
        $stoken = generatetoken(82);
        $ssid = gencheck("ses");

        $thirtyMinutes = date("Y-m-d H:i:s", strtotime("+30 minutes"));

        $session = inserts("ses", "sid,suid,stoken,sexpiry", ['ssss', $ssid, $uid, $stoken, $thirtyMinutes]);
        if ($session['res']) {
            return sendJsonResponse(200, true, null, ['access_token' => $stoken]);
        } else {
            notify(1, "Auto-Login Couldnt Be Completed Try Again Later", 500, 1);
            sendJsonResponse(500);
        }
    } elseif ($action == 4 && $uid) {
        // delete account

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $uname = $data['uname'];
        $email = $data['email'];
        $phone = $data['phone'];
        $country = $data['country'];
        $balance = $data['abrv'] . " " . $bal['balance'];
        $deposit = $data['abrv'] . " " . $bal['deposit'];

        deletes("ses", "suid = '$uid'");
        deletes("tra", "tuid = '$uid'");
        deletes("bal", "buid = '$uid'");
        updates("user", "parent_id = NULL, child_id = NULL", "parent_id = '$uid' OR child_id = '$uid'");
        // deletes("user","buid = '$uid'");
        $queryup = deletes("use", "uid = '$uid'");

        if ($queryup['res']) {

            notify(2, "Selected Account Has been Deleted Successfully ", 200, 1);

            notify(2, "Account Deleted Successfully", 200, 1);
            return sendJsonResponse(200, true);
        } else {

            notify(1, "Error Deleting Account", 200, 1);
            return sendJsonResponse(500);
        }
    } elseif ($action == 5 && $uid) {
        // suspend
        return suspendaccount();
    } elseif ($action == 6 && $uid) {
        //  update upline
        return upgradeupline();
    } elseif ($action == 7 && $uid) {
        return activateaccount();
    } elseif ($action == 8 && $uid) {
        return jsondeactivate();
    } elseif ($action == 9 && $uid) {
        return jaddtrans();
    } else {
        return sendJsonResponse(403);
    }
}
