<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AssistantService;
use PDO;

/**
 * The endpoint behind the chat bubble.
 *
 * Who the answers are about is decided here, from the session, and
 * nowhere else: a student is asking about themselves, a parent about the
 * child on their parents row. The question text never selects a student.
 */
class AssistantController extends BaseController
{
    /** Questions per person per minute. Generous for a person, a wall for a script. */
    private const RATE_LIMIT = 20;
    private const MAX_LENGTH = 300;

    public function ask(): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();

        $userType = (string) session_get('user_type');
        $askerId = (int) session_get('user_id');

        $studentId = match ($userType) {
            'student' => $askerId,
            'parent'  => $this->childOf($askerId),
            default   => null,
        };

        if ($studentId === null) {
            $this->json(['ok' => false, 'answer' => 'The assistant is for students and parents.'], 403);
        }

        if (!$this->withinRateLimit()) {
            $this->json(['ok' => false, 'answer' => "That's a lot of questions at once -- give it a minute and try again."], 429);
        }

        $question = trim((string) ($_POST['question'] ?? ''));
        $question = mb_substr($question, 0, self::MAX_LENGTH, 'UTF-8');

        $reply = (new AssistantService())->ask($question, $studentId, $userType);

        $this->log($userType, $askerId, $studentId, $question, $reply);

        $this->json(['ok' => true] + $reply);
    }

    private function childOf(int $parentId): ?int
    {
        $stmt = $this->db->prepare('SELECT student_id FROM parents WHERE id = :id AND status = \'active\' LIMIT 1');
        $stmt->execute([':id' => $parentId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * A simple per-session window. Not a defence against a determined
     * attacker -- that is the web server's job -- but it stops a stuck
     * key or a looping script from filling the log.
     */
    private function withinRateLimit(): bool
    {
        $now = time();
        $window = $_SESSION['_assistant_window'] ?? [];
        $window = array_values(array_filter($window, static fn (int $t): bool => $t > $now - 60));

        if (count($window) >= self::RATE_LIMIT) {
            $_SESSION['_assistant_window'] = $window;
            return false;
        }

        $window[] = $now;
        $_SESSION['_assistant_window'] = $window;

        return true;
    }

    /**
     * @param array<string, mixed> $reply
     */
    private function log(string $askerType, int $askerId, int $studentId, string $question, array $reply): void
    {
        if ($question === '') {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO assistant_log (asker_type, asker_id, student_id, question, intent, answered)
                 VALUES (:t, :a, :s, :q, :i, :ok)'
            );
            $stmt->execute([
                ':t'  => $askerType,
                ':a'  => $askerId,
                ':s'  => $studentId,
                ':q'  => $question,
                ':i'  => (string) $reply['intent'],
                ':ok' => $reply['answered'] ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            // The log is for improving the assistant; failing to write it
            // must never cost the student their answer.
            error_log('assistant_log write failed: ' . $e->getMessage());
        }
    }
}
