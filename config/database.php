<?php
/**
 * Database Configuration & PDO Connection
 */

class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $host    = env('DB_HOST', '127.0.0.1');
            $port    = env('DB_PORT', '3306');
            $dbname  = env('DB_NAME', 'student_lms');
            $user    = env('DB_USER', 'root');
            $pass    = env('DB_PASS', '');
            $charset = env('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Keep MySQL's NOW() on the same clock as PHP's date() (Asia/Colombo, +05:30),
                // even when the hosting server itself runs on UTC.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE {$charset}_unicode_ci, time_zone = '" . date('P') . "'",
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (env('APP_DEBUG', 'false') === 'true') {
                    die('Database connection failed: ' . $e->getMessage());
                }
                die('Database connection failed. Please check your configuration.');
            }
        }
        return self::$instance;
    }

    /**
     * Begin a transaction
     */
    public static function beginTransaction(): void
    {
        self::connect()->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public static function commit(): void
    {
        self::connect()->commit();
    }

    /**
     * Rollback a transaction
     */
    public static function rollback(): void
    {
        if (self::connect()->inTransaction()) {
            self::connect()->rollBack();
        }
    }
}
