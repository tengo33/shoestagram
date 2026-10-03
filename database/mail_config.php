<?php

$smtp_verify_peer = filter_var(
    shoestagram_env('SMTP_VERIFY_PEER', 'true'),
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE
);

return array(
    'host' => trim(shoestagram_env('SMTP_HOST', 'smtp.gmail.com')),
    'port' => (int) shoestagram_env('SMTP_PORT', '587'),
    'encryption' => strtolower(trim(shoestagram_env('SMTP_ENCRYPTION', 'tls'))),
    'username' => trim(shoestagram_env('SMTP_USERNAME', shoestagram_admin_email())),
    'password' => shoestagram_env('SMTP_PASSWORD', ''),
    'from_email' => trim(shoestagram_env('SMTP_FROM_EMAIL', shoestagram_admin_email())),
    'from_name' => trim(shoestagram_env('SMTP_FROM_NAME', 'Shoestagram')),
    'verify_peer' => $smtp_verify_peer !== false,
);
