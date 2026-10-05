<?php
/**
 * Student 360° View
 *
 * Variables: $student, $parents, $enrollments, $payments, $paymentSummary,
 *            $attendanceRecords, $attendanceSummary, $feeRiskRecords,
 *            $workflows, $auditTrail, $createdByName
 */

$student            = $student ?? [];
$parents            = $parents ?? [];
$enrollments        = $enrollments ?? [];
$payments           = $payments ?? [];
$paymentSummary     = $paymentSummary ?? ['total_due' => 0, 'total_paid' => 0, 'pending_count' => 0, 'overdue_count' => 0];
$attendanceRecords  = $attendanceRecords ?? [];
$attendanceSummary  = $attendanceSummary ?? ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'red_flag' => 0];
$feeRiskRecords     = $feeRiskRecords ?? [];
$workflows          = $workflows ?? [];
$auditTrail         = $auditTrail ?? [];
$createdByName      = $createdByName ?? '';

$status = $student['status'] ?? 'active';
$badgeClass = match ($status) {
    'active'      => 'badge--success',
    'inactive'    => 'badge--warning',
    'deactivated' => 'badge--danger',
    default       => 'badge--neutral',
};

// Attendance rate calculation
$attRate = $attendanceSummary['total'] > 0
    ? round(($attendanceSummary['present'] + $attendanceSummary['late']) / $attendanceSummary['total'] * 100, 1)
    : 0;

// Active enrollments count
$activeEnrollments = array_filter($enrollments, fn($e) => ($e['enrollment_status'] ?? '') === 'active');

// Helper for safe output
$e = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
?>

<style>
.s360-grid { display: grid; grid-template-columns: 300px 1fr; gap: 1.5rem; align-items: start; }
@media (max-width: 900px) { .s360-grid { grid-template-columns: 1fr; } }

.s360-profile { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.s360-profile__header { background: linear-gradient(135deg, var(--primary), #6366f1); padding: 2rem 1.5rem 1.5rem; text-align: center; color: #fff; }
.s360-profile__avatar { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 2rem; font-weight: 700; color: #fff; border: 3px solid rgba(255,255,255,0.4); overflow: hidden; }
.s360-profile__avatar img { width: 100%; height: 100%; object-fit: cover; }
.s360-profile__name { font-size: 1.125rem; font-weight: 700; margin-bottom: 0.25rem; }
.s360-profile__id { font-size: 0.8125rem; opacity: 0.85; margin-bottom: 0.5rem; }
.s360-profile__body { padding: 1.25rem; }
.s360-profile__row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border); font-size: 0.8125rem; }
.s360-profile__row:last-child { border-bottom: none; }
.s360-profile__label { color: var(--text-muted); flex-shrink: 0; margin-right: 0.75rem; }
.s360-profile__value { font-weight: 500; text-align: right; word-break: break-word; }
.s360-profile__actions { padding: 0 1.25rem 1.25rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }

.s360-kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
.s360-kpi { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem; text-align: center; }
.s360-kpi__value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
.s360-kpi__label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
.s360-kpi--green .s360-kpi__value { color: var(--success); }
.s360-kpi--blue .s360-kpi__value { color: var(--primary); }
.s360-kpi--orange .s360-kpi__value { color: var(--warning); }
.s360-kpi--red .s360-kpi__value { color: var(--danger); }

.s360-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 1rem; overflow-x: auto; }
.s360-tab { padding: 0.625rem 1rem; font-size: 0.8125rem; font-weight: 500; color: var(--text-muted); background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; white-space: nowrap; transition: all 0.2s; }
.s360-tab:hover { color: var(--text); }
.s360-tab.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }

.s360-section { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1rem; }
.s360-section__title { font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }

.s360-timeline { position: relative; padding-left: 1.5rem; }
.s360-timeline::before { content: ''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: var(--border); }
.s360-timeline__item { position: relative; padding-bottom: 1.25rem; }
.s360-timeline__dot { position: absolute; left: -1.5rem; top: 4px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--surface); }
.s360-timeline__dot--blue { background: var(--primary); }
.s360-timeline__dot--green { background: var(--success); }
.s360-timeline__dot--red { background: var(--danger); }
.s360-timeline__dot--orange { background: var(--warning); }

.risk-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 600; }
.risk-badge--safe { background: #dcfce7; color: #166534; }
.risk-badge--grace { background: #fef9c3; color: #854d0e; }
.risk-badge--high_risk { background: #fee2e2; color: #991b1b; }
.risk-badge--deactivated { background: #fecaca; color: #7f1d1d; }
.risk-badge--temporary_access { background: #dbeafe; color: #1e40af; }
</style>

<div class="s360-grid">
    <!-- ═══ LEFT: Profile Card ═══ -->
    <div>
        <div class="s360-profile">
            <div class="s360-profile__header">
                <div class="s360-profile__avatar">
                    <?php if (!empty($student['profile_photo'])): ?>
                        <img src="<?= $e($student['profile_photo']) ?>" alt="Photo">
                    <?php else: ?>
                        <?= strtoupper(mb_substr($student['full_name'] ?? 'S', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="s360-profile__name"><?= $e($student['full_name']) ?></div>
                <div class="s360-profile__id"><?= $e($student['student_id_display']) ?></div>
                <span class="badge <?= $badgeClass ?>" style="font-size:0.75rem;"><?= ucfirst($e($status)) ?></span>
            </div>

            <div class="s360-profile__body">
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Phone</span>
                    <span class="s360-profile__value"><?= $e($student['phone1'] ?? '-') ?></span>
                </div>
                <?php if (!empty($student['phone2'])): ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Phone 2</span>
                    <span class="s360-profile__value"><?= $e($student['phone2']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($student['whatsapp'])): ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">WhatsApp</span>
                    <span class="s360-profile__value"><?= $e($student['whatsapp']) ?></span>
                </div>
                <?php endif; ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Email</span>
                    <span class="s360-profile__value"><?= $e($student['email'] ?? '-') ?></span>
                </div>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">DOB</span>
                    <span class="s360-profile__value"><?= !empty($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : '-' ?></span>
                </div>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Address</span>
                    <span class="s360-profile__value"><?= $e($student['address'] ?? '-') ?></span>
                </div>
                <?php if (!empty($student['province']) || !empty($student['district'])): ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Province / District</span>
                    <span class="s360-profile__value"><?= $e(($student['district'] ?? '') . ($student['district'] && $student['province'] ? ', ' : '') . ($student['province'] ?? '')) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($student['home_number'])): ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Home No.</span>
                    <span class="s360-profile__value"><?= $e($student['home_number']) ?></span>
                </div>
                <?php endif; ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Registered</span>
                    <span class="s360-profile__value"><?= !empty($student['created_at']) ? date('M d, Y', strtotime($student['created_at'])) : '-' ?></span>
                </div>
                <?php if ($createdByName !== ''): ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Created By</span>
                    <span class="s360-profile__value"><?= $e($createdByName) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($student['notes'])): ?>
                <div class="s360-profile__row">
                    <span class="s360-profile__label">Notes</span>
                    <span class="s360-profile__value"><?= $e($student['notes']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="s360-profile__actions">
                <a href="<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>/edit" class="btn btn--secondary btn--sm">Edit</a>
                <?php if ($status === 'active'): ?>
                    <button type="button" class="btn btn--ghost btn--sm" onclick="openModal('deactivateModal')">Deactivate</button>
                <?php elseif ($status === 'deactivated'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>/reactivate" style="display:inline;">
                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                        <button type="submit" class="btn btn--success btn--sm">Reactivate</button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn--danger btn--sm" onclick="openModal('deleteModal')">Delete</button>
            </div>
        </div>

        <!-- Parent / Guardian Card -->
        <div class="s360-section" style="margin-top:1rem;">
            <div class="s360-section__title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4-4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Parent / Guardian
            </div>
            <?php if (!empty($parents)): ?>
                <?php foreach ($parents as $parent): ?>
                    <div style="padding:0.5rem 0; border-bottom:1px solid var(--border); font-size:0.8125rem;">
                        <div style="font-weight:600; margin-bottom:0.25rem;"><?= $e($parent['parent_name']) ?></div>
                        <?php if (!empty($parent['phone1'])): ?>
                            <div style="color:var(--text-muted);">Phone: <?= $e($parent['phone1']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($parent['phone2'])): ?>
                            <div style="color:var(--text-muted);">Phone 2: <?= $e($parent['phone2']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($parent['whatsapp'])): ?>
                            <div style="color:var(--text-muted);">WhatsApp: <?= $e($parent['whatsapp']) ?></div>
                        <?php endif; ?>
                        <span class="badge <?= ($parent['status'] ?? 'active') === 'active' ? 'badge--success' : 'badge--danger' ?>" style="font-size:0.6875rem; margin-top:0.25rem;">
                            <?= ucfirst($e($parent['status'] ?? 'active')) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-sm text-muted" style="margin:0;">No parent/guardian linked.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ RIGHT: Tabs & Content ═══ -->
    <div>
        <!-- KPI Row -->
        <div class="s360-kpi-row">
            <div class="s360-kpi s360-kpi--blue">
                <div class="s360-kpi__value"><?= count($activeEnrollments) ?></div>
                <div class="s360-kpi__label">Active Classes</div>
            </div>
            <div class="s360-kpi s360-kpi--green">
                <div class="s360-kpi__value"><?= $attRate ?>%</div>
                <div class="s360-kpi__label">Attendance Rate</div>
            </div>
            <div class="s360-kpi s360-kpi--orange">
                <div class="s360-kpi__value"><?= $paymentSummary['pending_count'] ?></div>
                <div class="s360-kpi__label">Pending Payments</div>
            </div>
            <div class="s360-kpi s360-kpi--red">
                <div class="s360-kpi__value"><?= $paymentSummary['overdue_count'] ?></div>
                <div class="s360-kpi__label">Overdue</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="s360-tabs" role="tablist">
            <button class="s360-tab active" role="tab" data-tab="enrollments" onclick="s360Tab('enrollments', this)">Enrollments</button>
            <button class="s360-tab" role="tab" data-tab="payments" onclick="s360Tab('payments', this)">Payments</button>
            <button class="s360-tab" role="tab" data-tab="attendance" onclick="s360Tab('attendance', this)">Attendance</button>
            <button class="s360-tab" role="tab" data-tab="fee-risk" onclick="s360Tab('fee-risk', this)">Fee Risk</button>
            <button class="s360-tab" role="tab" data-tab="workflows" onclick="s360Tab('workflows', this)">Workflows</button>
            <button class="s360-tab" role="tab" data-tab="audit" onclick="s360Tab('audit', this)">Audit Trail</button>
        </div>

        <!-- ─── Enrollments Tab ─── -->
        <div class="s360-panel" id="panel-enrollments">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Institute</th>
                            <th>Schedule</th>
                            <th>Monthly Fee</th>
                            <th>Scholarship</th>
                            <th>Status</th>
                            <th>Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($enrollments)): ?>
                            <?php foreach ($enrollments as $enr): ?>
                                <tr>
                                    <td class="font-medium"><?= $e($enr['class_name'] ?? '') ?></td>
                                    <td class="text-sm"><?= $e($enr['institute_name'] ?? '-') ?></td>
                                    <td class="text-sm">
                                        <?php
                                        $days = $enr['schedule_days'] ?? null;
                                        if (is_string($days)) {
                                            $days = json_decode($days, true);
                                        }
                                        if (is_array($days) && !empty($days)) {
                                            $shortDays = array_map(fn($d) => substr($d, 0, 3), $days);
                                            echo $e(implode(', ', $shortDays));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                        <?php if (!empty($enr['start_time'])): ?>
                                            <br><span style="color:var(--text-muted);"><?= $e(date('g:i A', strtotime($enr['start_time']))) ?> - <?= $e(date('g:i A', strtotime($enr['end_time'] ?? $enr['start_time']))) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-medium">LKR <?= number_format((float) ($enr['monthly_fee'] ?? 0), 2) ?></td>
                                    <td>
                                        <?php
                                        $schType = $enr['scholarship_type'] ?? 'full_payment';
                                        echo match ($schType) {
                                            'half_payment' => '<span class="badge badge--info">Half (50%)</span>',
                                            'free_card'    => '<span class="badge badge--success">Free Card</span>',
                                            default        => '<span class="badge badge--neutral">Full</span>',
                                        };
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $enrStatus = $enr['enrollment_status'] ?? 'active';
                                        echo '<span class="badge badge--' . ($enrStatus === 'active' ? 'success' : 'neutral') . '">' . ucfirst($e($enrStatus)) . '</span>';
                                        ?>
                                    </td>
                                    <td class="text-sm"><?= !empty($enr['enrolled_at']) ? date('M d, Y', strtotime($enr['enrolled_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="table-empty">No enrollments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─── Payments Tab ─── -->
        <div class="s360-panel" id="panel-payments" style="display:none;">
            <!-- Payment Summary -->
            <div class="s360-kpi-row" style="margin-bottom:1rem;">
                <div class="s360-kpi">
                    <div class="s360-kpi__value" style="color:var(--text);">LKR <?= number_format($paymentSummary['total_due'], 2) ?></div>
                    <div class="s360-kpi__label">Total Due</div>
                </div>
                <div class="s360-kpi s360-kpi--green">
                    <div class="s360-kpi__value">LKR <?= number_format($paymentSummary['total_paid'], 2) ?></div>
                    <div class="s360-kpi__label">Total Paid</div>
                </div>
                <div class="s360-kpi s360-kpi--orange">
                    <div class="s360-kpi__value">LKR <?= number_format($paymentSummary['total_due'] - $paymentSummary['total_paid'], 2) ?></div>
                    <div class="s360-kpi__label">Outstanding</div>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Class</th>
                            <th>Due (LKR)</th>
                            <th>Paid (LKR)</th>
                            <th>Status</th>
                            <th>Paid Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $pmt): ?>
                                <tr>
                                    <td class="font-medium">
                                        <?php
                                        $monthVal = $pmt['month_year'] ?? $pmt['month'] ?? '';
                                        echo $monthVal ? date('M Y', strtotime($monthVal)) : '-';
                                        ?>
                                    </td>
                                    <td><?= $e($pmt['class_name'] ?? '') ?></td>
                                    <td>LKR <?= number_format((float) ($pmt['amount_due'] ?? 0), 2) ?></td>
                                    <td>LKR <?= number_format((float) ($pmt['amount_paid'] ?? 0), 2) ?></td>
                                    <td>
                                        <?php
                                        $pmtStatus = $pmt['payment_status'] ?? $pmt['status'] ?? 'pending';
                                        $pmtBadge = match ($pmtStatus) {
                                            'paid'    => 'badge--success',
                                            'pending' => 'badge--warning',
                                            'overdue' => 'badge--danger',
                                            'waived'  => 'badge--info',
                                            default   => 'badge--neutral',
                                        };
                                        ?>
                                        <span class="badge <?= $pmtBadge ?>"><?= ucfirst($e($pmtStatus)) ?></span>
                                    </td>
                                    <td class="text-sm"><?= !empty($pmt['paid_at']) ? date('M d, Y g:i A', strtotime($pmt['paid_at'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="table-empty">No payment records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─── Attendance Tab ─── -->
        <div class="s360-panel" id="panel-attendance" style="display:none;">
            <!-- Attendance Summary -->
            <div class="s360-kpi-row" style="margin-bottom:1rem;">
                <div class="s360-kpi">
                    <div class="s360-kpi__value" style="color:var(--text);"><?= $attendanceSummary['total'] ?></div>
                    <div class="s360-kpi__label">Total Records</div>
                </div>
                <div class="s360-kpi s360-kpi--green">
                    <div class="s360-kpi__value"><?= $attendanceSummary['present'] ?></div>
                    <div class="s360-kpi__label">Present</div>
                </div>
                <div class="s360-kpi s360-kpi--orange">
                    <div class="s360-kpi__value"><?= $attendanceSummary['late'] ?></div>
                    <div class="s360-kpi__label">Late</div>
                </div>
                <div class="s360-kpi s360-kpi--red">
                    <div class="s360-kpi__value"><?= $attendanceSummary['red_flag'] ?></div>
                    <div class="s360-kpi__label">Red Flags</div>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Class</th>
                            <th>Check-in</th>
                            <th>Status</th>
                            <th>Method</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($attendanceRecords)): ?>
                            <?php foreach ($attendanceRecords as $att): ?>
                                <tr>
                                    <td class="font-medium"><?= $e(!empty($att['date']) ? date('M d, Y', strtotime($att['date'])) : '-') ?></td>
                                    <td><?= $e($att['class_name'] ?? '-') ?></td>
                                    <td class="text-sm"><?= $e(!empty($att['check_in_time']) ? date('g:i A', strtotime($att['check_in_time'])) : '-') ?></td>
                                    <td>
                                        <?php
                                        $attStatus = $att['status'] ?? 'present';
                                        $attBadge = match ($attStatus) {
                                            'present'  => 'badge--success',
                                            'late'     => 'badge--warning',
                                            'absent'   => 'badge--neutral',
                                            'red_flag' => 'badge--danger',
                                            default    => 'badge--neutral',
                                        };
                                        $attLabel = match ($attStatus) {
                                            'red_flag' => 'Red Flag',
                                            default    => ucfirst($attStatus),
                                        };
                                        ?>
                                        <span class="badge <?= $attBadge ?>"><?= $e($attLabel) ?></span>
                                    </td>
                                    <td class="text-sm"><?= ucfirst($e($att['scan_method'] ?? 'qr_scan')) ?></td>
                                    <td class="text-sm" style="max-width:200px;"><?= $e($att['flagged_reason'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="table-empty">No attendance records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─── Fee Risk Tab ─── -->
        <div class="s360-panel" id="panel-fee-risk" style="display:none;">
            <?php if (!empty($feeRiskRecords)): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Risk State</th>
                                <th>Grace Start</th>
                                <th>Deactivation Date</th>
                                <th>Temp Access Until</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeRiskRecords as $fr): ?>
                                <tr>
                                    <td class="font-medium"><?= $e($fr['class_name'] ?? '-') ?></td>
                                    <td>
                                        <?php $riskState = $fr['risk_state'] ?? 'safe'; ?>
                                        <span class="risk-badge risk-badge--<?= $e($riskState) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $e($riskState))) ?>
                                        </span>
                                    </td>
                                    <td class="text-sm"><?= !empty($fr['grace_start_date']) ? date('M d, Y', strtotime($fr['grace_start_date'])) : '-' ?></td>
                                    <td class="text-sm"><?= !empty($fr['deactivation_date']) ? date('M d, Y', strtotime($fr['deactivation_date'])) : '-' ?></td>
                                    <td class="text-sm"><?= !empty($fr['temp_access_until']) ? date('M d, Y', strtotime($fr['temp_access_until'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon" style="font-size:2rem;">&#9989;</div>
                    <div class="empty-state__title">No Fee Risk Records</div>
                    <div class="empty-state__text">This student has no fee-risk entries. All clear!</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ─── Workflows Tab ─── -->
        <div class="s360-panel" id="panel-workflows" style="display:none;">
            <?php if (!empty($workflows)): ?>
                <div class="s360-timeline">
                    <?php foreach ($workflows as $wf): ?>
                        <div class="s360-timeline__item">
                            <?php
                            $dotClass = match ($wf['status'] ?? '') {
                                'completed', 'approved' => 's360-timeline__dot--green',
                                'rejected'              => 's360-timeline__dot--red',
                                'pending'               => 's360-timeline__dot--orange',
                                default                 => 's360-timeline__dot--blue',
                            };
                            ?>
                            <div class="s360-timeline__dot <?= $dotClass ?>"></div>
                            <div class="s360-section" style="margin-bottom:0; padding:0.875rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                    <span class="font-medium" style="text-transform:capitalize;"><?= $e($wf['type'] ?? '') ?></span>
                                    <span class="badge <?= ($wf['status'] ?? '') === 'completed' ? 'badge--success' : (($wf['status'] ?? '') === 'rejected' ? 'badge--danger' : 'badge--warning') ?>">
                                        <?= ucfirst($e($wf['status'] ?? '')) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-muted" style="margin:0.25rem 0 0;">
                                    By <?= $e($wf['initiated_by_name'] ?? 'Unknown') ?> &middot;
                                    <?= !empty($wf['created_at']) ? date('M d, Y g:i A', strtotime($wf['created_at'])) : '' ?>
                                </p>
                                <?php if (!empty($wf['reason'])): ?>
                                    <p class="text-sm" style="margin:0.5rem 0 0;">Reason: <?= $e($wf['reason']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($wf['steps'])): ?>
                                    <div style="margin-top:0.5rem; padding-top:0.5rem; border-top:1px solid var(--border);">
                                        <?php foreach ($wf['steps'] as $step): ?>
                                            <div class="text-sm" style="display:flex; align-items:center; gap:0.5rem; padding:0.2rem 0;">
                                                <?php
                                                $stepAction = $step['action'] ?? $step['status'] ?? 'pending';
                                                $stepIcon = match ($stepAction) {
                                                    'approved' => '<span style="color:var(--success);">&#10003;</span>',
                                                    'rejected' => '<span style="color:var(--danger);">&#10007;</span>',
                                                    default    => '<span style="color:var(--text-muted);">&#9675;</span>',
                                                };
                                                ?>
                                                <?= $stepIcon ?>
                                                <span>Step <?= (int) ($step['step_number'] ?? $step['step_order'] ?? 0) ?>: <?= $e($step['approver_name'] ?? ucfirst($step['approver_role'] ?? '')) ?></span>
                                                <span class="text-muted"><?= $stepAction !== 'pending' && !empty($step['acted_at']) ? date('M d g:i A', strtotime($step['acted_at'])) : '' ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon" style="font-size:2rem;">&#128260;</div>
                    <div class="empty-state__title">No Workflow History</div>
                    <div class="empty-state__text">Workflow events for this student will appear here.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ─── Audit Trail Tab ─── -->
        <div class="s360-panel" id="panel-audit" style="display:none;">
            <?php if (!empty($auditTrail)): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditTrail as $log): ?>
                                <tr>
                                    <td class="text-sm"><?= !empty($log['created_at']) ? date('M d, Y g:i A', strtotime($log['created_at'])) : '' ?></td>
                                    <td class="text-sm">
                                        <?php
                                        // Try to resolve user name
                                        $logUserName = $log['user_name'] ?? '';
                                        if ($logUserName === '' && !empty($log['user_id'])) {
                                            $logUserName = 'User #' . $log['user_id'];
                                        }
                                        echo $e($logUserName ?: 'System');
                                        ?>
                                    </td>
                                    <td><span class="badge badge--neutral"><?= $e(str_replace('_', ' ', ucfirst($log['action'] ?? ''))) ?></span></td>
                                    <td class="text-sm" style="max-width:250px; word-break:break-word;">
                                        <?php
                                        $details = $log['details'] ?? '';
                                        if (is_string($details) && $details !== '') {
                                            $decoded = json_decode($details, true);
                                            if (is_array($decoded)) {
                                                $parts = [];
                                                foreach ($decoded as $k => $v) {
                                                    if (!is_array($v)) {
                                                        $parts[] = $e(str_replace('_', ' ', $k)) . ': ' . $e((string) $v);
                                                    }
                                                }
                                                echo implode(', ', $parts);
                                            } else {
                                                echo $e($details);
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon" style="font-size:2rem;">&#128209;</div>
                    <div class="empty-state__title">No Audit Trail</div>
                    <div class="empty-state__text">Actions performed on this student will be logged here.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Deactivate Modal -->
<div class="modal-backdrop" id="deactivateModal" aria-hidden="true">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Deactivate Student</h3>
            <button type="button" class="modal__close" onclick="closeModal('deactivateModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>/deactivate">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
            <div class="modal__body">
                <p>Are you sure you want to initiate deactivation for <strong><?= $e($student['full_name']) ?></strong>?</p>
                <p class="text-sm text-muted">This will start an approval workflow. The student will be deactivated once all approvers have approved.</p>
                <div class="form-group">
                    <label class="form-label" for="deactivate_reason">Reason <span class="required">*</span></label>
                    <textarea id="deactivate_reason" name="reason" class="form-control" required placeholder="Please provide a reason for deactivation..."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" onclick="closeModal('deactivateModal')">Cancel</button>
                <button type="submit" class="btn btn--primary">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-backdrop" id="deleteModal" aria-hidden="true">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Delete Student</h3>
            <button type="button" class="modal__close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/students/<?= (int) $student['id'] ?>/delete">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
            <div class="modal__body">
                <p>Are you sure you want to initiate deletion for <strong><?= $e($student['full_name']) ?></strong>?</p>
                <p class="text-sm text-muted">This will start a multi-step approval workflow. The student's data will be permanently removed once all approvers have approved.</p>
                <div class="form-group">
                    <label class="form-label" for="delete_reason">Reason <span class="required">*</span></label>
                    <textarea id="delete_reason" name="reason" class="form-control" required placeholder="Please provide a reason for deletion..."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn--danger">Submit for Deletion</button>
            </div>
        </form>
    </div>
</div>

<script>
function s360Tab(tabId, btn) {
    document.querySelectorAll('.s360-panel').forEach(function(p) { p.style.display = 'none'; });
    document.querySelectorAll('.s360-tab').forEach(function(t) { t.classList.remove('active'); });
    var panel = document.getElementById('panel-' + tabId);
    if (panel) panel.style.display = 'block';
    if (btn) btn.classList.add('active');
}

// Modal open/close
function openModal(id) {
    var m = document.getElementById(id);
    if (m) { m.setAttribute('aria-hidden', 'false'); m.style.display = 'flex'; }
}
function closeModal(id) {
    var m = document.getElementById(id);
    if (m) { m.setAttribute('aria-hidden', 'true'); m.style.display = 'none'; }
}

// Modal backdrop click to close
document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) closeModal(backdrop.id);
    });
});
</script>
