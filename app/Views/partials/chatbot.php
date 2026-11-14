<?php
/**
 * The public FAQ bot's chat bubble, for guests on the public site.
 *
 * Mirrors the student/parent study assistant widget (app/Views/partials/assistant.php)
 * but talks to /chatbot/ask and never touches any account data.
 * Answers are put on the page with textContent, never innerHTML.
 */
?>
<style>
/* Bottom-right, not left: a public-site footer commonly has a
   left-aligned call-to-action button (e.g. "Login") in its last block,
   and a fixed-position bubble on that same corner ends up sitting on
   top of it once the page is scrolled all the way down -- especially
   on a phone screen, where most visitors to this site are. The public
   pages never load the logged-in study assistant (that one is
   admin/portal-only, bottom-right there), so the right corner is free
   here and nothing else competes for it. */
.pb-fab { position:fixed; right:24px; bottom:24px; z-index:1000; width:58px; height:58px; border-radius:50%;
  border:none; cursor:pointer; background:#1D4ED8; color:#fff; font-size:1.6rem;
  box-shadow:0 8px 24px rgba(29,78,216,.35); display:grid; place-items:center; }
.pb-fab:hover { background:#1E40AF; }
.pb-fab__dot { position:absolute; top:4px; right:4px; width:12px; height:12px; border-radius:50%;
  background:#FACC15; border:2px solid #fff; }
.pb-panel { position:fixed; right:24px; bottom:96px; z-index:1001; width:min(380px, calc(100vw - 32px));
  height:min(560px, calc(100vh - 140px)); background:#fff; border:1px solid #E2E8F0;
  border-radius:16px; box-shadow:0 18px 48px rgba(15,23,42,.22); display:none; flex-direction:column; overflow:hidden; }
.pb-panel.is-open { display:flex; }
.pb-panel { color:#0F172A; }
.pb-head { padding:.9rem 1rem; background:linear-gradient(135deg, #1D4ED8, #172554); color:#fff;
  display:flex; align-items:center; gap:.6rem; }
.pb-head strong { display:block; font-size:.98rem; }
.pb-head small { opacity:.85; font-size:.75rem; }
.pb-head button { margin-left:auto; background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer; }
.pb-log { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.6rem; background:#F8FAFC; }
.pb-msg { max-width:86%; padding:.6rem .8rem; border-radius:14px; font-size:.88rem; line-height:1.45; word-wrap:break-word; }
.pb-msg--bot { background:#fff; border:1px solid #E2E8F0; align-self:flex-start; border-bottom-left-radius:4px; }
.pb-msg--me { background:#1D4ED8; color:#fff; align-self:flex-end; border-bottom-right-radius:4px; }
.pb-msg a { display:inline-block; margin-top:.45rem; font-weight:600; color:#1D4ED8; }
.pb-msg--me a { color:#fff; text-decoration:underline; }
.pb-chips { display:flex; flex-wrap:wrap; gap:.35rem; align-self:flex-start; }
.pb-chip { border:1px solid #E2E8F0; background:#fff; color:#1D4ED8; border-radius:999px;
  padding:.3rem .65rem; font-size:.78rem; cursor:pointer; }
.pb-chip:hover { border-color:#1D4ED8; }
.pb-typing { align-self:flex-start; color:#64748B; font-size:.8rem; }
.pb-form { display:flex; gap:.5rem; padding:.7rem; border-top:1px solid #E2E8F0; background:#fff; }
.pb-form input { flex:1; border:1px solid #E2E8F0; border-radius:999px; padding:.55rem .9rem;
  background:#F8FAFC; color:#0F172A; font-size:.9rem; }
.pb-form input:focus { outline:none; border-color:#1D4ED8; box-shadow:0 0 0 3px rgba(29,78,216,.18); }
.pb-form button { border:none; border-radius:999px; background:#FACC15; color:#1a1300; font-weight:700;
  padding:0 1rem; cursor:pointer; }
@media (max-width: 700px) {
  .pb-fab { right:16px; bottom:calc(16px + env(safe-area-inset-bottom)); width:52px; height:52px; font-size:1.4rem; }
  .pb-panel { right:16px; bottom:calc(16px + env(safe-area-inset-bottom) + 68px); height:min(70vh, 520px); }
  /* The home page's sticky "Join a class / Call" bar spans the full
     width at the very bottom, so only there does the bubble need to
     sit above it instead of in the corner. */
  body:has(.ts-mbar) .pb-fab { bottom: calc(72px + env(safe-area-inset-bottom) + 12px); }
  body:has(.ts-mbar) .pb-panel { bottom: calc(72px + env(safe-area-inset-bottom) + 84px); }
}
@media (prefers-reduced-motion: no-preference) { .pb-fab { transition: transform .15s ease; } .pb-fab:hover { transform: scale(1.06); } }
</style>

<button type="button" class="pb-fab" id="pb-fab" aria-label="<?= te('Open the help bot') ?>" aria-expanded="false">
    &#128172;<span class="pb-fab__dot" aria-hidden="true"></span>
</button>

<section class="pb-panel" id="pb-panel" role="dialog" aria-label="<?= te('Help bot') ?>">
    <header class="pb-head">
        <span style="font-size:1.5rem;">&#128172;</span>
        <div>
            <strong><?= te('Ask us anything') ?></strong>
            <small><?= te('Fees, timetable, branches, joining') ?></small>
        </div>
        <button type="button" id="pb-close" aria-label="<?= te('Close') ?>">&times;</button>
    </header>
    <div class="pb-log" id="pb-log" aria-live="polite"></div>
    <form class="pb-form" id="pb-form" autocomplete="off">
        <?= csrfField() ?>
        <input type="text" name="question" id="pb-input" maxlength="300"
               placeholder="<?= te('Ask about fees, classes, branches...') ?>" aria-label="<?= te('Your question') ?>">
        <button type="submit"><?= te('Ask') ?></button>
    </form>
</section>

<script>
(function () {
    var fab = document.getElementById('pb-fab');
    var panel = document.getElementById('pb-panel');
    var log = document.getElementById('pb-log');
    var form = document.getElementById('pb-form');
    var input = document.getElementById('pb-input');
    var endpoint = <?= json_encode(BASE_URL . '/chatbot/ask') ?>;
    var base = <?= json_encode(BASE_URL) ?>;
    var greeted = false;
    var L = <?= json_encode([
        'checking' => t('Checking…'),
        'wrong'    => t('Sorry, something went wrong.'),
        'expired'  => t('This page has been open a while. Refresh it and ask again.'),
        'offline'  => t("I couldn't reach the server just now. Try again in a moment."),
    ], JSON_UNESCAPED_UNICODE) ?>;
    var busy = false;

    function bubble(text, who) {
        var el = document.createElement('div');
        el.className = 'pb-msg pb-msg--' + who;
        String(text).split('\n').forEach(function (line, i) {
            if (i > 0) el.appendChild(document.createElement('br'));
            el.appendChild(document.createTextNode(line));
        });
        log.appendChild(el);
        return el;
    }

    function addLinks(el, links) {
        (links || []).forEach(function (l) {
            if (typeof l.href !== 'string' || l.href.indexOf(base + '/') !== 0) return;
            el.appendChild(document.createElement('br'));
            var a = document.createElement('a');
            a.href = l.href;
            a.textContent = l.label + ' →';
            el.appendChild(a);
        });
    }

    function addChips(list, labels) {
        if (!list || !list.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'pb-chips';
        list.forEach(function (s, i) {
            var shown = (labels && labels[i]) || s;
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pb-chip';
            b.textContent = shown;
            b.addEventListener('click', function () { ask(s, shown); });
            wrap.appendChild(b);
        });
        log.appendChild(wrap);
    }

    function scroll() { log.scrollTop = log.scrollHeight; }

    function open() {
        panel.classList.add('is-open');
        fab.setAttribute('aria-expanded', 'true');
        var dot = fab.querySelector('.pb-fab__dot');
        if (dot) dot.remove();
        if (!greeted) { greeted = true; send('hi', true); }
        setTimeout(function () { input.focus(); }, 50);
    }

    function close() {
        panel.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
        fab.focus();
    }

    function send(question, silent, shown) {
        if (busy) return;
        busy = true;
        if (!silent) bubble(shown || question, 'me');
        var typing = document.createElement('div');
        typing.className = 'pb-typing';
        typing.textContent = L.checking;
        log.appendChild(typing);
        scroll();

        var body = new URLSearchParams();
        body.append('question', question);
        body.append('_csrf_token', form.querySelector('[name="_csrf_token"]').value);

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        })
        .then(function (r) {
            if (r.status === 403) throw new Error('expired');
            return r.json();
        })
        .then(function (data) {
            typing.remove();
            var el = bubble(data.answer || L.wrong, 'bot');
            addLinks(el, data.links);
            addChips(data.suggestions, data.suggestion_labels);
            scroll();
        })
        .catch(function (err) {
            typing.remove();
            bubble(err && err.message === 'expired' ? L.expired : L.offline, 'bot');
            scroll();
        })
        .finally(function () { busy = false; });
    }

    function ask(question, shown) {
        if (!panel.classList.contains('is-open')) { panel.classList.add('is-open'); greeted = true; }
        send(question, false, shown);
    }

    fab.addEventListener('click', function () { panel.classList.contains('is-open') ? close() : open(); });
    document.getElementById('pb-close').addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) close(); });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = input.value.trim();
        if (!q) return;
        input.value = '';
        send(q, false);
    });

    window.PublicHelpBot = { ask: ask, open: open };
})();
</script>
