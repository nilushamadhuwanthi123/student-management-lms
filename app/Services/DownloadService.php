<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Past papers and other documents for the public Downloads page
 * (migration 026) -- a title plus an external link (typically a
 * Google Drive file or folder). The file itself is never stored on
 * this server: that keeps the LMS's own storage small and lets the
 * teacher or super admin manage the files from Drive directly.
 *
 * Deliberately the same shape and spirit as SiteAdService: a small,
 * flat, ordered list managed by the teacher or the super admin.
 */
final class DownloadService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /** Every row, for the manage screen. */
    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM site_downloads ORDER BY sort_order ASC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM site_downloads WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Active items, for the public Downloads page. */
    public function active(): array
    {
        return $this->db->query(
            'SELECT * FROM site_downloads WHERE is_active = 1 ORDER BY sort_order ASC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active items grouped into (possibly nested) folders by category,
     * for the public Downloads page. A category of "Grade 11/Science/1st
     * Term" becomes three levels deep -- typed with a "/" between each
     * level, no schema change needed. A plain category with no "/" is
     * just one folder, same as before.
     *
     * Each folder keeps its own items' sort_order; folders themselves
     * are ordered by the lowest sort_order among everything inside them
     * (items and sub-folders alike), so moving a category's files up
     * also moves the folder up. Anything with no category lands in one
     * folder at the end, labelled by the caller (never left out).
     *
     * @return array<int, array{category: ?string, items: array<int, array<string, mixed>>, children: array}>
     */
    public function activeGrouped(): array
    {
        return $this->buildFolderTree($this->active());
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function buildFolderTree(array $rows): array
    {
        $nodes = [];
        $uncategorised = [];

        foreach ($rows as $row) {
            $raw = trim((string) ($row['category'] ?? ''));
            $parts = $raw !== ''
                ? array_values(array_filter(array_map('trim', explode('/', $raw)), static fn ($p) => $p !== ''))
                : [];

            if (empty($parts)) {
                $uncategorised[] = $row;
                continue;
            }

            $head = $parts[0];
            $rest = array_slice($parts, 1);

            if (!isset($nodes[$head])) {
                $nodes[$head] = ['name' => $head, 'order' => (int) $row['sort_order'], 'leaves' => [], 'childRows' => []];
            } else {
                $nodes[$head]['order'] = min($nodes[$head]['order'], (int) $row['sort_order']);
            }

            if (empty($rest)) {
                $nodes[$head]['leaves'][] = $row;
            } else {
                $row['category'] = implode('/', $rest);
                $nodes[$head]['childRows'][] = $row;
            }
        }

        uasort($nodes, static function (array $a, array $b): int {
            return $a['order'] <=> $b['order'] ?: strcasecmp($a['name'], $b['name']);
        });

        $out = [];
        foreach ($nodes as $node) {
            $out[] = [
                'category' => $node['name'],
                'items'    => $node['leaves'],
                'children' => $node['childRows'] !== [] ? $this->buildFolderTree($node['childRows']) : [],
            ];
        }
        if ($uncategorised !== []) {
            $out[] = ['category' => null, 'items' => $uncategorised, 'children' => []];
        }
        return $out;
    }

    /** Existing category names, for the manage screen's autocomplete -- keeps spelling consistent instead of splitting one folder into near-duplicates. */
    public function categories(): array
    {
        return $this->db->query(
            "SELECT DISTINCT category FROM site_downloads WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @throws \InvalidArgumentException on bad input */
    public function create(array $in, int $userId): int
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('A title is required.');
        }
        $url = self::validLink((string) ($in['drive_url'] ?? ''));
        $category = trim((string) ($in['category'] ?? ''));

        $this->db->prepare(
            'INSERT INTO site_downloads (title, category, drive_url, is_active, sort_order, created_by, created_at, updated_at)
             VALUES (:title, :cat, :url, :active, :sort, :by, NOW(), NOW())'
        )->execute([
            ':title'  => mb_substr($title, 0, 190),
            ':cat'    => $category !== '' ? mb_substr($category, 0, 100) : null,
            ':url'    => $url,
            ':active' => !empty($in['is_active']) ? 1 : 0,
            ':sort'   => (int) ($in['sort_order'] ?? 0),
            ':by'     => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @throws \InvalidArgumentException on bad input or an unknown id */
    public function update(int $id, array $in): void
    {
        if ($this->find($id) === null) {
            throw new \InvalidArgumentException('That item no longer exists.');
        }
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('A title is required.');
        }
        $url = self::validLink((string) ($in['drive_url'] ?? ''));
        $category = trim((string) ($in['category'] ?? ''));

        $this->db->prepare(
            'UPDATE site_downloads SET title = :title, category = :cat, drive_url = :url, is_active = :active,
                    sort_order = :sort, updated_at = NOW()
              WHERE id = :id'
        )->execute([
            ':title'  => mb_substr($title, 0, 190),
            ':cat'    => $category !== '' ? mb_substr($category, 0, 100) : null,
            ':url'    => $url,
            ':active' => !empty($in['is_active']) ? 1 : 0,
            ':sort'   => (int) ($in['sort_order'] ?? 0),
            ':id'     => $id,
        ]);
    }

    public function toggle(int $id): void
    {
        $this->db->prepare('UPDATE site_downloads SET is_active = 1 - is_active, updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM site_downloads WHERE id = :id')->execute([':id' => $id]);
    }

    /** Only a full http(s) link -- a Google Drive share link, typically. */
    private static function validLink(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('The link must be a full http:// or https:// address (e.g. a Google Drive share link).');
        }
        return mb_substr($url, 0, 500);
    }
}
