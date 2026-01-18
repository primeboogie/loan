<?php
header("Content-Type: application/json");

$stkCallbackResponse = file_get_contents('php://input');
$logFile = "flutter2.json";
$log = fopen($logFile, "a");
fwrite($log, $stkCallbackResponse);
fclose($log);

$response = json_decode($stkCallbackResponse, true);


require "../config/func.php";
// require_once '/home/seosblog/earnempire.seosblog.com/config/func.php';


if(isset($response['data'])){
    $ref_id = $response['data']['tx_ref'] ?? null;
     $flwRef = $response['data']['flw_ref'] ?? null;
     
    if(!$ref_id  ||  $response['data']['status'] !== 'successful'){
        exit;
    }
     
    $search = selects("*","tra","tid = '$ref_id' and tstatus = 0 ",1);
    if($search['res']){
        $searchData = mysqli_fetch_assoc($search['qry']);
        $amount = $searchData['tamount'];
        $uid = $searchData['tuid'];
        
        updates("bal","deposit = deposit + '$amount'","buid = '$uid'");
        updates("tra", "tstatus = '2', tdeposit = tdeposit + '$amount', ref_payment = '$flwRef'","tid = '$ref_id'");
    }
}



