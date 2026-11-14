<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

/**
 * "Edit any text on the site" -- the super admin's one screen for
 * overriding exactly what config/lang.php's t()/te() prints, in either
 * language, for any fixed wording anywhere on the site (nav labels,
 * button text, headings, template phrases) without a code change.
 *
 * lang/si.php (the English -> Sinhala dictionary already shipped with the
 * site) is the starting list of overridable rows; this screen layers
 * admin overrides for either language on top of it in the
 * translation_overrides table (see migration 034 and lang.php's
 * lang_overrides()). Only the super admin may touch this -- it reaches
 * every page, every role.
 */
class TranslationController extends BaseController
{
    private const PER_PAGE = 40;

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();

        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $dictionary = require BASE_PATH . '/lang/si.php';
        $overrides = $this->overridesBySource();

        // Every English source text that exists anywhere: the shipped
        // dictionary's keys, plus any already-overridden text that has no
        // dictionary entry of its own (an English-only label, say).
        $sources = array_keys($dictionary);
        foreach (array_keys($overrides) as $src) {
            if (!isset($dictionary[$src])) {
                $sources[] = $src;
            }
        }
        sort($sources, SORT_STRING | SORT_FLAG_CASE);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $sources = array_values(array_filter($sources, static function (string $src) use ($needle, $dictionary, $overrides): bool {
                if (mb_stripos($src, $needle) !== false) {
                    return true;
                }
                if (mb_stripos((string) ($dictionary[$src] ?? ''), $needle) !== false) {
                    return true;
                }
                $ov = $overrides[$src] ?? [];
                return mb_stripos((string) ($ov['en'] ?? ''), $needle) !== false
                    || mb_stripos((string) ($ov['si'] ?? ''), $needle) !== false;
            }));
        }

        $total = count($sources);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $slice = array_slice($sources, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $rows = [];
        foreach ($slice as $src) {
            $rows[] = [
                'source'      => $src,
                'default_si'  => (string) ($dictionary[$src] ?? $src),
                'override_en' => $overrides[$src]['en'] ?? '',
                'override_si' => $overrides[$src]['si'] ?? '',
            ];
        }

        $this->view('translations/index', [
            'pageTitle'      => 'Translations',
            'activePage'     => 'settings',
            'activeSubPage'  => 'translations',
            'rows'       => $rows,
            'q'          => $q,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
        ]);
    }

    /** Save (or clear) one row's English and/or Sinhala override. */
    public function update(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        $source = trim((string) ($_POST['source'] ?? ''));
        $en = trim((string) ($_POST['override_en'] ?? ''));
        $si = trim((string) ($_POST['override_si'] ?? ''));

        if ($source === '') {
            $this->flash('error', t('That text could not be found.'));
            $this->redirectBack();
        }

        $userId = (int) session_get('user_id');
        $this->upsertOrClear($source, 'en', $en, $userId);
        $this->upsertOrClear($source, 'si', $si, $userId);

        $this->flash('success', t('Translation updated.'));
        $this->redirectBack();
    }

    /** @return array<string, array{en?: string, si?: string}> */
    private function overridesBySource(): array
    {
        $out = [];
        $rows = $this->db->query('SELECT source_text, lang, value FROM translation_overrides')
            ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $out[$row['source_text']][$row['lang']] = $row['value'];
        }
        return $out;
    }

    private function upsertOrClear(string $source, string $lang, string $value, int $userId): void
    {
        if ($value === '' || $value === $source) {
            // Blank, or typed back to exactly the English source: that's "no
            // override", so the row is removed rather than stored as a no-op.
            $this->db->prepare('DELETE FROM translation_overrides WHERE source_text = :s AND lang = :l')
                ->execute([':s' => mb_substr($source, 0, 500), ':l' => $lang]);
            return;
        }

        $this->db->prepare(
            'INSERT INTO translation_overrides (source_text, lang, value, updated_by, updated_at)
             VALUES (:s, :l, :v, :by, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by), updated_at = NOW()'
        )->execute([
            ':s'  => mb_substr($source, 0, 500),
            ':l'  => $lang,
            ':v'  => $value,
            ':by' => $userId,
        ]);
    }

    private function redirectBack(): void
    {
        $qs = trim((string) ($_POST['back_qs'] ?? ''));
        $this->redirect(BASE_URL . '/settings/translations' . ($qs !== '' ? '?' . $qs : ''));
    }
}
