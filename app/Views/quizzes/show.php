<?php
/**
 * The question builder.
 *
 * Existing questions on the left, one add-form on the right. The form
 * changes shape with the question type, so a short-answer question does
 * not ask for four options it will never use.
 */

$class     = $class ?? [];
$quiz      = $quiz ?? [];
$questions = $questions ?? [];
$window    = $window ?? ['state' => 'draft', 'label' => 'Draft', 'tone' => 'muted'];

$classId = (int) ($class['id'] ?? 0);
$quizId  = (int) ($quiz['id'] ?? 0);
$letters = ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted">
            <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            &middot; <?= count($questions) ?> <?= te('question') ?><?= count($questions) === 1 ? '' : 's' ?>
            &middot; <?= rtrim(rtrim(number_format((float) $quiz['total_marks'], 2), '0'), '.') ?> <?= te('marks') ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= $classId ?>/quizzes" class="btn btn--ghost">&larr; Quizzes</a>
        <?php if ((int) $quiz['total_marks'] > 0): ?>
            <a href="<?= BASE_URL ?>/classes/<?= $classId ?>/quizzes/<?= $quizId ?>/results"
               class="btn btn--ghost"><?= te('Results') ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- Status strip: what state the paper is in, and the one button that changes it -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card__body" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
        <div>
            <div style="font-weight:600;">
                <?= $quiz['quiz_status'] === 'published' ? te('Published') : te(ucfirst($quiz['quiz_status'])) ?>
                &middot; <span class="text-muted"><?= htmlspecialchars($window['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="text-sm text-muted">
                <?= date('j M Y, g:i a', strtotime($quiz['opens_at'])) ?>
                &rarr; <?= date('j M Y, g:i a', strtotime($quiz['closes_at'])) ?>
                <?php if (!empty($quiz['time_limit_minutes'])): ?>
                    &middot; <?= (int) $quiz['time_limit_minutes'] ?> <?= te('minute limit') ?>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/classes/<?= $classId ?>/quizzes/<?= $quizId ?>/publish"
              style="display:flex; gap:.5rem; align-items:center;">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
            <?php if ($quiz['quiz_status'] !== 'published'): ?>
                <input type="hidden" name="quiz_status" value="published">
                <button type="submit" class="btn btn--primary"
                    <?= $questions === [] ? 'disabled title="Add a question first"' : '' ?>>
                    <?= te('Publish') ?>
                </button>
            <?php else: ?>
                <input type="hidden" name="quiz_status" value="draft">
                <button type="submit" class="btn btn--ghost"><?= te('Pull back to draft') ?></button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div style="display:grid; grid-template-columns:minmax(0,1.3fr) minmax(300px,1fr); gap:1.5rem; align-items:start;">

    <!-- Existing questions -->
    <div class="card">
        <div class="card__header"><h3 style="margin:0;"><?= te('Questions') ?></h3></div>

        <?php if ($questions === []): ?>
            <div style="padding:2.5rem 1.5rem; text-align:center;">
                <div style="font-size:2rem;">&#10067;</div>
                <p class="text-sm text-muted" style="margin:.75rem 0 0;">
                    <?= te('No questions yet. Add the first one with the form beside this.') ?>
                </p>
            </div>
        <?php else: ?>
            <div class="card__body" style="display:flex; flex-direction:column; gap:1rem;">
                <?php foreach ($questions as $q): ?>
                    <div style="border:1px solid var(--border); border-radius:var(--radius); padding:1rem;">
                        <div style="display:flex; justify-content:space-between; gap:1rem;">
                            <div style="font-weight:600;">
                                <?= (int) $q['question_number'] ?>.
                                <?= htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="text-sm text-muted" style="white-space:nowrap;">
                                <?= rtrim(rtrim(number_format((float) $q['marks'], 2), '0'), '.') ?> <?= te('marks') ?>
                            </div>
                        </div>

                        <?php if ($q['question_type'] !== 'short_answer'): ?>
                            <ul style="list-style:none; padding:0; margin:.75rem 0 0; display:grid; gap:.35rem;">
                                <?php foreach ($letters as $key => $label): ?>
                                    <?php if (!empty($q['option_' . $key])): ?>
                                        <?php $isRight = strtolower((string) $q['correct_option']) === $key; ?>
                                        <li class="text-sm" style="<?= $isRight ? 'color:var(--success); font-weight:600;' : 'color:var(--text-secondary);' ?>">
                                            <?= $label ?>. <?= htmlspecialchars($q['option_' . $key], ENT_QUOTES, 'UTF-8') ?>
                                            <?= $isRight ? ' &#10003;' : '' ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-sm" style="margin-top:.75rem; color:var(--text-secondary);">
                                <?= te('Short answer') ?>
                                <?php if (!empty($q['correct_text'])): ?>
                                    &middot; expects &ldquo;<?= htmlspecialchars($q['correct_text'], ENT_QUOTES, 'UTF-8') ?>&rdquo;
                                <?php else: ?>
                                    &middot; <span style="color:var(--warning);"><?= te('you mark this one by hand') ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:.75rem; text-align:right;">
                            <form method="POST"
                                  action="<?= BASE_URL ?>/classes/<?= $classId ?>/quizzes/<?= $quizId ?>/questions/<?= (int) $q['id'] ?>/delete"
                                  data-confirm="<?= te('Remove question {n}?', ['n' => (int) $q['question_number']]) ?>">
                                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                <button type="submit" class="btn btn--ghost btn--sm" style="color:var(--danger);">
                                    <?= te('Remove') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add a question -->
    <div class="card">
        <div class="card__header"><h3 style="margin:0;"><?= te('Add a question') ?></h3></div>

        <form method="POST" action="<?= BASE_URL ?>/classes/<?= $classId ?>/quizzes/<?= $quizId ?>/questions">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

            <div class="card__body">
                <div class="form-group">
                    <label class="form-label" for="question_type"><?= te('Type') ?></label>
                    <select id="question_type" name="question_type" class="form-control">
                        <option value="mcq"><?= te('Multiple choice') ?></option>
                        <option value="true_false"><?= te('True or false') ?></option>
                        <option value="short_answer"><?= te('Short answer') ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="question_text">
                        <?= te('Question') ?> <span style="color:var(--danger);">*</span>
                    </label>
                    <textarea id="question_text" name="question_text" class="form-control" rows="2" required
                              placeholder="e.g. Which gas do plants take in during photosynthesis?"></textarea>
                </div>

                <?php $topicSelected = (int) ($quiz['topic_id'] ?? 0); $topicFieldId = 'question_topic'; ?>
                <?php include __DIR__ . '/../components/topic-select.php'; ?>

                <div class="form-group">
                    <label class="form-label" for="marks"><?= te('Marks') ?></label>
                    <input type="number" id="marks" name="marks" class="form-control"
                           min="0.5" max="100" step="0.5" value="10" required>
                </div>

                <!-- Multiple choice -->
                <div data-when="mcq">
                    <?php foreach ($letters as $key => $label): ?>
                        <div class="form-group">
                            <label class="form-label" for="option_<?= $key ?>"><?= te('Option') ?> <?= $label ?></label>
                            <input type="text" id="option_<?= $key ?>" name="option_<?= $key ?>"
                                   class="form-control" maxlength="500">
                        </div>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <label class="form-label" for="correct_option_mcq"><?= te('Correct option') ?></label>
                        <select id="correct_option_mcq" name="correct_option" class="form-control">
                            <?php foreach ($letters as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- True / false -->
                <div data-when="true_false" hidden>
                    <div class="form-group">
                        <label class="form-label"><?= te('The statement is') ?></label>
                        <select name="correct_option" class="form-control" disabled data-tf>
                            <option value="true"><?= te('True') ?></option>
                            <option value="false"><?= te('False') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Short answer -->
                <div data-when="short_answer" hidden>
                    <div class="form-group">
                        <label class="form-label" for="correct_text"><?= te('Model answer') ?></label>
                        <input type="text" id="correct_text" name="correct_text" class="form-control"
                               maxlength="500" placeholder="Leave blank to mark it yourself">
                        <small class="text-muted">
                            Matched ignoring case, spacing and trailing punctuation. Anything
                            subtler comes to you to mark.
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="explanation"><?= te('Explanation (shown after closing)') ?></label>
                    <input type="text" id="explanation" name="explanation" class="form-control" maxlength="500">
                </div>
            </div>

            <div class="card__footer" style="text-align:right;">
                <button type="submit" class="btn btn--primary"><?= te('Add question') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var typeSelect = document.getElementById('question_type');
    if (!typeSelect) { return; }

    var panels = document.querySelectorAll('[data-when]');
    var mcqCorrect = document.getElementById('correct_option_mcq');
    var tfCorrect = document.querySelector('[data-tf]');

    function sync() {
        var type = typeSelect.value;

        panels.forEach(function (panel) {
            var matches = panel.getAttribute('data-when') === type;
            panel.hidden = !matches;

            // A hidden field still posts, and two inputs both named
            // correct_option would fight over the same key. Disabling the
            // one that is out of view keeps exactly one in the request.
            panel.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !matches;
            });
        });

        if (mcqCorrect) { mcqCorrect.disabled = type !== 'mcq'; }
        if (tfCorrect) { tfCorrect.disabled = type !== 'true_false'; }
    }

    typeSelect.addEventListener('change', sync);
    sync();
})();
</script>
