<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;

class MessageController extends BaseController
{
    private \Notification $notificationModel;
    private \ClassModel $classModel;

    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new \Notification();
        $this->classModel = new \ClassModel();
    }

    /**
     * System Message Inbox View
     * Document 1 & Kavindu Role Guide Specification:
     * Available to Super Admin, Admin, Coordinator, Staff, Teacher, Student, Parent
     * Features: Read/unread status, filters by role/date/type, detail modal
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $currentUser = $this->getCurrentUser();
        $userId = (int) ($currentUser['id'] ?? 0);
        $role = $currentUser['role'] ?? 'user';
        $userType = in_array($role, ['student', 'parent'], true) ? $role : 'user';

        $typeFilter   = trim($_GET['type'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');
        $search       = trim($_GET['search'] ?? '');

        $filters = [
            'type'    => $typeFilter,
            'is_read' => ($statusFilter === 'unread') ? 0 : (($statusFilter === 'read') ? 1 : ''),
            'search'  => $search,
        ];

        $notifications = $this->notificationModel->getFilteredInbox($userId, $userType, $filters);
        $unreadCount   = $this->notificationModel->getUnreadCount($userId, $userType);

        // Classes this user may broadcast to (scoped to their own branch
        // for an admin/coordinator; every branch for the super admin),
        // only needed to populate the "message a class" dropdown.
        $classOptions = [];
        if (in_array($role, [AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR], true)) {
            $scope = BranchScope::current();
            $rows = $this->classModel->query(
                "SELECT c.id, c.class_name, c.grade, i.name AS institute_name
                   FROM classes c
                   JOIN institutes i ON i.id = c.institute_id
                  WHERE c.status = 'active'
                  ORDER BY i.name ASC, c.class_name ASC"
            )->fetchAll();
            $classOptions = array_values(array_filter($rows, static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));
        }

        $this->view('messages/index', [
            'pageTitle'     => 'System Message Inbox',
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'filters'       => $filters,
            'currentUser'   => $currentUser,
            'classOptions'  => $classOptions,
        ], 'layouts/admin');
    }

    /**
     * Mark single notification as read
     */
    public function markRead(string $id): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $this->notificationModel->markAsRead((int) $id, $userId);

        if ($this->isAjax()) {
            $this->json(['success' => true]);
        }

        $this->flash('success', 'Notification marked as read.');
        $this->back();
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead(): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();

        $currentUser = $this->getCurrentUser();
        $userId = (int) ($currentUser['id'] ?? 0);
        $role = $currentUser['role'] ?? 'user';
        $userType = in_array($role, ['student', 'parent'], true) ? $role : 'user';

        $this->notificationModel->markAllAsRead($userId, $userType);

        $this->flash('success', 'All messages marked as read.');
        $this->redirect('/messages');
    }

    /**
     * Broadcast / Send System Message.
     * Two kinds of target:
     *  - "role": every staff account (optionally one role) -- super admin
     *    only, since it reaches every branch at once.
     *  - "class": every actively-enrolled student of one class (and
     *    optionally their parents) -- the super admin for any class, or
     *    an admin/coordinator for a class in their own branch.
     */
    public function send(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);
        verifyCsrfToken();

        $role       = AuthMiddleware::getUserRole();
        $title      = trim($_POST['title'] ?? '');
        $body       = trim($_POST['body'] ?? '');
        $type       = trim($_POST['type'] ?? 'info');
        $targetType = trim($_POST['target_type'] ?? 'role');

        if ($title === '' || $body === '') {
            $this->flash('error', 'Message title and body are required.');
            $this->redirect('/messages');
        }

        if ($targetType === 'class') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $scope = BranchScope::current();
            if ($classId <= 0 || !$scope->allowsClass($classId)) {
                $this->flash('error', 'Choose a class you have access to.');
                $this->redirect('/messages');
            }
            $alsoParents = !empty($_POST['also_parents']);
            $count = $this->notificationModel->broadcastToClass($classId, $title, $body, $type, $alsoParents);
            $this->flash('success', "Message sent to {$count} recipient(s) in that class.");
            $this->redirect('/messages');
        }

        // Role broadcast reaches every account in every branch, so it
        // stays the super admin's; branch admins use the class target.
        if ($role !== AuthMiddleware::ROLE_SUPER_ADMIN) {
            $this->flash('error', 'Only the super admin can send a site-wide role broadcast. Target a class instead.');
            $this->redirect('/messages');
        }

        $targetRole = trim($_POST['target_role'] ?? 'all');
        // The model builds its WHERE clause from this value, so only the
        // known role names get through.
        if (!in_array($targetRole, ['all', 'super_admin', 'admin', 'coordinator', 'staff', 'teacher'], true)) {
            $targetRole = 'all';
        }

        $count = $this->notificationModel->broadcast($title, $body, $type, $targetRole);
        $this->flash('success', "System notification broadcasted to {$count} user(s).");
        $this->redirect('/messages');
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
