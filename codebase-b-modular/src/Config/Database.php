<?php
declare(strict_types=1);

namespace Clinic\Config;

use PDO;

final class Database
{
    public static function connect(?array $config = null): PDO
    {
        date_default_timezone_set('Asia/Kuala_Lumpur');
        $c = $config ?? require __DIR__ . '/../../config/config.php';

        return new PDO(
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
}
