-- 024: the real branches, replacing the demo ones.
--
-- Taken from the 2027 class flyer:
--   Galle   -- Sarasavi, Manawila        Grades 6-11
--   Colombo -- Riyal, Pitipana Homagama  Grades 9-11
--   Colombo -- Sepala, Pannipitiya       Grades 6-11
-- One number answers for all three: 077 354 2713.
--
-- Rows are renamed in place rather than replaced, so the classes,
-- students, enrolments and branch assignments already pointing at them
-- stay attached. Matching is by the old name, not by id, so this gives
-- the same result on any copy of the database.
-- Safe to re-run: every statement matches on the old value only.

UPDATE institutes
   SET name = 'Sepala - Pannipitiya',
       location = 'Pannipitiya',
       phone1 = '077 354 2713',
       phone2 = NULL,
       status = 'active', is_public = 1
 WHERE name = 'Pannipitiya Science Centre';

UPDATE institutes
   SET name = 'Sarasavi - Manawila, Galle',
       location = 'Manawila, Galle',
       phone1 = '077 354 2713',
       phone2 = NULL,
       status = 'active', is_public = 1
 WHERE name = 'Galle Science Centre';

UPDATE institutes
   SET name = 'Riyal - Pitipana, Homagama',
       location = 'Pitipana, Homagama',
       phone1 = '077 354 2713',
       phone2 = NULL,
       status = 'active', is_public = 1
 WHERE name = 'Kandy Science Centre';

-- The two first-migration placeholders carry the real names now held by
-- the rows above. Their classes are old A/L batches, already inactive,
-- so the rows stay (classes point at them) but leave public view.
UPDATE institutes
   SET status = 'inactive', is_public = 0
 WHERE name IN ('Sepala Institute', 'Sarasavi Institute');

-- The Kandy classes are the Homagama ones.
UPDATE classes
   SET class_name = REPLACE(class_name, ' - Kandy', ' - Homagama')
 WHERE class_name LIKE '% - Kandy';

-- The teacher's full name as it appears on her own material.
UPDATE users
   SET full_name = 'Harshani Madhuwanthi Wanasinghe'
 WHERE user_id_display = 'TCH20260100';

-- Galle runs 6-11 on the flyer but only had 9, 10 and 11. The three
-- lower grades are added to match, priced like the Grade 9 class and
-- taught by the same teacher. Day and time are deliberately left empty
-- for the office to set -- an invented time would be worse than none.
INSERT INTO classes (class_name, subject, medium, grade, institute_id, room,
                     delivery_mode, teacher_id, monthly_fee, status, notes)
SELECT CONCAT('Grade ', g.n, ' Science - Galle'), 'Science', 'Sinhala',
       CONCAT('Grade ', g.n), i.id, NULL, 'physical', u.id, 2000.00, 'active',
       'Day and time still to be set by the office.'
  FROM (SELECT 6 AS n UNION ALL SELECT 7 UNION ALL SELECT 8) AS g
  CROSS JOIN institutes i
  CROSS JOIN users u
 WHERE i.name = 'Sarasavi - Manawila, Galle'
   AND u.user_id_display = 'TCH20260100'
   AND NOT EXISTS (
         SELECT 1 FROM classes c
          WHERE c.institute_id = i.id AND c.grade = CONCAT('Grade ', g.n)
       );
