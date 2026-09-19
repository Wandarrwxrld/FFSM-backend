<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'service' => 'FFMS API',
    'status' => 'ok',
    'time' => date('c'),
    'endpoints' => [
        'POST /api/auth_signup.php',
        'POST /api/auth_verify.php',
        'POST /api/auth_resend_verification.php',
        'POST /api/auth_login.php',
        'POST /api/auth_logout.php',
        'GET  /api/auth_me.php',
        'POST /api/auth_change_password.php',
        'POST /api/auth_forgot_password.php',
        'POST /api/auth_reset_password.php',
        'GET|POST|PUT|DELETE /api/records.php?entity=...',
        'GET|PUT /api/team.php',
        'GET  /api/audit.php',
    ],
]);
