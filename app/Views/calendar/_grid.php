<?php
/**
 * One month grid, shared by the staff and family calendars.
 *
 * Expects $gridFrom, $gridTo, $byDate, $month, and optionally $openMap
 * (class_id => bool) to mark which classes the viewer's fee has opened.
 *
 * The grid is a plain table rather than a CSS grid so that it collapses
 * to a readable scroll on a phone, which is where a parent will open it.
 */

$byDate   = $byDate ?? [];
$openMap  = $openMap ?? [];
$showLock = $openMap !== [];

$today = date('Y-m-d');
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$tone = static fn (string $type, ?string $eventType): array => match (true) {
    $type === 'quiz_closes' => ['var(--danger)',  'rgba(180,35,24,.10)'],
    $type === 'quiz_opens'  => ['var(--info)',    'rgba(29,78,216,.10)'],
    $type === 'seminar'     => ['#7C3AED',        'rgba(124,58,237,.12)'],
    $type === 'homework'    => ['#DB2777',        'rgba(219,39,119,.10)'],
    $eventType === 'exam'   => ['var(--danger)',  'rgba(180,35,24,.12)'],
    $eventType === 'test'   => ['var(--warning)', 'rgba(245,179,1,.16)'],
    $eventType === 'holiday' => ['var(--success)', 'rgba(21,128,61,.10)'],
    $eventType === 'extra_class' => ['var(--accent-text)', 'rgba(245,179,1,.16)'],
    $eventType === 'notice' => ['var(--text-secondary)', 'rgba(132,148,172,.14)'],
    default                 => ['var(--primary)', 'rgba(29,78,216,.08)'],
};
?>

<div class="card" style="padding:0; overflow:hidden;">
    <div class="table-wrap">
        <table class="table" style="table-layout:fixed; min-width:760px;">
            <thead>
                <tr>
                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName): ?>
                        <th style="text-align:left; width:14.28%;"><?= htmlspecialchars(lang_date('D', strtotime('next ' . $dayName)), ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $cursor = $gridFrom;
            while ($cursor <= $gridTo):
                echo '<tr>';
                for ($i = 0; $i < 7; $i++):
                    $date = $cursor->format('Y-m-d');
                    $inMonth = $date >= $monthStart && $date <= $monthEnd;
                    $isToday = $date === $today;
                    $items = $byDate[$date] ?? [];
                    ?>
                    <td style="vertical-align:top; height:112px; padding:.4rem;
                               <?= $inMonth ? '' : 'background:var(--surface-sunken); opacity:.6;' ?>
                               <?= $isToday ? 'box-shadow:inset 0 0 0 2px var(--primary);' : '' ?>">
                        <div class="text-sm" style="font-weight:<?= $isToday ? '700' : '600' ?>;
                             color:<?= $isToday ? 'var(--primary)' : 'var(--text-muted)' ?>; margin-bottom:.25rem;">
                            <?= (int) $cursor->format('j') ?>
                        </div>

                        <?php foreach (array_slice($items, 0, 3) as $item):
                            [$colour, $background] = $tone($item['type'], $item['event_type'] ?? null);
                            $locked = $showLock
                                && $item['class_id'] !== null
                                && ($openMap[$item['class_id']] ?? false) === false;
                            ?>
                            <div style="background:<?= $background ?>; border-left:3px solid <?= $colour ?>;
                                        border-radius:4px; padding:.2rem .35rem; margin-bottom:.22rem;
                                        font-size:.72rem; line-height:1.25;">
                                <?php if ($item['start_time'] !== null): ?>
                                    <strong style="color:<?= $colour ?>;"><?= htmlspecialchars($item['start_time'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php endif; ?>
                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($locked): ?>
                                    <span title="<?= te("This month's fee is not settled, so the material stays locked. The class still runs.") ?>"
                                          style="color:var(--text-muted);">&#128274;</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($items) > 3): ?>
                            <div class="text-sm text-muted" style="font-size:.7rem;">
                                <?= te('+{n} more', ['n' => count($items) - 3]) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php
                    $cursor = $cursor->modify('+1 day');
                endfor;
                echo '</tr>';
            endwhile;
            ?>
            </tbody>
        </table>
    </div>
</div>
