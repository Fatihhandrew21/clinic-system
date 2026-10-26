<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kuala_Lumpur');

class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $c = require __DIR__ . '/../config/config.php';
            self::$pdo = new PDO(
                "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}",
                $c['user'],
                $c['pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
                ]
            );
        }
        return self::$pdo;
    }
}
