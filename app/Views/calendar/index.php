<?php
/**
 * The teacher's calendar: the month, what is coming, and the form that
 * puts a test on it.
 */

$byDate      = $byDate ?? [];
$upcoming    = $upcoming ?? [];
$classes     = $classes ?? [];
$institutes  = $institutes ?? [];
$month       = $month ?? date('Y-m');
$canEdit     = $canEdit ?? false;
$instituteId = $instituteId ?? null;

$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
$q = $instituteId !== null ? '&institute_id=' . $instituteId : '';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Calendar</h1>
        <p class="text-sm text-muted">
            Classes, quizzes and tests together &middot; <?= date('F Y', strtotime($month . '-01')) ?>
        </p>
    </div>
    <div class="page-header__actions" style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/calendar?month=<?= $prev . $q ?>" class="btn btn--ghost">&larr;</a>
        <a href="<?= BASE_URL ?>/calendar?month=<?= date('Y-m') . $q ?>" class="btn btn--ghost">Today</a>
        <a href="<?= BASE_URL ?>/calendar?month=<?= $next . $q ?>" class="btn btn--ghost">&rarr;</a>

        <form method="GET" action="<?= BASE_URL ?>/calendar" style="display:inline-flex; gap:.5rem;">
            <input type="hidden" name="month" value="<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?>">
            <select name="institute_id" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <option value="">All branches</option>
                <?php foreach ($institutes as $institute): ?>
                    <option value="<?= (int) $institute['id'] ?>" <?= $instituteId === (int) $institute['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($institute['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($canEdit): ?>
            <button type="button" class="btn btn--primary" onclick="document.getElementById('add-event').showModal()">
                Add a test or exam
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="cal-layout">
    <div>
        <?php require __DIR__ . '/_grid.php'; ?>

        <p class="text-sm text-muted" style="margin-top:.75rem;">
            Class times come from each class's own schedule and quizzes from their open and close times &mdash;
            they are read, never copied, so moving a class moves it here too. Only tests, exams, extra classes,
            cancellations, holidays and notices are entered on this page.
        </p>
    </div>

    <div>
        <div class="card" style="padding:1.15rem;">
            <h3 style="margin:0 0 .25rem; font-size:1rem;">Coming up</h3>
            <p class="text-sm text-muted" style="margin:0 0 .75rem;">The next 30 days.</p>

            <?php if ($upcoming === []): ?>
                <p class="text-sm text-muted">Nothing scheduled.</p>
            <?php else: ?>
                <?php foreach ($upcoming as $item): ?>
                    <div style="padding:.5rem 0; border-top:1px solid var(--border);">
                        <div class="text-sm" style="font-weight:600;">
                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-sm text-muted">
                            <?= htmlspecialchars($item['detail'], ENT_QUOTES, 'UTF-8') ?>
                            &middot; <?= date('D j M', strtotime($item['date'])) ?>
                            <?= $item['start_time'] !== null ? ' at ' . htmlspecialchars($item['start_time'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
            <div class="card" style="padding:1.15rem; margin-top:1rem;">
                <h3 style="margin:0 0 .25rem; font-size:1rem;">Reminders</h3>
                <p class="text-sm text-muted" style="margin:0 0 .75rem;">
                    Everyone gets a reminder a day before and again an hour before, in their notifications.
                    The scheduled job does this on its own; press this only if you have just added something
                    for tomorrow. Pressing it twice sends nothing twice.
                </p>
                <form method="POST" action="<?= BASE_URL ?>/calendar/reminders/run">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn--ghost" style="width:100%;">Send what is due now</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<dialog id="add-event" class="card" style="max-width:560px; width:92%; border:none; border-radius:12px; padding:1.5rem;">
    <h3 style="margin:0 0 1rem;">Put something on the calendar</h3>

    <form method="POST" action="<?= BASE_URL ?>/calendar/events">
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label" for="ev-type">What is it</label>
            <select name="event_type" id="ev-type" class="form-control" required>
                <option value="test">Test</option>
                <option value="exam">Exam</option>
                <option value="extra_class">Extra class</option>
                <option value="cancelled_class">Class cancelled</option>
                <option value="holiday">Holiday</option>
                <option value="notice">Notice</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="ev-title">Title</label>
            <input type="text" name="title" id="ev-title" class="form-control" maxlength="255" required
                   placeholder="Unit 3 test - Electricity">
        </div>

        <div class="form-group">
            <label class="form-label" for="ev-class">Class</label>
            <select name="class_id" id="ev-class" class="form-control">
                <option value="">Everyone (holiday or school-wide notice)</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= (int) $class['id'] ?>">
                        <?= htmlspecialchars($class['class_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem;">
            <div class="form-group">
                <label class="form-label" for="ev-date">Date</label>
                <input type="date" name="event_date" id="ev-date" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="ev-start">Starts</label>
                <input type="time" name="start_time" id="ev-start" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="ev-end">Ends</label>
                <input type="time" name="end_time" id="ev-end" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="ev-syllabus">What it covers</label>
            <textarea name="syllabus" id="ev-syllabus" class="form-control" rows="2"
                      placeholder="Units 1-3. Bring a calculator."></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="ev-room">Room</label>
            <input type="text" name="room" id="ev-room" class="form-control" maxlength="100">
        </div>

        <label style="display:flex; gap:.5rem; align-items:center; margin-bottom:1rem;">
            <input type="checkbox" name="remind" value="1" checked>
            <span class="text-sm">Remind everyone a day before and an hour before</span>
        </label>

        <div style="display:flex; gap:.5rem; justify-content:flex-end;">
            <button type="button" class="btn btn--ghost" onclick="document.getElementById('add-event').close()">Cancel</button>
            <button type="submit" class="btn btn--primary">Add it</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
