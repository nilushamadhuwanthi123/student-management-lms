<?php

declare(strict_types=1);

/**
 * CSRF Token Management
 *
 * Uses a per-session token with time-based expiry (not single-use),
 * so multi-tab usage works correctly.
 */

/** Token lifetime in seconds (default: 1 hour). */
define('CSRF_TOKEN_TTL', (int) env('CSRF_TOKEN_LIFETIME', '3600'));

/**
 * Generate a CSRF token and store it in the session with a timestamp.
 */
function generateCsrfToken(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['_csrf_token'] = $token;
    $_SESSION['_csrf_token_time'] = time();
    return $token;
}

/**
 * Get the current CSRF token, generating one if it doesn't exist or has expired.
 */
function getCsrfToken(): string
{
    $token = $_SESSION['_csrf_token'] ?? null;
    $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;

    // Regenerate if missing or expired
    if ($token === null || (time() - $tokenTime) > CSRF_TOKEN_TTL) {
        return generateCsrfToken();
    }

    return $token;
}

/**
 * Validate a CSRF token from a POST request.
 */
function validateCsrfToken(?string $token): bool
{
    if ($token === null || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;

    // Check if token has expired
    if ((time() - $tokenTime) > CSRF_TOKEN_TTL) {
        // Regenerate for next request
        generateCsrfToken();
        return false;
    }

    return hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * The page a refused form should go back to, or null if it is not safe.
 *
 * Only a same-host address is accepted. The Referer header is supplied
 * by the client, so trusting it as-is would turn this into an open
 * redirect: a forged form on another site could bounce someone off
 * this one to anywhere it liked.
 */
function csrfSafeReturnUrl(string $referer): ?string
{
    if ($referer === '') {
        return null;
    }

    $parts = parse_url($referer);
    $host = $parts['host'] ?? null;

    if ($host === null || !isset($_SERVER['HTTP_HOST'])) {
        return null;
    }

    $refererHost = strtolower($host . (isset($parts['port']) ? ':' . $parts['port'] : ''));

    if ($refererHost !== strtolower($_SERVER['HTTP_HOST'])) {
        return null;
    }

    return ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

/**
 * Validate the CSRF token from the current POST request.
 * A stale browser form is sent back to a fresh copy of its page;
 * anything else that fails is refused with 403.
 */
function verifyCsrfToken(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!validateCsrfToken($token)) {
            // An expired token on an ordinary form is nearly always a page
            // left open too long: the login page opened in a tab this
            // morning and submitted this evening. A bare 403 with no way
            // back strands the user, and pressing Back re-serves the same
            // stale form, so it fails again however many times they try.
            //
            // So a browser form is sent back to a fresh copy of the page it
            // came from, with the reason, and simply works on the next
            // submit. The protection is unchanged -- nothing in the stale
            // POST is acted on. Script calls (fetch with the header, or
            // asking for JSON) still get the 403, since a redirect would
            // be meaningless to them.
            $isScript = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
                || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

            $back = csrfSafeReturnUrl($_SERVER['HTTP_REFERER'] ?? '');

            if (!$isScript && $back !== null && function_exists('session_flash')) {
                session_flash('error', 'That page had been open for a while, so it has been refreshed. Please try again.');
                header('Location: ' . $back, true, 303);
                exit;
            }

            http_response_code(403);
            exit('Invalid or expired CSRF token. Please refresh the page and try again.');
        }
    }
}

/**
 * Return a hidden input field with the CSRF token.
 */
function csrfField(): string
{
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

/**
 * Return a meta tag with the CSRF token (for AJAX requests).
 */
function csrfMeta(): string
{
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<meta name="csrf-token" content="' . $token . '">';
}
