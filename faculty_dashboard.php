<?php
session_start();

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'eduportal';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch faculty information
$faculty_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM faculty WHERE id = ?");
$stmt->execute([$faculty_id]);
$faculty_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch dashboard statistics
$student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$faculty_count = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();

// Check if courses table exists, if not use 0
try {
    $course_count = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
} catch(PDOException $e) {
    $course_count = 0;
}

// Check if announcements table exists
try {
    $announcement_count = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
} catch(PDOException $e) {
    $announcement_count = 0;
}

// Recent announcements
try {
    $recent_announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recent_announcements = [];
}

// Recent students for grading
$recent_students = $pdo->query("SELECT id, student_id, full_name, email FROM students ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Recent events
try {
    $recent_events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recent_events = [];
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grade'])) {
    $student_id = $_POST['student_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $grade = $_POST['grade'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if ($student_id && $course_id && $grade) {
        try {
            // Check if grades table exists, if not create it
            $pdo->exec("CREATE TABLE IF NOT EXISTS grades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                faculty_id INT NOT NULL,
                course_id VARCHAR(100),
                grade VARCHAR(10),
                remarks TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(id),
                FOREIGN KEY (faculty_id) REFERENCES faculty(id)
            )");
            
            $stmt = $pdo->prepare("INSERT INTO grades (student_id, faculty_id, course_id, grade, remarks) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $faculty_id, $course_id, $grade, $remarks]);
            $grade_success = true;
        } catch(PDOException $e) {
            $grade_error = $e->getMessage();
        }
    }
}

// Fetch existing grades for this faculty
try {
    $existing_grades = $pdo->prepare("SELECT g.*, s.student_id, s.full_name FROM grades g JOIN students s ON g.student_id = s.id WHERE g.faculty_id = ? ORDER BY g.created_at DESC LIMIT 10");
    $existing_grades->execute([$faculty_id]);
    $existing_grades = $existing_grades->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $existing_grades = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EduPortal — Faculty Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:ital,wght@0,300;0,400;0,600;0,700;1,400;1,600&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --forest:     #1e3a14;
      --olive:      #556b2f;
      --olive-mid:  #4a5e28;
      --olive-lt:   #6e8a3e;
      --cream:      #f5f5dc;
      --cream-dark: #e4e4c0;
      --gold:       #c6a961;
      --gold-lt:    #d9bf80;
      --gold-dk:    #a88a48;
      --ink:        #1a2610;
      --ink-mid:    #2d3d1a;
      --muted:      #7a8a60;
      --white:      #fafaf0;
    }

    html, body {
      height: 100%;
      font-family: 'Sora', sans-serif;
      background: var(--cream);
    }

    body::after {
      content: '';
      position: fixed;
      inset: 0;
      z-index: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
      background-size: 200px 200px;
      pointer-events: none;
      opacity: 0.4;
    }

    .dashboard-container {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: 260px 1fr;
      min-height: 100vh;
    }

    /* ═══ SIDEBAR ═══ */
    .sidebar {
      background: var(--forest);
      display: flex;
      flex-direction: column;
      padding: 1.5rem;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }

    .sidebar-logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid rgba(245,245,220,0.1);
    }

    .sidebar-logo .logo-mark {
      width: 40px; height: 40px;
      border-radius: 10px;
      background: var(--olive);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .sidebar-logo .logo-text {
      font-family: 'Crimson Pro', serif;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--cream);
      line-height: 1.2;
    }

    .sidebar-logo .logo-text small {
      display: block;
      font-family: 'Sora', sans-serif;
      font-size: 0.55rem;
      font-weight: 500;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: var(--gold);
      margin-top: 2px;
    }

    .nav-section {
      margin-bottom: 2rem;
    }

    .nav-label {
      font-size: 0.65rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 0.75rem;
      padding-left: 0.5rem;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 0.85rem;
      border-radius: 8px;
      color: rgba(245,245,220,0.7);
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 500;
      transition: all 0.2s;
      margin-bottom: 0.25rem;
    }

    .nav-item:hover {
      background: rgba(245,245,220,0.08);
      color: var(--cream);
    }

    .nav-item.active {
      background: var(--olive);
      color: var(--cream);
      box-shadow: 0 2px 8px rgba(85,107,47,0.3);
    }

    .nav-item svg {
      width: 18px; height: 18px;
      flex-shrink: 0;
    }

    .sidebar-footer {
      margin-top: auto;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(245,245,220,0.1);
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      background: rgba(245,245,220,0.05);
      border-radius: 10px;
      margin-bottom: 0.75rem;
    }

    .user-avatar {
      width: 36px; height: 36px;
      border-radius: 9px;
      background: var(--olive);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .user-details {
      flex: 1;
      min-width: 0;
    }

    .user-name {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--cream);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .user-role {
      font-size: 0.68rem;
      color: var(--gold);
    }

    .logout-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      padding: 0.65rem;
      border: 1px solid rgba(245,245,220,0.15);
      border-radius: 8px;
      background: transparent;
      color: rgba(245,245,220,0.6);
      font-family: 'Sora', sans-serif;
      font-size: 0.8rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .logout-btn:hover {
      background: rgba(198,169,97,0.15);
      border-color: var(--gold);
      color: var(--cream);
    }

    /* ═══ MAIN CONTENT ═══ */
    .main-content {
      padding: 2rem 2.5rem;
      overflow-y: auto;
    }

    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
    }

    .header-title {
      font-family: 'Crimson Pro', serif;
      font-size: 2rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.1;
    }

    .header-title em {
      font-style: italic;
      color: var(--olive);
    }

    .header-subtitle {
      font-size: 0.85rem;
      color: var(--muted);
      margin-top: 0.25rem;
    }

    .header-actions {
      display: flex;
      gap: 0.75rem;
    }

    .action-btn {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.65rem 1rem;
      border: 1.5px solid var(--cream-dark);
      border-radius: 9px;
      background: var(--white);
      color: var(--ink-mid);
      font-family: 'Sora', sans-serif;
      font-size: 0.82rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .action-btn:hover {
      border-color: var(--olive);
      background: rgba(85,107,47,0.05);
    }

    .action-btn.primary {
      background: var(--olive);
      border-color: var(--olive);
      color: var(--cream);
      box-shadow: 0 3px 12px rgba(85,107,47,0.25);
    }

    .action-btn.primary:hover {
      background: var(--olive-mid);
      box-shadow: 0 4px 16px rgba(85,107,47,0.35);
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: var(--white);
      border: 1.5px solid var(--cream-dark);
      border-radius: 14px;
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
      transition: all 0.25s;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: -50px; right: -50px;
      width: 120px; height: 120px;
      border-radius: 50%;
      border: 30px solid rgba(85,107,47,0.04);
      pointer-events: none;
    }

    .stat-card:hover {
      border-color: var(--olive);
      box-shadow: 0 4px 20px rgba(85,107,47,0.12);
      transform: translateY(-2px);
    }

    .stat-icon {
      width: 44px; height: 44px;
      border-radius: 11px;
      background: rgba(85,107,47,0.1);
      border: 1px solid rgba(85,107,47,0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      margin-bottom: 1rem;
    }

    .stat-value {
      font-family: 'Crimson Pro', serif;
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1;
      margin-bottom: 0.25rem;
    }

    .stat-label {
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .stat-trend {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      font-size: 0.72rem;
      font-weight: 500;
      margin-top: 0.5rem;
      padding: 0.25rem 0.5rem;
      border-radius: 100px;
    }

    .stat-trend.positive {
      background: rgba(85,107,47,0.1);
      color: var(--olive);
    }

    .stat-trend.neutral {
      background: rgba(198,169,97,0.1);
      color: var(--gold-dk);
    }

    /* Content Grid */
    .content-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    .panel {
      background: var(--white);
      border: 1.5px solid var(--cream-dark);
      border-radius: 14px;
      overflow: hidden;
    }

    .panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--cream-dark);
    }

    .panel-title {
      font-family: 'Crimson Pro', serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--ink);
    }

    .panel-action {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--olive);
      text-decoration: none;
      transition: color 0.2s;
    }

    .panel-action:hover {
      color: var(--gold-dk);
    }

    .panel-body {
      padding: 0;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
    }

    .table th {
      text-align: left;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--muted);
      padding: 0.85rem 1.5rem;
      border-bottom: 1px solid var(--cream-dark);
      background: rgba(245,245,220,0.3);
    }

    .table td {
      font-size: 0.82rem;
      color: var(--ink-mid);
      padding: 0.9rem 1.5rem;
      border-bottom: 1px solid var(--cream-dark);
    }

    .table tr:last-child td {
      border-bottom: none;
    }

    .table tr:hover td {
      background: rgba(85,107,47,0.03);
    }

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.6rem;
      border-radius: 100px;
      font-size: 0.68rem;
      font-weight: 600;
    }

    .badge.student {
      background: rgba(85,107,47,0.1);
      color: var(--olive);
    }

    .badge.faculty {
      background: rgba(198,169,97,0.15);
      color: var(--gold-dk);
    }

    .badge.announcement {
      background: rgba(85,107,47,0.15);
      color: var(--olive-mid);
    }

    .empty-state {
      padding: 2.5rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.85rem;
    }

    @media (max-width: 1024px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }
      .sidebar {
        display: none;
      }
    }

    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(26, 38, 16, 0.6);
      backdrop-filter: blur(4px);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.2s ease-out;
    }

    .modal-overlay.active {
      display: flex;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal {
      background: var(--white);
      border: 1.5px solid var(--cream-dark);
      border-radius: 16px;
      padding: 2rem;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(26, 38, 16, 0.2);
    }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--cream-dark);
    }

    .modal-title {
      font-family: 'Crimson Pro', serif;
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--ink);
    }

    .modal-close {
      width: 36px;
      height: 36px;
      border: none;
      background: rgba(85, 107, 47, 0.1);
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }

    .modal-close:hover {
      background: rgba(85, 107, 47, 0.2);
    }

    .modal-close svg {
      width: 20px;
      height: 20px;
      stroke: var(--olive);
    }

    .modal-footer {
      margin-top: 1.5rem;
      padding-top: 1rem;
      border-top: 1px solid var(--cream-dark);
      display: flex;
      gap: 0.75rem;
      justify-content: flex-end;
    }

    .modal-btn {
      padding: 0.65rem 1.25rem;
      border-radius: 8px;
      font-family: 'Sora', sans-serif;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .modal-btn.cancel {
      background: transparent;
      border: 1.5px solid var(--cream-dark);
      color: var(--ink-mid);
    }

    .modal-btn.cancel:hover {
      border-color: var(--olive);
      background: rgba(85, 107, 47, 0.05);
    }

    .modal-btn.confirm {
      background: var(--olive);
      border: 1.5px solid var(--olive);
      color: var(--cream);
    }

    .modal-btn.confirm:hover {
      background: var(--olive-mid);
      box-shadow: 0 3px 12px rgba(85, 107, 47, 0.3);
    }
  </style>
</head>
<body>

<div class="dashboard-container">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-mark">
        <svg width="20" height="20" viewBox="0 0 22 22" fill="none">
          <path d="M11 2.5C8 5.5 4.5 7 4.5 11.5C4.5 15.5 7.5 18.5 11 19.5C14.5 18.5 17.5 15.5 17.5 11.5C17.5 7 14 5.5 11 2.5Z" fill="#f5f5dc" opacity="0.9"/>
          <path d="M11 2.5V19.5" stroke="#556b2f" stroke-width="1.2" stroke-linecap="round"/>
          <path d="M11 10C9.5 8.5 7 7.5 4.5 7.5" stroke="#556b2f" stroke-width="1" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="logo-text">
        CPSU Portal
        <small>Faculty Panel</small>
      </div>
    </div>

    <nav class="nav-section">
      <div class="nav-label">Main Menu</div>
      <a href="faculty_dashboard.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        Dashboard
      </a>
      <a href="#grades" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
        </svg>
        Student Grades
      </a>
    </nav>

    <nav class="nav-section">
      <div class="nav-label">Information</div>
      <a href="announcements.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
        </svg>
        Announcements
      </a>
      <a href="events.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        Events
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar">👤</div>
        <div class="user-details">
          <div class="user-name"><?php echo htmlspecialchars($faculty_info['full_name'] ?? 'Faculty'); ?></div>
          <div class="user-role">Faculty Member</div>
        </div>
      </div>
      <button class="logout-btn" onclick="openLogoutModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sign Out
      </button>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="header">
      <div>
        <h1 class="header-title">Welcome back,<br/><em><?php echo htmlspecialchars($faculty_info['full_name'] ?? 'Faculty'); ?>.</em></h1>
        <p class="header-subtitle">Here's what's happening in your department today.</p>
      </div>
      <div class="header-actions">
        <button class="action-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Export Report
        </button>
      </div>
    </header>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">🎓</div>
        <div class="stat-value"><?php echo number_format($student_count); ?></div>
        <div class="stat-label">Students to Grade</div>
        <div class="stat-trend positive">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"/>
          </svg>
          Active
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-value"><?php echo count($existing_grades); ?></div>
        <div class="stat-label">Grades Submitted</div>
        <div class="stat-trend neutral">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Total
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📢</div>
        <div class="stat-value"><?php echo number_format($announcement_count); ?></div>
        <div class="stat-label">Announcements</div>
        <div class="stat-trend neutral">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          All
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value"><?php echo count($recent_events); ?></div>
        <div class="stat-label">Upcoming Events</div>
        <div class="stat-trend neutral">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Total
        </div>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
      <!-- Grade Submission Form -->
      <div class="panel" id="grades">
        <div class="panel-header">
          <h2 class="panel-title">Submit Student Grade</h2>
        </div>
        <div class="panel-body" style="padding: 1.5rem;">
          <?php if (isset($grade_success)): ?>
            <div style="background: rgba(85,107,47,0.1); color: var(--olive); padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem;">
              ✓ Grade submitted successfully!
            </div>
          <?php endif; ?>
          <?php if (isset($grade_error)): ?>
            <div style="background: #fee; color: #c33; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem;">
              Error: <?php echo htmlspecialchars($grade_error); ?>
            </div>
          <?php endif; ?>
          <form method="POST" action="faculty_dashboard.php#grades">
            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-mid); margin-bottom: 0.5rem;">Select Student</label>
              <select name="student_id" required style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--cream-dark); border-radius: 8px; font-family: 'Sora', sans-serif; font-size: 0.9rem; color: var(--ink-mid); background: var(--white);">
                <option value="">-- Select Student --</option>
                <?php foreach ($recent_students as $student): ?>
                  <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name'] . ' (' . $student['student_id'] . ')'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-mid); margin-bottom: 0.5rem;">Course ID</label>
              <input type="text" name="course_id" required placeholder="e.g., CS101" style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--cream-dark); border-radius: 8px; font-family: 'Sora', sans-serif; font-size: 0.9rem; color: var(--ink-mid); background: var(--white);">
            </div>
            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-mid); margin-bottom: 0.5rem;">Grade</label>
              <input type="text" name="grade" required placeholder="e.g., A, B+, 85" style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--cream-dark); border-radius: 8px; font-family: 'Sora', sans-serif; font-size: 0.9rem; color: var(--ink-mid); background: var(--white);">
            </div>
            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-mid); margin-bottom: 0.5rem;">Remarks (Optional)</label>
              <textarea name="remarks" rows="3" placeholder="Additional comments..." style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--cream-dark); border-radius: 8px; font-family: 'Sora', sans-serif; font-size: 0.9rem; color: var(--ink-mid); background: var(--white); resize: vertical;"></textarea>
            </div>
            <button type="submit" name="submit_grade" class="action-btn primary" style="width: 100%; justify-content: center;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              Submit Grade
            </button>
          </form>
        </div>
      </div>

      <!-- Recent Grades -->
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Recent Grades Submitted</h2>
        </div>
        <div class="panel-body">
          <?php if (count($existing_grades) > 0): ?>
          <table class="table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Course</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($existing_grades as $grade): ?>
              <tr>
                <td><?php echo htmlspecialchars($grade['full_name']); ?></td>
                <td><?php echo htmlspecialchars($grade['course_id'] ?? 'N/A'); ?></td>
                <td><span class="badge student"><?php echo htmlspecialchars($grade['grade']); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">No grades submitted yet</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Announcements & Events Section -->
    <div class="content-grid" style="margin-top: 1.5rem;">
      <!-- Recent Announcements -->
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Recent Announcements</h2>
          <a href="announcements.php" class="panel-action">View All →</a>
        </div>
        <div class="panel-body">
          <?php if (count($recent_announcements) > 0): ?>
          <table class="table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_announcements as $announcement): ?>
              <tr>
                <td><?php echo htmlspecialchars($announcement['title'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($announcement['created_at'] ?? 'now'))); ?></td>
                <td><span class="badge announcement"><?php echo htmlspecialchars($announcement['status'] ?? 'Active'); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">No announcements available</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Upcoming Events -->
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Upcoming Events</h2>
          <a href="events.php" class="panel-action">View All →</a>
        </div>
        <div class="panel-body">
          <?php if (count($recent_events) > 0): ?>
          <table class="table">
            <thead>
              <tr>
                <th>Event</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_events as $event): ?>
              <tr>
                <td><?php echo htmlspecialchars($event['title'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($event['event_date'] ?? 'now'))); ?></td>
                <td><span class="badge student"><?php echo htmlspecialchars($event['status'] ?? 'Upcoming'); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">No upcoming events</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

</div>

<!-- Logout Confirmation Modal -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Sign Out</h3>
      <button class="modal-close" onclick="closeLogoutModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div style="margin-bottom: 1.5rem;">
      <p style="color: var(--ink-mid); font-size: 0.9rem;">Are you sure you want to sign out?</p>
    </div>
    <div class="modal-footer">
      <button class="modal-btn cancel" onclick="closeLogoutModal()">Cancel</button>
      <a href="logout.php" class="modal-btn confirm">Sign Out</a>
    </div>
  </div>
</div>

<script>
function openLogoutModal() {
  const overlay = document.getElementById('logoutModal');
  overlay.classList.add('active');
}

function closeLogoutModal() {
  const overlay = document.getElementById('logoutModal');
  overlay.classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
  const overlay = document.getElementById('logoutModal');
  overlay.addEventListener('click', function(e) {
    if (e.target === overlay) {
      closeLogoutModal();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeLogoutModal();
    }
  });
});
</script>

</body>
</html>
