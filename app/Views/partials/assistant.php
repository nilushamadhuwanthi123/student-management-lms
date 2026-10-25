<?php
/**
 * The study assistant's chat bubble, for students and parents only.
 *
 * Answers are put on the page with textContent, never innerHTML: the
 * text is built from the database, and a class or material title is
 * typed by staff, so it is treated as untrusted. Links are rendered only
 * when they point inside this site.
 */
$assistantFor = session_get('user_type') === 'parent' ? 'parent' : 'student';
?>
<style>
.sa-fab { position:fixed; right:24px; bottom:24px; z-index:1000; width:58px; height:58px; border-radius:50%;
  border:none; cursor:pointer; background:var(--primary); color:#fff; font-size:1.6rem;
  box-shadow:0 8px 24px rgba(29,78,216,.35); display:grid; place-items:center; }
.sa-fab:hover { background:var(--primary-hover); }
.sa-fab__dot { position:absolute; top:4px; right:4px; width:12px; height:12px; border-radius:50%;
  background:var(--accent); border:2px solid #fff; }
.sa-panel { position:fixed; right:24px; bottom:96px; z-index:1001; width:min(380px, calc(100vw - 32px));
  height:min(560px, calc(100vh - 140px)); background:var(--surface); border:1px solid var(--border);
  border-radius:16px; box-shadow:0 18px 48px rgba(15,23,42,.22); display:none; flex-direction:column; overflow:hidden; }
.sa-panel.is-open { display:flex; }
.sa-head { padding:.9rem 1rem; background:linear-gradient(135deg, var(--primary), var(--primary-deep)); color:#fff;
  display:flex; align-items:center; gap:.6rem; }
.sa-head strong { display:block; font-size:.98rem; }
.sa-head small { opacity:.85; font-size:.75rem; }
.sa-head button { margin-left:auto; background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer; }
.sa-log { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.6rem; background:var(--bg); }
.sa-msg { max-width:86%; padding:.6rem .8rem; border-radius:14px; font-size:.88rem; line-height:1.45; word-wrap:break-word; }
.sa-msg--bot { background:var(--surface); border:1px solid var(--border); align-self:flex-start; border-bottom-left-radius:4px; }
.sa-msg--me { background:var(--primary); color:#fff; align-self:flex-end; border-bottom-right-radius:4px; }
.sa-msg a { display:inline-block; margin-top:.45rem; font-weight:600; }
.sa-chips { display:flex; flex-wrap:wrap; gap:.35rem; align-self:flex-start; }
.sa-chip { border:1px solid var(--border); background:var(--surface); color:var(--primary); border-radius:999px;
  padding:.3rem .65rem; font-size:.78rem; cursor:pointer; }
.sa-chip:hover { border-color:var(--primary); }
.sa-typing { align-self:flex-start; color:var(--text-muted); font-size:.8rem; }
.sa-form { display:flex; gap:.5rem; padding:.7rem; border-top:1px solid var(--border); background:var(--surface); }
.sa-form input { flex:1; border:1px solid var(--border); border-radius:999px; padding:.55rem .9rem;
  background:var(--bg); color:var(--text); font-size:.9rem; }
.sa-form input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--ring); }
.sa-form button { border:none; border-radius:999px; background:var(--accent); color:#1a1300; font-weight:700;
  padding:0 1rem; cursor:pointer; }
@media (max-width: 520px) { .sa-panel { right:16px; bottom:88px; } .sa-fab { right:16px; bottom:16px; } }
</style>

<button type="button" class="sa-fab" id="sa-fab" aria-label="<?= te('Open the study assistant') ?>" aria-expanded="false">
    &#129302;<span class="sa-fab__dot" aria-hidden="true"></span>
</button>

<section class="sa-panel" id="sa-panel" role="dialog" aria-label="<?= te('Study assistant') ?>">
    <header class="sa-head">
        <span style="font-size:1.5rem;">&#129302;</span>
        <div>
            <strong><?= te('Study assistant') ?></strong>
            <small><?= $assistantFor === 'parent' ? te("About your child's classes") : te('About your classes, marks and fees') ?></small>
        </div>
        <button type="button" id="sa-close" aria-label="<?= te('Close') ?>">&times;</button>
    </header>
    <div class="sa-log" id="sa-log" aria-live="polite"></div>
    <form class="sa-form" id="sa-form" autocomplete="off">
        <?= csrfField() ?>
        <input type="text" name="question" id="sa-input" maxlength="300"
               placeholder="<?= te('Ask about classes, quizzes, marks, fees...') ?>" aria-label="<?= te('Your question') ?>">
        <button type="submit"><?= te('Ask') ?></button>
    </form>
</section>

<script>
(function () {
    var fab = document.getElementById('sa-fab');
    var panel = document.getElementById('sa-panel');
    var log = document.getElementById('sa-log');
    var form = document.getElementById('sa-form');
    var input = document.getElementById('sa-input');
    var endpoint = <?= json_encode(BASE_URL . '/assistant/ask') ?>;
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
        el.className = 'sa-msg sa-msg--' + who;
        // textContent per line, so nothing in the answer is ever parsed as HTML.
        String(text).split('\n').forEach(function (line, i) {
            if (i > 0) el.appendChild(document.createElement('br'));
            el.appendChild(document.createTextNode(line));
        });
        log.appendChild(el);
        return el;
    }

    function addLinks(el, links) {
        (links || []).forEach(function (l) {
            // Only links inside this site.
            if (typeof l.href !== 'string' || l.href.indexOf(base + '/') !== 0) return;
            el.appendChild(document.createElement('br'));
            var a = document.createElement('a');
            a.href = l.href;
            a.textContent = l.label + ' →';
            el.appendChild(a);
        });
    }

    // `list` is what each chip sends (the English question the matcher
    // knows); `labels`, when given, is what it shows.
    function addChips(list, labels) {
        if (!list || !list.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'sa-chips';
        list.forEach(function (s, i) {
            var shown = (labels && labels[i]) || s;
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'sa-chip';
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
        var dot = fab.querySelector('.sa-fab__dot');
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
        typing.className = 'sa-typing';
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
            bubble(err && err.message === 'expired'
                ? L.expired
                : L.offline, 'bot');
            scroll();
        })
        .finally(function () { busy = false; });
    }

    function ask(question, shown) {
        if (!panel.classList.contains('is-open')) { panel.classList.add('is-open'); greeted = true; }
        send(question, false, shown);
    }

    fab.addEventListener('click', function () { panel.classList.contains('is-open') ? close() : open(); });
    document.getElementById('sa-close').addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) close(); });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = input.value.trim();
        if (!q) return;
        input.value = '';
        send(q, false);
    });

    window.StudyAssistant = { ask: ask, open: open };
})();
</script>
