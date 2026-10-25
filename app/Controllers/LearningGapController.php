<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\TopicService;

/**
 * Where a class is struggling, from its own marks.
 *
 * Quiz marks count per question (a question's topic, or else its quiz's
 * topic), using each student's latest submitted attempt; unanswered
 * questions count as zero and answers still waiting to be marked are left
 * out. Marked homework counts under the homework's topic. Nothing here is a
 * prediction: every number is a sum of marks the teacher can check.
 */
class LearningGapController extends BaseController
{
    public const WEAK_BELOW = 50.0;

    /** GET /classes/{classId}/learning-gaps */
    public function show(string $classId): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_TEACHER]);

        $classId = (int) $classId;
        $class = (new \ClassModel())->find($classId);
        if (!$class || !BranchScope::current()->allowsClass($classId)) {
            http_response_code(403);
            $this->flash('error', t('That class is not in your branch.'));
            $this->redirect('/teaching');
        }

        $students = $this->enrolled($classId);
        $topicsById = [];
        foreach ((new TopicService($this->db))->forClass($classId) as $t) {
            $topicsById[(int) $t['id']] = $t;
        }

        // ---- marks per topic per student -------------------------------
        $scores = []; // topicKey => studentId => [got, max]
        $add = static function (array &$scores, $topicId, int $sid, float $got, float $max): void {
            $k = $topicId === null ? 0 : (int) $topicId;
            $scores[$k][$sid][0] = ($scores[$k][$sid][0] ?? 0) + $got;
            $scores[$k][$sid][1] = ($scores[$k][$sid][1] ?? 0) + $max;
        };

        $latest = "SELECT MAX(x.id) FROM quiz_attempts x
                    WHERE x.quiz_id = a.quiz_id AND x.student_id = a.student_id
                      AND x.attempt_status IN ('submitted','auto_submitted','graded')";
        $stmt = $this->db->prepare(
            "SELECT COALESCE(qq.topic_id, q.topic_id) AS topic_id, a.student_id,
                    SUM(COALESCE(ans.marks_awarded, 0)) AS got, SUM(qq.marks) AS max
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN quiz_questions qq ON qq.quiz_id = a.quiz_id
          LEFT JOIN quiz_answers ans ON ans.attempt_id = a.id AND ans.question_id = qq.id
              WHERE q.class_id = :c
                AND a.attempt_status IN ('submitted','auto_submitted','graded')
                AND a.id = ($latest)
                AND (ans.id IS NULL OR ans.marks_awarded IS NOT NULL)
           GROUP BY COALESCE(qq.topic_id, q.topic_id), a.student_id"
        );
        $stmt->execute([':c' => $classId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $add($scores, $r['topic_id'], (int) $r['student_id'], (float) $r['got'], (float) $r['max']);
        }

        $stmt = $this->db->prepare(
            "SELECT asg.topic_id, sub.student_id, SUM(sub.marks) AS got, SUM(asg.total_marks) AS max
               FROM assignment_submissions sub JOIN assignments asg ON asg.id = sub.assignment_id
              WHERE asg.class_id = :c AND sub.marks IS NOT NULL
           GROUP BY asg.topic_id, sub.student_id"
        );
        $stmt->execute([':c' => $classId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $add($scores, $r['topic_id'], (int) $r['student_id'], (float) $r['got'], (float) $r['max']);
        }

        // ---- summarise --------------------------------------------------
        $names = [];
        foreach ($students as $s) {
            $names[(int) $s['id']] = $s;
        }
        $topicRows = [];
        $overall = []; // sid => [got, max]
        foreach ($scores as $key => $bySid) {
            $got = 0.0; $max = 0.0; $weak = [];
            foreach ($bySid as $sid => [$g, $m]) {
                if (!isset($names[$sid]) || $m <= 0) {
                    continue; // left the class since
                }
                $got += $g; $max += $m;
                $overall[$sid][0] = ($overall[$sid][0] ?? 0) + $g;
                $overall[$sid][1] = ($overall[$sid][1] ?? 0) + $m;
                $pct = $g / $m * 100;
                if ($pct < self::WEAK_BELOW) {
                    $weak[] = ['id' => $sid, 'name' => $names[$sid]['full_name'], 'pct' => $pct];
                }
            }
            if ($max <= 0) {
                continue;
            }
            usort($weak, static fn ($a, $b): int => $a['pct'] <=> $b['pct']);
            $topic = $key === 0 ? null : ($topicsById[$key] ?? $this->topic($key));
            $topicRows[] = [
                'label'    => $topic === null ? t('Not tagged with a topic') : TopicService::label($topic),
                'untagged' => $key === 0,
                'pct'      => $got / $max * 100,
                'assessed' => count(array_filter(array_keys($bySid), static fn ($sid) => isset($names[$sid]))),
                'weak'     => $weak,
            ];
        }
        usort($topicRows, static fn ($a, $b): int => [$a['untagged'], $a['pct']] <=> [$b['untagged'], $b['pct']]);

        $studentRows = [];
        foreach ($students as $s) {
            $sid = (int) $s['id'];
            $weakest = null;
            foreach ($scores as $key => $bySid) {
                if ($key === 0 || !isset($bySid[$sid]) || $bySid[$sid][1] <= 0) {
                    continue;
                }
                $p = $bySid[$sid][0] / $bySid[$sid][1] * 100;
                if ($weakest === null || $p < $weakest['pct']) {
                    $topic = $topicsById[$key] ?? $this->topic($key);
                    $weakest = ['label' => $topic ? TopicService::label($topic) : '', 'pct' => $p];
                }
            }
            $studentRows[] = [
                'id'      => $sid,
                'name'    => $s['full_name'],
                'code'    => $s['student_id_display'],
                'pct'     => isset($overall[$sid]) && $overall[$sid][1] > 0 ? $overall[$sid][0] / $overall[$sid][1] * 100 : null,
                'weakest' => $weakest,
            ];
        }
        usort($studentRows, static fn ($a, $b): int => [$a['pct'] === null, $a['pct']] <=> [$b['pct'] === null, $b['pct']]);

        // ---- questions most often wrong ---------------------------------
        $stmt = $this->db->prepare(
            "SELECT qq.id, qq.question_number, qq.question_text, q.title AS quiz_title, q.id AS quiz_id,
                    COUNT(*) AS answered, SUM(ans.marks_awarded >= qq.marks) AS full_marks
               FROM quiz_answers ans
               JOIN quiz_attempts a ON a.id = ans.attempt_id
               JOIN quiz_questions qq ON qq.id = ans.question_id
               JOIN quizzes q ON q.id = qq.quiz_id
              WHERE q.class_id = :c AND ans.marks_awarded IS NOT NULL
                AND a.attempt_status IN ('submitted','auto_submitted','graded') AND a.id = ($latest)
           GROUP BY qq.id
             HAVING answered >= 3
           ORDER BY SUM(ans.marks_awarded >= qq.marks) / COUNT(*) ASC, COUNT(*) DESC
              LIMIT 10"
        );
        $stmt->execute([':c' => $classId]);
        $missed = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // ---- work not done ----------------------------------------------
        $stmt = $this->db->prepare(
            "SELECT q.id, q.title, q.closes_at,
                    (SELECT COUNT(*) FROM enrollments e JOIN students s ON s.id = e.student_id
                      WHERE e.class_id = q.class_id AND e.enrollment_status = 'active' AND s.status = 'active'
                        AND NOT EXISTS (SELECT 1 FROM quiz_attempts x WHERE x.quiz_id = q.id AND x.student_id = e.student_id
                                          AND x.attempt_status IN ('submitted','auto_submitted','graded'))) AS missing
               FROM quizzes q
              WHERE q.class_id = :c AND q.quiz_status IN ('published','closed') AND q.closes_at < NOW()
           ORDER BY q.closes_at DESC LIMIT 5"
        );
        $stmt->execute([':c' => $classId]);
        $quizGaps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "SELECT asg.id, asg.title, asg.due_at,
                    (SELECT COUNT(*) FROM enrollments e JOIN students s ON s.id = e.student_id
                      WHERE e.class_id = asg.class_id AND e.enrollment_status = 'active' AND s.status = 'active'
                        AND NOT EXISTS (SELECT 1 FROM assignment_submissions x WHERE x.assignment_id = asg.id AND x.student_id = e.student_id)) AS missing
               FROM assignments asg
              WHERE asg.class_id = :c AND asg.status = 'published' AND asg.due_at < NOW()
           ORDER BY asg.due_at DESC LIMIT 5"
        );
        $stmt->execute([':c' => $classId]);
        $homeworkGaps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('insights/learning-gaps', [
            'pageTitle'    => t('Learning gaps'),
            'class'        => $class,
            'topicRows'    => $topicRows,
            'studentRows'  => $studentRows,
            'missed'       => $missed,
            'quizGaps'     => $quizGaps,
            'homeworkGaps' => $homeworkGaps,
            'hasTopics'    => $topicsById !== [],
            'weakBelow'    => self::WEAK_BELOW,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function enrolled(int $classId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.full_name, s.student_id_display FROM enrollments e JOIN students s ON s.id = e.student_id
              WHERE e.class_id = :c AND e.enrollment_status = 'active' AND s.status = 'active' ORDER BY s.full_name"
        );
        $stmt->execute([':c' => $classId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null  (an archived topic still labels old work) */
    private function topic(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM topics WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
