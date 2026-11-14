<?php
/** Self-service enrolment requests (branch + class pick, slip upload) from the public website. */
$items = $items ?? [];
$status = $status ?? 'pending';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Enrolment Requests') ?></h1>
        <p class="text-sm text-muted"><?= te('New students who enrolled themselves on the website and uploaded a bank deposit slip.') ?></p>
    </div>
    <div class="page-header__actions">
        <?php foreach (['pending' => te('Pending'), 'approved' => te('Approved'), 'rejected' => te('Rejected')] as $k => $l): ?>
            <a href="<?= BASE_URL ?>/enrollment-requests?status=<?= $k ?>" class="btn btn--<?= $status === $k ? 'primary' : 'ghost' ?> btn--sm"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php if ($items === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No requests.') ?></div></div>
<?php endif; ?>
<div style="display:grid; gap:.75rem;">
<?php foreach ($items as $r): ?>
    <div class="card" style="padding:1rem 1.15rem; <?= $r['status'] === 'pending' ? 'border-left:4px solid var(--accent);' : '' ?>">
        <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
            <div style="min-width:0;">
                <div style="font-weight:700;">
                    <?= $e($r['student_name']) ?>
                    <span class="badge" style="background:var(--primary-light); color:var(--primary);"><?= $e($r['branch_name'] ?? '') ?></span>
                </div>
                <div class="text-sm">
                    &#128222; <a href="tel:<?= $e(preg_replace('/[^\d+]/', '', (string) $r['guardian_phone'])) ?>"><?= $e($r['guardian_phone']) ?></a>
                    <?php if (!empty($r['guardian_email'])): ?> &middot; <?= $e($r['guardian_email']) ?><?php endif; ?>
                    &middot; <?= te('Guardian:') ?> <?= $e($r['guardian_name']) ?>
                </div>
                <div class="text-sm text-muted">
                    <?= $e($r['class_name'] ?? '') ?><?= !empty($r['grade']) ? ' &middot; ' . $e($r['grade']) : '' ?>
                    &middot; <?= te('Payment type:') ?> <?= $e(ucwords(str_replace('_', ' ', (string) $r['scholarship_type']))) ?>
                    <?php if (!empty($r['amount'])): ?> &middot; LKR <?= number_format((float) $r['amount'], 2) ?><?php endif; ?>
                    <?php if (!empty($r['paid_on'])): ?> &middot; <?= $e(date('j M Y', strtotime((string) $r['paid_on']))) ?><?php endif; ?>
                    &middot; <?= $e(date('j M Y, g:i a', strtotime((string) $r['created_at']))) ?>
                </div>
                <div style="margin-top:.4rem;">
                    <a href="<?= BASE_URL ?>/enrollment-requests/<?= (int) $r['id'] ?>/slip" target="_blank" class="btn btn--ghost btn--sm">&#128206; <?= te('View bank slip') ?></a>
                </div>
                <?php if ($r['status'] === 'rejected' && !empty($r['review_note'])): ?>
                    <div class="text-sm" style="margin-top:.4rem; color:var(--danger);"><?= te('Rejected:') ?> <?= $e($r['review_note']) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($r['status'] === 'pending'): ?>
                <div style="display:flex; flex-direction:column; gap:.4rem; align-items:end;">
                    <form method="POST" action="<?= BASE_URL ?>/enrollment-requests/<?= (int) $r['id'] ?>/approve">
                        <?= csrfField() ?>
                        <button class="btn btn--primary btn--sm" type="submit" title="<?= te('Creates the student (and parent) account and enrols them in this class.') ?>">&#10003; <?= te('Approve & Create Account') ?></button>
                    </form>
                    <form method="POST" action="<?= BASE_URL ?>/enrollment-requests/<?= (int) $r['id'] ?>/reject" onsubmit="var r=prompt(<?= json_encode(te('Reason for rejecting this request:')) ?>); if(!r){return false;} this.querySelector('[name=reason]').value=r; return true;">
                        <?= csrfField() ?>
                        <input type="hidden" name="reason" value="">
                        <button class="btn btn--ghost btn--sm" type="submit"><?= te('Reject') ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
