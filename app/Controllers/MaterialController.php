<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ContentAccessService;
use ClassMaterial;

/**
 * The teacher's shelf: Zoom recordings, reading material and videos,
 * posted per class and per month.
 *
 * Two things are enforced here rather than left to the template:
 *
 *   Ownership. A teacher may only post into a class that is theirs.
 *   Admins and above may post anywhere. The check runs on every write,
 *   because a class_id in a form field is a suggestion, not a fact.
 *
 *   Shape. A material is either an uploaded file or a link, never both
 *   and never neither, so the student view never has to guess which
 *   one to render.
 */
class MaterialController extends BaseController
{
    private ClassMaterial $materials;

    /** Uploads the teacher is allowed to post, keyed by magic-byte signature. */
    private const ALLOWED_UPLOADS = [
        'pdf'  => ['application/pdf', '%PDF-'],
        'png'  => ['image/png', "\x89PNG\r\n\x1a\n"],
        'jpg'  => ['image/jpeg', "\xFF\xD8\xFF"],
    ];

    private const MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB

    public function __construct()
    {
        parent::__construct();
        $this->materials = new ClassMaterial();
    }

    /**
     * Everything posted to one class, newest month first.
     */
    public function index(string $classId): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $month = ContentAccessService::monthFromRequest($_GET['month'] ?? null);

        $this->view('materials/index', [
            'pageTitle' => 'Class material',
            'class'     => $class,
            'month'     => $month,
            'months'    => $this->monthOptions($classId),
            'materials' => $this->materials->forTeacher($classId, $month),
            'counts'    => $this->materials->countsByType($classId),
            'types'     => ClassMaterial::TYPE_LABELS,
        ]);
    }

    /**
     * The posting form.
     */
    public function create(string $classId): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        // The kind is chosen on the previous screen, so the form can be
        // about one job instead of four. An unknown or absent value
        // leaves the old behaviour -- the full form with a Type picker --
        // so an old bookmark still works.
        $kind = (string) ($_GET['type'] ?? '');
        if (!array_key_exists($kind, ClassMaterial::TYPE_LABELS)) {
            $kind = '';
        }

        $this->view('materials/create', [
            'pageTitle' => 'Post material',
            'class'     => $class,
            'month'     => ContentAccessService::monthFromRequest($_GET['month'] ?? null),
            'types'     => ClassMaterial::TYPE_LABELS,
            'kind'      => $kind,
        ]);
    }

    /**
     * Save a posted item.
     */
    public function store(string $classId): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $data = [
            'title'         => trim($_POST['title'] ?? ''),
            'material_type' => $_POST['material_type'] ?? '',
            'description'   => trim($_POST['description'] ?? ''),
            'external_url'  => trim($_POST['external_url'] ?? ''),
            'lesson_date'   => $_POST['lesson_date'] ?? '',
            'month_year'    => $_POST['month_year'] ?? '',
        ];

        $errors = $this->validate($data, [
            'title'         => 'required|max:255',
            'material_type' => 'required|in:recording,reading,video,link',
        ]);

        if ($errors !== []) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect("/classes/{$classId}/materials/create");
        }

        $hasUpload = isset($_FILES['material_file'])
            && ($_FILES['material_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $hasUrl = $data['external_url'] !== '';

        // Exactly one source. Both would leave the student view with two
        // competing things to open; neither leaves it with nothing.
        if ($hasUpload && $hasUrl) {
            $this->flash('error', 'Give either a file or a link, not both.');
            $this->redirect("/classes/{$classId}/materials/create");
        }

        if (!$hasUpload && !$hasUrl) {
            $this->flash('error', 'Attach a file or paste a link.');
            $this->redirect("/classes/{$classId}/materials/create");
        }

        if ($hasUrl && !filter_var($data['external_url'], FILTER_VALIDATE_URL)) {
            $this->flash('error', 'That link does not look like a web address.');
            $this->redirect("/classes/{$classId}/materials/create");
        }

        $filePath = null;
        $originalName = null;
        $fileSize = null;

        if ($hasUpload) {
            try {
                [$filePath, $originalName, $fileSize] = $this->storeUpload($_FILES['material_file'], $classId);
            } catch (\RuntimeException $e) {
                $this->flash('error', $e->getMessage());
                $this->redirect("/classes/{$classId}/materials/create");
            }
        }

        $month = $data['month_year'] !== ''
            ? ContentAccessService::normaliseMonth($data['month_year'])
            : ContentAccessService::normaliseMonth();

        $materialId = $this->materials->create([
            'class_id'        => $classId,
            'title'           => $data['title'],
            'material_type'   => $data['material_type'],
            'description'     => $data['description'] !== '' ? $data['description'] : null,
            'file_path'       => $filePath,
            'original_name'   => $originalName,
            'file_size_bytes' => $fileSize,
            'external_url'    => $hasUrl ? $data['external_url'] : null,
            'lesson_date'     => $data['lesson_date'] !== '' ? $data['lesson_date'] : null,
            'duration_minutes' => ($_POST['duration_minutes'] ?? '') !== ''
                ? (int) $_POST['duration_minutes'] : null,
            'month_year'      => $month,
            'sort_order'      => (int) ($_POST['sort_order'] ?? 0),
            'status'          => ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'uploaded_by'     => (int) session_get('user_id'),
        ]);

        (new \App\Services\TopicService($this->db))->tag('class_materials', (int) $materialId, (int) $classId, $_POST['topic_id'] ?? null);

        // Tell the class it is there. Uploading something nobody hears
        // about is the failure this whole module exists to prevent.
        // Drafts announce nothing -- AnnouncementService checks the
        // status itself, so a draft saved on Friday wakes no one.
        $announced = (new \App\Services\AnnouncementService())->materialPublished($materialId);

        $this->flash(
            'success',
            'Posted to ' . $this->e($class['class_name']) . '.'
            . ($announced['sent'] > 0
                ? " {$announced['sent']} students and guardians have been notified."
                : '')
        );
        $this->redirect("/classes/{$classId}/materials?month=" . urlencode($month));
    }

    /**
     * Take something down without destroying it.
     *
     * Archiving rather than deleting: a student who watched a recording
     * last month should keep the view that counts toward their
     * engagement, and the teacher may want it back next term.
     */
    public function archive(string $classId, string $id): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $material = $this->materials->find((int) $id);

        if ($material === null || (int) $material['class_id'] !== $classId) {
            $this->flash('error', 'That item is not in this class.');
            $this->redirect("/classes/{$classId}/materials");
        }

        $this->materials->update((int) $id, ['status' => 'archived']);
        $this->flash('success', 'Taken down. Students can no longer see it.');
        $this->redirect("/classes/{$classId}/materials");
    }

    /**
     * Serve an uploaded file.
     *
     * Uploads live under storage/, outside the web root, so the only way
     * to one is through here — which means every download passes the same
     * two checks: the viewer is entitled to this class's material, and
     * the path resolves to somewhere inside the upload folder.
     *
     * Everything is sent as an attachment with sniffing turned off. A PDF
     * that a browser renders inline is a page the uploader controls, and
     * this is content posted by a teacher for children to open.
     */
    public function download(string $id): void
    {
        AuthMiddleware::requireAuth();

        $material = $this->materials->find((int) $id);

        if ($material === null || empty($material['file_path'])) {
            http_response_code(404);
            $this->flash('error', 'That file is not available.');
            $this->redirect('/dashboard');
        }

        $this->requireViewerMayOpen($material);

        $base = realpath(BASE_PATH . '/storage/uploads/materials');
        $full = realpath($base . '/' . $material['file_path']);

        // realpath() collapses any '..' the stored path might contain, so
        // a value that escapes the upload folder fails this comparison
        // rather than reaching the filesystem.
        if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Not found.');
        }

        $extension = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = self::ALLOWED_UPLOADS[$extension][0] ?? 'application/octet-stream';
        $name = $material['original_name'] ?: ('material-' . (int) $id . '.' . $extension);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: private, max-age=0, must-revalidate');

        readfile($full);
        exit;
    }

    /**
     * May the person asking actually open this?
     *
     * Staff see everything. A student sees it only when they are enrolled
     * and that month's fee is settled — the same gate as the listing, so
     * a direct link cannot walk around the lock.
     */
    private function requireViewerMayOpen(array $material): void
    {
        $role = (string) session_get('user_role');

        if (session_get('user_type') === 'user'
            && in_array($role, ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'], true)) {
            // Staff open material of the classes in their branch, and a
            // teacher their own classes' -- not every file by id.
            if (\App\Services\BranchScope::current()->allowsClass((int) $material['class_id'])) {
                return;
            }
            http_response_code(403);
            exit('Not allowed.');
        }

        if (session_get('user_type') !== 'student') {
            http_response_code(403);
            exit('Not allowed.');
        }

        if ($material['status'] !== 'published') {
            http_response_code(403);
            exit('Not available.');
        }

        $access = new ContentAccessService();

        if (!$access->hasPaidFor(
            (int) session_get('user_id'),
            (int) $material['class_id'],
            $material['month_year']
        )) {
            $this->flash('error', 'Settle this month\'s fee to open the class material.');
            $this->redirect('/my/classes');
        }
    }

    // ---------- helpers ----------

    /**
     * Validate and move an upload.
     *
     * The declared MIME type and the filename are both attacker-supplied,
     * so neither decides anything: the first bytes of the file do. The
     * stored name is generated, so a file called "../../index.php" lands
     * where it is told to and nowhere else.
     *
     * @return array{0: string, 1: string, 2: int}
     * @throws \RuntimeException
     */
    private function storeUpload(array $file, int $classId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('That file did not upload cleanly. Try again.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('That upload could not be verified.');
        }

        $size = (int) $file['size'];
        if ($size <= 0) {
            throw new \RuntimeException('That file is empty.');
        }

        if ($size > self::MAX_UPLOAD_BYTES) {
            $mb = (int) (self::MAX_UPLOAD_BYTES / 1024 / 1024);
            throw new \RuntimeException("Files are capped at {$mb} MB. Put larger videos on a link instead.");
        }

        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 16);
        $extension = null;

        foreach (self::ALLOWED_UPLOADS as $ext => [$mime, $signature]) {
            if (str_starts_with($head, $signature)) {
                $extension = $ext;
                break;
            }
        }

        if ($extension === null) {
            throw new \RuntimeException(
                'Only PDF, PNG and JPG files can be uploaded. Renaming a file will not change what it is.'
            );
        }

        $dir = BASE_PATH . '/storage/uploads/materials/' . $classId;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('The upload folder could not be created.');
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
            throw new \RuntimeException('That file could not be saved.');
        }

        // The uploader's own name is kept for display only. It is escaped
        // on output and never used to build a path or a header.
        $originalName = $this->safeLabel((string) ($file['name'] ?? 'material'));

        return [$classId . '/' . $stored, $originalName, $size];
    }

    /**
     * Reduce an uploaded filename to something safe to show.
     */
    private function safeLabel(string $name): string
    {
        $name = str_replace(['/', '\\', "\0"], '', $name);
        $name = preg_replace('/\.{2,}/', '.', $name) ?? $name;
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $name = trim($name);

        return $name === '' ? 'material' : mb_substr($name, 0, 255);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireClass(int $classId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, i.name AS institute_name
               FROM classes c
               LEFT JOIN institutes i ON i.id = c.institute_id
              WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $classId]);
        $class = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($class === false) {
            $this->flash('error', 'That class does not exist.');
            $this->redirect('/classes');
        }

        return $class;
    }

    /**
     * A teacher owns their own classes; coordinators and above see all.
     */
    private function requireClassAccess(array $class): void
    {
        // One rule for every role, and BranchScope already holds it:
        // global roles see every class, office roles see their own
        // branch's, and a teacher sees the classes they are attached to
        // -- whether through classes.teacher_id or through user_classes.
        //
        // The old check here read classes.teacher_id alone, so a teacher
        // assigned to a class through user_classes was shown the class in
        // their list and then bounced off its material, recordings and
        // quizzes with "that is not one of your classes". Staff, whom the
        // route lets in, were refused the same way.
        if (\App\Services\BranchScope::current()->allowsClass((int) ($class['id'] ?? 0))) {
            return;
        }

        $this->flash('error', 'That is not one of your classes.');
        $this->redirect('/classes');
    }

    /**
     * The last six months, for the month switcher.
     *
     * @return array<string, string>
     */
    private function monthOptions(int $classId): array
    {
        $options = [];

        for ($i = 0; $i < 6; $i++) {
            $month = date('Y-m-01', strtotime("-{$i} month"));
            $options[$month] = date('F Y', strtotime($month));
        }

        // Anything already posted outside that window still gets an entry,
        // so older material stays reachable rather than being orphaned.
        foreach ($this->materials->publishedMonths($classId) as $month) {
            $options[$month] ??= date('F Y', strtotime($month));
        }

        krsort($options);

        return $options;
    }
}
