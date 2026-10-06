-- ============================================================================
-- Student Management LMS - Demo Seed Data
-- Synthetic data with realistic Sri Lankan names
-- All demo passwords: 'Demo@12345'
-- NOTE: These are DEMO credentials for local development only.
-- Change every password before any production or client deployment.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- Real bcrypt hash of 'Demo@12345' (verified with password_verify)
SET @demo_hash = '$2y$10$hOZwUwyTLdDQz9VMi1XaoOV3P3OXxD5SSP4hr4oLf/04E6a0VqguS';

-- ============================================================================
-- Users: 2 Admins, 2 Coordinators, 3 Staff, 4 Teachers
-- (Super Admin id=1 already exists from migration)
-- ============================================================================

INSERT INTO `users` (`user_id_display`, `role`, `full_name`, `email`, `phone1`, `phone2`, `whatsapp`, `date_of_birth`, `address`, `province`, `district`, `password_hash`, `status`, `created_by`) VALUES
-- Admins
('ADM20260001', 'admin', 'Kamal Perera', 'kamal.perera@lms.lk', '+94712345678', '+94112345678', '+94712345678', '1985-03-15', 'No. 45, Galle Road, Dehiwala', 'Western', 'Colombo', @demo_hash, 'active', 1),
('ADM20260002', 'admin', 'Nimali Fernando', 'nimali.fernando@lms.lk', '+94713456789', NULL, '+94713456789', '1988-07-22', 'No. 12, Kandy Road, Kadawatha', 'Western', 'Gampaha', @demo_hash, 'active', 1),

-- Coordinators
('CRD20260001', 'coordinator', 'Saman Jayasinghe', 'saman.jayasinghe@lms.lk', '+94714567890', NULL, '+94714567890', '1990-01-10', 'No. 78, High Level Road, Pannipitiya', 'Western', 'Colombo', @demo_hash, 'active', 1),
('CRD20260002', 'coordinator', 'Dilini Wickramasinghe', 'dilini.wickramasinghe@lms.lk', '+94715678901', NULL, '+94715678901', '1992-11-05', 'No. 23, Matara Road, Galle', 'Southern', 'Galle', @demo_hash, 'active', 1),

-- Staff
('STF20260001', 'staff', 'Ruwan Silva', 'ruwan.silva@lms.lk', '+94716789012', NULL, '+94716789012', '1993-04-18', 'No. 56, Station Road, Pannipitiya', 'Western', 'Colombo', @demo_hash, 'active', 1),
('STF20260002', 'staff', 'Chamari Herath', 'chamari.herath@lms.lk', '+94717890123', NULL, '+94717890123', '1995-09-30', 'No. 89, Wakwella Road, Galle', 'Southern', 'Galle', @demo_hash, 'active', 1),
('STF20260003', 'staff', 'Pradeep Bandara', 'pradeep.bandara@lms.lk', '+94718901234', NULL, '+94718901234', '1991-06-12', 'No. 34, Horana Road, Piliyandala', 'Western', 'Colombo', @demo_hash, 'active', 1),

-- Teachers
('TCH20260001', 'teacher', 'Sunil Rathnayake', 'sunil.rathnayake@lms.lk', '+94719012345', NULL, '+94719012345', '1980-02-28', 'No. 67, Temple Road, Nugegoda', 'Western', 'Colombo', @demo_hash, 'active', 1),
('TCH20260002', 'teacher', 'Malini Samaraweera', 'malini.samaraweera@lms.lk', '+94710123456', NULL, '+94710123456', '1982-08-14', 'No. 101, Galle Road, Panadura', 'Western', 'Kalutara', @demo_hash, 'active', 1),
('TCH20260003', 'teacher', 'Anil Gunawardena', 'anil.gunawardena@lms.lk', '+94711234567', NULL, '+94711234567', '1978-12-01', 'No. 5, Church Street, Galle Fort', 'Southern', 'Galle', @demo_hash, 'active', 1),
('TCH20260004', 'teacher', 'Nirosha Dissanayake', 'nirosha.dissanayake@lms.lk', '+94712345679', NULL, '+94712345679', '1984-05-20', 'No. 22, Lake Road, Beira, Colombo 02', 'Western', 'Colombo', @demo_hash, 'active', 1);

-- ============================================================================
-- Institutes
-- Coordinator IDs: CRD20260001 = id 4, CRD20260002 = id 5
-- ============================================================================

INSERT INTO `institutes` (`name`, `coordinator_id`, `location`, `phone1`, `phone2`, `notes`, `status`) VALUES
('Sepala Institute', 4, 'No. 120, High Level Road, Pannipitiya', '+94112853456', '+94112853457', 'Main branch - Western Province hub', 'active'),
('Sarasavi Institute', 5, 'No. 45, Matara Road, Galle', '+94912234567', '+94912234568', 'Southern Province branch', 'active');

-- ============================================================================
-- Classes (6 classes across 2 institutes)
-- Teacher IDs: TCH20260001=9, TCH20260002=10, TCH20260003=11, TCH20260004=12
-- Staff IDs: STF20260001=6, STF20260002=7, STF20260003=8
-- Institute IDs: Sepala=1, Sarasavi=2
-- ============================================================================

INSERT INTO `classes` (`class_name`, `subject`, `medium`, `grade`, `institute_id`, `room`, `teacher_id`, `staff_id`, `schedule_days`, `start_time`, `end_time`, `monthly_fee`, `teacher_revenue_split`, `notes`, `status`) VALUES
('2026 A/L Combined Maths - Batch A', 'Combined Mathematics', 'Sinhala', 'Grade 13', 1, 'Room A1', 9, 6, '["Saturday","Sunday"]', '08:00:00', '11:00:00', 3500.00, 70.00, 'Advanced Level Combined Maths batch', 'active'),
('2026 A/L Physics', 'Physics', 'Sinhala', 'Grade 13', 1, 'Room A2', 10, 6, '["Saturday"]', '13:00:00', '16:00:00', 3000.00, 65.00, 'A/L Physics theory and practical', 'active'),
('2026 A/L Chemistry', 'Chemistry', 'Sinhala', 'Grade 13', 1, 'Room B1', 12, 8, '["Sunday"]', '08:00:00', '11:00:00', 3000.00, 65.00, 'A/L Chemistry theory', 'active'),
('2026 O/L Mathematics', 'Mathematics', 'Sinhala', 'Grade 11', 2, 'Room C1', 9, 7, '["Friday"]', '15:00:00', '17:00:00', 2500.00, 70.00, 'O/L Maths preparation', 'active'),
('2026 O/L Science', 'Science', 'Sinhala', 'Grade 11', 2, 'Room C2', 11, 7, '["Saturday"]', '08:00:00', '10:00:00', 2500.00, 65.00, 'O/L Science combined', 'active'),
('2026 A/L Combined Maths - Batch B', 'Combined Mathematics', 'English', 'Grade 12', 2, 'Room D1', 10, 7, '["Sunday"]', '13:00:00', '16:00:00', 3500.00, 70.00, 'English medium A/L Maths', 'active');

-- ============================================================================
-- Students (10 students)
-- ============================================================================

INSERT INTO `students` (`student_id_display`, `full_name`, `email`, `phone1`, `phone2`, `whatsapp`, `date_of_birth`, `address`, `province`, `district`, `password_hash`, `status`, `created_by`) VALUES
('STU20260001', 'Tharindu Lakshan', 'tharindu.lakshan@gmail.com', '+94771001001', NULL, '+94771001001', '2008-03-12', 'No. 15, Pagoda Road, Nugegoda', 'Western', 'Colombo', @demo_hash, 'active', 2),
('STU20260002', 'Sanduni Madushani', 'sanduni.madushani@gmail.com', '+94771002002', NULL, '+94771002002', '2008-06-25', 'No. 78, Dutugemunu St, Dehiwala', 'Western', 'Colombo', @demo_hash, 'active', 2),
('STU20260003', 'Kasun Chamara', 'kasun.chamara@gmail.com', '+94771003003', NULL, '+94771003003', '2007-11-08', 'No. 23, Park Road, Pannipitiya', 'Western', 'Colombo', @demo_hash, 'active', 2),
('STU20260004', 'Nethmi Jayawardena', 'nethmi.jayawardena@gmail.com', '+94771004004', NULL, '+94771004004', '2008-01-30', 'No. 45, Kottawa Road, Piliyandala', 'Western', 'Colombo', @demo_hash, 'active', 2),
('STU20260005', 'Dinesh Kumara', 'dinesh.kumara@gmail.com', '+94771005005', NULL, '+94771005005', '2008-09-17', 'No. 12, Hill Street, Galle', 'Southern', 'Galle', @demo_hash, 'active', 3),
('STU20260006', 'Sachini Perera', 'sachini.perera@gmail.com', '+94771006006', NULL, '+94771006006', '2009-04-02', 'No. 89, Colombo Road, Galle', 'Southern', 'Galle', @demo_hash, 'active', 3),
('STU20260007', 'Harsha Weerasinghe', 'harsha.weerasinghe@gmail.com', '+94771007007', NULL, '+94771007007', '2007-07-19', 'No. 34, Marine Drive, Galle', 'Southern', 'Galle', @demo_hash, 'active', 3),
('STU20260008', 'Ishara Fernando', 'ishara.fernando@gmail.com', '+94771008008', NULL, '+94771008008', '2008-12-05', 'No. 56, Kandy Road, Kelaniya', 'Western', 'Gampaha', @demo_hash, 'active', 2),
('STU20260009', 'Malsha Gunasekara', 'malsha.gunasekara@gmail.com', '+94771009009', NULL, '+94771009009', '2009-02-14', 'No. 67, Baseline Road, Colombo 09', 'Western', 'Colombo', @demo_hash, 'active', 2),
('STU20260010', 'Ravindu Senaratne', 'ravindu.senaratne@gmail.com', '+94771010010', NULL, '+94771010010', '2008-08-28', 'No. 101, Havelock Road, Colombo 06', 'Western', 'Colombo', @demo_hash, 'active', 3);

-- ============================================================================
-- Parents (5 parents linked to students)
-- ============================================================================

INSERT INTO `parents` (`student_id`, `parent_name`, `phone1`, `phone2`, `whatsapp`, `password_hash`, `status`) VALUES
(1, 'Nimal Lakshan', '+94772001001', '+94112001001', '+94772001001', @demo_hash, 'active'),
(2, 'Sriyani Madushani', '+94772002002', NULL, '+94772002002', @demo_hash, 'active'),
(3, 'Gamini Chamara', '+94772003003', '+94112003003', '+94772003003', @demo_hash, 'active'),
(5, 'Anura Kumara', '+94772005005', NULL, '+94772005005', @demo_hash, 'active'),
(7, 'Padmini Weerasinghe', '+94772007007', NULL, '+94772007007', @demo_hash, 'active');

-- ============================================================================
-- Enrollments (multi-class for some students)
-- Class IDs: 1=CM Batch A, 2=Physics, 3=Chemistry, 4=OL Maths, 5=OL Science, 6=CM Batch B
-- ============================================================================

INSERT INTO `enrollments` (`student_id`, `class_id`, `scholarship_type`, `enrollment_status`, `enrolled_at`) VALUES
-- Tharindu: CM Batch A + Physics + Chemistry (full A/L science stream)
(1, 1, 'full_payment', 'active', '2026-01-15 09:00:00'),
(1, 2, 'full_payment', 'active', '2026-01-15 09:00:00'),
(1, 3, 'full_payment', 'active', '2026-01-15 09:00:00'),
-- Sanduni: CM Batch A + Physics (half scholarship)
(2, 1, 'half_payment', 'active', '2026-01-16 10:00:00'),
(2, 2, 'half_payment', 'active', '2026-01-16 10:00:00'),
-- Kasun: CM Batch A only
(3, 1, 'full_payment', 'active', '2026-02-01 08:30:00'),
-- Nethmi: Physics + Chemistry
(4, 2, 'full_payment', 'active', '2026-01-20 11:00:00'),
(4, 3, 'free_card', 'active', '2026-01-20 11:00:00'),
-- Dinesh: OL Maths + OL Science (Galle)
(5, 4, 'full_payment', 'active', '2026-02-10 14:00:00'),
(5, 5, 'full_payment', 'active', '2026-02-10 14:00:00'),
-- Sachini: OL Science only
(6, 5, 'full_payment', 'active', '2026-03-01 09:00:00'),
-- Harsha: OL Maths + CM Batch B
(7, 4, 'full_payment', 'active', '2026-02-15 10:00:00'),
(7, 6, 'half_payment', 'active', '2026-02-15 10:00:00'),
-- Ishara: CM Batch A + Chemistry
(8, 1, 'full_payment', 'active', '2026-03-05 08:00:00'),
(8, 3, 'full_payment', 'active', '2026-03-05 08:00:00'),
-- Malsha: CM Batch B (English medium)
(9, 6, 'full_payment', 'active', '2026-03-10 09:30:00'),
-- Ravindu: OL Maths + OL Science
(10, 4, 'full_payment', 'active', '2026-02-20 13:00:00'),
(10, 5, 'full_payment', 'active', '2026-02-20 13:00:00');

-- ============================================================================
-- Payments for September 2026 (current month)
-- Enrollment IDs match insertion order above: 1-18
-- ============================================================================

INSERT INTO `payments` (`enrollment_id`, `student_id`, `class_id`, `month_year`, `amount_due`, `amount_paid`, `scholarship_type`, `payment_status`, `paid_at`, `marked_by`, `notes`) VALUES
-- Tharindu (3 classes) - all paid
(1, 1, 1, '2026-09-01', 3500.00, 3500.00, 'full_payment', 'paid', '2026-09-05 10:30:00', 6, NULL),
(2, 1, 2, '2026-09-01', 3000.00, 3000.00, 'full_payment', 'paid', '2026-09-05 10:30:00', 6, NULL),
(3, 1, 3, '2026-09-01', 3000.00, 3000.00, 'full_payment', 'paid', '2026-09-05 10:35:00', 6, NULL),
-- Sanduni (2 classes, half scholarship) - paid
(4, 2, 1, '2026-09-01', 1750.00, 1750.00, 'half_payment', 'paid', '2026-09-08 09:00:00', 6, 'Half scholarship applied'),
(5, 2, 2, '2026-09-01', 1500.00, 1500.00, 'half_payment', 'paid', '2026-09-08 09:05:00', 6, 'Half scholarship applied'),
-- Kasun - pending
(6, 3, 1, '2026-09-01', 3500.00, 0.00, 'full_payment', 'pending', NULL, NULL, NULL),
-- Nethmi - Physics paid, Chemistry waived (free card)
(7, 4, 2, '2026-09-01', 3000.00, 3000.00, 'full_payment', 'paid', '2026-09-10 14:20:00', 8, NULL),
(8, 4, 3, '2026-09-01', 0.00, 0.00, 'free_card', 'waived', NULL, NULL, 'Free card - no fee'),
-- Dinesh (2 OL classes) - 1 paid, 1 overdue
(9, 5, 4, '2026-09-01', 2500.00, 2500.00, 'full_payment', 'paid', '2026-09-12 11:00:00', 7, NULL),
(10, 5, 5, '2026-09-01', 2500.00, 0.00, 'full_payment', 'overdue', NULL, NULL, 'Overdue since Sep 15'),
-- Sachini - pending
(11, 6, 5, '2026-09-01', 2500.00, 0.00, 'full_payment', 'pending', NULL, NULL, NULL),
-- Harsha (2 classes) - paid
(12, 7, 4, '2026-09-01', 2500.00, 2500.00, 'full_payment', 'paid', '2026-09-03 08:45:00', 7, NULL),
(13, 7, 6, '2026-09-01', 1750.00, 1750.00, 'half_payment', 'paid', '2026-09-03 08:50:00', 7, 'Half scholarship'),
-- Ishara - both pending
(14, 8, 1, '2026-09-01', 3500.00, 0.00, 'full_payment', 'pending', NULL, NULL, NULL),
(15, 8, 3, '2026-09-01', 3000.00, 0.00, 'full_payment', 'pending', NULL, NULL, NULL),
-- Malsha - paid
(16, 9, 6, '2026-09-01', 3500.00, 3500.00, 'full_payment', 'paid', '2026-09-07 16:00:00', 7, NULL),
-- Ravindu (2 OL classes) - both paid
(17, 10, 4, '2026-09-01', 2500.00, 2500.00, 'full_payment', 'paid', '2026-09-06 10:00:00', 7, NULL),
(18, 10, 5, '2026-09-01', 2500.00, 2500.00, 'full_payment', 'paid', '2026-09-06 10:05:00', 7, NULL);

-- ============================================================================
-- Fee Risk Status (sample entries)
-- ============================================================================

INSERT INTO `fee_risk_status` (`student_id`, `class_id`, `risk_state`, `grace_start_date`, `deactivation_date`, `temp_access_until`, `temp_granted_by`) VALUES
(1, 1, 'safe', NULL, NULL, NULL, NULL),
(1, 2, 'safe', NULL, NULL, NULL, NULL),
(1, 3, 'safe', NULL, NULL, NULL, NULL),
(2, 1, 'safe', NULL, NULL, NULL, NULL),
(2, 2, 'safe', NULL, NULL, NULL, NULL),
(3, 1, 'grace', '2026-09-16', NULL, NULL, NULL),
(5, 5, 'high_risk', '2026-09-01', NULL, NULL, NULL),
(8, 1, 'grace', '2026-09-16', NULL, NULL, NULL),
(8, 3, 'grace', '2026-09-16', NULL, NULL, NULL);

-- ============================================================================
-- Deactivation Settings (Option 1 default)
-- ============================================================================

INSERT INTO `deactivation_settings` (`setting_key`, `setting_value`, `updated_by`) VALUES
('auto_deactivation_option', '1', 1),
('deactivation_target', 'A', 1),
('grace_period_days', '15', 1),
('overdue_months_threshold', '2', 1);

-- ============================================================================
-- Deletion Settings (Option 1 default)
-- ============================================================================

INSERT INTO `deletion_settings` (`teacher_deletion_option`, `updated_by`) VALUES
('option1', 1);

-- ============================================================================
-- Dashboard Widget Defaults
-- ============================================================================

INSERT INTO `dashboard_settings` (`role`, `widget_key`, `is_visible`, `updated_by`) VALUES
-- Super Admin widgets
('super_admin', 'total_students', 1, 1),
('super_admin', 'total_teachers', 1, 1),
('super_admin', 'total_classes', 1, 1),
('super_admin', 'total_institutes', 1, 1),
('super_admin', 'revenue_overview', 1, 1),
('super_admin', 'pending_payments', 1, 1),
('super_admin', 'deactivation_alerts', 1, 1),
('super_admin', 'recent_enrollments', 1, 1),
-- Admin widgets
('admin', 'total_students', 1, 1),
('admin', 'total_classes', 1, 1),
('admin', 'pending_payments', 1, 1),
('admin', 'deactivation_alerts', 1, 1),
('admin', 'recent_enrollments', 1, 1),
('admin', 'workflow_pending', 1, 1),
-- Coordinator widgets
('coordinator', 'institute_students', 1, 1),
('coordinator', 'institute_classes', 1, 1),
('coordinator', 'institute_payments', 1, 1),
('coordinator', 'workflow_pending', 1, 1),
-- Staff widgets
('staff', 'assigned_classes', 1, 1),
('staff', 'class_payments', 1, 1),
('staff', 'pending_payments', 1, 1),
-- Teacher widgets
('teacher', 'my_classes', 1, 1),
('teacher', 'my_students', 1, 1),
('teacher', 'class_attendance', 1, 1),
('teacher', 'revenue_summary', 1, 1),
-- Student widgets
('student', 'my_classes', 1, 1),
('student', 'payment_status', 1, 1),
('student', 'upcoming_schedule', 1, 1),
-- Parent widgets
('parent', 'child_classes', 1, 1),
('parent', 'child_payments', 1, 1),
('parent', 'child_schedule', 1, 1);

-- ============================================================================
-- System Settings
-- ============================================================================

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_by`) VALUES
('system_name', 'Student Management LMS', 1),
('timezone', 'Asia/Colombo', 1),
('currency', 'LKR', 1),
('currency_symbol', 'Rs.', 1),
('date_format', 'Y-m-d', 1),
('academic_year', '2026', 1),
('max_login_attempts', '5', 1),
('session_timeout_minutes', '30', 1);

-- ============================================================================
-- User-Class Assignments
-- ============================================================================

INSERT INTO `user_classes` (`user_id`, `class_id`) VALUES
-- Admin Kamal (id=2) assigned to all Pannipitiya classes
(2, 1), (2, 2), (2, 3),
-- Admin Nimali (id=3) assigned to all Galle classes
(3, 4), (3, 5), (3, 6),
-- Coordinator Saman (id=4) - Pannipitiya
(4, 1), (4, 2), (4, 3),
-- Coordinator Dilini (id=5) - Galle
(5, 4), (5, 5), (5, 6),
-- Staff Ruwan (id=6) - Pannipitiya classes 1,2
(6, 1), (6, 2),
-- Staff Chamari (id=7) - Galle classes 4,5,6
(7, 4), (7, 5), (7, 6),
-- Staff Pradeep (id=8) - class 3
(8, 3),
-- Teachers to their own classes
(9, 1), (9, 4),   -- Sunil: CM Batch A, OL Maths
(10, 2), (10, 6), -- Malini: Physics, CM Batch B
(11, 5),          -- Anil: OL Science
(12, 3);          -- Nirosha: Chemistry

-- ============================================================================
-- Sample Audit Log Entries
-- ============================================================================

INSERT INTO `audit_log` (`user_id`, `user_type`, `user_role`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`) VALUES
(1, 'user', 'super_admin', 'system_initialized', 'system', NULL, '{"message":"System initialized with default settings"}', '127.0.0.1'),
(2, 'user', 'admin', 'student_created', 'student', 1, '{"student_name":"Tharindu Lakshan","student_id":"STU20260001"}', '192.168.1.10'),
(6, 'user', 'staff', 'payment_marked_paid', 'payment', 1, '{"student":"Tharindu Lakshan","class":"CM Batch A","amount":3500.00,"month":"2026-09"}', '192.168.1.15');

-- ============================================================================
-- Sample Notifications
-- ============================================================================

INSERT INTO `notifications` (`recipient_id`, `recipient_type`, `title`, `body`, `notification_type`, `is_read`, `related_entity_type`, `related_entity_id`) VALUES
(3, 'student', 'Payment Reminder', 'Your payment for Combined Mathematics - Batch A (September 2026) is pending. Please make the payment at your earliest convenience.', 'payment', 0, 'payment', 6),
(5, 'student', 'Payment Overdue', 'Your payment for O/L Science (September 2026) is overdue. Please contact the institute office.', 'payment', 0, 'payment', 10),
(2, 'user', 'New Enrollment', 'Student Tharindu Lakshan has been enrolled in Combined Mathematics - Batch A.', 'info', 1, 'enrollment', 1),
(1, 'user', 'System Alert', 'Monthly payment processing for September 2026 has been completed. 3 payments are still pending.', 'system', 0, NULL, NULL);
