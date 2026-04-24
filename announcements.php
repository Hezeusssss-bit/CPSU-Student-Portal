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

// Handle announcement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        // Handle delete
        $id = $_POST['id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: announcements.php?deleted=1');
            exit;
        } catch(PDOException $e) {
            die('Database error: ' . $e->getMessage());
        }
    } elseif ($action === 'edit') {
        // Handle edit
        $id = $_POST['id'] ?? '';
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $priority = $_POST['priority'] ?? 'medium';
        $target_audience = $_POST['target_audience'] ?? 'all';
        $status = $_POST['status'] ?? 'pending';
        
        try {
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, priority = ?, target_audience = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $content, $priority, $target_audience, $status, $id]);
            header('Location: announcements.php?edited=1');
            exit;
        } catch(PDOException $e) {
            die('Database error: ' . $e->getMessage());
        }
    } elseif (isset($_POST['create_announcement'])) {
        // Handle create
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $priority = $_POST['priority'] ?? 'medium';
        $target_audience = $_POST['target_audience'] ?? 'all';
        
        if (!empty($title) && !empty($content)) {
            try {
                // Check if announcements table exists, if not create it
                $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL,
                    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
                    target_audience ENUM('all', 'students', 'faculty') DEFAULT 'all',
                    status ENUM('pending', 'published', 'archived') DEFAULT 'pending',
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, priority, target_audience, status, created_by) VALUES (?, ?, ?, ?, 'pending', ?)");
                $stmt->execute([$title, $content, $priority, $target_audience, $_SESSION['user_id'] ?? 1]);
                
                // Return success response
                echo json_encode(['success' => true, 'message' => 'Announcement created successfully']);
                exit;
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Title and content are required']);
            exit;
        }
    }
}

// Fetch announcements
try {
    $announcements = $pdo->query("SELECT id, title, content, priority, target_audience, status, created_at FROM announcements ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $announcements = [];
}

// Check for success message
$edited = isset($_GET['edited']) && $_GET['edited'] == '1';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';
?>
<script>
<?php if ($edited): ?>
  document.addEventListener('DOMContentLoaded', function() {
    const successDiv = document.createElement('div');
    successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4caf50; color: white; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 2000; animation: slideIn 0.3s ease-out;';
    successDiv.innerHTML = '✓ Announcement updated successfully!';
    document.body.appendChild(successDiv);
    setTimeout(() => {
      successDiv.style.opacity = '0';
      successDiv.style.transition = 'opacity 0.3s';
      setTimeout(() => successDiv.remove(), 300);
    }, 3000);
  });
<?php endif; ?>

<?php if ($deleted): ?>
  document.addEventListener('DOMContentLoaded', function() {
    const successDiv = document.createElement('div');
    successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #d32f2f; color: white; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 2000; animation: slideIn 0.3s ease-out;';
    successDiv.innerHTML = '✓ Announcement deleted successfully!';
    document.body.appendChild(successDiv);
    setTimeout(() => {
      successDiv.style.opacity = '0';
      successDiv.style.transition = 'opacity 0.3s';
      setTimeout(() => successDiv.remove(), 300);
    }, 3000);
  });
<?php endif; ?>
</script>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Announcements - CPSU Portal ADMIN PANEL</title>
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

    .panel-body {
      padding: 0;
    }

    .empty-state {
      padding: 2.5rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.85rem;
    }

    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(26, 38, 16, 0.6);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(4px);
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal {
      background: var(--white);
      border-radius: 16px;
      width: 100%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
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
      padding: 1.5rem 2rem;
      border-bottom: 1px solid var(--cream-dark);
    }

    .modal-title {
      font-family: 'Crimson Pro', serif;
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--ink);
    }

    .modal-close {
      background: transparent;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 0.5rem;
      border-radius: 8px;
      transition: all 0.2s;
    }

    .modal-close:hover {
      background: rgba(122, 138, 96, 0.1);
      color: var(--ink);
    }

    .modal-body {
      padding: 2rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-label {
      display: block;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 0.5rem;
    }

    .form-input,
    .form-textarea {
      width: 100%;
      padding: 0.85rem 1rem;
      border: 1.5px solid var(--cream-dark);
      border-radius: 10px;
      font-family: 'Sora', sans-serif;
      font-size: 0.9rem;
      color: var(--ink-mid);
      background: var(--white);
      transition: all 0.2s;
    }

    .form-input:focus,
    .form-textarea:focus {
      outline: none;
      border-color: var(--olive);
      box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
    }

    .form-textarea {
      min-height: 150px;
      resize: vertical;
    }

    .form-select {
      width: 100%;
      padding: 0.85rem 1rem;
      border: 1.5px solid var(--cream-dark);
      border-radius: 10px;
      font-family: 'Sora', sans-serif;
      font-size: 0.9rem;
      color: var(--ink-mid);
      background: var(--white);
      transition: all 0.2s;
      cursor: pointer;
    }

    .form-select:focus {
      outline: none;
      border-color: var(--olive);
      box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
    }

    .modal-footer {
      display: flex;
      gap: 0.75rem;
      justify-content: flex-end;
      padding: 1.5rem 2rem;
      border-top: 1px solid var(--cream-dark);
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
      vertical-align: middle;
    }

    .table tr:last-child td {
      border-bottom: none;
    }

    .table tr:hover td {
      background: rgba(85,107,47,0.03);
    }

    .table th:last-child,
    .table td:last-child {
      text-align: right;
    }

    .action-buttons {
      display: flex;
      gap: 0.5rem;
      justify-content: flex-end;
    }

    .edit-btn {
      padding: 0.4rem 0.75rem;
      font-size: 0.75rem;
      background-color: #f5f5dc;
      color: #8da346;
      border-color: #8da346;
      border-radius: 8px;
    }

    .edit-btn:hover {
      background-color: #e0e0c0;
    }

    .delete-btn {
      padding: 0.4rem 0.75rem;
      font-size: 0.75rem;
      background-color: #f5f5dc;
      color: #d32f2f;
      border-color: #d32f2f;
      border-radius: 8px;
    }

    .delete-btn:hover {
      background-color: #ffe0e0;
    }

    @media (max-width: 1024px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }
      .sidebar {
        display: none;
      }
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
      <a href="admin_dashboard.php" class="nav-item">
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
      <a href="announcements.php" class="nav-item active">
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
      <a href="logout.php" class="logout-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sign Out
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="header">
      <div>
        <h1 class="header-title">Announcements</h1>
        <p class="header-subtitle">Create and manage announcements for the portal.</p>
      </div>
      <div class="header-actions">
        <button class="action-btn primary" id="newAnnouncementBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Announcement
        </button>
      </div>
    </header>

    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">All Announcements</h2>
      </div>
      <div class="panel-body">
        <?php if (count($announcements) > 0): ?>
        <table class="table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Priority</th>
              <th>Audience</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($announcements as $announcement): ?>
            <tr>
              <td><?php echo htmlspecialchars($announcement['title']); ?></td>
              <td>
                <span class="badge <?php 
                  echo match($announcement['priority']) {
                    'high' => 'faculty',
                    'medium' => 'student',
                    'low' => 'course',
                    default => 'student'
                  };
                ?>">
                  <?php echo ucfirst($announcement['priority']); ?>
                </span>
              </td>
              <td><?php echo ucfirst($announcement['target_audience']); ?></td>
              <td>
                <span class="badge <?php 
                  echo match($announcement['status']) {
                    'published' => 'student',
                    'pending' => 'faculty',
                    'archived' => 'course',
                    default => 'student'
                  };
                ?>">
                  <?php echo ucfirst($announcement['status']); ?>
                </span>
              </td>
              <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
              <td>
                <div class="action-buttons">
                  <button class="action-btn edit-btn" onclick="openEditModal(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>', '<?php echo htmlspecialchars($announcement['content']); ?>', '<?php echo $announcement['priority']; ?>', '<?php echo $announcement['target_audience']; ?>', '<?php echo $announcement['status']; ?>')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"/>
                    </svg>
                    Edit
                  </button>
                  <button class="action-btn delete-btn" onclick="openDeleteModal(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="3 6 5 6 21 6"/>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No announcements yet. Click "New Announcement" to create one.</div>
        <?php endif; ?>
      </div>
    </div>
  </main>

</div>

<!-- Modal -->
<div class="modal-overlay" id="announcementModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">New Announcement</h3>
      <button class="modal-close" id="closeModalBtn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="announcementForm" method="POST" action="announcements.php">
        <div class="form-group">
          <label class="form-label" for="title">Announcement Type</label>
          <select id="title" name="title" class="form-select" required>
            <option value="">Select type...</option>
            <option value="General Notice">General Notice</option>
            <option value="Event Update">Event Update</option>
            <option value="Schedule Change">Schedule Change</option>
            <option value="Urgent Alert">Urgent Alert</option>
            <option value="Holiday Notice">Holiday Notice</option>
            <option value="Maintenance">Maintenance</option>
            <option value="Policy Update">Policy Update</option>
            <option value="Exam Schedule">Exam Schedule</option>
            <option value="Registration">Registration</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="content">Content</label>
          <textarea id="content" name="content" class="form-textarea" placeholder="Enter announcement content" required></textarea>
        </div>
        <div class="form-group">
          <label class="form-label" for="priority">Priority</label>
          <select id="priority" name="priority" class="form-select">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="target_audience">Target Audience</label>
          <select id="target_audience" name="target_audience" class="form-select">
            <option value="all">All Users</option>
            <option value="students">Students Only</option>
            <option value="faculty">Faculty Only</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="action-btn" id="cancelBtn">Cancel</button>
      <button class="action-btn primary" id="submitBtn">Create Announcement</button>
    </div>
  </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal-overlay" id="editAnnouncementModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Edit Announcement</h3>
      <button class="modal-close" onclick="closeEditModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="editAnnouncementForm" method="POST" action="announcements.php">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-group">
          <label class="form-label" for="edit_title">Announcement Type</label>
          <select id="edit_title" name="title" class="form-select" required>
            <option value="">Select type...</option>
            <option value="General Notice">General Notice</option>
            <option value="Event Update">Event Update</option>
            <option value="Schedule Change">Schedule Change</option>
            <option value="Urgent Alert">Urgent Alert</option>
            <option value="Holiday Notice">Holiday Notice</option>
            <option value="Maintenance">Maintenance</option>
            <option value="Policy Update">Policy Update</option>
            <option value="Exam Schedule">Exam Schedule</option>
            <option value="Registration">Registration</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_content">Content</label>
          <textarea id="edit_content" name="content" class="form-textarea" placeholder="Enter announcement content" required></textarea>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_priority">Priority</label>
          <select id="edit_priority" name="priority" class="form-select">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_target_audience">Target Audience</label>
          <select id="edit_target_audience" name="target_audience" class="form-select">
            <option value="all">All Users</option>
            <option value="students">Students Only</option>
            <option value="faculty">Faculty Only</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_status">Status</label>
          <select id="edit_status" name="status" class="form-select">
            <option value="pending">Pending</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="action-btn" onclick="closeEditModal()">Cancel</button>
      <button class="action-btn primary" onclick="document.getElementById('editAnnouncementForm').submit()">Update Announcement</button>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Delete Announcement</h3>
      <button class="modal-close" onclick="closeDeleteModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="color: var(--ink-mid); font-size: 0.9rem;">Are you sure you want to delete <strong id="deleteAnnouncementTitle"></strong>? This action cannot be undone.</p>
    </div>
    <div class="modal-footer">
      <button class="action-btn" onclick="closeDeleteModal()">Cancel</button>
      <form id="deleteForm" method="POST" action="announcements.php" style="display: inline;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
        <button type="submit" class="action-btn" style="background: #d32f2f; border-color: #d32f2f; color: white;">Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
  const modal = document.getElementById('announcementModal');
  const newAnnouncementBtn = document.getElementById('newAnnouncementBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  const submitBtn = document.getElementById('submitBtn');
  const announcementForm = document.getElementById('announcementForm');

  // Open modal
  newAnnouncementBtn.addEventListener('click', () => {
    modal.classList.add('active');
  });

  // Close modal functions
  function closeModal() {
    modal.classList.remove('active');
    announcementForm.reset();
  }

  closeModalBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);

  // Close on overlay click
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeModal();
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      closeModal();
    }
  });

  // Edit modal functions
  function openEditModal(id, title, content, priority, targetAudience, status) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_content').value = content;
    document.getElementById('edit_priority').value = priority;
    document.getElementById('edit_target_audience').value = targetAudience;
    document.getElementById('edit_status').value = status;
    document.getElementById('editAnnouncementModal').classList.add('active');
  }

  function closeEditModal() {
    document.getElementById('editAnnouncementModal').classList.remove('active');
  }

  // Delete modal functions
  function openDeleteModal(id, title) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteAnnouncementTitle').textContent = title;
    document.getElementById('deleteModal').classList.add('active');
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
  }

  // Close modals on overlay click
  document.getElementById('editAnnouncementModal').addEventListener('click', (e) => {
    if (e.target.id === 'editAnnouncementModal') {
      closeEditModal();
    }
  });

  document.getElementById('deleteModal').addEventListener('click', (e) => {
    if (e.target.id === 'deleteModal') {
      closeDeleteModal();
    }
  });

  // Close all modals on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeEditModal();
      closeDeleteModal();
    }
  });

  // Form submission
  submitBtn.addEventListener('click', (e) => {
    e.preventDefault();
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;
    const priority = document.getElementById('priority').value;
    const target_audience = document.getElementById('target_audience').value;

    if (title && content) {
      // Create FormData
      const formData = new FormData();
      formData.append('title', title);
      formData.append('content', content);
      formData.append('priority', priority);
      formData.append('target_audience', target_audience);
      formData.append('create_announcement', 'true');

      // Submit form
      fetch('announcements.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        closeModal();
        location.reload();
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to create announcement. Please try again.');
      });
    } else {
      alert('Please fill in all required fields.');
    }
  });
</script>

</body>
</html>
