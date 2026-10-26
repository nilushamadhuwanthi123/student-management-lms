<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\GoalService;

/**
 * Personal learning goals.
 *
 * A student sets up to three goals of their own from My Home and can
 * remove them. A teacher (or admin) sets goals for a student from that
 * student's page; the student sees who set it and cannot remove it.
 * Progress is always measured live from the records -- nobody types in a
 * result.
 */
class GoalController extends BaseController
{
    private const STAFF = [AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_TEACHER];

    /** POST /my/goals */
    public function storeMine(): void
    {
        $studentId = $this->requireStudent();
        verifyCsrfToken();

        $result = (new GoalService($this->db))->create($studentId, $_POST, 'student', $studentId);
        if (is_string($result)) {
            $this->flash('error', $result);
        } else {
            $this->flash('success', t('Goal saved. Your progress towards it updates by itself.'));
        }
        $this->redirect('/my/home#goals');
    }

    /** POST /my/goals/{id}/remove */
    public function removeMine(string $id): void
    {
        $studentId = $this->requireStudent();
        verifyCsrfToken();

        $ok = (new GoalService($this->db))->remove((int) $id, $studentId, 'student', $studentId);
        $this->flash($ok ? 'success' : 'error', $ok ? t('Goal removed.') : t('Only goals you set yourself can be removed.'));
        $this->redirect('/my/home#goals');
    }

    /** POST /students/{id}/goals */
    public function storeForStudent(string $id): void
    {
        $studentId = $this->requireStaffFor((int) $id);
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $result = (new GoalService($this->db))->create($studentId, $_POST, 'user', $userId);
        if (is_string($result)) {
            $this->flash('error', $result);
        } else {
            $this->audit('goal.create', $studentId, ['goal_id' => $result, 'type' => (string) ($_POST['goal_type'] ?? '')]);
            $this->flash('success', t('Goal set. The student will see it on their home page.'));
        }
        $this->redirect('/students/' . $studentId . '#goals');
    }

    /** POST /students/{id}/goals/{goalId}/remove */
    public function removeForStudent(string $id, string $goalId): void
    {
        $studentId = $this->requireStaffFor((int) $id);
        verifyCsrfToken();

        $ok = (new GoalService($this->db))->remove((int) $goalId, $studentId, 'user', (int) session_get('user_id'));
        if ($ok) {
            $this->audit('goal.remove', $studentId, ['goal_id' => (int) $goalId]);
        }
        $this->flash($ok ? 'success' : 'error', $ok ? t('Goal removed.') : t('You can remove only goals you set.'));
        $this->redirect('/students/' . $studentId . '#goals');
    }

    // ---------- guards ----------

    private function requireStudent(): int
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'student') {
            $this->flash('error', t('That area is for students.'));
            $this->redirect('/dashboard');
        }
        return (int) session_get('user_id');
    }

    private function requireStaffFor(int $studentId): int
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::STAFF);
        if ($studentId <= 0 || !BranchScope::current()->allowsStudent($studentId)) {
            http_response_code(403);
            exit('Not allowed.');
        }
        return $studentId;
    }

    private function audit(string $action, int $studentId, array $details): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'student', $studentId, $details);
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
