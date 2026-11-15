<?php
/**
 * Printable ID Card View for User (Admin, Coordinator, Staff, Teacher)
 */
$user = $user ?? [];
$role = $user['role'] ?? 'staff';
$cfg = $roleColors[$role] ?? [
    'primary' => '#1D4ED8',
    'bg'      => '#F4F7FC',
    'label'   => ucfirst($role),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('ID Card') ?> - <?= htmlspecialchars($user['full_name'] ?? te('User'), ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (is_file(__DIR__ . '/../components/favicon.php')) { include __DIR__ . '/../components/favicon.php'; } ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .print-btn {
            margin-bottom: 2rem;
            padding: 0.65rem 1.5rem;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .id-card-wrapper {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .id-card {
            width: 320px;
            height: 480px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
        }
        .id-card__header {
            background: <?= $cfg['primary'] ?>;
            color: #ffffff;
            padding: 1.25rem 1rem;
            text-align: center;
        }
        .id-card__logo {
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .id-card__sublogo {
            font-size: 0.7rem;
            opacity: 0.9;
        }
        .id-card__body {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            text-align: center;
        }
        .id-card__photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            margin-top: -2.5rem;
            background: #f8fafc;
            object-fit: cover;
        }
        .id-card__name {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0.75rem;
            margin-bottom: 0.25rem;
        }
        .id-card__role {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.2rem 0.75rem;
            border-radius: 20px;
            background: <?= $cfg['bg'] ?>;
            color: <?= $cfg['primary'] ?>;
            display: inline-block;
            margin-bottom: 0.75rem;
        }
        .id-card__id {
            font-family: monospace;
            font-size: 1rem;
            font-weight: 800;
            color: #334155;
            letter-spacing: 0.1em;
            margin-bottom: 1rem;
        }
        .id-card__info-table {
            width: 100%;
            font-size: 0.8rem;
            text-align: left;
            color: #475569;
            border-collapse: collapse;
            margin-top: auto;
        }
        .id-card__info-table td {
            padding: 0.3rem 0;
        }
        .id-card__footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 0.75rem;
            text-align: center;
            font-size: 0.6875rem;
            color: #64748b;
        }
        @media print {
            body { background: none; padding: 0; }
            .print-btn { display: none; }
            .id-card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <button type="button" class="print-btn" onclick="window.print()">
        &#128424; <?= te('Print ID Card') ?>
    </button>

    <div class="id-card-wrapper">
        <!-- Front Side -->
        <div class="id-card">
            <div class="id-card__header">
                <div class="id-card__logo"><?= htmlspecialchars(\App\Services\PublicSite::brandName(), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="id-card__sublogo"><?= te('Science classes') ?> &middot; <?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></div>
            </div>

            <div class="id-card__body">
                <?php if (!empty($user['profile_photo'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="id-card__photo">
                <?php else: ?>
                    <div class="id-card__photo" style="display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:700; color:<?= $cfg['primary'] ?>;">
                        <?= strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div class="id-card__name"><?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="id-card__role"><?= $cfg['label'] ?></div>
                <div class="id-card__id"><?= htmlspecialchars($user['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>

                <table class="id-card__info-table">
                    <tr>
                        <td style="font-weight:600; width:35%;"><?= te('Phone:') ?></td>
                        <td><?= htmlspecialchars($user['phone1'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;"><?= te('Email:') ?></td>
                        <td><?= htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;"><?= te('Issue Date:') ?></td>
                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                </table>
            </div>

            <div class="id-card__footer">
                <?= te('Official Staff Identification Card') ?> &bull; <?= te('Scan for Attendance & Verification') ?>
            </div>
        </div>
    </div>

</body>
</html>
