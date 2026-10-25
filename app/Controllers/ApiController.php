<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class ApiController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Return classes for a given institute as JSON.
     * Used by student create/edit forms to dynamically load classes.
     */
    public function classesByInstitute(string $id): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- staff forms only
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF, AuthMiddleware::ROLE_TEACHER]);

        $instituteId = (int) $id;

        // Another branch's class list is not returned.
        if (!\App\Services\BranchScope::current()->allowsInstitute($instituteId)) {
            header('Content-Type: application/json; charset=utf-8');
            echo '[]';
            exit;
        }

        $stmt = $this->db->prepare("
            SELECT c.id, c.class_name, c.subject, c.grade, c.medium,
                   c.monthly_fee, c.status,
                   u.full_name AS teacher_name
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE c.institute_id = :iid AND c.status = 'active'
            ORDER BY c.class_name ASC
        ");
        $stmt->execute([':iid' => $instituteId]);
        $classes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($classes);
        exit;
    }
}
