<?php
/**
 * Add a recording: a title and a link. Branch, grade and class are
 * already fixed by which class this form was opened from.
 */

$class = $class ?? [];
$month = $month ?? date('Y-m-01');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Add a recording') ?></h1>
        <p class="text-sm text-muted">
            <?= te('To') ?> <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/recordings" class="btn btn--ghost">&larr; <?= te('Back') ?></a>
    </div>
</div>

<div class="card" style="max-width:820px;">
    <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/recordings/create">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="card__body">
            <div class="form-group">
                <label class="form-label" for="title"><?= te('Title') ?> <span style="color:var(--danger);">*</span></label>
                <input type="text" id="title" name="title" class="form-control" required maxlength="255"
                       placeholder="<?= te('e.g. Chemical Reactions - full lesson recording') ?>">
            </div>

            <?php include __DIR__ . '/../components/topic-select.php'; ?>

            <div class="form-group">
                <label class="form-label" for="video_url"><?= te('Recording link') ?> <span style="color:var(--danger);">*</span></label>
                <input type="url" id="video_url" name="video_url" class="form-control" required
                       placeholder="https://youtube.com/watch?v=... or https://drive.google.com/...">
                <small class="text-muted"><?= te('A YouTube, Google Drive or other video link.') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label" for="thumbnail_url"><?= te('Thumbnail link (optional)') ?></label>
                <input type="url" id="thumbnail_url" name="thumbnail_url" class="form-control"
                       placeholder="https://...">
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">
                <div class="form-group">
                    <label class="form-label" for="month_year"><?= te('Month this belongs to') ?></label>
                    <input type="month" id="month_year" name="month_year" class="form-control"
                           value="<?= date('Y-m', strtotime($month)) ?>">
                    <small class="text-muted"><?= te("Students who settled this month's fee can open it.") ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="recording_date"><?= te('Lesson date') ?></label>
                    <input type="date" id="recording_date" name="recording_date" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= te('Description') ?></label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="<?= te('What is in it, and what the class should do with it.') ?>"></textarea>
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
            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/recordings" class="btn btn--ghost"><?= te('Cancel') ?></a>
            <button type="submit" class="btn btn--primary"><?= te('Add recording') ?></button>
        </div>
    </form>
</div>
