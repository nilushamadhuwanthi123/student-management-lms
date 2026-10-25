<?php
/** Inquiries from the public website. */
$items = $items ?? [];
$status = $status ?? null;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$new = count(array_filter($items, static fn (array $m): bool => $m['status'] === 'new'));
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Inquiries</h1>
        <p class="text-sm text-muted"><?= $new ?> new &middot; from the public website</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/inquiries" class="btn btn--<?= $status === null ? 'primary' : 'ghost' ?> btn--sm">All</a>
        <?php foreach (['new' => 'New', 'read' => 'Seen', 'responded' => 'Responded'] as $k => $l): ?>
            <a href="<?= BASE_URL ?>/inquiries?status=<?= $k ?>" class="btn btn--<?= $status === $k ? 'primary' : 'ghost' ?> btn--sm"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php if ($items === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted">No inquiries.</div></div>
<?php endif; ?>
<div style="display:grid; gap:.75rem;">
<?php foreach ($items as $m): ?>
    <div class="card" style="padding:1rem 1.15rem; <?= $m['status'] === 'new' ? 'border-left:4px solid var(--accent);' : '' ?>">
        <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
            <div style="min-width:0;">
                <div style="font-weight:700;"><?= $e($m['full_name']) ?>
                    <?php if (($m['kind'] ?? 'contact') === 'enrollment'): ?><span class="badge" style="background:var(--primary-light); color:var(--primary);">Enrolment</span><?php endif; ?></div>
                <div class="text-sm">
                    <?php if (!empty($m['phone'])): ?>&#128222; <a href="tel:<?= $e(preg_replace('/[^\d+]/', '', (string) $m['phone'])) ?>"><?= $e($m['phone']) ?></a><?php endif; ?>
                    <?php if (!empty($m['email'])): ?> &middot; <?= $e($m['email']) ?><?php endif; ?>
                </div>
                <div class="text-sm text-muted">
                    <?= $e($m['subject']) ?><?= !empty($m['grade']) ? ' &middot; Grade ' . $e($m['grade']) : '' ?><?= !empty($m['branch_name']) ? ' &middot; ' . $e($m['branch_name']) : '' ?><?= !empty($m['student_name']) ? ' &middot; Student: ' . $e($m['student_name']) : '' ?>
                    &middot; <?= $e(date('j M Y, g:i a', strtotime((string) $m['created_at']))) ?>
                </div>
                <?php if (!empty($m['message']) && $m['message'] !== '-'): ?><div style="margin-top:.45rem; white-space:pre-wrap; overflow-wrap:anywhere;"><?= $e($m['message']) ?></div><?php endif; ?>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/inquiries/<?= (int) $m['id'] ?>/status" style="display:flex; gap:.4rem; align-items:start;">
                <?= csrfField() ?>
                <select name="status" class="form-control form-control--sm">
                    <?php foreach (['new' => 'New', 'read' => 'Seen', 'responded' => 'Responded'] as $k => $l): ?><option value="<?= $k ?>" <?= $m['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn--ghost btn--sm" type="submit">Save</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
