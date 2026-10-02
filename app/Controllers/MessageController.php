<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class MessageController extends BaseController
{
    private \Notification $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new \Notification();
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

        $this->view('messages/index', [
            'pageTitle'     => 'System Message Inbox',
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'filters'       => $filters,
            'currentUser'   => $currentUser,
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
     * Broadcast / Send System Message (Super Admin & Admin only)
     */
    public function send(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $title      = trim($_POST['title'] ?? '');
        $body       = trim($_POST['body'] ?? '');
        $type       = trim($_POST['type'] ?? 'info');
        $targetRole = trim($_POST['target_role'] ?? 'all');

        if (empty($title) || empty($body)) {
            $this->flash('error', 'Message title and body are required.');
            $this->redirect('/messages');
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
