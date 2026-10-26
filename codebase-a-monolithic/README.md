# Codebase A - Monolithic (poorer-quality target)
Setup: `mysql < database/schema.sql && php database/seed.php && php examples/demo.php`
Requires PHP 8.2 (pdo_mysql) and MySQL 8.0.16+.

Deliberate smells to analyse:
- `AppointmentManager::bookAppointment()` - Long/God Method, nesting depth ~8, SQL + rules + transaction mixed.
- God Class: accounts, search, slots, booking and staff admin in one file; duplicated staff/clinic checks.
