<?php
declare(strict_types=1);

namespace Clinic\Infrastructure;

use PDO;
use Throwable;

final class TransactionManager
{
    public function __construct(private readonly PDO $pdo) {}

    /** Runs $work atomically; rolls back and rethrows on any failure. */
    public function transactional(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
