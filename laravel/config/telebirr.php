<?php
return [
 'enabled'=>env('TELEBIRR_ENABLED',false),'contract_approved'=>env('TELEBIRR_CONTRACT_APPROVED',false),
 // Obtain these exact URLs and field names from the merchant's active contract.
 'token_url'=>env('TELEBIRR_TOKEN_URL'),'preorder_url'=>env('TELEBIRR_PREORDER_URL'),'query_url'=>env('TELEBIRR_QUERY_URL'),'checkout_url'=>env('TELEBIRR_CHECKOUT_URL'),
 'fabric_app_id'=>env('TELEBIRR_FABRIC_APP_ID'),'app_secret'=>env('TELEBIRR_APP_SECRET'),'merchant_app_id'=>env('TELEBIRR_MERCHANT_APP_ID'),'merchant_code'=>env('TELEBIRR_MERCHANT_CODE'),
 'private_key_path'=>env('TELEBIRR_PRIVATE_KEY_PATH'),'provider_key_path'=>env('TELEBIRR_PROVIDER_KEY_PATH'),
 'signature_algorithm'=>env('TELEBIRR_SIGNATURE_ALGORITHM'), // pss-sha256 or pkcs1-sha256; never guessed.
 'sign_type'=>env('TELEBIRR_SIGN_TYPE','SHA256WithRSA'),
 'reference_field'=>env('TELEBIRR_REFERENCE_FIELD','payment_order_id'),
 'status_field'=>env('TELEBIRR_STATUS_FIELD','trade_status'),
 'success_status'=>env('TELEBIRR_SUCCESS_STATUS','SUCCESS'),'failure_statuses'=>array_filter(explode(',',env('TELEBIRR_FAILURE_STATUSES','FAILED,CLOSED'))),
 'acknowledgement'=>env('TELEBIRR_ACKNOWLEDGEMENT','success'),
 'authorization_prefix'=>env('TELEBIRR_AUTHORIZATION_PREFIX','Bearer '),
 'query_method'=>env('TELEBIRR_QUERY_METHOD','payment.queryorder'),
];
