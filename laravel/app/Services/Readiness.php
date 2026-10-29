<?php
namespace App\Services;
final class Readiness {
 public function issues():array {
  $issues=[];$need=function($ok,$message)use(&$issues){if(!$ok)$issues[]=$message;};
  $need(app()->environment('production'),'APP_ENV must be production for launch.');
  $need(!config('app.debug'),'APP_DEBUG must be false.');
  $need(str_starts_with((string)config('app.url'),'https://'),'APP_URL must use HTTPS.');
  $need(!config('meda.mock_auth')&&!config('meda.mock_payments'),'Mock authentication and payments must be disabled.');
  $need(config('session.secure')&&config('session.http_only')&&config('session.encrypt'),'Secure encrypted HTTP-only sessions are required.');
  $need(config('meda.staff_mfa'),'Staff MFA must be enabled.');
  $need(strlen((string)config('meda.qr_keys.'.config('meda.qr_active_key')))>=32,'Configure a dedicated QR signing key.');
  $need(config('meda.policies_approved')&&config('meda.operator_name')&&config('meda.support_email'),'Approve policies, operator identity and support contact.');
  $need(config('mail.default')==='smtp'&&config('mail.mailers.smtp.host')&&config('mail.from.address')&&!str_ends_with(config('mail.from.address'),'.test'),'Configure real SMTP and the verified sender address.');
  $need(config('fayda.enabled'),'Approved Fayda sign-in must be enabled.');
  foreach(['issuer','authorization_url','token_url','jwks_url']as$key)$need(str_starts_with((string)config('fayda.'.$key),'https://'),'Configure Fayda '.$key.' using HTTPS.');
  $need(config('fayda.client_id')&&strlen((string)config('fayda.subject_pepper'))>=32,'Configure Fayda client ID and stable subject pepper.');
  if(config('fayda.client_auth')==='private_key_jwt')$need(is_readable((string)config('fayda.private_key_path')),'Fayda signing key is unreadable.');
  else $need(config('fayda.client_auth')==='client_secret_basic'&&config('fayda.client_secret'),'Configure an approved Fayda client authentication method.');
  $need(config('telebirr.enabled')&&config('telebirr.contract_approved'),'Telebirr merchant contract and sandbox certification must be approved.');
  foreach(['token_url','preorder_url','query_url','checkout_url']as$key)$need(str_starts_with((string)config('telebirr.'.$key),'https://'),'Configure Telebirr '.$key.' using HTTPS.');
  foreach(['fabric_app_id','app_secret','merchant_app_id','merchant_code']as$key)$need((bool)config('telebirr.'.$key),'Configure Telebirr '.$key.'.');
  foreach(['private_key_path','provider_key_path']as$key)$need(is_readable((string)config('telebirr.'.$key)),'Telebirr '.$key.' is unreadable.');
  $need(in_array(config('telebirr.signature_algorithm'),['pss-sha256','pkcs1-sha256'],true),'Select the merchant-approved Telebirr signature algorithm.');
  $need(strlen((string)config('app.key'))>=32,'APP_KEY is missing.');
  $need(config('database.default')==='pgsql','PostgreSQL is required.');
  return $issues;
 }
}
