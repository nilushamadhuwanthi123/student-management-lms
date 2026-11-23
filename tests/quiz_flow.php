<?php

declare(strict_types=1);

/**
 * Set a quiz, the whole way through, as a real teacher.
 *
 * Reading the three screens tells you they exist. It does not tell you
 * that a quiz saved on the first one can be filled on the second and
 * published on the third, which is the only question that matters the
 * morning of a handover. So this walks the flow over HTTP the way the
 * teacher does -- real form, real CSRF token, real redirects -- and
 * checks the guard rail too: an empty quiz must refuse to publish,
 * because a paper with no questions marks every sitting zero out of
 * zero.
 *
 * It signs in by writing a session file for an existing teacher, so no
 * password is read or changed, and it deletes the quiz, its question
 * and the notifications it raised before it finishes. Run it against a
 * development database all the same: between publishing and cleaning
 * up, the class really is told about a quiz.
 *
 * Usage:  php tests/quiz_flow.php
 */

require dirname(__DIR__) . '/bin/_bootstrap.php';
require_once dirname(__DIR__) . '/config/session.php';

$base = 'http://127.0.0.1/student-management-lms';
$ua   = 'LMS-quiz-selftest/1.0';
$db   = Database::connect();

$u = $db->query("SELECT id, COALESCE(credential_version,1) cv, full_name FROM users WHERE role='teacher' AND status='active' ORDER BY id LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);

$sid = 'qztest' . bin2hex(random_bytes(8));
$sess = [
    'user_id' => (int) $u['id'], 'user_name' => $u['full_name'], 'user_role' => 'teacher',
    'user_status' => 'active', 'user_institute_id' => null, 'user_display_id' => null,
    'user_type' => 'user', 'credential_version' => (int) $u['cv'],
    'last_activity' => time(), 'lang' => 'en',
];
$enc = '';
foreach ($sess as $k => $v) { $enc .= $k . '|' . serialize($v); }
$dir = session_save_path() ?: sys_get_temp_dir();
file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid, $enc);

function req(string $url, string $sid, string $ua, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => $ua,
        CURLOPT_COOKIE => 'STUDENT_MGMT_SESSID=' . $sid . '; lms_lang=en',
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
function token(string $body): string {
    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $m);
    return $m[1] ?? '';
}
function say(string $step, bool $ok, string $detail = ''): void {
    printf("  %-46s %s%s\n", $step, $ok ? 'OK' : 'FAILED', $detail !== '' ? '  ' . $detail : '');
}

$q = $db->prepare("SELECT id FROM classes WHERE teacher_id = :u1 UNION SELECT class_id FROM user_classes WHERE user_id = :u2");
$q->execute([':u1' => (int) $u['id'], ':u2' => (int) $u['id']]);
$classId = (int) ($q->fetchAll(PDO::FETCH_COLUMN)[0] ?? 0);

echo "Setting a quiz, as teacher #{$u['id']}, in class #{$classId}\n\n";

// 1. the form
$page = req("{$base}/classes/{$classId}/quizzes/create", $sid, $ua);
say('1. the "Set a quiz" form opens', $page['status'] === 200 && str_contains($page['body'], '<form'));

$title = 'ZZ quiz self-test ' . date('His');
$opens  = date('Y-m-d\TH:i', strtotime('+1 hour'));
$closes = date('Y-m-d\TH:i', strtotime('+3 hours'));

// 2. create it
$r = req("{$base}/classes/{$classId}/quizzes/create", $sid, $ua, [
    '_csrf_token' => token($page['body']),
    'title' => $title, 'opens_at' => $opens, 'closes_at' => $closes,
    'time_limit_minutes' => '20', 'month_year' => date('Y-m'),
]);
$st = $db->prepare("SELECT id, quiz_status FROM quizzes WHERE title = :t");
$st->execute([':t' => $title]);
$quiz = $st->fetch(PDO::FETCH_ASSOC);
say('2. the quiz is saved as a draft', $quiz !== false && $quiz['quiz_status'] === 'draft',
    $quiz ? "quiz #{$quiz['id']}" : 'nothing written');
if ($quiz === false) { exit(1); }
$quizId = (int) $quiz['id'];

// 3. the list says what it is waiting on
$list = req("{$base}/classes/{$classId}/quizzes", $sid, $ua);
say('3. the list says "Add questions"',
    str_contains($list['body'], 'Add questions') && str_contains($list['body'], 'No questions yet'));

// 4. publishing is refused while it is empty
$show = req("{$base}/classes/{$classId}/quizzes/{$quizId}", $sid, $ua);
req("{$base}/classes/{$classId}/quizzes/{$quizId}/publish", $sid, $ua,
    ['_csrf_token' => token($show['body']), 'quiz_status' => 'published']);
$st = $db->prepare("SELECT quiz_status FROM quizzes WHERE id = :i");
$st->execute([':i' => $quizId]);
say('4. an empty quiz refuses to publish', $st->fetchColumn() === 'draft');

// 5. add a question
$show = req("{$base}/classes/{$classId}/quizzes/{$quizId}", $sid, $ua);
req("{$base}/classes/{$classId}/quizzes/{$quizId}/questions", $sid, $ua, [
    '_csrf_token' => token($show['body']),
    'question_type' => 'mcq', 'question_text' => 'Which gas do plants take in?',
    'marks' => '2',
    'option_a' => 'Oxygen', 'option_b' => 'Carbon dioxide',
    'option_c' => 'Nitrogen', 'option_d' => 'Hydrogen',
    'correct_option' => 'b',
]);
$st = $db->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = :i");
$st->execute([':i' => $quizId]);
$qCount = (int) $st->fetchColumn();
say('5. the question is saved', $qCount === 1, "{$qCount} question");

// 6. the list now says publish
$list = req("{$base}/classes/{$classId}/quizzes", $sid, $ua);
say('6. the list now says "Publish it"', str_contains($list['body'], 'Publish it'));

// 7. publish
$show = req("{$base}/classes/{$classId}/quizzes/{$quizId}", $sid, $ua);
req("{$base}/classes/{$classId}/quizzes/{$quizId}/publish", $sid, $ua,
    ['_csrf_token' => token($show['body']), 'quiz_status' => 'published']);
$st = $db->prepare("SELECT quiz_status FROM quizzes WHERE id = :i");
$st->execute([':i' => $quizId]);
say('7. it publishes once it has a question', $st->fetchColumn() === 'published');

// 8. the class was told
$st = $db->prepare("SELECT COUNT(*) FROM notifications WHERE related_entity_type = 'quiz_published' AND related_entity_id = :i");
$st->execute([':i' => $quizId]);
$notified = (int) $st->fetchColumn();
say('8. students and guardians were notified', $notified > 0, "{$notified} people");

// 9. clean up: this test leaves nothing behind
$db->prepare("DELETE FROM notifications WHERE related_entity_type = 'quiz_published' AND related_entity_id = :i")->execute([':i' => $quizId]);
$db->prepare("DELETE FROM reminder_dispatches WHERE source_type = 'quiz_published' AND source_id = :i")->execute([':i' => $quizId]);
$db->prepare("DELETE FROM quiz_questions WHERE quiz_id = :i")->execute([':i' => $quizId]);
$db->prepare("DELETE FROM quizzes WHERE id = :i")->execute([':i' => $quizId]);
$st = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE id = :i");
$st->execute([':i' => $quizId]);
say('9. the test removed its own data', (int) $st->fetchColumn() === 0);

@unlink(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid);
