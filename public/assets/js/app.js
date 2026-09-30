/**
 * Student Management System - Main JavaScript
 */
(function () {
    'use strict';

    // ── Theme Toggle ────────────────────────────────────────────────
    function initThemeToggle() {
        var saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) { /* ignore */ }
        var theme = saved || 'light';
        document.documentElement.setAttribute('data-theme', theme);

        document.querySelectorAll('[data-toggle="theme"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var current = document.documentElement.getAttribute('data-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('theme', next); } catch (e) { /* ignore */ }
            });
        });
    }

    // ── Mobile Nav Toggle ───────────────────────────────────────────
    function initMobileNav() {
        var toggler = document.querySelector('[data-toggle="mobile-nav"]');
        var nav = document.getElementById('mobile-nav');
        if (!toggler || !nav) return;

        toggler.addEventListener('click', function () {
            var expanded = toggler.getAttribute('aria-expanded') === 'true';
            toggler.setAttribute('aria-expanded', String(!expanded));
            nav.classList.toggle('is-open');
        });
    }

    // ── Flash Message Auto-Dismiss ──────────────────────────────────
    function initFlashMessages() {
        document.querySelectorAll('.flash-message').forEach(function (el) {
            // Auto-dismiss after 5 seconds
            var timer = setTimeout(function () { dismissFlash(el); }, 5000);

            // Manual dismiss
            var closeBtn = el.querySelector('.flash-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    clearTimeout(timer);
                    dismissFlash(el);
                });
            }
        });
    }

    function dismissFlash(el) {
        el.classList.add('flash-dismiss');
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 300);
    }

    // ── Password Show/Hide Toggle ───────────────────────────────────
    function initPasswordToggles() {
        document.querySelectorAll('[data-toggle="password"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-target');
                var input = targetId ? document.getElementById(targetId) : btn.previousElementSibling;
                if (!input) return;

                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                btn.textContent = isPassword ? 'Hide' : 'Show';
                btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });
    }

    // ── Password Strength Meter ─────────────────────────────────────
    function initPasswordStrength() {
        document.querySelectorAll('[data-password-strength]').forEach(function (input) {
            var meterId = input.getAttribute('data-password-strength');
            var meter = document.getElementById(meterId);
            if (!meter) return;

            input.addEventListener('input', function () {
                var result = checkPasswordStrength(input.value);
                meter.className = 'password-strength password-strength--' + result.level;
                meter.style.setProperty('--strength', result.score + '%');
                meter.textContent = result.label;
            });
        });
    }

    function checkPasswordStrength(password) {
        var score = 0;
        if (password.length >= 8) score += 20;
        if (password.length >= 12) score += 10;
        if (/[A-Z]/.test(password)) score += 20;
        if (/[a-z]/.test(password)) score += 20;
        if (/[0-9]/.test(password)) score += 15;
        if (/[^A-Za-z0-9]/.test(password)) score += 15;

        var level, label;
        if (score < 30) { level = 'weak'; label = 'Weak'; }
        else if (score < 60) { level = 'fair'; label = 'Fair'; }
        else if (score < 80) { level = 'good'; label = 'Good'; }
        else { level = 'strong'; label = 'Strong'; }

        return { score: score, level: level, label: label };
    }

    // ── Form Validation Helpers ─────────────────────────────────────
    function initFormValidation() {
        document.querySelectorAll('form[data-validate]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var isValid = true;

                form.querySelectorAll('[required]').forEach(function (field) {
                    clearFieldError(field);
                    if (!field.value.trim()) {
                        showFieldError(field, 'This field is required.');
                        isValid = false;
                    }
                });

                form.querySelectorAll('[type="email"]').forEach(function (field) {
                    if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                        showFieldError(field, 'Please enter a valid email address.');
                        isValid = false;
                    }
                });

                form.querySelectorAll('[data-min-length]').forEach(function (field) {
                    var min = parseInt(field.getAttribute('data-min-length'), 10);
                    if (field.value && field.value.length < min) {
                        showFieldError(field, 'Must be at least ' + min + ' characters.');
                        isValid = false;
                    }
                });

                if (!isValid) e.preventDefault();
            });
        });
    }

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        var error = document.createElement('div');
        error.className = 'field-error';
        error.textContent = message;
        field.parentNode.appendChild(error);
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        var existing = field.parentNode.querySelector('.field-error');
        if (existing) existing.parentNode.removeChild(existing);
    }

    // ── Modal Open/Close ────────────────────────────────────────────
    window.openModal = function (modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        // Focus trap — focus the first focusable element
        var focusable = modal.querySelector('input, button, select, textarea, [tabindex]');
        if (focusable) focusable.focus();
    };

    window.closeModal = function (modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };

    function initModals() {
        // Close modal on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.addEventListener('click', function () {
                var modal = backdrop.closest('.modal');
                if (modal) closeModal(modal.id);
            });
        });

        // Close modal on close button
        document.querySelectorAll('[data-dismiss="modal"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = btn.closest('.modal');
                if (modal) closeModal(modal.id);
            });
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var active = document.querySelector('.modal.is-active');
                if (active) closeModal(active.id);
            }
        });
    }

    // ── Toast Notifications ─────────────────────────────────────────
    window.showToast = function (message, type, duration) {
        type = type || 'info';
        duration = duration || 3000;

        var container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.textContent = message;
        container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(function () { toast.classList.add('toast--visible'); });

        setTimeout(function () {
            toast.classList.remove('toast--visible');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, duration);
    };

    // ── Confirm Dialog Wrapper ──────────────────────────────────────
    window.confirmAction = function (message, callback) {
        if (window.confirm(message)) {
            callback();
        }
    };

    // ── Table Sort Click Handler ────────────────────────────────────
    function initTableSort() {
        document.querySelectorAll('th[data-sort]').forEach(function (th) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function () {
                var table = th.closest('table');
                var tbody = table.querySelector('tbody');
                if (!tbody) return;

                var colIndex = Array.from(th.parentNode.children).indexOf(th);
                var rows = Array.from(tbody.querySelectorAll('tr'));
                var isAsc = th.classList.contains('sort-asc');

                // Clear sort classes on all headers
                th.parentNode.querySelectorAll('th').forEach(function (h) {
                    h.classList.remove('sort-asc', 'sort-desc');
                });

                rows.sort(function (a, b) {
                    var aText = (a.children[colIndex] || {}).textContent || '';
                    var bText = (b.children[colIndex] || {}).textContent || '';
                    aText = aText.trim().toLowerCase();
                    bText = bText.trim().toLowerCase();

                    var aNum = parseFloat(aText);
                    var bNum = parseFloat(bText);
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAsc ? bNum - aNum : aNum - bNum;
                    }
                    return isAsc ? bText.localeCompare(aText) : aText.localeCompare(bText);
                });

                th.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
                rows.forEach(function (row) { tbody.appendChild(row); });
            });
        });
    }

    // ── Sidebar Toggle ──────────────────────────────────────────────
    function initSidebarToggle() {
        var toggler = document.querySelector('[data-toggle="sidebar"]');
        var sidebar = document.getElementById('sidebar');
        if (!toggler || !sidebar) return;

        toggler.addEventListener('click', function () {
            sidebar.classList.toggle('is-collapsed');
            var collapsed = sidebar.classList.contains('is-collapsed');
            try { localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) { /* ignore */ }
        });

        // Restore saved state
        try {
            if (localStorage.getItem('sidebar-collapsed') === '1') {
                sidebar.classList.add('is-collapsed');
            }
        } catch (e) { /* ignore */ }
    }

    // ── CSRF Token Refresh ──────────────────────────────────────────
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Expose for AJAX calls
    window.getCsrfToken = getCsrfToken;

    // ── Smooth Scroll for Anchor Links ──────────────────────────────
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var targetId = link.getAttribute('href').substring(1);
                if (!targetId) return;
                var target = document.getElementById(targetId);
                if (!target) return;

                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // ── Initialization ──────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        initThemeToggle();
        initMobileNav();
        initFlashMessages();
        initPasswordToggles();
        initPasswordStrength();
        initFormValidation();
        initModals();
        initTableSort();
        initSidebarToggle();
        initSmoothScroll();
    });
})();
