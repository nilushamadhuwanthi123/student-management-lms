<?php
/**
 * 1.3 Classes Page — 13-Column Management Grid
 * Document 1 Page 6 Specifications:
 * Columns: CLASS NAME + Subject + Grade, TEACHER, INSTITUTE, COORDINATOR, ROOM, STAFF,
 *          SCHEDULE, TIME, FEE, STUDENTS (Count), STATUS, SPLIT, ACTIONS (Edit, Activate/Deactivate, Delete)
 */

$classes    = $classes ?? [];
$institutes = $institutes ?? [];
$filters    = $filters ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class Management') ?></h1>
        <p class="text-sm text-muted"><?= te('Manage academic classes, assigned teachers, weekly schedules, and revenue splits') ?></p>
    </div>
    <?php
    /*
     * A teacher could see "Create Class", "Hall Arrangement" and an Edit
     * button on every row, and every one of them threw her back to the
     * dashboard with "You do not have permission". The routes were right
     * to refuse her; the page was wrong to offer. A button that refuses
     * you is worse than no button -- it reads as the system being broken
     * rather than as a job that is not yours.
     *
     * These match the roles the routes already enforce.
     */
    // config/permissions.php holds the one copy of this rule.
    $canManageClasses = can('classes.manage');
    ?>
    <div class="page-header__actions">
        <?php if ($canManageClasses): ?>
        <a href="<?= BASE_URL ?>/classes/rooms" class="btn btn--secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <?= te('Hall Arrangement') ?>
        </a>
        <a href="<?= BASE_URL ?>/classes/create" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= te('Create Class') ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <!-- Filter Toolbar -->
    <div class="table-toolbar">
        <form method="GET" action="<?= BASE_URL ?>/classes" style="display:flex; flex-wrap:wrap; gap:0.75rem; width:100%; align-items:center;">
            <!-- Search classes -->
            <div style="flex:1; min-width:200px;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control form-control--sm" 
                    placeholder="<?= te('Search classes, subject, teacher...') ?>" 
                    value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <!-- Institute Filter -->
            <select name="institute_id" class="form-control form-control--sm" style="width:auto; min-width:160px;" onchange="this.form.submit()">
                <option value=""><?= te('All Institutes') ?></option>
                <?php foreach ($institutes as $inst): ?>
                    <option value="<?= (int) $inst['id'] ?>" <?= ($filters['institute_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Medium -->
            <select name="medium" class="form-control form-control--sm" style="width:auto; min-width:120px;" onchange="this.form.submit()">
                <option value=""><?= te('All Mediums') ?></option>
                <option value="Sinhala" <?= ($filters['medium'] ?? '') === 'Sinhala' ? 'selected' : '' ?>><?= te('Sinhala') ?></option>
                <option value="English" <?= ($filters['medium'] ?? '') === 'English' ? 'selected' : '' ?>><?= te('English') ?></option>
                <option value="Tamil" <?= ($filters['medium'] ?? '') === 'Tamil' ? 'selected' : '' ?>><?= te('Tamil') ?></option>
            </select>

            <!-- Status -->
            <select name="status" class="form-control form-control--sm" style="width:auto; min-width:110px;" onchange="this.form.submit()">
                <option value=""><?= te('All Statuses') ?></option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= te('Active') ?></option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= te('Inactive') ?></option>
            </select>

            <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
            <a href="<?= BASE_URL ?>/classes" class="btn btn--ghost btn--sm"><?= te('Reset') ?></a>
        </form>
    </div>

    <!-- 13-Column Grid as specified in Document 1 Page 6 -->
    <div class="table-wrapper" style="overflow-x:auto;">
        <table class="data-table" style="min-width:1250px; font-size:0.8125rem;">
            <thead>
                <tr>
                    <th style="min-width:180px;"><?= te('Class Name + Subject + Grade') ?></th>
                    <th><?= te('Teacher') ?></th>
                    <th><?= te('Institute') ?></th>
                    <th><?= te('Coordinator') ?></th>
                    <th><?= te('Room') ?></th>
                    <th><?= te('Staff') ?></th>
                    <th><?= te('Schedule') ?></th>
                    <th><?= te('Time') ?></th>
                    <th><?= te('Fee') ?></th>
                    <th><?= te('Students') ?></th>
                    <th><?= te('Status') ?></th>
                    <th><?= te('Split') ?></th>
                    <th style="text-align:right; min-width:140px;"><?= te('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($classes)): ?>
                    <?php foreach ($classes as $c): 
                        $status = $c['status'] ?? 'active';
                        // Format schedule days
                        $daysStr = '-';
                        if (!empty($c['schedule_days'])) {
                            $decoded = json_decode($c['schedule_days'], true);
                            if (is_array($decoded)) {
                                $daysStr = implode(', ', array_map(fn($d) => lang_names(substr($d, 0, 3)), $decoded));
                            }
                        }
                        // Format time
                        $timeStr = '-';
                        if (!empty($c['start_time'])) {
                            $timeStr = lang_names(date('g:i A', strtotime($c['start_time'])));
                            if (!empty($c['end_time'])) {
                                $timeStr .= ' - ' . lang_names(date('g:i A', strtotime($c['end_time'])));
                            }
                        }
                    ?>
                        <tr>
                            <!-- 1. CLASS NAME + Subject + Grade -->
                            <td>
                                <strong style="color:var(--text);"><?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="text-xs text-muted">
                                    <?= htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($c['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($c['medium'])): ?>
                                        <span class="badge badge--neutral" style="font-size:0.65rem; padding:1px 4px;"><?= te($c['medium']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- 2. TEACHER -->
                            <td>
                                <?php if (!empty($c['teacher_name'])): ?>
                                    <div style="font-weight:600;"><?= htmlspecialchars($c['teacher_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($c['teacher_display_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 3. INSTITUTE -->
                            <td>
                                <span><?= htmlspecialchars($c['institute_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>

                            <!-- 4. COORDINATOR -->
                            <td>
                                <?php if (!empty($c['coordinator_name'])): ?>
                                    <div><?= htmlspecialchars($c['coordinator_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 5. ROOM -->
                            <td>
                                <span class="badge badge--neutral"><?= htmlspecialchars($c['room'] ?? te('N/A'), ENT_QUOTES, 'UTF-8') ?></span>
                            </td>

                            <!-- 6. STAFF -->
                            <td>
                                <span><?= htmlspecialchars($c['staff_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>

                            <!-- 7. SCHEDULE -->
                            <td>
                                <strong><?= htmlspecialchars($daysStr, ENT_QUOTES, 'UTF-8') ?></strong>
                            </td>

                            <!-- 8. TIME -->
                            <td>
                                <span class="text-xs"><?= htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>

                            <!-- 9. FEE -->
                            <td>
                                <strong><?= te('Rs. {amount}', ['amount' => number_format((float) $c['monthly_fee'], 2)]) ?></strong>
                            </td>

                            <!-- 10. STUDENTS (Count) -->
                            <td style="text-align:center;">
                                <span class="badge badge--info font-bold">
                                    <?= (int) ($c['student_count'] ?? 0) ?>
                                </span>
                            </td>

                            <!-- 11. STATUS -->
                            <td>
                                <span class="badge <?= $status === 'active' ? 'badge--success' : 'badge--neutral' ?>">
                                    <?= te(ucfirst($status)) ?>
                                </span>
                            </td>

                            <!-- 12. SPLIT -->
                            <td>
                                <?php if ($c['teacher_revenue_split'] !== null): ?>
                                    <span class="text-xs"><?= te('T: {pct}%', ['pct' => (float) $c['teacher_revenue_split']]) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 13. ACTIONS (Edit, Activate/Deactivate, Delete) -->
                            <td style="text-align:right;">
                                <div class="d-flex gap-1 justify-end items-center">
                                    <?php /*
                                     * Every role that can see the list can
                                     * teach into the class, so the way in
                                     * to material and quizzes belongs on
                                     * the row. Without it a teacher, whose
                                     * manage buttons are hidden below, had
                                     * nothing on this page to click at all.
                                     */ ?>
                                    <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/quizzes" class="btn btn--ghost btn--xs" title="<?= te('Quizzes set for this class') ?>">
                                        <?= te('Quizzes') ?>
                                    </a>
                                    <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/materials" class="btn btn--ghost btn--xs" title="<?= te('Material posted to this class') ?>">
                                        <?= te('Material') ?>
                                    </a>
                                    <?php if ($canManageClasses): ?>
                                    <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/edit" class="btn btn--ghost btn--xs" title="<?= te('Edit Class') ?>">
                                        <?= te('Edit') ?>
                                    </a>

                                    <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/toggle-status" style="margin:0;">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn <?= $status === 'active' ? 'btn--secondary' : 'btn--primary' ?> btn--xs">
                                            <?= $status === 'active' ? te('Deactivate') : te('Activate') ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/delete" style="margin:0;" data-confirm="<?= htmlspecialchars(t('Delete the class {name}? This cannot be undone.', ['name' => $c['class_name']]), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn btn--danger btn--xs" title="<?= te('Delete Class') ?>">
                                            <?= te('Delete') ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13">
                            <div class="empty-state">
                                <div class="empty-state__icon">&#128218;</div>
                                <div class="empty-state__title"><?= te('No classes found') ?></div>
                                <div class="empty-state__text"><?= $canManageClasses
                                    ? te('Click "Create Class" to schedule your first tuition course.')
                                    : te('No classes have been set up for you yet. The office creates them.') ?></div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
