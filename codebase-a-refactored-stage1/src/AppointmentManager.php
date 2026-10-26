<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * CODEBASE A - MONOLITHIC "GOD CLASS".
 * Accounts, doctors, slots, booking rules, overlap checks, SQL and
 * transactions all live here. Methods return ['success' => bool, 'message' => string, ...].
 *
 * The booking method has been refactored using Extract Method.
 * Other responsibilities remain in this centralised class for the later Extract Class demonstration.
 */
class AppointmentManager
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /* ===================== ACCOUNT MANAGEMENT ===================== */

    public function registerPatient(string $name, string $email, string $password, string $phone): array
    {
        $name = trim($name); $email = strtolower(trim($email)); $phone = trim($phone);
        if ($name === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Name, phone and a valid email are required.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Email is already registered.'];
        }
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role)
             VALUES (:n, :e, :p, :ph, 'Patient')"
        );
        $stmt->execute([':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT), ':ph' => $phone]);
        return ['success' => true, 'message' => 'Registration successful.', 'user_id' => (int)$this->db->lastInsertId()];
    }

    public function login(string $email, string $password): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['role']    = $user['role'];
        unset($user['password_hash']);
        return ['success' => true, 'message' => 'Logged in.', 'user' => $user];
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION = [];
        session_destroy();
    }

    public function updateProfile(int $userId, string $name, string $phone, ?string $newPassword = null): array
    {
        if (trim($name) === '' || trim($phone) === '') {
            return ['success' => false, 'message' => 'Name and phone are required.'];
        }
        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
            }
            $stmt = $this->db->prepare('UPDATE users SET name=:n, phone=:p, password_hash=:h WHERE user_id=:id');
            $stmt->execute([':n' => trim($name), ':p' => trim($phone), ':h' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
        } else {
            $stmt = $this->db->prepare('UPDATE users SET name=:n, phone=:p WHERE user_id=:id');
            $stmt->execute([':n' => trim($name), ':p' => trim($phone), ':id' => $userId]);
        }
        return ['success' => true, 'message' => 'Profile updated.'];
    }

    /* ===================== DOCTOR SEARCH & SLOTS ===================== */

    public function searchDoctors(?string $name = null, ?string $specialization = null, ?string $location = null): array
    {
        $sql = 'SELECT d.doctor_id, d.name, d.specialization, c.name AS clinic_name, c.location
                FROM doctors d JOIN clinics c ON c.clinic_id = d.clinic_id WHERE 1=1';
        $params = [];
        if ($name !== null && trim($name) !== '')                     { $sql .= ' AND d.name LIKE :name';            $params[':name'] = '%' . trim($name) . '%'; }
        if ($specialization !== null && trim($specialization) !== '') { $sql .= ' AND d.specialization LIKE :spec';  $params[':spec'] = '%' . trim($specialization) . '%'; }
        if ($location !== null && trim($location) !== '')             { $sql .= ' AND c.location LIKE :loc';         $params[':loc'] = '%' . trim($location) . '%'; }
        $stmt = $this->db->prepare($sql . ' ORDER BY d.name');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDoctorProfile(int $doctorId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, c.name AS clinic_name, c.location, c.contact_number
             FROM doctors d JOIN clinics c ON c.clinic_id = d.clinic_id WHERE d.doctor_id = :id'
        );
        $stmt->execute([':id' => $doctorId]);
        return $stmt->fetch() ?: null;
    }

    /** Future slots with an is_available flag (no active 'Booked' appointment). */
    public function getFutureSlots(int $doctorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.slot_id, s.start_time, s.end_time,
                    NOT EXISTS (SELECT 1 FROM appointments a WHERE a.slot_id = s.slot_id AND a.status = 'Booked') AS is_available
             FROM appointment_slots s
             WHERE s.doctor_id = :id AND s.start_time > NOW()
             ORDER BY s.start_time"
        );
        $stmt->execute([':id' => $doctorId]);
        return $stmt->fetchAll();
    }

    /* ===================== BOOKING ===================== */

    public function bookAppointment(int $patientId, int $slotId): array
    {
        $result = ['success' => false, 'message' => 'Unknown error.'];

        try {
            $this->db->beginTransaction();
            $result = $this->bookWithinTransaction($patientId, $slotId);

            if ($result['success']) { $this->db->commit(); } else { $this->db->rollBack(); }
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            $result = ($e->getCode() === '23000')
                ? ['success' => false, 'message' => 'This slot is already booked.']
                : ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
        return $result;
    }

    /** Called only inside bookAppointment()'s open transaction. */
    private function bookWithinTransaction(int $patientId, int $slotId): array
    {
        $patient = $this->loadBookingPatient($patientId);
        if (!$patient) {
            return ['success' => false, 'message' => 'Patient not found.'];
        }
        if ($patient['role'] !== 'Patient') {
            return ['success' => false, 'message' => 'Only patients can book appointments.'];
        }

        $slot = $this->loadBookingSlot($slotId);
        if (!$slot) {
            return ['success' => false, 'message' => 'Slot not found.'];
        }
        if ((int)$slot['is_future'] !== 1) {
            return ['success' => false, 'message' => 'This slot has already started or passed.'];
        }
        if ($this->isSlotBooked($slotId)) {
            return ['success' => false, 'message' => 'This slot is already booked.'];
        }
        if ($this->patientHasBookingOverlap($patientId, $slot)) {
            return ['success' => false, 'message' => 'This slot overlaps with one of your existing bookings.'];
        }
        return $this->insertBookedAppointment($patientId, $slotId);
    }

    /** The row lock serialises concurrent requests from the same patient. */
    private function loadBookingPatient(int $patientId): array|false
    {
        $stmt = $this->db->prepare('SELECT user_id, role FROM users WHERE user_id = :id FOR UPDATE');
        $stmt->execute([':id' => $patientId]);
        return $stmt->fetch();
    }

    /** The row lock serialises attempts to book the same slot. */
    private function loadBookingSlot(int $slotId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT slot_id, start_time, end_time, (start_time > NOW()) AS is_future
             FROM appointment_slots WHERE slot_id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $slotId]);
        return $stmt->fetch();
    }

    private function isSlotBooked(int $slotId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM appointments WHERE slot_id = :s AND status = 'Booked'");
        $stmt->execute([':s' => $slotId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function patientHasBookingOverlap(int $patientId, array $slot): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             WHERE a.patient_id = :p AND a.status = 'Booked'
               AND s.start_time < :end AND s.end_time > :start"
        );
        $stmt->execute([':p' => $patientId, ':start' => $slot['start_time'], ':end' => $slot['end_time']]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function insertBookedAppointment(int $patientId, int $slotId): array
    {
        $stmt = $this->db->prepare("INSERT INTO appointments (patient_id, slot_id, status) VALUES (:p, :s, 'Booked')");
        $stmt->execute([':p' => $patientId, ':s' => $slotId]);
        return [
            'success' => true,
            'message' => 'Appointment booked.',
            'appointment_id' => (int)$this->db->lastInsertId(),
        ];
    }

    /* ===================== CANCEL / COMPLETE ===================== */

    public function cancelAppointment(int $patientId, int $appointmentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.status, a.patient_id, (s.start_time > NOW()) AS before_start
             FROM appointments a JOIN appointment_slots s ON s.slot_id = a.slot_id
             WHERE a.appointment_id = :id'
        );
        $stmt->execute([':id' => $appointmentId]);
        $a = $stmt->fetch();
        if (!$a || (int)$a['patient_id'] !== $patientId) {
            return ['success' => false, 'message' => 'Appointment not found.'];
        }
        if ($a['status'] !== 'Booked') {
            return ['success' => false, 'message' => 'Only booked appointments can be cancelled.'];
        }
        if ((int)$a['before_start'] !== 1) {
            return ['success' => false, 'message' => 'Appointments cannot be cancelled after the start time.'];
        }
        $this->db->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = :id AND status = 'Booked'")
                 ->execute([':id' => $appointmentId]);
        return ['success' => true, 'message' => 'Appointment cancelled; slot released.'];
    }

    public function completeAppointment(int $staffId, int $appointmentId): array
    {
        $staff = $this->getStaff($staffId);
        if (!$staff) { return ['success' => false, 'message' => 'Staff access required.']; }

        $stmt = $this->db->prepare(
            'SELECT a.status, d.clinic_id, (s.end_time <= NOW()) AS has_ended
             FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             WHERE a.appointment_id = :id'
        );
        $stmt->execute([':id' => $appointmentId]);
        $a = $stmt->fetch();
        if (!$a || (int)$a['clinic_id'] !== (int)$staff['clinic_id']) {
            return ['success' => false, 'message' => 'Appointment not found in your clinic.'];
        }
        if ($a['status'] !== 'Booked') {
            return ['success' => false, 'message' => 'Only booked appointments can be completed.'];
        }
        if ((int)$a['has_ended'] !== 1) {
            return ['success' => false, 'message' => 'Appointment has not ended yet.'];
        }
        $this->db->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = :id AND status = 'Booked'")
                 ->execute([':id' => $appointmentId]);
        return ['success' => true, 'message' => 'Appointment marked as completed.'];
    }

    /** Clinic staff: cancel a Booked appointment of their own clinic before its start time. */
    public function cancelAppointmentByStaff(int $staffId, int $appointmentId): array
    {
        $staff = $this->getStaff($staffId);
        if (!$staff) { return ['success' => false, 'message' => 'Staff access required.']; }

        $stmt = $this->db->prepare(
            'SELECT a.status, d.clinic_id, (s.start_time > NOW()) AS before_start
             FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             WHERE a.appointment_id = :id'
        );
        $stmt->execute([':id' => $appointmentId]);
        $a = $stmt->fetch();
        if (!$a || (int)$a['clinic_id'] !== (int)$staff['clinic_id']) {
            return ['success' => false, 'message' => 'Appointment not found in your clinic.'];
        }
        if ($a['status'] !== 'Booked') {
            return ['success' => false, 'message' => 'Only booked appointments can be cancelled.'];
        }
        if ((int)$a['before_start'] !== 1) {
            return ['success' => false, 'message' => 'Appointments cannot be cancelled after the start time.'];
        }
        $this->db->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = :id AND status = 'Booked'")
                 ->execute([':id' => $appointmentId]);
        return ['success' => true, 'message' => 'Appointment cancelled; slot released.'];
    }

    /** Clinic staff: all bookings (any status) for the doctors of their assigned clinic. */
    public function getClinicBookings(int $staffId): array
    {
        $staff = $this->getStaff($staffId);
        if (!$staff) { return ['success' => false, 'message' => 'Staff access required.']; }

        $stmt = $this->db->prepare(
            'SELECT a.appointment_id, a.status, a.created_at, s.start_time, s.end_time,
                    d.name AS doctor_name, u.name AS patient_name, u.phone AS patient_phone
             FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             JOIN users u ON u.user_id = a.patient_id
             WHERE d.clinic_id = :c ORDER BY s.start_time DESC'
        );
        $stmt->execute([':c' => $staff['clinic_id']]);
        return ['success' => true, 'message' => 'OK', 'bookings' => $stmt->fetchAll()];
    }

    public function getPatientAppointments(int $patientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.appointment_id, a.status, s.start_time, s.end_time, d.name AS doctor_name, c.name AS clinic_name
             FROM appointments a
             JOIN appointment_slots s ON s.slot_id = a.slot_id
             JOIN doctors d ON d.doctor_id = s.doctor_id
             JOIN clinics c ON c.clinic_id = d.clinic_id
             WHERE a.patient_id = :p ORDER BY s.start_time DESC'
        );
        $stmt->execute([':p' => $patientId]);
        return $stmt->fetchAll();
    }

    /* ===================== STAFF: DOCTORS & SLOTS ===================== */

    /** Add (doctorId = null) or update a doctor in the staff member's own clinic. */
    public function saveDoctor(int $staffId, string $name, string $specialization, ?int $doctorId = null): array
    {
        $staff = $this->getStaff($staffId);
        if (!$staff) { return ['success' => false, 'message' => 'Staff access required.']; }
        if (trim($name) === '' || trim($specialization) === '') {
            return ['success' => false, 'message' => 'Name and specialization are required.'];
        }
        if ($doctorId === null) {
            $this->db->prepare('INSERT INTO doctors (name, specialization, clinic_id) VALUES (:n,:s,:c)')
                     ->execute([':n' => trim($name), ':s' => trim($specialization), ':c' => $staff['clinic_id']]);
            return ['success' => true, 'message' => 'Doctor added.', 'doctor_id' => (int)$this->db->lastInsertId()];
        }
        if (!$this->doctorBelongsToClinic($doctorId, (int)$staff['clinic_id'])) {
            return ['success' => false, 'message' => 'Doctor not found in your clinic.'];
        }
        $this->db->prepare('UPDATE doctors SET name=:n, specialization=:s WHERE doctor_id=:id')
                 ->execute([':n' => trim($name), ':s' => trim($specialization), ':id' => $doctorId]);
        return ['success' => true, 'message' => 'Doctor updated.'];
    }

    public function createSlot(int $staffId, int $doctorId, string $start, string $end): array
    {
        $staff = $this->getStaff($staffId);
        if (!$staff) { return ['success' => false, 'message' => 'Staff access required.']; }
        if (!$this->doctorBelongsToClinic($doctorId, (int)$staff['clinic_id'])) {
            return ['success' => false, 'message' => 'Doctor not found in your clinic.'];
        }
        $s = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start);
        $e = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end);
        if (!$s || !$e) { return ['success' => false, 'message' => 'Use the format YYYY-MM-DD HH:MM:SS.']; }
        if ($e <= $s)   { return ['success' => false, 'message' => 'End time must be after start time.']; }
        if ($s <= new DateTimeImmutable('now')) { return ['success' => false, 'message' => 'Slots must be in the future.']; }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM appointment_slots WHERE doctor_id = :d AND start_time < :e AND end_time > :s'
        );
        $stmt->execute([':d' => $doctorId, ':s' => $start, ':e' => $end]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Slot overlaps an existing slot for this doctor.'];
        }
        $this->db->prepare('INSERT INTO appointment_slots (doctor_id, start_time, end_time) VALUES (:d,:s,:e)')
                 ->execute([':d' => $doctorId, ':s' => $start, ':e' => $end]);
        return ['success' => true, 'message' => 'Slot created.', 'slot_id' => (int)$this->db->lastInsertId()];
    }

    public function deleteSlot(int $staffId, int $slotId): array
    {
        $staff = $this->getStaff($staffId);
        if (!$staff) { return ['success' => false, 'message' => 'Staff access required.']; }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'SELECT d.clinic_id FROM appointment_slots s JOIN doctors d ON d.doctor_id = s.doctor_id
                 WHERE s.slot_id = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $slotId]);
            $row = $stmt->fetch();
            if (!$row || (int)$row['clinic_id'] !== (int)$staff['clinic_id']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Slot not found in your clinic.'];
            }
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM appointments WHERE slot_id = :id');   // ALL statuses
            $stmt->execute([':id' => $slotId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Slot has appointment history and cannot be deleted.'];
            }
            $this->db->prepare('DELETE FROM appointment_slots WHERE slot_id = :id')->execute([':id' => $slotId]);
            $this->db->commit();
            return ['success' => true, 'message' => 'Slot deleted.'];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            return ['success' => false, 'message' => 'Slot has appointment history and cannot be deleted.'];
        }
    }

    /* ===================== PRIVATE HELPERS ===================== */

    private function getStaff(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT user_id, clinic_id FROM users WHERE user_id = :id AND role = 'Staff'");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    private function doctorBelongsToClinic(int $doctorId, int $clinicId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM doctors WHERE doctor_id = :d AND clinic_id = :c');
        $stmt->execute([':d' => $doctorId, ':c' => $clinicId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
