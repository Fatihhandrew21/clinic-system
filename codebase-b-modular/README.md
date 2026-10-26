# Codebase B - Modular / Layered (better-quality reference)
Setup: `mysql < database/schema.sql && php database/seed.php && php examples/demo.php`

Layers: `Repository` (SQL only) -> `Validation` (pure rules) -> `Service` (workflow + transactions) -> caller.
Failures are typed exceptions (`ClinicException` hierarchy) instead of result arrays.
