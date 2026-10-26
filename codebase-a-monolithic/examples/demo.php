<?php
// php examples/demo.php   (after schema.sql + seed.php)
require __DIR__ . '/../src/AppointmentManager.php';

$m     = new AppointmentManager();
$slots = $m->getFutureSlots(1);
print_r($m->bookAppointment(2, (int)$slots[0]['slot_id']));   // Ali books slot 1
print_r($m->bookAppointment(3, (int)$slots[0]['slot_id']));   // Vera -> "already booked"
print_r($m->bookAppointment(2, (int)$slots[1]['slot_id']));   // Ali books the next slot
