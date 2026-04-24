<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

// Fetch dashboard statistics
$student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$faculty_count = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();

// Check if courses table exists, if not use 0
try {
    $course_count = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
} catch(PDOException $e) {
    $course_count = 0;
}

// Check if announcements table exists, if not use 0
try {
    $announcement_count = $pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'pending'")->fetchColumn();
} catch(PDOException $e) {
    $announcement_count = 0;
}

// Analytics: Student enrollment trends (last 6 months)
$student_trends = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM students WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
    } catch(PDOException $e) {
        $count = 0;
    }
    $student_trends[] = ['month' => $month_name, 'count' => $count];
}

// Analytics: Department breakdown (from faculty)
$department_stats = [];
try {
    $dept_query = $pdo->query("SELECT department, COUNT(*) as count FROM faculty GROUP BY department ORDER BY count DESC");
    while ($row = $dept_query->fetch(PDO::FETCH_ASSOC)) {
        $department_stats[] = $row;
    }
} catch(PDOException $e) {
    $department_stats = [];
}

// Analytics: Recent activity timeline
$recent_activity = [];
try {
    // Get recent student registrations
    $students = $pdo->query("SELECT 'student' as type, full_name, created_at FROM students ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($students as $s) {
        $recent_activity[] = ['type' => 'student', 'name' => $s['full_name'], 'action' => 'registered', 'time' => $s['created_at']];
    }
    // Get recent faculty additions
    $faculty = $pdo->query("SELECT 'faculty' as type, full_name, created_at FROM faculty ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($faculty as $f) {
        $recent_activity[] = ['type' => 'faculty', 'name' => $f['full_name'], 'action' => 'added', 'time' => $f['created_at']];
    }
    // Get recent course additions
    $courses = $pdo->query("SELECT 'course' as type, course_name, created_at FROM courses ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($courses as $c) {
        $recent_activity[] = ['type' => 'course', 'name' => $c['course_name'], 'action' => 'created', 'time' => $c['created_at']];
    }
    // Sort by time
    usort($recent_activity, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    $recent_activity = array_slice($recent_activity, 0, 8);
} catch(PDOException $e) {
    $recent_activity = [];
}

// Calculate growth percentage (current month vs last month)
$current_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));
try {
    $current_students = $pdo->query("SELECT COUNT(*) FROM students WHERE DATE_FORMAT(created_at, '%Y-%m') = '$current_month'")->fetchColumn();
    $last_month_students = $pdo->query("SELECT COUNT(*) FROM students WHERE DATE_FORMAT(created_at, '%Y-%m') = '$last_month'")->fetchColumn();
    if ($last_month_students > 0) {
        $student_growth = round((($current_students - $last_month_students) / $last_month_students) * 100, 1);
    } else {
        $student_growth = $current_students > 0 ? 100 : 0;
    }
} catch(PDOException $e) {
    $student_growth = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EduPortal — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:ital,wght@0,300;0,400;0,600;0,700;1,400;1,600&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    .empty-state {
      padding: 2.5rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.85rem;
    }

    /* Analytics Section */
    .analytics-section {
      margin-top: 2rem;
    }

    .section-title {
      font-family: 'Crimson Pro', serif;
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 1.5rem;
    }

    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .chart-panel {
      min-height: 350px;
    }

    .chart-body {
      padding: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .analytics-bottom-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    .activity-timeline {
      padding: 1.5rem;
      max-height: 350px;
      overflow-y: auto;
    }

    .activity-item {
      display: flex;
      gap: 1rem;
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--cream-dark);
    }

    .activity-item:last-child {
      border-bottom: none;
    }

    .activity-icon {
      width: 36px;
      height: 36px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .activity-icon.student {
      background: rgba(85,107,47,0.1);
    }

    .activity-icon.faculty {
      background: rgba(198,169,97,0.15);
    }

    .activity-icon.course {
      background: rgba(85,107,47,0.15);
    }

    .activity-content {
      flex: 1;
    }

    .activity-text {
      font-size: 0.85rem;
      color: var(--ink-mid);
      margin-bottom: 0.25rem;
    }

    .activity-time {
      font-size: 0.72rem;
      color: var(--muted);
    }

    @media (max-width: 1024px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }
      .sidebar {
        display: none;
      }
      .content-grid {
        grid-template-columns: 1fr;
      }
      .charts-grid {
        grid-template-columns: 1fr;
      }
      .analytics-bottom-grid {
        grid-template-columns: 1fr;
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
      max-width: 400px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(26, 38, 16, 0.2);
      animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
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
        <small>Admin Panel</small>
      </div>
    </div>

    <nav class="nav-section">
      <div class="nav-label">Main Menu</div>
      <a href="admin_dashboard.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        Dashboard
      </a>
      <a href="students.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Students
      </a>
      <a href="faculty.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
        Faculty
      </a>
      <a href="courses.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>
        </svg>
        Courses
      </a>
    </nav>

    <nav class="nav-section">
      <div class="nav-label">Management</div>
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
      <a href="settings.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.47a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>
        </svg>
        Settings
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar">👤</div>
        <div class="user-details">
          <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></div>
          <div class="user-role">Administrator</div>
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
        <h1 class="header-title">Welcome back,<br/><em>Administrator.</em></h1>
        
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
        <div class="stat-label">Total Students</div>
        <div class="stat-trend <?php echo $student_growth >= 0 ? 'positive' : 'neutral'; ?>">
          <?php if ($student_growth >= 0): ?>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"/>
          </svg>
          +<?php echo $student_growth; ?>% this month
          <?php else: ?>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
          <?php echo $student_growth; ?>% this month
          <?php endif; ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">👩‍🏫</div>
        <div class="stat-value"><?php echo number_format($faculty_count); ?></div>
        <div class="stat-label">Faculty Members</div>
        <div class="stat-trend neutral">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Stable
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-value"><?php echo number_format($course_count); ?></div>
        <div class="stat-label">Active Courses</div>
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
        <div class="stat-label">Pending Announcements</div>
        <div class="stat-trend neutral">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Needs review
        </div>
      </div>
    </div>


    <!-- Analytics Section -->
    <div class="analytics-section">
      <h2 class="section-title">Analytics Overview</h2>
      
      <!-- Charts Grid -->
      <div class="charts-grid">
        <!-- Enrollment Trends Chart -->
        <div class="panel chart-panel">
          <div class="panel-header">
            <h2 class="panel-title">Student Enrollment Trends (6 Months)</h2>
          </div>
          <div class="panel-body chart-body">
            <canvas id="enrollmentChart"></canvas>
          </div>
        </div>

        <!-- Department Distribution Chart -->
        <div class="panel chart-panel">
          <div class="panel-header">
            <h2 class="panel-title">Faculty by Department</h2>
          </div>
          <div class="panel-body chart-body">
            <canvas id="departmentChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Course Enrollment & Activity Timeline -->
      <div class="analytics-bottom-grid">
        <!-- Course Enrollment -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Course Enrollment</h2>
          </div>
          <div class="panel-body">
            <?php 
            $course_enrollment = [];
            try {
                // Check if student_courses table exists
                $table_check = $pdo->query("SHOW TABLES LIKE 'student_courses'")->fetch();
                if ($table_check) {
                    $course_query = $pdo->query("SELECT c.course_name, c.course_code, COUNT(sc.student_id) as enrolled 
                                               FROM courses c 
                                               LEFT JOIN student_courses sc ON c.course_code = sc.course_code 
                                               GROUP BY c.course_code, c.course_name 
                                               ORDER BY enrolled DESC");
                    while ($row = $course_query->fetch(PDO::FETCH_ASSOC)) {
                        $course_enrollment[] = $row;
                    }
                } else {
                    // If student_courses doesn't exist, just show courses with 0 enrollment
                    $course_query = $pdo->query("SELECT course_name, course_code, 0 as enrolled FROM courses ORDER BY course_name");
                    while ($row = $course_query->fetch(PDO::FETCH_ASSOC)) {
                        $course_enrollment[] = $row;
                    }
                }
            } catch(PDOException $e) {
                $course_enrollment = [];
            }
            ?>
            <?php if (count($course_enrollment) > 0): ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Code</th>
                  <th>Enrolled</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($course_enrollment as $course): ?>
                <tr>
                  <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                  <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                  <td><?php echo number_format($course['enrolled']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">No enrollment data available</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Activity Timeline -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Recent Activity</h2>
          </div>
          <div class="panel-body activity-timeline">
            <?php if (count($recent_activity) > 0): ?>
            <?php foreach ($recent_activity as $activity): ?>
            <div class="activity-item">
              <div class="activity-icon <?php echo $activity['type']; ?>">
                <?php
                switch($activity['type']) {
                  case 'student': echo '🎓'; break;
                  case 'faculty': echo '👩‍🏫'; break;
                  case 'course': echo '📚'; break;
                  default: echo '📌';
                }
                ?>
              </div>
              <div class="activity-content">
                <div class="activity-text">
                  <strong><?php echo htmlspecialchars($activity['name']); ?></strong> 
                  <?php echo $activity['action']; ?>
                </div>
                <div class="activity-time"><?php echo date('M d, H:i', strtotime($activity['time'])); ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">No recent activity</div>
            <?php endif; ?>
          </div>
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

  // Initialize Charts
  initCharts();
});

function initCharts() {
  // Enrollment Trends Chart
  const enrollmentCtx = document.getElementById('enrollmentChart');
  if (enrollmentCtx) {
    const enrollmentData = <?php echo json_encode($student_trends); ?>;
    new Chart(enrollmentCtx, {
      type: 'line',
      data: {
        labels: enrollmentData.map(d => d.month),
        datasets: [{
          label: 'New Students',
          data: enrollmentData.map(d => d.count),
          borderColor: '#556b2f',
          backgroundColor: 'rgba(85, 107, 47, 0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#556b2f',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });
  }

  // Department Distribution Chart
  const departmentCtx = document.getElementById('departmentChart');
  if (departmentCtx) {
    const departmentData = <?php echo json_encode($department_stats); ?>;
    const colors = ['#556b2f', '#c6a961', '#6e8a3e', '#a88a48', '#4a5e28'];
    new Chart(departmentCtx, {
      type: 'doughnut',
      data: {
        labels: departmentData.map(d => d.department || 'Unassigned'),
        datasets: [{
          data: departmentData.map(d => d.count),
          backgroundColor: colors.slice(0, departmentData.length),
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 15,
              usePointStyle: true
            }
          }
        },
        cutout: '60%'
      }
    });
  }
}
</script>

</body>
</html>
