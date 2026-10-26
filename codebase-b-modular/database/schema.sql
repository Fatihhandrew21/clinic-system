CREATE DATABASE IF NOT EXISTS clinic_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinic_db;

CREATE TABLE clinics (
    clinic_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    location       VARCHAR(255) NOT NULL,
    contact_number VARCHAR(30)  NOT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(30)  NOT NULL,
    role          ENUM('Patient','Staff') NOT NULL,
    clinic_id     INT UNSIGNED NULL,
    CONSTRAINT fk_users_clinic FOREIGN KEY (clinic_id) REFERENCES clinics(clinic_id),
    CONSTRAINT chk_staff_has_clinic CHECK (role = 'Patient' OR clinic_id IS NOT NULL)
) ENGINE=InnoDB;

CREATE TABLE doctors (
    doctor_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(120) NOT NULL,
    specialization VARCHAR(120) NOT NULL,
    clinic_id      INT UNSIGNED NOT NULL,
    CONSTRAINT fk_doctors_clinic FOREIGN KEY (clinic_id) REFERENCES clinics(clinic_id),
    INDEX idx_doctor_spec (specialization)
) ENGINE=InnoDB;

CREATE TABLE appointment_slots (
    slot_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id  INT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time   DATETIME NOT NULL,
    CONSTRAINT fk_slots_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id),
    CONSTRAINT chk_slot_range CHECK (end_time > start_time),
    INDEX idx_slot_doctor_start (doctor_id, start_time)
) ENGINE=InnoDB;

-- active_slot_id is non-NULL only while status = 'Booked'; the UNIQUE index
-- therefore guarantees at most one active booking per slot (safety net).
-- ON DELETE RESTRICT on slot_id also blocks deleting slots with any history.
CREATE TABLE appointments (
    appointment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id     INT UNSIGNED NOT NULL,
    slot_id        INT UNSIGNED NOT NULL,
    status         ENUM('Booked','Cancelled','Completed') NOT NULL DEFAULT 'Booked',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_slot_id INT UNSIGNED GENERATED ALWAYS AS (IF(status = 'Booked', slot_id, NULL)) STORED,
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES users(user_id),
    CONSTRAINT fk_appt_slot    FOREIGN KEY (slot_id)    REFERENCES appointment_slots(slot_id),
    UNIQUE KEY uq_active_slot (active_slot_id),
    INDEX idx_appt_patient_status (patient_id, status)
) ENGINE=InnoDB;
