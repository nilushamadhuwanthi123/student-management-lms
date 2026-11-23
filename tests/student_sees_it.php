<?php

declare(strict_types=1);

/**
 * Does what the teacher posts actually reach the students, and can
 * they do anything with it?
 *
 * Every other test here checks the teacher's side: the form saves, the
 * row is written, the list shows it. None answers the question the
 * teacher actually asks, which is whether the class can see it -- and
 * in this system that is not a given, because content is gated on the
 * month's fee. A quiz published to a class whose fees have not been
 * generated is invisible to every student in it, and nothing on the
 * teacher's side says so.
 *
 * The gate is not the same for every kind of thing, on purpose:
 *
 *   material, quizzes   hidden entirely until the fee is settled
 *   recordings, homework listed with a lock, so the student knows the
 *                        work exists, but cannot be opened or sat
 *
 * So the test posts one of each as the teacher, then looks at the
 * class through each enrolled student's own session and checks both
 * halves: that a settled student sees and can open everything, and
 * that an unsettled one is stopped at the right point rather than a
 * different one.
 *
 * To prove the settled case without inventing a payment, it puts one
 * enrolment on a free card for the length of the run and puts the old
 * value back afterwards. Students sitting on a forced password change
 * are reported and skipped: they see nothing because the system is
 * holding them at the password screen, which is correct.
 *
 * Signs in by writing session files, so no password is read or
 * changed, and deletes everything it posted. Development databases
 * only: between publishing and cleaning up, the class is really told.
 *
 * Usage:  php tests/student_sees_it.php [classId]
 */
require dirname(__DIR__) . '/bin/_bootstrap.php';
require_once dirname(__DIR__) . "/config/session.php";

use App\Services\ContentAccessService;

$base = 'http://127.0.0.1/student-management-lms';
$ua   = 'LMS-student-visibility/1.0';
$db   = Database::connect();

// ------------------------------------------------------------- helpers

function sessionFor(array $who): string
{
    $sid = 'see' . bin2hex(random_bytes(10));
    $data = [
        'user_id'            => (int) $who['id'],
        'user_name'          => (string) $who['name'],
        'user_role'          => (string) $who['role'],
        'user_status'        => 'active',
        'user_institute_id'  => null,
        'user_display_id'    => $who['display'] ?? null,
        'user_type'          => (string) $who['type'],
        'credential_version' => (int) $who['cv'],
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

function dropSession(string $sid): void
{
    $dir = session_save_path() ?: sys_get_temp_dir();
    @unlink(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid);
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
    $hl = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $code, 'body' => substr($raw, $hl)];
}

function csrf(string $body): string
{
    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $m);

    return $m[1] ?? '';
}

// --------------------------------------------------------------- setup

$classId = (int) ($argv[1] ?? 0);

if ($classId === 0) {
    $classId = (int) $db->query(
        "SELECT c.id
           FROM classes c
           JOIN enrollments e ON e.class_id = c.id AND e.enrollment_status = 'active'
          WHERE c.status = 'active' AND c.teacher_id IS NOT NULL
       GROUP BY c.id
       ORDER BY COUNT(*) DESC
          LIMIT 1"
    )->fetchColumn();
}

if ($classId === 0) {
    echo "No active class with students. Nothing to test.\n";
    exit(0);
}

$st = $db->prepare("SELECT id, class_name, teacher_id FROM classes WHERE id = :i");
$st->execute([':i' => $classId]);
$class = $st->fetch(PDO::FETCH_ASSOC);

$st = $db->prepare("SELECT id, full_name, COALESCE(credential_version,1) cv FROM users WHERE id = :i");
$st->execute([':i' => (int) $class['teacher_id']]);
$t = $st->fetch(PDO::FETCH_ASSOC);

$teacherSid = sessionFor([
    'id' => $t['id'], 'name' => $t['full_name'], 'role' => 'teacher',
    'type' => 'user', 'cv' => $t['cv'], 'display' => null,
]);

$st = $db->prepare(
    "SELECT s.id, s.full_name, s.student_id_display, COALESCE(s.credential_version,1) cv
       FROM students s
       JOIN enrollments e ON e.student_id = s.id
      WHERE e.class_id = :c AND e.enrollment_status = 'active' AND s.status = 'active'
   ORDER BY s.id"
);
$st->execute([':c' => $classId]);
$students = $st->fetchAll(PDO::FETCH_ASSOC);

$month = ContentAccessService::normaliseMonth();
$stamp = date('His');

echo "Posting to {$class['class_name']} (class #{$classId}), then looking through "
   . count($students) . " students' own sessions\n";
echo str_repeat('=', 74), "\n\n";

// ------------------------------------------------- post one of each

$titles = [
    'material'  => "ZZ visibility material {$stamp}",
    'recording' => "ZZ visibility recording {$stamp}",
    'quiz'      => "ZZ visibility quiz {$stamp}",
    'homework'  => "ZZ visibility homework {$stamp}",
];

$page = req("{$base}/classes/{$classId}/materials/create?type=reading", $teacherSid, $ua);
req("{$base}/classes/{$classId}/materials/create", $teacherSid, $ua, [
    '_csrf_token' => csrf($page['body']),
    'title' => $titles['material'], 'material_type' => 'reading',
    'external_url' => 'https://example.com/zz-notes',
    'month_year' => date('Y-m'), 'status' => 'published',
]);

$page = req("{$base}/classes/{$classId}/recordings/create", $teacherSid, $ua);
req("{$base}/classes/{$classId}/recordings/create", $teacherSid, $ua, [
    '_csrf_token' => csrf($page['body']),
    'title' => $titles['recording'], 'video_url' => 'https://example.com/zz-video',
    'month_year' => date('Y-m'), 'status' => 'published',
]);

$page = req("{$base}/classes/{$classId}/quizzes/create", $teacherSid, $ua);
req("{$base}/classes/{$classId}/quizzes/create", $teacherSid, $ua, [
    '_csrf_token' => csrf($page['body']),
    'title' => $titles['quiz'],
    'opens_at' => date('Y-m-d\TH:i', strtotime('-1 hour')),
    'closes_at' => date('Y-m-d\TH:i', strtotime('+7 days')),
    'month_year' => date('Y-m'),
]);
$st = $db->prepare("SELECT id FROM quizzes WHERE title = :t");
$st->execute([':t' => $titles['quiz']]);
$quizId = (int) $st->fetchColumn();

if ($quizId > 0) {
    $show = req("{$base}/classes/{$classId}/quizzes/{$quizId}", $teacherSid, $ua);
    req("{$base}/classes/{$classId}/quizzes/{$quizId}/questions", $teacherSid, $ua, [
        '_csrf_token' => csrf($show['body']),
        'question_type' => 'mcq', 'question_text' => 'Which gas do plants take in?',
        'marks' => '2', 'option_a' => 'Oxygen', 'option_b' => 'Carbon dioxide',
        'option_c' => 'Nitrogen', 'option_d' => 'Hydrogen', 'correct_option' => 'b',
    ]);
    $show = req("{$base}/classes/{$classId}/quizzes/{$quizId}", $teacherSid, $ua);
    req("{$base}/classes/{$classId}/quizzes/{$quizId}/publish", $teacherSid, $ua, [
        '_csrf_token' => csrf($show['body']), 'quiz_status' => 'published',
    ]);
}

$page = req("{$base}/classes/{$classId}/assignments/create", $teacherSid, $ua);
req("{$base}/classes/{$classId}/assignments/create", $teacherSid, $ua, [
    '_csrf_token' => csrf($page['body']),
    'title' => $titles['homework'],
    'instructions' => 'Answer the three questions in your book.',
    'due_at' => date('Y-m-d\TH:i', strtotime('+7 days')),
    'total_marks' => '10', 'action' => 'publish',
]);

// What actually got written, so a posting failure is not reported as a
// visibility failure.
$written = [];
foreach ([
    'material'  => ['class_materials', 'title', 'status'],
    'recording' => ['recordings', 'title', 'status'],
    'quiz'      => ['quizzes', 'title', 'quiz_status'],
    'homework'  => ['assignments', 'title', 'status'],
] as $what => [$table, $col, $statusCol]) {
    $q = $db->prepare("SELECT {$statusCol} FROM {$table} WHERE {$col} = :t LIMIT 1");
    $q->execute([':t' => $titles[$what]]);
    $written[$what] = $q->fetchColumn() ?: null;
}

echo "Posted by the teacher\n";
foreach ($written as $what => $status) {
    printf("  %-10s %s\n", $what, $status === null ? 'NOT SAVED' : "saved, {$status}");
}
echo "\n";

// ------------------------------------------- look through their eyes

$access = new ContentAccessService();
$problems = 0;
$skipped = [];

// One student is put on a free card for the length of the run, so the
// settled case is proved without inventing a payment. The old value
// goes back in the cleanup below.
$freeCardStudent = null;
$freeCardWas = null;
foreach ($students as $s) {
    $q = $db->prepare("SELECT must_change_password FROM students WHERE id = :i");
    $q->execute([':i' => (int) $s['id']]);
    if ((int) $q->fetchColumn() === 1) {
        continue;                       // held at the password screen
    }
    $q = $db->prepare("SELECT scholarship_type FROM enrollments WHERE student_id = :s AND class_id = :c AND enrollment_status = 'active' LIMIT 1");
    $q->execute([':s' => (int) $s['id'], ':c' => $classId]);
    $freeCardWas = $q->fetchColumn();
    $freeCardStudent = (int) $s['id'];
    $db->prepare("UPDATE enrollments SET scholarship_type = 'free_card' WHERE student_id = :s AND class_id = :c AND enrollment_status = 'active'")
       ->execute([':s' => $freeCardStudent, ':c' => $classId]);
    break;
}

// What the gate is meant to do, per kind of thing. "listed" means the
// student sees it in the list whether or not the fee is settled; the
// lock is on opening it.
$rules = [
    'material'  => ['listed' => 'when_open'],
    'quiz'      => ['listed' => 'when_open'],
    'recording' => ['listed' => 'always'],
    'homework'  => ['listed' => 'always'],
];

foreach ($students as $s) {
    $q = $db->prepare("SELECT must_change_password FROM students WHERE id = :i");
    $q->execute([':i' => (int) $s['id']]);
    if ((int) $q->fetchColumn() === 1) {
        $skipped[] = $s['student_id_display'];
        continue;
    }

    $gate = $access->gateStatus((int) $s['id'], $classId, $month);
    $open = (bool) $gate['allowed'];

    $sid = sessionFor([
        'id' => $s['id'], 'name' => $s['full_name'], 'role' => 'student',
        'type' => 'student', 'cv' => $s['cv'], 'display' => $s['student_id_display'],
    ]);

    $detail = req("{$base}/my/classes/{$classId}", $sid, $ua);
    $recs   = req("{$base}/my/recordings", $sid, $ua);
    $hw     = req("{$base}/my/assignments", $sid, $ua);

    $sees = [
        'material'  => str_contains($detail['body'], $titles['material']),
        'quiz'      => str_contains($detail['body'], $titles['quiz']),
        'recording' => str_contains($recs['body'], $titles['recording'])
                       || str_contains($detail['body'], $titles['recording']),
        'homework'  => str_contains($hw['body'], $titles['homework']),
    ];

    printf(
        "%-30s %s\n",
        $s['student_id_display'] . ' ' . mb_substr((string) $s['full_name'], 0, 16),
        $open
            ? 'fee settled (' . $gate['reason'] . ') -- content open'
            : 'locked: ' . $gate['reason']
    );

    foreach ($rules as $what => $rule) {
        $expected = $rule['listed'] === 'always' ? true : $open;
        $ok = $sees[$what] === $expected;
        if (!$ok) {
            $problems++;
        }
        printf(
            "    %-10s %-12s %s\n",
            $what,
            $sees[$what] ? 'listed' : 'not listed',
            $ok ? '' : ($expected ? '<-- SHOULD BE LISTED AND IS NOT' : '<-- SHOWN WHEN IT SHOULD NOT BE')
        );
    }

    // Opening is where the lock belongs, so check the doors too.
    if ($quizId > 0) {
        $sit = req("{$base}/my/quizzes/{$quizId}/sit", $sid, $ua);
        $canSit = $sit['status'] === 200 && str_contains($sit['body'], 'plants take in');
        $ok = $canSit === $open;
        if (!$ok) {
            $problems++;
        }
        printf(
            "    %-10s %-12s %s\n",
            'sit quiz',
            $canSit ? 'can sit it' : 'cannot sit',
            $ok ? '' : ($open ? '<-- SETTLED STUDENT CANNOT SIT IT' : '<-- LOCKED STUDENT CAN SIT IT')
        );
    }

    $q = $db->prepare("SELECT id FROM recordings WHERE title = :t LIMIT 1");
    $q->execute([':t' => $titles['recording']]);
    $recId = (int) $q->fetchColumn();
    if ($recId > 0) {
        // Both outcomes are a redirect: to the video when it opens,
        // back to the list when the lock holds. Where it went is the
        // only thing that tells them apart.
        $ch = curl_init("{$base}/my/recordings/{$recId}/open");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => $ua,
            CURLOPT_COOKIE => 'STUDENT_MGMT_SESSID=' . $sid,
        ]);
        $raw = (string) curl_exec($ch);
        curl_close($ch);
        preg_match('/^Location:\s*(.+)$/mi', $raw, $loc);
        $went = trim($loc[1] ?? '');
        $opened = $went !== '' && !str_contains($went, '/my/recordings');

        $ok = $opened === $open;
        if (!$ok) {
            $problems++;
        }
        printf(
            "    %-10s %-12s %s\n",
            'open video',
            $opened ? 'opens' : 'blocked',
            $ok ? '' : ($open ? '<-- SETTLED STUDENT CANNOT OPEN IT' : '<-- LOCKED STUDENT CAN OPEN IT')
        );
    }

    echo "\n";
    dropSession($sid);
}

if ($skipped !== []) {
    echo 'Skipped, held at the forced password change: ' . implode(', ', $skipped) . "\n\n";
}

// Put the free card back the way it was.
if ($freeCardStudent !== null) {
    if ($freeCardWas === null || $freeCardWas === false) {
        $db->prepare("UPDATE enrollments SET scholarship_type = NULL WHERE student_id = :s AND class_id = :c AND enrollment_status = 'active'")
           ->execute([':s' => $freeCardStudent, ':c' => $classId]);
    } else {
        $db->prepare("UPDATE enrollments SET scholarship_type = :v WHERE student_id = :s AND class_id = :c AND enrollment_status = 'active'")
           ->execute([':v' => $freeCardWas, ':s' => $freeCardStudent, ':c' => $classId]);
    }
}

// ------------------------------------------------------------ cleanup

if ($quizId > 0) {
    $db->prepare("DELETE FROM quiz_attempts WHERE quiz_id = :i")->execute([':i' => $quizId]);
    $db->prepare("DELETE FROM quiz_questions WHERE quiz_id = :i")->execute([':i' => $quizId]);
    $db->prepare("DELETE FROM notifications WHERE related_entity_type = 'quiz_published' AND related_entity_id = :i")->execute([':i' => $quizId]);
    $db->prepare("DELETE FROM reminder_dispatches WHERE source_type = 'quiz_published' AND source_id = :i")->execute([':i' => $quizId]);
    $db->prepare("DELETE FROM quizzes WHERE id = :i")->execute([':i' => $quizId]);
}
foreach ([
    ['class_materials', $titles['material']],
    ['recordings', $titles['recording']],
    ['assignments', $titles['homework']],
] as [$table, $title]) {
    $db->prepare("DELETE FROM {$table} WHERE title = :t")->execute([':t' => $title]);
}

dropSession($teacherSid);

echo str_repeat('=', 74), "\n";
if ($problems === 0) {
    echo "Everything the teacher posted behaved as the fee gate says it should.\n";
    exit(0);
}

printf("%d thing%s did not match what the fee gate allows.\n", $problems, $problems === 1 ? '' : 's');
exit(1);
