<?php
/**
 * Students - 360 View (Show)
 *
 * Variables: $student, $enrollments, $payments, $workflows, $auditTrail
 */

$student     = $student ?? [];
$enrollments = $enrollments ?? [];
$payments    = $payments ?? [];
$workflows   = $workflows ?? [];
$auditTrail  = $auditTrail ?? [];

$status = $student['status'] ?? 'active';
$badgeClass = match ($status) {
    'active'      => 'badge--active',
    'inactive'    => 'badge--inactive',
    'deactivated' => 'badge--deactivated',
    default       => 'badge--neutral',
};
?>

<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<div class="d-flex flex-wrap gap-3" style="align-items:flex-start;">
    <!-- Profile Card -->
    <div style="flex:0 0 280px;min-width:260px;">
        <div class="profile-card">
            <?php if (!empty($student['photo'])): ?>
                <img src="<?= htmlspecialchars($student['photo'], ENT_QUOTES, 'UTF-8') ?>" alt="Student photo" class="profile-card__photo">
            <?php else: ?>
                <div class="avatar avatar--xl" style="margin:0 auto 1rem;">
                    <?= strtoupper(mb_substr($student['full_name'] ?? 'S', 0, 1)) ?>
                </div>
            <?php endif; ?>

            <div class="profile-card__name"><?= htmlspecialchars($student['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="profile-card__role">
                <?= htmlspecialchars($student['student_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <span class="badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?></span>

            <div style="margin-top:1.25rem;" class="d-flex flex-wrap gap-1 justify-center">
                <a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn--secondary btn--sm">Edit</a>
                <?php if ($status === 'active'): ?>
                    <button type="button" class="btn btn--ghost btn--sm" onclick="openModal('deactivateModal')">Deactivate</button>
                <?php elseif ($status === 'deactivated'): ?>
                    <form method="POST" action="/students/<?= (int) $student['id'] ?>/reactivate" style="display:inline;">
                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Reactivate</button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn--danger btn--sm" onclick="openModal('deleteModal')">Delete</button>
            </div>
        </div>
    </div>

    <!-- Detail Tabs -->
    <div style="flex:1;min-width:0;">
        <div class="tabs" role="tablist">
            <button class="tab active" role="tab" data-tab="overview" onclick="switchTab('overview', this)">Overview</button>
            <button class="tab" role="tab" data-tab="enrollments" onclick="switchTab('enrollments', this)">Enrollments</button>
            <button class="tab" role="tab" data-tab="payments" onclick="switchTab('payments', this)">Payments</button>
            <button class="tab" role="tab" data-tab="workflow-history" onclick="switchTab('workflow-history', this)">Workflow History</button>
            <button class="tab" role="tab" data-tab="audit-trail" onclick="switchTab('audit-trail', this)">Audit Trail</button>
        </div>

        <!-- Overview Tab -->
        <div class="tab-panel" id="tab-overview">
            <div class="card mb-2">
                <h4 style="margin-bottom:0.75rem;">Personal Details</h4>
                <ul class="profile-card__details">
                    <li><span class="label">Full Name</span><span class="value"><?= htmlspecialchars($student['full_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                    <li><span class="label">Date of Birth</span><span class="value"><?= htmlspecialchars($student['dob'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                    <li><span class="label">Gender</span><span class="value"><?= ucfirst(htmlspecialchars($student['gender'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></span></li>
                    <li><span class="label">Phone</span><span class="value"><?= htmlspecialchars($student['phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                    <li><span class="label">Email</span><span class="value"><?= htmlspecialchars($student['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                    <li><span class="label">Address</span><span class="value"><?= htmlspecialchars($student['address'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                </ul>
            </div>

            <div class="card mb-2">
                <h4 style="margin-bottom:0.75rem;">Guardian Information</h4>
                <ul class="profile-card__details">
                    <li><span class="label">Guardian Name</span><span class="value"><?= htmlspecialchars($student['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                    <li><span class="label">Guardian Phone</span><span class="value"><?= htmlspecialchars($student['guardian_phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                    <li><span class="label">Guardian Email</span><span class="value"><?= htmlspecialchars($student['guardian_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                </ul>
            </div>

            <div class="card">
                <h4 style="margin-bottom:0.75rem;">Enrollment Summary</h4>
                <p class="text-sm text-muted">
                    Enrolled in <strong><?= count($enrollments) ?></strong> class(es).
                    <?php
                    $activeEnrollments = array_filter($enrollments, fn($e) => ($e['status'] ?? '') === 'active');
                    ?>
                    <strong><?= count($activeEnrollments) ?></strong> active.
                </p>
            </div>
        </div>

        <!-- Enrollments Tab -->
        <div class="tab-panel" id="tab-enrollments" style="display:none;">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Scholarship Type</th>
                            <th>Status</th>
                            <th>Enrolled Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($enrollments)): ?>
                            <?php foreach ($enrollments as $enr): ?>
                                <tr>
                                    <td><?= htmlspecialchars($enr['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php
                                        $schType = $enr['scholarship_type'] ?? 'full_payment';
                                        echo match ($schType) {
                                            'half_payment' => '<span class="badge badge--info">Half Payment (50%)</span>',
                                            'free_card'    => '<span class="badge badge--success">Free Card (100%)</span>',
                                            default        => '<span class="badge badge--neutral">Full Payment</span>',
                                        };
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $enrStatus = $enr['status'] ?? 'active';
                                        echo '<span class="badge badge--' . ($enrStatus === 'active' ? 'active' : 'neutral') . '">' . ucfirst(htmlspecialchars($enrStatus, ENT_QUOTES, 'UTF-8')) . '</span>';
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($enr['enrolled_at'] ?? $enr['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="table-empty">No enrollments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments Tab -->
        <div class="tab-panel" id="tab-payments" style="display:none;">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Class</th>
                            <th>Amount (LKR)</th>
                            <th>Status</th>
                            <th>Paid Date</th>
                            <th>Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $pmt): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pmt['month'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($pmt['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="font-medium">LKR <?= number_format((float) ($pmt['amount'] ?? 0), 2) ?></td>
                                    <td>
                                        <?php
                                        $pmtStatus = $pmt['status'] ?? 'pending';
                                        $pmtBadge = match ($pmtStatus) {
                                            'paid'    => 'badge--success',
                                            'pending' => 'badge--pending',
                                            'overdue' => 'badge--danger',
                                            default   => 'badge--neutral',
                                        };
                                        ?>
                                        <span class="badge <?= $pmtBadge ?>"><?= ucfirst(htmlspecialchars($pmtStatus, ENT_QUOTES, 'UTF-8')) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($pmt['paid_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($pmt['marked_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="table-empty">No payment records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Workflow History Tab -->
        <div class="tab-panel" id="tab-workflow-history" style="display:none;">
            <?php if (!empty($workflows)): ?>
                <div style="position:relative;padding-left:1.5rem;border-left:2px solid var(--border);">
                    <?php foreach ($workflows as $wf): ?>
                        <div style="position:relative;padding-bottom:1.5rem;">
                            <div style="position:absolute;left:-1.75rem;top:0.15rem;width:12px;height:12px;border-radius:50%;background:var(--primary);border:2px solid var(--surface);"></div>
                            <div class="card" style="padding:1rem;">
                                <div class="d-flex items-center justify-between flex-wrap gap-1">
                                    <span class="font-medium"><?= htmlspecialchars($wf['type'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="badge <?= ($wf['status'] ?? '') === 'completed' ? 'badge--success' : (($wf['status'] ?? '') === 'rejected' ? 'badge--danger' : 'badge--pending') ?>">
                                        <?= ucfirst(htmlspecialchars($wf['status'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-muted" style="margin:0.25rem 0 0;">
                                    Initiated by <?= htmlspecialchars($wf['initiated_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    on <?= htmlspecialchars($wf['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <?php if (!empty($wf['reason'])): ?>
                                    <p class="text-sm" style="margin:0.5rem 0 0;">Reason: <?= htmlspecialchars($wf['reason'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon">&#128260;</div>
                    <div class="empty-state__title">No workflow history</div>
                    <div class="empty-state__text">Workflow events for this student will appear here.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Audit Trail Tab -->
        <div class="tab-panel" id="tab-audit-trail" style="display:none;">
            <?php if (!empty($auditTrail)): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditTrail as $log): ?>
                                <tr>
                                    <td class="text-sm"><?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($log['user_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge badge--neutral"><?= htmlspecialchars($log['action'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="text-sm truncate" style="max-width:250px;"><?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon">&#128209;</div>
                    <div class="empty-state__title">No audit trail</div>
                    <div class="empty-state__text">Actions performed on this student will be logged here.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Deactivate Modal -->
<div class="modal-backdrop" id="deactivateModal" aria-hidden="true">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Deactivate Student</h3>
            <button type="button" class="modal__close" onclick="closeModal('deactivateModal')">&times;</button>
        </div>
        <form method="POST" action="/students/<?= (int) $student['id'] ?>/deactivate">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
            <div class="modal__body">
                <p>Are you sure you want to initiate deactivation for <strong><?= htmlspecialchars($student['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>?</p>
                <div class="form-group">
                    <label class="form-label" for="deactivate_reason">Reason <span class="required">*</span></label>
                    <textarea id="deactivate_reason" name="reason" class="form-control" required placeholder="Please provide a reason..."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" onclick="closeModal('deactivateModal')">Cancel</button>
                <button type="submit" class="btn btn--primary">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-backdrop" id="deleteModal" aria-hidden="true">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Delete Student</h3>
            <button type="button" class="modal__close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST" action="/students/<?= (int) $student['id'] ?>/delete">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
            <div class="modal__body">
                <p>Are you sure you want to initiate deletion for <strong><?= htmlspecialchars($student['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>? This will start a multi-step approval workflow.</p>
                <div class="form-group">
                    <label class="form-label" for="delete_reason">Reason <span class="required">*</span></label>
                    <textarea id="delete_reason" name="reason" class="form-control" required placeholder="Please provide a reason..."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn--danger">Submit for Deletion</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-panel').forEach(function (p) { p.style.display = 'none'; });
    document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
    var panel = document.getElementById('tab-' + tabId);
    if (panel) panel.style.display = 'block';
    if (btn) btn.classList.add('active');
}

// Modal backdrop click to close
document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
            closeModal(backdrop.id);
        }
    });
});
</script>
