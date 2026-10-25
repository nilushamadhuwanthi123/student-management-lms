-- ---------------------------------------------------------------------
-- 008: which branch each staff account belongs to
-- ---------------------------------------------------------------------
--
-- Until now nothing recorded that "Kasun is the Pannipitiya admin". The
-- users table has no branch column, so a branch admin could open every
-- branch's students, payments and reports. This adds the missing link.
--
-- Additive only: one new table, no existing table or row is changed or
-- removed. Safe to run more than once (IF NOT EXISTS / INSERT IGNORE).
--
-- Who it applies to:
--   super_admin  - not listed here; sees every branch.
--   admin, coordinator, staff - see only the branches listed here.
--   teacher      - scoped by the classes they teach, not by branch.
--
-- Rollback (removes only this feature, nothing else):
--   DROP TABLE IF EXISTS user_institutes;

CREATE TABLE IF NOT EXISTS user_institutes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    institute_id  INT UNSIGNED NOT NULL,
    assigned_by   INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_institute (user_id, institute_id),
    KEY idx_ui_institute (institute_id),
    CONSTRAINT fk_ui_user      FOREIGN KEY (user_id)      REFERENCES users (id)      ON DELETE CASCADE,
    CONSTRAINT fk_ui_institute FOREIGN KEY (institute_id) REFERENCES institutes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Back-fill from what the data already says, so no one loses access
-- they legitimately had through an existing assignment:

-- 1. The coordinator named on each branch.
INSERT IGNORE INTO user_institutes (user_id, institute_id)
SELECT i.coordinator_id, i.id
  FROM institutes i
  JOIN users u ON u.id = i.coordinator_id
 WHERE u.role IN ('admin', 'coordinator', 'staff');

-- 2. Admins, coordinators and staff already attached to a class through
--    user_classes, or named as a class's staff member, belong to that
--    class's branch.
INSERT IGNORE INTO user_institutes (user_id, institute_id)
SELECT DISTINCT uc.user_id, c.institute_id
  FROM user_classes uc
  JOIN users u   ON u.id = uc.user_id
  JOIN classes c ON c.id = uc.class_id
 WHERE u.role IN ('admin', 'coordinator', 'staff');

INSERT IGNORE INTO user_institutes (user_id, institute_id)
SELECT DISTINCT c.staff_id, c.institute_id
  FROM classes c
  JOIN users u ON u.id = c.staff_id
 WHERE u.role IN ('admin', 'coordinator', 'staff');

-- Anyone still without a branch after this (for example a newly created
-- branch admin) sees no branch data until the super admin assigns one on
-- the user's edit page. Deny by default.
