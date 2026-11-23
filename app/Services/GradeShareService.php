<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Sending one recording or one piece of material to a whole grade.
 *
 * The teacher's own words for this: a lab demonstration is filmed
 * once and every Grade 10 class should see it, whichever branch they
 * sit in. Adding it four times and keeping four copies in step is work
 * the system should be doing.
 *
 * How it works: a grade-wide share still writes one row per class. No
 * query that answers "what can this class see" changes, so student
 * visibility cannot drift -- the risky way to build this would have
 * been a second kind of row that every one of those queries then has to
 * remember. The rows share a `shared_batch` stamp, which is what lets
 * them be shown and withdrawn as one thing.
 *
 * Scope: only classes the signed-in person already teaches or
 * administers, as BranchScope decides. A teacher cannot reach another
 * teacher's class through this, and a branch coordinator cannot reach
 * another branch's.
 */
class GradeShareService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * The other classes of the same grade this person may post to.
     *
     * The class the teacher is already posting to is left out: it is
     * the one being written anyway.
     *
     * @return array<int, array{id: int, class_name: string, institute_name: ?string}>
     */
    public function siblings(int $classId): array
    {
        $grade = $this->gradeOf($classId);
        if ($grade === null || trim($grade) === '') {
            return [];
        }

        $scope = BranchScope::current();
        $allowed = $scope->classIds();           // null means every class
        if ($allowed !== null && $allowed === []) {
            return [];
        }

        $sql = "SELECT c.id, c.class_name, i.name AS institute_name
                  FROM classes c
             LEFT JOIN institutes i ON i.id = c.institute_id
                 WHERE c.grade = :grade
                   AND c.id <> :class_id
                   AND c.status = 'active'";

        $params = [':grade' => $grade, ':class_id' => $classId];

        if ($allowed !== null) {
            // Built from integers this service just read out of the
            // database, never from the request.
            $sql .= ' AND c.id IN (' . implode(',', array_map('intval', $allowed)) . ')';
        }

        $sql .= ' ORDER BY i.name, c.class_name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $r): array => [
                'id'             => (int) $r['id'],
                'class_name'     => (string) $r['class_name'],
                'institute_name' => $r['institute_name'] !== null ? (string) $r['institute_name'] : null,
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    /** The grade a class is in, as it is written -- "Grade 10", not 10. */
    public function gradeOf(int $classId): ?string
    {
        $stmt = $this->db->prepare("SELECT grade FROM classes WHERE id = :id");
        $stmt->execute([':id' => $classId]);
        $grade = $stmt->fetchColumn();

        return $grade === false ? null : (string) $grade;
    }

    /** A stamp that marks the rows of one share as one thing. */
    public function newBatch(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Was "share with the whole grade" actually asked for?
     *
     * An unticked checkbox posts nothing, so the answer is read from
     * the tick alone and never assumed.
     */
    public function requested(array $post): bool
    {
        return ($post['share_grade'] ?? '') === '1';
    }

    /**
     * How many classes a batch reaches, for the line shown on the list.
     */
    public function batchSize(string $table, string $batch): int
    {
        if (!in_array($table, ['recordings', 'class_materials'], true)) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE shared_batch = :b");
        $stmt->execute([':b' => $batch]);

        return (int) $stmt->fetchColumn();
    }
}
