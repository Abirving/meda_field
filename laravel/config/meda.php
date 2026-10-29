<?php
return [
 'mock_auth'=>env('MEDA_MOCK_AUTH',false),'mock_payments'=>env('MEDA_MOCK_PAYMENTS',false),
 'timezone'=>'Africa/Addis_Ababa','play_minutes'=>60,'buffer_minutes'=>15,'hold_seconds'=>300,
 'advance_days'=>30,'minimum_notice_minutes'=>5,'max_active_holds'=>2,
 'staff_mfa'=>env('MEDA_STAFF_MFA',true),
 'terms_version'=>'2026-10-28','policies_approved'=>env('MEDA_POLICIES_APPROVED',false),
 'support_email'=>env('MEDA_SUPPORT_EMAIL'),'support_phone'=>env('MEDA_SUPPORT_PHONE'),'operator_name'=>env('MEDA_OPERATOR_NAME'),
 'qr_active_key'=>env('MEDA_QR_ACTIVE_KEY','v1'),
 'qr_keys'=>['v1'=>env('MEDA_QR_KEY_V1'), 'v2'=>env('MEDA_QR_KEY_V2')],
];
