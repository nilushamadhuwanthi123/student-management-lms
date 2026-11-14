<?php

declare(strict_types=1);

namespace App\Middleware;

class SecurityHeaders
{
    /**
     * Apply security headers to the response.
     */
    public static function apply(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        $csp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data: https://img.youtube.com; "
            . "connect-src 'self'; "
            // The contact page embeds a Google map per branch. Without
            // this, frame-src falls back to default-src 'self' and every
            // map renders as an empty white box.
            . "frame-src https://www.google.com https://maps.google.com; "
            . "frame-ancestors 'self'";
        header('Content-Security-Policy: ' . $csp);

        // Add HSTS header if connection is over HTTPS
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
