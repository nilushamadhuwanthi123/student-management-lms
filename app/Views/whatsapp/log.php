<?php
/**
 * The send log.
 *
 * 'Sent' here means a person confirmed sending it. There is no delivery
 * receipt on this channel and the column header says so, because a log
 * that implies more certainty than it has is worse than no log.
 */

$messages = $messages ?? [];
$counts   = $counts ?? [];
$status   = $status ?? null;

$tone = [
    'sent'    => 'var(--success)',
    'queued'  => 'var(--warning)',
    'skipped' => 'var(--text-muted)',
    'failed'  => 'var(--danger)',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Message log') ?></h1>
        <p class="text-sm text-muted"><?= te('Every WhatsApp message composed here') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/whatsapp/reminders" class="btn btn--primary"><?= te('Send reminders') ?></a>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem;">
    <?php foreach (['sent' => te('Confirmed sent'), 'queued' => te('Opened, unconfirmed'), 'skipped' => te('Skipped'), 'failed' => te('Failed')] as $key => $label): ?>
        <a href="<?= BASE_URL ?>/whatsapp/log?status=<?= $key ?>" class="card"
           style="padding:1rem; text-decoration:none; color:inherit; <?= $status === $key ? 'border-color:var(--primary);' : '' ?>">
            <div class="text-sm text-muted"><?= $label ?></div>
            <div style="font-size:1.6rem; font-weight:700; margin-top:.2rem; color:<?= $tone[$key] ?>;">
                <?= (int) ($counts[$key] ?? 0) ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($status !== null): ?>
    <p class="text-sm" style="margin-bottom:1rem;">
        <?= te('Showing {status} only.', ['status' => '<strong>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</strong>']) ?>
        <a href="<?= BASE_URL ?>/whatsapp/log"><?= te('Show everything') ?></a>
    </p>
<?php endif; ?>

<div class="card">
    <?php if ($messages === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;">
            <div style="font-size:2.5rem;">&#128172;</div>
            <h4 style="margin:.75rem 0 .35rem;"><?= te('Nothing sent yet') ?></h4>
            <p class="text-sm text-muted" style="margin:0;"><?= te('Fee reminders you compose will appear here.') ?></p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= te('When') ?></th>
                        <th><?= te('About') ?></th>
                        <th><?= te('To') ?></th>
                        <th><?= te('Message') ?></th>
                        <th><?= te('Status') ?></th>
                        <th><?= te('By') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td class="text-sm text-muted" style="white-space:nowrap;">
                            <?= date('j M, g:i a', strtotime($m['created_at'])) ?>
                        </td>
                        <td>
                            <div style="font-weight:600;">
                                <?= htmlspecialchars((string) ($m['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($m['class_name'])): ?>
                                <div class="text-sm text-muted"><?= htmlspecialchars($m['class_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= htmlspecialchars($m['recipient_name'], ENT_QUOTES, 'UTF-8') ?>
                            <div class="text-muted">+<?= htmlspecialchars($m['recipient_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td class="text-sm text-muted" style="max-width:38ch;">
                            <?= htmlspecialchars(mb_substr($m['body'], 0, 120), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($m['body']) > 120 ? '&hellip;' : '' ?>
                        </td>
                        <td>
                            <span class="badge" style="background:var(--surface-sunken); color:<?= $tone[$m['send_status']] ?? 'var(--text-muted)' ?>; text-transform:capitalize;">
                                <?= te(ucfirst($m['send_status'])) ?>
                            </span>
                            <?php if (!empty($m['note'])): ?>
                                <div class="text-sm text-muted"><?= htmlspecialchars($m['note'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-muted">
                            <?= htmlspecialchars((string) ($m['sent_by_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
