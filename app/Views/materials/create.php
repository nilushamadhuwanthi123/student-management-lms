<?php
/**
 * Posting form.
 *
 * File and link are mutually exclusive, and the form says so rather
 * than letting the teacher fill both in and get a server-side refusal
 * after the upload has already gone over a slow connection.
 */

$class = $class ?? [];
$types = $types ?? [];
$month = $month ?? date('Y-m-01');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Post material') ?></h1>
        <p class="text-sm text-muted">
            <?= te('To') ?> <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials?month=<?= urlencode($month) ?>"
           class="btn btn--ghost">&larr; <?= te('Back') ?></a>
    </div>
</div>

<div class="card" style="max-width:820px;">
    <form method="POST"
          action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials/create"
          enctype="multipart/form-data">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="card__body">

            <div class="form-group">
                <label class="form-label" for="title">
                    <?= te('Title') ?> <span style="color:var(--danger);">*</span>
                </label>
                <input type="text" id="title" name="title" class="form-control" required
                       maxlength="255"
                       placeholder="e.g. Chemical Reactions - full lesson recording">
            </div>

            <?php include __DIR__ . '/../components/topic-select.php'; ?>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">
                <div class="form-group">
                    <label class="form-label" for="material_type">
                        <?= te('Type') ?> <span style="color:var(--danger);">*</span>
                    </label>
                    <select id="material_type" name="material_type" class="form-control" required>
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="month_year"><?= te('Month this belongs to') ?></label>
                    <input type="month" id="month_year" name="month_year" class="form-control"
                           value="<?= date('Y-m', strtotime($month)) ?>">
                    <small class="text-muted">
                        <?= te("Students who settled this month's fee can open it.") ?>
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="lesson_date"><?= te('Lesson date') ?></label>
                    <input type="date" id="lesson_date" name="lesson_date" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label" for="duration_minutes"><?= te('Length (minutes)') ?></label>
                    <input type="number" id="duration_minutes" name="duration_minutes"
                           class="form-control" min="1" max="600" placeholder="e.g. 95">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= te('Description') ?></label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="What is in it, and what the class should do with it."></textarea>
            </div>

            <hr style="border:none; border-top:1px solid var(--border); margin:1.5rem 0;">

            <h3 style="margin:0 0 .35rem;"><?= te('Where is it?') ?></h3>
            <p class="text-sm text-muted" style="margin:0 0 1rem;">
                <?= te('A link or a file — one or the other. Zoom recordings and YouTube videos belong on a link; notes and past papers are better uploaded.') ?>
            </p>

            <div class="form-group">
                <label class="form-label" for="external_url"><?= te('Link') ?></label>
                <input type="url" id="external_url" name="external_url" class="form-control"
                       placeholder="https://zoom.us/rec/share/...">
            </div>

            <div style="text-align:center; margin:.75rem 0; color:var(--text-muted); font-size:.875rem;">
                &mdash; <?= te('or') ?> &mdash;
            </div>

            <div class="form-group">
                <label class="form-label" for="material_file"><?= te('Upload a file') ?></label>
                <input type="file" id="material_file" name="material_file" class="form-control"
                       accept=".pdf,.png,.jpg,.jpeg">
                <small class="text-muted">
                    <?= te('PDF, PNG or JPG, up to 20 MB. Put larger videos on a link instead.') ?>
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="status"><?= te('Visibility') ?></label>
                <select id="status" name="status" class="form-control">
                    <option value="published"><?= te('Published — the class can see it') ?></option>
                    <option value="draft"><?= te('Draft — only you can see it') ?></option>
                </select>
            </div>
        </div>

        <div class="card__footer" style="display:flex; gap:.75rem; justify-content:flex-end;">
            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials?month=<?= urlencode($month) ?>"
               class="btn btn--ghost"><?= te('Cancel') ?></a>
            <button type="submit" class="btn btn--primary"><?= te('Post it') ?></button>
        </div>
    </form>
</div>

<script>
(function () {
    // Keep the two sources exclusive while typing, so the teacher finds
    // out before uploading rather than after.
    var url = document.getElementById('external_url');
    var file = document.getElementById('material_file');
    if (!url || !file) { return; }

    function sync() {
        var hasUrl = url.value.trim() !== '';
        var hasFile = file.files && file.files.length > 0;

        file.disabled = hasUrl;
        url.disabled = hasFile;

        file.parentElement.style.opacity = hasUrl ? '.5' : '1';
        url.parentElement.style.opacity = hasFile ? '.5' : '1';
    }

    url.addEventListener('input', sync);
    file.addEventListener('change', sync);
})();
</script>
