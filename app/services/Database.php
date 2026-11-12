<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $cfg = require BASE_PATH . '/app/config/db.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['name'],
                $cfg['charset']
            );

            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('DB connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed.');
            }
        }

        return self::$instance;
    }
}

