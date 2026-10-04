<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

/**
 * API Controller for AJAX/JSON endpoints.
 */
class ApiController extends BaseController
{
    /**
     * Return active classes for a given institute as JSON.
     *
     * GET /api/classes-by-institute/{id}
     *
     * Response contract:
     * [
     *   { "id": int, "name": string, "monthly_fee": float },
     *   ...
     * ]
     */
    public function classesByInstitute(string $id): void
    {
        // Require authentication — only logged-in users may query classes
        if (!AuthMiddleware::isAuthenticated()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $instituteId = (int) $id;

        if ($instituteId <= 0) {
            $this->json(['error' => 'Invalid institute ID'], 400);
        }

        try {
            // Verify institute exists and is active
            $instituteModel = new \Institute();
            $institute = $instituteModel->find($instituteId);

            if (!$institute || ($institute['status'] ?? '') !== 'active') {
                $this->json([], 200); // Return empty array for invalid/inactive institute
            }

            // Fetch active classes for this institute
            $classModel = new \ClassModel();
            $classes = $classModel->getByInstitute($instituteId);

            // Filter to active classes only and build consistent response
            $result = [];
            foreach ($classes as $cls) {
                if (($cls['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $result[] = [
                    'id'          => (int) $cls['id'],
                    'name'        => $cls['class_name'] ?? $cls['name'] ?? '',
                    'monthly_fee' => (float) ($cls['monthly_fee'] ?? 0),
                ];
            }

            $this->json($result);
        } catch (\Throwable $e) {
            error_log('ApiController::classesByInstitute error: ' . $e->getMessage());
            $this->json(['error' => 'Failed to load classes'], 500);
        }
    }
}
