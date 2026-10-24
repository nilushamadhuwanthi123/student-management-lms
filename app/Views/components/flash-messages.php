<?php
/**
 * Flash Messages Component
 * Renders success, error, warning, info flash messages.
 * Auto-dismisses after 5 seconds. Dismissible with X button.
 *
 * Expects variables from layout: $flashSuccess, $flashError, $flashWarning, $flashInfo
 */

$flashTypes = [
    'success' => $flashSuccess ?? null,
    'error'   => $flashError   ?? null,
    'warning' => $flashWarning ?? null,
    'info'    => $flashInfo    ?? null,
];
?>

<?php foreach ($flashTypes as $type => $message): ?>
    <?php if ($message): ?>
        <div class="flash flash--<?= $type ?>" role="alert" data-flash>
            <span class="flash__icon">
                <?php if ($type === 'success'): ?>
                    &#10003;
                <?php elseif ($type === 'error'): ?>
                    &#10007;
                <?php elseif ($type === 'warning'): ?>
                    &#9888;
                <?php else: ?>
                    &#8505;
                <?php endif; ?>
            </span>
            <?php // te() translates a fixed message and escapes it, exactly as before.
                  // A message already built with values (t('Welcome back, {name}!'))
                  // has no dictionary entry and passes through unchanged. ?>
            <span class="flash__text"><?= function_exists('te') ? te((string) $message) : htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></span>
            <button type="button" class="flash__dismiss" onclick="this.parentElement.remove()" aria-label="<?= function_exists('te') ? te('Dismiss') : 'Dismiss' ?>">&times;</button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
(function () {
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 5000);
    });
})();
</script>
