<?php
/**
 * Reusable Modal Component
 *
 * Variables:
 *   $modalId        - Unique ID for the modal
 *   $modalTitle     - Title text
 *   $modalBody      - Body HTML content (slot)
 *   $modalAction    - Action button label (e.g. "Confirm", "Delete")
 *   $modalActionClass - CSS class for action button (default: btn--primary)
 *   $modalFormAction  - If set, wraps content in a form with this action URL
 *   $modalFormMethod  - Form method (default: POST)
 *   $showReasonField  - If true, includes a reason textarea
 */

$modalId          = $modalId ?? 'modal';
$modalTitle       = $modalTitle ?? te('Confirm');
$modalBody        = $modalBody ?? '';
$modalAction      = $modalAction ?? te('Confirm');
$modalActionClass = $modalActionClass ?? 'btn--primary';
$modalFormAction  = $modalFormAction ?? '';
$modalFormMethod  = $modalFormMethod ?? 'POST';
$showReasonField  = $showReasonField ?? false;
?>

<div class="modal-backdrop" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title"><?= htmlspecialchars($modalTitle, ENT_QUOTES, 'UTF-8') ?></h3>
            <button type="button" class="modal__close" onclick="closeModal('<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>')">&times;</button>
        </div>

        <?php if ($modalFormAction): ?>
        <form method="<?= htmlspecialchars($modalFormMethod, ENT_QUOTES, 'UTF-8') ?>" action="<?= htmlspecialchars($modalFormAction, ENT_QUOTES, 'UTF-8') ?>">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
        <?php endif; ?>

            <div class="modal__body">
                <?= $modalBody ?>

                <?php if ($showReasonField): ?>
                    <div class="form-group" style="margin-top:1rem;">
                        <label class="form-label" for="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>_reason"><?= te('Reason') ?> <span class="required">*</span></label>
                        <textarea
                            id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>_reason"
                            name="reason"
                            class="form-control"
                            required
                            placeholder="<?= te('Please provide a reason...') ?>"
                        ></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" onclick="closeModal('<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>')"><?= te('Cancel') ?></button>
                <?php if ($modalFormAction): ?>
                    <button type="submit" class="btn <?= htmlspecialchars($modalActionClass, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($modalAction, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn <?= htmlspecialchars($modalActionClass, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>_action">
                        <?= htmlspecialchars($modalAction, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php endif; ?>
            </div>

        <?php if ($modalFormAction): ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var backdrop = document.getElementById('<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>');
    if (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) {
                closeModal('<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>');
            }
        });
    }
})();
</script>
