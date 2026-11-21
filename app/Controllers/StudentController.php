<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\GoalService;

class StudentController extends BaseController
{
    private \Student $studentModel;

    public function __construct()
    {
        parent::__construct();
        $this->studentModel = new \Student();
    }

    /**
     * List students with pagination, search, and filters.
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
            AuthMiddleware::ROLE_TEACHER,
        ]);

        $search      = trim($_GET['search'] ?? '');
        $statusFilter = $_GET['status'] ?? '';
        $classFilter  = $_GET['class_id'] ?? '';
        $page         = max(1, (int) ($_GET['page'] ?? 1));
        $perPage      = 15;

        $filters = [];
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($classFilter !== '') {
            $filters['class_id'] = (int) $classFilter;
        }

        // Branch staff and teachers only ever list students in their own
        // branch or classes, so the list always goes through search().
        $scope = BranchScope::current();
        if (!$scope->isGlobal()) {
            $filters['scope_sql'] = $scope->studentSql('s.id');
        }

        // If there's a search term, use the search method
        if ($search !== '' || !empty($filters)) {
            $students = $this->studentModel->search($search, $filters);
            $total = count($students);
            $pages = (int) ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;
            $students = array_slice($students, $offset, $perPage);

            $pagination = [
                'data'         => $students,
                'total'        => $total,
                'pages'        => $pages,
                'current_page' => $page,
            ];
        } else {
            $pagination = $this->studentModel->paginate($page, $perPage, [], 'full_name ASC');
        }

        // Which of the students on this page are in no class at all.
        //
        // Registering a student and enrolling them are two steps, and
        // the second one gets missed -- an office interrupted mid-form
        // saves the student and moves on. Nothing complains: the record
        // is perfectly valid. The child just logs in to an empty portal
        // with no materials, no quizzes and no marks, and nobody finds
        // out until the parent rings.
        //
        // One query for the page, not one per row.
        $unenrolled = [];
        $pageIds = array_values(array_filter(array_map(
            static fn ($r): int => (int) ($r['id'] ?? 0),
            $pagination['data']
        )));
        if ($pageIds !== []) {
            try {
                $in = implode(',', array_map('intval', $pageIds));
                $rows = $this->db->query(
                    "SELECT s.id FROM students s
                      WHERE s.id IN ({$in})
                        AND s.status = 'active'
                        AND NOT EXISTS (
                            SELECT 1 FROM enrollments e
                             JOIN classes c ON c.id = e.class_id
                             WHERE e.student_id = s.id
                               AND e.enrollment_status = 'active'
                               AND c.status = 'active')"
                )->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                foreach ($rows as $sid) {
                    $unenrolled[(int) $sid] = true;
                }
            } catch (\Throwable $e) {
                // A flag that cannot be worked out is simply not shown.
                error_log('Unenrolled-student check failed: ' . $e->getMessage());
            }
        }

        $this->view('students/index', [
            'pageTitle'  => 'Students',
            'students'   => $pagination['data'],
            'unenrolled' => $unenrolled,
            'pagination' => $pagination,
            'search'     => $search,
            'filters'    => [
                'status'   => $statusFilter,
                'class_id' => $classFilter,
            ],
        ]);
    }

    /**
     * Show the student registration form.
     */
    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);

        $institutes = [];
        $classes = [];
        if ($this->db) {
            try {
                $instituteModel = new \Institute();
                $institutes = $instituteModel->all('name ASC');
                $classModel = new \ClassModel();
                $classes = $classModel->all('class_name ASC');
                $scope = BranchScope::current();
                $institutes = array_values(array_filter($institutes, static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));
                $classes = array_values(array_filter($classes, static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));
                foreach ($classes as &$cls) {
                    $cls['name'] = $cls['name'] ?? $cls['class_name'] ?? '';
                    $cls['fee'] = $cls['fee'] ?? $cls['monthly_fee'] ?? 0;
                }
            } catch (\Throwable $e) {
                error_log('Failed to load form data: ' . $e->getMessage());
            }
        }

        // Came here from Inquiries > "Approve & Register" -- prefill the
        // parent's details from that enrolment inquiry so nothing has to
        // be retyped. The inquiry must be an enrolment one for a branch
        // this user is allowed to manage (same rule as InquiryController).
        $old = [];
        $fromInquiry = null;
        $inquiryId = (int) ($_GET['from_inquiry'] ?? 0);
        if ($inquiryId > 0 && $this->db) {
            try {
                $stmt = $this->db->prepare("SELECT * FROM contact_messages WHERE id = :id AND kind = 'enrollment' LIMIT 1");
                $stmt->execute([':id' => $inquiryId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row !== false && BranchScope::current()->allowsInstitute((int) ($row['institute_id'] ?? 0))) {
                    $old = [
                        'full_name'      => $row['student_name'] !== null && $row['student_name'] !== '' ? $row['student_name'] : $row['full_name'],
                        'phone1'         => $row['phone'] ?? '',
                        'email'          => (!empty($row['student_name']) && $row['student_name'] !== $row['full_name']) ? '' : ($row['email'] ?? ''),
                        'guardian_name'  => $row['full_name'] ?? '',
                        'guardian_phone' => $row['phone'] ?? '',
                        'guardian_email' => $row['email'] ?? '',
                        'institute_id'   => (int) ($row['institute_id'] ?? 0),
                    ];
                    $fromInquiry = [
                        'id'      => $inquiryId,
                        'grade'   => $row['grade'] ?? '',
                        'message' => $row['message'] ?? '',
                    ];
                }
            } catch (\Throwable $e) {
                error_log('Failed to load inquiry for prefill: ' . $e->getMessage());
            }
        }

        $this->view('students/create', [
            'pageTitle'   => 'Register Student',
            'institutes'  => $institutes,
            'classes'     => $classes,
            'old'         => $old,
            'fromInquiry' => $fromInquiry,
        ]);
    }

    /**
     * Process student registration form submission.
     */
    public function store(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);
        verifyCsrfToken();

        $fullName = trim($_POST['full_name'] ?? '');
        if ($fullName === '') {
            $fullName = trim(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? ''));
        }

        $dob     = trim($_POST['dob'] ?? $_POST['date_of_birth'] ?? '');
        $gender  = trim($_POST['gender'] ?? 'male');
        $phone   = trim($_POST['phone'] ?? $_POST['phone1'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($fullName === '') {
            $this->flash('error', 'Full Name is required.');
            $this->redirect('/students/create');
        }

        // Guardian login (optional): name + a Sri Lankan phone number.
        $guardianName  = trim((string) ($_POST['guardian_name'] ?? ''));
        $guardianPhone = preg_replace('/[\s-]/', '', trim((string) ($_POST['guardian_phone'] ?? ''))) ?? '';
        $guardianEmail = trim((string) ($_POST['guardian_email'] ?? ''));
        if (($guardianName !== '') !== ($guardianPhone !== '')
            || ($guardianPhone !== '' && preg_match('/^(\+94|0)\d{9}$/', $guardianPhone) !== 1)
            || ($guardianEmail !== '' && !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL))) {
            $this->flash('error', t('For a parent login, give both the guardian name and a Sri Lankan phone number (for example 0771234567).'));
            $this->redirect('/students/create');
        }

        // Classes must be in the registering person's branches.
        $classIds = array_values(array_filter(array_map('intval', (array) ($_POST['class_ids'] ?? [])), static fn (int $c): bool => $c > 0));
        foreach ($classIds as $cid) {
            if (!BranchScope::current()->allowsClass($cid)) {
                http_response_code(403);
                $this->flash('error', t('One of the chosen classes is not in your branch.'));
                $this->redirect('/students/create');
            }
        }

        // Generate student ID and create. createWithGeneratedId() retries on a
        // student_id_display collision -- two staff adding students at nearly
        // the same moment (or one of this and an enrolment approval) can
        // otherwise both compute the same "next" id and the second INSERT
        // fails (see Student::createWithGeneratedId()'s docblock).
        $studentPassword  = \App\Services\AccountService::temporaryPassword();
        $insertData = [
            'full_name'          => $fullName,
            'email'              => $email !== '' ? $email : null,
            'phone1'             => $phone !== '' ? $phone : null,
            'date_of_birth'      => $dob !== '' ? $dob : date('Y-m-d'),
            'address'            => $address !== '' ? $address : null,
            'status'             => 'active',
            'password_hash'      => password_hash($studentPassword, PASSWORD_DEFAULT),
            'created_by'         => (int) session_get('user_id'),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        $issued = [];
        try {
            $this->db->beginTransaction();
            $created = $this->studentModel->createWithGeneratedId($insertData);
            $id = $created['id'];
            $studentIdDisplay = $created['student_id_display'];
            $this->db->prepare("UPDATE students SET must_change_password = 1 WHERE id = :id")->execute([':id' => $id]);
            $issued[] = [
                'kind' => 'password', 'name' => $fullName, 'login_id' => $studentIdDisplay,
                'secret' => $studentPassword, 'expires_at' => null,
                'phone' => $phone !== '' ? $phone : $guardianPhone, 'email' => $email !== '' ? $email : null,
            ];

            if ($guardianPhone !== '') {
                $existing = $this->db->prepare("SELECT COUNT(*) FROM parents WHERE phone1 = :p");
                $existing->execute([':p' => $guardianPhone]);
                $parentPassword = \App\Services\AccountService::temporaryPassword();
                $this->db->prepare(
                    "INSERT INTO parents (student_id, parent_name, phone1, email, password_hash, must_change_password, status)
                     VALUES (:s, :n, :p, :em, :h, 1, 'active')"
                )->execute([
                    ':s' => $id, ':n' => $guardianName, ':p' => $guardianPhone,
                    ':em' => $guardianEmail !== '' ? $guardianEmail : null,
                    ':h' => password_hash($parentPassword, PASSWORD_DEFAULT),
                ]);
                if ((int) $existing->fetchColumn() > 0) {
                    // Known limitation: a phone number logs in to the first child linked to it.
                    $this->flash('warning', t('This phone number is already a parent login for another child. The parent portal shows one child per phone number, so use a different number if the parent needs to see this child too.'));
                } else {
                    $issued[] = [
                        'kind' => 'password', 'name' => $guardianName . ' (' . t('parent') . ')', 'login_id' => $guardianPhone,
                        'secret' => $parentPassword, 'expires_at' => null,
                        'phone' => $guardianPhone, 'email' => $guardianEmail !== '' ? $guardianEmail : null,
                    ];
                }
            }

            // Handle class enrollments if provided
            $scholarshipTypes = $_POST['scholarship_type'] ?? [];
            if (!empty($classIds)) {
                $enrollStmt = $this->db->prepare("INSERT INTO enrollments (student_id, class_id, scholarship_type, enrollment_status, enrolled_at, updated_at) VALUES (:sid, :cid, :stype, 'active', NOW(), NOW())");
                foreach ($classIds as $cid) {
                    $cid = (int) $cid;
                    if ($cid > 0 && BranchScope::current()->allowsClass($cid)) {
                        $stype = (string) ($scholarshipTypes[$cid] ?? 'full_payment');
                        if (!in_array($stype, ['full_payment', 'half_payment', 'free_card'], true)) {
                            $stype = 'full_payment';
                        }
                        $enrollStmt->execute([
                            ':sid'   => $id,
                            ':cid'   => $cid,
                            ':stype' => $stype,
                        ]);
                    }
                }
            }

            // Log the action
            $this->logAudit('create_student', 'student', $id, [
                'student_id' => $studentIdDisplay,
                'name'       => $fullName,
            ]);

            $this->db->commit();

            // The temporary passwords are shown once on the next page, then forgotten.
            session_set('issued_credentials', $issued);
            session_set('issued_back', '/students/' . $id);
            $this->flash('success', t('Student registered. Student ID: {id}', ['id' => $studentIdDisplay]));
            $this->redirect('/accounts/issued');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Student creation failed: ' . $e->getMessage());
            $this->flash('error', t('The student could not be registered. Please check the details and try again.'));
            $this->redirect('/students/create');
        }
    }

    /**
     * Show the student 360-degree view.
     */
    public function show(string $id): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as the Students list; any student's record was readable by id
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF, AuthMiddleware::ROLE_TEACHER]);

        $student = $this->studentModel->getWithEnrollments((int) $id);

        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect('/students');
        }
        $this->requireStudentInScope((int) $id);

        // ─── Parent / Guardian info ──────────────────────────────────
        $parents = [];
        try {
            $parentModel = new \Parent_();
            $parents = $parentModel->findByStudent((int) $id);
        } catch (\Throwable $e) {
            error_log('Parent fetch failed: ' . $e->getMessage());
        }

        // ─── Payment history ─────────────────────────────────────────
        $payments = [];
        $paymentSummary = ['total_due' => 0, 'total_paid' => 0, 'pending_count' => 0, 'overdue_count' => 0];
        try {
            $paymentModel = new \Payment();
            $payments = $paymentModel->getByStudent((int) $id);

            foreach ($payments as $pmt) {
                $paymentSummary['total_due'] += (float) ($pmt['amount_due'] ?? 0);
                $paymentSummary['total_paid'] += (float) ($pmt['amount_paid'] ?? 0);
                $pmtStatus = $pmt['payment_status'] ?? $pmt['status'] ?? 'pending';
                if ($pmtStatus === 'pending') {
                    $paymentSummary['pending_count']++;
                }
                if ($pmtStatus === 'overdue') {
                    $paymentSummary['overdue_count']++;
                }
            }
        } catch (\Throwable $e) {
            error_log('Payment fetch failed: ' . $e->getMessage());
        }

        // ─── Attendance stats ────────────────────────────────────────
        $attendanceRecords = [];
        $attendanceSummary = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'red_flag' => 0];
        try {
            $attendanceModel = new \Attendance();
            $attendanceRecords = $attendanceModel->getByStudent((int) $id, 20);
            $attendanceSummary = $attendanceModel->getStudentSummary((int) $id);
        } catch (\Throwable $e) {
            error_log('Attendance fetch failed: ' . $e->getMessage());
        }

        // ─── Fee risk status ─────────────────────────────────────────
        $feeRiskRecords = [];
        try {
            $feeRiskModel = new \FeeRisk();
            $feeRiskRecords = $feeRiskModel->getByStudent((int) $id);
        } catch (\Throwable $e) {
            error_log('Fee risk fetch failed: ' . $e->getMessage());
        }

        // ─── Workflow history ────────────────────────────────────────
        $workflows = [];
        try {
            $wfModel = new \Workflow();
            $workflows = $wfModel->getHistory((int) $id, 'student');
        } catch (\Throwable $e) {
            error_log('Workflow fetch failed: ' . $e->getMessage());
        }

        // ─── Audit trail ─────────────────────────────────────────────
        $auditTrail = [];
        try {
            $auditLog = new \AuditLog();
            $auditTrail = $auditLog->getByEntity('student', (int) $id);
        } catch (\Throwable $e) {
            error_log('Audit trail fetch failed: ' . $e->getMessage());
        }

        // ─── Created by user name ────────────────────────────────────
        $createdByName = '';
        if (!empty($student['created_by'])) {
            try {
                $userModel = new \User();
                $creator = $userModel->find((int) $student['created_by']);
                $createdByName = $creator['full_name'] ?? '';
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // ─── Learning goals (migration 015) ───────────────────────────
        $studentGoals = [];
        $goalOptions = ['classes' => [], 'topics' => []];
        try {
            $goalService = new GoalService();
            $studentGoals = $goalService->forStudent((int) $id);
            $goalOptions = $goalService->formOptions((int) $id);
        } catch (\Throwable $e) {
            error_log('Goals fetch failed: ' . $e->getMessage());
        }

        $this->view('students/show', [
            'pageTitle'          => $student['full_name'] . ' - Student 360',
            'activePage'         => 'students',
            'activeSubPage'      => 'list',
            'student'            => $student,
            'studentGoals'       => $studentGoals,
            'goalOptions'        => $goalOptions,
            'goalStudentId'      => (int) $id,
            'parents'            => $parents,
            'enrollments'        => $student['enrollments'] ?? [],
            'payments'           => $payments,
            'paymentSummary'     => $paymentSummary,
            'attendanceRecords'  => $attendanceRecords,
            'attendanceSummary'  => $attendanceSummary,
            'feeRiskRecords'     => $feeRiskRecords,
            'workflows'          => $workflows,
            'auditTrail'         => $auditTrail,
            'createdByName'      => $createdByName,
            'breadcrumbs'        => [
                ['Students', '/students'],
                [$student['full_name'], ''],
            ],
        ]);
    }

    /**
     * Show the student edit form.
     */
    public function edit(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);

        $student = $this->studentModel->find((int) $id);

        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect('/students');
        }
        $this->requireStudentInScope((int) $id);

        $institutes = [];
        $classes = [];
        $enrollments = [];
        if ($this->db) {
            try {
                $instituteModel = new \Institute();
                $institutes = $instituteModel->all('name ASC');
                $classModel = new \ClassModel();
                $classes = $classModel->all('class_name ASC');
                $scope = BranchScope::current();
                $institutes = array_values(array_filter($institutes, static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));
                $classes = array_values(array_filter($classes, static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));
                foreach ($classes as &$cls) {
                    $cls['name'] = $cls['name'] ?? $cls['class_name'] ?? '';
                    $cls['fee']  = $cls['fee'] ?? $cls['monthly_fee'] ?? 0;
                }
                $stmt = $this->db->prepare("SELECT * FROM enrollments WHERE student_id = :sid");
                $stmt->execute([':sid' => (int) $id]);
                $enrollments = $stmt->fetchAll();
            } catch (\Throwable $e) {
                error_log('Failed to load edit form data: ' . $e->getMessage());
            }
        }

        $this->view('students/edit', [
            'pageTitle'   => 'Edit Student',
            'student'     => $student,
            'institutes'  => $institutes,
            'classes'     => $classes,
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * Process student update.
     */
    public function update(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);
        verifyCsrfToken();

        $student = $this->studentModel->find((int) $id);
        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect('/students');
        }
        $this->requireStudentInScope((int) $id);

        $fullName = trim($_POST['full_name'] ?? '');
        if ($fullName === '') {
            $fullName = trim(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? ''));
        }

        $dob     = trim($_POST['dob'] ?? $_POST['date_of_birth'] ?? '');
        $phone   = trim($_POST['phone'] ?? $_POST['phone1'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($fullName === '') {
            $this->flash('error', 'Full Name is required.');
            $this->redirect(BASE_URL . "/students/{$id}/edit");
        }

        $data = [
            'full_name'     => $fullName,
            'date_of_birth' => $dob !== '' ? $dob : ($student['date_of_birth'] ?? date('Y-m-d')),
            'address'       => $address !== '' ? $address : null,
            'phone1'        => $phone !== '' ? $phone : null,
            'email'         => $email !== '' ? $email : null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        // Optional fields
        $phone2 = trim($_POST['phone2'] ?? '');
        if ($phone2 !== '') {
            $data['phone2'] = $phone2;
        }
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        if ($whatsapp !== '') {
            $data['whatsapp'] = $whatsapp;
        }
        $homeNumber = trim($_POST['home_number'] ?? '');
        if ($homeNumber !== '') {
            $data['home_number'] = $homeNumber;
        }
        $province = trim($_POST['province'] ?? '');
        if ($province !== '') {
            $data['province'] = $province;
        }
        $district = trim($_POST['district'] ?? '');
        if ($district !== '') {
            $data['district'] = $district;
        }
        $notes = trim($_POST['notes'] ?? '');
        if ($notes !== '') {
            $data['notes'] = $notes;
        }

        try {
            $this->studentModel->update((int) $id, $data);

            $this->logAudit('update_student', 'student', (int) $id, [
                'name' => $fullName,
            ]);

            $this->flash('success', 'Student updated successfully.');
            $this->redirect('/students/' . $id);
        } catch (\Throwable $e) {
            error_log('Student update failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to update student: ' . $e->getMessage());
            $this->redirect(BASE_URL . "/students/{$id}/edit");
        }
    }

    /**
     * Initiate student deactivation workflow.
     */
    public function deactivate(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);
        verifyCsrfToken();

        $student = $this->studentModel->find((int) $id);
        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect('/students');
        }
        $this->requireStudentInScope((int) $id);

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $this->flash('error', 'Reason is required for deactivation.');
            $this->redirect('/students/' . $id);
        }

        $currentUser = $this->getCurrentUser();
        $currentRole = $currentUser['role'] ?? 'staff';

        try {
            $steps = $this->buildDeactivationSteps($currentRole, (int) $currentUser['id']);

            if (empty($steps)) {
                // Super admin can deactivate directly
                $this->studentModel->deactivate((int) $id);
                $this->logAudit('deactivate_student', 'student', (int) $id, [
                    'reason' => $reason,
                    'direct' => true,
                ]);
                $this->flash('success', 'Student deactivated successfully.');
            } else {
                $wfModel = new \Workflow();
                $wfModel->createWorkflow(
                    'deactivation',
                    (int) $id,
                    (int) $currentUser['id'],
                    $currentRole,
                    $steps,
                    $reason
                );
                $this->logAudit('initiate_deactivation', 'student', (int) $id, [
                    'reason' => $reason,
                ]);
                $this->flash('success', 'Deactivation request submitted for approval.');
            }
        } catch (\Throwable $e) {
            error_log('Deactivation failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to process deactivation: ' . $e->getMessage());
        }

        $this->redirect('/students/' . $id);
    }

    /**
     * Reactivate a deactivated student.
     */
    public function reactivate(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);
        verifyCsrfToken();

        $student = $this->studentModel->find((int) $id);
        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect('/students');
        }
        $this->requireStudentInScope((int) $id);

        try {
            $this->studentModel->reactivate((int) $id);

            // Also reactivate linked parents
            $parentModel = new \Parent_();
            $parents = $parentModel->findByStudent((int) $id);
            foreach ($parents as $p) {
                $parentModel->reactivate((int) $p['id']);
            }

            $this->logAudit('reactivate_student', 'student', (int) $id, [
                'name' => $student['full_name'],
            ]);

            $this->flash('success', 'Student reactivated successfully.');
        } catch (\Throwable $e) {
            error_log('Reactivation failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to reactivate student.');
        }

        $this->redirect('/students/' . $id);
    }


    /**
     * Initiate student deletion workflow.
     */
    public function delete(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);
        verifyCsrfToken();

        $student = $this->studentModel->find((int) $id);
        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect('/students');
        }
        $this->requireStudentInScope((int) $id);

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $this->flash('error', 'Reason is required for deletion.');
            $this->redirect('/students/' . $id);
        }

        $currentUser = $this->getCurrentUser();
        $currentRole = $currentUser['role'] ?? 'staff';

        try {
            $steps = $this->buildDeactivationSteps($currentRole, (int) $currentUser['id']);

            if (empty($steps)) {
                // Super admin can delete directly
                $this->studentModel->delete((int) $id);
                $this->logAudit('delete_student', 'student', (int) $id, [
                    'name'   => $student['full_name'],
                    'reason' => $reason,
                    'direct' => true,
                ]);
                $this->flash('success', 'Student deleted successfully.');
                $this->redirect('/students');
                return;
            } else {
                $wfModel = new \Workflow();
                $wfModel->createWorkflow(
                    'deletion',
                    (int) $id,
                    (int) $currentUser['id'],
                    $currentRole,
                    $steps,
                    $reason
                );
                $this->logAudit('initiate_deletion', 'student', (int) $id, [
                    'name'   => $student['full_name'],
                    'reason' => $reason,
                ]);
                $this->flash('success', 'Deletion request submitted for approval.');
            }
        } catch (\Throwable $e) {
            error_log('Deletion workflow failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to process deletion: ' . $e->getMessage());
        }

        $this->redirect('/students/' . $id);
    }

    /**
     * Build deactivation approval steps based on initiator role.
     */
    private function buildDeactivationSteps(string $initiatorRole, int $initiatorId): array
    {
        $steps = [];
        $userModel = new \BranchAwareApprovers(new \User(), $initiatorId);

        if (in_array($initiatorRole, ['staff', 'teacher'], true)) {
            $coordinators = $userModel->getByRole('coordinator', 'active');
            if (!empty($coordinators)) {
                $steps[] = ['approver_id' => $coordinators[0]['id'], 'approver_role' => 'coordinator'];
            }
        }

        if (in_array($initiatorRole, ['staff', 'teacher', 'coordinator'], true)) {
            $admins = $userModel->getByRole('admin', 'active');
            if (!empty($admins)) {
                $steps[] = ['approver_id' => $admins[0]['id'], 'approver_role' => 'admin'];
            }
        }

        if (in_array($initiatorRole, ['staff', 'teacher', 'coordinator', 'admin'], true)) {
            $superAdmins = $userModel->getByRole('super_admin', 'active');
            if (!empty($superAdmins)) {
                $steps[] = ['approver_id' => $superAdmins[0]['id'], 'approver_role' => 'super_admin'];
            }
        }

        return $steps;
    }

    /**
     * Stop a branch admin, coordinator, staff member or teacher opening a
     * student outside their branch or classes by changing the id.
     */
    private function requireStudentInScope(int $studentId): void
    {
        if (!BranchScope::current()->allowsStudent($studentId)) {
            http_response_code(403);
            $this->flash('error', 'That student is not in your branch.');
            $this->redirect('/students');
        }
    }

    /**
     * Log an audit entry for student actions.
     */
    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'unknown',
                $action,
                $entityType,
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
