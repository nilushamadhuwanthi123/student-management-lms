<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * The English / Sinhala switch.
 *
 * Open to everyone, signed in or not, because the login page is where a
 * student first needs it. Changing language changes nothing but how the
 * page reads, so a plain link is enough -- there is nothing here worth
 * a CSRF token.
 */
class LanguageController extends BaseController
{
    public function switch(string $code): void
    {
        lang_set($code);

        // Back to the page the switch was pressed on -- but only if that
        // page is on this site. Referer comes from the client, and
        // following it blindly would make this an open redirect.
        $back = function_exists('csrfSafeReturnUrl')
            ? csrfSafeReturnUrl($_SERVER['HTTP_REFERER'] ?? '')
            : null;

        $this->redirect($back ?? '/');
    }
}
