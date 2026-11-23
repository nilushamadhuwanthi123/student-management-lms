<?php

declare(strict_types=1);

/**
 * The whole chain, once, with real HTTP: teacher posts, student sees,
 * student sits, the system marks, the reports move.
 *
 * Every link in it was built and reviewed separately, which is exactly
 * how a chain ends up broken in the middle: the quiz saves, the
 * student's page never lists it, and nobody finds out until a class of
 * thirty says "miss, there's nothing there". So this walks it end to
 * end as two different people and checks the answers are marked right
 * AND wrong correctly -- a marker that calls everything correct passes
 * a careless test and fails a class.
 *
 * It signs both people in by writing session files, so no password is
 * read or changed, and it deletes the quiz, the material, the attempt,
 * the answers and the notifications before it finishes.
 *
 * Development databases only: between publishing and cleaning up, the
 * class really is told about a quiz.
 *
 * Usage:  php tests/student_journey.php
 */

$root = dirname(__DIR__);
require $root . '/bin/_bootstrap.php';
require_once $root . '/config/session.php';

$base = 'http://127.0.0.1/student-management-lms';
$ua   = 'LMS-student-journey/1.0';
$db   = Database::connect();

$pass = 0;
$fail = 0;
$stepNo = 0;

/** Numbered by the order it is called, so inserting a step cannot
 *  leave the list reading 18, 19, 20, 26. */
function say(string $step, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $stepNo;
    $ok ? $pass++ : $fail++;
    $stepNo++;
    printf("  %-52s %s%s\n", $stepNo . '. ' . $step, $ok ? 'OK' : 'FAILED', $detail !== '' ? '   ' . $detail : '');
}

function session_for(PDO $db, array $who): string
{
    $sid = 'jrny' . bin2hex(random_bytes(8));
    $data = [
        'user_id'            => $who['id'],
        'user_name'          => $who['name'],
        'user_role'          => $who['role'],
        'user_status'        => 'active',
        'user_institute_id'  => null,
        'user_display_id'    => null,
        'user_type'          => $who['type'],
        'credential_version' => $who['cv'],
        'last_activity'      => time(),
        'lang'               => 'en',
    ];
    $enc = '';
    foreach ($data as $k => $v) {
        $enc .= $k . '|' . serialize($v);
    }
    $dir = session_save_path() ?: sys_get_temp_dir();
    file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid, $enc);

    return $sid;
}

function req(string $url, string $sid, string $ua, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_COOKIE         => 'STUDENT_MGMT_SESSID=' . $sid . '; lms_lang=en',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hl   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $code, 'body' => substr($raw, $hl)];
}

function token(string $body): string
{
    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $m);

    return $m[1] ?? '';
}

// ---------------------------------------------------------- who and where

// A class with an enrolled student whose fee for the month is settled.
// An unpaid student is locked out of content by design, so testing with
// one would prove nothing about whether posting works.
//
// The month comes from the data, not from today's date: a school with
// no fees recorded yet for the current month would otherwise fail this
// run for a reason that has nothing to do with the code.
$month = (string) ($db->query(
    "SELECT MAX(month_year) FROM payments WHERE payment_status = 'paid'"
)->fetchColumn() ?: date('Y-m-01'));

$row = $db->prepare(
    "SELECT c.id AS class_id, c.class_name, c.teacher_id, s.id AS student_id, s.full_name AS student_name,
            COALESCE(s.credential_version,1) AS student_cv
       FROM classes c
       JOIN enrollments e ON e.class_id = c.id AND e.enrollment_status = 'active'
       JOIN students s ON s.id = e.student_id AND s.status = 'active'
       JOIN payments p ON p.class_id = c.id AND p.student_id = s.id
                      AND p.month_year = :m AND p.payment_status = 'paid'
      WHERE c.status = 'active' AND c.teacher_id IS NOT NULL
      ORDER BY c.id
      LIMIT 1"
);
$row->execute([':m' => $month]);
$case = $row->fetch(PDO::FETCH_ASSOC);

if ($case === false) {
    echo "No class has an active student with a settled fee in any month, so the content\n";
    echo "gate would block everything and the run would prove nothing. Record one paid\n";
    echo "payment and run again.\n";
    exit(2);
}

$classId   = (int) $case['class_id'];
$studentId = (int) $case['student_id'];

$t = $db->prepare("SELECT id, full_name, COALESCE(credential_version,1) cv FROM users WHERE id = :i");
$t->execute([':i' => (int) $case['teacher_id']]);
$teacher = $t->fetch(PDO::FETCH_ASSOC);

$teacherSid = session_for($db, [
    'id' => (int) $teacher['id'], 'name' => $teacher['full_name'], 'role' => 'teacher',
    'type' => 'user', 'cv' => (int) $teacher['cv'],
]);
$studentSid = session_for($db, [
    'id' => $studentId, 'name' => $case['student_name'], 'role' => 'student',
    'type' => 'student', 'cv' => (int) $case['student_cv'],
]);

printf(
    "Class #%d %s\n  teacher %s\n  student %s (fee settled for %s)\n\n",
    $classId,
    $case['class_name'],
    $teacher['full_name'],
    $case['student_name'],
    date('F Y', strtotime($month))
);

$stamp        = date('His');
$materialName = "ZZ journey material {$stamp}";
$quizName     = "ZZ journey quiz {$stamp}";

$quizId = 0;
$attemptId = 0;
$materialId = 0;

try {
    // -------------------------------------------------- the teacher posts

    echo "The teacher posts\n";

    $page = req("{$base}/classes/{$classId}/materials/create?type=reading", $teacherSid, $ua);
    req("{$base}/classes/{$classId}/materials/create", $teacherSid, $ua, [
        '_csrf_token'   => token($page['body']),
        'title'         => $materialName,
        'material_type' => 'link',
        'external_url'  => 'https://example.com/journey',
        'month_year'    => date('Y-m', strtotime($month)),
        'status'        => 'published',
    ]);
    $st = $db->prepare("SELECT id, status FROM class_materials WHERE title = :t");
    $st->execute([':t' => $materialName]);
    $material = $st->fetch(PDO::FETCH_ASSOC);
    $materialId = $material ? (int) $material['id'] : 0;
    say('material is posted and published', $material !== false && $material['status'] === 'published');

    // A window that is open right now, so the student can sit it.
    $opens  = date('Y-m-d\TH:i', strtotime('-10 minutes'));
    $closes = date('Y-m-d\TH:i', strtotime('+2 hours'));

    $page = req("{$base}/classes/{$classId}/quizzes/create", $teacherSid, $ua);
    req("{$base}/classes/{$classId}/quizzes/create", $teacherSid, $ua, [
        '_csrf_token' => token($page['body']),
        'title'       => $quizName,
        'opens_at'    => $opens,
        'closes_at'   => $closes,
        'month_year'  => date('Y-m', strtotime($month)),
        // The form ships this ticked; an unticked box posts nothing and
        // the answers would stay hidden for ever.
        'show_answers_after' => '1',
    ]);
    say('the create form offers to show answers, ticked',
        (bool) preg_match('/name="show_answers_after"[^>]*checked/', $page['body'])
        || (bool) preg_match('/checked[^>]*name="show_answers_after"/', $page['body']));

    $st = $db->prepare("SELECT id FROM quizzes WHERE title = :t");
    $st->execute([':t' => $quizName]);
    $quizId = (int) ($st->fetchColumn() ?: 0);
    say('quiz is created', $quizId > 0, $quizId > 0 ? "quiz #{$quizId}" : '');

    if ($quizId === 0) {
        throw new RuntimeException('no quiz to carry on with');
    }

    // Three questions: one the student will get right, one wrong, one
    // typed. Marks differ so a wrong total cannot look right by luck.
    $questions = [
        ['question_type' => 'mcq', 'question_text' => 'Which gas do plants take in?', 'marks' => '2',
         'option_a' => 'Oxygen', 'option_b' => 'Carbon dioxide', 'option_c' => 'Nitrogen', 'option_d' => 'Hydrogen',
         'correct_option' => 'b', 'explanation' => 'Plants take in carbon dioxide and give out oxygen.'],
        ['question_type' => 'mcq', 'question_text' => 'Water boils at what temperature at sea level?', 'marks' => '3',
         'option_a' => '50 C', 'option_b' => '75 C', 'option_c' => '100 C', 'option_d' => '150 C',
         'correct_option' => 'c', 'explanation' => 'At sea level water boils at 100 degrees Celsius.'],
        ['question_type' => 'short_answer', 'question_text' => 'Name the gas we breathe out.', 'marks' => '1',
         'correct_text' => 'carbon dioxide', 'explanation' => 'We breathe out carbon dioxide.'],
    ];

    foreach ($questions as $q) {
        $show = req("{$base}/classes/{$classId}/quizzes/{$quizId}", $teacherSid, $ua);
        req("{$base}/classes/{$classId}/quizzes/{$quizId}/questions", $teacherSid, $ua,
            array_merge(['_csrf_token' => token($show['body'])], $q));
    }

    $st = $db->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = :i");
    $st->execute([':i' => $quizId]);
    $qCount = (int) $st->fetchColumn();
    say('three questions are saved', $qCount === 3, "{$qCount} saved");

    $st = $db->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = :i AND explanation IS NOT NULL AND explanation <> ''");
    $st->execute([':i' => $quizId]);
    say('each question keeps its marking note', (int) $st->fetchColumn() === 3);

    $show = req("{$base}/classes/{$classId}/quizzes/{$quizId}", $teacherSid, $ua);
    req("{$base}/classes/{$classId}/quizzes/{$quizId}/publish", $teacherSid, $ua,
        ['_csrf_token' => token($show['body']), 'quiz_status' => 'published']);
    $st = $db->prepare("SELECT quiz_status FROM quizzes WHERE id = :i");
    $st->execute([':i' => $quizId]);
    say('quiz is published', $st->fetchColumn() === 'published');

    // -------------------------------------------------- the student sees

    echo "\nThe student sees it\n";

    $detail = req("{$base}/my/classes/{$classId}?month=" . urlencode(date('Y-m', strtotime($month))), $studentSid, $ua);
    say('the class page opens for the student', $detail['status'] === 200);
    say('the material is listed', str_contains($detail['body'], $materialName));
    say('the quiz is listed', str_contains($detail['body'], $quizName));

    // ------------------------------------------------- the student sits it

    echo "\nThe student sits it\n";

    $sit = req("{$base}/my/quizzes/{$quizId}/sit", $studentSid, $ua);
    say('the paper opens', $sit['status'] === 200 && str_contains($sit['body'], 'Which gas'));

    $st = $db->prepare("SELECT id FROM quiz_attempts WHERE quiz_id = :q AND student_id = :s ORDER BY id DESC LIMIT 1");
    $st->execute([':q' => $quizId, ':s' => $studentId]);
    $attemptId = (int) ($st->fetchColumn() ?: 0);
    say('an attempt is started', $attemptId > 0);

    $st = $db->prepare("SELECT id, question_type, correct_option FROM quiz_questions WHERE quiz_id = :i ORDER BY question_number, id");
    $st->execute([':i' => $quizId]);
    $saved = $st->fetchAll(PDO::FETCH_ASSOC);

    // Deliberately: first right, second wrong, third typed correctly.
    $csrf = token($sit['body']);
    $answers = [
        ['question_id' => (int) $saved[0]['id'], 'selected_option' => 'b'],                    // right, 2 marks
        ['question_id' => (int) $saved[1]['id'], 'selected_option' => 'a'],                    // wrong, 0 of 3
        ['question_id' => (int) $saved[2]['id'], 'answer_text'     => 'carbon dioxide'],       // right, 1 mark
    ];
    foreach ($answers as $a) {
        req("{$base}/my/quizzes/answer", $studentSid, $ua,
            array_merge(['_csrf_token' => $csrf, 'attempt_id' => $attemptId], $a));
    }

    $st = $db->prepare("SELECT COUNT(*) FROM quiz_answers WHERE attempt_id = :i");
    $st->execute([':i' => $attemptId]);
    $answered = (int) $st->fetchColumn();
    say('the answers reach the server', $answered === 3, "{$answered} of 3 saved");

    req("{$base}/my/quizzes/submit", $studentSid, $ua,
        ['_csrf_token' => $csrf, 'attempt_id' => $attemptId]);

    $st = $db->prepare("SELECT attempt_status, score, total_marks, percentage, needs_marking FROM quiz_attempts WHERE id = :i");
    $st->execute([':i' => $attemptId]);
    $attempt = $st->fetch(PDO::FETCH_ASSOC);
    say('the paper is handed in', $attempt !== false && $attempt['attempt_status'] !== 'in_progress',
        $attempt ? (string) $attempt['attempt_status'] : '');

    // ------------------------------------------------- the system marks it

    echo "\nThe system marks it\n";

    $st = $db->prepare(
        "SELECT a.is_correct, a.marks_awarded, q.question_type, q.marks
           FROM quiz_answers a JOIN quiz_questions q ON q.id = a.question_id
          WHERE a.attempt_id = :i ORDER BY q.question_number, q.id"
    );
    $st->execute([':i' => $attemptId]);
    $marked = $st->fetchAll(PDO::FETCH_ASSOC);

    say('the right answer is marked right',
        isset($marked[0]) && (int) $marked[0]['is_correct'] === 1 && (float) $marked[0]['marks_awarded'] === 2.0,
        isset($marked[0]) ? "{$marked[0]['marks_awarded']} of 2" : '');

    say('the wrong answer is marked wrong, and scores nothing',
        isset($marked[1]) && (int) $marked[1]['is_correct'] === 0 && (float) $marked[1]['marks_awarded'] === 0.0,
        isset($marked[1]) ? "{$marked[1]['marks_awarded']} of 3" : '');

    say('the typed answer is marked right',
        isset($marked[2]) && (int) $marked[2]['is_correct'] === 1 && (float) $marked[2]['marks_awarded'] === 1.0,
        isset($marked[2]) ? "{$marked[2]['marks_awarded']} of 1" : '');

    $expected = 3.0;   // 2 + 0 + 1
    say('the total is right, not just the parts',
        $attempt !== false && (float) $attempt['score'] === $expected && (float) $attempt['total_marks'] === 6.0,
        $attempt ? "{$attempt['score']} of {$attempt['total_marks']}" : '');

    say('the percentage follows the marks',
        $attempt !== false && abs((float) $attempt['percentage'] - 50.0) < 0.51,
        $attempt ? "{$attempt['percentage']}%" : '');

    // ------------------------------------------- the student sees the result

    echo "\nThe student sees what went wrong\n";

    $result = req("{$base}/my/quizzes/{$attemptId}/result", $studentSid, $ua);
    say('the result page opens', $result['status'] === 200);

    // While the window is still open the key is deliberately withheld,
    // or the first student to finish becomes everyone else's answer
    // sheet. So check both halves: hidden now, shown once it closes.
    say('the key is withheld while the quiz is still open',
        !str_contains($result['body'], 'At sea level water boils')
        && str_contains($result['body'], 'once the quiz closes'));

    $db->prepare("UPDATE quizzes SET closes_at = :c WHERE id = :i")
       ->execute([':c' => date('Y-m-d H:i:s', strtotime('-1 minute')), ':i' => $quizId]);

    $result = req("{$base}/my/quizzes/{$attemptId}/result", $studentSid, $ua);
    say('once closed, the correct answer is shown for the one got wrong',
        str_contains($result['body'], '100 C'));
    say('once closed, the teacher\'s marking note is shown',
        str_contains($result['body'], 'At sea level water boils'));

    // ------------------------------------------------ the reports move

    echo "\nThe reports move\n";

    $progress = req("{$base}/classes/{$classId}/progress", $teacherSid, $ua);
    say('the class progress page counts the sitting',
        $progress['status'] === 200 && str_contains($progress['body'], (string) $case['student_name']));

    $charts = new App\Services\TeachingChartService($db);
    $participation = $charts->participation([$classId], $month);
    $found = false;
    foreach ($participation as $p) {
        if (str_contains((string) ($p['label'] ?? ''), $quizName) || (int) ($p['sat'] ?? 0) > 0) {
            $found = true;
            break;
        }
    }
    say('the participation chart counts it', $found);

    $marks = $charts->marksByGrade([$classId], $month);
    say('the marks chart has data to draw', $marks !== []);

    $home = req("{$base}/my/home", $studentSid, $ua);
    say('the student\'s own page opens after sitting', $home['status'] === 200);
} finally {
    // ------------------------------------------------------------ clean up

    echo "\nCleaning up\n";

    if ($attemptId > 0) {
        $db->prepare("DELETE FROM quiz_answers WHERE attempt_id = :i")->execute([':i' => $attemptId]);
        $db->prepare("DELETE FROM quiz_attempts WHERE id = :i")->execute([':i' => $attemptId]);
    }
    if ($quizId > 0) {
        $db->prepare("DELETE FROM notifications WHERE related_entity_type = 'quiz_published' AND related_entity_id = :i")->execute([':i' => $quizId]);
        $db->prepare("DELETE FROM reminder_dispatches WHERE source_type = 'quiz_published' AND source_id = :i")->execute([':i' => $quizId]);
        $db->prepare("DELETE FROM quiz_questions WHERE quiz_id = :i")->execute([':i' => $quizId]);
        $db->prepare("DELETE FROM quizzes WHERE id = :i")->execute([':i' => $quizId]);
    }
    if ($materialId > 0) {
        $db->prepare("DELETE FROM notifications WHERE related_entity_type = 'material_published' AND related_entity_id = :i")->execute([':i' => $materialId]);
        $db->prepare("DELETE FROM reminder_dispatches WHERE source_type = 'material_published' AND source_id = :i")->execute([':i' => $materialId]);
        $db->prepare("DELETE FROM material_views WHERE material_id = :i")->execute([':i' => $materialId]);
        $db->prepare("DELETE FROM class_materials WHERE id = :i")->execute([':i' => $materialId]);
    }

    $left = (int) $db->query("SELECT (SELECT COUNT(*) FROM quizzes WHERE title LIKE 'ZZ journey%')
                                   + (SELECT COUNT(*) FROM class_materials WHERE title LIKE 'ZZ journey%')")->fetchColumn();
    say('the test removed its own data', $left === 0);

    $dir = session_save_path() ?: sys_get_temp_dir();
    foreach ([$teacherSid, $studentSid] as $sid) {
        @unlink(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid);
    }

    printf("\n%d passed, %d failed.\n", $pass, $fail);
}

exit($fail > 0 ? 1 : 0);
