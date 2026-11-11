<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\SeminarService;
use PDO;

/**
 * Seminar / event management -- same posting-rights shape as the
 * notice board, with one addition: admin/coordinator/super_admin (not
 * teacher) may also target a whole grade, optionally across every
 * branch at once.
 *
 *   super admin        every branch, one branch, one grade (any/own branch), or one class
 *   admin/coordinator   their own branch, their own branch's grade, or a class in it
 *   teacher             the classes they teach
 *   staff                read only
 */
class SeminarController extends BaseController
{
    private const POSTERS = ['super_admin', 'admin', 'coordinator', 'teacher'];

    public function index(): void
    {
        $this->requireStaffUser();
        $scope = BranchScope::current();

        $sql = "SELECT s.*, c.class_name, i.name AS institute_name, u.full_name AS author,
                       (SELECT COUNT(*) FROM seminar_registrations r WHERE r.seminar_id = s.id AND r.status = 'registered') AS registered_count
                  FROM seminars s
                  LEFT JOIN classes c ON c.id = s.class_id
                  LEFT JOIN institutes i ON i.id = s.institute_id
                  LEFT JOIN users u ON u.id = s.created_by
                 WHERE s.status <> 'archived'";
        if (!$scope->isGlobal()) {
            $sql .= " AND (s.target_scope = 'all' AND s.for_staff = 1
                       OR (s.target_scope = 'branch'" . $scope->instituteSql('s.institute_id') . ")
                       OR (s.target_scope = 'grade'" . $scope->instituteSql('s.institute_id') . ")
                       OR (s.target_scope = 'class'" . $scope->classSql('s.class_id') . "))";
        }
        $rows = $this->db->query($sql . " ORDER BY s.event_date DESC, s.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

        $seminarService = new SeminarService($this->db);
        foreach ($rows as &$r) {
            if ((int) $r['registration_required'] === 1) {
                $r['waitlisted_count'] = $seminarService->waitlistCount((int) $r['id']);
                $r['attended_count'] = $seminarService->attendedCount((int) $r['id']);
            }
            $r['can_manage'] = $this->mayPost(
                $r['target_scope'],
                $r['institute_id'] !== null ? (int) $r['institute_id'] : null,
                $r['grade'],
                $r['class_id'] !== null ? (int) $r['class_id'] : null
            );
        }

        $this->view('seminars/index', [
            'pageTitle'  => 'Seminars & Events',
            'activePage' => 'seminars',
            'seminars'   => $rows,
            'canPost'    => in_array((string) session_get('user_role'), self::POSTERS, true),
        ]);
    }

    public function create(): void
    {
        $this->requirePoster();
        $scope = BranchScope::current();

        $this->view('seminars/create', [
            'pageTitle'  => 'New seminar',
            'activePage' => 'seminars',
            'institutes' => $scope->isTeacher() ? [] : $this->db->query(
                "SELECT id, name FROM institutes WHERE status = 'active'" . $scope->instituteSql('id') . " ORDER BY name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'classes'    => $this->db->query(
                "SELECT id, class_name FROM classes WHERE status = 'active'" . $scope->classSql('id') . " ORDER BY grade, class_name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'grades'     => $this->db->query("SELECT DISTINCT grade FROM classes WHERE grade IS NOT NULL ORDER BY grade")->fetchAll(PDO::FETCH_COLUMN),
            'isGlobal'   => $scope->isGlobal(),
            'canTargetGrade' => !$scope->isTeacher(),
        ]);
    }

    public function store(): void
    {
        $this->requirePoster();
        verifyCsrfToken();

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $eventTs = $eventDate !== '' ? strtotime($eventDate) : false;
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $venue = trim((string) ($_POST['venue'] ?? ''));
        $onlineUrl = trim((string) ($_POST['online_url'] ?? ''));
        $coverImageUrl = trim((string) ($_POST['cover_image_url'] ?? ''));

        $scope = in_array($_POST['target_scope'] ?? '', ['all', 'branch', 'grade', 'class'], true) ? $_POST['target_scope'] : 'class';
        $instituteId = in_array($scope, ['branch', 'grade'], true) && (int) ($_POST['institute_id'] ?? 0) > 0
            ? (int) $_POST['institute_id'] : null;
        $grade = $scope === 'grade' ? trim((string) ($_POST['grade'] ?? '')) : null;
        $classId = $scope === 'class' ? (int) ($_POST['class_id'] ?? 0) : null;

        $registrationRequired = isset($_POST['registration_required']) ? 1 : 0;
        $maxCapacityRaw = trim((string) ($_POST['max_capacity'] ?? ''));
        $maxCapacity = $maxCapacityRaw !== '' && ctype_digit($maxCapacityRaw) && (int) $maxCapacityRaw > 0 ? (int) $maxCapacityRaw : null;
        $publish = ($_POST['action'] ?? '') === 'publish';

        $students = isset($_POST['for_students']) ? 1 : 0;
        $parents = isset($_POST['for_parents']) ? 1 : 0;
        $staff = isset($_POST['for_staff']) ? 1 : 0;

        $showOnWebsite = isset($_POST['show_on_website']) ? 1 : 0;

        if ($title === '' || $eventTs === false || ($students + $parents + $staff) === 0
            || ($scope === 'grade' && $grade === '')
            || ($onlineUrl !== '' && filter_var($onlineUrl, FILTER_VALIDATE_URL) === false)
            || !$this->validCover($coverImageUrl, null)) {
            $this->flash('error', t('Write a title, pick a date, choose who should see it, and check any links.'));
            $this->redirect('/seminars/create');
        }
        if (!$this->mayPost($scope, $instituteId, $grade, $classId)) {
            http_response_code(403);
            $this->flash('error', t('You can only post seminars for your own branch, grade or classes.'));
            $this->redirect('/seminars/create');
        }
        $uploaded = $this->coverUpload();
        if ($uploaded === false) {
            $this->redirect('/seminars/create');
        }
        if ($uploaded !== null) {
            $coverImageUrl = $uploaded;
        }

        $this->db->prepare(
            "INSERT INTO seminars (title, description, cover_image_url, event_date, start_time, end_time, venue, online_url,
                                    target_scope, institute_id, grade, class_id, for_students, for_parents, for_staff,
                                    registration_required, show_on_website, max_capacity, status, created_by)
             VALUES (:t, :d, :img, :ed, :st, :et, :v, :ou, :ts, :i, :g, :c, :fs, :fp, :ff, :rr, :web, :cap, :status, :by)"
        )->execute([
            ':t' => mb_substr($title, 0, 255), ':d' => $description !== '' ? $description : null,
            ':img' => $coverImageUrl !== '' ? $coverImageUrl : null,
            ':ed' => date('Y-m-d', $eventTs), ':st' => $startTime !== '' ? $startTime : null, ':et' => $endTime !== '' ? $endTime : null,
            ':v' => $venue !== '' ? $venue : null, ':ou' => $onlineUrl !== '' ? $onlineUrl : null,
            ':ts' => $scope, ':i' => $instituteId, ':g' => $grade, ':c' => $classId,
            ':fs' => $students, ':fp' => $parents, ':ff' => $staff,
            ':rr' => $registrationRequired, ':web' => $showOnWebsite, ':cap' => $maxCapacity,
            ':status' => $publish ? 'published' : 'draft',
            ':by' => (int) session_get('user_id'),
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->audit('create_seminar', $id, ['target_scope' => $scope, 'published' => $publish]);

        $sent = $publish ? (new SeminarService($this->db))->announce($id) : 0;
        $this->flash('success', $publish ? t('Published. {n} people notified.', ['n' => $sent]) : t('Saved as a draft.'));
        $this->redirect('/seminars');
    }

    public function show(string $id): void
    {
        $this->requireStaffUser();
        $seminar = $this->findSeminar((int) $id);
        // Same rule as the list: a branch's staff never see another
        // branch's seminar, even by typing its id into the address bar.
        if ($seminar === null || !$this->staffMaySee($seminar)) {
            $this->flash('error', t('That seminar is not available.'));
            $this->redirect('/seminars');
        }

        $seminarService = new SeminarService($this->db);
        $this->view('seminars/show', [
            'pageTitle'  => $seminar['title'],
            'activePage' => 'seminars',
            'seminar'    => $seminar,
            'audience'   => $seminarService->audienceCounts($seminar),
            'registeredCount' => $seminarService->registrationCount((int) $seminar['id']),
            'waitlistedCount' => $seminarService->waitlistCount((int) $seminar['id']),
            'attendedCount'   => $seminarService->attendedCount((int) $seminar['id']),
            'canManage'  => $this->mayPost(
                $seminar['target_scope'],
                $seminar['institute_id'] !== null ? (int) $seminar['institute_id'] : null,
                $seminar['grade'],
                $seminar['class_id'] !== null ? (int) $seminar['class_id'] : null
            ),
        ]);
    }

    /**
     * Edit an existing seminar. Who it targets (scope/branch/grade/class
     * and who sees it) can only change while it is still a draft -- once
     * published and people have been notified, changing the audience
     * silently would be misleading, so only the practical details
     * (time, venue, link, capacity, description, cover image) can be
     * corrected afterwards.
     */
    public function edit(string $id): void
    {
        $seminar = $this->managedSeminar((int) $id);
        $scope = BranchScope::current();

        $this->view('seminars/create', [
            'pageTitle'  => 'Edit seminar',
            'activePage' => 'seminars',
            'seminar'    => $seminar,
            'isEdit'     => true,
            'canEditAudience' => $seminar['status'] === 'draft',
            'institutes' => $scope->isTeacher() ? [] : $this->db->query(
                "SELECT id, name FROM institutes WHERE status = 'active'" . $scope->instituteSql('id') . " ORDER BY name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'classes'    => $this->db->query(
                "SELECT id, class_name FROM classes WHERE status = 'active'" . $scope->classSql('id') . " ORDER BY grade, class_name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'grades'     => $this->db->query("SELECT DISTINCT grade FROM classes WHERE grade IS NOT NULL ORDER BY grade")->fetchAll(PDO::FETCH_COLUMN),
            'isGlobal'   => $scope->isGlobal(),
            'canTargetGrade' => !$scope->isTeacher(),
        ]);
    }

    public function update(string $id): void
    {
        $seminar = $this->managedSeminar((int) $id);
        verifyCsrfToken();

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $eventTs = $eventDate !== '' ? strtotime($eventDate) : false;
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $venue = trim((string) ($_POST['venue'] ?? ''));
        $onlineUrl = trim((string) ($_POST['online_url'] ?? ''));
        $coverImageUrl = trim((string) ($_POST['cover_image_url'] ?? ''));
        $registrationRequired = isset($_POST['registration_required']) ? 1 : 0;
        $maxCapacityRaw = trim((string) ($_POST['max_capacity'] ?? ''));
        $maxCapacity = $maxCapacityRaw !== '' && ctype_digit($maxCapacityRaw) && (int) $maxCapacityRaw > 0 ? (int) $maxCapacityRaw : null;

        $showOnWebsite = isset($_POST['show_on_website']) ? 1 : 0;

        if ($title === '' || $eventTs === false
            || ($onlineUrl !== '' && filter_var($onlineUrl, FILTER_VALIDATE_URL) === false)
            || !$this->validCover($coverImageUrl, $seminar['cover_image_url'] ?? null)) {
            $this->flash('error', t('Write a title, pick a date, and check any links.'));
            $this->redirect('/seminars/' . (int) $seminar['id'] . '/edit');
        }
        $uploaded = $this->coverUpload();
        if ($uploaded === false) {
            $this->redirect('/seminars/' . (int) $seminar['id'] . '/edit');
        }
        if ($uploaded !== null) {
            $coverImageUrl = $uploaded;
        }

        $sets = [
            'title = :t', 'description = :d', 'cover_image_url = :img', 'event_date = :ed',
            'start_time = :st', 'end_time = :et', 'venue = :v', 'online_url = :ou',
            'registration_required = :rr', 'show_on_website = :web', 'max_capacity = :cap',
        ];
        $params = [
            ':t' => mb_substr($title, 0, 255), ':d' => $description !== '' ? $description : null,
            ':img' => $coverImageUrl !== '' ? $coverImageUrl : null,
            ':ed' => date('Y-m-d', $eventTs), ':st' => $startTime !== '' ? $startTime : null, ':et' => $endTime !== '' ? $endTime : null,
            ':v' => $venue !== '' ? $venue : null, ':ou' => $onlineUrl !== '' ? $onlineUrl : null,
            ':rr' => $registrationRequired, ':web' => $showOnWebsite, ':cap' => $maxCapacity,
            ':id' => (int) $seminar['id'],
        ];

        if ($seminar['status'] === 'draft') {
            $scope = in_array($_POST['target_scope'] ?? '', ['all', 'branch', 'grade', 'class'], true) ? $_POST['target_scope'] : 'class';
            $instituteId = in_array($scope, ['branch', 'grade'], true) && (int) ($_POST['institute_id'] ?? 0) > 0
                ? (int) $_POST['institute_id'] : null;
            $grade = $scope === 'grade' ? trim((string) ($_POST['grade'] ?? '')) : null;
            $classId = $scope === 'class' ? (int) ($_POST['class_id'] ?? 0) : null;
            $students = isset($_POST['for_students']) ? 1 : 0;
            $parents = isset($_POST['for_parents']) ? 1 : 0;
            $staff = isset($_POST['for_staff']) ? 1 : 0;

            if (($students + $parents + $staff) === 0 || ($scope === 'grade' && $grade === '') || !$this->mayPost($scope, $instituteId, $grade, $classId)) {
                $this->flash('error', t('Choose who should see it, within your own branch, grade or classes.'));
                $this->redirect('/seminars/' . (int) $seminar['id'] . '/edit');
            }

            $sets = array_merge($sets, ['target_scope = :ts', 'institute_id = :i', 'grade = :g', 'class_id = :c', 'for_students = :fs', 'for_parents = :fp', 'for_staff = :ff']);
            $params += [':ts' => $scope, ':i' => $instituteId, ':g' => $grade, ':c' => $classId, ':fs' => $students, ':fp' => $parents, ':ff' => $staff];
        }

        $this->db->prepare('UPDATE seminars SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        $this->audit('update_seminar', (int) $seminar['id']);
        $this->flash('success', t('Seminar updated.'));
        $this->redirect('/seminars/' . (int) $seminar['id']);
    }

    /** Organizer view: who registered/waitlisted, and attendance check-in. */
    public function registrants(string $id): void
    {
        $seminar = $this->managedSeminar((int) $id);
        $service = new SeminarService($this->db);

        $this->view('seminars/registrants', [
            'pageTitle'  => 'Registrants: ' . $seminar['title'],
            'activePage' => 'seminars',
            'seminar'    => $seminar,
            'registrants' => $service->registrants((int) $seminar['id']),
        ]);
    }

    public function markAttendance(string $id): void
    {
        $seminar = $this->managedSeminar((int) $id);
        verifyCsrfToken();
        $registrationId = (int) ($_POST['registration_id'] ?? 0);
        $attended = !empty($_POST['attended']);

        (new SeminarService($this->db))->setAttendance($registrationId, (int) $seminar['id'], $attended);
        $this->audit('seminar_attendance', (int) $seminar['id'], ['registration_id' => $registrationId, 'attended' => $attended]);
        $this->redirect('/seminars/' . (int) $seminar['id'] . '/registrants');
    }

    public function publish(string $id): void
    {
        $seminar = $this->managedSeminar((int) $id);
        verifyCsrfToken();
        $this->db->prepare("UPDATE seminars SET status = 'published' WHERE id = :id")->execute([':id' => (int) $seminar['id']]);
        $sent = (new SeminarService($this->db))->announce((int) $seminar['id']);
        $this->audit('publish_seminar', (int) $seminar['id']);
        $this->flash('success', t('Published. {n} people notified.', ['n' => $sent]));
        $this->redirect('/seminars');
    }

    public function cancel(string $id): void
    {
        $seminar = $this->managedSeminar((int) $id);
        verifyCsrfToken();
        $this->db->prepare("UPDATE seminars SET status = 'cancelled' WHERE id = :id")->execute([':id' => (int) $seminar['id']]);
        $this->audit('cancel_seminar', (int) $seminar['id']);
        $this->flash('success', t('Seminar cancelled.'));
        $this->redirect('/seminars');
    }

    /** A draft that was never published/notified can be removed outright; anything else is cancelled instead. */
    public function delete(string $id): void
    {
        $seminar = $this->managedSeminar((int) $id);
        verifyCsrfToken();
        if ($seminar['status'] !== 'draft') {
            $this->flash('error', t('Only a draft can be deleted; publish it then cancel it instead.'));
            $this->redirect('/seminars');
        }
        $this->db->prepare("DELETE FROM seminars WHERE id = :id AND status = 'draft'")->execute([':id' => (int) $seminar['id']]);
        $this->audit('delete_seminar', (int) $seminar['id']);
        $this->flash('success', t('Draft deleted.'));
        $this->redirect('/seminars');
    }

    // Students, parents and staff registering ---------------------------

    public function mine(): void
    {
        AuthMiddleware::requireAuth();
        $type = (string) session_get('user_type');
        if ($type === 'student') {
            $studentId = (int) session_get('user_id');
        } elseif ($type === 'parent') {
            $s = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
            $s->execute([':p' => (int) session_get('user_id')]);
            $studentId = (int) $s->fetchColumn();
        } else {
            $this->redirect('/seminars');
        }

        $service = new SeminarService($this->db);
        $rows = $service->forStudent($studentId, $type === 'parent');
        foreach ($rows as &$r) {
            $status = $service->registrationStatus((int) $r['id'], $type, (int) session_get('user_id'));
            $r['is_registered'] = $status !== null;
            $r['is_waitlisted'] = $status === 'waitlisted';
            $r['spots_left'] = $r['max_capacity'] !== null ? max(0, (int) $r['max_capacity'] - $service->registrationCount((int) $r['id'])) : null;
        }

        $this->view('seminars/mine', [
            'pageTitle'  => 'Seminars & Events',
            'activePage' => $type === 'parent' ? 'family-seminars' : 'my-seminars',
            'seminars'   => $rows,
            'isParent'   => $type === 'parent',
        ]);
    }

    public function register(string $id): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();
        $type = (string) session_get('user_type');
        $userId = (int) session_get('user_id');
        $back = $type === 'parent' ? '/family/seminars' : '/my/seminars';

        if (!in_array($type, ['student', 'parent'], true)) {
            $this->redirect($back);
        }

        $seminar = $this->findSeminar((int) $id);
        // Only a seminar this student (or this parent's child) is actually
        // invited to: the same list /my/seminars shows, never any id.
        $studentId = $type === 'student' ? $userId : 0;
        if ($type === 'parent') {
            $s = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
            $s->execute([':p' => $userId]);
            $studentId = (int) $s->fetchColumn();
        }
        $invited = $studentId > 0 && in_array(
            (int) $id,
            array_map(static fn (array $r): int => (int) $r['id'], (new SeminarService($this->db))->forStudent($studentId, $type === 'parent')),
            true
        );
        if ($seminar === null || !$invited || $seminar['status'] !== 'published' || (int) $seminar['registration_required'] !== 1) {
            $this->flash('error', t('Registration is not open for that seminar.'));
            $this->redirect($back);
        }

        $result = (new SeminarService($this->db))->register((int) $id, $type, $userId);
        $this->flash('success', $result === 'waitlisted'
            ? t('This seminar is full -- you have been added to the waiting list and will be registered automatically if a spot opens up.')
            : t('You are registered.'));
        $this->redirect($back);
    }

    public function unregister(string $id): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();
        $type = (string) session_get('user_type');
        $userId = (int) session_get('user_id');
        $back = $type === 'parent' ? '/family/seminars' : '/my/seminars';

        if (in_array($type, ['student', 'parent'], true)) {
            (new SeminarService($this->db))->unregister((int) $id, $type, $userId);
            $this->flash('success', t('Registration cancelled.'));
        }
        $this->redirect($back);
    }

    // Helpers -----------------------------------------------------------

    private function requireStaffUser(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'user') {
            $this->redirect('/dashboard');
        }
    }

    private function requirePoster(): void
    {
        $this->requireStaffUser();
        if (!in_array((string) session_get('user_role'), self::POSTERS, true)) {
            $this->flash('error', 'You do not have permission to access this page.');
            $this->redirect('/seminars');
        }
    }

    private function mayPost(string $scope, ?int $instituteId, ?string $grade, ?int $classId): bool
    {
        $branchScope = BranchScope::current();
        if (!in_array((string) session_get('user_role'), self::POSTERS, true)) {
            return false;
        }

        return match ($scope) {
            'all'    => $branchScope->isGlobal(),
            'branch' => !$branchScope->isTeacher() && $instituteId !== null && $branchScope->allowsInstitute($instituteId),
            'grade'  => !$branchScope->isTeacher() && $grade !== null && $grade !== ''
                            && ($instituteId === null ? $branchScope->isGlobal() : $branchScope->allowsInstitute($instituteId)),
            'class'  => $classId !== null && $branchScope->allowsClass($classId),
            default  => false,
        };
    }

    /**
     * A poster/cover image is either an http(s) web address, or the path of
     * a poster already uploaded for this seminar (kept as it was). Nothing
     * else -- in particular no javascript: or data: address -- because the
     * image is shown on the public home page.
     */
    private function validCover(string $value, ?string $existing): bool
    {
        if ($value === '') {
            return true;
        }
        if ($existing !== null && $value === $existing && str_starts_with($existing, '/uploads/seminars/')) {
            return true;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false && (bool) preg_match('#^https?://#i', $value);
    }

    /**
     * Save an uploaded poster, if one was chosen. Returns its public path,
     * null when no file was chosen, or false (with an error flashed) when
     * the file is not an acceptable image.
     */
    private function coverUpload(): string|false|null
    {
        $f = $_FILES['cover_image_file'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            $this->flash('error', t('The poster could not be uploaded. Please try again.'));
            return false;
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($fi, (string) $f['tmp_name']);
            finfo_close($fi);
        }
        if (!isset($allowed[$mime]) || @getimagesize((string) $f['tmp_name']) === false) {
            $this->flash('error', t('The poster must be a JPG, PNG or WEBP image.'));
            return false;
        }
        if ((int) $f['size'] > 3 * 1024 * 1024) {
            $this->flash('error', t('The poster must be 3MB or smaller.'));
            return false;
        }
        $dir = BASE_PATH . '/public/uploads/seminars';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'seminar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $name)) {
            $this->flash('error', t('The poster could not be uploaded. Please try again.'));
            return false;
        }
        return '/uploads/seminars/' . $name;
    }

    /**
     * Whether the signed-in staff member may see this seminar at all --
     * the exact rule index() applies in SQL, for a single row.
     *
     * @param array<string, mixed> $s
     */
    private function staffMaySee(array $s): bool
    {
        $scope = BranchScope::current();
        if ($scope->isGlobal()) {
            return true;
        }
        $inst = $s['institute_id'] !== null ? (int) $s['institute_id'] : null;
        return match ((string) $s['target_scope']) {
            'all'             => (int) $s['for_staff'] === 1,
            'branch', 'grade' => $scope->allowsInstitute($inst),
            'class'           => $scope->allowsClass($s['class_id'] !== null ? (int) $s['class_id'] : null),
            default           => false,
        };
    }

    /** @return array<string, mixed>|null */
    private function findSeminar(int $id): ?array
    {
        $s = $this->db->prepare("SELECT * FROM seminars WHERE id = :id AND status <> 'archived'");
        $s->execute([':id' => $id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed> */
    private function managedSeminar(int $id): array
    {
        $this->requirePoster();
        $n = $this->findSeminar($id);
        if ($n === null || !$this->mayPost(
            $n['target_scope'],
            $n['institute_id'] !== null ? (int) $n['institute_id'] : null,
            $n['grade'],
            $n['class_id'] !== null ? (int) $n['class_id'] : null
        )) {
            http_response_code(403);
            $this->flash('error', t('That seminar is not yours to change.'));
            $this->redirect('/seminars');
        }

        return $n;
    }

    private function audit(string $action, int $id, array $details = []): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'seminar', $id, $details);
        } catch (\Throwable $e) {
            error_log('Seminar audit failed: ' . $e->getMessage());
        }
    }
}
