<?php
return [
 'enabled'=>env('FAYDA_ENABLED',false),'issuer'=>env('FAYDA_ISSUER'),
 'authorization_url'=>env('FAYDA_AUTHORIZATION_URL'),'token_url'=>env('FAYDA_TOKEN_URL'),'jwks_url'=>env('FAYDA_JWKS_URL'),
 'client_id'=>env('FAYDA_CLIENT_ID'),'client_secret'=>env('FAYDA_CLIENT_SECRET'),
 'client_auth'=>env('FAYDA_CLIENT_AUTH','private_key_jwt'),'private_key_path'=>env('FAYDA_PRIVATE_KEY_PATH'),'key_id'=>env('FAYDA_KEY_ID'),
 'subject_pepper'=>env('FAYDA_SUBJECT_PEPPER'),'scopes'=>'openid profile email',
];
