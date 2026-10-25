<?php
/**
 * Upload a past paper. Variables: $grade, $topics
 */
use App\Controllers\PastPaperController;
use App\Services\TopicService;
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Add a past paper') ?></h1>
        <p class="text-sm text-muted"><?= te('PDF, PNG or JPG, up to 15 MB each. Upload only papers you are allowed to share with your students.') ?></p>
    </div>
</div>

<form method="GET" action="<?= BASE_URL ?>/past-papers/create" class="mb-2" style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
    <label class="form-label" for="g" style="margin:0;"><?= te('Grade') ?></label>
    <select id="g" name="grade" class="form-control" style="width:auto;" onchange="this.form.submit()">
        <?php for ($g = 6; $g <= 13; $g++): ?><option value="<?= $g ?>" <?= $g === $grade ? 'selected' : '' ?>><?= te('Grade {n}', ['n' => $g]) ?></option><?php endfor; ?>
    </select>
    <noscript><button class="btn btn--ghost btn--sm"><?= te('Show') ?></button></noscript>
</form>

<form method="POST" action="<?= BASE_URL ?>/past-papers" enctype="multipart/form-data" class="card" style="max-width:760px;">
    <?= csrfField() ?>
    <input type="hidden" name="grade" value="<?= (int) $grade ?>">
    <div class="card__body" style="display:grid; gap:1rem;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem;">
            <div class="form-group" style="margin:0;"><label class="form-label" for="year"><?= te('Year') ?> *</label>
                <input type="number" id="year" name="year" min="1990" max="<?= (int) date('Y') + 1 ?>" value="<?= (int) date('Y') - 1 ?>" required class="form-control"></div>
            <div class="form-group" style="margin:0;"><label class="form-label" for="paper_type"><?= te('Paper type') ?> *</label>
                <select id="paper_type" name="paper_type" class="form-control"><?php foreach (PastPaperController::TYPES as $k => $v): ?><option value="<?= $h($k) ?>"><?= te($v) ?></option><?php endforeach; ?></select></div>
            <div class="form-group" style="margin:0;"><label class="form-label" for="medium"><?= te('Medium') ?> *</label>
                <select id="medium" name="medium" class="form-control"><?php foreach (PastPaperController::MEDIA as $k => $v): ?><option value="<?= $h($k) ?>"><?= te($v) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-group" style="margin:0;"><label class="form-label" for="title"><?= te('Title') ?></label>
            <input type="text" id="title" name="title" maxlength="255" class="form-control" placeholder="<?= te('Leave empty to name it from the grade, type and year') ?>"></div>
        <?php if ($topics !== []): ?>
        <div class="form-group" style="margin:0;"><label class="form-label" for="topic_id"><?= te('Topic (unit)') ?></label>
            <select id="topic_id" name="topic_id" class="form-control"><option value=""><?= te('Whole paper (no single topic)') ?></option>
                <?php foreach ($topics as $tp): ?><option value="<?= (int) $tp['id'] ?>"><?= $h(TopicService::label($tp)) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="form-group" style="margin:0;"><label class="form-label" for="paper_file"><?= te('Paper') ?> *</label>
            <input type="file" id="paper_file" name="paper_file" accept=".pdf,.png,.jpg,.jpeg" required class="form-control"></div>
        <div class="form-group" style="margin:0;"><label class="form-label" for="answers_file"><?= te('Marking scheme (optional)') ?></label>
            <input type="file" id="answers_file" name="answers_file" accept=".pdf,.png,.jpg,.jpeg" class="form-control">
            <label style="display:flex; gap:.5rem; align-items:center; margin-top:.5rem;"><input type="checkbox" name="show_answers" value="1"> <?= te('Students may see the marking scheme now') ?></label></div>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <button type="submit" name="action" value="publish" class="btn btn--primary"><?= te('Publish') ?></button>
            <button type="submit" name="action" value="draft" class="btn btn--ghost"><?= te('Save as draft') ?></button>
            <a href="<?= BASE_URL ?>/past-papers?grade=<?= (int) $grade ?>" class="btn btn--ghost"><?= te('Cancel') ?></a>
        </div>
    </div>
</form>
