<?php
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'u253423478_motoapex',
    'db_user' => 'u253423478_rootmotoapex',
    'db_password' => 'REPLACE_ON_SERVER',
    // Exact HTTPS origins, without a trailing slash. Remove temporary sites after migration.
    'allowed_origins' => [
        'https://wheat-stinkbug-153908.hostingersite.com',
        'https://darkorange-ant-895420.hostingersite.com',
        'https://motoapexcr.com',
        'https://admin.motoapexcr.com',
    ],
    'token_lifetime' => 28800,
    // Generate on the server: php -r 'echo base64_encode(random_bytes(32));'
    // Back up securely; losing or replacing this key makes existing MFA secrets unreadable.
    'mfa_encryption_key' => '',
    'password_reset_url' => 'https://admin.motoapexcr.com/reset-password',
    // SMTP is configured only here, outside public_html; missing values disable recovery (503).
    'smtp' => ['host'=>'','port'=>587,'encryption'=>'tls','username'=>'','password'=>'','from'=>''],
];
