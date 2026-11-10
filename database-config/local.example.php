<?php
// Copy to local.php (ignored). Use your deployment environment in production.
return [
    'DB_HOST' => '',
    'DB_PORT' => '3306',
    'DB_NAME' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'DB_SSL_CA' => '', // Optional private CA file path required by some remote databases
    'APP_KEY' => '', // At least 32 random bytes as hex; use different values in SIT and production
    'MAIL_ENABLED' => '0', // Enable only with a verified sender and controlled test mailbox
    'EVENTNEST_SUPPORT_EMAIL' => '',
    'EVENTNEST_BASE_URL' => 'https://your-public-eventnest-host.example',
    'EVENTNEST_SMTP_HOST' => 'smtp-relay.brevo.com',
    'EVENTNEST_SMTP_PORT' => '587',
    'EVENTNEST_SMTP_USER' => '',
    'EVENTNEST_SMTP_PASS' => '',
    'EVENTNEST_FROM_EMAIL' => '', // Brevo-verified sender
    'EVENTNEST_PASSWORD_RESET_SECRET' => '', // At least 32 random bytes, encoded as hex
    'PAYFAST_MODE' => 'sandbox', // sandbox or live; use matching credentials
    'PAYFAST_MERCHANT_ID' => '',
    'PAYFAST_MERCHANT_KEY' => '',
    'PAYFAST_PASSPHRASE' => '',
];
