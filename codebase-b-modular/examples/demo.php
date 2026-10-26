<?php
// php examples/demo.php   (after schema.sql + seed.php)
require __DIR__ . '/../src/bootstrap.php';

use Clinic\Exception\ClinicException;

$svc   = buildServices();
$slots = $svc['slot']->listFutureSlots(1);

foreach ([[2, 0], [3, 0], [2, 1]] as [$patient, $i]) {   // second booking of slot 0 must fail
    try {
        $id = $svc['appointment']->book($patient, (int)$slots[$i]['slot_id']);
        echo "Patient $patient booked slot index $i => appointment #$id\n";
    } catch (ClinicException $e) {
        echo "Patient $patient, slot index $i rejected: {$e->getMessage()}\n";
    }
}
