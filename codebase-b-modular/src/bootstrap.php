<?php
declare(strict_types=1);

// PSR-4 style autoloader: Clinic\Foo\Bar => src/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Clinic\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) { require $file; }
});

use Clinic\Config\Database;
use Clinic\Infrastructure\TransactionManager;
use Clinic\Repository\{AppointmentRepository, DoctorRepository, SlotRepository, UserRepository};
use Clinic\Service\{AccountService, AppointmentService, DoctorService, SlotService, StaffAuthorizer};
use Clinic\Validation\BookingValidator;

/** Composition root: wires the object graph by hand (no DI container needed). */
function buildServices(?PDO $pdo = null): array
{
    $pdo ??= Database::connect();
    $users = new UserRepository($pdo);
    $doctors = new DoctorRepository($pdo);
    $slots = new SlotRepository($pdo);
    $appointments = new AppointmentRepository($pdo);
    $tx = new TransactionManager($pdo);
    $staff = new StaffAuthorizer($users);

    return [
        'account'     => new AccountService($users),
        'doctor'      => new DoctorService($doctors, $staff),
        'slot'        => new SlotService($slots, $appointments, $doctors, $staff, $tx),
        'appointment' => new AppointmentService($appointments, $slots, $users, new BookingValidator(), $staff, $tx),
    ];
}
