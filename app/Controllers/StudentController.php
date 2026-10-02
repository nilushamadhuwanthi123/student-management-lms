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
                // Ensure name and fee aliases exist on class rows for view
                foreach ($classes as &$cls) {
                    $cls['name'] = $cls['name'] ?? $cls['class_name'] ?? '';
                    $cls['fee'] = $cls['fee'] ?? $cls['monthly_fee'] ?? 0;
                }
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

        // Generate student ID and create
        $studentIdDisplay = $this->studentModel->generateStudentId();
        $insertData = [
            'student_id_display' => $studentIdDisplay,
            'full_name'          => $fullName,
            'email'              => $email !== '' ? $email : null,
            'phone1'             => $phone !== '' ? $phone : null,
            'date_of_birth'      => $dob !== '' ? $dob : date('Y-m-d'),
            'gender'             => in_array($gender, ['male', 'female', 'other'], true) ? $gender : 'male',
            'address'            => $address !== '' ? $address : null,
            'status'             => 'active',
            'created_by'         => (int) session_get('user_id'),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        try {
            $id = $this->studentModel->create($insertData);

            // Handle class enrollments if provided
            $classIds = $_POST['class_ids'] ?? [];
            $scholarshipTypes = $_POST['scholarship_type'] ?? [];
            if (!empty($classIds) && is_array($classIds)) {
                $enrollStmt = $this->db->prepare("INSERT INTO enrollments (student_id, class_id, scholarship_type, enrollment_status, enrolled_at, updated_at) VALUES (:sid, :cid, :stype, 'active', NOW(), NOW())");
                foreach ($classIds as $cid) {
                    $cid = (int) $cid;
                    if ($cid > 0) {
                        $stype = $scholarshipTypes[$cid] ?? 'full_payment';
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

            $this->flash('success', "Student registered successfully. Student ID: {$studentIdDisplay}");
            $this->redirect('/students/' . $id);
        } catch (\Throwable $e) {
            error_log('Student creation failed: ' . $e->getMessage());
            $this->flash('error', 'Failed to register student: ' . $e->getMessage());
            $this->redirect('/students/create');
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
            $this->redirect('/students');
        }

        // Get payment history
        $payments = [];
        try {
            $paymentModel = new \Payment();
            $payments = $paymentModel->getByStudent((int) $id);
        } catch (\Throwable $e) {
            error_log('Payment fetch failed: ' . $e->getMessage());
        }

        // Get workflow history
        $workflows = [];
        try {
            $wfModel = new \Workflow();
            $workflows = $wfModel->getHistory((int) $id, 'student');
        } catch (\Throwable $e) {
            error_log('Workflow fetch failed: ' . $e->getMessage());
        }

        // Get audit trail
        $auditTrail = [];
        try {
            $auditLog = new \AuditLog();
            $auditTrail = $auditLog->getByEntity('student', (int) $id);
        } catch (\Throwable $e) {
            error_log('Audit trail fetch failed: ' . $e->getMessage());
        }

        $this->view('students/show', [
            'pageTitle'   => $student['full_name'],
            'student'     => $student,
            'enrollments' => $student['enrollments'] ?? [],
            'payments'    => $payments,
            'workflows'   => $workflows,
            'auditTrail'  => $auditTrail,
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

        $institutes = [];
        $classes = [];
        $enrollments = [];
        if ($this->db) {
            try {
                $instituteModel = new \Institute();
                $institutes = $instituteModel->all('name ASC');
                $classModel = new \ClassModel();
                $classes = $classModel->all('class_name ASC');
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
            $this->redirect("/students/{$id}/edit");
        }

        $data = [
            'full_name'     => $fullName,
            'date_of_birth' => $dob !== '' ? $dob : ($student['date_of_birth'] ?? date('Y-m-d')),
            'gender'        => in_array($gender, ['male', 'female', 'other'], true) ? $gender : 'male',
            'address'       => $address !== '' ? $address : null,
            'phone1'        => $phone !== '' ? $phone : null,
            'email'         => $email !== '' ? $email : null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

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
            $this->redirect("/students/{$id}/edit");
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
