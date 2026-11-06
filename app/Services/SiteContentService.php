<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * The small, fixed set of editable text blocks on the public website
 * (see migration 019). Deliberately not a general page builder: the
 * key list below is the only thing that can ever be written here, each
 * key has a default equal to the text that was hard-coded on the page
 * before this existed (so an untouched install looks identical to
 * today), and nothing else on the public site -- branch names, class
 * counts, the teacher's name -- is ever sourced from here. Those still
 * come only from real records, via PublicSite.
 */
final class SiteContentService
{
    /** @var array<string, array{label: string, default: string}> */
    public const FIELDS = [
        'home_hero_text' => [
            'label'   => 'Home page: hero paragraph (under the heading)',
            'default' => 'Weekly Science classes for Grades {r}, following the school syllabus. Every lesson is backed by recordings, notes, quizzes and homework in the portal, and parents can follow their child\'s progress.',
        ],
        'about_in_class_text' => [
            'label'   => 'About page: "In class" paragraph',
            'default' => 'Each grade has its own weekly class at the branch. Students who miss a lesson can catch up from the recording and notes, and the class register lets parents know about an absence.',
        ],
        'about_extra_text' => [
            'label'   => 'About page: extra paragraph (optional -- leave blank to hide)',
            'default' => '',
        ],
        'contact_intro_text' => [
            'label'   => 'Contact page: intro line (under the heading)',
            'default' => 'Call the branch nearest to you, or send a message and the office will reply.',
        ],
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * The stored value for $key, or its default when unset, empty, or
     * on any error (fail open to the original hard-coded text, never to
     * a blank page). $params are substituted the same way as te()'s
     * placeholders -- {r}, {n}, etc. -- but without a translation
     * lookup, since this is the site owner's own text, not an i18n key.
     */
    public function get(string $key, array $params = []): string
    {
        $value = self::FIELDS[$key]['default'] ?? '';
        try {
            $stmt = $this->db->prepare('SELECT content_value FROM site_content WHERE content_key = :k');
            $stmt->execute([':k' => $key]);
            $stored = $stmt->fetchColumn();
            if ($stored !== false && trim((string) $stored) !== '') {
                $value = (string) $stored;
            }
        } catch (\Throwable $e) {
            error_log('Site content unavailable: ' . $e->getMessage());
        }

        foreach ($params as $ph => $v) {
            $value = str_replace('{' . $ph . '}', (string) $v, $value);
        }

        return $value;
    }

    /** @return array<string, string> every known key => its current (or default) value */
    public function getAll(): array
    {
        $out = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $out[$key] = $this->get($key);
        }
        return $out;
    }

    /** @throws \InvalidArgumentException if $key is not one of the fixed, known fields */
    public function set(string $key, string $value, int $updatedBy): void
    {
        if (!array_key_exists($key, self::FIELDS)) {
            throw new \InvalidArgumentException("Unknown site content key: {$key}");
        }
        $this->db->prepare(
            'INSERT INTO site_content (content_key, content_value, updated_by, updated_at)
             VALUES (:k, :v, :by, NOW())
             ON DUPLICATE KEY UPDATE content_value = :v2, updated_by = :by2, updated_at = NOW()'
        )->execute([
            ':k' => $key, ':v' => $value, ':by' => $updatedBy,
            ':v2' => $value, ':by2' => $updatedBy,
        ]);
    }
}
