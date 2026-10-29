<?php

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require __DIR__ . '/functions.php';
require __DIR__ . '/db.php';
