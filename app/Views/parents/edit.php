<?php
/**
 * Edit an existing guardian's details.
 *
 * Variables: $parent
 */

$parent = $parent ?? [];
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('Edit guardian') ?></h1>
</div>

<form method="POST" action="<?= BASE_URL ?>/parents/<?= (int) ($parent['id'] ?? 0) ?>/edit" data-validate class="card" style="max-width:600px;">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <div class="form-group">
        <label class="form-label" for="parent_name"><?= te('Guardian Name') ?> <span class="required">*</span></label>
        <input type="text" id="parent_name" name="parent_name" class="form-control" value="<?= $e($parent['parent_name'] ?? '') ?>" required>
    </div>

    <div class="form-row form-row--2">
        <div class="form-group">
            <label class="form-label" for="phone1"><?= te('Phone (login number)') ?> <span class="required">*</span></label>
            <input type="tel" id="phone1" name="phone1" class="form-control" value="<?= $e($parent['phone1'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="phone2"><?= te('Second Phone') ?></label>
            <input type="tel" id="phone2" name="phone2" class="form-control" value="<?= $e($parent['phone2'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="whatsapp"><?= te('WhatsApp') ?></label>
        <input type="tel" id="whatsapp" name="whatsapp" class="form-control" value="<?= $e($parent['whatsapp'] ?? '') ?>">
    </div>

    <div class="form-hint" style="margin-bottom:1rem;"><?= te('Changing the phone number changes which number this guardian logs in with.') ?></div>

    <div style="display:flex; gap:.5rem;">
        <button type="submit" class="btn btn--primary"><?= te('Save changes') ?></button>
        <a href="<?= BASE_URL ?>/students/<?= (int) ($parent['student_id'] ?? 0) ?>" class="btn btn--ghost"><?= te('Cancel') ?></a>
    </div>
</form>
