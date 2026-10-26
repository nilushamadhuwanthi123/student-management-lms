<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Simple learning goals. A goal has a type, a target and a period; its
 * progress is always worked out from the records (never typed in), and it
 * becomes "achieved" the first time the target is met, or "missed" when the
 * period ends without it.
 *
 *   quiz_average  average quiz % in the period          (target: %)
 *   attendance    classes attended / classes marked     (target: %)
 *   homework      homework handed in / homework due     (target: %)
 *   topic         marks in one Science unit             (target: %)
 */
final class GoalService
{
    public const TYPES = ['quiz_average', 'attendance', 'homework', 'topic'];
    public const MAX_ACTIVE_OWN = 3;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * Create a goal. Returns the new id, or an error message.
     *
     * @param array{goal_type: string, target_value: mixed, period_end: string, class_id?: mixed, topic_id?: mixed, note?: string} $in
     */
    public function create(int $studentId, array $in, string $byType, ?int $byId): int|string
    {
        $type = (string) ($in['goal_type'] ?? '');
        $target = (float) ($in['target_value'] ?? 0);
        $end = (string) ($in['period_end'] ?? '');
        $endDate = \DateTime::createFromFormat('Y-m-d', $end);
        if (!in_array($type, self::TYPES, true)) {
            return t('Choose a type of goal.');
        }
        if ($target < 1 || $target > 100) {
            return t('The target must be between 1 and 100%.');
        }
        if (!$endDate || $endDate->format('Y-m-d') !== $end || $end < date('Y-m-d') || $end > date('Y-m-d', strtotime('+6 months'))) {
            return t('Choose an end date between today and six months from now.');
        }
        $topicId = (int) ($in['topic_id'] ?? 0) ?: null;
        if ($type === 'topic' && $topicId === null) {
            return t('Choose the Science unit for this goal.');
        }
        if ($topicId !== null && !$this->topicFitsStudent($studentId, $topicId)) {
            return t('That unit is not part of your grade.');
        }
        $classId = (int) ($in['class_id'] ?? 0) ?: null;
        if ($classId !== null && !$this->isEnrolled($studentId, $classId)) {
            return t('That class is not one of yours.');
        }
        if ($byType === 'student') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM student_goals WHERE student_id = :s AND status = 'active' AND set_by_type = 'student'");
            $stmt->execute([':s' => $studentId]);
            if ((int) $stmt->fetchColumn() >= self::MAX_ACTIVE_OWN) {
                return t('You can have up to {n} goals of your own at a time.', ['n' => self::MAX_ACTIVE_OWN]);
            }
        }

        $this->db->prepare(
            "INSERT INTO student_goals (student_id, goal_type, target_value, class_id, topic_id, period_start, period_end, set_by_type, set_by_id, note)
             VALUES (:s, :t, :v, :c, :tp, :ps, :pe, :bt, :bi, :n)"
        )->execute([
            ':s' => $studentId, ':t' => $type, ':v' => $target, ':c' => $classId, ':tp' => $topicId,
            ':ps' => date('Y-m-d'), ':pe' => $end, ':bt' => $byType, ':bi' => $byId,
            ':n' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 255) ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Remove a goal (the student may remove only their own; teachers theirs). */
    public function remove(int $goalId, int $studentId, string $byType, ?int $byId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE student_goals SET status = 'removed'
              WHERE id = :g AND student_id = :s AND status = 'active' AND set_by_type = :bt" . ($byType === 'user' ? " AND set_by_id = :bi" : '')
        );
        $params = [':g' => $goalId, ':s' => $studentId, ':bt' => $byType];
        if ($byType === 'user') {
            $params[':bi'] = $byId;
        }
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * What the goal form can offer this student: their active classes and
     * the Science units of those classes' grades.
     *
     * @return array{classes: list<array{id: int, class_name: string}>, topics: list<array<string, mixed>>}
     */
    public function formOptions(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade FROM enrollments e JOIN classes c ON c.id = e.class_id
              WHERE e.student_id = :s AND e.enrollment_status = 'active' ORDER BY c.grade, c.class_name"
        );
        $stmt->execute([':s' => $studentId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grades = [];
        foreach ($classes as $c) {
            $n = TopicService::gradeNumber((string) $c['grade']);
            if ($n !== null) {
                $grades[$n] = true;
            }
        }
        $topics = [];
        if ($grades !== []) {
            try {
                $in = implode(',', array_map('intval', array_keys($grades)));
                $topics = $this->db->query(
                    "SELECT id, grade, unit_no, title, title_si FROM topics WHERE grade IN ($in) AND status = 'active' ORDER BY grade, unit_no, title"
                )->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('Topics unavailable: ' . $e->getMessage()); // before migration 014
            }
        }
        return [
            'classes' => array_map(static fn ($c) => ['id' => (int) $c['id'], 'class_name' => (string) $c['class_name']], $classes),
            'topics'  => $topics,
        ];
    }

    /**
     * Goals with live progress; updates achieved / missed as it goes.
     *
     * @return list<array<string, mixed>>
     */
    public function forStudent(int $studentId, bool $includeFinished = true): array
    {
        $stmt = $this->db->prepare(
            "SELECT g.*, t.title AS topic_title, t.title_si AS topic_title_si, t.unit_no AS topic_unit,
                    c.class_name, u.full_name AS set_by_name
               FROM student_goals g
          LEFT JOIN topics t ON t.id = g.topic_id
          LEFT JOIN classes c ON c.id = g.class_id
          LEFT JOIN users u ON g.set_by_type = 'user' AND u.id = g.set_by_id
              WHERE g.student_id = :s AND g.status " . ($includeFinished ? "<> 'removed'" : "= 'active'") . "
           ORDER BY g.status = 'active' DESC, g.period_end, g.id"
        );
        $stmt->execute([':s' => $studentId]);
        $goals = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
            [$value, $basis] = $this->progress($g);
            $g['value'] = $value;
            $g['basis'] = $basis; // how many records the value is based on
            $g['pct_of_target'] = $value === null ? 0 : (int) min(100, round($value / max(1, (float) $g['target_value']) * 100));

            if ($g['status'] === 'active') {
                if ($value !== null && $basis > 0 && $value >= (float) $g['target_value'] && $this->canDecideEarly($g)) {
                    $this->setStatus((int) $g['id'], 'achieved');
                    $g['status'] = 'achieved';
                    $g['achieved_at'] = date('Y-m-d H:i:s');
                } elseif ($g['period_end'] < date('Y-m-d')) {
                    $final = $value !== null && $basis > 0 && $value >= (float) $g['target_value'];
                    $this->setStatus((int) $g['id'], $final ? 'achieved' : 'missed');
                    $g['status'] = $final ? 'achieved' : 'missed';
                }
            }
            $goals[] = $g;
        }
        return $goals;
    }

    /**
     * Averages over a period can still fall, so attendance and homework goals
     * are decided at the end of the period; quiz and unit goals can be met as
     * soon as the average reaches the target with real marks behind it.
     */
    private function canDecideEarly(array $g): bool
    {
        return in_array($g['goal_type'], ['quiz_average', 'topic'], true) && (int) ($g['basis'] ?? 0) >= 3;
    }

    private function setStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE student_goals SET status = :st, achieved_at = IF(:st2 = 'achieved', NOW(), achieved_at) WHERE id = :id AND status = 'active'")
            ->execute([':st' => $status, ':st2' => $status, ':id' => $id]);
    }

    /** @return array{0: ?float, 1: int} [value %, number of records behind it] */
    private function progress(array $g): array
    {
        $s = (int) $g['student_id'];
        $from = $g['period_start'] . ' 00:00:00';
        $to = $g['period_end'] . ' 23:59:59';
        $class = $g['class_id'] !== null ? (int) $g['class_id'] : null;

        switch ($g['goal_type']) {
            case 'quiz_average':
                $stmt = $this->db->prepare(
                    "SELECT AVG(a.percentage), COUNT(*) FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
                      WHERE a.student_id = :s AND a.percentage IS NOT NULL AND a.submitted_at BETWEEN :f AND :t
                        AND a.attempt_status IN ('submitted','auto_submitted','graded')" . ($class ? " AND q.class_id = :c" : '')
                );
                $stmt->execute(array_filter([':s' => $s, ':f' => $from, ':t' => $to, ':c' => $class], static fn ($v) => $v !== null));
                [$avg, $n] = $stmt->fetch(PDO::FETCH_NUM);
                return [$avg !== null ? round((float) $avg, 1) : null, (int) $n];

            case 'attendance':
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*), SUM(status IN ('present','late')) FROM attendance
                      WHERE student_id = :s AND class_id IS NOT NULL AND date BETWEEN :f AND :t" . ($class ? " AND class_id = :c" : '')
                );
                $stmt->execute(array_filter([':s' => $s, ':f' => $g['period_start'], ':t' => $g['period_end'], ':c' => $class], static fn ($v) => $v !== null));
                [$total, $came] = $stmt->fetch(PDO::FETCH_NUM);
                return [(int) $total > 0 ? round((int) $came / (int) $total * 100, 1) : null, (int) $total];

            case 'homework':
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*), SUM(x.done) FROM (
                     SELECT a.id, a.class_id, a.due_at, EXISTS (SELECT 1 FROM assignment_submissions sub WHERE sub.assignment_id = a.id AND sub.student_id = :s1) AS done
                       FROM assignments a JOIN enrollments e ON e.class_id = a.class_id AND e.student_id = :s2 AND e.enrollment_status = 'active'
                      WHERE a.status = 'published' AND a.due_at BETWEEN :f AND :t) x
                      WHERE (x.due_at <= NOW() OR x.done = 1)" . ($class ? " AND x.class_id = :c" : '')
                );
                $stmt->execute(array_filter([':s1' => $s, ':s2' => $s, ':f' => $from, ':t' => $to, ':c' => $class], static fn ($v) => $v !== null));
                [$due, $done] = $stmt->fetch(PDO::FETCH_NUM);
                return [(int) $due > 0 ? round((int) $done / (int) $due * 100, 1) : null, (int) $due];

            case 'topic':
                $topic = (int) $g['topic_id'];
                $stmt = $this->db->prepare(
                    "SELECT SUM(COALESCE(ans.marks_awarded, 0)), SUM(qq.marks), COUNT(DISTINCT a.id)
                       FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id JOIN quiz_questions qq ON qq.quiz_id = a.quiz_id
                  LEFT JOIN quiz_answers ans ON ans.attempt_id = a.id AND ans.question_id = qq.id
                      WHERE a.student_id = :s AND a.attempt_status IN ('submitted','auto_submitted','graded')
                        AND a.submitted_at BETWEEN :f AND :t AND COALESCE(qq.topic_id, q.topic_id) = :tp
                        AND (ans.id IS NULL OR ans.marks_awarded IS NOT NULL)"
                );
                $stmt->execute([':s' => $s, ':f' => $from, ':t' => $to, ':tp' => $topic]);
                [$got, $max, $n] = $stmt->fetch(PDO::FETCH_NUM);
                $stmt = $this->db->prepare(
                    "SELECT SUM(sub.marks), SUM(a.total_marks), COUNT(*) FROM assignment_submissions sub JOIN assignments a ON a.id = sub.assignment_id
                      WHERE sub.student_id = :s AND sub.marks IS NOT NULL AND a.topic_id = :tp AND sub.submitted_at BETWEEN :f AND :t"
                );
                $stmt->execute([':s' => $s, ':f' => $from, ':t' => $to, ':tp' => $topic]);
                [$hg, $hm, $hn] = $stmt->fetch(PDO::FETCH_NUM);
                $max = (float) $max + (float) $hm;
                return [$max > 0 ? round(((float) $got + (float) $hg) / $max * 100, 1) : null, (int) $n + (int) $hn];
        }
        return [null, 0];
    }

    private function isEnrolled(int $studentId, int $classId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM enrollments WHERE student_id = :s AND class_id = :c AND enrollment_status = 'active'");
        $stmt->execute([':s' => $studentId, ':c' => $classId]);
        return (bool) $stmt->fetchColumn();
    }

    /** A unit fits when it belongs to the grade of one of the student's active classes. */
    private function topicFitsStudent(int $studentId, int $topicId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT t.grade, t.subject, c.grade AS class_grade, c.subject AS class_subject
               FROM topics t JOIN enrollments e ON e.student_id = :s AND e.enrollment_status = 'active'
               JOIN classes c ON c.id = e.class_id WHERE t.id = :t"
        );
        $stmt->execute([':s' => $studentId, ':t' => $topicId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (TopicService::gradeNumber($r['class_grade']) === (int) $r['grade'] && $r['class_subject'] === $r['subject']) {
                return true;
            }
        }
        return false;
    }
}
