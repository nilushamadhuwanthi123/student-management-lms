<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

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

        // If there's a search term, use the search method
        if ($search !== '' || !empty($filters)) {
            $students = $this->studentModel->search($search, $filters);
            // Manual pagination over search results
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

        $this->view('students/index', [
            'pageTitle'  => 'Students',
            'students'   => $pagination['data'],
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

        // Load institutes and classes for dropdowns
        $institutes = [];
        $classes = [];
        if ($this->db) {
            try {
                $instituteModel = new \Institute();
                $institutes = $instituteModel->all('name ASC');
                $classModel = new \ClassModel();
                $classes = $classModel->all('class_name ASC');
            } catch (\Throwable $e) {
                error_log('Failed to load form data: ' . $e->getMessage());
            }
        }

        $this->view('students/create', [
            'pageTitle'  => 'Register Student',
            'institutes' => $institutes,
            'classes'    => $classes,
        ]);
    }

    /**
     * Process student registration form submission.
     *
     * DB schema uses: full_name (not first_name/last_name), student_id_display,
     * and does NOT have a gender column.
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

        $data = [
            'full_name'     => trim($_POST['full_name'] ?? ''),
            'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
            'address'       => trim($_POST['address'] ?? ''),
            'phone1'        => trim($_POST['phone1'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
            'institute_id'  => $_POST['institute_id'] ?? '',
        ];

        // Optional fields
        if (!empty($_POST['phone2'])) {
            $data['phone2'] = trim($_POST['phone2']);
        }
        if (!empty($_POST['whatsapp'])) {
            $data['whatsapp'] = trim($_POST['whatsapp']);
        }
        if (!empty($_POST['province'])) {
            $data['province'] = trim($_POST['province']);
        }
        if (!empty($_POST['district'])) {
            $data['district'] = trim($_POST['district']);
        }
        if (!empty($_POST['notes'])) {
            $data['notes'] = trim($_POST['notes']);
        }

        // Guardian info from form
        $guardianName  = trim($_POST['guardian_name'] ?? '');
        $guardianPhone = trim($_POST['guardian_phone'] ?? '');
        $guardianEmail = trim($_POST['guardian_email'] ?? '');

        // Enrollment data
        $classIds        = $_POST['class_ids'] ?? [];
        $scholarshipTypes = $_POST['scholarship_type'] ?? [];

        $errors = $this->validate($data, [
            'full_name'     => 'required|max:255',
            'date_of_birth' => 'required|date',
            'institute_id'  => 'required|integer',
        ]);

        if (empty($classIds)) {
            $errors['class_ids'] = 'At least one class must be selected.';
        }

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect(BASE_URL . '/students/create');
        }

        // Generate student display ID and set defaults
        $data['student_id_display'] = $this->studentModel->generateStudentId();
        $data['status'] = 'active';
        $data['created_by'] = (int) session_get('user_id');
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Set a default password hash (student changes on first login)
        $data['password_hash'] = password_hash('password', PASSWORD_DEFAULT);

        try {
            $studentId = $this->studentModel->create($data);

            // ── Create enrollments for selected classes ──
            $enrollmentModel = new \Enrollment();
            foreach ($classIds as $classId) {
                $scholarship = $scholarshipTypes[$classId] ?? 'full_payment';
                $enrollmentModel->enrollStudent(
                    $studentId,
                    (int) $classId,
                    $scholarship
                );
            }

            // ── Create guardian/parent record if provided ──
            if ($guardianName !== '' || $guardianPhone !== '') {
                $this->createOrLinkGuardian($studentId, $guardianName, $guardianPhone, $guardianEmail);
            }

            // Log the action
            $this->logAudit('create_student', 'student', $studentId, [
                'student_id_display' => $data['student_id_display'],
                'name'               => $data['full_name'],
                'classes_enrolled'   => count($classIds),
            ]);

            $this->flash('success', "Student registered successfully. Student ID: {$data['student_id_display']}");
            $this->redirect(BASE_URL . '/students/' . $studentId);
        } catch (\Throwable $e) {
            error_log('Student creation failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to register student. Please try again.');
            $this->redirect(BASE_URL . '/students/create');
        }
    }

    /**
     * Show the student 360-degree view.
     */
    public function show(string $id): void
    {
        AuthMiddleware::requireAuth();

        $student = $this->studentModel->getWithEnrollments((int) $id);

        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect(BASE_URL . '/students');
        }

        // Get payment history
        $payments = [];
        try {
            $paymentModel = new \Payment();
            $payments = $paymentModel->getByStudent((int) $id);
        } catch (\Throwable $e) {
            error_log('Payment fetch failed: ' . $e->getMessage());
        }

        // Get guardian info from parents table
        $guardian = null;
        try {
            $parentModel = new \Parent_();
            $guardians = $parentModel->findByStudent((int) $id);
            if (!empty($guardians)) {
                $guardian = $guardians[0]; // Primary guardian
            }
        } catch (\Throwable $e) {
            error_log('Guardian fetch failed: ' . $e->getMessage());
        }

        // Merge guardian info into student data for view compatibility
        if ($guardian) {
            $student['guardian_name']  = $guardian['parent_name'] ?? '';
            $student['guardian_phone'] = $guardian['phone1'] ?? '';
            $student['guardian_email'] = ''; // parents table has no email column
        }

        $this->view('students/show', [
            'pageTitle'   => $student['full_name'] ?? 'Student Details',
            'student'     => $student,
            'enrollments' => $student['enrollments'] ?? [],
            'payments'    => $payments,
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
            $this->redirect(BASE_URL . '/students');
        }

        $institutes  = [];
        $classes     = [];
        $enrollments = [];

        try {
            $instituteModel = new \Institute();
            $institutes = $instituteModel->all('name ASC');

            // Load classes for the student's institute
            if (!empty($student['institute_id'])) {
                $classModel = new \ClassModel();
                $classes = $classModel->getByInstitute((int) $student['institute_id']);
            }

            // Load current enrollments
            $enrollmentModel = new \Enrollment();
            $enrollments = $enrollmentModel->getByStudent((int) $id);
        } catch (\Throwable $e) {
            error_log('Failed to load edit form data: ' . $e->getMessage());
        }

        // Load guardian info
        $guardian = null;
        try {
            $parentModel = new \Parent_();
            $guardians = $parentModel->findByStudent((int) $id);
            if (!empty($guardians)) {
                $guardian = $guardians[0];
            }
        } catch (\Throwable $e) {
            error_log('Guardian fetch failed: ' . $e->getMessage());
        }

        // Merge guardian data into student for form pre-population
        $student['guardian_name']  = $guardian['parent_name'] ?? '';
        $student['guardian_phone'] = $guardian['phone1'] ?? '';
        $student['guardian_email'] = ''; // parents table has no email column

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
            $this->redirect(BASE_URL . '/students');
        }

        $data = [
            'full_name'     => trim($_POST['full_name'] ?? ''),
            'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
            'address'       => trim($_POST['address'] ?? ''),
            'phone1'        => trim($_POST['phone1'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
            'institute_id'  => $_POST['institute_id'] ?? '',
        ];

        // Optional fields — always update so they can be cleared
        $data['phone2']   = trim($_POST['phone2'] ?? '');
        $data['whatsapp'] = trim($_POST['whatsapp'] ?? '');
        $data['province'] = trim($_POST['province'] ?? '');
        $data['district'] = trim($_POST['district'] ?? '');
        $data['notes']    = trim($_POST['notes'] ?? '');

        // Guardian info
        $guardianName  = trim($_POST['guardian_name'] ?? '');
        $guardianPhone = trim($_POST['guardian_phone'] ?? '');
        $guardianEmail = trim($_POST['guardian_email'] ?? '');

        // Enrollment data
        $classIds         = $_POST['class_ids'] ?? [];
        $scholarshipTypes = $_POST['scholarship_type'] ?? [];

        $errors = $this->validate($data, [
            'full_name'     => 'required|max:255',
            'date_of_birth' => 'required|date',
            'institute_id'  => 'required|integer',
        ]);

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect(BASE_URL . "/students/{$id}/edit");
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->studentModel->update((int) $id, $data);

            // ── Sync enrollments ──
            $this->syncEnrollments((int) $id, $classIds, $scholarshipTypes);

            // ── Update or create guardian ──
            $this->createOrLinkGuardian((int) $id, $guardianName, $guardianPhone, $guardianEmail);

            $this->logAudit('update_student', 'student', (int) $id, [
                'name' => $data['full_name'],
            ]);

            $this->flash('success', 'Student updated successfully.');
            $this->redirect(BASE_URL . '/students/' . $id);
        } catch (\Throwable $e) {
            error_log('Student update failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to update student. Please try again.');
            $this->redirect(BASE_URL . "/students/{$id}/edit");
        }
    }

    /**
     * Delete a student (soft delete via workflow or hard delete).
     */
    public function delete(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $student = $this->studentModel->find((int) $id);
        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect(BASE_URL . '/students');
            return;
        }

        // Initiate deletion workflow instead of immediate delete
        try {
            $workflowModel = new \Workflow();
            $user = $this->getCurrentUser();
            $workflowModel->create([
                'type'            => 'student_deletion',
                'entity_type'     => 'student',
                'entity_id'       => (int) $id,
                'initiated_by'    => (int) ($user['id'] ?? 0),
                'reason'          => trim($_POST['reason'] ?? 'No reason provided'),
                'status'          => 'pending',
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->logAudit('request_delete_student', 'student', (int) $id, [
                'name'   => $student['full_name'],
                'reason' => trim($_POST['reason'] ?? ''),
            ]);

            $this->flash('info', 'Student deletion request submitted for approval.');
        } catch (\Throwable $e) {
            error_log('Delete workflow failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to submit deletion request.');
        }

        $this->redirect(BASE_URL . '/students/' . $id);
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * Create or update a guardian record linked to a student.
     */
    private function createOrLinkGuardian(int $studentId, string $name, string $phone, string $email): void
    {
        if ($name === '' && $phone === '') {
            return; // No guardian data provided
        }

        try {
            $parentModel = new \Parent_();
            $existing = $parentModel->findByStudent($studentId);

            $guardianData = [
                'student_id'  => $studentId,
                'parent_name' => $name,
                'phone1'      => $phone,
                'status'      => 'active',
                'updated_at'  => date('Y-m-d H:i:s'),
            ];

            if (!empty($existing)) {
                // Update existing guardian
                $parentModel->update((int) $existing[0]['id'], $guardianData);
            } else {
                // Create new guardian with default password
                $guardianData['password_hash'] = password_hash('guardian', PASSWORD_DEFAULT);
                $guardianData['created_at'] = date('Y-m-d H:i:s');
                $parentModel->create($guardianData);
            }
        } catch (\Throwable $e) {
            error_log('Guardian create/update failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync enrollments: enroll in new classes, unenroll from removed ones,
     * update scholarship types for existing ones.
     */
    private function syncEnrollments(int $studentId, array $newClassIds, array $scholarshipTypes): void
    {
        $enrollmentModel = new \Enrollment();
        $currentClassIds = $enrollmentModel->getActiveClassIds($studentId);

        $newClassIds = array_map('intval', $newClassIds);

        // Enroll in newly selected classes
        $toEnroll = array_diff($newClassIds, $currentClassIds);
        foreach ($toEnroll as $classId) {
            $scholarship = $scholarshipTypes[$classId] ?? 'full_payment';
            $enrollmentModel->enrollStudent($studentId, $classId, $scholarship);
        }

        // Unenroll from removed classes
        $toUnenroll = array_diff($currentClassIds, $newClassIds);
        foreach ($toUnenroll as $classId) {
            $enrollmentModel->unenroll($studentId, $classId);
        }

        // Update scholarship types for classes that remain
        $remaining = array_intersect($newClassIds, $currentClassIds);
        foreach ($remaining as $classId) {
            $scholarship = $scholarshipTypes[$classId] ?? 'full_payment';
            $enrollmentModel->updateScholarship($studentId, $classId, $scholarship);
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
