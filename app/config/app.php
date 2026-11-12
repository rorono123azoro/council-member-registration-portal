<?php

declare(strict_types=1);

return [
    'name'                 => (string) ($_ENV['APP_NAME'] ?? 'Council Member Registration Portal'),
    'council_name'         => (string) ($_ENV['COUNCIL_NAME'] ?? 'National Civic & Professional Council'),
    'env'                  => (string) ($_ENV['APP_ENV'] ?? 'production'),
    'url'                  => rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost:8000'), '/'),
    'debug'                => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'timezone'             => (string) ($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata'),
    'session_ttl'          => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
    'rate_register'        => (int) ($_ENV['RATE_LIMIT_REGISTER'] ?? 5),
    'rate_check_unique'    => (int) ($_ENV['RATE_LIMIT_CHECK_UNIQUE'] ?? 30),
    'rate_login'           => (int) ($_ENV['RATE_LIMIT_LOGIN'] ?? 5),
    'lockout_minutes'      => (int) ($_ENV['LOCKOUT_MINUTES'] ?? 15),
];
