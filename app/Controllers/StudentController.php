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

        $errors = $this->validate($data, [
            'full_name'     => 'required|max:255',
            'date_of_birth' => 'required|date',
            'institute_id'  => 'required|integer',
        ]);

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
            $id = $this->studentModel->create($data);

            // Log the action
            $this->logAudit('create_student', 'student', $id, [
                'student_id_display' => $data['student_id_display'],
                'name'               => $data['full_name'],
            ]);

            $this->flash('success', "Student registered successfully. Student ID: {$data['student_id_display']}");
            $this->redirect(BASE_URL . '/students/' . $id);
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

        $this->view('students/show', [
            'pageTitle' => $student['full_name'] ?? 'Student Details',
            'student'   => $student,
            'payments'  => $payments,
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

        $institutes = [];
        if ($this->db) {
            try {
                $instituteModel = new \Institute();
                $institutes = $instituteModel->all('name ASC');
            } catch (\Throwable $e) {
                error_log('Failed to load institutes: ' . $e->getMessage());
            }
        }

        $this->view('students/edit', [
            'pageTitle'  => 'Edit Student',
            'student'    => $student,
            'institutes' => $institutes,
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
