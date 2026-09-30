<?php
/**
 * Workflows - Pending Approvals
 *
 * Variables: $workflows, $pendingCount
 */

$workflows    = $workflows ?? [];
$pendingCount = $pendingCount ?? count($workflows);
?>

<div class="page-header">
    <h1 class="page-header__title">
        Pending Approvals
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge--pending" style="vertical-align:middle;font-size:0.875rem;"><?= (int) $pendingCount ?></span>
        <?php endif; ?>
    </h1>
</div>

<?php if (!empty($workflows)): ?>
    <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($workflows as $wf): ?>
            <div class="approval-card animate-slide-up">
                <div class="approval-card__header">
                    <span class="approval-card__type"><?= htmlspecialchars($wf['type'] ?? 'Approval', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="approval-card__date"><?= htmlspecialchars($wf['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="approval-card__title"><?= htmlspecialchars($wf['student_name'] ?? 'Unknown Student', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="approval-card__meta">
                    Initiated by <strong><?= htmlspecialchars($wf['initiated_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($wf['reason'])): ?>
                        &mdash; <?= htmlspecialchars($wf['reason'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($wf['current_step'])): ?>
                    <div class="text-sm text-muted">
                        Current step: <strong><?= htmlspecialchars($wf['current_step'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if (!empty($wf['current_approver'])): ?>
                            (awaiting <?= htmlspecialchars($wf['current_approver'], ENT_QUOTES, 'UTF-8') ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="approval-card__actions">
                    <form method="POST" action="/workflows/<?= (int) $wf['id'] ?>/approve" style="display:inline;">
                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                        <button type="submit" class="btn btn--primary btn--sm" onclick="return confirm('Approve this workflow?')">
                            Approve
                        </button>
                    </form>
                    <button type="button" class="btn btn--danger btn--sm" onclick="openRejectModal(<?= (int) $wf['id'] ?>, '<?= htmlspecialchars($wf['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>')">
                        Reject
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">&#9989;</div>
            <div class="empty-state__title">All caught up!</div>
            <div class="empty-state__text">There are no pending approvals at the moment.</div>
        </div>
    </div>
<?php endif; ?>

<!-- Reject Modal -->
<div class="modal-backdrop" id="rejectModal" aria-hidden="true">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Reject Workflow</h3>
            <button type="button" class="modal__close" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <form method="POST" id="rejectForm" action="">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
            <div class="modal__body">
                <p>Reject the workflow for <strong id="rejectStudentName"></strong>?</p>
                <div class="form-group">
                    <label class="form-label" for="reject_reason">Reason <span class="required">*</span></label>
                    <textarea id="reject_reason" name="reason" class="form-control" required placeholder="Provide a reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn--danger">Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(workflowId, studentName) {
    document.getElementById('rejectForm').action = '/workflows/' + workflowId + '/reject';
    document.getElementById('rejectStudentName').textContent = studentName;
    document.getElementById('reject_reason').value = '';
    openModal('rejectModal');
}

document.getElementById('rejectModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal('rejectModal');
});
</script>
