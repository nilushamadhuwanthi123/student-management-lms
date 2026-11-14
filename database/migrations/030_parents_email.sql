-- Adds an email column to parents, so a guardian's email (already
-- captured on the Add Student form and the public enrol form) can be
-- stored and later used to send login details by email.
ALTER TABLE parents ADD COLUMN email VARCHAR(255) NULL AFTER phone1;
