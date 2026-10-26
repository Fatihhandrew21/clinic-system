<?php
// Run once after importing schema.sql:  php database/seed.php
declare(strict_types=1);
date_default_timezone_set('Asia/Kuala_Lumpur');

$c   = require __DIR__ . '/../config/config.php';
$pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}", $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET time_zone = '+08:00'");

$pdo->prepare('INSERT INTO clinics (name, location, contact_number) VALUES (?,?,?)')
    ->execute(['Klinik Sihat Cheras', 'Cheras, Kuala Lumpur', '03-9100 0001']);
$clinicId = (int)$pdo->lastInsertId();

$u = $pdo->prepare('INSERT INTO users (name,email,password_hash,phone,role,clinic_id) VALUES (?,?,?,?,?,?)');
$u->execute(['Clinic Staff',  'staff@clinic.my',  password_hash('Staff@123',   PASSWORD_DEFAULT), '0111111111', 'Staff',   $clinicId]);
$u->execute(['Ali Patient',    'ali@example.my',   password_hash('Patient@123', PASSWORD_DEFAULT), '0122222222', 'Patient', null]);
$u->execute(['Vera Patient',  'vera@example.my',  password_hash('Patient@123', PASSWORD_DEFAULT), '0133333333', 'Patient', null]);

$pdo->prepare('INSERT INTO doctors (name, specialization, clinic_id) VALUES (?,?,?)')
    ->execute(['Dr. Aisyah Rahman', 'General Practice', $clinicId]);
$doctorId = (int)$pdo->lastInsertId();

$s = $pdo->prepare('INSERT INTO appointment_slots (doctor_id,start_time,end_time) VALUES (?,?,?)');
foreach (['09:00', '09:30', '10:00', '10:30', '11:00'] as $t) {
    $start = new DateTimeImmutable("tomorrow $t");
    $s->execute([$doctorId, $start->format('Y-m-d H:i:s'), $start->modify('+30 minutes')->format('Y-m-d H:i:s')]);
}
echo "Seeded. Staff: staff@clinic.my / Staff@123 | Patients: ali@example.my, vera@example.my / Patient@123\n";
