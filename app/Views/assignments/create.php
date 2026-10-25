<?php
/** Set an assignment for a class. */
$class = $class ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Set an assignment') ?></h1>
        <p class="text-sm text-muted"><?= $e($class['class_name'] ?? '') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments" class="btn btn--ghost">&larr; <?= te('Assignments') ?></a>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments/create" class="card" style="max-width:760px;">
    <?= csrfField() ?>
    <div class="card__body" style="display:grid; gap:1rem;">
        <div class="form-group">
            <label class="form-label" for="title"><?= te('Title') ?> *</label>
            <input type="text" id="title" name="title" class="form-control" maxlength="255" required>
        </div>
        <?php include __DIR__ . '/../components/topic-select.php'; ?>

        <div class="form-group">
            <label class="form-label" for="instructions"><?= te('Instructions') ?></label>
            <textarea id="instructions" name="instructions" class="form-control" rows="6" placeholder="<?= te('What should the students do? Which pages, which questions?') ?>"></textarea>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem;">
            <div class="form-group">
                <label class="form-label" for="due_at"><?= te('Due') ?> *</label>
                <input type="datetime-local" id="due_at" name="due_at" class="form-control" required
                       value="<?= date('Y-m-d\TH:i', strtotime('+7 days 20:00')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="total_marks"><?= te('Out of (marks)') ?> *</label>
                <input type="number" id="total_marks" name="total_marks" class="form-control" min="1" max="1000" step="0.5" value="10" required>
            </div>
        </div>
        <label style="display:flex; gap:.5rem; align-items:center;">
            <input type="checkbox" name="allow_file" value="1" checked>
            <?= te('Students may attach a photo or PDF of their work') ?>
        </label>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <button type="submit" name="action" value="publish" class="btn btn--primary"><?= te('Publish and tell the class') ?></button>
            <button type="submit" name="action" value="draft" class="btn btn--ghost"><?= te('Save as draft') ?></button>
        </div>
    </div>
</form>
