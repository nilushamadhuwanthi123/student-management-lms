<?php

declare(strict_types=1);

/**
 * Router class and route definitions.
 */

class Router
{
    /** @var array<array{method: string, pattern: string, handler: callable|array, middleware: array<string>}> */
    private array $routes = [];

    /** @var array<string> Current middleware group */
    private array $currentMiddleware = [];

    /** @var string Current route prefix */
    private string $currentPrefix = '';

    /**
     * Register a GET route.
     */
    public function get(string $uri, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $uri, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $uri, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $uri, $handler, $middleware);
    }

    /**
     * Group routes with shared middleware and/or prefix.
     *
     * @param array{middleware?: array<string>, prefix?: string} $options
     */
    public function group(array $options, callable $callback): self
    {
        $previousMiddleware = $this->currentMiddleware;
        $previousPrefix = $this->currentPrefix;

        if (isset($options['middleware'])) {
            $this->currentMiddleware = array_merge(
                $this->currentMiddleware,
                (array) $options['middleware']
            );
        }

        if (isset($options['prefix'])) {
            $this->currentPrefix .= '/' . trim($options['prefix'], '/');
        }

        $callback($this);

        $this->currentMiddleware = $previousMiddleware;
        $this->currentPrefix = $previousPrefix;

        return $this;
    }

    /**
     * Add a route to the collection.
     */
    private function addRoute(string $method, string $uri, callable|array $handler, array $middleware = []): self
    {
        $fullUri = $this->currentPrefix . '/' . trim($uri, '/');
        $fullUri = '/' . trim($fullUri, '/');

        // Normalize root path
        if ($fullUri === '') {
            $fullUri = '/';
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => $fullUri,
            'handler' => $handler,
            'middleware' => array_merge($this->currentMiddleware, $middleware),
        ];

        return $this;
    }

    /**
     * Match a request URI and method against registered routes.
     *
     * @return array{handler: callable|array, params: array<string, string>, middleware: array<string>}|null
     */
    public function match(string $method, string $uri): ?array
    {
        // Strip query string
        $uri = strtok($uri, '?') ?: '/';
        $uri = '/' . trim($uri, '/');

        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPattern($route['pattern'], $uri);

            if ($params !== null) {
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => $route['middleware'],
                ];
            }
        }

        return null;
    }

    /**
     * Match a route pattern against a URI and extract parameters.
     *
     * @return array<string, string>|null  Params on match, null on no match.
     */
    private function matchPattern(string $pattern, string $uri): ?array
    {
        // Convert {param} to named regex groups
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Extract only named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return null;
    }

    /**
     * Get all registered routes (for debugging).
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Route Definitions
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

$router = new Router();

// Public routes
$router->get('/', ['App\Controllers\HomeController', 'index']);
$router->get('/about', ['App\Controllers\HomeController', 'about']);
$router->get('/features', ['App\Controllers\HomeController', 'features']);
$router->get('/contact', ['App\Controllers\HomeController', 'contact']);
$router->post('/contact', ['App\Controllers\HomeController', 'contactSubmit']);
// Public tuition pages: timetable, branch pages and the enrolment inquiry.
// /our-classes is the branch-picker landing page (photo + description +
// "My Classes" per branch) -- NOT /classes, which is already the
// logged-in Class Management route below. /timetable is the full,
// filterable class grid that "My Classes" links into.
$router->get('/our-classes', ['App\Controllers\HomeController', 'classesLanding']);
$router->get('/timetable', ['App\Controllers\HomeController', 'classes']);
$router->get('/branches/{id}', ['App\Controllers\HomeController', 'branch']);
$router->post('/inquiry', ['App\Controllers\HomeController', 'inquiry']);
$router->get('/downloads', ['App\Controllers\DownloadController', 'index']);
$router->get('/events', ['App\Controllers\PublicController', 'events']);
$router->get('/enroll', ['App\Controllers\EnrollmentController', 'form']);
$router->post('/enroll', ['App\Controllers\EnrollmentController', 'store']);
$router->get('/enroll/classes/{id}', ['App\Controllers\EnrollmentController', 'classesByInstitute']);

$router->get('/seminar/{id}', ['App\Controllers\PublicController', 'seminar']);

// The free FAQ bot widget. Guest-facing, answers only from public data.
$router->post('/chatbot/ask', ['App\Controllers\ChatBotController', 'ask']);

// Authentication routes
$router->get('/login', ['App\Controllers\AuthController', 'showLogin']);
$router->post('/login', ['App\Controllers\AuthController', 'login']);
$router->get('/logout', ['App\Controllers\AuthController', 'logout']);
$router->post('/logout', ['App\Controllers\AuthController', 'logout']);

// English / Sinhala. Outside the auth group on purpose: the login page
// is where a student first needs it.
$router->get('/lang/{code}', ['App\Controllers\LanguageController', 'switch']);
$router->get('/first-login-password', ['App\Controllers\AuthController', 'showFirstLoginPassword']);
$router->post('/first-login-password', ['App\Controllers\AuthController', 'updateFirstLoginPassword']);

// Forgotten password: redeem a one-time code issued by the branch office.
$router->get('/forgot-password', ['App\Controllers\AccountController', 'showForgot']);
$router->post('/forgot-password', ['App\Controllers\AccountController', 'requestCode']);
$router->get('/reset-password', ['App\Controllers\AccountController', 'showReset']);
$router->post('/reset-password', ['App\Controllers\AccountController', 'reset']);

// Password management routes (requires auth)
$router->get('/change-password', ['App\Controllers\AuthController', 'showChangePassword']);
$router->post('/change-password', ['App\Controllers\AuthController', 'changePassword']);

// Authenticated routes
$router->group(['middleware' => ['auth']], function (Router $router) {

    // Dashboard
    $router->get('/dashboard', ['App\Controllers\DashboardController', 'index']);

    // User Profile
    $router->get('/profile', ['App\Controllers\UserController', 'profile']);

    // User & Role Management (Admins, Coordinators, Staff, Teachers)
    $router->get('/users', ['App\Controllers\UserController', 'index']);
    $router->get('/users/create', ['App\Controllers\UserController', 'create']);
    $router->post('/users/create', ['App\Controllers\UserController', 'store']);
    $router->get('/users/{id}/edit', ['App\Controllers\UserController', 'edit']);
    $router->post('/users/{id}/edit', ['App\Controllers\UserController', 'update']);
    $router->post('/users/{id}/toggle-status', ['App\Controllers\UserController', 'toggleStatus']);
    $router->post('/users/{id}/delete', ['App\Controllers\UserController', 'delete']);
    $router->post('/users/{id}/reset-password', ['App\Controllers\UserController', 'resetPassword']);
    $router->post('/users/{id}/reset-code', ['App\Controllers\AccountController', 'userCode']);
    $router->post('/students/{id}/reset-code', ['App\Controllers\AccountController', 'studentCode']);
    $router->post('/parents/{id}/reset-code', ['App\Controllers\AccountController', 'parentCode']);
    $router->get('/students/{studentId}/parents/create', ['App\Controllers\ParentController', 'create']);
    $router->post('/students/{studentId}/parents/create', ['App\Controllers\ParentController', 'store']);
    $router->get('/parents/{id}/edit', ['App\Controllers\ParentController', 'edit']);
    $router->post('/parents/{id}/edit', ['App\Controllers\ParentController', 'update']);
    $router->post('/parents/{id}/deactivate', ['App\Controllers\ParentController', 'deactivate']);
    $router->post('/parents/{id}/reactivate', ['App\Controllers\ParentController', 'reactivate']);
    $router->get('/accounts/issued', ['App\Controllers\AccountController', 'issued']);
    $router->post('/accounts/issued/send', ['App\Controllers\AccountController', 'sendIssued']);
    $router->get('/users/{id}/id-card', ['App\Controllers\UserController', 'idCard']);

    // Institutes Management
    $router->get('/institutes', ['App\Controllers\InstituteController', 'index']);
    $router->get('/institutes/create', ['App\Controllers\InstituteController', 'create']);
    $router->post('/institutes/create', ['App\Controllers\InstituteController', 'store']);
    $router->get('/institutes/{id}/edit', ['App\Controllers\InstituteController', 'edit']);
    $router->post('/institutes/{id}/edit', ['App\Controllers\InstituteController', 'update']);
    $router->post('/institutes/{id}/toggle-status', ['App\Controllers\InstituteController', 'toggleStatus']);
    $router->post('/institutes/{id}/delete', ['App\Controllers\InstituteController', 'delete']);

    // Classes Management
    $router->get('/classes', ['App\Controllers\ClassController', 'index']);
    $router->get('/classes/create', ['App\Controllers\ClassController', 'create']);
    $router->get('/classes/rooms', ['App\Controllers\ClassController', 'rooms']);
    $router->post('/classes/create', ['App\Controllers\ClassController', 'store']);
    $router->get('/classes/{id}/edit', ['App\Controllers\ClassController', 'edit']);
    $router->post('/classes/{id}/edit', ['App\Controllers\ClassController', 'update']);
    $router->post('/classes/{id}/toggle-status', ['App\Controllers\ClassController', 'toggleStatus']);
$router->post('/classes/{id}/update-room', ['App\Controllers\ClassController', 'updateRoom']);
    $router->post('/classes/{id}/delete', ['App\Controllers\ClassController', 'delete']);

    // The teaching hub: material and quizzes both hang off a class, so
    // this is the one page that makes either reachable without knowing
    // a class id by heart.
    $router->get('/teaching', ['App\Controllers\TeachingController', 'index']);

    // Syllabus topics, past papers, learning gaps
    $router->get('/topics', ['App\Controllers\TopicController', 'index']);
    $router->post('/topics', ['App\Controllers\TopicController', 'store']);
    $router->post('/topics/{id}/edit', ['App\Controllers\TopicController', 'update']);
    $router->post('/topics/{id}/archive', ['App\Controllers\TopicController', 'archive']);
    $router->get('/past-papers', ['App\Controllers\PastPaperController', 'index']);
    $router->get('/past-papers/create', ['App\Controllers\PastPaperController', 'create']);
    $router->post('/past-papers', ['App\Controllers\PastPaperController', 'store']);
    $router->post('/past-papers/{id}/status', ['App\Controllers\PastPaperController', 'status']);
    $router->get('/past-papers/{id}/file/{which}', ['App\Controllers\PastPaperController', 'file']);
    $router->get('/my/papers', ['App\Controllers\PastPaperController', 'mine']);
    $router->get('/classes/{classId}/learning-gaps', ['App\Controllers\LearningGapController', 'show']);
    $router->get('/classes/{classId}/progress', ['App\Controllers\ClassProgressController', 'show']);

    // ---- LMS: class material ----
    // Nested under the class because material has no meaning apart from
    // one, and the class id in the path is what the ownership check reads.
    $router->get('/classes/{classId}/materials', ['App\Controllers\MaterialController', 'index']);
    $router->get('/classes/{classId}/materials/create', ['App\Controllers\MaterialController', 'create']);
    $router->post('/classes/{classId}/materials/create', ['App\Controllers\MaterialController', 'store']);
    $router->post('/classes/{classId}/materials/{id}/archive', ['App\Controllers\MaterialController', 'archive']);
    // Class Recordings -- kept separate from materials/homework/quizzes,
    // per the client's explicit request; same class/branch/grade targeting.
    $router->get('/classes/{classId}/recordings', ['App\Controllers\RecordingController', 'index']);
    $router->get('/classes/{classId}/recordings/create', ['App\Controllers\RecordingController', 'create']);
    $router->post('/classes/{classId}/recordings/create', ['App\Controllers\RecordingController', 'store']);
    $router->post('/classes/{classId}/recordings/{id}/publish', ['App\Controllers\RecordingController', 'publish']);
    $router->post('/classes/{classId}/recordings/{id}/archive', ['App\Controllers\RecordingController', 'archive']);

    // Downloads are flat: the file is served by its own id, and the
    // controller re-derives the class so the link cannot assert one.
    $router->get('/materials/{id}/download', ['App\Controllers\MaterialController', 'download']);

    // ---- LMS: quizzes (teacher side) ----
    $router->get('/classes/{classId}/quizzes', ['App\Controllers\QuizController', 'index']);
    $router->get('/classes/{classId}/quizzes/create', ['App\Controllers\QuizController', 'create']);
    $router->post('/classes/{classId}/quizzes/create', ['App\Controllers\QuizController', 'store']);
    $router->get('/classes/{classId}/quizzes/{id}', ['App\Controllers\QuizController', 'show']);
    $router->get('/classes/{classId}/quizzes/{id}/results', ['App\Controllers\QuizController', 'results']);
    $router->post('/classes/{classId}/quizzes/{id}/questions', ['App\Controllers\QuizController', 'addQuestion']);
    $router->post('/classes/{classId}/quizzes/{id}/questions/{questionId}/delete', ['App\Controllers\QuizController', 'deleteQuestion']);
    $router->post('/classes/{classId}/quizzes/{id}/publish', ['App\Controllers\QuizController', 'publish']);

    // Money and marks across every branch, for whoever owns the place.
    // Above the coordinator line on purpose: a branch coordinator has no
    // business reading another branch's takings.
    $router->get('/reports/insights', ['App\Controllers\InsightsController', 'index']);

    // The calendar. Class times are read from each class's own schedule
    // and quizzes from their open and close times, so neither is stored
    // twice; only one-off entries (tests, exams, cancellations) are
    // written here. Role checks live in the controller because the three
    // audiences differ: staff see every branch, a student only their own
    // enrolled classes, a parent only their child's.
    $router->get('/calendar', ['App\Controllers\CalendarController', 'index']);
    $router->post('/calendar/events', ['App\Controllers\CalendarController', 'store']);
    $router->post('/calendar/events/{id}/cancel', ['App\Controllers\CalendarController', 'cancel']);
    $router->post('/calendar/reminders/run', ['App\Controllers\CalendarController', 'runReminders']);
    $router->get('/my/calendar', ['App\Controllers\CalendarController', 'mine']);

    // A student's own home page, instead of the institute dashboard.
    $router->get('/my/home', ['App\Controllers\StudentHomeController', 'index']);

    // A student's own learning goals (up to 3), and the goals a teacher
    // sets for a student from the student's page. Progress is always
    // measured live from the records.
    $router->post('/my/goals', ['App\Controllers\GoalController', 'storeMine']);
    $router->post('/my/goals/{id}/remove', ['App\Controllers\GoalController', 'removeMine']);
    $router->post('/students/{id}/goals', ['App\Controllers\GoalController', 'storeForStudent']);
    $router->post('/students/{id}/goals/{goalId}/remove', ['App\Controllers\GoalController', 'removeForStudent']);

    // A student's profile, receipts and downloadable reports. Whose
    // record it is comes from the session; a parent gets their own
    // child's receipts and reports. No route here takes a student id.
    $router->get('/my/profile', ['App\Controllers\StudentProfileController', 'profile']);
    $router->get('/my/receipts/{id}', ['App\Controllers\StudentProfileController', 'receipt']);
    $router->get('/my/report', ['App\Controllers\StudentProfileController', 'report']);
    $router->get('/my/report.csv', ['App\Controllers\StudentProfileController', 'reportCsv']);

    // The study assistant. Answers come from the asker's own records;
    // who they are about is decided from the session, never the question.
    $router->post('/assistant/ask', ['App\Controllers\AssistantController', 'ask']);
    $router->get('/family/calendar', ['App\Controllers\CalendarController', 'family']);

    // ---- WhatsApp ----
    // Nothing is sent server-side: these compose the message, log the
    // attempt, and hand the teacher a click-to-chat link.
    $router->get('/whatsapp/reminders', ['App\Controllers\WhatsAppController', 'reminders']);
    $router->get('/whatsapp/log', ['App\Controllers\WhatsAppController', 'log']);
    $router->post('/whatsapp/queue', ['App\Controllers\WhatsAppController', 'queue']);
    $router->post('/whatsapp/confirm', ['App\Controllers\WhatsAppController', 'confirm']);

    // ---- Student portal ----
    // Everything under /my is about the student in the session. No route
    // here takes a student id, so there is no identifier to tamper with.
    $router->get('/my/classes', ['App\Controllers\StudentPortalController', 'classes']);
    $router->get('/my/classes/{classId}', ['App\Controllers\StudentPortalController', 'classDetail']);
    $router->get('/my/materials/{id}/open', ['App\Controllers\StudentPortalController', 'openMaterial']);
    $router->get('/my/recordings', ['App\Controllers\RecordingController', 'mine']);
    $router->get('/my/recordings/{id}/open', ['App\Controllers\RecordingController', 'open']);
    $router->get('/my/marks', ['App\Controllers\StudentPortalController', 'marks']);
    $router->get('/my/progress', ['App\Controllers\StudentPortalController', 'progress']);
    $router->get('/my/quizzes/{quizId}/sit', ['App\Controllers\StudentPortalController', 'sitQuiz']);
    $router->post('/my/quizzes/answer', ['App\Controllers\StudentPortalController', 'saveAnswer']);
    $router->post('/my/quizzes/submit', ['App\Controllers\StudentPortalController', 'submitQuiz']);
    $router->get('/my/quizzes/{attemptId}/result', ['App\Controllers\StudentPortalController', 'quizResult']);

    // ---- Parent portal ----
    // Likewise /family: the child is resolved from the guardian's own
    // row, never from the request.
    $router->get('/family', ['App\Controllers\ParentPortalController', 'overview']);
    $router->get('/family/marks', ['App\Controllers\ParentPortalController', 'marks']);
    $router->get('/family/fees', ['App\Controllers\ParentPortalController', 'fees']);
    $router->get('/family/account', ['App\Controllers\ParentPortalController', 'account']);
    $router->post('/family/account/photo', ['App\Controllers\ParentPortalController', 'uploadPhoto']);

    // ---- Homework ----
    // Teacher side is under the class; access is checked against the
    // class's teacher and the user's branch, never the URL alone.
    $router->get('/classes/{classId}/assignments', ['App\Controllers\AssignmentController', 'index']);
    $router->get('/classes/{classId}/assignments/create', ['App\Controllers\AssignmentController', 'create']);
    $router->post('/classes/{classId}/assignments/create', ['App\Controllers\AssignmentController', 'store']);
    $router->get('/classes/{classId}/assignments/{id}', ['App\Controllers\AssignmentController', 'show']);
    $router->post('/classes/{classId}/assignments/{id}/publish', ['App\Controllers\AssignmentController', 'publish']);
    $router->post('/classes/{classId}/assignments/{id}/archive', ['App\Controllers\AssignmentController', 'archive']);
    $router->post('/classes/{classId}/assignments/{id}/mark', ['App\Controllers\AssignmentController', 'mark']);
    $router->get('/assignments/submissions/{id}/file', ['App\Controllers\AssignmentController', 'file']);
    $router->get('/my/assignments', ['App\Controllers\AssignmentController', 'mine']);
    $router->get('/my/assignments/{id}', ['App\Controllers\AssignmentController', 'sit']);
    $router->post('/my/assignments/{id}/submit', ['App\Controllers\AssignmentController', 'submit']);
    $router->get('/family/assignments', ['App\Controllers\AssignmentController', 'family']);
    $router->get('/family/recordings', ['App\Controllers\RecordingController', 'family']);

    // ---- Questions to the teacher, and support requests ----
    $router->get('/my/help', ['App\Controllers\HelpController', 'mine']);
    $router->post('/my/help', ['App\Controllers\HelpController', 'create']);
    $router->get('/my/help/{id}', ['App\Controllers\HelpController', 'showMine']);
    $router->post('/my/help/{id}/reply', ['App\Controllers\HelpController', 'replyMine']);
    $router->post('/my/help/{id}/resolve', ['App\Controllers\HelpController', 'resolveMine']);
    $router->get('/family/help', ['App\Controllers\HelpController', 'mine']);
    $router->post('/family/help', ['App\Controllers\HelpController', 'create']);
    $router->get('/family/help/{id}', ['App\Controllers\HelpController', 'showMine']);
    $router->post('/family/help/{id}/reply', ['App\Controllers\HelpController', 'replyMine']);
    $router->post('/family/help/{id}/resolve', ['App\Controllers\HelpController', 'resolveMine']);
    $router->get('/help', ['App\Controllers\HelpController', 'inbox']);
    $router->get('/help/messages/{id}/file', ['App\Controllers\HelpController', 'file']);
    $router->get('/help/{id}', ['App\Controllers\HelpController', 'show']);
    $router->post('/help/{id}/reply', ['App\Controllers\HelpController', 'reply']);
    $router->post('/help/{id}/status', ['App\Controllers\HelpController', 'status']);

    // ---- Notice board ----
    $router->get('/notices', ['App\Controllers\NoticeController', 'index']);
    $router->get('/notices/create', ['App\Controllers\NoticeController', 'create']);
    $router->post('/notices/create', ['App\Controllers\NoticeController', 'store']);
    $router->post('/notices/{id}/publish', ['App\Controllers\NoticeController', 'publish']);
    $router->post('/notices/{id}/archive', ['App\Controllers\NoticeController', 'archive']);
    $router->get('/my/notices', ['App\Controllers\NoticeController', 'mine']);
    $router->get('/family/notices', ['App\Controllers\NoticeController', 'mine']);

    // Seminars & Events -- same branch/grade/class targeting idea as
    // notices, plus an event date, a venue/online link and optional
    // registration.
    $router->get('/seminars', ['App\Controllers\SeminarController', 'index']);
    $router->get('/seminars/create', ['App\Controllers\SeminarController', 'create']);
    $router->post('/seminars/create', ['App\Controllers\SeminarController', 'store']);
    $router->get('/seminars/{id}', ['App\Controllers\SeminarController', 'show']);
    $router->get('/seminars/{id}/edit', ['App\Controllers\SeminarController', 'edit']);
    $router->post('/seminars/{id}/edit', ['App\Controllers\SeminarController', 'update']);
    $router->get('/seminars/{id}/registrants', ['App\Controllers\SeminarController', 'registrants']);
    $router->post('/seminars/{id}/attendance', ['App\Controllers\SeminarController', 'markAttendance']);
    $router->post('/seminars/{id}/publish', ['App\Controllers\SeminarController', 'publish']);
    $router->post('/seminars/{id}/cancel', ['App\Controllers\SeminarController', 'cancel']);
    $router->post('/seminars/{id}/delete', ['App\Controllers\SeminarController', 'delete']);
    $router->get('/my/seminars', ['App\Controllers\SeminarController', 'mine']);
    $router->post('/my/seminars/{id}/register', ['App\Controllers\SeminarController', 'register']);
    $router->post('/my/seminars/{id}/unregister', ['App\Controllers\SeminarController', 'unregister']);
    $router->get('/family/seminars', ['App\Controllers\SeminarController', 'mine']);
    $router->post('/family/seminars/{id}/register', ['App\Controllers\SeminarController', 'register']);
    $router->post('/family/seminars/{id}/unregister', ['App\Controllers\SeminarController', 'unregister']);

    // ---- Downloads (past papers etc.): super admin or a teacher ----
    $router->get('/downloads/manage', ['App\Controllers\DownloadController', 'manage']);
    $router->post('/downloads/manage', ['App\Controllers\DownloadController', 'store']);
    $router->post('/downloads/{id}/update', ['App\Controllers\DownloadController', 'update']);
    $router->post('/downloads/{id}/toggle', ['App\Controllers\DownloadController', 'toggle']);
    $router->post('/downloads/{id}/delete', ['App\Controllers\DownloadController', 'delete']);

    // ---- Inquiries from the public website ----
    $router->get('/inquiries', ['App\Controllers\InquiryController', 'index']);
    $router->post('/inquiries/{id}/status', ['App\Controllers\InquiryController', 'status']);
    $router->post('/inquiries/{id}/approve', ['App\Controllers\InquiryController', 'approve']);

    // ---- Enrolment requests (self-service enrol + bank slip from the public site) ----
    $router->get('/enrollment-requests', ['App\Controllers\EnrollmentController', 'queue']);
    $router->post('/enrollment-requests/{id}/approve', ['App\Controllers\EnrollmentController', 'approve']);
    $router->post('/enrollment-requests/{id}/reject', ['App\Controllers\EnrollmentController', 'reject']);
    $router->get('/enrollment-requests/{id}/slip', ['App\Controllers\EnrollmentController', 'slipFile']);

    // Students
    $router->get('/students', ['App\Controllers\StudentController', 'index']);
    $router->get('/students/create', ['App\Controllers\StudentController', 'create']);
    $router->post('/students/create', ['App\Controllers\StudentController', 'store']);
    $router->post('/students', ['App\Controllers\StudentController', 'store']);
    $router->get('/students/{id}', ['App\Controllers\StudentController', 'show']);
    $router->get('/students/{id}/edit', ['App\Controllers\StudentController', 'edit']);
    $router->post('/students/{id}/edit', ['App\Controllers\StudentController', 'update']);
    $router->post('/students/{id}', ['App\Controllers\StudentController', 'update']);
    $router->post('/students/{id}/delete', ['App\Controllers\StudentController', 'delete']);
    $router->post('/students/{id}/deactivate', ['App\Controllers\StudentController', 'deactivate']);
    $router->post('/students/{id}/reactivate', ['App\Controllers\StudentController', 'reactivate']);

    // Payment slips (bank deposits). Declared before /payments/{studentId}
    // so "slips" is never read as a student id.
    $router->get('/payments/slips', ['App\Controllers\PaymentSlipController', 'queue']);
    $router->post('/payments/slips/{id}/approve', ['App\Controllers\PaymentSlipController', 'approve']);
    $router->post('/payments/slips/{id}/reject', ['App\Controllers\PaymentSlipController', 'reject']);
    $router->get('/payment-slips/{id}/file', ['App\Controllers\PaymentSlipController', 'file']);
    $router->get('/my/payments', ['App\Controllers\PaymentSlipController', 'mine']);
    $router->post('/my/payments/slip', ['App\Controllers\PaymentSlipController', 'upload']);
    $router->get('/family/payments', ['App\Controllers\PaymentSlipController', 'mine']);
    $router->post('/family/payments/slip', ['App\Controllers\PaymentSlipController', 'upload']);

    // Payments
    $router->get('/payments', ['App\Controllers\PaymentController', 'index']);
    $router->get('/payments/generate', ['App\Controllers\PaymentController', 'generateView']);
    $router->post('/payments/generate', ['App\Controllers\PaymentController', 'generateMonthly']);
    $router->post('/payments/generate-monthly', ['App\Controllers\PaymentController', 'generateMonthly']);
    $router->get('/payments/{studentId}', ['App\Controllers\PaymentController', 'studentPayments']);
    $router->post('/payments/{id}/mark-paid', ['App\Controllers\PaymentController', 'markPaidById']);
    $router->post('/payments/mark-paid', ['App\Controllers\PaymentController', 'markPaid']);

    // Workflows
    $router->get('/workflows', ['App\Controllers\WorkflowController', 'index']);
    $router->get('/workflows/history', ['App\Controllers\WorkflowController', 'history']);
    $router->post('/workflows/{id}/approve', ['App\Controllers\WorkflowController', 'approve']);
    $router->post('/workflows/{id}/reject', ['App\Controllers\WorkflowController', 'reject']);

    // Attendance & QR Terminal
    $router->get('/attendance', ['App\Controllers\AttendanceController', 'index']);
    $router->get('/attendance/qr', ['App\Controllers\AttendanceController', 'terminal']);
    $router->post('/attendance/scan', ['App\Controllers\AttendanceController', 'scan']);
    $router->post('/attendance/mark-manual', ['App\Controllers\AttendanceController', 'markManual']);
    $router->get('/attendance/register', ['App\Controllers\AttendanceController', 'register']);
    $router->post('/attendance/register', ['App\Controllers\AttendanceController', 'saveRegister']);

    // In-app Messages & Notifications
    $router->get('/messages', ['App\Controllers\MessageController', 'index']);
    $router->post('/messages/{id}/read', ['App\Controllers\MessageController', 'markRead']);
    $router->post('/messages/mark-all-read', ['App\Controllers\MessageController', 'markAllRead']);
    $router->post('/messages/send', ['App\Controllers\MessageController', 'send']);

    // Reports Hub & Exports
    $router->get('/reports', ['App\Controllers\ReportController', 'index']);
    $router->get('/reports/attendance', ['App\Controllers\ReportController', 'attendance']);
    $router->get('/reports/classes', ['App\Controllers\ReportController', 'classes']);
    $router->get('/reports/users', ['App\Controllers\ReportController', 'users']);
    $router->get('/reports/payments', ['App\Controllers\ReportController', 'payments']);
    $router->get('/reports/students', ['App\Controllers\ReportController', 'students']);

    // Audit & History
    $router->get('/audit', ['App\Controllers\AuditController', 'index']);
    $router->get('/audit-log', ['App\Controllers\AuditController', 'index']);
    $router->get('/audit/export', ['App\Controllers\AuditController', 'export']);
    $router->get('/audit/user/{id}', ['App\Controllers\AuditController', 'userHistory']);
    $router->get('/workflow-history', ['App\Controllers\AuditController', 'workflowHistory']);

    // Settings
    $router->get('/settings', ['App\Controllers\SettingsController', 'index']);
    $router->post('/settings', ['App\Controllers\SettingsController', 'update']);
    $router->get('/settings/deactivation', ['App\Controllers\SettingsController', 'deactivation']);
    $router->post('/settings/deactivation', ['App\Controllers\SettingsController', 'updateDeactivation']);
    $router->get('/settings/deletion', ['App\Controllers\SettingsController', 'deletion']);
    $router->post('/settings/deletion', ['App\Controllers\SettingsController', 'updateDeletion']);
    $router->get('/settings/dashboard-items', ['App\Controllers\SettingsController', 'dashboardItems']);
    $router->post('/settings/dashboard-items', ['App\Controllers\SettingsController', 'updateDashboardItems']);
    $router->get('/settings/feature-visibility', ['App\Controllers\SettingsController', 'featureVisibility']);
    $router->post('/settings/feature-visibility', ['App\Controllers\SettingsController', 'updateFeatureVisibility']);
    $router->get('/site-builder', ['App\Controllers\SettingsController', 'siteBuilder']);
    $router->get('/settings/site-content', ['App\Controllers\SettingsController', 'siteContent']);
    $router->post('/settings/site-content', ['App\Controllers\SettingsController', 'updateSiteContent']);
    $router->post('/settings/site-content/reset-look', ['App\Controllers\SettingsController', 'resetLook']);
    $router->post('/settings/site-content/preview', ['App\Controllers\SettingsController', 'previewLook']);
    $router->get('/settings/site-content/preview/stop', ['App\Controllers\SettingsController', 'stopPreview']);
    $router->get('/settings/sms', ['App\Controllers\SettingsController', 'sms']);
    $router->post('/settings/sms', ['App\Controllers\SettingsController', 'updateSms']);
    $router->post('/settings/sms/test', ['App\Controllers\SettingsController', 'testSms']);
    $router->get('/settings/email', ['App\Controllers\SettingsController', 'email']);
    $router->post('/settings/email', ['App\Controllers\SettingsController', 'updateEmail']);
    $router->post('/settings/email/test', ['App\Controllers\SettingsController', 'testEmail']);
    $router->get('/settings/whatsapp', ['App\Controllers\SettingsController', 'whatsapp']);
    $router->post('/settings/whatsapp', ['App\Controllers\SettingsController', 'updateWhatsapp']);
    $router->post('/settings/whatsapp/test', ['App\Controllers\SettingsController', 'testWhatsapp']);
    $router->get('/settings/translations', ['App\Controllers\TranslationController', 'index']);
    $router->post('/settings/translations/update', ['App\Controllers\TranslationController', 'update']);
    $router->get('/settings/ads', ['App\Controllers\SettingsController', 'ads']);
    $router->post('/settings/ads', ['App\Controllers\SettingsController', 'storeAd']);
    $router->post('/settings/ads/{id}/update', ['App\Controllers\SettingsController', 'updateAd']);
    $router->post('/settings/ads/{id}/toggle', ['App\Controllers\SettingsController', 'toggleAd']);
    $router->post('/settings/ads/{id}/delete', ['App\Controllers\SettingsController', 'deleteAd']);

    $router->get('/settings/hero-media', ['App\Controllers\SettingsController', 'heroMedia']);
    $router->post('/settings/hero-media', ['App\Controllers\SettingsController', 'storeHeroMedia']);
    $router->post('/settings/hero-media/{id}/update', ['App\Controllers\SettingsController', 'updateHeroMedia']);
    $router->post('/settings/hero-media/{id}/toggle', ['App\Controllers\SettingsController', 'toggleHeroMedia']);
    $router->post('/settings/hero-media/{id}/delete', ['App\Controllers\SettingsController', 'deleteHeroMedia']);

    // API-style endpoints
    $router->get('/api/classes-by-institute/{id}', ['App\Controllers\ApiController', 'classesByInstitute']);
});

return $router;
