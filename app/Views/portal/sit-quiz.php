<?php
/**
 * Sitting a quiz.
 *
 * The countdown on screen is a courtesy. The server fixed this
 * attempt's deadline when it started and re-checks it on every save
 * and on submit, so a student who pauses the JavaScript, reloads, or
 * changes their clock gains nothing.
 *
 * Answers are posted as they are chosen rather than held until the
 * end, so a dropped connection costs one question, not the paper.
 */

$quiz        = $quiz ?? [];
$attempt     = $attempt ?? [];
$questions   = $questions ?? [];
$answers     = $answers ?? [];
$secondsLeft = $secondsLeft ?? null;

$letters = ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'];
?>

<div style="max-width:780px; margin:0 auto; padding:1.5rem 1rem 4rem;">

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 .25rem; font-size:1.4rem;">
                <?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <div class="text-sm text-muted">
                <?= count($questions) ?> questions
                &middot; <?= rtrim(rtrim(number_format((float) $quiz['total_marks'], 2), '0'), '.') ?> marks
            </div>
        </div>

        <?php if ($secondsLeft !== null): ?>
            <div id="timer" data-seconds="<?= (int) $secondsLeft ?>"
                 style="padding:.6rem 1rem; border-radius:var(--radius); background:var(--primary-light);
                        color:var(--primary); font-weight:700; font-variant-numeric:tabular-nums; font-size:1.15rem;">
                &mdash;:&mdash;&mdash;
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($quiz['instructions'])): ?>
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card__body" style="padding:1rem;">
                <div class="text-sm" style="color:var(--text-secondary);">
                    <?= nl2br(htmlspecialchars($quiz['instructions'], ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="save-state" class="text-sm text-muted"
         style="position:sticky; top:0; z-index:5; padding:.5rem 0; background:var(--bg);" aria-live="polite"></div>

    <form id="quiz-form" method="POST" action="<?= BASE_URL ?>/my/quizzes/submit">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
        <input type="hidden" name="attempt_id" value="<?= (int) $attempt['id'] ?>">

        <?php foreach ($questions as $index => $q):
            $saved = $answers[(int) $q['id']] ?? null;
        ?>
            <div class="card" style="margin-bottom:1.25rem;" data-question="<?= (int) $q['id'] ?>">
                <div class="card__body">
                    <div style="display:flex; justify-content:space-between; gap:1rem; margin-bottom:1rem;">
                        <div style="font-weight:600;">
                            <?= $index + 1 ?>. <?= htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-sm text-muted" style="white-space:nowrap;">
                            <?= rtrim(rtrim(number_format((float) $q['marks'], 2), '0'), '.') ?> marks
                        </div>
                    </div>

                    <?php if ($q['question_type'] === 'short_answer'): ?>
                        <input type="text" class="form-control" data-answer-text
                               data-question-id="<?= (int) $q['id'] ?>"
                               value="<?= htmlspecialchars((string) ($saved['answer_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Your answer">
                    <?php else: ?>
                        <div style="display:grid; gap:.5rem;">
                            <?php foreach ($letters as $key => $label): ?>
                                <?php if (!empty($q['option_' . $key])): ?>
                                    <?php $checked = ($saved['selected_option'] ?? '') === $key; ?>
                                    <label style="display:flex; gap:.75rem; align-items:center; padding:.75rem 1rem;
                                                  border:1px solid <?= $checked ? 'var(--primary)' : 'var(--border)' ?>;
                                                  border-radius:var(--radius); cursor:pointer;
                                                  background:<?= $checked ? 'var(--primary-light)' : 'transparent' ?>;">
                                        <input type="radio"
                                               name="q<?= (int) $q['id'] ?>"
                                               value="<?= $key ?>"
                                               data-answer-option
                                               data-question-id="<?= (int) $q['id'] ?>"
                                               <?= $checked ? 'checked' : '' ?>>
                                        <span><strong><?= $label ?>.</strong>
                                            <?= htmlspecialchars($q['option_' . $key], ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <div class="card__body" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                <div class="text-sm text-muted">
                    <span id="answered-count">0</span> of <?= count($questions) ?> answered.
                    Unanswered questions score zero.
                </div>
                <button type="submit" class="btn btn--primary" id="submit-btn">Hand it in</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('quiz-form');
    var state = document.getElementById('save-state');
    var timer = document.getElementById('timer');
    var countEl = document.getElementById('answered-count');
    var submitBtn = document.getElementById('submit-btn');
    var csrf = form.querySelector('input[name="_csrf_token"]');
    var attemptId = form.querySelector('input[name="attempt_id"]').value;
    var submitting = false;

    function say(message, tone) {
        state.textContent = message;
        state.style.color = tone === 'bad' ? 'var(--danger)'
            : (tone === 'good' ? 'var(--success)' : 'var(--text-muted)');
    }

    function recount() {
        var answered = 0;
        form.querySelectorAll('[data-question]').forEach(function (block) {
            var radio = block.querySelector('input[type=radio]:checked');
            var text = block.querySelector('[data-answer-text]');
            if (radio || (text && text.value.trim() !== '')) { answered++; }
        });
        countEl.textContent = String(answered);
    }

    function save(questionId, payload) {
        var body = new FormData();
        body.append('attempt_id', attemptId);
        body.append('question_id', questionId);
        body.append('_csrf_token', csrf ? csrf.value : '');
        if (payload.option !== undefined) { body.append('selected_option', payload.option); }
        if (payload.text !== undefined) { body.append('answer_text', payload.text); }

        say('Saving...');

        return fetch('<?= BASE_URL ?>/my/quizzes/answer', {
            method: 'POST', body: body, credentials: 'same-origin'
        }).then(function (res) {
            return res.json().catch(function () { return { ok: false, message: 'Could not save.' }; });
        }).then(function (data) {
            if (data.ok) {
                say('Saved', 'good');
            } else {
                // The usual cause is the clock having run out, in which
                // case the server has already submitted the paper.
                say(data.message || 'Could not save.', 'bad');
                if (/time/i.test(data.message || '')) { window.location.reload(); }
            }
        }).catch(function () {
            say('Offline - your answer is not saved yet.', 'bad');
        });
    }

    // Multiple choice: save on pick, and reflect the selection.
    form.querySelectorAll('[data-answer-option]').forEach(function (input) {
        input.addEventListener('change', function () {
            var block = input.closest('[data-question]');
            block.querySelectorAll('label').forEach(function (label) {
                var picked = label.querySelector('input').checked;
                label.style.borderColor = picked ? 'var(--primary)' : 'var(--border)';
                label.style.background = picked ? 'var(--primary-light)' : 'transparent';
            });
            recount();
            save(input.getAttribute('data-question-id'), { option: input.value });
        });
    });

    // Short answer: save when the student moves on, not on every keystroke.
    form.querySelectorAll('[data-answer-text]').forEach(function (input) {
        var last = input.value;
        input.addEventListener('blur', function () {
            if (input.value === last) { return; }
            last = input.value;
            recount();
            save(input.getAttribute('data-question-id'), { text: input.value });
        });
    });

    form.addEventListener('submit', function () {
        submitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Handing in...';
    });

    // Warn on leaving mid-attempt. The attempt survives, but a student
    // who closed the tab by accident should know it is still running.
    window.addEventListener('beforeunload', function (e) {
        if (submitting) { return; }
        e.preventDefault();
        e.returnValue = '';
    });

    // Countdown. The server owns the real deadline; this just shows it.
    if (timer) {
        var remaining = parseInt(timer.getAttribute('data-seconds'), 10) || 0;

        var tick = setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                clearInterval(tick);
                timer.textContent = '0:00';
                say('Time is up. Handing your paper in.', 'bad');
                submitting = true;
                form.submit();
                return;
            }

            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            timer.textContent = m + ':' + (s < 10 ? '0' : '') + s;

            if (remaining <= 60) {
                timer.style.background = 'var(--danger-light)';
                timer.style.color = 'var(--danger)';
            }
        }, 1000);
    }

    recount();
})();
</script>
