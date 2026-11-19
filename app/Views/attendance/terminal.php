<?php
/**
 * QR Scan Terminal View
 * Document 2 Page 2, 9, 10 & Kavindu Role Guide:
 * - Real-time QR / Barcode Scan Terminal
 * - 5-Second Loud Alert Sound on Deactivated Account
 * - Modal Display: Account Deactivated Alert
 * - Marked with RED FLAG in Database
 */

$classes = $classes ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('QR Scan Entrance Terminal') ?></h1>
        <p class="text-sm text-muted"><?= te('Automated student gate & classroom attendance scanner') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/attendance" class="btn btn--ghost">
            &larr; <?= te('View Attendance Sheet') ?>
        </a>
    </div>
</div>

<style>
/* Scanner on the left, today's log on the right; stacked on a phone or
   small tablet, where a fixed 380px column ran off the screen. */
#terminalLayout > * { min-width: 0; }
@media (max-width: 900px) { #terminalLayout { grid-template-columns: minmax(0, 1fr) !important; } }
</style>
<div style="display:grid; grid-template-columns: 1fr 380px; gap:1.5rem;" id="terminalLayout">
    <!-- LEFT: SCANNER INTERFACE -->
    <div>
        <!-- Class Selector & Status Bar -->
        <div class="card mb-3" style="background:var(--surface); border:1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <div style="flex:1; min-width:240px;">
                    <label class="form-label text-xs" for="terminalClassSelector"><?= te('Active Classroom / Session:') ?></label>
                    <select id="terminalClassSelector" class="form-control form-control--sm">
                        <option value=""><?= te('-- All Active Classes / Entrance Gate --') ?></option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>">
                                <?= te($c['class_name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex items-center gap-1">
                    <span id="scannerStatusBadge" class="badge badge--success" style="padding:0.4rem 0.8rem; font-size:0.8rem;">
                        &#9679; <?= te('Terminal Ready') ?>
                    </span>
                    <button type="button" class="btn btn--ghost btn--sm" id="testAudioBtn" onclick="testAlertAudio()" title="<?= te('Test 5s Loud Alert Sound') ?>">
                        &#128266; <?= te('Test Siren') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Scanner Card -->
        <div class="card mb-3" style="text-align:center; padding:2rem 1.5rem; position:relative; overflow:hidden;">
            <!-- Barcode / Manual Input Bar -->
            <div style="max-width:540px; margin:0 auto 1.5rem auto;">
                <form id="scanInputForm" onsubmit="handleManualSubmit(event)">
                    <div style="display:flex; gap:0.5rem;">
                        <input 
                            type="text" 
                            id="scanInput" 
                            class="form-control" 
                            placeholder="<?= te('Scan QR Card or Enter Student ID (e.g. STU20260001)...') ?>" 
                            autofocus 
                            autocomplete="off"
                            style="font-size:1.1rem; font-family:monospace; padding:0.75rem 1rem;"
                        >
                        <button type="submit" class="btn btn--primary" style="padding:0 1.5rem; font-weight:700;">
                            <?= te('Verify') ?>
                        </button>
                    </div>
                </form>
                <div class="form-hint text-xs mt-1" style="color:var(--text-muted);">
                    <?= te('Scanner hardware will automatically trigger on barcode / QR swipe.') ?>
                </div>
            </div>

            <!-- Virtual Scanner Target Graphic -->
            <div id="scannerTargetBox" style="width:260px; height:260px; margin:0 auto; border:3px dashed var(--primary); border-radius:16px; display:flex; flex-direction:column; align-items:center; justify-content:center; background:var(--surface-sunken); transition:all 0.3s ease;">
                <div style="font-size:3rem; margin-bottom:0.5rem;">&#128247;</div>
                <strong style="color:var(--text); font-size:0.95rem;"><?= te('Present QR Code') ?></strong>
                <span class="text-xs text-muted mt-1"><?= te('Ready for input') ?></span>
            </div>

            <!-- Last Scanned Result Overlay Card -->
            <div id="scanResultCard" style="display:none; max-width:480px; margin:1.5rem auto 0 auto; padding:1.25rem; border-radius:12px; animation:slideUp 0.3s ease;">
                <div style="display:flex; align-items:center; gap:1rem; text-align:left;">
                    <div id="resPhoto" style="width:64px; height:64px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; flex-shrink:0; overflow:hidden;">
                        S
                    </div>
                    <div style="flex:1;">
                        <div id="resStatusBadge" class="badge mb-1">Status</div>
                        <h3 id="resName" style="margin:0 0 0.2rem 0; font-size:1.15rem;">Student Name</h3>
                        <div id="resId" style="font-family:monospace; font-weight:700; font-size:0.875rem; color:var(--primary);">STU20260001</div>
                        <div id="resMsg" class="text-xs text-muted mt-1">Message</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: LIVE SCAN LOGS PANEL -->
    <div class="card" style="display:flex; flex-direction:column; height:fit-content; max-height:650px;">
        <div class="d-flex justify-between items-center mb-2 pb-2" style="border-bottom:1px solid var(--border);">
            <h3 style="margin:0; font-size:1rem;"><?= te('Live Terminal Feed') ?></h3>
            <span class="text-xs text-muted" id="feedCount">0 <?= te('Scans') ?></span>
        </div>

        <div id="terminalFeedList" style="overflow-y:auto; display:flex; flex-direction:column; gap:0.6rem; padding-right:0.25rem; flex:1;">
            <div class="empty-state" id="emptyFeedState" style="padding:2rem 1rem;">
                <div class="empty-state__icon">&#128225;</div>
                <div class="empty-state__title" style="font-size:0.9rem;"><?= te('Waiting for scans...') ?></div>
                <div class="empty-state__text text-xs"><?= te('Student attendance scans will appear here live.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- DEACTIVATED ACCOUNT RED ALERT MODAL (Document 2 Page 2, 9) -->
<!-- ======================================================== -->
<!-- Fee reminder. Amber, not red: the student has been checked in and
     is welcome; this is for the desk to mention the outstanding months
     while the family is still standing there. -->
<div id="feeOverdueModal" style="display:none; position:fixed; inset:0; background:rgba(180, 83, 9, 0.40); backdrop-filter:blur(6px); z-index:10000; align-items:center; justify-content:center; animation:fadeIn 0.2s ease;">
    <div style="background:#ffffff; border:3px solid #D97706; border-radius:16px; width:100%; max-width:540px; margin:1rem; padding:1.9rem; box-shadow:0 25px 50px -12px rgba(217,119,6,0.35);">
        <div style="text-align:center;">
            <div style="width:68px; height:68px; border-radius:50%; background:#FEF3C7; color:#B45309; display:flex; align-items:center; justify-content:center; font-size:2.2rem; margin:0 auto .9rem auto;">
                &#128181;
            </div>
            <h2 style="margin:0 0 .25rem; font-size:1.3rem; color:#B45309;"><?= te('Fee not paid') ?></h2>
            <p style="margin:0 0 1rem; color:#64748B; font-size:.9rem;"><?= te('The student has been checked in. Please mention this at the desk.') ?></p>
        </div>

        <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:12px; padding:.9rem 1rem; margin-bottom:1rem;">
            <div style="font-weight:700; font-size:1.05rem; color:#1C2538;" id="feeStudentName"></div>
            <div style="font-family:ui-monospace,monospace; font-size:.85rem; color:#64748B;" id="feeStudentID"></div>
        </div>

        <p style="margin:0 0 .4rem; font-weight:600; font-size:.9rem; color:#1C2538;">
            <?= te('Unpaid months') ?> (<span id="feeMonthCount">0</span>)
        </p>
        <ul id="feeMonthList" style="list-style:none; margin:0 0 1rem; padding:0; max-height:190px; overflow:auto; border:1px solid #E2E8F0; border-radius:10px;"></ul>

        <div style="display:flex; justify-content:space-between; align-items:center; padding:.7rem .9rem; background:#FEF3C7; border-radius:10px; margin-bottom:1.2rem;">
            <span style="font-weight:600; color:#92400E;"><?= te('Total outstanding') ?></span>
            <strong style="font-size:1.15rem; color:#B45309;"><span id="feeTotal">0.00</span></strong>
        </div>

        <button type="button" onclick="closeFeeOverdueModal()"
                style="width:100%; padding:.8rem; border:0; border-radius:10px; background:#D97706; color:#fff; font-weight:700; font-size:1rem; cursor:pointer;">
            <?= te('Noted, continue scanning') ?>
        </button>
    </div>
</div>

<style>
    #feeMonthList li { display:flex; justify-content:space-between; gap:1rem; padding:.55rem .8rem; font-size:.88rem; border-bottom:1px solid #F1F5F9; }
    #feeMonthList li:last-child { border-bottom:0; }
    #feeMonthList li strong { color:#B45309; font-variant-numeric: tabular-nums; }
</style>

<div id="deactivatedAlertModal" style="display:none; position:fixed; inset:0; background:rgba(220, 38, 38, 0.45); backdrop-filter:blur(6px); z-index:10000; align-items:center; justify-content:center; animation:fadeIn 0.2s ease;">
    <div style="background:#ffffff; border:3px solid #dc2626; border-radius:16px; width:100%; max-width:540px; margin:1rem; padding:2rem; box-shadow:0 25px 50px -12px rgba(220,38,38,0.35); text-align:center;">
        <div style="width:72px; height:72px; border-radius:50%; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:2.5rem; margin:0 auto 1rem auto; animation:bounce 1s infinite;">
            &#9888;
        </div>

        <h2 style="color:#b91c1c; font-size:1.5rem; margin:0 0 0.5rem 0;" id="deactModalHeader">
            <?= te('Account Deactivated') ?>
        </h2>

        <div id="deactStudentDetails" style="padding:0.75rem; background:#fef2f2; border-radius:8px; border:1px solid #fecaca; margin-bottom:1rem; display:inline-block; font-weight:700; color:#991b1b;">
            <span id="deactStudentName"><?= te('Student Name') ?></span> &bull; <code id="deactStudentID">STU20260001</code>
        </div>

        <p style="color:#4b5563; font-size:0.95rem; line-height:1.5; margin:0 0 1.5rem 0;" id="deactModalBody">
            <?= te('Your account has been automatically deactivated because the monthly fee was not received within the 21-day grace period. Please make a payment to reactivate your access.') ?>
        </p>

        <div style="padding:0.5rem; background:#fee2e2; border-radius:6px; color:#991b1b; font-weight:700; font-size:0.8125rem; margin-bottom:1.5rem;">
            &#9873; <?= te('Attendance Marked with RED FLAG in System Logs') ?>
        </div>

        <button type="button" class="btn btn--danger" style="padding:0.75rem 2rem; font-size:1rem; font-weight:700; width:100%;" onclick="closeDeactivatedModal()">
            <?= te('Acknowledge & Silence Siren') ?>
        </button>
    </div>
</div>

<div id="scanExpiredNote" style="display:none; margin:0 0 1rem; padding:.85rem 1rem; border-radius:10px;
     background:#FEF2F2; border:1px solid #FECACA; color:#B3261E; font-weight:600; text-align:center;">
    <?= te('This page has been open too long and scanning has stopped. Please reload the page to carry on.') ?>
    <button type="button" onclick="window.location.reload()"
            style="margin-left:.6rem; padding:.3rem .8rem; border-radius:8px; border:0; background:#B3261E; color:#fff; cursor:pointer; font-weight:600;">
        <?= te('Reload') ?>
    </button>
</div>

<script>
// Labels the JS below swaps in dynamically -- translated once here so the
// terminal reads correctly in Sinhala instead of hard-coded English.
var TL = <?= json_encode([
    'present'     => t('Present'),
    'redFlag'     => t('RED FLAG'),
    'scans'       => t('Scans'),
    'student'     => t('Student'),
    'scanError'   => t('Scan error.'),
    'sirenTest'   => t('5-Second Siren Test playing!'),
    'accountDeactivated' => t('Account Deactivated'),
], JSON_UNESCAPED_UNICODE) ?>;

// Web Audio API 5-Second Loud Siren Alert Generator
var audioCtx = null;
var sirenInterval = null;

function getAudioContext() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
}

// 5-Second Continuous Loud Alarm Sound (Document 2 Page 2, 9 Requirement)
function playLoudDeactivatedAlertSound() {
    try {
        var ctx = getAudioContext();
        var startTime = ctx.currentTime;
        var duration = 5.0; // exactly 5 seconds

        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = 'sawtooth';
        // Alternating frequency siren between 880Hz and 440Hz
        for (var t = 0; t < duration; t += 0.5) {
            osc.frequency.setValueAtTime(880, startTime + t);
            osc.frequency.setValueAtTime(440, startTime + t + 0.25);
        }

        gain.gain.setValueAtTime(0.5, startTime);
        gain.gain.exponentialRampToValueAtTime(0.01, startTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(startTime);
        osc.stop(startTime + duration);
    } catch (e) {
        console.error('Audio playback error:', e);
    }
}

// Pleasant Chime for Present / Valid Check-in
function playSuccessChime() {
    try {
        var ctx = getAudioContext();
        var now = ctx.currentTime;
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(523.25, now); // C5
        osc.frequency.setValueAtTime(659.25, now + 0.1); // E5
        osc.frequency.setValueAtTime(783.99, now + 0.2); // G5

        gain.gain.setValueAtTime(0.3, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.6);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + 0.6);
    } catch (e) {}
}

function testAlertAudio() {
    playLoudDeactivatedAlertSound();
    alert(TL.sirenTest);
}

// Handle Form Submission
function handleManualSubmit(e) {
    e.preventDefault();
    var input = document.getElementById('scanInput');
    var code = input.value.trim();
    if (!code) return;

    var classId = document.getElementById('terminalClassSelector').value;
    processScan(code, classId);
    input.value = '';
    input.focus();
}

// Process scan request via AJAX
function processScan(code, classId) {
    var box = document.getElementById('scannerTargetBox');
    box.style.borderColor = '#3b82f6';
    box.style.background = '#eff6ff';

    var csrfMetaTag = document.querySelector('meta[name="csrf-token"]');

    fetch('<?= BASE_URL ?>/attendance/scan', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfMetaTag ? csrfMetaTag.getAttribute('content') : ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ code: code, class_id: classId })
    })
    .then(function(res) {
        // A scan terminal is left open all day, and the CSRF token in the
        // page expires after an hour. Without this the scanner would just
        // stop working with no sign of why, so say so plainly instead.
        if (res.status === 403) {
            throw new Error('csrf_expired');
        }
        return res.json();
    })
    .then(function(data) {
        box.style.borderColor = 'var(--primary)';
        box.style.background = 'var(--surface-sunken)';

        if (data.is_deactivated || data.status === 'red_flag') {
            // TRIGGER 5-SEC LOUD ALERT SOUND AND MODAL
            playLoudDeactivatedAlertSound();
            showDeactivatedModal(data);
            addFeedItem(data, 'red_flag');
        } else if (data.success) {
            // The scan has already been recorded. An unpaid fee is a
            // reminder for the desk, never a refusal, so the student is
            // still checked in and the normal chime still plays.
            if (data.payment_due && data.payment_due.overdue) {
                playFeeOverdueSiren();
                showFeeOverdueModal(data);
            } else {
                playSuccessChime();
            }
            showScanSuccess(data);
            addFeedItem(data, 'present');
        } else {
            alert(data.message || TL.scanError);
        }
    })
    .catch(function(err) {
        console.error('Scan error:', err);
        box.style.borderColor = 'var(--danger)';

        if (err && err.message === 'csrf_expired') {
            var note = document.getElementById('scanExpiredNote');
            if (note) {
                note.style.display = 'block';
            }
        }
    });
}

// Fee reminder siren. Deliberately gentler than the deactivated-account
// alert -- two short rising notes rather than five seconds of wailing.
// A family that is simply late should not be announced to the whole
// hall as though something is wrong with them.
function playFeeOverdueSiren() {
    try {
        var ctx = getAudioContext();
        var t0 = ctx.currentTime;

        [0, 0.42].forEach(function (offset) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(620, t0 + offset);
            osc.frequency.linearRampToValueAtTime(880, t0 + offset + 0.3);
            gain.gain.setValueAtTime(0.0001, t0 + offset);
            gain.gain.exponentialRampToValueAtTime(0.45, t0 + offset + 0.04);
            gain.gain.exponentialRampToValueAtTime(0.0001, t0 + offset + 0.34);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t0 + offset);
            osc.stop(t0 + offset + 0.36);
        });
    } catch (e) {
        console.error('Audio playback error:', e);
    }
}

function showFeeOverdueModal(data) {
    var modal = document.getElementById('feeOverdueModal');
    if (!modal) { return; }

    var s = data.student || {};
    var due = data.payment_due || {};

    document.getElementById('feeStudentName').textContent = s.full_name || TL.student;
    document.getElementById('feeStudentID').textContent = s.student_id_display || '';

    var list = document.getElementById('feeMonthList');
    list.innerHTML = '';
    (due.detail || []).forEach(function (d) {
        var li = document.createElement('li');
        var left = document.createElement('span');
        left.textContent = d.label + (d.class_name ? ' \u00b7 ' + d.class_name : '');
        var right = document.createElement('strong');
        right.textContent = Number(d.balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        li.appendChild(left);
        li.appendChild(right);
        list.appendChild(li);
    });

    document.getElementById('feeTotal').textContent =
        Number(due.total || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('feeMonthCount').textContent = due.count || 0;

    modal.style.display = 'flex';
}

function closeFeeOverdueModal() {
    var modal = document.getElementById('feeOverdueModal');
    if (modal) { modal.style.display = 'none'; }
    var input = document.getElementById('scanInput');
    if (input) { input.focus(); }
}

function showDeactivatedModal(data) {
    var modal = document.getElementById('deactivatedAlertModal');
    var s = data.student || {};
    document.getElementById('deactModalHeader').textContent = data.header || TL.accountDeactivated;
    document.getElementById('deactStudentName').textContent = s.full_name || TL.student;
    document.getElementById('deactStudentID').textContent = s.student_id_display || '';
    document.getElementById('deactModalBody').textContent = data.message || '';
    modal.style.display = 'flex';
}

function closeDeactivatedModal() {
    document.getElementById('deactivatedAlertModal').style.display = 'none';
    document.getElementById('scanInput').focus();
}

function showScanSuccess(data) {
    var card = document.getElementById('scanResultCard');
    var s = data.student || {};
    card.style.display = 'block';
    card.style.background = '#f0fdf4';
    card.style.border = '1px solid #bbf7d0';

    document.getElementById('resStatusBadge').className = 'badge badge--success';
    document.getElementById('resStatusBadge').textContent = TL.present + ' • ' + (data.check_in_time || '');
    document.getElementById('resName').textContent = s.full_name || TL.student;
    document.getElementById('resId').textContent = s.student_id_display || '';
    document.getElementById('resMsg').textContent = data.message || '';

    var photoEl = document.getElementById('resPhoto');
    if (s.profile_photo) {
        photoEl.innerHTML = '<img src="' + s.profile_photo + '" style="width:100%;height:100%;object-fit:cover;">';
    } else {
        photoEl.innerHTML = (s.full_name ? s.full_name.charAt(0).toUpperCase() : 'S');
    }
}

var scanCount = 0;
function addFeedItem(data, status) {
    var empty = document.getElementById('emptyFeedState');
    if (empty) empty.style.display = 'none';

    var list = document.getElementById('terminalFeedList');
    var s = data.student || {};
    scanCount++;
    document.getElementById('feedCount').textContent = scanCount + ' ' + TL.scans;

    var isRed = (status === 'red_flag');
    var item = document.createElement('div');
    item.style.padding = '0.5rem 0.75rem';
    item.style.borderRadius = '8px';
    item.style.borderLeft = isRed ? '4px solid #dc2626' : '4px solid #16a34a';
    item.style.background = isRed ? '#fef2f2' : 'var(--surface-sunken)';
    item.style.fontSize = '0.8125rem';
    item.style.animation = 'slideUp 0.2s ease';

    item.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <strong>${s.full_name || TL.student}</strong>
            <span class="badge ${isRed ? 'badge--danger' : 'badge--success'}" style="font-size:0.65rem;">
                ${isRed ? TL.redFlag : TL.present}
            </span>
        </div>
        <div class="text-xs text-muted" style="display:flex; justify-content:space-between; margin-top:0.2rem;">
            <span>${s.student_id_display || ''}</span>
            <span>${data.check_in_time || new Date().toLocaleTimeString()}</span>
        </div>
    `;

    list.insertBefore(item, list.firstChild);
}

// Auto-focus input on page load
window.addEventListener('load', function() {
    var input = document.getElementById('scanInput');
    if (input) input.focus();
});
</script>
