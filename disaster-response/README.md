# Disaster Response Coordination System

**Setup (XAMPP)**
1. Copy this folder to `C:\xampp\htdocs\disaster-response`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin or MySQL Workbench and run `database.sql`. (It creates the `disaster_response` database.)
4. Check credentials in `config.php` (defaults: root / empty password).
5. Visit http://localhost/disaster-response/

**Default admin:** admin@dr.com / admin123 (change after first login)
Citizens, volunteers and relief teams register from the Register page.

**DBMS concepts:** PK/FK, joins, 3NF, views (`v_request_summary`, `v_shelter_availability`), triggers (status log, donation stock, shelter capacity check), stored procedure (`assign_request`, with transaction), indexes, aggregates.
