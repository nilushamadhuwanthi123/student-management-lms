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
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'EduManager', ENT_QUOTES, 'UTF-8') ?> - EduManager</title>
    <?php if (function_exists('csrfMeta')): ?><?= csrfMeta() ?><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/style.css">
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
        <button type="button" class="sidebar-toggle-mobile" id="sidebarToggle" aria-label="Toggle sidebar">
            &#9776;
        </button>

        <div class="navbar__breadcrumbs">
            <?php if (!empty($breadcrumbs)): ?>
                <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
            <?php endif; ?>
        </div>

        <div class="navbar__right">
            <!-- Theme Toggle -->
            <button type="button" class="theme-toggle-btn" data-toggle="theme" aria-label="Toggle theme">
                <span class="theme-icon-light">&#9790;</span>
                <span class="theme-icon-dark" style="display:none;">&#9728;</span>
            </button>

            <!-- Notifications -->
            <button type="button" class="notification-bell" aria-label="Notifications" onclick="window.location.href='/notifications'">
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
                        <div class="user-dropdown-trigger__role"><?= htmlspecialchars($userRoleLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <span style="font-size:0.625rem; color:var(--text-muted);">&#9660;</span>
                </button>
                <div class="dropdown__menu">
                    <a href="/profile" class="dropdown__item">Profile</a>
                    <a href="/settings" class="dropdown__item">Settings</a>
                    <div class="dropdown__divider"></div>
                    <form method="POST" action="/logout" style="margin:0;">
                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                        <button type="submit" class="dropdown__item dropdown__item--danger" style="width:100%;">Logout</button>
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
            <span>EduManager</span>
        </div>

        <nav class="sidebar__nav">
            <div class="sidebar__section">Main</div>

            <!-- Dashboard -->
            <a href="/dashboard" class="sidebar__link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard
            </a>

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
                    <a href="/students" class="sidebar__sub-link <?= $activeSubPage === 'list' ? 'active' : '' ?>">All Students</a>
                    <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
                    <a href="/students/create" class="sidebar__sub-link <?= $activeSubPage === 'create' ? 'active' : '' ?>">Register New</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'])): ?>
            <!-- Payments -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'payments' ? 'active open' : '' ?>" data-toggle-sub="payments-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        Payments
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'payments' ? 'open' : '' ?>" id="payments-sub">
                    <a href="/payments" class="sidebar__sub-link <?= $activeSubPage === 'overview' ? 'active' : '' ?>">Overview</a>
                    <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
                    <a href="/payments/generate" class="sidebar__sub-link <?= $activeSubPage === 'generate' ? 'active' : '' ?>">Generate Monthly</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'])): ?>
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
                    <a href="/workflows" class="sidebar__sub-link <?= $activeSubPage === 'pending' ? 'active' : '' ?>">Pending Approvals</a>
                    <a href="/workflows/history" class="sidebar__sub-link <?= $activeSubPage === 'history' ? 'active' : '' ?>">History</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'coordinator'])): ?>
            <div class="sidebar__section">Administration</div>

            <!-- Audit Log -->
            <a href="/audit" class="sidebar__link <?= $activePage === 'audit' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                Audit Log
            </a>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
            <!-- Settings -->
            <div class="sidebar__nav-group">
                <a href="#" class="sidebar__link sidebar__link--has-sub <?= $activePage === 'settings' ? 'active open' : '' ?>" data-toggle-sub="settings-sub">
                    <span style="display:flex;align-items:center;gap:0.75rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        Settings
                    </span>
                    <span class="chevron">&#9654;</span>
                </a>
                <div class="sidebar__sub-links <?= $activePage === 'settings' ? 'open' : '' ?>" id="settings-sub">
                    <a href="/settings" class="sidebar__sub-link <?= $activeSubPage === 'general' ? 'active' : '' ?>">General</a>
                    <a href="/settings/deactivation" class="sidebar__sub-link <?= $activeSubPage === 'deactivation' ? 'active' : '' ?>">Deactivation</a>
                    <a href="/settings/deletion" class="sidebar__sub-link <?= $activeSubPage === 'deletion' ? 'active' : '' ?>">Deletion</a>
                    <a href="/settings/dashboard-items" class="sidebar__sub-link <?= $activeSubPage === 'dashboard-items' ? 'active' : '' ?>">Dashboard Items</a>
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

    <script src="/assets/js/app.js"></script>
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
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            });
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }

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
</body>
</html>
