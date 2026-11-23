<?php
/**
 * Students - List View
 *
 * Variables: $students (paginated array), $institutes, $classes,
 *            $currentPage, $totalPages, $filters, $totalStudents
 */

$students     = $students ?? [];
$institutes   = $institutes ?? [];
$classes      = $classes ?? [];
$filters      = $filters ?? [];
$currentPage  = $currentPage ?? 1;
$totalPages   = $totalPages ?? 1;
$totalStudents = $totalStudents ?? 0;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('Student Management') ?></h1>
    <?php
    /*
     * Same as the class list: a teacher was shown "Register New Student"
     * and an Edit button on every row, and each one bounced her to the
     * dashboard with a permission error. Registering and editing students
     * is the office's job; she reads the list. Matches the roles the
     * routes enforce.
     */
    // config/permissions.php holds the one copy of this rule.
    $canManageStudents = can('students.manage');
    ?>
    <div class="page-header__actions">
        <?php if ($canManageStudents): ?>
        <a href="<?= BASE_URL ?>/students/create" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= te('Register New Student') ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="table-wrapper mb-3">
    <div class="table-toolbar">
        <form method="GET" action="<?= BASE_URL ?>/students" style="display:flex;flex-wrap:wrap;gap:0.75rem;width:100%;align-items:center;">
            <input
                type="text"
                name="search"
                class="table-search"
                placeholder="<?= te('Search by name, ID, or email...') ?>"
                value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >
            <select name="institute" class="form-control" style="width:auto;min-width:150px;">
                <option value=""><?= te('All Institutes') ?></option>
                <?php foreach ($institutes as $inst): ?>
                    <option value="<?= (int) $inst['id'] ?>" <?= ($filters['institute'] ?? '') == $inst['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="class_id" class="form-control" style="width:auto;min-width:150px;">
                <option value=""><?= te('All Classes') ?></option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?= (int) $cls['id'] ?>" <?= ($filters['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cls['class_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width:auto;min-width:130px;">
                <option value=""><?= te('All Statuses') ?></option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= te('Active') ?></option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= te('Inactive') ?></option>
                <option value="deactivated" <?= ($filters['status'] ?? '') === 'deactivated' ? 'selected' : '' ?>><?= te('Deactivated') ?></option>
            </select>
            <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
            <a href="<?= BASE_URL ?>/students" class="btn btn--ghost btn--sm"><?= te('Clear') ?></a>
        </form>
    </div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th data-sort><?= te('Student ID') ?></th>
                <th data-sort><?= te('Name') ?></th>
                <th data-sort><?= te('Institute') ?></th>
                <th><?= te('Class(es)') ?></th>
                <th data-sort><?= te('Status') ?></th>
                <th><?= te('Scholarship') ?></th>
                <th><?= te('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($students)): ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td class="font-medium"><?= htmlspecialchars($student['student_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="d-flex items-center gap-1">
                                <div class="avatar avatar--sm">
                                    <?= strtoupper(mb_substr($student['full_name'] ?? 'S', 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?= htmlspecialchars($student['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($unenrolled[(int) ($student['id'] ?? 0)])): ?>
                                        <?php /* Registered but in no running class: their portal is
                                                 empty and nothing else on this screen would say so. */ ?>
                                        <a class="stu-noclass"
                                           href="<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>/edit"
                                           title="<?= te('This student is registered but not in any running class, so their portal is empty. Click to enrol them.') ?>">
                                            <?= te('In no class') ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($student['email'])): ?>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($student['institute_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php
                            $classNames = $student['class_names'] ?? '';
                            if (is_array($classNames)) {
                                $classNames = implode(', ', $classNames);
                            }
                            ?>
                            <span class="text-sm"><?= htmlspecialchars($classNames ?: '-', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <?php
                            $status = $student['status'] ?? 'active';
                            $badgeClass = match ($status) {
                                'active'      => 'badge--active',
                                'inactive'    => 'badge--inactive',
                                'deactivated' => 'badge--deactivated',
                                default       => 'badge--neutral',
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= te(ucfirst($status)) ?></span>
                        </td>
                        <td>
                            <?php
                            $scholarship = $student['scholarship_type'] ?? null;
                            if ($scholarship === 'half_payment') {
                                echo '<span class="badge badge--info">' . te('50% Off') . '</span>';
                            } elseif ($scholarship === 'free_card') {
                                echo '<span class="badge badge--success">' . te('Free Card') . '</span>';
                            } else {
                                echo '<span class="text-sm text-muted">' . te('Full Payment') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>" class="btn btn--ghost btn--sm" data-tooltip="<?= te('View') ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php if ($canManageStudents): ?>
                                <a href="<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>/edit" class="btn btn--ghost btn--sm" data-tooltip="<?= te('Edit') ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <button type="button" class="btn btn--ghost btn--sm" data-tooltip="<?= te('Delete') ?>" onclick="confirmAction('<?= te("Are you sure you want to initiate deletion for this student?") ?>', function(){ window.location.href='<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>/delete'; })">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state__icon">&#128100;</div>
                            <div class="empty-state__title"><?= te('No students found') ?></div>
                            <div class="empty-state__text"><?= $canManageStudents
                                ? te('Try adjusting your filters or register a new student.')
                                : te('Try adjusting your filters.') ?></div>
                            <?php if ($canManageStudents): ?>
                            <a href="<?= BASE_URL ?>/students/create" class="btn btn--primary btn--sm"><?= te('Register New Student') ?></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        $baseUrl = '/students?' . http_build_query(array_filter($filters));
        include __DIR__ . '/../components/pagination.php';
        ?>
    <?php endif; ?>
</div>

<style>
    /* A warning, not an error: the record is valid, the child's portal
       is just empty. It links to the place that fixes it rather than
       only pointing the problem out. */
    .stu-noclass { display: inline-block; margin-top: .15rem; text-decoration: none;
                   font-size: .68rem; font-weight: 800; letter-spacing: .02em;
                   color: #9A3412; background: #FFF7ED; border: 1px solid #FED7AA;
                   border-radius: 999px; padding: .08rem .45rem; }
    .stu-noclass:hover { background: #FFEDD5; }
</style>
