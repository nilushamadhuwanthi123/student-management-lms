<?php
/**
 * Contact: the real branch offices (from the database) and a short message form.
 */
use App\Services\PublicSite;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$branches = PublicSite::branches();
$old = static fn (string $k): string => htmlspecialchars((string) ($_POST[$k] ?? ''), ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_site.php';
?>
<section class="ts-hero" style="padding:6.2rem 0 3.6rem;">
    <div class="ts-wrap">
        <div class="ts-eyebrow"><?= te('Contact') ?></div>
        <h1><?= te('Talk to the branch office') ?></h1>
        <p><?= $e(PublicSite::content('contact_intro_text')) ?></p>
    </div>
</section>

<section class="ts-section">
    <div class="ts-wrap">
        <?php if ($branches === []): ?>
            <p class="ts-muted"><?= te('Branch contact details will appear here soon.') ?></p>
        <?php else: ?>
        <div class="ts-grid">
            <?php foreach ($branches as $b): ?>
                <div class="ts-card ts-reveal">
                    <h3><?= te($b['name']) ?></h3>
                    <?php if (!empty($b['location'])): ?>
                        <div class="ts-muted"><?= te($b['location']) ?></div>
                        <a class="ts-muted" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($b['name'] . ' ' . $b['location']) ?>"><?= te('Open in Maps') ?></a>
                        <iframe
                            src="<?= $e(PublicSite::mapEmbedUrl($b)) ?>"
                            title="<?= $e(t('Map of {branch}', ['branch' => $b['name']])) ?>"
                            width="100%" height="190" style="border:0; border-radius:.6rem; margin-top:.7rem; display:block;"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen></iframe>
                    <?php endif; ?>
                    <div style="margin-top:.6rem; display:flex; gap:.5rem; flex-wrap:wrap;">
                        <?php foreach (['phone1', 'phone2'] as $k): if (!empty($b[$k])): ?>
                            <a class="ts-btn ts-btn--line" style="padding:.45rem .9rem; font-size:.85rem;" href="<?= $e(PublicSite::telHref($b[$k])) ?>">&#128222; <?= $e($b[$k]) ?></a>
                        <?php endif; endforeach; ?>
                        <?php if (empty($b['phone1']) && empty($b['phone2'])): ?><span class="ts-muted"><?= te('Phone number coming soon') ?></span><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php
$waNumber = \App\Services\WhatsAppService::normalisePhone(PublicSite::content('contact_whatsapp'));
$siteUrl  = PublicSite::externalUrl(PublicSite::content('contact_website_url'));
$fbUrl    = PublicSite::externalUrl(PublicSite::content('contact_facebook_url'));
$email    = trim(PublicSite::content('contact_email'));
$email    = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
?>
<?php if ($waNumber !== null || $siteUrl !== null || $fbUrl !== null || $email !== null): ?>
<?php if (\App\Services\PublicSite::isOn('show_contact_online')): ?>
<section class="ts-section">
    <div class="ts-wrap">
        <h2><?= te('Find us online') ?></h2>
        <div style="display:flex; gap:.6rem; flex-wrap:wrap; margin-top:1rem;">
            <?php if ($waNumber !== null): ?>
                <a class="ts-btn ts-btn--line" target="_blank" rel="noopener"
                   href="https://wa.me/<?= $e($waNumber) ?>">&#128172; <?= te('WhatsApp') ?> &middot; <?= $e(PublicSite::content('contact_whatsapp')) ?></a>
            <?php endif; ?>
            <?php if ($siteUrl !== null): ?>
                <a class="ts-btn ts-btn--line" target="_blank" rel="noopener"
                   href="<?= $e($siteUrl) ?>">&#127760; <?= $e(preg_replace('#^https?://#', '', $siteUrl)) ?></a>
            <?php endif; ?>
            <?php if ($fbUrl !== null): ?>
                <a class="ts-btn ts-btn--line" target="_blank" rel="noopener"
                   href="<?= $e($fbUrl) ?>">&#128100; <?= te('Facebook') ?></a>
            <?php endif; ?>
            <?php if ($email !== null): ?>
                <a class="ts-btn ts-btn--line" href="mailto:<?= $e($email) ?>">&#9993; <?= $e($email) ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if (\App\Services\PublicSite::isOn('show_contact_form')): ?>
<section class="ts-section ts-section--alt">
    <div class="ts-wrap" style="max-width:760px;">
        <h2><?= te('Send a message') ?></h2>
        <p class="ts-lead"><?= te('For joining a class, the enrolment inquiry is quickest:') ?> <a href="<?= BASE_URL ?>/#inquiry"><?= te('Join a class') ?> &rarr;</a></p>
        <form action="<?= BASE_URL ?>/contact" method="POST" class="ts-card">
            <?= csrfField() ?>
            <div class="ts-form">
                <div><label for="c-name"><?= te('Your name') ?> *</label><input id="c-name" name="name" maxlength="255" required autocomplete="name" value="<?= $old('name') ?>"></div>
                <div><label for="c-phone"><?= te('Phone number') ?></label><input id="c-phone" name="phone" inputmode="tel" maxlength="50" placeholder="07X XXX XXXX" autocomplete="tel" value="<?= $old('phone') ?>"></div>
                <div><label for="c-email"><?= te('Email (optional)') ?></label><input id="c-email" type="email" name="email" maxlength="255" autocomplete="email" value="<?= $old('email') ?>"></div>
                <div><label for="c-subject"><?= te('Subject') ?> *</label><input id="c-subject" name="subject" maxlength="255" required value="<?= $old('subject') ?>"></div>
                <div style="grid-column:1/-1;"><label for="c-msg"><?= te('Message') ?> *</label><textarea id="c-msg" name="message" rows="5" maxlength="5000" required><?= $old('message') ?></textarea></div>
            </div>
            <p class="ts-muted" style="margin:.8rem 0;"><?= te('Give a phone number or an email so the office can reply.') ?></p>
            <button type="submit" class="ts-btn ts-btn--blue"><?= te('Send message') ?></button>
        </form>
    </div>
</section>
<?php endif; ?>
<noscript><style>.ts-reveal { opacity: 1; transform: none; }</style></noscript>
