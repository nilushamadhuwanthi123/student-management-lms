<?php
/**
 * Fee reminders: everyone unpaid for a month, each with the message
 * already written.
 *
 * Students with no usable number are listed separately rather than
 * dropped. A reminder nobody can send is still something the office
 * needs to know about, and silently omitting the row makes it look as
 * though everybody was contacted.
 */

$recipients  = $recipients ?? [];
$noNumber    = $noNumber ?? [];
$months      = $months ?? [];
$month       = $month ?? date('Y-m-01');
$classes     = $classes ?? [];
$institutes  = $institutes ?? [];
$templates   = $templates ?? [];
$templateKey = $templateKey ?? 'fee_reminder';
$filters     = $filters ?? [];
$totalOwed   = $totalOwed ?? 0.0;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('WhatsApp reminders') ?></h1>
        <p class="text-sm text-muted">
            <?= count($recipients) ?> <?= te('to send') ?>
            &middot; Rs. <?= number_format((float) $totalOwed, 2) ?> <?= te('outstanding') ?>
            &middot; <?= date('F Y', strtotime($month)) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/whatsapp/log" class="btn btn--ghost"><?= te('Message log') ?></a>
    </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
    <form method="GET" action="<?= BASE_URL ?>/whatsapp/reminders" class="card__body"
          style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:1rem; align-items:end;">
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="month"><?= te('Month') ?></label>
            <select id="month" name="month" class="form-control">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $month ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin:0;">
            <label class="form-label" for="institute_id"><?= te('Branch') ?></label>
            <select id="institute_id" name="institute_id" class="form-control">
                <option value=""><?= te('All branches') ?></option>
                <?php foreach ($institutes as $i): ?>
                    <option value="<?= (int) $i['id'] ?>" <?= (int) ($filters['institute_id'] ?? 0) === (int) $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin:0;">
            <label class="form-label" for="class_id"><?= te('Class') ?></label>
            <select id="class_id" name="class_id" class="form-control">
                <option value=""><?= te('All classes') ?></option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($filters['class_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin:0;">
            <label class="form-label" for="template"><?= te('Wording') ?></label>
            <select id="template" name="template" class="form-control">
                <?php foreach ($templates as $t): ?>
                    <option value="<?= htmlspecialchars($t['template_key'], ENT_QUOTES, 'UTF-8') ?>"
                        <?= $t['template_key'] === $templateKey ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn--primary"><?= te('Apply') ?></button>
    </form>
</div>

<div class="alert" style="background:var(--info-light); border-left:3px solid var(--primary);
     padding:.875rem 1rem; border-radius:var(--radius-sm); margin-bottom:1.5rem;">
    <strong><?= te('How this sends.') ?></strong>
    <span class="text-sm">
        <?= te('Each button opens WhatsApp with the message already written — you press send there. Nothing leaves this system on its own, so the log records what you confirmed sending, not a delivery receipt.') ?>
    </span>
</div>

<?php if ($recipients === [] && $noNumber === []): ?>
    <div class="card" style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#9989;</div>
        <h4 style="margin:.75rem 0 .35rem;"><?= te('Everyone has paid for {month}', ['month' => date('F', strtotime($month))]) ?></h4>
        <p class="text-sm text-muted" style="margin:0;"><?= te('Nothing to chase.') ?></p>
    </div>
<?php else: ?>

<?php if ($recipients !== []): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card__header" style="display:flex; justify-content:space-between; align-items:center; gap:1rem;">
            <h3 style="margin:0;"><?= te('Ready to send') ?></h3>
            <span class="text-sm text-muted"><?= count($recipients) ?> <?= te('message') ?><?= count($recipients) === 1 ? '' : 's' ?></span>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= te('Student') ?></th>
                        <th><?= te('Goes to') ?></th>
                        <th class="text-right"><?= te('Owing') ?></th>
                        <th><?= te('Message') ?></th>
                        <th class="text-right"><?= te('Send') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recipients as $r):
                    $row = $r['row'];
                    $overdue = $row['payment_status'] === 'overdue';
                ?>
                    <tr data-row>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($row['student_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-sm text-muted">
                                <?= htmlspecialchars($row['class_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($row['institute_name'])): ?>
                                    &middot; <?= htmlspecialchars($row['institute_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($overdue): ?>
                                <span class="badge" style="background:var(--danger-light); color:var(--danger);"><?= te('Overdue') ?></span>
                            <?php endif; ?>
                            <?php if ((int) $row['reminders_sent'] > 0): ?>
                                <span class="badge" style="background:var(--surface-sunken); color:var(--text-muted);">
                                    <?= (int) $row['reminders_sent'] ?> <?= te('sent before') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= htmlspecialchars($r['to']['name'], ENT_QUOTES, 'UTF-8') ?>
                            <div class="text-muted">
                                +<?= htmlspecialchars($r['to']['phone'], ENT_QUOTES, 'UTF-8') ?>
                                <?= $r['to']['type'] === 'student' ? ' (' . te('student') . ')' : '' ?>
                            </div>
                        </td>
                        <td class="text-right" style="font-weight:600; color:<?= $overdue ? 'var(--danger)' : 'var(--warning)' ?>;">
                            Rs. <?= number_format((float) $r['outstanding'], 2) ?>
                        </td>
                        <td class="text-sm text-muted" style="max-width:34ch;">
                            <?= htmlspecialchars(mb_substr($r['body'], 0, 110), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($r['body']) > 110 ? '&hellip;' : '' ?>
                        </td>
                        <td class="text-right">
                            <button type="button" class="btn btn--primary btn--sm" data-send
                                    data-link="<?= htmlspecialchars($r['link'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-phone="<?= htmlspecialchars($r['to']['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-name="<?= htmlspecialchars($r['to']['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-type="<?= htmlspecialchars($r['to']['type'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-student="<?= (int) $row['student_id'] ?>"
                                    data-class="<?= (int) $row['class_id'] ?>"
                                    data-payment="<?= (int) $row['payment_id'] ?>"
                                    data-template="<?= htmlspecialchars($templateKey, ENT_QUOTES, 'UTF-8') ?>"
                                    data-body="<?= htmlspecialchars($r['body'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= te('WhatsApp') ?>
                            </button>
                            <span class="text-sm" data-state style="display:none;"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($noNumber !== []): ?>
    <div class="card" style="border-color:var(--warning);">
        <div class="card__header">
            <h3 style="margin:0;"><?= te('No usable number') ?> &mdash; <?= count($noNumber) ?></h3>
            <p class="text-sm text-muted" style="margin:.25rem 0 0;">
                <?= te('These owe money too, but there is no mobile on record to message.') ?>
            </p>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= te('Student') ?></th><th><?= te('Class') ?></th><th><?= te('On record') ?></th><th class="text-right"><?= te('Owing') ?></th></tr></thead>
                <tbody>
                <?php foreach ($noNumber as $row): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($row['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($row['class_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-sm text-muted">
                            <?= htmlspecialchars((string) ($row['parent_phone'] ?: $row['student_phone'] ?: te('nothing')), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-right">
                            Rs. <?= number_format((float) $row['amount_due'] - (float) $row['amount_paid'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>

<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    function post(url, data) {
        var body = new FormData();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        body.append('_csrf_token', token);
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    document.querySelectorAll('[data-send]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('[data-row]');
            var state = row.querySelector('[data-state]');

            btn.disabled = true;
            btn.textContent = 'Opening...';

            // Logged before the link opens, so an attempt is recorded
            // even if the teacher never comes back to confirm.
            post('<?= BASE_URL ?>/whatsapp/queue', {
                phone: btn.dataset.phone,
                recipient_name: btn.dataset.name,
                recipient_type: btn.dataset.type,
                student_id: btn.dataset.student,
                class_id: btn.dataset.class,
                payment_id: btn.dataset.payment,
                template_key: btn.dataset.template,
                body: btn.dataset.body
            }).then(function (res) {
                if (!res.ok) {
                    btn.disabled = false;
                    btn.textContent = 'WhatsApp';
                    state.style.display = '';
                    state.style.color = 'var(--danger)';
                    state.textContent = res.message || 'Could not log that.';
                    return;
                }

                if (res.gateway && res.sent) {
                    btn.style.display = 'none';
                    state.style.display = '';
                    state.style.color = '#16a34a';
                    state.innerHTML = '<strong style="color:#16a34a;">&#10003; ' + (window.__te ? window.__te('Sent via WA Client') : 'Sent via WA Client') + '</strong>';
                    return;
                }

                window.open(btn.dataset.link, '_blank', 'noopener');

                // The teacher is the only one who knows whether it
                // actually went, so the row asks.
                btn.style.display = 'none';
                state.style.display = '';
                state.style.color = 'var(--text-secondary)';
                state.innerHTML = 'Sent it? ';

                var yes = document.createElement('button');
                yes.type = 'button';
                yes.className = 'btn btn--ghost btn--sm';
                yes.textContent = 'Yes';
                yes.style.color = 'var(--success)';

                var no = document.createElement('button');
                no.type = 'button';
                no.className = 'btn btn--ghost btn--sm';
                no.textContent = 'No';

                function settle(outcome, label, colour) {
                    post('<?= BASE_URL ?>/whatsapp/confirm', { id: res.id, outcome: outcome })
                        .then(function () {
                            state.textContent = label;
                            state.style.color = colour;
                        });
                }

                yes.addEventListener('click', function () { settle('sent', 'Sent', 'var(--success)'); });
                no.addEventListener('click', function () { settle('skipped', 'Skipped', 'var(--text-muted)'); });

                state.appendChild(yes);
                state.appendChild(no);
            }).catch(function () {
                btn.disabled = false;
                btn.textContent = 'WhatsApp';
            });
        });
    });
})();
</script>
