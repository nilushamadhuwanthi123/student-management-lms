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
$kind  = $kind ?? '';

/*
 * One screen, one job.
 *
 * 'source' says which box this kind actually needs. Showing a link box
 * and an upload box side by side, with a paragraph explaining which to
 * use, made the teacher decide something the kind already decides.
 *
 * Video is 'url' on purpose. Uploads here are capped at 20 MB and the
 * server only accepts PDF, PNG and JPG by file signature -- a real lab
 * video is far bigger than that, and most shared hosting refuses the
 * upload before PHP sees it. Pretending otherwise would fail halfway
 * through, after she had waited for it. A link to YouTube (unlisted)
 * or Drive is the thing that works, so the form says so.
 */
$kinds = [
    'recording' => [
        'title'  => t('Post a lesson recording'),
        'lead'   => t('The Zoom recording of a class you taught.'),
        'source' => 'url',
        'label'  => t('Zoom share link'),
        'ph'     => 'https://zoom.us/rec/share/...',
        'help'   => t('Copy the share link from Zoom and paste it here.'),
        'eg'     => t('e.g. Chemical Reactions -- full lesson recording'),
    ],
    'reading' => [
        'title'  => t('Post notes or a paper'),
        'lead'   => t('A PDF or a photo: tutes, notes, a past paper.'),
        'source' => 'file',
        'label'  => t('Choose the file'),
        'help'   => t('PDF, PNG or JPG, up to 20 MB.'),
        'eg'     => t('e.g. Acids and Bases -- tute 3'),
    ],
    'video' => [
        'title'  => t('Post a lab or lesson video'),
        'lead'   => t('An experiment you filmed, or any other video.'),
        'source' => 'url',
        'label'  => t('Video link'),
        'ph'     => 'https://youtu.be/...',
        'help'   => t('Upload the video to YouTube as "Unlisted", or to Google Drive, and paste the link here. Videos are not stored on the class website -- they are too big for it, and the upload would fail halfway.'),
        'eg'     => t('e.g. Testing for starch -- lab demonstration'),
    ],
    'link' => [
        'title'  => t('Post a link'),
        'lead'   => t('Anything already on the web that they should read or watch.'),
        'source' => 'url',
        'label'  => t('Link'),
        'ph'     => 'https://...',
        'help'   => t('Check it opens for someone who is not signed in to your account.'),
        'eg'     => t('e.g. The water cycle -- short explainer'),
    ],
];
$k = $kinds[$kind] ?? null;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $k ? htmlspecialchars($k['title'], ENT_QUOTES, 'UTF-8') : te('Post material') ?></h1>
        <p class="text-sm text-muted">
            <?php if ($k): ?><?= htmlspecialchars($k['lead'], ENT_QUOTES, 'UTF-8') ?> &middot; <?php endif; ?>
            <?= te('To') ?> <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials?month=<?= urlencode($month) ?>"
           class="btn btn--ghost">&larr; <?= $k ? te('Post something else') : te('Back') ?></a>
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
                       placeholder="<?= $k ? htmlspecialchars($k['eg'], ENT_QUOTES, 'UTF-8') : te('e.g. Chemical Reactions -- full lesson recording') ?>">
            </div>

            <?php include __DIR__ . '/../components/topic-select.php'; ?>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">
                <?php if ($k): ?>
                    <input type="hidden" name="material_type" value="<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
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
                <?php endif; ?>

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

            <?php if ($k): ?>
                <h3 style="margin:0 0 .9rem;"><?= te('Where is it?') ?></h3>
            <?php endif; ?>

            <?php if ($k && $k['source'] === 'url'): ?>
                <?php /* This kind lives on a link, so that is the only box. */ ?>
                <div class="form-group">
                    <label class="form-label" for="external_url">
                        <?= htmlspecialchars($k['label'], ENT_QUOTES, 'UTF-8') ?> <span style="color:var(--danger);">*</span>
                    </label>
                    <input type="url" id="external_url" name="external_url" class="form-control" required
                           placeholder="<?= htmlspecialchars($k['ph'] ?? 'https://...', ENT_QUOTES, 'UTF-8') ?>">
                    <small class="text-muted"><?= htmlspecialchars($k['help'], ENT_QUOTES, 'UTF-8') ?></small>
                </div>

            <?php elseif ($k && $k['source'] === 'file'): ?>
                <div class="form-group">
                    <label class="form-label" for="material_file">
                        <?= htmlspecialchars($k['label'], ENT_QUOTES, 'UTF-8') ?> <span style="color:var(--danger);">*</span>
                    </label>
                    <input type="file" id="material_file" name="material_file" class="form-control" required
                           accept=".pdf,.png,.jpg,.jpeg">
                    <small class="text-muted"><?= htmlspecialchars($k['help'], ENT_QUOTES, 'UTF-8') ?></small>
                </div>

            <?php else: ?>
                <?php /* No kind chosen (an old bookmark): the original form. */ ?>
                <h3 style="margin:0 0 .35rem;"><?= te('Where is it?') ?></h3>
                <p class="text-sm text-muted" style="margin:0 0 1rem;">
                    <?= te('A link or a file -- one or the other. Zoom recordings and YouTube videos belong on a link; notes and past papers are better uploaded.') ?>
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
            <?php endif; ?>

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
    // With a kind chosen there is only one source box, so there is
    // nothing to keep exclusive and this does nothing.
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
