<?php

declare(strict_types=1);

/**
 * Demo data for the science tuition LMS.
 *
 * One teacher, science, grades 6 to 11, taught across three city
 * branches -- and the material, quizzes, fees and attendance that make
 * the screens worth looking at.
 *
 * Idempotent on purpose. Every insert is guarded by a lookup on a
 * natural key, so running this twice leaves the same database rather
 * than a second copy of every student. That matters because a seeder
 * you are afraid to re-run is a seeder you stop using the moment the
 * schema changes.
 *
 * Nothing already in the database is deleted or rewritten. The existing
 * demo rows stay exactly where they are.
 *
 * Run:  php database/seeds/science_lms_seed.php
 */

define('BASE_PATH', dirname(__DIR__, 2));
define('BASE_URL', '');

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';

$db = Database::connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Shared demo password for every account this seeder creates. */
const DEMO_PASSWORD = 'Demo@12345';

$report = [];
$note = function (string $line) use (&$report): void {
    $report[] = $line;
    echo $line . "\n";
};

// ------------------------------------------------------------------
// Small helpers
// ------------------------------------------------------------------

/**
 * Insert only when the guard query finds nothing; return the row id either way.
 */
function ensure(PDO $db, string $findSql, array $findParams, string $insertSql, array $insertParams): int
{
    $stmt = $db->prepare($findSql);
    $stmt->execute($findParams);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        return (int) $id;
    }

    $db->prepare($insertSql)->execute($insertParams);

    return (int) $db->lastInsertId();
}

/** First day of the month, N months back from today. */
function monthBack(int $n): string
{
    return date('Y-m-01', strtotime("-{$n} month"));
}

$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

// ------------------------------------------------------------------
// 1. Branches — the three cities
// ------------------------------------------------------------------

$branches = [
    ['name' => 'Pannipitiya Science Centre', 'location' => 'No. 120, High Level Road, Pannipitiya', 'phone' => '011 284 1234'],
    ['name' => 'Galle Science Centre',       'location' => 'No. 45, Matara Road, Galle',            'phone' => '091 223 4567'],
    ['name' => 'Kandy Science Centre',       'location' => 'No. 8, Peradeniya Road, Kandy',         'phone' => '081 220 9876'],
];

$branchIds = [];

foreach ($branches as $branch) {
    $branchIds[$branch['name']] = ensure(
        $db,
        "SELECT id FROM institutes WHERE name = :name LIMIT 1",
        [':name' => $branch['name']],
        "INSERT INTO institutes (name, location, phone1, notes, status)
         VALUES (:name, :location, :phone, :notes, 'active')",
        [
            ':name'     => $branch['name'],
            ':location' => $branch['location'],
            ':phone'    => $branch['phone'],
            ':notes'    => 'Science classes, grades 6 to 11.',
        ]
    );
}

$note('Branches ready: ' . implode(', ', array_keys($branchIds)));

// ------------------------------------------------------------------
// 2. The teacher, and the staff around her
// ------------------------------------------------------------------

$teacherId = ensure(
    $db,
    "SELECT id FROM users WHERE user_id_display = 'TCH20260100' LIMIT 1",
    [],
    "INSERT INTO users (user_id_display, role, full_name, email, phone1, whatsapp,
                        password_hash, password_changed_at, status)
     VALUES ('TCH20260100', 'teacher', :name, :email, :phone, :phone2,
             :hash, NOW(), 'active')",
    [
        ':name'   => 'Harshani Madhuwanthi',
        ':email'  => 'harshani@sciencelms.lk',
        ':phone'  => '0771234500',
        ':phone2' => '0771234500',
        ':hash'   => $hash,
    ]
);

$note("Teacher ready: Harshani Madhuwanthi (TCH20260100)");

// One coordinator per branch, as the teacher described.
$coordinators = [
    'Pannipitiya Science Centre' => ['CRD20260101', 'Anura Senanayake',   '0771234511'],
    'Galle Science Centre'       => ['CRD20260102', 'Chamath Dahanayake', '0771234512'],
    'Kandy Science Centre'       => ['CRD20260103', 'Nilmini Ekanayake',  '0771234513'],
];

foreach ($coordinators as $branchName => [$displayId, $name, $phone]) {
    $coordId = ensure(
        $db,
        "SELECT id FROM users WHERE user_id_display = :d LIMIT 1",
        [':d' => $displayId],
        "INSERT INTO users (user_id_display, role, full_name, email, phone1,
                            password_hash, password_changed_at, status)
         VALUES (:d, 'coordinator', :name, :email, :phone, :hash, NOW(), 'active')",
        [
            ':d'     => $displayId,
            ':name'  => $name,
            ':email' => strtolower(str_replace(' ', '.', $name)) . '@sciencelms.lk',
            ':phone' => $phone,
            ':hash'  => $hash,
        ]
    );

    // Attach the coordinator to their branch, but never overwrite one
    // somebody has already set by hand.
    $db->prepare(
        "UPDATE institutes SET coordinator_id = :c WHERE id = :i AND coordinator_id IS NULL"
    )->execute([':c' => $coordId, ':i' => $branchIds[$branchName]]);
}

// One admin per branch.
$admins = [
    ['ADM20260101', 'Kasun Wijeratne',  '0771234521'],
    ['ADM20260102', 'Shanika Perera',   '0771234522'],
    ['ADM20260103', 'Ruwan Gunasekara', '0771234523'],
];

foreach ($admins as [$displayId, $name, $phone]) {
    ensure(
        $db,
        "SELECT id FROM users WHERE user_id_display = :d LIMIT 1",
        [':d' => $displayId],
        "INSERT INTO users (user_id_display, role, full_name, email, phone1,
                            password_hash, password_changed_at, status)
         VALUES (:d, 'admin', :name, :email, :phone, :hash, NOW(), 'active')",
        [
            ':d'     => $displayId,
            ':name'  => $name,
            ':email' => strtolower(str_replace(' ', '.', $name)) . '@sciencelms.lk',
            ':phone' => $phone,
            ':hash'  => $hash,
        ]
    );
}

$note('Coordinators and admins ready: 3 each, one per branch.');

// ------------------------------------------------------------------
// 3. Classes — grades 6 to 11, science
// ------------------------------------------------------------------

/**
 * The main branch carries every grade; the two outstation branches run
 * the upper grades only, which is how a single teacher covering three
 * cities actually divides a week.
 */
$gradePlan = [
    'Pannipitiya Science Centre' => [6, 7, 8, 9, 10, 11],
    'Galle Science Centre'       => [9, 10, 11],
    'Kandy Science Centre'       => [9, 10, 11],
];

/** Fee rises with the grade; O/L years cost more. */
$feeFor = fn (int $grade): string => match (true) {
    $grade >= 10 => '2500.00',
    $grade >= 8  => '2000.00',
    default      => '1750.00',
};

$dayFor = [
    'Pannipitiya Science Centre' => ['Saturday', 'Sunday'],
    'Galle Science Centre'       => ['Friday'],
    'Kandy Science Centre'       => ['Wednesday'],
];

$classIds = [];

foreach ($gradePlan as $branchName => $grades) {
    foreach ($grades as $i => $grade) {
        $className = sprintf('Grade %d Science - %s', $grade, explode(' ', $branchName)[0]);
        $start = sprintf('%02d:00:00', 8 + ($i * 2));
        $end   = sprintf('%02d:30:00', 9 + ($i * 2));

        $classIds[$className] = ensure(
            $db,
            "SELECT id FROM classes WHERE class_name = :n LIMIT 1",
            [':n' => $className],
            "INSERT INTO classes (class_name, subject, medium, grade, institute_id, room,
                                  teacher_id, schedule_days, start_time, end_time,
                                  monthly_fee, status)
             VALUES (:n, 'Science', 'Sinhala', :grade, :inst, :room,
                     :teacher, :days, :start, :end, :fee, 'active')",
            [
                ':n'       => $className,
                ':grade'   => 'Grade ' . $grade,
                ':inst'    => $branchIds[$branchName],
                ':room'    => 'Hall ' . (($i % 3) + 1),
                ':teacher' => $teacherId,
                ':days'    => json_encode($dayFor[$branchName]),
                ':start'   => $start,
                ':end'     => $end,
                ':fee'     => $feeFor($grade),
            ]
        );
    }
}

$note('Classes ready: ' . count($classIds) . ' science classes across grades 6-11.');

// ------------------------------------------------------------------
// 4. Students, enrolments and guardians
// ------------------------------------------------------------------

$firstNames = ['Sathsara','Imesha','Dinuka','Hiruni','Kavindu','Nethmi','Tharusha','Sanduni',
               'Ravindu','Oshadi','Pasindu','Thilini','Yasiru','Amaya','Chamodi','Senuri',
               'Vihanga','Dulanjali','Kalana','Nipuni','Ashen','Rashmi','Janith','Sewwandi',
               'Lahiru','Upeksha','Charith','Hasini','Malsha','Tharindi'];
$lastNames  = ['Perera','Fernando','Silva','Jayasinghe','Bandara','Rajapaksha','Wickramasinghe',
               'Gunawardena','Herath','Dissanayake'];

$classList = array_values($classIds);
$classNames = array_keys($classIds);
$studentCount = 0;
$enrolCount = 0;

foreach ($firstNames as $n => $first) {
    $last = $lastNames[$n % count($lastNames)];
    $displayId = sprintf('STU2026%04d', 200 + $n);
    $fullName = "{$first} {$last}";

    $studentId = ensure(
        $db,
        "SELECT id FROM students WHERE student_id_display = :d LIMIT 1",
        [':d' => $displayId],
        "INSERT INTO students (student_id_display, full_name, email, phone1, whatsapp,
                               home_number, date_of_birth, address, province, district,
                               password_hash, password_changed_at, status, created_by)
         VALUES (:d, :name, :email, :phone, :wa, :home, :dob, :addr, :prov, :dist,
                 :hash, NOW(), 'active', :by)",
        [
            ':d'     => $displayId,
            ':name'  => $fullName,
            ':email' => strtolower($first . '.' . $last) . '@student.sciencelms.lk',
            // PDO binds a named placeholder once, so phone1 and whatsapp
            // each need their own even when the value is identical.
            ':phone' => '07' . str_pad((string) (10000000 + ($n * 37)), 8, '0', STR_PAD_LEFT),
            ':wa'    => '07' . str_pad((string) (10000000 + ($n * 37)), 8, '0', STR_PAD_LEFT),
            ':home'  => '011' . str_pad((string) (2000000 + ($n * 13)), 7, '0', STR_PAD_LEFT),
            // Grades 6-11 means roughly 11 to 17 year olds.
            ':dob'   => date('Y-m-d', strtotime(sprintf('-%d years -%d days', 11 + ($n % 6), $n * 9))),
            ':addr'  => sprintf('No. %d, Temple Road', 10 + $n),
            ':prov'  => ['Western','Southern','Central'][$n % 3],
            ':dist'  => ['Colombo','Galle','Kandy'][$n % 3],
            ':hash'  => $hash,
            ':by'    => $teacherId,
        ]
    );
    $studentCount++;

    // A guardian for roughly every second student, so the parent portal
    // has both cases to show: a child with a linked parent and one without.
    if ($n % 2 === 0) {
        // `parents` names the column `parent_name`, not `full_name`, and
        // carries no email or relationship field -- a guardian here is a
        // phone number and a password, nothing more.
        ensure(
            $db,
            "SELECT id FROM parents WHERE student_id = :s LIMIT 1",
            [':s' => $studentId],
            "INSERT INTO parents (student_id, parent_name, phone1, whatsapp,
                                  password_hash, status)
             VALUES (:s, :name, :phone, :wa, :hash, 'active')",
            [
                ':s'     => $studentId,
                ':name'  => ($n % 4 === 0 ? 'Mr. ' : 'Mrs. ') . $last,
                ':phone' => '07' . str_pad((string) (20000000 + ($n * 41)), 8, '0', STR_PAD_LEFT),
                ':wa'    => '07' . str_pad((string) (20000000 + ($n * 41)), 8, '0', STR_PAD_LEFT),
                ':hash'  => $hash,
            ]
        );
    }

    // Each student takes the class for their own grade, at one branch.
    //
    // An earlier version gave every third student a second class picked
    // four places along the list -- which landed them in a different
    // grade, a Grade 6 child also sitting Grade 10 science. One teacher
    // teaches one grade to each child, and the portal is built on a
    // student seeing only their own grade, so the demo must not invent
    // the opposite.
    $primary = $classList[$n % count($classList)];
    $picks = [$primary];

    foreach (array_unique($picks) as $classId) {
        // Roughly one in ten is on a free card; the gate must let them
        // through without a payment row, and the demo should show that.
        $scholarship = $n % 10 === 7 ? 'free_card' : ($n % 10 === 3 ? 'half_payment' : 'full_payment');

        $enrolId = ensure(
            $db,
            "SELECT id FROM enrollments WHERE student_id = :s AND class_id = :c LIMIT 1",
            [':s' => $studentId, ':c' => $classId],
            "INSERT INTO enrollments (student_id, class_id, scholarship_type, enrollment_status)
             VALUES (:s, :c, :sch, 'active')",
            [':s' => $studentId, ':c' => $classId, ':sch' => $scholarship]
        );

        if ($enrolId > 0) {
            $enrolCount++;
        }
    }
}

$note("Students ready: {$studentCount}, with guardians on every second one.");
$note("Enrolments ready: {$enrolCount}.");

// ------------------------------------------------------------------
// 5. Fees — deliberately mixed, so the content gate is visible
// ------------------------------------------------------------------

/**
 * Three months of fees. The pattern is chosen so that every state the
 * gate can be in appears somewhere in the demo: a settled month that
 * opens, an overdue month that locks, a pending current month, and
 * free-card students who are never asked for either.
 *
 *   two months ago  -- everyone settled
 *   last month      -- mostly settled, a visible tail of overdue
 *   this month      -- a third settled, the rest still pending
 */
$feePlan = [
    monthBack(2) => fn (int $i): string => 'paid',
    monthBack(1) => fn (int $i): string => $i % 5 === 0 ? 'overdue' : 'paid',
    monthBack(0) => fn (int $i): string => $i % 3 === 0 ? 'paid' : 'pending',
];

// Scoped to this teacher's classes for the same reason as the material
// below: raising fees against a class somebody else runs is not this
// seeder's business.
$enrolments = $db->prepare(
    "SELECT e.id, e.student_id, e.class_id, e.scholarship_type, c.monthly_fee
       FROM enrollments e
       JOIN classes c ON c.id = e.class_id
      WHERE e.enrollment_status = 'active'
        AND c.subject = 'Science'
        AND c.teacher_id = :teacher"
);
$enrolments->execute([':teacher' => $teacherId]);
$enrolments = $enrolments->fetchAll(PDO::FETCH_ASSOC);

$feeRows = 0;

foreach ($feePlan as $month => $statusFor) {
    foreach ($enrolments as $i => $e) {
        // A free card owes nothing, so there is no fee row to raise.
        if ($e['scholarship_type'] === 'free_card') {
            continue;
        }

        $due = (float) $e['monthly_fee'];
        if ($e['scholarship_type'] === 'half_payment') {
            $due = round($due / 2, 2);
        }

        $status = $statusFor($i);
        $paid = $status === 'paid' ? $due : 0.00;

        $existing = $db->prepare(
            "SELECT id FROM payments
              WHERE student_id = :s AND class_id = :c AND month_year = :m LIMIT 1"
        );
        $existing->execute([':s' => $e['student_id'], ':c' => $e['class_id'], ':m' => $month]);

        if ($existing->fetchColumn() !== false) {
            continue;
        }

        $db->prepare(
            "INSERT INTO payments (enrollment_id, student_id, class_id, month_year,
                                   amount_due, amount_paid, scholarship_type,
                                   payment_status, paid_at, marked_by)
             VALUES (:e, :s, :c, :m, :due, :paid, :sch, :status, :paid_at, :by)"
        )->execute([
            ':e'       => $e['id'],
            ':s'       => $e['student_id'],
            ':c'       => $e['class_id'],
            ':m'       => $month,
            ':due'     => $due,
            ':paid'    => $paid,
            ':sch'     => $e['scholarship_type'],
            ':status'  => $status,
            ':paid_at' => $status === 'paid' ? date('Y-m-d H:i:s', strtotime($month . ' +4 days')) : null,
            ':by'      => $status === 'paid' ? $teacherId : null,
        ]);

        $feeRows++;
    }
}

$note("Fees ready: {$feeRows} rows across three months, mixed paid / overdue / pending.");

// ------------------------------------------------------------------
// 6. Class material — recordings, reading, video
// ------------------------------------------------------------------

/** Lesson topics by grade, so the demo reads like real science teaching. */
$topics = [
    6  => ['The Solar System', 'States of Matter', 'Plants Around Us'],
    7  => ['Heat and Temperature', 'Cells and Life', 'Simple Machines'],
    8  => ['Acids, Bases and Salts', 'The Human Digestive System', 'Light and Reflection'],
    9  => ['Chemical Reactions', 'Electricity and Circuits', 'Photosynthesis'],
    10 => ['Periodic Table Basics', 'Force, Work and Energy', 'Human Nervous System'],
    11 => ['Organic Compounds', 'Electromagnetism', 'Genetics and Heredity'],
];

$materialRows = 0;
$months = [monthBack(2), monthBack(1), monthBack(0)];

// Only the classes this seeder creates. Matching on subject = 'Science'
// alone would also sweep in any pre-existing demo class that happens to
// teach science under another teacher, and fill somebody else's class
// with Harshani's material.
$classRows = $db->prepare(
    "SELECT id, class_name, grade FROM classes
      WHERE subject = 'Science' AND status = 'active' AND teacher_id = :teacher"
);
$classRows->execute([':teacher' => $teacherId]);
$classRows = $classRows->fetchAll(PDO::FETCH_ASSOC);

foreach ($classRows as $class) {
    $grade = (int) filter_var($class['grade'], FILTER_SANITIZE_NUMBER_INT);
    $classTopics = $topics[$grade] ?? $topics[9];

    foreach ($months as $mIndex => $month) {
        $topic = $classTopics[$mIndex % count($classTopics)];

        $items = [
            [
                'title' => "{$topic} - full lesson recording",
                'type'  => 'recording',
                'desc'  => "Zoom recording of the {$topic} lesson. Watch it again before the quiz.",
                'url'   => 'https://zoom.us/rec/share/demo-' . $class['id'] . '-' . ($mIndex + 1),
                'mins'  => 95,
            ],
            [
                'title' => "{$topic} - lesson notes",
                'type'  => 'reading',
                'desc'  => "Printable notes covering everything in the {$topic} lesson.",
                'url'   => 'https://drive.google.com/file/d/demo-notes-' . $class['id'] . '-' . ($mIndex + 1),
                'mins'  => null,
            ],
            [
                'title' => "{$topic} - experiment video",
                'type'  => 'video',
                'desc'  => "A short demonstration of the {$topic} experiment done in class.",
                'url'   => 'https://www.youtube.com/watch?v=demo' . $class['id'] . $mIndex,
                'mins'  => 12,
            ],
        ];

        foreach ($items as $order => $item) {
            $exists = $db->prepare(
                "SELECT id FROM class_materials
                  WHERE class_id = :c AND title = :t AND month_year = :m LIMIT 1"
            );
            $exists->execute([':c' => $class['id'], ':t' => $item['title'], ':m' => $month]);

            if ($exists->fetchColumn() !== false) {
                continue;
            }

            $db->prepare(
                "INSERT INTO class_materials (class_id, title, material_type, description,
                                              external_url, lesson_date, duration_minutes,
                                              month_year, sort_order, status, uploaded_by)
                 VALUES (:c, :t, :type, :desc, :url, :lesson, :mins, :m, :ord, 'published', :by)"
            )->execute([
                ':c'      => $class['id'],
                ':t'      => $item['title'],
                ':type'   => $item['type'],
                ':desc'   => $item['desc'],
                ':url'    => $item['url'],
                ':lesson' => date('Y-m-d', strtotime($month . ' +6 days')),
                ':mins'   => $item['mins'],
                ':m'      => $month,
                ':ord'    => $order,
                ':by'     => $teacherId,
            ]);

            $materialRows++;
        }
    }
}

$note("Material ready: {$materialRows} items -- recordings, notes and videos.");

// ------------------------------------------------------------------
// 7. Quizzes, questions, and some sat papers
// ------------------------------------------------------------------

/** A small bank of real science questions, by grade band. */
$questionBank = [
    'lower' => [
        ['Which planet is closest to the Sun?', ['Mercury','Venus','Earth','Mars'], 'a'],
        ['What state of matter has a fixed shape and a fixed volume?', ['Solid','Liquid','Gas','Plasma'], 'a'],
        ['Which part of a plant makes its food?', ['Root','Stem','Leaf','Flower'], 'c'],
        ['Water boils at what temperature at sea level?', ['50 C','75 C','100 C','150 C'], 'c'],
        ['Which gas do plants take in during photosynthesis?', ['Oxygen','Carbon dioxide','Nitrogen','Hydrogen'], 'b'],
    ],
    'upper' => [
        ['What is the chemical formula for water?', ['CO2','H2O','NaCl','O2'], 'b'],
        ['Which particle in an atom carries a negative charge?', ['Proton','Neutron','Electron','Nucleus'], 'c'],
        ['What is the SI unit of force?', ['Joule','Watt','Newton','Pascal'], 'c'],
        ['Which organ pumps blood around the body?', ['Liver','Heart','Lung','Kidney'], 'b'],
        ['An acid turns blue litmus paper what colour?', ['Green','Blue','Red','Yellow'], 'c'],
        ['What does DNA stand for?', ['Deoxyribonucleic acid','Dinitrogen acid','Dual nucleic acid','Deoxy nitrate acid'], 'a'],
    ],
];

$quizCount = 0;
$questionCount = 0;

foreach ($classRows as $class) {
    $grade = (int) filter_var($class['grade'], FILTER_SANITIZE_NUMBER_INT);
    $bank = $grade >= 9 ? $questionBank['upper'] : $questionBank['lower'];
    $classTopics = $topics[$grade] ?? $topics[9];

    // One quiz for last month (closed, with results) and one for this
    // month that is open now, so both states can be demonstrated.
    $plan = [
        [
            'month'  => monthBack(1),
            'title'  => $classTopics[1 % count($classTopics)] . ' - monthly test',
            'opens'  => date('Y-m-d 18:00:00', strtotime(monthBack(1) . ' +20 days')),
            'closes' => date('Y-m-d 20:00:00', strtotime(monthBack(1) . ' +20 days')),
            'status' => 'published',
        ],
        [
            'month'  => monthBack(0),
            'title'  => $classTopics[2 % count($classTopics)] . ' - monthly test',
            'opens'  => date('Y-m-d H:i:s', strtotime('-2 days')),
            'closes' => date('Y-m-d H:i:s', strtotime('+12 days')),
            'status' => 'published',
        ],
    ];

    foreach ($plan as $p) {
        $exists = $db->prepare(
            "SELECT id FROM quizzes WHERE class_id = :c AND title = :t AND month_year = :m LIMIT 1"
        );
        $exists->execute([':c' => $class['id'], ':t' => $p['title'], ':m' => $p['month']]);
        $quizId = $exists->fetchColumn();

        if ($quizId === false) {
            $db->prepare(
                "INSERT INTO quizzes (class_id, title, description, instructions,
                                      opens_at, closes_at, time_limit_minutes,
                                      pass_mark, max_attempts, month_year,
                                      quiz_status, created_by)
                 VALUES (:c, :t, :desc, :inst, :opens, :closes, 20, 50.00, 1, :m, :status, :by)"
            )->execute([
                ':c'      => $class['id'],
                ':t'      => $p['title'],
                ':desc'   => 'Short test on what we covered this month.',
                ':inst'   => 'Answer every question. You have 20 minutes once you start.',
                ':opens'  => $p['opens'],
                ':closes' => $p['closes'],
                ':m'      => $p['month'],
                ':status' => $p['status'],
                ':by'     => $teacherId,
            ]);
            $quizId = (int) $db->lastInsertId();
            $quizCount++;

            foreach (array_slice($bank, 0, 5) as $qNo => [$text, $options, $correct]) {
                $db->prepare(
                    "INSERT INTO quiz_questions (quiz_id, question_number, question_text,
                                                 question_type, marks,
                                                 option_a, option_b, option_c, option_d,
                                                 correct_option, explanation)
                     VALUES (:q, :no, :text, 'mcq', 10.00, :a, :b, :c, :d, :correct, :exp)"
                )->execute([
                    ':q'       => $quizId,
                    ':no'      => $qNo + 1,
                    ':text'    => $text,
                    ':a'       => $options[0],
                    ':b'       => $options[1],
                    ':c'       => $options[2],
                    ':d'       => $options[3],
                    ':correct' => $correct,
                    ':exp'     => 'Covered in this month\'s lesson recording.',
                ]);
                $questionCount++;
            }

            // Keep the header total honest against the questions attached.
            $db->prepare(
                "UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks),0)
                   FROM quiz_questions WHERE quiz_id = :q) WHERE id = :q2"
            )->execute([':q' => $quizId, ':q2' => $quizId]);
        }
    }
}

$note("Quizzes ready: {$quizCount} with {$questionCount} questions.");

// ------------------------------------------------------------------
// 8. Sat papers — so marks, progress and the parent view have data
// ------------------------------------------------------------------

/**
 * Only last month's closed quizzes get attempts. This month's are left
 * open and unsat on purpose, so the student portal shows a live quiz
 * waiting to be taken rather than a page where everything is already done.
 */
$closedQuizzes = $db->query(
    "SELECT q.id, q.class_id, q.total_marks
       FROM quizzes q
      WHERE q.closes_at < NOW()
        AND q.quiz_status = 'published'"
)->fetchAll(PDO::FETCH_ASSOC);

$attemptCount = 0;
$answerCount = 0;

foreach ($closedQuizzes as $quiz) {
    $questions = $db->prepare(
        "SELECT id, correct_option, marks FROM quiz_questions WHERE quiz_id = :q ORDER BY question_number"
    );
    $questions->execute([':q' => $quiz['id']]);
    $questions = $questions->fetchAll(PDO::FETCH_ASSOC);

    if ($questions === []) {
        continue;
    }

    $sitters = $db->prepare(
        "SELECT e.student_id
           FROM enrollments e
          WHERE e.class_id = :c AND e.enrollment_status = 'active'"
    );
    $sitters->execute([':c' => $quiz['class_id']]);
    $sitters = $sitters->fetchAll(PDO::FETCH_COLUMN);

    foreach ($sitters as $s => $studentId) {
        // Four in five sat it. The absent fifth is realistic and gives
        // the teacher's results screen a "did not sit" row to show.
        if ($s % 5 === 4) {
            continue;
        }

        $exists = $db->prepare(
            "SELECT id FROM quiz_attempts WHERE quiz_id = :q AND student_id = :s LIMIT 1"
        );
        $exists->execute([':q' => $quiz['id'], ':s' => $studentId]);

        if ($exists->fetchColumn() !== false) {
            continue;
        }

        // A spread of ability rather than everyone near the same mark,
        // so the progress levels actually differ between students.
        $rightCount = [5, 4, 4, 3, 3, 2, 5, 3, 4, 1][($studentId + $s) % 10];

        $started = date('Y-m-d H:i:s', strtotime('-' . (20 + $s) . ' days'));
        $db->prepare(
            "INSERT INTO quiz_attempts (quiz_id, student_id, attempt_number, started_at,
                                        submitted_at, expires_at, attempt_status, needs_marking)
             VALUES (:q, :s, 1, :started, :submitted, :expires, 'graded', 0)"
        )->execute([
            ':q'         => $quiz['id'],
            ':s'         => $studentId,
            ':started'   => $started,
            ':submitted' => date('Y-m-d H:i:s', strtotime($started . ' +14 minutes')),
            ':expires'   => date('Y-m-d H:i:s', strtotime($started . ' +20 minutes')),
        ]);
        $attemptId = (int) $db->lastInsertId();
        $attemptCount++;

        $score = 0.0;
        $total = 0.0;

        foreach ($questions as $qi => $question) {
            $total += (float) $question['marks'];
            $isCorrect = $qi < $rightCount;

            // A wrong answer picks a different option than the right one,
            // so the review screen shows a real mistake, not a blank.
            $chosen = $isCorrect
                ? $question['correct_option']
                : ['a','b','c','d'][(array_search($question['correct_option'], ['a','b','c','d'], true) + 1) % 4];

            $awarded = $isCorrect ? (float) $question['marks'] : 0.0;
            $score += $awarded;

            $db->prepare(
                "INSERT INTO quiz_answers (attempt_id, question_id, selected_option,
                                           is_correct, marks_awarded)
                 VALUES (:a, :q, :sel, :ok, :marks)"
            )->execute([
                ':a'     => $attemptId,
                ':q'     => $question['id'],
                ':sel'   => $chosen,
                ':ok'    => $isCorrect ? 1 : 0,
                ':marks' => $awarded,
            ]);
            $answerCount++;
        }

        $db->prepare(
            "UPDATE quiz_attempts
                SET score = :score, total_marks = :total, percentage = :pct
              WHERE id = :id"
        )->execute([
            ':score' => $score,
            ':total' => $total,
            ':pct'   => $total > 0 ? round(($score / $total) * 100, 2) : 0,
            ':id'    => $attemptId,
        ]);
    }
}

$note("Sat papers ready: {$attemptCount} attempts, {$answerCount} answers, all marked.");

// ------------------------------------------------------------------
// 9. Attendance and material views
// ------------------------------------------------------------------

$attendanceRows = 0;

foreach ($classRows as $class) {
    $enrolled = $db->prepare(
        "SELECT student_id FROM enrollments WHERE class_id = :c AND enrollment_status = 'active'"
    );
    $enrolled->execute([':c' => $class['id']]);
    $enrolled = $enrolled->fetchAll(PDO::FETCH_COLUMN);

    // Eight recent sessions per class.
    for ($week = 0; $week < 8; $week++) {
        $date = date('Y-m-d', strtotime("-{$week} week"));

        foreach ($enrolled as $s => $studentId) {
            $exists = $db->prepare(
                "SELECT id FROM attendance WHERE student_id = :s AND class_id = :c AND date = :d LIMIT 1"
            );
            $exists->execute([':s' => $studentId, ':c' => $class['id'], ':d' => $date]);

            if ($exists->fetchColumn() !== false) {
                continue;
            }

            $roll = ($studentId + $week + $s) % 10;
            $status = match (true) {
                $roll === 0 => 'absent',
                $roll === 1 => 'late',
                default     => 'present',
            };

            $db->prepare(
                "INSERT INTO attendance (student_id, class_id, date, check_in_time,
                                         status, scan_method, marked_by)
                 VALUES (:s, :c, :d, :t, :st, 'qr_scan', :by)"
            )->execute([
                ':s'  => $studentId,
                ':c'  => $class['id'],
                ':d'  => $date,
                ':t'  => $status === 'late' ? '08:21:00' : '07:52:00',
                ':st' => $status,
                ':by' => $teacherId,
            ]);
            $attendanceRows++;
        }
    }
}

$note("Attendance ready: {$attendanceRows} records over eight weeks.");

// Material views, so the engagement half of the progress score is real.
$viewRows = 0;
$openMaterials = $db->query(
    "SELECT m.id AS material_id, e.student_id
       FROM class_materials m
       JOIN enrollments e ON e.class_id = m.class_id AND e.enrollment_status = 'active'
      WHERE m.status = 'published'"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($openMaterials as $i => $row) {
    // Around two thirds get opened: enough variation that engagement
    // actually separates students instead of being 100% for everyone.
    if (($row['student_id'] + $i) % 3 === 0) {
        continue;
    }

    $db->prepare(
        "INSERT IGNORE INTO material_views (material_id, student_id, view_count)
         VALUES (:m, :s, :n)"
    )->execute([
        ':m' => $row['material_id'],
        ':s' => $row['student_id'],
        ':n' => 1 + ($i % 3),
    ]);
    $viewRows++;
}

$note("Material views ready: {$viewRows}.");

echo "\n";
echo "Demo logins (password for all: " . DEMO_PASSWORD . ")\n";
echo "  Teacher      TCH20260100   Harshani Madhuwanthi\n";
echo "  Admin        ADM20260101   Kasun Wijeratne\n";
echo "  Coordinator  CRD20260101   Anura Senanayake\n";
echo "  Student      STU20260200   (and STU20260201 upwards)\n";
echo "\nSeed complete.\n";
