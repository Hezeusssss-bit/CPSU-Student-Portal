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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    
    if ($action === 'delete') {
        // Handle delete
        $student_id = $_POST['student_id'] ?? '';
        try {
            // Get the internal student ID first
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $internal_id = $stmt->fetchColumn();
            
            if ($internal_id) {
                // Delete from student_courses first (due to foreign key)
                $stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ?");
                $stmt->execute([$internal_id]);
                
                // Delete from grades
                $stmt = $pdo->prepare("DELETE FROM grades WHERE student_id = ?");
                $stmt->execute([$internal_id]);
                
                // Delete the student
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $stmt->execute([$internal_id]);
                
                header('Location: students.php?deleted=1');
                exit;
            } else {
                $errors[] = 'Student not found';
            }
        } catch(PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    } elseif ($action === 'edit') {
        // Handle edit
        $original_student_id = $_POST['original_student_id'] ?? '';
        $student_id = trim($_POST['student_id'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $errors = [];
        
        // Validate inputs
        if (empty($student_id)) {
            $errors[] = 'Student ID is required';
        }
        if (empty($full_name)) {
            $errors[] = 'Full name is required';
        }
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if (empty($course)) {
            $errors[] = 'Course is required';
        }
        
        // Check if student_id already exists (excluding current record)
        if (empty($errors) && $student_id !== $original_student_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Student ID already exists';
            }
        }
        
        // Check if email already exists (excluding current record)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ? AND student_id != ?");
            $stmt->execute([$email, $original_student_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Email already exists';
            }
        }
        
        // Update student if no errors
        if (empty($errors)) {
            try {
                // Get the student's internal ID
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
                $stmt->execute([$original_student_id]);
                $student_internal_id = $stmt->fetchColumn();
                
                if (!empty($password)) {
                    // Update with new password
                    if (strlen($password) < 6) {
                        $errors[] = 'Password must be at least 6 characters';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE students SET student_id = ?, full_name = ?, email = ?, password = ? WHERE student_id = ?");
                        $stmt->execute([$student_id, $full_name, $email, $hashed_password, $original_student_id]);
                    }
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE students SET student_id = ?, full_name = ?, email = ? WHERE student_id = ?");
                    $stmt->execute([$student_id, $full_name, $email, $original_student_id]);
                }
                
                // Update course enrollment - delete old enrollments and add new one
                $stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ?");
                $stmt->execute([$student_internal_id]);
                $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_code, enrolled_at) VALUES (?, ?, NOW())");
                $stmt->execute([$student_internal_id, $course]);
                
                if (empty($errors)) {
                    header('Location: students.php?edited=1');
                    exit;
                }
            } catch(PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        // Handle add
        $student_id = trim($_POST['student_id'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $errors = [];
        
        // Validate inputs
        if (empty($student_id)) {
            $errors[] = 'Student ID is required';
        }
        if (empty($full_name)) {
            $errors[] = 'Full name is required';
        }
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if (empty($course)) {
            $errors[] = 'Course is required';
        }
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        // Check if student_id already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Student ID already exists';
            }
        }
        
        // Check if email already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Email already exists';
            }
        }
        
        // Insert student if no errors
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO students (student_id, full_name, email, password, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$student_id, $full_name, $email, $hashed_password]);
                
                // Get the new student ID
                $new_student_id = $pdo->lastInsertId();
                
                // Enroll student in the selected course
                $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_code, enrolled_at) VALUES (?, ?, NOW())");
                $stmt->execute([$new_student_id, $course]);
                
                // Redirect to refresh the page
                header('Location: students.php?success=1');
                exit;
            } catch(PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch courses for dropdown
try {
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $courses = [];
}

// Fetch students with their enrolled courses
$students = $pdo->query("SELECT s.student_id, s.full_name, s.email, s.created_at, sc.course_code 
                        FROM students s 
                        LEFT JOIN student_courses sc ON s.id = sc.student_id 
                        ORDER BY s.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Check for success message
$success = isset($_GET['success']) && $_GET['success'] == '1';
$edited = isset($_GET['edited']) && $_GET['edited'] == '1';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Students - CPSU Portal ADMIN PANEL</title>
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

    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--ink-mid);
      margin-bottom: 0.5rem;
    }

    .form-input {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1.5px solid var(--cream-dark);
      border-radius: 8px;
      font-family: 'Sora', sans-serif;
      font-size: 0.9rem;
      color: var(--ink-mid);
      background: var(--white);
      transition: all 0.2s;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--olive);
      box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
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

    .error-message {
      color: #d32f2f;
      font-size: 0.8rem;
      margin-top: 0.25rem;
      display: none;
    }

    .error-message.show {
      display: block;
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
      <a href="students.php" class="nav-item active">
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
        <h1 class="header-title">Students</h1>
        <p class="header-subtitle">Manage student records and information.</p>
      </div>
      <div class="header-actions">
        <button class="action-btn primary" onclick="openModal()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Add Student
        </button>
      </div>
    </header>

    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">All Students</h2>
      </div>
      <div class="panel-body">
        <?php if (count($students) > 0): ?>
        <table class="table">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Course</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $student): ?>
            <tr>
              <td><?php echo htmlspecialchars($student['student_id']); ?></td>
              <td><?php echo htmlspecialchars($student['full_name']); ?></td>
              <td><?php echo htmlspecialchars($student['email']); ?></td>
              <td><?php echo htmlspecialchars($student['course_code'] ?? 'N/A'); ?></td>
              <td><span class="badge student">Active</span></td>
              <td>
                <div style="display: flex; gap: 0.5rem;">
                  <button class="action-btn edit-btn edit-student-btn" 
                          data-student-id="<?php echo htmlspecialchars($student['student_id']); ?>"
                          data-full-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                          data-email="<?php echo htmlspecialchars($student['email']); ?>"
                          data-course="<?php echo htmlspecialchars($student['course_code'] ?? ''); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"/>
                    </svg>
                    Edit
                  </button>
                  <button class="action-btn delete-btn delete-student-btn"
                          data-student-id="<?php echo htmlspecialchars($student['student_id']); ?>"
                          data-full-name="<?php echo htmlspecialchars($student['full_name']); ?>">
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
        <div class="empty-state">No students registered yet</div>
        <?php endif; ?>
      </div>
    </div>
  </main>

</div>

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Add New Student</h3>
      <button class="modal-close" onclick="closeModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form id="addStudentForm" method="POST" action="students.php">
      <div class="form-group">
        <label class="form-label" for="student_id">Student ID</label>
        <input type="text" id="student_id" name="student_id" class="form-input" required placeholder="e.g., 2024-0001">
        <div class="error-message" id="student_id_error"></div>
      </div>
      <div class="form-group">
        <label class="form-label" for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" class="form-input" required placeholder="e.g., Juan Dela Cruz">
        <div class="error-message" id="full_name_error"></div>
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-input" required placeholder="e.g., juan@example.com">
        <div class="error-message" id="email_error"></div>
      </div>
      <div class="form-group">
        <label class="form-label" for="course">Course</label>
        <select id="course" name="course" class="form-input" required>
          <option value="">-- Select Course --</option>
          <?php foreach ($courses as $course): ?>
            <option value="<?php echo htmlspecialchars($course['course_code']); ?>">
              <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="error-message" id="course_error"></div>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-input" required placeholder="Enter password">
        <div class="error-message" id="password_error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="modal-btn confirm">Add Student</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Edit Student</h3>
      <button class="modal-close" onclick="closeEditModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form id="editStudentForm" method="POST" action="students.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="original_student_id" id="edit_original_student_id">
      <div class="form-group">
        <label class="form-label" for="edit_student_id">Student ID</label>
        <input type="text" id="edit_student_id" name="student_id" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_full_name">Full Name</label>
        <input type="text" id="edit_full_name" name="full_name" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_email">Email</label>
        <input type="email" id="edit_email" name="email" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_course">Course</label>
        <select id="edit_course" name="course" class="form-input" required>
          <option value="">-- Select Course --</option>
          <?php foreach ($courses as $course): ?>
            <option value="<?php echo htmlspecialchars($course['course_code']); ?>">
              <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="edit_password">Password (leave blank to keep current)</label>
        <input type="password" id="edit_password" name="password" class="form-input" placeholder="Optional">
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn cancel" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="modal-btn confirm">Update Student</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Delete Student</h3>
      <button class="modal-close" onclick="closeDeleteModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div style="margin-bottom: 1.5rem;">
      <p style="color: var(--ink-mid); font-size: 0.9rem;">Are you sure you want to delete <strong id="deleteStudentName"></strong>? This action cannot be undone.</p>
    </div>
    <form id="deleteForm" method="POST" action="students.php">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="student_id" id="delete_student_id">
      <div class="modal-footer">
        <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="modal-btn confirm" style="background: #d32f2f; border-color: #d32f2f;">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
<?php 
if (isset($errors) && !empty($errors)): 
?>
  // Show errors and open modal
  const errors = <?php echo json_encode($errors); ?>;
  document.addEventListener('DOMContentLoaded', function() {
    openModal();
    const errorDiv = document.createElement('div');
    errorDiv.style.cssText = 'background: #fee; color: #c33; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem;';
    errorDiv.innerHTML = errors.join('<br>');
    const form = document.getElementById('addStudentForm');
    form.insertBefore(errorDiv, form.firstChild);
  });
<?php endif; ?>

<?php if ($success): ?>
  // Show success message
  document.addEventListener('DOMContentLoaded', function() {
    const successDiv = document.createElement('div');
    successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4caf50; color: white; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 2000; animation: slideIn 0.3s ease-out;';
    successDiv.innerHTML = '✓ Student added successfully!';
    document.body.appendChild(successDiv);
    setTimeout(() => {
      successDiv.style.opacity = '0';
      successDiv.style.transition = 'opacity 0.3s';
      setTimeout(() => successDiv.remove(), 300);
    }, 3000);
  });
<?php endif; ?>

<?php if ($edited): ?>
  // Show edit success message
  document.addEventListener('DOMContentLoaded', function() {
    const successDiv = document.createElement('div');
    successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4caf50; color: white; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 2000; animation: slideIn 0.3s ease-out;';
    successDiv.innerHTML = '✓ Student updated successfully!';
    document.body.appendChild(successDiv);
    setTimeout(() => {
      successDiv.style.opacity = '0';
      successDiv.style.transition = 'opacity 0.3s';
      setTimeout(() => successDiv.remove(), 300);
    }, 3000);
  });
<?php endif; ?>

<?php if ($deleted): ?>
  // Show delete success message
  document.addEventListener('DOMContentLoaded', function() {
    const successDiv = document.createElement('div');
    successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #d32f2f; color: white; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 2000; animation: slideIn 0.3s ease-out;';
    successDiv.innerHTML = '✓ Student deleted successfully!';
    document.body.appendChild(successDiv);
    setTimeout(() => {
      successDiv.style.opacity = '0';
      successDiv.style.transition = 'opacity 0.3s';
      setTimeout(() => successDiv.remove(), 300);
    }, 3000);
  });
<?php endif; ?>

function openModal() {
  const overlay = document.getElementById('addStudentModal');
  overlay.classList.add('active');
}

function closeModal() {
  const overlay = document.getElementById('addStudentModal');
  overlay.classList.remove('active');
  setTimeout(() => {
    document.getElementById('addStudentForm').reset();
    const errorDiv = document.querySelector('#addStudentForm > div[style*="background: #fee"]');
    if (errorDiv) errorDiv.remove();
  }, 200);
}

function openEditModal(studentId, fullName, email, course) {
  document.getElementById('edit_original_student_id').value = studentId;
  document.getElementById('edit_student_id').value = studentId;
  document.getElementById('edit_full_name').value = fullName;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_course').value = course;
  document.getElementById('edit_password').value = '';
  const overlay = document.getElementById('editStudentModal');
  overlay.classList.add('active');
}

function closeEditModal() {
  const overlay = document.getElementById('editStudentModal');
  overlay.classList.remove('active');
  setTimeout(() => {
    document.getElementById('editStudentForm').reset();
  }, 200);
}

function openDeleteModal(studentId, fullName) {
  document.getElementById('delete_student_id').value = studentId;
  document.getElementById('deleteStudentName').textContent = fullName;
  const overlay = document.getElementById('deleteModal');
  overlay.classList.add('active');
}

function closeDeleteModal() {
  const overlay = document.getElementById('deleteModal');
  overlay.classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
  // Edit button event listeners
  document.querySelectorAll('.edit-student-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const studentId = this.getAttribute('data-student-id');
      const fullName = this.getAttribute('data-full-name');
      const email = this.getAttribute('data-email');
      const course = this.getAttribute('data-course');
      openEditModal(studentId, fullName, email, course);
    });
  });

  // Delete button event listeners
  document.querySelectorAll('.delete-student-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const studentId = this.getAttribute('data-student-id');
      const fullName = this.getAttribute('data-full-name');
      openDeleteModal(studentId, fullName);
    });
  });

  const overlay = document.getElementById('addStudentModal');
  if (overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) {
        closeModal();
      }
    });
  }

  const editOverlay = document.getElementById('editStudentModal');
  if (editOverlay) {
    editOverlay.addEventListener('click', function(e) {
      if (e.target === editOverlay) {
        closeEditModal();
      }
    });
  }

  const deleteOverlay = document.getElementById('deleteModal');
  if (deleteOverlay) {
    deleteOverlay.addEventListener('click', function(e) {
      if (e.target === deleteOverlay) {
        closeDeleteModal();
      }
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeModal();
      closeEditModal();
      closeDeleteModal();
    }
  });
});
</script>

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
