<?php

declare(strict_types=1);

/**
 * One place that says which roles may open which page.
 *
 * This exists because of a whole class of bug that no lint pass and no
 * page test catches: a button or sidebar link shown to a role whose
 * route then refuses it. The click lands back on the dashboard with a
 * flash nobody reads, and the user concludes the feature is missing.
 *
 * The rule: a view asks can() before drawing a control, using the same
 * answer the route enforces. When a route's role list changes, it
 * changes here too, and every view follows.
 *
 * tests/role_crawl.php walks the site as each role and reports any link
 * that bounces, so a drift between this map and the routes is caught
 * rather than shipped.
 */

/** @var array<string, list<string>> */
const PERMISSION_MAP = [
    // Classes: the office creates and schedules them; teachers only teach.
    'classes.manage'      => ['super_admin', 'admin', 'coordinator'],
    // Student records: the office keeps them. Staff included -- they do
    // the front-desk data entry.
    'students.manage'     => ['super_admin', 'admin', 'coordinator', 'staff'],
    // Branches: creating or deleting one is the owner's call alone.
    'institutes.create'   => ['super_admin'],
    'institutes.edit'     => ['super_admin', 'admin'],
    // Money and staff reports: not a branch coordinator's business.
    'reports.money'       => ['super_admin', 'admin'],
    'reports.users'       => ['super_admin', 'admin'],
    // Homework: whoever teaches the class, plus the admins above them.
    'assignments.view'    => ['super_admin', 'admin', 'coordinator', 'teacher'],
    'assignments.set'     => ['super_admin', 'admin', 'teacher'],
    // Syllabus topics are written by the people who teach them.
    'topics.manage'       => ['super_admin', 'admin', 'teacher'],
    // The enrolment funnel: coordinator and up, not front-desk staff.
    'enrolment.review'    => ['super_admin', 'admin', 'coordinator'],
];

/**
 * May the signed-in role do this?
 *
 * An unknown key answers false rather than true: a typo must hide a
 * control, never expose one.
 */
function can(string $what, ?string $role = null): bool
{
    $role ??= (string) (function_exists('session_get') ? session_get('user_role') : '');
    return in_array($role, PERMISSION_MAP[$what] ?? [], true);
}
