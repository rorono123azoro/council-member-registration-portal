<?php

declare(strict_types=1);

return [
    'host'    => (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'),
    'port'    => (string) ($_ENV['DB_PORT'] ?? '3306'),
    'name'    => (string) ($_ENV['DB_NAME'] ?? 'council_portal'),
    'user'    => (string) ($_ENV['DB_USER'] ?? 'root'),
    'pass'    => (string) ($_ENV['DB_PASS'] ?? ''),
    'charset' => 'utf8mb4',
];
