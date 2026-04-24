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

// Fetch student's enrolled courses
try {
    $stmt = $pdo->prepare("SELECT c.course_code, c.course_name, c.description, c.credits FROM courses c 
                          INNER JOIN enrollments e ON c.id = e.course_id 
                          WHERE e.student_id = ? ORDER BY c.course_name");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $courses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Courses - CPSU Portal</title>
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
      text-decoration: none;
    }

    .logout-btn:hover {
      background: rgba(198,169,97,0.15);
      border-color: var(--gold);
      color: var(--cream);
    }

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

    .header-subtitle {
      font-size: 0.85rem;
      color: var(--muted);
      margin-top: 0.25rem;
    }

    .courses-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }

    .course-card {
      background: var(--white);
      border: 1.5px solid var(--cream-dark);
      border-radius: 14px;
      padding: 1.5rem;
      transition: all 0.2s;
    }

    .course-card:hover {
      box-shadow: 0 4px 16px rgba(26, 38, 16, 0.1);
      transform: translateY(-2px);
    }

    .course-code {
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--olive);
      margin-bottom: 0.5rem;
    }

    .course-name {
      font-family: 'Crimson Pro', serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 0.5rem;
    }

    .course-description {
      font-size: 0.82rem;
      color: var(--ink-mid);
      line-height: 1.5;
      margin-bottom: 1rem;
    }

    .course-credits {
      font-size: 0.75rem;
      color: var(--muted);
    }

    .empty-state {
      padding: 3rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.9rem;
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
        <small>Student Panel</small>
      </div>
    </div>

    <nav class="nav-section">
      <div class="nav-label">Main Menu</div>
      <a href="student_dashboard.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        Dashboard
      </a>
      <a href="student_courses.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>
        </svg>
        My Courses
      </a>
      <a href="student_grades.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
        </svg>
        Grades
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar">👤</div>
        <div class="user-details">
          <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></div>
          <div class="user-role">Student</div>
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
        <h1 class="header-title">My Courses</h1>
        <p class="header-subtitle">View your enrolled courses and details.</p>
      </div>
    </header>

    <?php if (count($courses) > 0): ?>
    <div class="courses-grid">
      <?php foreach ($courses as $course): ?>
      <div class="course-card">
        <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
        <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
        <div class="course-description"><?php echo htmlspecialchars($course['description']); ?></div>
        <div class="course-credits"><?php echo $course['credits']; ?> Credits</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">No courses enrolled yet</div>
    <?php endif; ?>
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
