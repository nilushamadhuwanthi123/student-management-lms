<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;

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
        $scope = BranchScope::current();
        if (!$scope->isGlobal()) {
            $users = array_values(array_filter($users, static fn (array $u): bool => $scope->allowsUser((int) $u['id'])));
            // Every figure on the page is this branch's own: the totals are
            // worked out from the branch's people, never the whole system.
            $mine = array_values(array_filter($this->userModel->getFilteredUsers([]), static fn (array $u): bool => $scope->allowsUser((int) $u['id'])));
            $count = static function (array $roles, bool $activeOnly) use ($mine): int {
                return count(array_filter($mine, static fn (array $u): bool => in_array($u['role'], $roles, true) && (!$activeOnly || $u['status'] === 'active')));
            };
            $kpi = [
                'total'        => count($mine),
                'active_users' => count(array_filter($mine, static fn (array $u): bool => $u['status'] === 'active')),
                'admins'       => ['active' => $count(['admin', 'super_admin'], true), 'total' => $count(['admin', 'super_admin'], false)],
                'coordinators' => ['active' => $count(['coordinator'], true), 'total' => $count(['coordinator'], false)],
                'staff'        => ['active' => $count(['staff'], true), 'total' => $count(['staff'], false)],
                'teachers'     => ['active' => $count(['teacher'], true), 'total' => $count(['teacher'], false)],
            ];
        }
        // What the signed-in user may do to each card, and the branches each
        // person actually works in: the branches they are attached to plus
        // the branches of the classes they teach (a teacher is linked by
        // her classes, not by a branch row, so she used to show as
        // "Not assigned").
        $branchStmt = $this->db->prepare(
            "SELECT DISTINCT i.name FROM institutes i
              WHERE i.status = 'active'
                AND (i.id IN (SELECT ui.institute_id FROM user_institutes ui WHERE ui.user_id = :u1)
                  OR i.id IN (SELECT c.institute_id FROM classes c WHERE c.teacher_id = :u2 AND c.status = 'active'))
              ORDER BY i.name"
        );
        foreach ($users as &$u) {
            $u['can_manage'] = $scope->allowsManageUser((int) $u['id']);
            $u['manage_note'] = $u['can_manage'] ? '' : $this->manageBlockReason((int) $u['id']);
            $branchStmt->execute([':u1' => (int) $u['id'], ':u2' => (int) $u['id']]);
            $u['branch_names'] = $branchStmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        unset($u);

        // Dropdowns for filters: only this branch's branches and classes.
        if ($instFilter !== '' && !$scope->allowsInstitute((int) $instFilter)) {
            $instFilter = '';
        }
        $institutes = array_values(array_filter($this->instituteModel->getActiveInstitutes(), static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));
        $classes = !empty($instFilter)
            ? $this->classModel->getActiveClasses((int) $instFilter)
            : $this->classModel->getActiveClasses();
        $classes = array_values(array_filter($classes, static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));

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

        $scope = BranchScope::current();
        $institutes = array_values(array_filter($this->instituteModel->getActiveInstitutes(), static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));
        $classes = array_values(array_filter($this->classModel->getActiveClasses(), static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));

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
     * The extension to save an uploaded image under, or null if it is not
     * an image we accept.
     *
     * Decided by inspecting the file's own bytes. Both the browser's
     * Content-Type and the original filename are attacker-controlled: a
     * .php file announced as image/jpeg used to be saved under its own
     * name into public/uploads/profiles, which is served by the web
     * server, so visiting it afterwards ran it. The extension must come
     * from what the file actually is, never from what it claims to be.
     *
     * Same approach as ParentPortalController::uploadPhoto().
     */
    private static function imageExtension(string $tmpPath): ?string
    {
        if (!is_uploaded_file($tmpPath)) {
            return null;
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);

        return $allowed[$mime] ?? null;
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
        } elseif (!BranchScope::current()->allowsInstitute($instituteId)) {
            $errors[] = 'You can only add people to your own branch.';
        }
        // A branch admin manages coordinators, staff and teachers. Creating
        // another administrator is the super admin's job.
        if ($role === 'admin' && !BranchScope::current()->isGlobal()) {
            $errors[] = 'Only the super admin can create administrator accounts.';
        }

        if (!empty($email) && $this->userModel->findByEmail($email)) {
            $errors[] = 'This email address is already in use.';
        }

        // Profile Photo Upload Handling
        $profilePhoto = null;
        if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = self::imageExtension($_FILES['profile_photo']['tmp_name']);
            if ($ext !== null) {
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

        // A random temporary password, shown once; it must be changed at the first login.
        $temporaryPassword = \App\Services\AccountService::temporaryPassword();
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

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

            // The branch chosen on the form is now actually recorded, so
            // the new admin/coordinator/staff member is scoped to it.
            if ($instituteId && $role !== 'teacher') {
                $this->userModel->query(
                    "INSERT IGNORE INTO user_institutes (user_id, institute_id, assigned_by) VALUES (:u, :i, :by)",
                    [':u' => $userId, ':i' => $instituteId, ':by' => $currentUserId]
                );
            }

            // Sync assigned classes
            if (!empty($selectedClasses) && is_array($selectedClasses)) {
                $selectedClasses = array_values(array_filter($selectedClasses, static fn ($c): bool => BranchScope::current()->allowsClass((int) $c)));
                $this->userModel->syncClasses($userId, $selectedClasses);
            }

            $this->logAudit('create_user', 'user', $userId, [
                'display_id' => $displayId,
                'role'       => $role,
                'name'       => $fullName,
            ]);

            session_set('issued_credentials', [[
                'kind' => 'password', 'name' => $fullName, 'login_id' => $displayId,
                'secret' => $temporaryPassword, 'expires_at' => null,
                'phone' => $phone1, 'email' => (string) ($email ?? ''),
            ]]);
            session_set('issued_back', '/users');
            $this->flash('success', t('Account created. Login ID: {id}', ['id' => $displayId]));
            $this->redirect('/accounts/issued');
        } catch (\Throwable $e) {
            error_log('User store error: ' . $e->getMessage());
            $this->flash('error', t('The account could not be created. Please check the details and try again.'));
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
        $this->requireUserManageable((int) $user['id']);

        $scope = BranchScope::current();
        $institutes = $this->instituteModel->getActiveInstitutes();
        $classes = array_values(array_filter($this->classModel->getActiveClasses(), static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));
        $assignedClasses = $this->userModel->getAssignedClassIds((int) $id);
        $branchStmt = $this->db->prepare("SELECT institute_id FROM user_institutes WHERE user_id = :u");
        $branchStmt->execute([':u' => (int) $id]);
        $assignedBranches = array_map('intval', $branchStmt->fetchAll(\PDO::FETCH_COLUMN));

        $this->view('users/edit', [
            'pageTitle'       => 'Edit User - ' . ($user['full_name'] ?? ''),
            'user'            => $user,
            'institutes'      => $institutes,
            'classes'         => $classes,
            'assignedClasses' => $assignedClasses,
            'assignedBranches' => $assignedBranches,
            'canEditBranches' => $scope->isGlobal() && $user['role'] !== 'super_admin',
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
        $this->requireUserManageable((int) $user['id']);

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
            $ext = self::imageExtension($_FILES['profile_photo']['tmp_name']);
            if ($ext !== null) {
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

            // A branch admin only sees their branch's classes on the form,
            // so assignments to other branches' classes are kept as they
            // were instead of being wiped by the save.
            $scope = BranchScope::current();
            if (!$scope->isGlobal()) {
                $kept = array_values(array_filter(
                    array_map('intval', $this->userModel->getAssignedClassIds((int) $id)),
                    static fn (int $c): bool => !$scope->allowsClass($c)
                ));
                $chosen = array_values(array_filter(array_map('intval', (array) $selectedClasses), static fn (int $c): bool => $scope->allowsClass($c)));
                $selectedClasses = array_values(array_unique(array_merge($kept, $chosen)));
            }
            $this->userModel->syncClasses((int) $id, $selectedClasses);

            // Branch membership is set by the super admin only.
            if ($scope->isGlobal() && isset($_POST['branches_submitted']) && $user['role'] !== 'super_admin') {
                $branches = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['branches'] ?? [])))));
                $this->db->prepare("DELETE FROM user_institutes WHERE user_id = :u")->execute([':u' => (int) $id]);
                $add = $this->db->prepare("INSERT IGNORE INTO user_institutes (user_id, institute_id, assigned_by) VALUES (:u, :i, :by)");
                foreach ($branches as $b) {
                    $add->execute([':u' => (int) $id, ':i' => $b, ':by' => (int) session_get('user_id')]);
                }
                $this->logAudit('update_user_branches', 'user', (int) $id, ['branches' => $branches]);
            }

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
        $this->requireUserManageable((int) $id);

        $newStatus = $this->userModel->toggleStatus((int) $id);
        $this->logAudit('toggle_user_status', 'user', (int) $id, ['new_status' => $newStatus]);

        $this->flash('success', "User status updated to " . ucfirst($newStatus) . ".");
        $this->redirect('/users');
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

        // A branch admin could previously reset ANY account's password by
        // id, including the super admin's. Now only people in their branch.
        $this->requireUserManageable((int) $id);
        if ((int) $id !== (int) session_get('user_id')) {
            $target = $this->userModel->find((int) $id);
            if ($target && $target['role'] === 'super_admin' && !BranchScope::current()->isGlobal()) {
                http_response_code(403);
                $this->flash('error', 'You cannot reset that account.');
                $this->back();
            }
        }

        // Office staff no longer choose (or see) someone else's password:
        // this now issues a one-time reset code, like for students and parents.
        (new AccountController())->userCode($id);
    }

    /**
     * Printable ID Card View
     */
    public function idCard(string $id): void
    {
        AuthMiddleware::requireAuth();

        // access: role-checked -- admins may print anyone's card, and a
        // member of staff may print their own. Checking user_type as well
        // as the id matters: a student's session id is a students.id and
        // can equal a staff member's users.id.
        $isOwnCard = session_get('user_type') === 'user'
            && (int) session_get('user_id') === (int) $id;
        if (!$isOwnCard) {
            AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN]);
        }

        $user = $this->userModel->find((int) $id);
        if (!$user) {
            $this->flash('error', 'User not found.');
            $this->redirect('/users');
        }
        $this->requireUserInScope((int) $user['id']);

        $this->view('users/id-card', [
            'pageTitle'  => 'ID Card - ' . ($user['full_name'] ?? ''),
            'user'       => $user,
            'roleColors' => \User::ROLE_COLORS,
        ], null); // Render without layout for printing
    }

    /**
     * A branch admin manages the people of their own branch only, and
     * never the super admin. Anyone may open their own record.
     */
    private function requireUserInScope(int $userId): void
    {
        if (!BranchScope::current()->allowsUser($userId)) {
            http_response_code(403);
            $this->flash('error', 'That account is not in your branch.');
            $this->redirect('/users');
        }
    }

    /** For anything that changes the account; see BranchScope::allowsManageUser(). */
    private function requireUserManageable(int $userId): void
    {
        $this->requireUserInScope($userId);
        if (!BranchScope::current()->allowsManageUser($userId)) {
            http_response_code(403);
            $this->flash('error', $this->manageBlockReason($userId));
            $this->redirect('/users');
        }
    }

    /** Why the signed-in user may not change this account (shown on the card and as the error). */
    private function manageBlockReason(int $userId): string
    {
        if ((int) session_get('user_id') === $userId) {
            return 'This is your own account. Change your details from My Profile.';
        }
        $role = (string) ($this->userModel->find($userId)['role'] ?? '');
        if (in_array($role, ['admin', 'super_admin'], true)) {
            return 'Only the super admin can change an admin account.';
        }
        return 'Works in more than one branch, so only the super admin can change this account.';
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
        $this->requireUserManageable((int) $user['id']);

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

        // access: role-checked -- this page reads the staff table by the
        // session id. For a student that id is a students.id, so student
        // number 11 was being shown staff member 11's profile. Students
        // and parents go to their own pages instead.
        $userType = session_get('user_type');
        if ($userType === 'student') {
            $this->redirect('/my/home');
        }
        if ($userType === 'parent') {
            $this->redirect('/family');
        }

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

