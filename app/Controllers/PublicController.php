<?php

declare(strict_types=1);

namespace App\Controllers;

class PublicController extends BaseController
{
    /**
     * Home page.
     */
    public function home(): void
    {
        // The tuition home page is built from real records: the branches
        // the super admin has made public, and their active classes. No
        // invented student counts, success rates or testimonials.
        $branches = $this->publicBranches();
        $this->view('public/tuition-home', [
            'pageTitle'       => (\App\Services\PublicSite::teacherName() ? \App\Services\PublicSite::teacherName() . ' | ' : '') . t('Science classes') . ' ' . t('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]),
            'metaDescription' => 'Science tuition for Grades 6 to 11 at branches in Pannipitiya, Galle and Kandy - physical and online classes, lesson recordings, quizzes and a parent portal.',
            'activePage'      => 'home',
            'branches'        => $branches,
            'classes'         => $this->publicClasses(),
            'teacher'         => \App\Services\PublicSite::teacherName(),
        ], 'layouts/public');
    }

    /** All public classes, filterable by grade and branch. */
    public function classes(): void
    {
        $grade = preg_match('/^(6|7|8|9|10|11)$/', (string) ($_GET['grade'] ?? '')) ? (string) $_GET['grade'] : null;
        $branch = ctype_digit((string) ($_GET['branch'] ?? '')) ? (int) $_GET['branch'] : null;

        $this->view('public/classes', [
            'pageTitle'       => 'Class timetable | Science Grades 6-11',
            'metaDescription' => 'Science class timetable for Grades 6 to 11 at each branch, with physical and online options.',
            'activePage'      => 'classes',
            'branches'        => $this->publicBranches(),
            'classes'         => $this->publicClasses($grade, $branch),
            'grade'           => $grade,
            'branch'          => $branch,
        ], 'layouts/public');
    }

    /** One branch: where it is, how to reach it, and its classes. */
    public function branch(string $id): void
    {
        $branch = null;
        foreach ($this->publicBranches() as $b) {
            if ((int) $b['id'] === (int) $id) {
                $branch = $b;
            }
        }
        if ($branch === null) {
            $this->redirect('/timetable');
        }

        $this->view('public/branch', [
            'pageTitle'       => $branch['name'] . ' | Science classes',
            'metaDescription' => 'Science classes for Grades 6 to 11 at ' . $branch['name'] . '.',
            'activePage'      => 'branch',
            'branch'          => $branch,
            'classes'         => $this->publicClasses(null, (int) $branch['id']),
        ], 'layouts/public');
    }

    /**
     * Enrolment inquiry from the public site. Stored for the chosen
     * branch's office; spam is held back by a hidden honeypot field, a
     * minimum fill time and a per-session limit.
     */
    public function inquiry(): void
    {
        verifyCsrfToken();
        $back = '/#inquiry';

        $started = (int) ($_POST['started_at'] ?? 0);
        $recent = (array) ($_SESSION['inquiry_times'] ?? []);
        $recent = array_values(array_filter($recent, static fn ($t): bool => (int) $t > time() - 3600));
        if (trim((string) ($_POST['website'] ?? '')) !== '' || ($started > 0 && time() - $started < 3) || count($recent) >= 3) {
            // Looks automated, or too many in an hour: accept silently.
            $this->flash('success', t('Thank you! The branch office will contact you soon.'));
            $this->redirect($back);
        }

        $data = [
            'parent_name'  => trim((string) ($_POST['parent_name'] ?? '')),
            'phone'        => trim((string) ($_POST['phone'] ?? '')),
            'email'        => trim((string) ($_POST['email'] ?? '')),
            'student_name' => trim((string) ($_POST['student_name'] ?? '')),
            'grade'        => (string) ($_POST['grade'] ?? ''),
            'message'      => trim((string) ($_POST['message'] ?? '')),
        ];
        $branchId = (int) ($_POST['branch_id'] ?? 0);
        $branchIds = array_map(static fn (array $b): int => (int) $b['id'], $this->publicBranches());

        $phoneOk = preg_match('/^(\+94|0)?7\d{8}$|^(\+94|0)\d{9}$/', preg_replace('/[\s-]/', '', $data['phone']) ?? '') === 1;
        if ($data['parent_name'] === '' || !$phoneOk || !in_array($data['grade'], ['6', '7', '8', '9', '10', '11'], true)
            || !in_array($branchId, $branchIds, true)
            || ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            || mb_strlen($data['message']) > 2000 || mb_strlen($data['parent_name']) > 255 || mb_strlen($data['student_name']) > 255) {
            $this->flash('error', t('Please give your name, a Sri Lankan phone number, the grade and the branch.'));
            $this->redirect($back);
        }

        $this->db->prepare(
            "INSERT INTO contact_messages (kind, institute_id, grade, student_name, full_name, email, phone, subject, message, status, created_at)
             VALUES ('enrollment', :i, :g, :sn, :n, :e, :p, :s, :m, 'new', NOW())"
        )->execute([
            ':i' => $branchId, ':g' => $data['grade'], ':sn' => $data['student_name'] ?: null,
            ':n' => $data['parent_name'], ':e' => $data['email'], ':p' => $data['phone'],
            ':s' => 'Enrolment inquiry - Grade ' . $data['grade'],
            ':m' => $data['message'] !== '' ? $data['message'] : '-',
        ]);
        $_SESSION['inquiry_times'] = array_merge($recent, [time()]);

        $this->flash('success', t('Thank you! The branch office will contact you soon.'));
        $this->redirect($back);
    }

    /** @return array<int, array<string, mixed>> */
    private function publicBranches(): array
    {
        if (!$this->db) {
            return [];
        }
        try {
            return $this->db->query(
                "SELECT i.id, i.name, i.location, i.phone1, i.phone2, i.public_show_fees,
                        (SELECT COUNT(*) FROM classes c WHERE c.institute_id = i.id AND c.status = 'active') AS class_count
                   FROM institutes i
                  WHERE i.status = 'active' AND i.is_public = 1
                  ORDER BY i.id"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Public branches unavailable: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Public class facts only: no rosters, no meeting links, and the fee
     * only where the branch allows it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function publicClasses(?string $grade = null, ?int $branchId = null): array
    {
        if (!$this->db) {
            return [];
        }
        $sql = "SELECT c.id, c.class_name, c.subject, c.grade, c.medium, c.room, c.delivery_mode,
                       c.schedule_days, c.start_time, c.end_time, c.institute_id,
                       CASE WHEN i.public_show_fees = 1 THEN c.monthly_fee ELSE NULL END AS public_fee,
                       i.name AS branch_name, u.full_name AS teacher_name
                  FROM classes c
                  JOIN institutes i ON i.id = c.institute_id AND i.status = 'active' AND i.is_public = 1
                  LEFT JOIN users u ON u.id = c.teacher_id
                 WHERE c.status = 'active'";
        $params = [];
        if ($grade !== null) {
            $sql .= " AND c.grade LIKE :g";
            $params[':g'] = 'Grade ' . $grade . '%';
        }
        if ($branchId !== null) {
            $sql .= " AND c.institute_id = :b";
            $params[':b'] = $branchId;
        }
        try {
            $stmt = $this->db->prepare($sql . " ORDER BY CAST(SUBSTRING(c.grade, 7) AS UNSIGNED), i.id, c.start_time");
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Public classes unavailable: ' . $e->getMessage());
            return [];
        }
    }

    private function leadTeacher(): ?string
    {
        if (!$this->db) {
            return null;
        }
        try {
            $name = $this->db->query(
                "SELECT u.full_name FROM classes c
                   JOIN institutes i ON i.id = c.institute_id AND i.is_public = 1
                   JOIN users u ON u.id = c.teacher_id
                  WHERE c.status = 'active'
                  GROUP BY u.id ORDER BY COUNT(*) DESC LIMIT 1"
            )->fetchColumn();
            return $name !== false ? (string) $name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * About page.
     */
    public function about(): void
    {
        $this->view('public/about', [
            'pageTitle'       => t('About the classes'),
            'metaDescription' => 'Science classes for Grades 6 to 11: the teacher, how the classes run, and the branches.',
            'activePage'      => 'about',
        ], 'layouts/public');
    }

    /**
     * Features page.
     */
    public function features(): void
    {
        // The old product-feature page described the software, not the
        // classes; parents are sent to the section that explains the classes.
        $this->redirect('/#how');
    }

    /**
     * Contact page — GET shows the form.
     */
    public function contact(): void
    {
        $this->view('public/contact', [
            'pageTitle'       => t('Contact'),
            'metaDescription' => 'Phone numbers and locations of the Science class branches.',
            'activePage'      => 'contact',
        ], 'layouts/public');
    }

    /**
     * Contact form — POST processes the submission.
     */
    public function contactSubmit(): void
    {
        verifyCsrfToken();

        $data = [
            'name'    => trim($_POST['name'] ?? ''),
            'email'   => trim($_POST['email'] ?? ''),
            'phone'   => trim($_POST['phone'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
        ];

        $errors = $this->validate($data, [
            'name'    => 'required|max:255',
            'email'   => 'max:255',
            'phone'   => 'max:50',
            'subject' => 'required|max:255',
            'message' => 'required|max:5000',
        ]);

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('That email address is not valid.');
        }
        if ($data['email'] === '' && $data['phone'] === '') {
            $errors['phone'] = t('Give a phone number or an email so the office can reply.');
        }
        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/contact');
        }

        // Store the contact message (using the database if available)
        if ($this->db) {
            $sql = "INSERT INTO contact_messages (full_name, email, phone, subject, message, created_at)
                    VALUES (:full_name, :email, :phone, :subject, :message, NOW())";
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':full_name' => $data['name'],
                    ':email'     => $data['email'],
                    ':phone'     => $data['phone'] !== '' ? $data['phone'] : null,
                    ':subject'   => $data['subject'],
                    ':message'   => $data['message'],
                ]);
            } catch (\PDOException $e) {
                // Table may not exist yet — log and continue
                error_log('Contact form storage failed: ' . $e->getMessage());
            }
        }

        $this->flash('success', t('Thank you! The branch office will contact you soon.'));
        $this->redirect('/contact');
    }
}
