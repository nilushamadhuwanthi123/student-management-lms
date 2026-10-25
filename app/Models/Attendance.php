<?php
/**
 * Attendance Model
 * Document 1 & Document 2 Specifications:
 * - QR Scan attendance marking
 * - Detects deactivated students (plays 5-sec loud alert sound & flags attendance with red flag)
 * - Daily attendance logs and reports
 */

require_once __DIR__ . '/BaseModel.php';

class Attendance extends BaseModel
{
    protected string $table = 'attendance';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id', 'class_id', 'date', 'check_in_time',
        'status', 'flagged_reason', 'scan_method', 'marked_by', 'created_at',
    ];

    /**
     * Process QR code scan for a student
     * Implements Document 2 Page 2, 9, 10 requirements:
     * - If account is deactivated: plays 5s loud alert sound, shows alert modal, marks attendance with RED FLAG!
     * - If active: marks attendance as present, logs check-in time.
     */
    public function recordScan(string $studentIdentifier, ?int $classId = null, ?int $markedBy = null): array
    {
        $identifier = trim($studentIdentifier);
        $today = date('Y-m-d');
        $now = date('H:i:s');

        // Lookup student by display ID (STU...) or numeric primary key
        $sql = "SELECT s.*, 
                       (SELECT c.id FROM enrollments e JOIN classes c ON e.class_id = c.id WHERE e.student_id = s.id LIMIT 1) AS primary_class_id,
                       (SELECT c.class_name FROM enrollments e JOIN classes c ON e.class_id = c.id WHERE e.student_id = s.id LIMIT 1) AS primary_class_name,
                       (SELECT i.name FROM enrollments e JOIN classes c ON e.class_id = c.id JOIN institutes i ON c.institute_id = i.id WHERE e.student_id = s.id LIMIT 1) AS institute_name
                FROM students s 
                WHERE s.student_id_display = :ident OR s.id = :ident2 LIMIT 1";

        $stmt = $this->query($sql, [':ident' => $identifier, ':ident2' => is_numeric($identifier) ? (int) $identifier : 0]);
        $student = $stmt->fetch();

        if (!$student) {
            return [
                'success' => false,
                'status'  => 'not_found',
                'message' => "Student record not found for code: '{$identifier}'",
            ];
        }

        $effectiveClassId = $classId ?: (int) ($student['primary_class_id'] ?? 0);

        // Check if student is DEACTIVATED (Document 2 Page 2 & 9)
        $isDeactivated = ($student['status'] === 'deactivated' || $student['status'] === 'inactive');

        if ($isDeactivated) {
            // CRITICAL REQUIREMENT (Document 2 Page 2 & 9):
            // "When student enter to the class scanning the QR Card, Alert must be appeared on QR scanning screen (with loud alert sound for 5 sec) as:
            // Alert Header: Account Deactivated, Body: 'Your account has been automatically deactivated because the monthly fee was not received within the 21-day grace period. Please make a payment to reactivate your access.'
            // But attendance must be marked with red flag."

            $insertSql = "INSERT INTO {$this->table} 
                            (student_id, class_id, date, check_in_time, status, flagged_reason, scan_method, marked_by, created_at)
                          VALUES 
                            (:sid, :cid, :dt, :tm, 'red_flag', :reason, 'qr_scan', :mb, NOW())";

            $reason = 'Deactivated account (Unpaid monthly fees exceeding grace period threshold)';
            $this->query($insertSql, [
                ':sid'    => $student['id'],
                ':cid'    => $effectiveClassId ?: null,
                ':dt'     => $today,
                ':tm'     => $now,
                ':reason' => $reason,
                ':mb'     => $markedBy,
            ]);

            return [
                'success'        => false,
                'status'         => 'red_flag',
                'is_deactivated' => true,
                'alert_sound'    => true, // triggers 5-second loud alarm in frontend
                'header'         => 'Account Deactivated',
                'message'        => 'Your account has been automatically deactivated because the monthly fee was not received within the grace period. Please make a payment to reactivate your access.',
                'student'        => [
                    'id'                 => $student['id'],
                    'student_id_display' => $student['student_id_display'],
                    'full_name'          => $student['full_name'],
                    'class_name'         => $student['primary_class_name'] ?? 'Class',
                    'profile_photo'      => $student['profile_photo'] ?? null,
                ],
                'check_in_time'  => date('g:i:s A', strtotime($now)),
            ];
        }

        // Student is ACTIVE: Mark as Present
        // Check if already checked in today
        $checkSql = "SELECT * FROM {$this->table} 
                     WHERE student_id = :sid AND date = :dt AND status IN ('present', 'late') LIMIT 1";
        $existing = $this->query($checkSql, [':sid' => $student['id'], ':dt' => $today])->fetch();

        if ($existing) {
            return [
                'success'       => true,
                'status'        => 'already_checked_in',
                'message'       => 'Attendance already marked for today at ' . date('g:i A', strtotime($existing['check_in_time'])),
                'student'       => [
                    'id'                 => $student['id'],
                    'student_id_display' => $student['student_id_display'],
                    'full_name'          => $student['full_name'],
                    'class_name'         => $student['primary_class_name'] ?? 'Class',
                    'profile_photo'      => $student['profile_photo'] ?? null,
                ],
                'check_in_time' => date('g:i:s A', strtotime($existing['check_in_time'])),
            ];
        }

        // Insert Present record
        $insertSql = "INSERT INTO {$this->table} 
                        (student_id, class_id, date, check_in_time, status, scan_method, marked_by, created_at)
                      VALUES 
                        (:sid, :cid, :dt, :tm, 'present', 'qr_scan', :mb, NOW())";

        $this->query($insertSql, [
            ':sid' => $student['id'],
            ':cid' => $effectiveClassId ?: null,
            ':dt'  => $today,
            ':tm'  => $now,
            ':mb'  => $markedBy,
        ]);

        return [
            'success'        => true,
            'status'         => 'present',
            'is_deactivated' => false,
            'message'        => 'Attendance marked successfully: Present',
            'student'        => [
                'id'                 => $student['id'],
                'student_id_display' => $student['student_id_display'],
                'full_name'          => $student['full_name'],
                'class_name'         => $student['primary_class_name'] ?? 'Class',
                'institute_name'     => $student['institute_name'] ?? '',
                'profile_photo'      => $student['profile_photo'] ?? null,
            ],
            'check_in_time'  => date('g:i:s A', strtotime($now)),
        ];
    }

    /**
     * Get attendance records for a specific date with filters
     */
    public function getDailyAttendance(string $date, array $filters = []): array
    {
        $params = [':dt' => $date];
        $where = ['a.date = :dt'];

        if (!empty($filters['class_id'])) {
            $where[] = 'a.class_id = :cid';
            $params[':cid'] = (int) $filters['class_id'];
        }

        // Branch restriction from App\Services\BranchScope (database ids).
        if (array_key_exists('class_ids', $filters) && $filters['class_ids'] !== null) {
            $ids = array_map('intval', (array) $filters['class_ids']);
            $where[] = $ids === [] ? '1 = 0' : 'a.class_id IN (' . implode(',', $ids) . ')';
        }

        if (!empty($filters['status'])) {
            $where[] = 'a.status = :st';
            $params[':st'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(s.full_name LIKE :srch OR s.student_id_display LIKE :srch2)';
            $params[':srch'] = '%' . trim($filters['search']) . '%';
            $params[':srch2'] = '%' . trim($filters['search']) . '%';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT 
                    a.*,
                    s.full_name AS student_name,
                    s.student_id_display,
                    s.profile_photo,
                    c.class_name,
                    c.subject,
                    i.name AS institute_name,
                    u.full_name AS marked_by_name
                FROM {$this->table} a
                JOIN students s ON a.student_id = s.id
                LEFT JOIN classes c ON a.class_id = c.id
                LEFT JOIN institutes i ON c.institute_id = i.id
                LEFT JOIN users u ON a.marked_by = u.id
                WHERE {$whereClause}
                ORDER BY a.check_in_time DESC";

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get daily attendance summary counts
     */
    public function getSummary(string $date, ?array $classIds = null): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total_records,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                    SUM(CASE WHEN status = 'red_flag' THEN 1 ELSE 0 END) AS red_flag_count
                FROM {$this->table} 
                WHERE date = :dt";

        if ($classIds !== null) {
            $sql .= $classIds === [] ? ' AND 1 = 0' : ' AND class_id IN (' . implode(',', array_map('intval', $classIds)) . ')';
        }

        $res = $this->query($sql, [':dt' => $date])->fetch();
        return [
            'total'     => (int) ($res['total_records'] ?? 0),
            'present'   => (int) ($res['present_count'] ?? 0),
            'late'      => (int) ($res['late_count'] ?? 0),
            'absent'    => (int) ($res['absent_count'] ?? 0),
            'red_flag'  => (int) ($res['red_flag_count'] ?? 0),
        ];
    }

    /**
     * Mark attendance manually
     */
    public function markManual(int $studentId, int $classId, string $date, string $status, ?int $markedBy = null): bool
    {
        $now = date('H:i:s');
        $sql = "INSERT INTO {$this->table} 
                    (student_id, class_id, date, check_in_time, status, scan_method, marked_by, created_at)
                VALUES 
                    (:sid, :cid, :dt, :tm, :st, 'manual', :mb, NOW())
                ON DUPLICATE KEY UPDATE 
                    status = :st2, check_in_time = :tm2, marked_by = :mb2";

        $this->query($sql, [
            ':sid' => $studentId,
            ':cid' => $classId,
            ':dt'  => $date,
            ':tm'  => $now,
            ':st'  => $status,
            ':mb'  => $markedBy,
            ':st2' => $status,
            ':tm2' => $now,
            ':mb2' => $markedBy,
        ]);

        return true;
    }

    /**
     * Attendance Report across date range
     */
    public function getAttendanceReport(string $startDate, string $endDate, array $filters = []): array
    {
        $params = [':sd' => $startDate, ':ed' => $endDate];
        $where = ['a.date BETWEEN :sd AND :ed'];

        if (!empty($filters['class_id'])) {
            $where[] = 'a.class_id = :cid';
            $params[':cid'] = (int) $filters['class_id'];
        }

        if (!empty($filters['institute_id'])) {
            $where[] = 'c.institute_id = :iid';
            $params[':iid'] = (int) $filters['institute_id'];
        }

        if (array_key_exists('class_ids', $filters) && $filters['class_ids'] !== null) {
            $ids = array_map('intval', (array) $filters['class_ids']);
            $where[] = $ids === [] ? '1 = 0' : 'a.class_id IN (' . implode(',', $ids) . ')';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT 
                    a.date,
                    c.class_name,
                    c.subject,
                    i.name AS institute_name,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) AS present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) AS late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) AS absent_count,
                    COUNT(CASE WHEN a.status = 'red_flag' THEN 1 END) AS red_flag_count,
                    COUNT(*) AS total_logged
                FROM {$this->table} a
                LEFT JOIN classes c ON a.class_id = c.id
                LEFT JOIN institutes i ON c.institute_id = i.id
                WHERE {$whereClause}
                GROUP BY a.date, a.class_id
                ORDER BY a.date DESC";

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get attendance records for a specific student (most recent first).
     */
    public function getByStudent(int $studentId, int $limit = 50): array
    {
        $sql = "SELECT a.*, c.class_name
                FROM {$this->table} a
                LEFT JOIN classes c ON a.class_id = c.id
                WHERE a.student_id = :sid
                ORDER BY a.date DESC, a.check_in_time DESC
                LIMIT {$limit}";
        return $this->query($sql, [':sid' => $studentId])->fetchAll();
    }

    /**
     * Get attendance summary counts for a specific student (all time).
     */
    public function getStudentSummary(int $studentId): array
    {
        $sql = "SELECT
                    COUNT(*) AS total_records,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                    SUM(CASE WHEN status = 'red_flag' THEN 1 ELSE 0 END) AS red_flag_count
                FROM {$this->table}
                WHERE student_id = :sid";

        $res = $this->query($sql, [':sid' => $studentId])->fetch();
        return [
            'total'    => (int) ($res['total_records'] ?? 0),
            'present'  => (int) ($res['present_count'] ?? 0),
            'late'     => (int) ($res['late_count'] ?? 0),
            'absent'   => (int) ($res['absent_count'] ?? 0),
            'red_flag' => (int) ($res['red_flag_count'] ?? 0),
        ];
    }
}
