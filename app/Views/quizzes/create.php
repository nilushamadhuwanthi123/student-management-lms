<?php
/**
 * Set a quiz: the paper and its window. Questions come after saving.
 */

$class = $class ?? [];

// Sensible defaults: opens this evening, closes in a week. A teacher
// setting a quiz on a Tuesday almost never wants it live at midnight.
$defaultOpen  = date('Y-m-d\TH:i', strtotime('today 18:00'));
$defaultClose = date('Y-m-d\TH:i', strtotime('+7 days 21:00'));
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Set a quiz') ?></h1>
        <p class="text-sm text-muted">
            <?= te('For') ?> <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes" class="btn btn--ghost">&larr; <?= te('Back') ?></a>
    </div>
</div>

<div class="card" style="max-width:820px;">
    <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/create">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="card__body">
            <div class="form-group">
                <label class="form-label" for="title">
                    <?= te('Title') ?> <span style="color:var(--danger);">*</span>
                </label>
                <input type="text" id="title" name="title" class="form-control" required maxlength="255"
                       placeholder="e.g. Chemical Reactions - monthly test">
            </div>

            <?php include __DIR__ . '/../components/topic-select.php'; ?>

            <div class="form-group">
                <label class="form-label" for="description"><?= te('Description') ?></label>
                <input type="text" id="description" name="description" class="form-control"
                       placeholder="One line the students see before they start">
            </div>

            <div class="form-group">
                <label class="form-label" for="instructions"><?= te('Instructions') ?></label>
                <textarea id="instructions" name="instructions" class="form-control" rows="2"
                          placeholder="e.g. Answer every question. You have 20 minutes once you start."></textarea>
            </div>

            <hr style="border:none; border-top:1px solid var(--border); margin:1.5rem 0;">

            <h3 style="margin:0 0 .35rem;"><?= te('When can they sit it?') ?></h3>
            <p class="text-sm text-muted" style="margin:0 0 1rem;">
                <?= te('Students can only start inside this window. The time limit then caps one sitting — a paper started five minutes before closing still gets its full time, up to the closing moment.') ?>
            </p>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">
                <div class="form-group">
                    <label class="form-label" for="opens_at">
                        <?= te('Opens') ?> <span style="color:var(--danger);">*</span>
                    </label>
                    <input type="datetime-local" id="opens_at" name="opens_at" class="form-control"
                           value="<?= $defaultOpen ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="closes_at">
                        <?= te('Closes') ?> <span style="color:var(--danger);">*</span>
                    </label>
                    <input type="datetime-local" id="closes_at" name="closes_at" class="form-control"
                           value="<?= $defaultClose ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="time_limit_minutes"><?= te('Time limit (minutes)') ?></label>
                    <input type="number" id="time_limit_minutes" name="time_limit_minutes"
                           class="form-control" min="1" max="300" value="20"
                           placeholder="Leave blank for no limit">
                </div>

                <div class="form-group">
                    <label class="form-label" for="month_year"><?= te("Counts against which month's fee") ?></label>
                    <input type="month" id="month_year" name="month_year" class="form-control"
                           value="<?= date('Y-m') ?>">
                    <small class="text-muted"><?= te('Only students who settled it can sit the quiz.') ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pass_mark"><?= te('Pass mark (%)') ?></label>
                    <input type="number" id="pass_mark" name="pass_mark" class="form-control"
                           min="0" max="100" step="0.5" value="50">
                </div>

                <div class="form-group">
                    <label class="form-label" for="max_attempts"><?= te('Attempts allowed') ?></label>
                    <input type="number" id="max_attempts" name="max_attempts" class="form-control"
                           min="1" max="5" value="1">
                </div>
            </div>

            <div class="form-group" style="margin-top:.5rem;">
                <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                    <input type="checkbox" name="shuffle_questions" value="1">
                    <span><?= te('Shuffle the question order for each student') ?></span>
                </label>
            </div>

            <div class="form-group">
                <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                    <input type="checkbox" name="show_answers_after" value="1" checked>
                    <span><?= te('Show the correct answers once the quiz closes') ?></span>
                </label>
            </div>

            <div class="alert" style="background:var(--info-light); border-left:3px solid var(--primary); padding:.875rem 1rem; border-radius:var(--radius-sm); margin-top:1rem;">
                <strong><?= te('Saved as a draft.') ?></strong>
                <span class="text-sm">
                    <?= te('You add the questions on the next screen, then publish when it is ready. Students cannot see a draft.') ?>
                </span>
            </div>
        </div>

        <div class="card__footer" style="display:flex; gap:.75rem; justify-content:flex-end;">
            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes" class="btn btn--ghost"><?= te('Cancel') ?></a>
            <button type="submit" class="btn btn--primary"><?= te('Save and add questions') ?></button>
        </div>
    </form>
</div>
