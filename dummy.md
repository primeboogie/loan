1. on the get started section we need to make it look like we dont request money for kyc and disbursment fee that quickly ad some entising words and explanation before going to each steps inshort add more communication between 99 and actual dibusrment fee 

2. any notification that comes from the backend always are info  whcih is an array so craete a  notification popups for each request that comes in  
eg 

{
    "status": 200,
    "resultcode": true,
    "msg": "Loan approved! Your funds will be disbursed within 3 hours.",
    "data": {
        "session_id": "895b24e566261a4750d1e6ae6d6c7e78",
        "loan_id": 4,
        "loan_amount": 12000,
        "loan_fee": 1800.000000000000227373675443232059478759765625,
        "duration": 3,
        "monthly_payment": 4600,
        "total_repayment": 13800,
        "status": "pending_disbursement",
        "ref": "CC2FFF417F19E49574A6"
    },
    "memory": {
        "used": "0.07 MB",
        "peak": "1.37 MB"
    },
    "info": [
        {
            "state": 2,
            "color": "#24db14",
            "msg": "Payment of KES 120 received successfully! Your transaction reference is: CC2FFF417F19E49574A6",
            "errno": "0",
            "time": "February 9, 11:02:15 AM",
            "icon": "<i class='fa-solid fa-check'></i>"
        },
        {
            "state": 2,
            "color": "#24db14",
            "msg": "Loan approved! KES 12,000 will be disbursed within 3 hours.",
            "errno": 0,
            "time": "February 9, 11:02:18 AM",
            "icon": "<i class='fa-solid fa-check'></i>"
        }
    ]
}


so l itesend for evry request and do a popup ntification for each in case it appers 

