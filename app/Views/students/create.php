<?php
/**
 * Students - Registration Form
 *
 * Variables: $institutes, $classes, $errors, $old
 */

$institutes = $institutes ?? [];
$classes    = $classes ?? [];
$errors     = $errors ?? [];
$old        = $old ?? [];
?>

<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<div class="page-header">
    <h1 class="page-header__title">Register New Student</h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash flash--error mb-2">
        <span>&#10007;</span>
        <span>Please fix the following errors:</span>
    </div>
<?php endif; ?>

<form method="POST" action="/students" data-validate class="card" style="max-width:800px;">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <!-- Personal Information -->
    <h3 style="margin-bottom:1rem;">Personal Information</h3>

    <div class="form-row form-row--2">
        <div class="form-group">
            <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            <?php if (isset($errors['full_name'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php if (isset($errors['date_of_birth'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['date_of_birth'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-row form-row--3">
        <div class="form-group">
            <label class="form-label" for="phone1">Phone</label>
            <input type="tel" id="phone1" name="phone1" class="form-control <?= isset($errors['phone1']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old['phone1'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+94 XX XXX XXXX">
            <?php if (isset($errors['phone1'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['phone1'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="whatsapp">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" class="form-control <?= isset($errors['whatsapp']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+94 XX XXX XXXX">
            <?php if (isset($errors['whatsapp'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['whatsapp'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="address">Address</label>
        <textarea id="address" name="address" class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" rows="2"><?= htmlspecialchars($old['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        <?php if (isset($errors['address'])): ?>
            <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['address'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>

    <!-- Guardian Information -->
    <h3 style="margin-top:1.5rem;margin-bottom:1rem;">Guardian Information</h3>

    <div class="form-row form-row--2">
        <div class="form-group">
            <label class="form-label" for="guardian_name">Guardian Name</label>
            <input type="text" id="guardian_name" name="guardian_name" class="form-control" value="<?= htmlspecialchars($old['guardian_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-hint">If a parent account exists, it will be linked automatically.</div>
        </div>
        <div class="form-group">
            <label class="form-label" for="guardian_phone">Guardian Phone</label>
            <input type="tel" id="guardian_phone" name="guardian_phone" class="form-control" value="<?= htmlspecialchars($old['guardian_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+94 XX XXX XXXX">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="guardian_email">Guardian Email</label>
        <input type="email" id="guardian_email" name="guardian_email" class="form-control" value="<?= htmlspecialchars($old['guardian_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <!-- Enrollment Details -->
    <h3 style="margin-top:1.5rem;margin-bottom:1rem;">Enrollment Details</h3>

    <div class="form-group">
        <label class="form-label" for="institute_id">Institute <span class="required">*</span></label>
        <select id="institute_id" name="institute_id" class="form-control <?= isset($errors['institute_id']) ? 'is-invalid' : '' ?>" required>
            <option value="">Select Institute</option>
            <?php foreach ($institutes as $inst): ?>
                <option value="<?= (int) $inst['id'] ?>" <?= ($old['institute_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['institute_id'])): ?>
            <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['institute_id'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group" id="classSelectionGroup">
        <label class="form-label">Classes &amp; Scholarship Type <span class="required">*</span></label>
        <div id="classCheckboxes" style="display:flex;flex-direction:column;gap:0.75rem;">
            <?php if (!empty($classes)): ?>
                <?php foreach ($classes as $cls): ?>
                    <?php $isChecked = in_array($cls['id'], (array) ($old['class_ids'] ?? [])); ?>
                    <div class="card" style="padding:0.75rem 1rem;">
                        <div class="d-flex items-center justify-between flex-wrap gap-1">
                            <label class="form-check" style="margin-bottom:0;">
                                <input type="checkbox" name="class_ids[]" value="<?= (int) $cls['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                <?= htmlspecialchars($cls['class_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($cls['fee'])): ?>
                                    <span class="text-muted text-sm">(LKR <?= number_format((float) $cls['fee'], 2) ?>)</span>
                                <?php endif; ?>
                            </label>
                            <select name="scholarship_type[<?= (int) $cls['id'] ?>]" class="form-control" style="width:auto;min-width:160px;">
                                <option value="full_payment" <?= ($old['scholarship_type'][$cls['id']] ?? '') === 'full_payment' ? 'selected' : '' ?>>Full Payment</option>
                                <option value="half_payment" <?= ($old['scholarship_type'][$cls['id']] ?? '') === 'half_payment' ? 'selected' : '' ?>>Half Payment (50%)</option>
                                <option value="free_card" <?= ($old['scholarship_type'][$cls['id']] ?? '') === 'free_card' ? 'selected' : '' ?>>Free Card (100%)</option>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-sm">Select an institute first to see available classes.</p>
            <?php endif; ?>
        </div>
        <?php if (isset($errors['class_ids'])): ?>
            <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['class_ids'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>

    <!-- Submit -->
    <div class="d-flex gap-1" style="margin-top:1.5rem;">
        <button type="submit" class="btn btn--primary">Register Student</button>
        <a href="/students" class="btn btn--ghost">Cancel</a>
    </div>
</form>

<script>
// AJAX load classes when institute changes
document.getElementById('institute_id').addEventListener('change', function () {
    var instituteId = this.value;
    var container = document.getElementById('classCheckboxes');
    if (!instituteId) {
        container.innerHTML = '<p class="text-muted text-sm">Select an institute first to see available classes.</p>';
        return;
    }
    container.innerHTML = '<div class="spinner-overlay"><div class="spinner spinner--sm"></div></div>';

    fetch('/api/classes-by-institute/' + instituteId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) { return res.json(); })
    .then(function (classes) {
        if (!classes.length) {
            container.innerHTML = '<p class="text-muted text-sm">No classes available for this institute.</p>';
            return;
        }
        var html = '';
        classes.forEach(function (cls) {
            var fee = cls.fee ? ' (LKR ' + parseFloat(cls.fee).toLocaleString('en-LK', {minimumFractionDigits: 2}) + ')' : '';
            html += '<div class="card" style="padding:0.75rem 1rem;">'
                + '<div class="d-flex items-center justify-between flex-wrap gap-1">'
                + '<label class="form-check" style="margin-bottom:0;">'
                + '<input type="checkbox" name="class_ids[]" value="' + cls.id + '"> '
                + cls.name + '<span class="text-muted text-sm">' + fee + '</span>'
                + '</label>'
                + '<select name="scholarship_type[' + cls.id + ']" class="form-control" style="width:auto;min-width:160px;">'
                + '<option value="full_payment">Full Payment</option>'
                + '<option value="half_payment">Half Payment (50%)</option>'
                + '<option value="free_card">Free Card (100%)</option>'
                + '</select>'
                + '</div></div>';
        });
        container.innerHTML = html;
    })
    .catch(function () {
        container.innerHTML = '<p class="text-muted text-sm">Failed to load classes. Please try again.</p>';
    });
});
</script>
