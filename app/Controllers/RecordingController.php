<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AnnouncementService;
use App\Services\BranchScope;
use App\Services\ContentAccessService;
use App\Services\TopicService;
use PDO;

/**
 * Class Recordings: a lesson video or a link to one, posted per class.
 *
 * Kept deliberately separate from homework, assignments, quizzes and
 * general class material -- its own table, its own tab, its own "add
 * a recording" form -- because the client asked for recordings not to
 * be mixed in with any of those, even though the underlying rule for
 * who may see one is identical: the same class, the same month's fee.
 *
 * Targeting is implicit in which class the teacher is posting to.
 * Branch and grade are properties of that class (institute_id, grade),
 * so there is no separate "pick a branch, pick a grade" step -- the
 * single Science Teacher reaches a class the same way she already does
 * for material and homework, and the recording reaches exactly that
 * class's actively enrolled students, nobody else's.
 */
class RecordingController extends BaseController
{
    public function index(string $classId): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $stmt = $this->db->prepare(
            "SELECT r.*, t.title AS topic_title, t.title_si AS topic_title_si
               FROM recordings r
               LEFT JOIN topics t ON t.id = r.topic_id
              WHERE r.class_id = :c AND r.status <> 'archived'
              ORDER BY r.recording_date DESC, r.id DESC"
        );
        $stmt->execute([':c' => $classId]);

        $this->view('recordings/index', [
            'pageTitle'  => 'Class Recordings',
            'activePage' => 'teaching',
            'class'      => $class,
            'recordings' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function create(string $classId): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $this->view('recordings/create', [
            'pageTitle'  => 'Add a recording',
            'activePage' => 'teaching',
            'class'      => $class,
            'month'      => ContentAccessService::monthFromRequest($_GET['month'] ?? null),
        ]);
    }

    public function store(string $classId): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $data = [
            'title'          => trim($_POST['title'] ?? ''),
            'description'    => trim($_POST['description'] ?? ''),
            'video_url'      => trim($_POST['video_url'] ?? ''),
            'thumbnail_url'  => trim($_POST['thumbnail_url'] ?? ''),
            'recording_date' => $_POST['recording_date'] ?? '',
            'month_year'     => $_POST['month_year'] ?? '',
        ];

        $errors = $this->validate($data, [
            'title'     => 'required|max:255',
            'video_url' => 'required|max:1000',
        ]);
        if ($errors !== []) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect("/classes/{$classId}/recordings/create");
        }

        if (!filter_var($data['video_url'], FILTER_VALIDATE_URL)) {
            $this->flash('error', t('That recording link does not look like a web address.'));
            $this->redirect("/classes/{$classId}/recordings/create");
        }
        if ($data['thumbnail_url'] !== '' && !filter_var($data['thumbnail_url'], FILTER_VALIDATE_URL)) {
            $this->flash('error', t('That thumbnail link does not look like a web address.'));
            $this->redirect("/classes/{$classId}/recordings/create");
        }

        $month = $data['month_year'] !== ''
            ? ContentAccessService::normaliseMonth($data['month_year'])
            : ContentAccessService::normaliseMonth();

        $stmt = $this->db->prepare(
            "INSERT INTO recordings
                (class_id, title, description, video_url, thumbnail_url, recording_date, month_year, sort_order, status, created_by)
             VALUES
                (:class_id, :title, :description, :video_url, :thumbnail_url, :recording_date, :month_year, :sort_order, :status, :created_by)"
        );
        $stmt->execute([
            ':class_id'       => $classId,
            ':title'          => $data['title'],
            ':description'    => $data['description'] !== '' ? $data['description'] : null,
            ':video_url'      => $data['video_url'],
            ':thumbnail_url'  => $data['thumbnail_url'] !== '' ? $data['thumbnail_url'] : null,
            ':recording_date' => $data['recording_date'] !== '' ? $data['recording_date'] : null,
            ':month_year'     => $month,
            ':sort_order'     => (int) ($_POST['sort_order'] ?? 0),
            ':status'         => ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            ':created_by'     => (int) session_get('user_id'),
        ]);
        $recordingId = (int) $this->db->lastInsertId();

        (new TopicService($this->db))->tag('recordings', $recordingId, $classId, $_POST['topic_id'] ?? null);

        $announced = (new AnnouncementService())->recordingPublished($recordingId);

        $this->flash(
            'success',
            t('Recording added to {class}.', ['class' => $class['class_name']])
            . ($announced['sent'] > 0
                ? ' ' . t('{n} students and guardians have been notified.', ['n' => $announced['sent']])
                : '')
        );
        $this->redirect("/classes/{$classId}/recordings");
    }

    public function publish(string $classId, string $id): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);
        $recording = $this->requireRecording((int) $id, $classId);

        $this->db->prepare("UPDATE recordings SET status = 'published' WHERE id = :id")->execute([':id' => $recording['id']]);
        $announced = (new AnnouncementService())->recordingPublished((int) $recording['id']);

        $this->flash('success', t('Published.') . ($announced['sent'] > 0 ? ' ' . t('{n} students and guardians have been notified.', ['n' => $announced['sent']]) : ''));
        $this->redirect("/classes/{$classId}/recordings");
    }

    public function archive(string $classId, string $id): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);
        $recording = $this->requireRecording((int) $id, $classId);

        $this->db->prepare("UPDATE recordings SET status = 'archived' WHERE id = :id")->execute([':id' => $recording['id']]);

        $this->flash('success', t('Taken down. Students can no longer see it.'));
        $this->redirect("/classes/{$classId}/recordings");
    }

    // ------------------------------------------------------------------
    // Student side
    // ------------------------------------------------------------------

    public function mine(): void
    {
        $studentId = $this->requireStudent();

        $this->view('recordings/mine', [
            'pageTitle'  => 'Class Recordings',
            'activePage' => 'my-recordings',
            'recordings' => $this->forStudent($studentId),
        ]);
    }

    public function open(string $id): void
    {
        $studentId = $this->requireStudent();
        $recording = $this->studentRecording($studentId, (int) $id);

        header('Location: ' . $recording['video_url']);
        exit;
    }

    // ------------------------------------------------------------------
    // Parent side
    // ------------------------------------------------------------------

    public function family(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'parent') {
            $this->redirect('/dashboard');
        }
        $studentId = $this->parentStudentId();
        if ($studentId === null) {
            $this->flash('error', t('No student is linked to this login. Contact the office.'));
            $this->redirect('/logout');
        }

        $name = $this->db->prepare("SELECT full_name FROM students WHERE id = :s");
        $name->execute([':s' => $studentId]);

        $this->view('recordings/family', [
            'pageTitle'  => 'Class Recordings',
            'activePage' => 'family-recordings',
            'childName'  => (string) $name->fetchColumn(),
            'recordings' => $this->forStudent($studentId),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Published recordings of the student's active classes, open ones
     * first in the list, the locked ones shown but marked so, same as
     * assignments -- locked, not hidden, so a family knows it exists.
     *
     * @return array<int, array<string, mixed>>
     */
    private function forStudent(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.id, r.title, r.description, r.recording_date, r.video_url, r.thumbnail_url,
                    r.class_id, r.month_year, c.class_name
               FROM recordings r
               JOIN classes c ON c.id = r.class_id
               JOIN enrollments e ON e.class_id = r.class_id AND e.student_id = :s AND e.enrollment_status = 'active'
              WHERE r.status = 'published'
              ORDER BY r.recording_date DESC, r.id DESC"
        );
        $stmt->execute([':s' => $studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $access = new ContentAccessService();
        foreach ($rows as &$r) {
            $r['locked'] = !$access->hasPaidFor($studentId, (int) $r['class_id'], (string) $r['month_year']);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function studentRecording(int $studentId, int $id): array
    {
        foreach ($this->forStudent($studentId) as $row) {
            if ((int) $row['id'] === $id) {
                if ($row['locked']) {
                    $this->flash('error', t("Settle this month's fee to open the class material."));
                    $this->redirect('/my/recordings');
                }
                return $row;
            }
        }

        $this->flash('error', t('That recording is not available.'));
        $this->redirect('/my/recordings');
    }

    /** @return array<string, mixed> */
    private function requireClass(int $classId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, i.name AS institute_name
               FROM classes c
               LEFT JOIN institutes i ON i.id = c.institute_id
              WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($class === false) {
            $this->flash('error', 'That class does not exist.');
            $this->redirect('/classes');
        }

        return $class;
    }

    /** A teacher manages their own classes; coordinators and above see their branch or every branch. */
    private function requireClassAccess(array $class): void
    {
        // One rule for every role -- see MaterialController for why the
        // teacher_id-only check that used to live here was wrong.
        if (BranchScope::current()->allowsClass((int) ($class['id'] ?? 0))) {
            return;
        }

        $this->flash('error', 'That is not one of your classes.');
        $this->redirect('/classes');
    }

    /** @return array<string, mixed> */
    private function requireRecording(int $id, int $classId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM recordings WHERE id = :id AND class_id = :c");
        $stmt->execute([':id' => $id, ':c' => $classId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r === false) {
            $this->flash('error', t('That recording is not available.'));
            $this->redirect('/classes/' . $classId . '/recordings');
        }

        return $r;
    }

    private function requireStudent(): int
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'student') {
            $this->flash('error', 'That area is for students.');
            $this->redirect('/dashboard');
        }

        return (int) session_get('user_id');
    }

    private function parentStudentId(): ?int
    {
        $stmt = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
        $stmt->execute([':p' => (int) session_get('user_id')]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
