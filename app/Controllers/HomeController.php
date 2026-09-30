<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * HomeController — extends PublicController so the route definitions
 * in web.php (which reference HomeController) resolve correctly.
 *
 * Methods about(), features(), contact(), contactSubmit() are all
 * inherited from PublicController. Only index() needs a mapping.
 */
class HomeController extends PublicController
{
    /**
     * Landing page (route: GET /).
     */
    public function index(): void
    {
        $this->home();
    }
}
