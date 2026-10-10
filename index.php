<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config.php';

const COURSES = [
    'BS Information Technology',
    'BS Computer Science',
    'BS Information Systems',
    'BS Business Administration',
    'BS Accountancy',
    'BA Communication',
    'BS Psychology',
];

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectToList(): never
{
    header('Location: index.php');
    exit;
}

function validDate(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$statuses = ['Enrolled', 'Pending', 'Graduated'];
$genders = ['Female', 'Male', 'Prefer not to say'];
$csrfToken = $_SESSION['csrf_token'];
$errors = [];
$formValues = [];
$formOpen = false;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        http_response_code(403);
        exit('Your session token is invalid or expired. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            http_response_code(400);
            exit('A valid student ID is required.');
        }

        $statement = $pdo->prepare('DELETE FROM students WHERE id = :id');
        $statement->execute(['id' => $id]);
        $_SESSION['flash'] = $statement->rowCount() === 1
            ? ['type' => 'success', 'message' => 'Student record deleted.']
            : ['type' => 'error', 'message' => 'That student record could not be found.'];
        redirectToList();
    }

    if ($action === 'create' || $action === 'update') {
        $formOpen = true;
        $formValues = [
            'id' => trim((string) ($_POST['id'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'date_of_birth' => trim((string) ($_POST['date_of_birth'] ?? '')),
            'course' => trim((string) ($_POST['course'] ?? '')),
            'year_level' => trim((string) ($_POST['year_level'] ?? '')),
            'enrollment_date' => trim((string) ($_POST['enrollment_date'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
        ];

        if ($action === 'update' && (!ctype_digit($formValues['id']) || (int) $formValues['id'] < 1)) {
            http_response_code(400);
            exit('A valid student ID is required.');
        }

        foreach (['first_name', 'last_name', 'email', 'phone', 'gender', 'date_of_birth', 'course', 'year_level', 'enrollment_date', 'status'] as $field) {
            if ($formValues[$field] === '') {
                $errors[] = 'Please complete all required fields.';
                break;
            }
        }

        foreach (['first_name' => 80, 'last_name' => 80, 'email' => 190, 'phone' => 30, 'course' => 120, 'address' => 255] as $field => $maxLength) {
            if (strlen($formValues[$field]) > $maxLength) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be {$maxLength} characters or fewer.";
            }
        }

        if (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($formValues['gender'], $genders, true)) {
            $errors[] = 'Choose a valid gender option.';
        }
        if (!in_array($formValues['course'], COURSES, true)) {
            $errors[] = 'Choose a course from the list.';
        }
        if (!in_array($formValues['status'], $statuses, true)) {
            $errors[] = 'Choose a valid enrollment status.';
        }
        if (!ctype_digit($formValues['year_level']) || (int) $formValues['year_level'] < 1 || (int) $formValues['year_level'] > 6) {
            $errors[] = 'Year level must be between 1 and 6.';
        }
        if (!validDate($formValues['date_of_birth']) || !validDate($formValues['enrollment_date'])) {
            $errors[] = 'Enter valid dates for date of birth and enrollment date.';
        } elseif ($formValues['date_of_birth'] >= date('Y-m-d')) {
            $errors[] = 'Date of birth must be in the past.';
        }

        if ($errors === []) {
            $duplicateQuery = 'SELECT id FROM students WHERE email = :email';
            $duplicateParameters = ['email' => $formValues['email']];
            if ($action === 'update') {
                $duplicateQuery .= ' AND id <> :id';
                $duplicateParameters['id'] = (int) $formValues['id'];
            }
            $duplicateStatement = $pdo->prepare($duplicateQuery);
            $duplicateStatement->execute($duplicateParameters);

            if ($duplicateStatement->fetch()) {
                $errors[] = 'A student with this email address already exists.';
            } else {
                $student = [
                    'first_name' => $formValues['first_name'],
                    'last_name' => $formValues['last_name'],
                    'email' => $formValues['email'],
                    'phone' => $formValues['phone'],
                    'gender' => $formValues['gender'],
                    'date_of_birth' => $formValues['date_of_birth'],
                    'course' => $formValues['course'],
                    'year_level' => (int) $formValues['year_level'],
                    'enrollment_date' => $formValues['enrollment_date'],
                    'status' => $formValues['status'],
                    'address' => $formValues['address'],
                ];

                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO students (first_name, last_name, email, phone, gender, date_of_birth, course, year_level, enrollment_date, status, address)
                         VALUES (:first_name, :last_name, :email, :phone, :gender, :date_of_birth, :course, :year_level, :enrollment_date, :status, :address)'
                    );
                    $statement->execute($student);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student enrolled successfully.'];
                } else {
                    $student['id'] = (int) $formValues['id'];
                    $statement = $pdo->prepare(
                        'UPDATE students
                         SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone,
                             gender = :gender, date_of_birth = :date_of_birth, course = :course,
                             year_level = :year_level, enrollment_date = :enrollment_date,
                             status = :status, address = :address
                         WHERE id = :id'
                    );
                    $statement->execute($student);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student record updated successfully.'];
                }

                redirectToList();
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page !== null ? max(1, $page) : 1;
$perPage = 8;
$where = [];
$parameters = [];

if ($search !== '') {
    $where[] = '(first_name LIKE :search_first OR last_name LIKE :search_last OR email LIKE :search_email OR course LIKE :search_course)';
    $searchValue = '%' . $search . '%';
    $parameters['search_first'] = $searchValue;
    $parameters['search_last'] = $searchValue;
    $parameters['search_email'] = $searchValue;
    $parameters['search_course'] = $searchValue;
}
if (in_array($statusFilter, $statuses, true)) {
    $where[] = 'status = :status';
    $parameters['status'] = $statusFilter;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$countStatement = $pdo->prepare('SELECT COUNT(*) FROM students' . $whereSql);
$countStatement->execute($parameters);
$filteredCount = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($filteredCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$listStatement = $pdo->prepare(
    'SELECT id, first_name, last_name, email, phone, course, year_level, enrollment_date, status
     FROM students' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $name => $value) {
    $listStatement->bindValue(':' . $name, $value, PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$students = $listStatement->fetchAll();

$summary = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(status = 'Enrolled') AS enrolled,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'Graduated') AS graduated
     FROM students"
)->fetch();

$editStudent = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editId !== false && $editId !== null && $editId > 0) {
    $editStatement = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $editStatement->execute(['id' => $editId]);
    $editStudent = $editStatement->fetch() ?: null;
    if ($editStudent !== null) {
        $formOpen = true;
    } else {
        $flash = ['type' => 'error', 'message' => 'That student record could not be found.'];
    }
}

if ($editStudent !== null && !$formValues) {
    $formValues = $editStudent;
}
$formAction = isset($formValues['id']) && $formValues['id'] !== '' ? 'update' : 'create';
$shownFrom = $filteredCount === 0 ? 0 : $offset + 1;
$shownTo = min($offset + $perPage, $filteredCount);
$fullName = static fn (array $student): string => $student['first_name'] . ' ' . $student['last_name'];
$initials = static fn (array $student): string => strtoupper(
    substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)
);
$queryString = http_build_query(['q' => $search, 'status' => $statusFilter]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8fc">
    <title>Students | Northstar Enrollment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" aria-label="Main navigation">
        <a class="brand" href="index.php" aria-label="Northstar home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none"><path d="M16 3.5 19.1 13l9.4 3-9.4 3L16 28.5 12.9 19 3.5 16l9.4-3L16 3.5Z" fill="currentColor"/><circle cx="16" cy="16" r="3" fill="#fff"/></svg>
            </span>
            <span class="brand-copy"><strong>northstar</strong><span>ACADEMIC PORTAL</span></span>
        </a>

        <div class="nav-label">WORKSPACE</div>
        <nav class="nav-list">
            <a class="nav-item" href="index.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.4"/></svg>
                Overview
            </a>
            <a class="nav-item active" href="index.php" aria-current="page">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.4"/><path d="M3.5 20v-1.2a5.5 5.5 0 0 1 11 0V20H3.5Z"/><path d="M16 4.8a3.4 3.4 0 0 1 0 6.4m1 3.1a5.5 5.5 0 0 1 3.5 5.1V20h-3"/></svg>
                Students <span class="nav-count"><?= (int) $summary['total'] ?></span>
            </a>
            <a class="nav-item" href="#student-table">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h4"/></svg>
                Enrollment records
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="term-card">
                <span class="term-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="4" y="5.5" width="16" height="15" rx="2"/><path d="M8 3.5v4m8-4v4M4 10h16"/></svg>
                </span>
                <span class="term-label">CURRENT TERM</span>
                <strong>Academic Year 2026–27</strong>
                <span class="term-status"><i></i> Enrollment open</span>
            </div>
            <div class="profile-card">
                <span class="profile-avatar">AD</span>
                <span class="profile-copy"><strong>Admin Desk</strong><small>Registrar</small></span>
                <span class="profile-menu" aria-hidden="true">•••</span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumbs"><span>Workspace</span><span class="crumb-divider">/</span><strong>Students</strong></div>
            <div class="topbar-right">
                <span class="current-term">AY 2026–2027</span>
                <span class="topbar-divider"></span>
                <span class="topbar-date"><?= escape(date('D, M j, Y')) ?></span>
                <span class="notification-button" aria-label="Notifications">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4"/></svg>
                    <i></i>
                </span>
            </div>
        </header>

        <div class="page-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= escape($flash['type']) ?>" role="status">
                    <span><?= escape($flash['message']) ?></span>
                    <button class="alert-close" type="button" aria-label="Dismiss message">&times;</button>
                </div>
            <?php endif; ?>

            <section class="page-heading">
                <div>
                    <div class="eyebrow"><span></span> PEOPLE &amp; RECORDS</div>
                    <h1>Student directory</h1>
                    <p>Manage your student records and keep enrollment on track.</p>
                </div>
                <button class="button button-primary" type="button" data-open-form>
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12"/></svg>
                    Add student
                </button>
            </section>

            <section class="stats-grid" aria-label="Enrollment summary">
                <article class="stat-card stat-total">
                    <div class="stat-topline"><span class="stat-label">TOTAL STUDENTS</span><span class="stat-icon icon-indigo"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M3 20v-1.5a6 6 0 0 1 12 0V20H3Zm14-11a3.5 3.5 0 0 1 0 6.8m1 2a5 5 0 0 1 3 4.2"/></svg></span></div>
                    <div class="stat-number"><?= (int) $summary['total'] ?></div>
                    <div class="stat-caption"><span class="stat-dot dot-indigo"></span>Across all programs</div>
                </article>
                <article class="stat-card">
                    <div class="stat-topline"><span class="stat-label">CURRENTLY ENROLLED</span><span class="stat-icon icon-green"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.2 4L19 7"/></svg></span></div>
                    <div class="stat-number"><?= (int) ($summary['enrolled'] ?? 0) ?></div>
                    <div class="stat-caption"><span class="stat-dot dot-green"></span>Active this semester</div>
                </article>
                <article class="stat-card">
                    <div class="stat-topline"><span class="stat-label">AWAITING REVIEW</span><span class="stat-icon icon-amber"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.2 2"/></svg></span></div>
                    <div class="stat-number"><?= (int) ($summary['pending'] ?? 0) ?></div>
                    <div class="stat-caption"><span class="stat-dot dot-amber"></span>Pending enrollment</div>
                </article>
                <article class="stat-card">
                    <div class="stat-topline"><span class="stat-label">GRADUATED</span><span class="stat-icon icon-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.5 9 8.5-4 8.5 4-8.5 4-8.5-4Z"/><path d="M7 11v5c2.5 2.5 7.5 2.5 10 0v-5m3-2v6"/></svg></span></div>
                    <div class="stat-number"><?= (int) ($summary['graduated'] ?? 0) ?></div>
                    <div class="stat-caption"><span class="stat-dot dot-blue"></span>Alumni community</div>
                </article>
            </section>

            <section class="directory-panel" id="student-table">
                <div class="panel-heading">
                    <div>
                        <h2>All students <span class="heading-count"><?= (int) $filteredCount ?></span></h2>
                        <p>View and manage your enrolled student records.</p>
                    </div>
                    <span class="updated-label"><span></span> Directory up to date</span>
                </div>

                <form class="toolbar" method="get" action="index.php">
                    <label class="search-box">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.8" cy="8.8" r="5.8"/><path d="m13 13 4 4"/></svg>
                        <input type="search" name="q" placeholder="Search students, email, course..." value="<?= escape($search) ?>" aria-label="Search students">
                    </label>
                    <label class="filter-select">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M5.5 10h9M8 15h4"/></svg>
                        <select name="status" aria-label="Filter by status">
                            <option value="">All statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= escape($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= escape($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
                    </label>
                    <button class="button button-filter" type="submit">Filter</button>
                    <?php if ($search !== '' || $statusFilter !== ''): ?>
                        <a class="clear-filter" href="index.php">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>STUDENT</th><th>STUDENT ID</th><th>COURSE &amp; YEAR</th><th>ENROLLED ON</th><th>STATUS</th><th><span class="visually-hidden">Actions</span></th></tr>
                        </thead>
                        <tbody>
                        <?php if ($students === []): ?>
                            <tr><td class="empty-cell" colspan="6">
                                <span class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5M8 10.5h5"/></svg></span>
                                <strong>No students found</strong>
                                <span>Try another search or add a new student to your directory.</span>
                                <?php if ($search === '' && $statusFilter === ''): ?><button class="text-action" type="button" data-open-form>Add your first student</button><?php endif; ?>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td>
                                        <div class="student-cell">
                                            <span class="student-avatar avatar-<?= $index % 5 ?>"><?= escape($initials($student)) ?></span>
                                            <span class="student-copy"><strong><?= escape($fullName($student)) ?></strong><span><?= escape($student['email']) ?></span></span>
                                        </div>
                                    </td>
                                    <td><span class="student-id">STU-<?= str_pad((string) $student['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                                    <td><span class="course-name"><?= escape($student['course']) ?></span><span class="year-label">Year <?= (int) $student['year_level'] ?></span></td>
                                    <td class="date-cell"><?= escape(date('M j, Y', strtotime($student['enrollment_date']))) ?></td>
                                    <td><span class="status-badge badge-<?= strtolower(escape($student['status'])) ?>"><i></i><?= escape($student['status']) ?></span></td>
                                    <td class="actions-cell">
                                        <a class="icon-action" href="index.php?edit=<?= (int) $student['id'] ?>" aria-label="Edit <?= escape($fullName($student)) ?>" title="Edit student">
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m13.8 3.8 2.4 2.4M3.5 16.5l3.7-.8L16 6.9a1.7 1.7 0 0 0-2.4-2.4l-8.8 8.8-.8 3.2Z"/></svg>
                                        </a>
                                        <form method="post" action="index.php" class="delete-form" data-student-name="<?= escape($fullName($student)) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                            <button class="icon-action delete-action" type="submit" aria-label="Delete <?= escape($fullName($student)) ?>" title="Delete student">
                                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4.5 6h11m-9.5 0 .6 10.5h6.8L14 6M8 6V4h4v2m-3 3v5m2-5v5"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <footer class="table-footer">
                    <span>Showing <strong><?= $shownFrom ?>–<?= $shownTo ?></strong> of <strong><?= (int) $filteredCount ?></strong> students</span>
                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Student list pages">
                            <?php if ($page > 1): ?><a href="?<?= escape($queryString) ?>&amp;page=<?= $page - 1 ?>" aria-label="Previous page"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12.5 4-6 6 6 6"/></svg></a><?php else: ?><span class="page-disabled" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m12.5 4-6 6 6 6"/></svg></span><?php endif; ?>
                            <span class="page-indicator">Page <strong><?= $page ?></strong> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?><a href="?<?= escape($queryString) ?>&amp;page=<?= $page + 1 ?>" aria-label="Next page"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m7.5 4 6 6-6 6"/></svg></a><?php else: ?><span class="page-disabled" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m7.5 4 6 6-6 6"/></svg></span><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </footer>
            </section>
            <p class="page-note"><span class="note-star">✳</span> Your students, all in one place. <span>Enrollment management made simple.</span></p>
        </div>
    </main>
</div>

<div class="drawer-backdrop <?= $formOpen ? 'is-open' : '' ?>" data-close-form></div>
<aside class="student-drawer <?= $formOpen ? 'is-open' : '' ?>" id="student-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title" aria-hidden="<?= $formOpen ? 'false' : 'true' ?>">
    <div class="drawer-heading">
        <div>
            <span class="drawer-kicker"><?= $formAction === 'update' ? 'STUDENT RECORD' : 'STUDENT DIRECTORY' ?></span>
            <h2 id="drawer-title"><?= $formAction === 'update' ? 'Edit student' : 'Add a student' ?></h2>
            <p><?= $formAction === 'update' ? 'Update this student’s enrollment details.' : 'Add a new student to your enrollment records.' ?></p>
        </div>
        <button class="drawer-close" type="button" data-close-form aria-label="Close form">&times;</button>
    </div>
    <?php if ($errors !== []): ?>
        <div class="form-errors" role="alert">
            <strong>Please check the following:</strong>
            <ul><?php foreach (array_unique($errors) as $error): ?><li><?= escape($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form class="student-form" method="post" action="index.php">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <input type="hidden" name="action" value="<?= escape($formAction) ?>">
        <?php if ($formAction === 'update'): ?><input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>"><?php endif; ?>
        <div class="form-section-label"><span>01</span> PERSONAL INFORMATION</div>
        <div class="form-grid">
            <label class="form-field"><span>First name <i>*</i></span><input type="text" name="first_name" value="<?= escape((string) ($formValues['first_name'] ?? '')) ?>" maxlength="80" autocomplete="given-name" placeholder="e.g. Jordan" required></label>
            <label class="form-field"><span>Last name <i>*</i></span><input type="text" name="last_name" value="<?= escape((string) ($formValues['last_name'] ?? '')) ?>" maxlength="80" autocomplete="family-name" placeholder="e.g. Rivera" required></label>
            <label class="form-field form-full"><span>Email address <i>*</i></span><input type="email" name="email" value="<?= escape((string) ($formValues['email'] ?? '')) ?>" maxlength="190" autocomplete="email" placeholder="student@example.com" required></label>
            <label class="form-field"><span>Phone number <i>*</i></span><input type="tel" name="phone" value="<?= escape((string) ($formValues['phone'] ?? '')) ?>" maxlength="30" autocomplete="tel" placeholder="+63 917 000 0000" required></label>
            <label class="form-field"><span>Date of birth <i>*</i></span><input type="date" name="date_of_birth" value="<?= escape((string) ($formValues['date_of_birth'] ?? '')) ?>" required></label>
            <label class="form-field form-full"><span>Gender <i>*</i></span><select name="gender" required><option value="" disabled <?= empty($formValues['gender']) ? 'selected' : '' ?>>Select gender</option><?php foreach ($genders as $gender): ?><option value="<?= escape($gender) ?>" <?= ($formValues['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= escape($gender) ?></option><?php endforeach; ?></select></label>
        </div>

        <div class="form-section-label section-spaced"><span>02</span> ENROLLMENT DETAILS</div>
        <div class="form-grid">
            <label class="form-field form-full"><span>Course / program <i>*</i></span><select name="course" required><option value="" disabled <?= empty($formValues['course']) ? 'selected' : '' ?>>Select a course</option><?php foreach (COURSES as $course): ?><option value="<?= escape($course) ?>" <?= ($formValues['course'] ?? '') === $course ? 'selected' : '' ?>><?= escape($course) ?></option><?php endforeach; ?></select></label>
            <label class="form-field"><span>Year level <i>*</i></span><select name="year_level" required><option value="" disabled <?= empty($formValues['year_level']) ? 'selected' : '' ?>>Select year</option><?php for ($year = 1; $year <= 6; $year++): ?><option value="<?= $year ?>" <?= (string) ($formValues['year_level'] ?? '') === (string) $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endfor; ?></select></label>
            <label class="form-field"><span>Enrollment date <i>*</i></span><input type="date" name="enrollment_date" value="<?= escape((string) ($formValues['enrollment_date'] ?? date('Y-m-d'))) ?>" required></label>
            <label class="form-field form-full"><span>Enrollment status <i>*</i></span><select name="status" required><?php foreach ($statuses as $status): ?><option value="<?= escape($status) ?>" <?= ($formValues['status'] ?? 'Pending') === $status ? 'selected' : '' ?>><?= escape($status) ?></option><?php endforeach; ?></select></label>
            <label class="form-field form-full"><span>Address <small>Optional</small></span><input type="text" name="address" value="<?= escape((string) ($formValues['address'] ?? '')) ?>" maxlength="255" autocomplete="street-address" placeholder="City, province"></label>
        </div>
        <div class="drawer-footer">
            <button class="button button-cancel" type="button" data-close-form>Cancel</button>
            <button class="button button-primary" type="submit">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="<?= $formAction === 'update' ? 'm4 10 4 4 8-8' : 'M10 4v12M4 10h12' ?>"/></svg>
                <?= $formAction === 'update' ? 'Save changes' : 'Add student' ?>
            </button>
        </div>
    </form>
</aside>
</body>
</html>