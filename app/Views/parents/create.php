<?php
/**
 * Add a guardian to an already-registered student.
 *
 * Variables: $student
 */

$student = $student ?? [];
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('Add a guardian') ?></h1>
    <p class="text-muted" style="margin:0;"><?= te('for') ?> <?= $e($student['full_name'] ?? '') ?> (<?= $e($student['student_id_display'] ?? '') ?>)</p>
</div>

<form method="POST" action="<?= BASE_URL ?>/students/<?= (int) ($student['id'] ?? 0) ?>/parents/create" data-validate class="card" style="max-width:600px;">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <div class="form-group">
        <label class="form-label" for="parent_name"><?= te('Guardian Name') ?> <span class="required">*</span></label>
        <input type="text" id="parent_name" name="parent_name" class="form-control" required>
    </div>

    <div class="form-row form-row--2">
        <div class="form-group">
            <label class="form-label" for="phone1"><?= te('Phone (login number)') ?> <span class="required">*</span></label>
            <input type="tel" id="phone1" name="phone1" class="form-control" placeholder="07XXXXXXXX" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="phone2"><?= te('Second Phone') ?></label>
            <input type="tel" id="phone2" name="phone2" class="form-control" placeholder="07XXXXXXXX">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="whatsapp"><?= te('WhatsApp') ?></label>
        <input type="tel" id="whatsapp" name="whatsapp" class="form-control" placeholder="07XXXXXXXX">
    </div>

    <div class="form-hint" style="margin-bottom:1rem;"><?= te('A temporary password will be generated and shown once, the same way it is for a new student.') ?></div>

    <div style="display:flex; gap:.5rem;">
        <button type="submit" class="btn btn--primary"><?= te('Add guardian') ?></button>
        <a href="<?= BASE_URL ?>/students/<?= (int) ($student['id'] ?? 0) ?>" class="btn btn--ghost"><?= te('Cancel') ?></a>
    </div>
</form>
