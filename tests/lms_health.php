<?php

declare(strict_types=1);

/**
 * Does the teaching side actually hold together?
 *
 * Every page in this system returns 200 even when the data behind it
 * is wrong, because a page with nothing on it still renders. So a
 * crawl proves almost nothing about a learning system. These are the
 * conditions that make a student's portal quietly useless -- enrolled
 * in nothing, a quiz with no questions, a recording whose file is
 * gone -- each of which looks fine from the office and broken from
 * the family's side.
 *
 * Read-only. Run it any time:
 *
 *   php tests/lms_health.php
 *
 * Exit code is the number of problems found, capped at 250.
 */

require __DIR__ . '/../bin/_bootstrap.php';

$db = Database::connect();

/** @var list<array{0:string,1:string,2:string}> label, why it matters, SQL returning rows */
$checks = [
    [
        'Active students enrolled in no class',
        'They can log in and the portal has nothing in it -- no materials, no quizzes, no marks.',
        "SELECT s.id, s.student_id_display AS ref, s.full_name AS detail
           FROM students s
          WHERE s.status = 'active'
            AND NOT EXISTS (SELECT 1 FROM enrollments e
                             WHERE e.student_id = s.id AND e.enrollment_status = 'active')",
    ],
    [
        'Active classes with no teacher',
        'Nobody can post materials or mark work for them, and the public page names no teacher.',
        "SELECT c.id, c.class_name AS ref, COALESCE(i.name, '-') AS detail
           FROM classes c
      LEFT JOIN institutes i ON i.id = c.institute_id
          WHERE c.status = 'active' AND (c.teacher_id IS NULL OR c.teacher_id = 0)",
    ],
    [
        'Published quizzes with no questions',
        'A student opens it and sits an empty paper.',
        "SELECT q.id, q.title AS ref, c.class_name AS detail
           FROM quizzes q
      LEFT JOIN classes c ON c.id = q.class_id
          WHERE q.quiz_status = 'published'
            AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id)",
    ],
    [
        'Quiz questions with no correct answer',
        'The question can never be marked right, so every student loses that mark.',
        "SELECT qq.id, LEFT(qq.question_text, 60) AS ref, q.title AS detail
           FROM quiz_questions qq
      LEFT JOIN quizzes q ON q.id = qq.quiz_id
          WHERE (qq.question_type IN ('mcq','true_false')
                 AND (qq.correct_option IS NULL OR qq.correct_option = ''))
             OR (qq.question_type = 'short_answer'
                 AND (qq.correct_text IS NULL OR qq.correct_text = ''))",
    ],
    [
        'Active classes at an inactive or non-public branch',
        'The class is running but nobody can find it on the website.',
        "SELECT c.id, c.class_name AS ref, i.name AS detail
           FROM classes c
           JOIN institutes i ON i.id = c.institute_id
          WHERE c.status = 'active' AND (i.status <> 'active' OR i.is_public = 0)",
    ],
    [
        'Students with no parent or guardian recorded',
        'Nobody to call about fees or absence, and no parent portal for that child.',
        "SELECT s.id, s.student_id_display AS ref, s.full_name AS detail
           FROM students s
          WHERE s.status = 'active'
            AND NOT EXISTS (SELECT 1 FROM parents p WHERE p.student_id = s.id)",
    ],
    [
        'Class enrolments pointing at a class that no longer exists',
        'Leftover rows that make enrolment counts wrong.',
        "SELECT e.id, CAST(e.student_id AS CHAR) AS ref, CAST(e.class_id AS CHAR) AS detail
           FROM enrollments e
          WHERE NOT EXISTS (SELECT 1 FROM classes c WHERE c.id = e.class_id)
             OR NOT EXISTS (SELECT 1 FROM students s WHERE s.id = e.student_id)",
    ],
    [
        'Payments recorded against a student who no longer exists',
        'Money sitting against nobody -- it shows in totals and in no one report.',
        "SELECT p.id, p.month_year AS ref, CAST(p.student_id AS CHAR) AS detail
           FROM payments p
          WHERE NOT EXISTS (SELECT 1 FROM students s WHERE s.id = p.student_id)",
    ],
    [
        'Published quizzes whose marks do not add up',
        'The paper is out of one number and the questions add to another, so every percentage shown is wrong.',
        "SELECT q.id, q.title AS ref,
                CONCAT('paper says ', q.total_marks, ', questions add to ',
                       COALESCE((SELECT SUM(qq.marks) FROM quiz_questions qq WHERE qq.quiz_id = q.id), 0)) AS detail
           FROM quizzes q
          WHERE q.quiz_status = 'published'
            AND q.total_marks > 0
            AND ABS(q.total_marks - COALESCE((SELECT SUM(qq.marks) FROM quiz_questions qq WHERE qq.quiz_id = q.id), 0)) > 0.01",
    ],
    [
        'Published quizzes that close before they open',
        'The window never exists, so no student can ever sit it.',
        "SELECT q.id, q.title AS ref, CONCAT(q.opens_at, ' -> ', q.closes_at) AS detail
           FROM quizzes q
          WHERE q.quiz_status = 'published' AND q.closes_at <= q.opens_at",
    ],
    [
        'Enrolments in a class that is no longer active',
        'The student still counts as enrolled in something that is not running.',
        "SELECT e.id, s.student_id_display AS ref, c.class_name AS detail
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           JOIN classes c ON c.id = e.class_id
          WHERE e.enrollment_status = 'active' AND c.status <> 'active'",
    ],
];

$total = 0;
$fileChecks = [];

echo "Teaching-side health\n";
echo str_repeat('=', 64) . "\n\n";

foreach ($checks as [$label, $why, $sql]) {
    try {
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // A check against a table this install does not have is not a
        // failure of the install: say so and move on.
        printf("  ?  %-52s (cannot check: %s)\n\n", $label, $e->getMessage());
        continue;
    }

    $n = count($rows);
    $total += $n;

    printf("  %s %-52s %d\n", $n === 0 ? 'OK ' : '>> ', $label, $n);
    if ($n > 0) {
        echo "       " . $why . "\n";
        foreach (array_slice($rows, 0, 8) as $r) {
            printf("       - #%s  %s  %s\n", $r['id'] ?? '?', $r['ref'] ?? '', $r['detail'] ?? '');
        }
        if ($n > 8) {
            printf("       ... and %d more\n", $n - 8);
        }
    }
    echo "\n";
}

// Files are checked separately: the row can be perfect and the file
// still missing from disk, which is the failure a student actually
// meets -- a download that 404s.
$fileTables = [
    'class_materials' => ['file_path', 'title'],
    'past_papers'     => ['paper_path', 'title'],
    // The marking scheme is a second file on the same row, and it is
    // the one that goes missing: it is uploaded later, often by
    // somebody else, after the paper has already been published.
    'past_papers (marking schemes)' => ['answers_path', 'title', 'past_papers'],
];
foreach ($fileTables as $label => $spec) {
    $col     = $spec[0];
    $nameCol = $spec[1];
    $table   = $spec[2] ?? $label;
    try {
        $rows = $db->query("SELECT id, {$col} AS path, {$nameCol} AS name FROM {$table}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Say so rather than skipping in silence: a check that quietly
        // does not run is worse than no check, because the clean report
        // reads as "nothing wrong here".
        printf("  ?  %-52s (cannot check: %s)\n\n", "Missing files in {$label}", $e->getMessage());
        continue;
    }
    $missing = [];
    foreach ($rows as $r) {
        $path = (string) $r['path'];
        if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
            continue;   // a link, not a file we host
        }
        $full = BASE_PATH . '/public' . (str_starts_with($path, '/') ? $path : '/' . $path);
        if (!is_file($full)) {
            $missing[] = $r;
        }
    }
    $total += count($missing);
    printf("  %s %-52s %d\n", $missing === [] ? 'OK ' : '>> ', "Missing files in {$label}", count($missing));
    foreach (array_slice($missing, 0, 8) as $r) {
        printf("       - #%s  %s  %s\n", $r['id'], $r['name'], $r['path']);
    }
    echo "\n";
}

echo str_repeat('=', 64) . "\n";
printf("Total problems: %d\n", $total);

exit(min(250, $total));
