<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ChatBotService;

/**
 * The endpoint behind the public FAQ bot's chat bubble.
 *
 * Open to guests on purpose -- it only ever answers from public data
 * (CMS text, branches, fees, the FAQ), the same things any visitor can
 * already read on the site. No account data is ever touched here.
 */
class ChatBotController extends BaseController
{
    /** Questions per visitor per minute. Generous for a person, a wall for a script. */
    private const RATE_LIMIT = 15;
    private const MAX_LENGTH = 300;

    public function ask(): void
    {
        verifyCsrfToken();

        if (!$this->withinRateLimit()) {
            $this->json(['ok' => false, 'answer' => t("That's a lot of questions at once -- give it a minute and try again.")], 429);
        }

        $question = trim((string) ($_POST['question'] ?? ''));
        $question = mb_substr($question, 0, self::MAX_LENGTH, 'UTF-8');

        $reply = (new ChatBotService())->ask($question);

        $this->json(['ok' => true] + $reply);
    }

    private function withinRateLimit(): bool
    {
        $now = time();
        $window = $_SESSION['_chatbot_window'] ?? [];
        $window = array_values(array_filter($window, static fn (int $t): bool => $t > $now - 60));

        if (count($window) >= self::RATE_LIMIT) {
            $_SESSION['_chatbot_window'] = $window;
            return false;
        }

        $window[] = $now;
        $_SESSION['_chatbot_window'] = $window;

        return true;
    }
}
