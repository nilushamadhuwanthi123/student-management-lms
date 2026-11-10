<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * The small, fixed set of editable content blocks on the public website
 * (see migration 019). Deliberately not a general page builder: the
 * key list below is the only thing that can ever be written here, each
 * text/richtext key has a default equal to the text that was hard-coded
 * on the page before this existed (so an untouched install looks
 * identical to today), and nothing else on the public site -- branch
 * names, class counts, the teacher's name -- is ever sourced from here.
 * Those still come only from real records, via PublicSite.
 *
 * Three field types:
 *  - 'text'     a single plain line, stored and shown escaped.
 *  - 'richtext' a short formatted passage (bold/italic/lists/links only),
 *               sanitised to that whitelist on save and safe to echo
 *               unescaped wherever it is shown.
 *  - 'image'    an uploaded photo that replaces a fixed site image;
 *               the stored value is a public-relative path such as
 *               '/uploads/site-content/teacher_photo_image_xxx.jpg',
 *               or '' to keep using the original fixed photo.
 */
final class SiteContentService
{
    public const TYPE_TEXT = 'text';
    public const TYPE_RICHTEXT = 'richtext';
    public const TYPE_IMAGE = 'image';

    /** @var array<string, array{label: string, default: string, type: string}> */
    public const FIELDS = [
        'home_hero_text' => [
            'label'   => 'Home page: hero paragraph (under the heading)',
            'default' => 'Weekly Science classes for Grades {r}, following the school syllabus. Every lesson is backed by recordings, notes, quizzes and homework in the portal, and parents can follow their child\'s progress.',
            'type'    => self::TYPE_RICHTEXT,
        ],
        'about_in_class_text' => [
            'label'   => 'About page: "In class" paragraph',
            'default' => 'Each grade has its own weekly class at the branch. Students who miss a lesson can catch up from the recording and notes, and the class register lets parents know about an absence.',
            'type'    => self::TYPE_RICHTEXT,
        ],
        'about_extra_text' => [
            'label'   => 'About page: extra paragraph (optional -- leave blank to hide)',
            'default' => '',
            'type'    => self::TYPE_RICHTEXT,
        ],
        'contact_intro_text' => [
            'label'   => 'Contact page: intro line (under the heading)',
            'default' => 'Call the branch nearest to you, or send a message and the office will reply.',
            'type'    => self::TYPE_TEXT,
        ],
        'teacher_photo_image' => [
            'label'   => 'Teacher photo (home page hero and "Your teacher" section on the About page)',
            'default' => '',
            'type'    => self::TYPE_IMAGE,
        ],
        'about_class_image' => [
            'label'   => 'About page: "In class" section photo',
            'default' => '',
            'type'    => self::TYPE_IMAGE,
        ],
    ];

    /** Allowed HTML for richtext fields: inline formatting, lists and links -- nothing that can script or embed. */
    private const RICHTEXT_ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a>';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * The stored value for $key, or its default when unset, empty, or
     * on any error (fail open to the original hard-coded text/image,
     * never to a blank page). $params are substituted the same way as
     * te()'s placeholders -- {r}, {n}, etc. -- but without a translation
     * lookup, since this is the site owner's own text, not an i18n key.
     * Not applied to 'image' fields (a path has no placeholders).
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

        if ((self::FIELDS[$key]['type'] ?? self::TYPE_TEXT) !== self::TYPE_IMAGE) {
            foreach ($params as $ph => $v) {
                $value = str_replace('{' . $ph . '}', (string) $v, $value);
            }
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
        if ((self::FIELDS[$key]['type'] ?? self::TYPE_TEXT) === self::TYPE_RICHTEXT) {
            $value = self::sanitizeRichText($value);
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

    /**
     * Save an uploaded photo for an 'image' field, replacing any previous
     * upload for that key. $file is one entry of $_FILES (already checked
     * by the caller to be present with error === UPLOAD_ERR_OK).
     *
     * @param array{tmp_name: string, name: string, type: string, size: int} $file
     * @throws \InvalidArgumentException on an unknown key, wrong field type, bad file type/size, or a failed move
     */
    public function setImage(string $key, array $file, int $updatedBy): void
    {
        if (!array_key_exists($key, self::FIELDS) || self::FIELDS[$key]['type'] !== self::TYPE_IMAGE) {
            throw new \InvalidArgumentException("Unknown site content image key: {$key}");
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $file['type'] ?? '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected !== false) {
                $mime = $detected;
            }
        }
        if (!isset($allowed[$mime])) {
            throw new \InvalidArgumentException('Photo must be a JPG, PNG or WEBP image.');
        }
        if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
            throw new \InvalidArgumentException('Photo must be 3MB or smaller.');
        }

        $targetDir = BASE_PATH . '/public/uploads/site-content';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = preg_replace('/[^a-z0-9_]/', '', strtolower($key)) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \InvalidArgumentException('Could not save the uploaded photo.');
        }

        $oldPath = $this->get($key);
        $this->set($key, '/uploads/site-content/' . $filename, $updatedBy);

        if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/site-content/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /** Clear an 'image' field back to the original fixed photo. */
    public function clearImage(string $key, int $updatedBy): void
    {
        if (!array_key_exists($key, self::FIELDS) || self::FIELDS[$key]['type'] !== self::TYPE_IMAGE) {
            throw new \InvalidArgumentException("Unknown site content image key: {$key}");
        }
        $oldPath = $this->get($key);
        $this->set($key, '', $updatedBy);
        if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/site-content/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /**
     * Reduce $html to the richtext whitelist (paragraphs, line breaks, bold,
     * italic, underline, lists and links with an http(s)/mailto href only),
     * dropping every other tag, and every attribute except href/target/rel
     * on <a>. Safe to store and to echo unescaped afterwards.
     */
    public static function sanitizeRichText(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $clean = strip_tags($html, self::RICHTEXT_ALLOWED_TAGS);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $clean . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }

        self::stripDisallowedAttributes($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        // Collapse an editor's empty paragraphs/line breaks left over from backspacing.
        $out = preg_replace('/(<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>)+$/i', '', $out) ?? $out;

        return mb_substr(trim($out), 0, 4000);
    }

    private static function stripDisallowedAttributes(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);
            $toRemove = [];
            foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                $name = strtolower($attr->name);
                $keep = false;
                if ($tag === 'a' && $name === 'href') {
                    $keep = (bool) preg_match('#^(https?://|mailto:|/)#i', trim($attr->value));
                } elseif ($tag === 'a' && in_array($name, ['target', 'rel'], true)) {
                    $keep = true;
                }
                if (!$keep) {
                    $toRemove[] = $attr->name;
                }
            }
            foreach ($toRemove as $name) {
                $node->removeAttribute($name);
            }
            if ($tag === 'a' && $node->hasAttribute('href')) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            self::stripDisallowedAttributes($child);
        }
    }
}
