<?php

require_once "modules/networking.php";


function unauthorized($action)
{
    switch ($action) {
        case 'login':
            case 'allusertest':

        case 'memory':
        case 'adminlogin':
        case 'auth':
        case 'freeuser':
        case 'freeemail':
        case 'returndefault':
        case 'compenstaion':
        case 'freephone':
        case 'register':
        case 'newpasswords':
        case 'populateCountrys':
        case 'affilatefee':
        case 'suspendaccount':
        case 'flutterwave':
        case 'giveOutRandId':
        case 'populateAllCountrys':

            fne($action);
            break;
        default:
            authorized($action);
    }
}

function authorized($action)
{
    if (auths()['status']) {
        switch ($action) {
            case 'alluser':
            case 'searchuser':
            case 'tasks':
            case 'addTariff':
            case 'grabTariff':
            case 'updatedailyBonus':
            case 'deleteTariff':
            case 'adminbalances':
            case 'countryanaysis':
            case 'fetchflutter':
            case 'updateflutter':
            case 'updatewhatsappgroup':
            case 'addflutter':
            case 'deactivateuser':
            case 'upgradeupline':
            case 'admindeposit';
            case 'adminwithdrawals':
            case 'grabupline':
            case 'changeupline':
            case 'updateaccurtemoney':
            case 'adminTopEarners';
            case 'userspindata';
            case 'adminupdate':
            case 'updatetrans':
            case 'requestSpin':
            case 'answerdquiz':
            case 'payAds':
            case 'freespin':
            case 'weektrivia':
            case 'soloupdate':
            case 'requestpayment':
            case 'confirmpayforclient':
            case 'payforclient':
            case 'populatepayfroclient':
            case 'grabpayment':
            case 'deposithistory':
            case 'userdata':
            case 'myDownlines':
            case 'allmyDownlines':
            case 'stkpush':
            case 'populateads':
            case 'updatepassword':
            case 'activateaccount':
            case 'systemwithdrawal':
            case 'populatetrivia':
            case 'populateyoutube':
            case 'populatetiktok':
            case 'paytiktok':
            case 'payyoutube':
            case 'welcomebonus':
            case 'dailybonus':
            case 'withdrawalhistory':
            case 'adminstats':
            case 'registrationfee':
            case 'recovercode':
            case 'updateregistrationfee':
            case 'manualpayload':
            case 'allPaymentProcedure':
            case 'singlePaymentProcedure':
            case 'addPaymentMethod':
            case 'addPaymentProcedure':
            case 'updatePaymentProcedure':
            case 'deletePaymentProcedure':
            case 'updatePaymentMethod':
            case 'statusPaymentMethod':
            case 'deletePaymentMethod':
                fne($action);
        }
    }
}
//  F0C0FBC2