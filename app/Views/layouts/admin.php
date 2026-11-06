<?php
/**
 * Admin Layout
 *
 * Variables available:
 *   $pageTitle     - Page title
 *   $activePage    - Current sidebar nav item key
 *   $activeSubPage - Current sub-nav item key
 *   $breadcrumbs   - Array of [label, url] pairs
 *   $currentUser   - Authenticated user array
 *   $content       - Rendered page content
 *   $flashSuccess, $flashError, $flashWarning, $flashInfo - Flash messages
 */

$activePage    = $activePage ?? '';
$activeSubPage = $activeSubPage ?? '';
$currentUser   = $currentUser ?? [];
$userName      = htmlspecialchars($currentUser['full_name'] ?? $currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$userRole      = $currentUser['role'] ?? 'student';
$userRoleLabel = ucwords(str_replace('_', ' ', $userRole));
$notificationCount = $notificationCount ?? 0;
?>
<!DOCTYPE html>
<html lang="<?= lang_current() ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= te((string) ($pageTitle ?? 'EduManager')) ?> - EduManager</title>
    <?php if (function_exists('csrfMeta')): ?><?= csrfMeta() ?><?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(BASE_PATH . '/public/assets/css/style.css') ?: 1 ?>">
    <style>
        .navbar-admin {
            padding-left: calc(var(--sidebar-width) + 1.5rem);
        }
        @media (max-width: 1024px) {
            .navbar-admin { padding-left: 1rem; }
        }
        .navbar__right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: auto;
        }
        .navbar__breadcrumbs {
            flex: 1;
            min-width: 0;
        }
        .navbar__breadcrumbs .breadcrumbs {
            margin-bottom: 0;
        }
        .notification-bell {
            position: relative;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: var(--radius-sm);
            transition: color var(--transition), background var(--transition);
        }
        .notification-bell:hover {
            color: var(--text);
            background: var(--surface-sunken);
        }
        .notification-bell__count {
            position: absolute;
            top: -2px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            font-size: 0.625rem;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            color: #fff;
            background: var(--danger);
            border-radius: var(--radius-full);
        }
        .user-dropdown-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.375rem 0.5rem;
            border-radius: var(--radius-sm);
            transition: background var(--transition);
            color: var(--text);
            font-size: 0.875rem;
        }
        .user-dropdown-trigger:hover {
            background: var(--surface-sunken);
        }
        .user-dropdown-trigger__info {
            text-align: left;
            line-height: 1.3;
        }
        .user-dropdown-trigger__name {
            font-weight: 600;
            font-size: 0.8125rem;
        }
        .user-dropdown-trigger__role {
            font-size: 0.6875rem;
            color: var(--text-muted);
        }
        .theme-toggle-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.125rem;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: var(--radius-sm);
            transition: color var(--transition), background var(--transition);
        }
        .theme-toggle-btn:hover {
            color: var(--text);
            background: var(--surface-sunken);
        }
        .sidebar__sub-links {
            display: none;
            padding-left: 2.25rem;
        }
        .sidebar__sub-links.open {
            display: block;
        }
        .sidebar__link--has-sub {
            justify-content: space-between;
        }
        .sidebar__link--has-sub .chevron {
            font-size: 0.625rem;
            transition: transform var(--transition);
        }
        .sidebar__link--has-sub.open .chevron {
            transform: rotate(90deg);
        }
        .sidebar__sub-link {
            display: block;
            padding: 0.4rem 0.75rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
            border-radius: var(--radius-sm);
            transition: all var(--transition);
            margin-bottom: 0.125rem;
        }
        .sidebar__sub-link:hover {
            color: var(--primary);
            background: var(--primary-light);
        }
        .sidebar__sub-link.active {
            color: var(--primary);
            font-weight: 600;
        }
        .admin-content-wrapper {
            padding-top: 64px;
        }
        .sidebar-toggle-mobile {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
        }
        @media (max-width: 1024px) {
            .sidebar-toggle-mobile { display: block; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar--solid navbar-admin">
        <button type="button" class="sidebar-toggle-mobile" id="sidebarToggle" aria-label="Show or hide the menu" title="Show or hide the menu">
            &#9776;
        </button>

        <div class="navbar__breadcrumbs">
            <?php if (!empty($breadcrumbs)): ?>
                <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
            <?php endif; ?>
        </div>

        <div class="navbar__right">
            <?php if (in_array(session_get('user_type'), ['student', 'parent'], true)): ?>
            <!-- English / Sinhala -->
            <div class="lang-switch" role="group" aria-label="Language / භාෂාව">
                <a href="<?= lang_switch_url('en') ?>" class="<?= lang_current() === 'en' ? 'is-on' : '' ?>" lang="en" hreflang="en" aria-current="<?= lang_current() === 'en' ? 'true' : 'false' ?>">EN</a>
                <a href="<?= lang_switch_url('si') ?>" class="<?= lang_current() === 'si' ? 'is-on' : '' ?>" lang="si" hreflang="si" aria-current="<?= lang_current() === 'si' ? 'true' : 'false' ?>">සිං</a>
            </div>
            <?php endif; ?>

            <!-- Theme Toggle -->
            <button type="button" class="theme-toggle-btn" data-toggle="theme" aria-label="Toggle theme">
                <span class="theme-icon-light">&#9790;</span>
                <span class="theme-icon-dark" style="display:none;">&#9728;</span>
            </button>

            <!-- Messages & Notifications -->
            <button type="button" class="notification-bell" aria-label="Messages" onclick="window.location.href='<?= BASE_URL ?>/messages'">
                &#128276;
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-bell__count"><?= (int) $notificationCount ?></span>
                <?php endif; ?>
            </button>

            <!-- User Dropdown -->
            <div class="dropdown" id="userDropdown">
                <button type="button" class="user-dropdown-trigger" onclick="document.getElementById('userDropdown').classList.toggle('open')">
                    <div class="avatar avatar--sm">
                        <?= strtoupper(mb_substr($userName, 0, 1)) ?>
                    </div>
                    <div class="user-dropdown-trigger__info">
                        <div class="user-dropdown-trigger__name"><?= $userName ?></div>
                        <div class="user-dropdown-trigger__role"><?= te((string) $userRoleLabel) ?></div>
                    </div>
                    <span style="font-size:0.625rem; color:var(--text-muted);">&#9660;</span>
                </button>
                <div class="dropdown__menu">
                    <?php if (session_get('user_type') === 'student'): ?>
                        <a href="<?= BASE_URL ?>/my/profile" class="dropdown__item"><?= te('My Profile') ?></a>
                    <?php elseif (session_get('user_type') === 'parent'): ?>
                        <a href="<?= BASE_URL ?>/family" class="dropdown__item"><?= te('My Child') ?></a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/profile" class="dropdown__item">Profile</a>
                        <?php if (session_get('user_role') === 'super_admin'): ?>
                            <a href="<?= BASE_URL ?>/settings" class="dropdown__item"><?= te('Settings') ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="dropdown__divider"></div>
                    <form method="POST" action="<?= BASE_URL ?>/logout" style="margin:0;">
                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                        <button type="submit" class="dropdown__item dropdown__item--danger" style="width:100%;"><?= te('Logout') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                <rect width="32" height="32" rx="8" fill="var(--primary)"/>
                <path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="var(--text-inverse)" stroke-width="1.5" fill="none"/>
                <path d="M16 12v10M8 12l8 4 8-4" stroke="var(--text-inverse)" stroke-width="1.5"/>
            </svg>
            <span class="sidebar__brand-text">EduManager</span>

            <button type="button" class="sidebar-collapse-btn" id="sidebarCollapse"
                    aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar"
                    style="margin-left:auto;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
        </div>

        <nav class="sidebar__nav">
            <div class="sidebar__section"><?= te('Main') ?></div>

            <!-- Dashboard: the institute's, so staff only. Students and parents
                 have their own home pages further down. -->
            <?php if (!in_array(session_get('user_type'), ['student', 'parent'], true)): ?>
            <a href="<?= BASE_URL ?>/dashboard" class="sidebar__link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard
            </a>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
            <!-- All Users (Admins, Coordinators, Staff, Teachers) -->
            <a href="<?= BASE_URL ?>/users" class="sidebar__link <?= $activePage === 'users' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                All Users
            </a>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator'])): ?>
            <!-- Institutes -->
            <a href="<?= BASE_URL ?>/institutes" class="sidebar__link <?= $activePage === 'institutes' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                Institutes
            </a>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'])): ?>
            <!-- Classes -->
            <a href="<?= BASE_URL ?>/classes" class="sidebar__link <?= $activePage === 'classes' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Classes
            </a>
            <?php endif; ?>

            <?php
            // The learner-facing areas. A student and a guardian never see
            // the staff navigation at all, so these are grouped first and
            // the rest of the sidebar is skipped for them entirely.
            $userType = session_get('user_type');

            // The current page, from the URL rather than from $activePage:
            // the portal pages never set $activePage, so no student link was
            // ever highlighted. The path is taken relative to BASE_URL so the
            // subfolder install works the same as a root one.
            $basePath = rtrim((string) (parse_url(BASE_URL, PHP_URL_PATH) ?? ''), '/');
            $herePath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
            if ($basePath !== '' && str_starts_with($herePath, $basePath)) {
                $herePath = substr($herePath, strlen($basePath));
            }
            $herePath = '/' . trim($herePath, '/');
            $isHere = static fn (string $p, bool $exact = false): bool =>
                $herePath === $p || (!$exact && str_starts_with($herePath, $p . '/'));
            ?>

            <?php if ($userType === 'student'): ?>
            <a href="<?= BASE_URL ?>/my/home" class="sidebar__link <?= $isHere('/my/home') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <?= te('My Home') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/classes" class="sidebar__link <?= ($isHere('/my/classes') || $isHere('/my/quizzes') || $isHere('/my/materials')) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <?= te('My Classes') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/marks" class="sidebar__link <?= $isHere('/my/marks') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                <?= te('My Marks') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/progress" class="sidebar__link <?= $isHere('/my/progress') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                <?= te('My Progress') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/calendar" class="sidebar__link <?= $isHere('/my/calendar') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= te('My Calendar') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/assignments" class="sidebar__link <?= $isHere('/my/assignments') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                <?= te('My assignments') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/recordings" class="sidebar__link <?= $isHere('/my/recordings') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                <?= te('Class Recordings') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/papers" class="sidebar__link <?= $isHere('/my/papers') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5z"/><path d="M8 7h8M8 11h6"/></svg>
                <?= te('Past papers') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/payments" class="sidebar__link <?= $isHere('/my/payments') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <?= te('Payments') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/notices" class="sidebar__link <?= $isHere('/my/notices') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?= te('Notices') ?>
            </a>
            <a href="<?= BASE_URL ?>/my/help" class="sidebar__link <?= $isHere('/my/help') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?= te('Help & questions') ?>
            </a>
            <?php endif; ?>

            <?php if ($userType === 'parent'): ?>
            <a href="<?= BASE_URL ?>/family" class="sidebar__link <?= $isHere('/family', true) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?= te('My Child') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/marks" class="sidebar__link <?= $isHere('/family/marks') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                <?= te('Marks') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/fees" class="sidebar__link <?= $isHere('/family/fees') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                <?= te('Fees') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/calendar" class="sidebar__link <?= $isHere('/family/calendar') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= te('Calendar') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/assignments" class="sidebar__link <?= $isHere('/family/assignments') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                <?= te('Assignments') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/recordings" class="sidebar__link <?= $isHere('/family/recordings') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                <?= te('Class Recordings') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/payments" class="sidebar__link <?= $isHere('/family/payments') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <?= te('Pay a fee') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/notices" class="sidebar__link <?= $isHere('/family/notices') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?= te('Notices') ?>
            </a>
            <a href="<?= BASE_URL ?>/family/help" class="sidebar__link <?= $isHere('/family/help') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?= te('Help & questions') ?>
            </a>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'])): ?>
            <!-- Teaching: material and quizzes, both per class -->
            <a href="<?= BASE_URL ?>/teaching" class="sidebar__link <?= $activePage === 'teaching' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Teaching
            </a>
            <?php if (in_array($userRole, ['super_admin', 'admin', 'teacher'], true)): ?>
            <a href="<?= BASE_URL ?>/topics" class="sidebar__link <?= $isHere('/topics') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <?= te('Topics') ?>
            </a>
            <a href="<?= BASE_URL ?>/past-papers" class="sidebar__link <?= $isHere('/past-papers') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5z"/><path d="M8 7h8M8 11h6"/></svg>
                <?= te('Past papers') ?>
            </a>
            <?php endif; ?>
            <!-- Classes, quizzes and tests on one grid -->
            <a href="<?= BASE_URL ?>/calendar" class="sidebar__link <?= $activePage === 'calendar' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Calendar
            </a>
            <a href="<?= BASE_URL ?>/notices" class="sidebar__link <?= $activePage === 'notices' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?= te('Notice board') ?>
            </a>
            <a href="<?= BASE_URL ?>/help" class="sidebar__link <?= $activePage === 'help' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?= te('Help desk') ?>
            </a>
            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
            <a href="<?= BASE_URL ?>/inquiries" class="sidebar__link <?= $activePage === 'inquiries' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Inquiries
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
            <!-- WhatsApp: fee chasing, which is an office job rather than a teaching one -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'whatsapp' ? 'active open' : '' ?>" data-toggle-sub="whatsapp-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                        WhatsApp
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'whatsapp' ? 'open' : '' ?>" id="whatsapp-sub">
                    <a href="<?= BASE_URL ?>/whatsapp/reminders" class="sidebar__sub-link">Fee Reminders</a>
                    <a href="<?= BASE_URL ?>/whatsapp/log" class="sidebar__sub-link">Message Log</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'])): ?>
            <!-- Students -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'students' ? 'active open' : '' ?>" data-toggle-sub="students-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        Students
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'students' ? 'open' : '' ?>" id="students-sub">
                    <a href="<?= BASE_URL ?>/students" class="sidebar__sub-link <?= $activeSubPage === 'list' ? 'active' : '' ?>">All Students</a>
                    <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
                    <a href="<?= BASE_URL ?>/students/create" class="sidebar__sub-link <?= $activeSubPage === 'create' ? 'active' : '' ?>">Register New</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
            <!-- Payments (desk roles; teachers do not handle fees) -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'payments' ? 'active open' : '' ?>" data-toggle-sub="payments-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        Payments
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'payments' ? 'open' : '' ?>" id="payments-sub">
                    <a href="<?= BASE_URL ?>/payments" class="sidebar__sub-link <?= $activeSubPage === 'overview' ? 'active' : '' ?>">Overview</a>
                    <?php if (in_array($userRole, ['super_admin', 'admin', 'staff'])): ?>
                    <a href="<?= BASE_URL ?>/payments/slips" class="sidebar__sub-link">Payment slips</a>
                    <?php endif; ?>
                    <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
                    <a href="<?= BASE_URL ?>/payments/generate" class="sidebar__sub-link <?= $activeSubPage === 'generate' ? 'active' : '' ?>">Generate Monthly</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'])): ?>
            <!-- Attendance & QR -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'attendance' ? 'active open' : '' ?>" data-toggle-sub="attendance-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                        Attendance &amp; QR
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'attendance' ? 'open' : '' ?>" id="attendance-sub">
                    <a href="<?= BASE_URL ?>/attendance" class="sidebar__sub-link <?= $activeSubPage === 'log' ? 'active' : '' ?>">Daily Attendance</a>
                    <a href="<?= BASE_URL ?>/attendance/qr" class="sidebar__sub-link <?= $activeSubPage === 'qr' ? 'active' : '' ?>">QR Scan Terminal</a>
                    <a href="<?= BASE_URL ?>/attendance/register" class="sidebar__sub-link <?= $isHere('/attendance/register') ? 'active' : '' ?>"><?= te('Class register') ?></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
            <!-- Workflows -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'workflows' ? 'active open' : '' ?>" data-toggle-sub="workflows-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Workflows
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'workflows' ? 'open' : '' ?>" id="workflows-sub">
                    <a href="<?= BASE_URL ?>/workflows" class="sidebar__sub-link <?= $activeSubPage === 'pending' ? 'active' : '' ?>">Pending Approvals</a>
                    <a href="<?= BASE_URL ?>/workflows/history" class="sidebar__sub-link <?= $activeSubPage === 'history' ? 'active' : '' ?>">History</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Messages Inbox -->
            <a href="<?= BASE_URL ?>/messages" class="sidebar__link <?= $activePage === 'messages' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <?= te('Messages') ?>
                <?php if ($notificationCount > 0): ?>
                    <span class="badge badge--danger text-xs" style="margin-left:auto; border-radius:9999px; padding:0.15rem 0.45rem;"><?= (int) $notificationCount ?></span>
                <?php endif; ?>
            </a>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator'])): ?>
            <!-- Reports Hub -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'reports' ? 'active open' : '' ?>" data-toggle-sub="reports-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Reports Hub
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'reports' ? 'open' : '' ?>" id="reports-sub">
                    <a href="<?= BASE_URL ?>/reports" class="sidebar__sub-link <?= $activeSubPage === 'hub' ? 'active' : '' ?>">All Reports</a>
                    <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
                        <a href="<?= BASE_URL ?>/reports/insights" class="sidebar__sub-link <?= $activeSubPage === 'insights' ? 'active' : '' ?>">Branch Insights</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/reports/attendance" class="sidebar__sub-link <?= $activeSubPage === 'attendance' ? 'active' : '' ?>">Attendance Report</a>
                    <a href="<?= BASE_URL ?>/reports/classes" class="sidebar__sub-link <?= $activeSubPage === 'classes' ? 'active' : '' ?>">Class &amp; Enrollment</a>
                    <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
                    <a href="<?= BASE_URL ?>/reports/users" class="sidebar__sub-link <?= $activeSubPage === 'users' ? 'active' : '' ?>">Users &amp; Staff</a>
                    <a href="<?= BASE_URL ?>/reports/payments" class="sidebar__sub-link <?= $activeSubPage === 'payments' ? 'active' : '' ?>">Payment Summary</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/reports/students" class="sidebar__sub-link <?= $activeSubPage === 'students' ? 'active' : '' ?>">Student Status</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($userRole === 'super_admin'): ?>
            <div class="sidebar__section">Administration</div>

            <!-- Audit Log -->
            <a href="<?= BASE_URL ?>/audit" class="sidebar__link <?= $activePage === 'audit' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                Audit Log
            </a>
            <?php endif; ?>

            <?php if ($userRole === 'super_admin'): ?>
            <!-- Settings (system-wide, so super admin only) -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'settings' ? 'active open' : '' ?>" data-toggle-sub="settings-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        Settings
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'settings' ? 'open' : '' ?>" id="settings-sub">
                    <a href="<?= BASE_URL ?>/settings" class="sidebar__sub-link <?= $activeSubPage === 'general' ? 'active' : '' ?>">General</a>
                    <a href="<?= BASE_URL ?>/settings/deactivation" class="sidebar__sub-link <?= $activeSubPage === 'deactivation' ? 'active' : '' ?>">Deactivation</a>
                    <a href="<?= BASE_URL ?>/settings/deletion" class="sidebar__sub-link <?= $activeSubPage === 'deletion' ? 'active' : '' ?>">Deletion</a>
                    <a href="<?= BASE_URL ?>/settings/dashboard-items" class="sidebar__sub-link <?= $activeSubPage === 'dashboard-items' ? 'active' : '' ?>">Dashboard Items</a>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar__footer">
            &copy; <?= date('Y') ?> EduManager
        </div>
    </aside>

    <!-- Main Content -->
    <div class="admin-layout">
        <main class="admin-main admin-content-wrapper">
            <?php include __DIR__ . '/../components/flash-messages.php'; ?>
            <?= $content ?>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(BASE_PATH . '/public/assets/js/app.js') ?: 1 ?>"></script>
    <script>
    (function () {
        // Theme icon sync
        function syncThemeIcons() {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            document.querySelectorAll('.theme-icon-light').forEach(function (el) { el.style.display = isDark ? 'none' : 'inline'; });
            document.querySelectorAll('.theme-icon-dark').forEach(function (el) { el.style.display = isDark ? 'inline' : 'none'; });
        }

        // Restore theme
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        } catch (e) {}

        // Restore the sidebar width alongside it, for the same reason:
        // reading it later would mean the sidebar renders wide and then
        // snaps narrow on every page load.
        try {
            if (localStorage.getItem('sidebarCollapsed') === '1') {
                document.documentElement.setAttribute('data-sidebar', 'collapsed');
            }
        } catch (e) {}
        syncThemeIcons();

        // Observe data-theme changes
        var observer = new MutationObserver(syncThemeIcons);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        // Sidebar mobile toggle
        var sidebarToggle = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (sidebarToggle && sidebar && overlay) {
            sidebarToggle.addEventListener('click', function () {
                // On a desktop the same button slides the sidebar to its
                // icon rail and back; on a phone it opens the drawer.
                var collapse = document.getElementById('sidebarCollapse');
                if (window.innerWidth > 1024 && collapse) {
                    collapse.click();
                    return;
                }
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            });
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }

        // Sidebar collapse (desktop). The state lives on <html> so the
        // CSS variable it changes applies before anything paints, and is
        // remembered per browser -- a teacher who works collapsed should
        // not have to collapse it again on every page.
        (function () {
            var collapseBtn = document.getElementById('sidebarCollapse');
            if (!collapseBtn) { return; }

            var root = document.documentElement;

            function apply(collapsed) {
                if (collapsed) {
                    root.setAttribute('data-sidebar', 'collapsed');
                } else {
                    root.removeAttribute('data-sidebar');
                }
                collapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                collapseBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                collapseBtn.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';

                // Collapsed links show only an icon, so each one needs its
                // own tooltip to stay identifiable.
                document.querySelectorAll('.sidebar__link').forEach(function (link) {
                    if (collapsed) {
                        if (!link.dataset.label) {
                            link.dataset.label = (link.textContent || '').trim();
                        }
                        link.title = link.dataset.label;
                    } else {
                        link.removeAttribute('title');
                    }
                });
            }

            collapseBtn.addEventListener('click', function () {
                var next = root.getAttribute('data-sidebar') !== 'collapsed';
                apply(next);
                try {
                    localStorage.setItem('sidebarCollapsed', next ? '1' : '0');
                } catch (e) { /* private browsing: the choice just will not stick */ }
            });

            apply(root.getAttribute('data-sidebar') === 'collapsed');
        })();

        // Sidebar sub-nav toggling
        document.querySelectorAll('[data-toggle-sub]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var targetId = link.getAttribute('data-toggle-sub');
                var subLinks = document.getElementById(targetId);
                if (subLinks) {
                    subLinks.classList.toggle('open');
                    link.classList.toggle('open');
                }
            });
        });

        // Close user dropdown on outside click
        document.addEventListener('click', function (e) {
            var dd = document.getElementById('userDropdown');
            if (dd && !dd.contains(e.target)) {
                dd.classList.remove('open');
            }
        });
    })();
    </script>

    <?php if (in_array(session_get('user_type'), ['student', 'parent'], true)): ?>
        <?php require BASE_PATH . '/app/Views/partials/assistant.php'; ?>
    <?php endif; ?>
</body>
</html>
