<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AccountService;
use App\Services\BranchScope;

/**
 * Staff-side parent/guardian account management.
 *
 * A guardian login can already be created at student-registration time
 * (StudentController::store()); this controller fills the gap after that:
 * adding another guardian to an already-registered student, correcting a
 * guardian's name/phone/WhatsApp, and deactivating/reactivating one
 * guardian's login without touching the student or any other guardian.
 *
 * Authorization mirrors AccountController::parentCode() exactly: the same
 * ISSUER_ROLES set, and the same BranchScope::allowsStudent() check against
 * the linked student -- a parent row has no branch of its own, so it is
 * always reached through the student it belongs to.
 */
class ParentController extends BaseController
{
    private const ISSUER_ROLES = [
        AuthMiddleware::ROLE_SUPER_ADMIN,
        AuthMiddleware::ROLE_ADMIN,
        AuthMiddleware::ROLE_COORDINATOR,
    ];

    /** GET /students/{studentId}/parents/create */
    public function create(string $studentId): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);

        $student = $this->studentOrFail((int) $studentId);

        $this->view('parents/create', [
            'pageTitle' => t('Add a guardian'),
            'student'   => $student,
        ]);
    }

    /** POST /students/{studentId}/parents/create */
    public function store(string $studentId): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);
        verifyCsrfToken();

        $student = $this->studentOrFail((int) $studentId);

        [$name, $phone1, $phone2, $whatsapp, $error] = $this->readGuardianFields();
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/students/' . (int) $student['id'] . '/parents/create');
        }

        $existing = $this->db->prepare('SELECT COUNT(*) FROM parents WHERE phone1 = :p');
        $existing->execute([':p' => $phone1]);
        $parentPassword = AccountService::temporaryPassword();

        $this->db->prepare(
            'INSERT INTO parents (student_id, parent_name, phone1, phone2, whatsapp, password_hash, must_change_password, status, created_at, updated_at)
             VALUES (:s, :n, :p1, :p2, :wa, :h, 1, \'active\', NOW(), NOW())'
        )->execute([
            ':s'  => (int) $student['id'],
            ':n'  => $name,
            ':p1' => $phone1,
            ':p2' => $phone2,
            ':wa' => $whatsapp,
            ':h'  => password_hash($parentPassword, PASSWORD_DEFAULT),
        ]);

        if ((int) $existing->fetchColumn() > 0) {
            $this->flash('warning', t('This phone number is already a parent login for another child. The parent portal shows one child per phone number, so use a different number if this guardian needs to see this child too.'));
        } else {
            $this->flash('success', t('Guardian added.') . ' ' . t('Temporary password') . ': ' . $parentPassword);
        }
        $this->redirect('/students/' . (int) $student['id']);
    }

    /** GET /parents/{id}/edit */
    public function edit(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);

        $parent = $this->parentOrFail((int) $id);

        $this->view('parents/edit', [
            'pageTitle' => t('Edit guardian'),
            'parent'    => $parent,
        ]);
    }

    /** POST /parents/{id}/edit */
    public function update(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);
        verifyCsrfToken();

        $parent = $this->parentOrFail((int) $id);

        [$name, $phone1, $phone2, $whatsapp, $error] = $this->readGuardianFields();
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/parents/' . (int) $parent['id'] . '/edit');
        }

        if ($phone1 !== $parent['phone1']) {
            $dup = $this->db->prepare('SELECT COUNT(*) FROM parents WHERE phone1 = :p AND id != :id');
            $dup->execute([':p' => $phone1, ':id' => (int) $parent['id']]);
            if ((int) $dup->fetchColumn() > 0) {
                $this->flash('warning', t('This phone number is already a parent login for another child. The parent portal shows one child per phone number.'));
            }
        }

        $this->db->prepare(
            'UPDATE parents SET parent_name = :n, phone1 = :p1, phone2 = :p2, whatsapp = :wa, updated_at = NOW() WHERE id = :id'
        )->execute([
            ':n'   => $name,
            ':p1'  => $phone1,
            ':p2'  => $phone2,
            ':wa'  => $whatsapp,
            ':id'  => (int) $parent['id'],
        ]);

        $this->flash('success', t('Guardian details updated.'));
        $this->redirect('/students/' . (int) $parent['student_id']);
    }

    /** POST /parents/{id}/deactivate */
    public function deactivate(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);
        verifyCsrfToken();

        $parent = $this->parentOrFail((int) $id);
        (new \Parent_())->deactivate((int) $parent['id']);
        $this->flash('success', t('Guardian login deactivated. This does not affect the student or any other guardian.'));
        $this->redirect('/students/' . (int) $parent['student_id']);
    }

    /** POST /parents/{id}/reactivate */
    public function reactivate(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);
        verifyCsrfToken();

        $parent = $this->parentOrFail((int) $id);
        (new \Parent_())->reactivate((int) $parent['id']);
        $this->flash('success', t('Guardian login reactivated.'));
        $this->redirect('/students/' . (int) $parent['student_id']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function studentOrFail(int $studentId): array
    {
        $stmt = $this->db->prepare('SELECT id, student_id_display, full_name FROM students WHERE id = :id');
        $stmt->execute([':id' => $studentId]);
        $student = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$student || !BranchScope::current()->allowsStudent((int) $student['id'])) {
            http_response_code(403);
            $this->flash('error', t('That student is not in your branch.'));
            $this->redirect('/students');
        }
        return $student;
    }

    private function parentOrFail(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM parents WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $parent = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$parent || !BranchScope::current()->allowsStudent((int) $parent['student_id'])) {
            http_response_code(403);
            $this->flash('error', t('That student is not in your branch.'));
            $this->redirect('/students');
        }
        return $parent;
    }

    /**
     * @return array{0:string,1:string,2:?string,3:?string,4:?string}
     */
    private function readGuardianFields(): array
    {
        $name     = trim((string) ($_POST['parent_name'] ?? ''));
        $phone1   = preg_replace('/[\s-]/', '', trim((string) ($_POST['phone1'] ?? ''))) ?? '';
        $phone2   = trim((string) ($_POST['phone2'] ?? ''));
        $phone2   = $phone2 !== '' ? (preg_replace('/[\s-]/', '', $phone2) ?? '') : '';
        $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));

        if ($name === '' || $phone1 === '' || preg_match('/^(\+94|0)\d{9}$/', $phone1) !== 1) {
            return [$name, $phone1, $phone2 ?: null, $whatsapp ?: null, t('Give the guardian\'s name and a Sri Lankan phone number (for example 0771234567).')];
        }
        if ($phone2 !== '' && preg_match('/^(\+94|0)\d{9}$/', $phone2) !== 1) {
            return [$name, $phone1, $phone2 ?: null, $whatsapp ?: null, t('The second phone number is not a valid Sri Lankan number.')];
        }

        return [$name, $phone1, $phone2 !== '' ? $phone2 : null, $whatsapp !== '' ? $whatsapp : null, null];
    }
}
