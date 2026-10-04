<?php
/**
 * Students - List View
 *
 * Variables: $students (paginated array), $institutes, $classes,
 *            $pagination, $search, $filters
 */

$students   = $students ?? [];
$institutes = $institutes ?? [];
$classes    = $classes ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'pages' => 1, 'total' => 0];
$search     = $search ?? '';
$filters    = $filters ?? [];
$baseUrl    = defined('BASE_URL') ? BASE_URL : '';
?>

<div class="page-header">
    <h1 class="page-header__title">Student Management</h1>
    <div class="page-header__actions">
        <a href="<?= $baseUrl ?>/students/create" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Register New Student
        </a>
    </div>
</div>

<!-- Filters -->
<div class="table-wrapper mb-3">
    <div class="table-toolbar">
        <form method="GET" action="<?= $baseUrl ?>/students" style="display:flex;flex-wrap:wrap;gap:0.75rem;width:100%;align-items:center;">
            <input
                type="text"
                name="search"
                class="table-search"
                placeholder="Search by name, ID, or email..."
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            >
            <select name="status" class="form-control" style="width:auto;min-width:130px;">
                <option value="">All Statuses</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="deactivated" <?= ($filters['status'] ?? '') === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
            </select>
            <button type="submit" class="btn btn--primary btn--sm">Filter</button>
            <a href="<?= $baseUrl ?>/students" class="btn btn--ghost btn--sm">Clear</a>
        </form>
    </div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th data-sort>Student ID</th>
                <th data-sort>Name</th>
                <th>Phone</th>
                <th data-sort>Status</th>
                <th>Actions</th>
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
                                    <?php if (!empty($student['email'])): ?>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($student['phone1'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
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
                            <span class="badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= $baseUrl ?>/students/<?= (int) $student['id'] ?>" class="btn btn--ghost btn--sm" data-tooltip="View">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <a href="<?= $baseUrl ?>/students/<?= (int) $student['id'] ?>/edit" class="btn btn--ghost btn--sm" data-tooltip="Edit">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-state__icon">&#128100;</div>
                            <div class="empty-state__title">No students found</div>
                            <div class="empty-state__text">Try adjusting your filters or register a new student.</div>
                            <a href="<?= $baseUrl ?>/students/create" class="btn btn--primary btn--sm">Register New Student</a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if (($pagination['pages'] ?? 1) > 1): ?>
        <?php
        $paginationBaseUrl = $baseUrl . '/students?' . http_build_query(array_filter([
            'search' => $search,
            'status' => $filters['status'] ?? '',
            'class_id' => $filters['class_id'] ?? '',
        ]));
        $currentPage = $pagination['current_page'] ?? 1;
        $totalPages  = $pagination['pages'] ?? 1;
        include __DIR__ . '/../components/pagination.php';
        ?>
    <?php endif; ?>
</div>
