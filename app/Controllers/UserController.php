<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class UserController extends BaseController
{
    private \User $userModel;
    private \Institute $instituteModel;
    private \ClassModel $classModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new \User();
        $this->instituteModel = new \Institute();
        $this->classModel = new \ClassModel();
    }

    /**
     * All Users Page: Super Admin & Admin user management view
     * Features: User KPI card, cascading filters, role-colored card layout
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $search      = trim($_GET['search'] ?? '');
        $roleFilter  = trim($_GET['role'] ?? '');
        $instFilter  = trim($_GET['institute_id'] ?? '');
        $classFilter = trim($_GET['class_id'] ?? '');
        $statusFilter= trim($_GET['status'] ?? '');

        $filters = [
            'search'       => $search,
            'role'         => $roleFilter,
            'institute_id' => $instFilter,
            'class_id'     => $classFilter,
            'status'       => $statusFilter,
        ];

        // Fetch filtered users (Admin, Coordinator, Staff, Teacher - NOT students)
        $users = $this->userModel->getFilteredUsers($filters);
        $kpi = $this->userModel->getStatistics();

        // Dropdowns for filters
        $institutes = $this->instituteModel->getActiveInstitutes();
        $classes = !empty($instFilter) 
            ? $this->classModel->getActiveClasses((int) $instFilter)
            : $this->classModel->getActiveClasses();

        $this->view('users/index', [
            'pageTitle'  => 'User Management',
            'users'      => $users,
            'kpi'        => $kpi,
            'institutes' => $institutes,
            'classes'    => $classes,
            'filters'    => $filters,
            'roles'      => [
                'admin'       => 'Administrator',
                'coordinator' => 'Coordinator',
                'staff'       => 'Staff',
                'teacher'     => 'Teacher',
            ],
            'roleColors' => \User::ROLE_COLORS,
        ], 'layouts/admin');
    }

    /**
     * Show Create User Form
     * Fields: Photo, Personal, Contact, Academic (Institute & Classes)
     */
    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $institutes = $this->instituteModel->getActiveInstitutes();
        $classes = $this->classModel->getActiveClasses();

        $this->view('users/create', [
            'pageTitle'  => 'Create New User',
            'institutes' => $institutes,
            'classes'    => $classes,
            'roles'      => [
                'admin'       => 'Administrator (ADM)',
                'coordinator' => 'Coordinator (COD)',
                'staff'       => 'Staff (STF)',
                'teacher'     => 'Teacher (TCH)',
            ],
            'roleColors' => \User::ROLE_COLORS,
        ], 'layouts/admin');
    }

    /**
     * Store new User
     */
    public function store(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $role        = strtolower(trim($_POST['role'] ?? ''));
        $fullName    = trim($_POST['full_name'] ?? '');
        $dob         = trim($_POST['date_of_birth'] ?? '') ?: null;
        $address     = trim($_POST['address'] ?? '');
        $province    = trim($_POST['province'] ?? '');
        $district    = trim($_POST['district'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        $email       = trim($_POST['email'] ?? '') ?: null;
        $phone1      = trim($_POST['phone1'] ?? '');
        $phone2      = trim($_POST['phone2'] ?? '') ?: null;
        $whatsapp    = trim($_POST['whatsapp'] ?? '');
        $homeNumber  = trim($_POST['home_number'] ?? '') ?: null;
        $instituteId = !empty($_POST['institute_id']) ? (int) $_POST['institute_id'] : null;
        $selectedClasses = $_POST['classes'] ?? [];

        // Validation
        $errors = [];
        if (empty($role) || !in_array($role, ['admin', 'coordinator', 'staff', 'teacher'], true)) {
            $errors[] = 'Please select a valid user Role.';
        }
        if (empty($fullName)) {
            $errors[] = 'Full Name is required.';
        }
        if (empty($phone1)) {
            $errors[] = 'Personal Number 1 is required.';
        }
        if (empty($whatsapp)) {
            $errors[] = 'WhatsApp Number is required.';
        }
        if (empty($instituteId)) {
            $errors[] = 'Institute selection is required.';
        }

        if (!empty($email) && $this->userModel->findByEmail($email)) {
            $errors[] = 'This email address is already in use.';
        }

        // Profile Photo Upload Handling
        $profilePhoto = null;
        if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = $_FILES['profile_photo']['type'];
            if (in_array($fileType, $allowedTypes, true)) {
                $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetDir = BASE_PATH . '/public/uploads/profiles';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $targetPath = $targetDir . '/' . $filename;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                    $profilePhoto = '/uploads/profiles/' . $filename;
                }
            } else {
                $errors[] = 'Profile photo must be a JPG, PNG, or WEBP image.';
            }
        }

        if (!empty($errors)) {
            $this->flash('error', implode('<br>', $errors));
            $this->redirect('/users/create');
        }

        // Auto-generate User ID display (ADM20260001, COD20260001, etc.)
        $displayId = $this->userModel->generateDisplayId($role);
        $currentUserId = (int) (session_get('user_id') ?? 1);

        // Initial default password hash for first login
        $defaultPassword = 'Password@123';
        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

        try {
            $userId = $this->userModel->create([
                'user_id_display' => $displayId,
                'role'            => $role,
                'full_name'       => $fullName,
                'email'           => $email,
                'phone1'          => $phone1,
                'phone2'          => $phone2,
                'whatsapp'        => $whatsapp,
                'home_number'     => $homeNumber,
                'date_of_birth'   => $dob,
                'address'         => $address,
                'province'        => $province,
                'district'        => $district,
                'notes'           => $notes,
                'profile_photo'   => $profilePhoto,
                'password_hash'   => $passwordHash,
                'status'          => 'pending_first_login',
                'created_by'      => $currentUserId,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            // If coordinator, assign to institute if institute has no coordinator
            if ($role === 'coordinator' && $instituteId) {
                $sql = "UPDATE institutes SET coordinator_id = :cid WHERE id = :iid AND (coordinator_id IS NULL OR coordinator_id = 0)";
                $this->userModel->query($sql, [':cid' => $userId, ':iid' => $instituteId]);
            }

            // Sync assigned classes
            if (!empty($selectedClasses) && is_array($selectedClasses)) {
                $this->userModel->syncClasses($userId, $selectedClasses);
            }

            $this->logAudit('create_user', 'user', $userId, [
                'display_id' => $displayId,
                'role'       => $role,
                'name'       => $fullName,
            ]);

            $this->flash('success', "User {$fullName} registered successfully with Registration ID: <strong>{$displayId}</strong>. Status is set to 'Pending first login'.");
            $this->redirect('/users');
        } catch (\Throwable $e) {
            error_log('User store error: ' . $e->getMessage());
            $this->flash('error', 'Failed to create user: ' . $e->getMessage());
            $this->redirect('/users/create');
        }
    }

    /**
     * Show Edit User Form
     */
    public function edit(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $user = $this->userModel->find((int) $id);
        if (!$user) {
            $this->flash('error', 'User not found.');
            $this->redirect('/users');
        }

        $institutes = $this->instituteModel->getActiveInstitutes();
        $classes = $this->classModel->getActiveClasses();
        $assignedClasses = $this->userModel->getAssignedClassIds((int) $id);

        $this->view('users/edit', [
            'pageTitle'       => 'Edit User - ' . ($user['full_name'] ?? ''),
            'user'            => $user,
            'institutes'      => $institutes,
            'classes'         => $classes,
            'assignedClasses' => $assignedClasses,
            'roles'           => [
                'admin'       => 'Administrator (ADM)',
                'coordinator' => 'Coordinator (COD)',
                'staff'       => 'Staff (STF)',
                'teacher'     => 'Teacher (TCH)',
            ],
            'roleColors'      => \User::ROLE_COLORS,
        ], 'layouts/admin');
    }

    /**
     * Process User Update
     */
    public function update(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $user = $this->userModel->find((int) $id);
        if (!$user) {
            $this->flash('error', 'User not found.');
            $this->redirect('/users');
        }

        $fullName    = trim($_POST['full_name'] ?? '');
        $dob         = trim($_POST['date_of_birth'] ?? '') ?: null;
        $address     = trim($_POST['address'] ?? '');
        $province    = trim($_POST['province'] ?? '');
        $district    = trim($_POST['district'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        $email       = trim($_POST['email'] ?? '') ?: null;
        $phone1      = trim($_POST['phone1'] ?? '');
        $phone2      = trim($_POST['phone2'] ?? '') ?: null;
        $whatsapp    = trim($_POST['whatsapp'] ?? '');
        $homeNumber  = trim($_POST['home_number'] ?? '') ?: null;
        $status      = in_array($_POST['status'] ?? '', ['active', 'inactive', 'pending_first_login'], true) ? $_POST['status'] : $user['status'];
        $selectedClasses = $_POST['classes'] ?? [];

        $data = [
            'full_name'     => $fullName,
            'date_of_birth' => $dob,
            'address'       => $address,
            'province'      => $province,
            'district'      => $district,
            'notes'         => $notes,
            'email'         => $email,
            'phone1'        => $phone1,
            'phone2'        => $phone2,
            'whatsapp'      => $whatsapp,
            'home_number'   => $homeNumber,
            'status'        => $status,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        // Handle profile photo upload update
        if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = $_FILES['profile_photo']['type'];
            if (in_array($fileType, $allowedTypes, true)) {
                $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetDir = BASE_PATH . '/public/uploads/profiles';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $targetPath = $targetDir . '/' . $filename;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                    $data['profile_photo'] = '/uploads/profiles/' . $filename;
                }
            }
        }

        try {
            $this->userModel->update((int) $id, $data);
            $this->userModel->syncClasses((int) $id, $selectedClasses);

            $this->logAudit('update_user', 'user', (int) $id, ['name' => $fullName]);
            $this->flash('success', "User {$fullName} updated successfully.");
            $this->redirect('/users');
        } catch (\Throwable $e) {
            error_log('User update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update user.');
            $this->redirect(BASE_URL . "/users/{$id}/edit");
        }
    }

    /**
     * Toggle Active / Inactive Status
     */
    public function toggleStatus(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $newStatus = $this->userModel->toggleStatus((int) $id);
        $this->logAudit('toggle_user_status', 'user', (int) $id, ['new_status' => $newStatus]);

        $this->flash('success', "User status updated to " . ucfirst($newStatus) . ".");
        $this->back();
    }

    /**
     * Reset Password for a User
     */
    public function resetPassword(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $newPassword = trim($_POST['new_password'] ?? 'Password@123');
        if (strlen($newPassword) < 6) {
            $this->flash('error', 'New password must be at least 6 characters long.');
            $this->back();
        }

        $this->userModel->updatePassword((int) $id, $newPassword);
        $this->logAudit('reset_user_password', 'user', (int) $id);

        $this->flash('success', 'User password has been successfully reset to: <strong>' . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8') . '</strong>');
        $this->back();
    }

    /**
     * Printable ID Card View
     */
    public function idCard(string $id): void
    {
        AuthMiddleware::requireAuth();
        $user = $this->userModel->find((int) $id);
        if (!$user) {
            $this->flash('error', 'User not found.');
            $this->redirect('/users');
        }

        $this->view('users/id-card', [
            'pageTitle'  => 'ID Card - ' . ($user['full_name'] ?? ''),
            'user'       => $user,
            'roleColors' => \User::ROLE_COLORS,
        ], null); // Render without layout for printing
    }

    /**
     * Delete User
     */
    public function delete(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN]);
        verifyCsrfToken();

        $userId = (int) $id;
        $user = $this->userModel->find($userId);

        if (!$user) {
            $this->flash('error', 'User not found.');
            $this->redirect('/users');
        }

        if ($user['role'] === 'super_admin') {
            $this->flash('error', 'Super Admin account cannot be deleted.');
            $this->redirect('/users');
        }

        $this->userModel->delete($userId);
        $this->logAudit('delete_user', 'user', $userId, ['name' => $user['full_name']]);

        $this->flash('success', "User {$user['full_name']} has been deleted.");
        $this->redirect('/users');
    }

    /**
     * User profile view
     */
    public function profile(): void
    {
        AuthMiddleware::requireAuth();
        $currentUser = $this->getCurrentUser();
        $user = $this->userModel->find((int) ($currentUser['id'] ?? 0));

        $this->view('users/profile', [
            'pageTitle' => 'My Profile',
            'user'      => $user ?: $currentUser,
        ]);
    }

    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'super_admin',
                $action,
                $entityType,
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log error: ' . $e->getMessage());
        }
    }
}

