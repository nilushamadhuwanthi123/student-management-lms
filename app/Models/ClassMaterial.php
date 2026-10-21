<?php

declare(strict_types=1);

/**
 * Lesson material attached to a class: Zoom recordings, reading
 * material, videos and plain links.
 *
 * Reading is deliberately split in two. `forTeacher()` returns
 * everything including drafts; `forStudent()` returns only what a
 * student is entitled to see. Keeping them apart means a student
 * listing cannot accidentally inherit a teacher's wider query by
 * someone adding a parameter later.
 */
class ClassMaterial extends BaseModel
{
    protected string $table = 'class_materials';

    protected array $fillable = [
        'class_id',
        'title',
        'material_type',
        'description',
        'file_path',
        'original_name',
        'file_size_bytes',
        'external_url',
        'lesson_date',
        'duration_minutes',
        'month_year',
        'sort_order',
        'status',
        'uploaded_by',
    ];

    public const TYPES = ['recording', 'reading', 'video', 'link'];

    public const TYPE_LABELS = [
        'recording' => 'Zoom recording',
        'reading'   => 'Reading material',
        'video'     => 'Video',
        'link'      => 'Link',
    ];

    /**
     * Everything in a class, newest lesson first — the teacher's view.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forTeacher(int $classId, ?string $monthYear = null): array
    {
        $sql = "SELECT m.*, u.full_name AS uploaded_by_name,
                       (SELECT COUNT(*) FROM material_views v WHERE v.material_id = m.id) AS view_count
                  FROM class_materials m
                  LEFT JOIN users u ON u.id = m.uploaded_by
                 WHERE m.class_id = :class_id
                   AND m.status <> 'archived'";

        $params = [':class_id' => $classId];

        if ($monthYear !== null) {
            $sql .= " AND m.month_year = :month_year";
            $params[':month_year'] = $monthYear;
        }

        $sql .= " ORDER BY m.month_year DESC, m.sort_order ASC, m.lesson_date DESC, m.id DESC";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * What a student may see in a class for one month.
     *
     * Drafts are excluded here rather than filtered in the view, so a
     * template that forgets to check cannot leak an unfinished upload.
     * The payment gate is NOT applied here — that is ContentAccessService's
     * job, and the controller asks it before calling this at all.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $classId, string $monthYear, int $studentId): array
    {
        $sql = "SELECT m.id, m.class_id, m.title, m.material_type, m.description,
                       m.file_path, m.original_name, m.file_size_bytes, m.external_url,
                       m.lesson_date, m.duration_minutes, m.month_year, m.created_at,
                       v.viewed_at, v.view_count
                  FROM class_materials m
                  LEFT JOIN material_views v
                         ON v.material_id = m.id
                        AND v.student_id = :student_id
                 WHERE m.class_id = :class_id
                   AND m.month_year = :month_year
                   AND m.status = 'published'
                 ORDER BY m.sort_order ASC, m.lesson_date DESC, m.id DESC";

        return $this->query($sql, [
            ':class_id'   => $classId,
            ':month_year' => $monthYear,
            ':student_id' => $studentId,
        ])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Which months a class has published material for.
     *
     * Drives the month switcher, so a student is never offered a month
     * that would open onto an empty shelf.
     *
     * @return array<int, string>
     */
    public function publishedMonths(int $classId): array
    {
        $sql = "SELECT DISTINCT month_year
                  FROM class_materials
                 WHERE class_id = :class_id
                   AND status = 'published'
                 ORDER BY month_year DESC";

        return $this->query($sql, [':class_id' => $classId])->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Record that a student opened something.
     *
     * Upserted on (material_id, student_id): the first open is what the
     * engagement measure counts, and later opens only bump the counter.
     */
    public function recordView(int $materialId, int $studentId): void
    {
        $sql = "INSERT INTO material_views (material_id, student_id)
                VALUES (:material_id, :student_id)
                ON DUPLICATE KEY UPDATE
                    view_count = view_count + 1,
                    viewed_at  = CURRENT_TIMESTAMP";

        $this->query($sql, [':material_id' => $materialId, ':student_id' => $studentId]);
    }

    /**
     * Counts by type for a class, for the teacher's summary strip.
     *
     * @return array<string, int>
     */
    public function countsByType(int $classId): array
    {
        $sql = "SELECT material_type, COUNT(*) AS n
                  FROM class_materials
                 WHERE class_id = :class_id
                   AND status = 'published'
                 GROUP BY material_type";

        $counts = array_fill_keys(self::TYPES, 0);

        foreach ($this->query($sql, [':class_id' => $classId])->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['material_type']] = (int) $row['n'];
        }

        return $counts;
    }
}
