<?php
session_start();

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

// Fetch counts for display
$student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$faculty_count = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if ($student_id && $password) {
        // Static admin account check
        if ($student_id === 'admin' && $password === 'admin123') {
            $_SESSION['user_id']   = 1;
            $_SESSION['role']      = 'admin';
            $_SESSION['full_name'] = 'Administrator';
            echo json_encode(['success' => true, 'redirect' => 'admin_dashboard.php']);
            exit;
        }

        try {
            // Check students table
            $stmt = $pdo->prepare("SELECT id, student_id, password, full_name FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = 'student';
                $_SESSION['full_name'] = $user['full_name'];
                echo json_encode(['success' => true, 'redirect' => 'student_dashboard.php']);
                exit;
            }

            // Check faculty table
            $stmt = $pdo->prepare("SELECT id, faculty_id, password, full_name FROM faculty WHERE faculty_id = ?");
            $stmt->execute([$student_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = 'faculty';
                $_SESSION['full_name'] = $user['full_name'];
                echo json_encode(['success' => true, 'redirect' => 'faculty_dashboard.php']);
                exit;
            }

            // Check admins table
            $stmt = $pdo->prepare("SELECT id, admin_id, password, full_name FROM admins WHERE admin_id = ?");
            $stmt->execute([$student_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = 'admin';
                $_SESSION['full_name'] = $user['full_name'];
                echo json_encode(['success' => true, 'redirect' => 'admin_dashboard.php']);
                exit;
            }

            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            exit;
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EduPortal — Sign In</title>
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
      background: var(--forest);
      overflow: hidden;
    }

    body::after {
      content: '';
      position: fixed;
      inset: 0;
      z-index: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.05'/%3E%3C/svg%3E");
      background-size: 200px 200px;
      pointer-events: none;
      opacity: 0.5;
    }

    .wrapper {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: 52% 1fr;
      height: 100vh;
    }

    /* ═══ LEFT PANEL ═══ */
    .panel-left {
      background: var(--cream);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 3.5rem 4.5rem 3.5rem 5rem;
      position: relative;
      overflow: hidden;
    }

    .panel-left::before {
      content: '';
      position: absolute;
      bottom: -120px; left: -120px;
      width: 500px; height: 500px;
      border-radius: 50%;
      border: 60px solid rgba(85,107,47,0.06);
      pointer-events: none;
    }

    .panel-left::after {
      content: '';
      position: absolute;
      top: -80px; right: -80px;
      width: 320px; height: 320px;
      border-radius: 50%;
      border: 40px solid rgba(198,169,97,0.08);
      pointer-events: none;
    }

    .logo-row {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-bottom: 3.2rem;
      animation: fadeDown 0.55s ease both;
    }

    .logo-mark {
      width: 44px; height: 44px;
      border-radius: 11px;
      background: var(--olive);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 14px rgba(85,107,47,0.35);
    }

    .logo-text {
      font-family: 'Crimson Pro', serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.1;
    }

    .logo-text small {
      display: block;
      font-family: 'Sora', sans-serif;
      font-size: 0.6rem;
      font-weight: 500;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--muted);
      margin-top: 2px;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.68rem;
      font-weight: 600;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--gold-dk);
      margin-bottom: 0.9rem;
      animation: fadeDown 0.55s 0.08s ease both;
    }
    .eyebrow::before {
      content: '';
      width: 20px; height: 2px;
      background: var(--gold);
      border-radius: 2px;
    }

    .form-headline {
      font-family: 'Crimson Pro', serif;
      font-size: 3.1rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.07;
      letter-spacing: -1px;
      margin-bottom: 0.5rem;
      animation: fadeDown 0.55s 0.13s ease both;
    }
    .form-headline em { font-style: italic; color: var(--olive); }

    .form-sub {
      font-size: 0.82rem;
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 2.2rem;
      animation: fadeDown 0.55s 0.18s ease both;
    }

    /* Role switch */
    .role-switch {
      display: flex;
      background: var(--cream-dark);
      border-radius: 10px;
      padding: 4px;
      margin-bottom: 1.7rem;
      gap: 4px;
      animation: fadeDown 0.55s 0.22s ease both;
    }

    .role-btn {
      flex: 1;
      padding: 0.55rem 0;
      border: none;
      border-radius: 7px;
      background: transparent;
      font-family: 'Sora', sans-serif;
      font-size: 0.77rem;
      font-weight: 600;
      color: var(--muted);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      transition: all 0.22s;
    }
    .role-btn.active {
      background: var(--olive);
      color: var(--cream);
      box-shadow: 0 2px 10px rgba(85,107,47,0.3);
    }
    .role-btn:hover:not(.active) { color: var(--ink); }

    /* Inputs */
    .fields { animation: fadeDown 0.55s 0.27s ease both; }
    .field { margin-bottom: 1.1rem; }

    .field label {
      display: block;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--ink-mid);
      margin-bottom: 0.42rem;
    }

    .input-shell { position: relative; }

    .input-shell .icon {
      position: absolute;
      left: 13px; top: 50%;
      transform: translateY(-50%);
      color: #9aaa78;
      display: flex;
      pointer-events: none;
      transition: color 0.2s;
    }

    .input-shell input {
      width: 100%;
      padding: 0.82rem 2.8rem;
      border: 1.5px solid var(--cream-dark);
      border-radius: 10px;
      background: var(--white);
      font-family: 'Sora', sans-serif;
      font-size: 0.875rem;
      color: var(--ink);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .input-shell input::placeholder { color: #b5bc98; }
    .input-shell input:focus {
      border-color: var(--olive);
      box-shadow: 0 0 0 3px rgba(85,107,47,0.14);
    }
    .input-shell:focus-within .icon { color: var(--olive); }

    .eye-btn {
      position: absolute;
      right: 13px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer;
      color: #9aaa78;
      padding: 2px; display: flex;
      transition: color 0.2s;
    }
    .eye-btn:hover { color: var(--olive); }

    .util-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 1rem 0 1.7rem;
      animation: fadeDown 0.55s 0.32s ease both;
    }

    .check-label {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.77rem;
      color: var(--muted);
      cursor: pointer;
      user-select: none;
    }
    .check-label input[type="checkbox"] {
      width: 15px; height: 15px;
      accent-color: var(--olive);
      cursor: pointer; padding: 0;
    }

    .forgot-link {
      font-size: 0.77rem;
      font-weight: 600;
      color: var(--olive-mid);
      text-decoration: none;
      transition: color 0.2s;
    }
    .forgot-link:hover { color: var(--gold-dk); }

    /* CTA */
    .btn-cta {
      width: 100%;
      padding: 0.92rem;
      border: none;
      border-radius: 10px;
      background: var(--olive);
      color: var(--cream);
      font-family: 'Sora', sans-serif;
      font-size: 0.9rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      position: relative;
      overflow: hidden;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
      animation: fadeDown 0.55s 0.37s ease both;
      box-shadow: 0 4px 18px rgba(85,107,47,0.3);
    }
    .btn-cta::after {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
      transform: translateX(-100%);
      transition: transform 0.5s;
    }
    .btn-cta:hover { background: var(--olive-mid); transform: translateY(-1px); box-shadow: 0 8px 26px rgba(85,107,47,0.4); }
    .btn-cta:hover::after { transform: translateX(100%); }
    .btn-cta:active { transform: translateY(0); }

    .or-row {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      margin: 1.2rem 0;
      font-size: 0.7rem;
      letter-spacing: 0.1em;
      color: var(--muted);
      animation: fadeDown 0.55s 0.42s ease both;
    }
    .or-row::before, .or-row::after {
      content: ''; flex: 1;
      height: 1px; background: var(--cream-dark);
    }

    .btn-sso {
      width: 100%;
      padding: 0.8rem;
      border: 1.5px solid var(--cream-dark);
      border-radius: 10px;
      background: transparent;
      font-family: 'Sora', sans-serif;
      font-size: 0.82rem; font-weight: 500;
      color: var(--ink-mid);
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 0.6rem;
      transition: border-color 0.2s, background 0.2s;
      animation: fadeDown 0.55s 0.46s ease both;
    }
    .btn-sso:hover { border-color: var(--olive); background: rgba(85,107,47,0.05); }

    .form-footer {
      margin-top: 1.4rem;
      font-size: 0.77rem;
      color: var(--muted);
      animation: fadeDown 0.55s 0.5s ease both;
    }
    .form-footer a { color: var(--olive); font-weight: 600; text-decoration: none; }
    .form-footer a:hover { color: var(--gold-dk); }

    /* ═══ RIGHT PANEL ═══ */
    .panel-right {
      background: var(--olive);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 3rem 2.5rem;
    }

    .panel-right::before {
      content: '';
      position: absolute;
      top: -140px; right: -100px;
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(198,169,97,0.28) 0%, transparent 65%);
      pointer-events: none;
    }

    .panel-right::after {
      content: '';
      position: absolute;
      bottom: -60px; left: -60px;
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(30,58,20,0.55) 0%, transparent 65%);
      pointer-events: none;
    }

    .deco-bg-letter {
      position: absolute;
      bottom: -3rem; right: -2rem;
      font-family: 'Crimson Pro', serif;
      font-size: 24rem;
      font-weight: 700;
      font-style: italic;
      color: rgba(245,245,220,0.045);
      line-height: 1;
      pointer-events: none;
      user-select: none;
    }

    .deco-top {
      position: relative; z-index: 1;
      display: flex; flex-direction: column;
      align-items: center; text-align: center; gap: 0.9rem;
    }

    .seal-wrapper {
      position: relative;
      width: 120px; height: 120px;
      flex-shrink: 0;
      animation: fadeDown 0.6s 0.3s ease both;
    }

    .seal-ring { width: 120px; height: 120px; animation: rotateSeal 28s linear infinite; }

    .seal-core {
      position: absolute;
      top: 50%; left: 50%;
      width: 66px; height: 66px;
      animation: rotateSealCenter 28s linear infinite reverse;
    }

    @keyframes rotateSeal { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes rotateSealCenter {
      from { transform: translate(-50%,-50%) rotate(0deg); }
      to   { transform: translate(-50%,-50%) rotate(360deg); }
    }

    .school-name {
      font-family: 'Crimson Pro', serif;
      font-size: 2.1rem;
      font-weight: 700;
      color: var(--cream);
      line-height: 1.1;
      animation: fadeDown 0.6s 0.4s ease both;
    }
    .school-name span { color: var(--gold); }

    .school-motto {
      font-size: 0.65rem;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: rgba(245,245,220,0.5);
      animation: fadeDown 0.6s 0.48s ease both;
    }

    .stat-row {
      position: relative; z-index: 1;
      display: flex; gap: 0.8rem;
      animation: popIn 0.6s 0.55s ease both;
    }

    .stat-card {
      flex: 1;
      background: rgba(245,245,220,0.09);
      border: 1px solid rgba(198,169,97,0.25);
      border-radius: 12px;
      padding: 1rem 0.6rem;
      text-align: center;
      transition: background 0.2s, border-color 0.2s;
    }
    .stat-card:hover { background: rgba(245,245,220,0.14); border-color: rgba(198,169,97,0.45); }

    .stat-num {
      font-family: 'Crimson Pro', serif;
      font-size: 2rem; font-weight: 700;
      color: var(--gold); line-height: 1;
    }
    .stat-lbl {
      font-size: 0.62rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: rgba(245,245,220,0.55);
      margin-top: 4px;
    }

    .feat-list {
      position: relative; z-index: 1;
      display: flex; flex-direction: column; gap: 0.7rem;
    }

    .feat-item {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      padding: 0.75rem 1rem;
      background: rgba(245,245,220,0.07);
      border: 1px solid rgba(198,169,97,0.15);
      border-radius: 10px;
      transition: background 0.2s;
      animation: slideRight 0.5s ease both;
    }
    .feat-item:nth-child(1) { animation-delay: 0.5s; }
    .feat-item:nth-child(2) { animation-delay: 0.64s; }
    .feat-item:nth-child(3) { animation-delay: 0.78s; }
    .feat-item:hover { background: rgba(245,245,220,0.12); }

    .feat-icon {
      width: 36px; height: 36px;
      border-radius: 8px;
      background: rgba(198,169,97,0.2);
      border: 1px solid rgba(198,169,97,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; flex-shrink: 0;
    }

    .feat-text strong { display: block; font-size: 0.82rem; font-weight: 600; color: var(--cream); }
    .feat-text span { font-size: 0.7rem; color: rgba(245,245,220,0.55); }

    .deco-footer {
      position: relative; z-index: 1;
      font-size: 0.6rem;
      letter-spacing: 0.12em;
      color: rgba(245,245,220,0.22);
      text-transform: uppercase;
      text-align: center;
    }

    /* Toast */
    .toast {
      position: fixed;
      bottom: 2rem; left: 50%;
      transform: translateX(-50%) translateY(100px);
      padding: 0.7rem 1.5rem;
      border-radius: 100px;
      font-size: 0.82rem; font-weight: 500;
      z-index: 100;
      transition: transform 0.4s cubic-bezier(0.22,1,0.36,1);
      white-space: nowrap; pointer-events: none;
      background: var(--ink); color: var(--cream);
    }
    .toast.show  { transform: translateX(-50%) translateY(0); }
    .toast.error { background: #5a2a1a; color: #fde8d8; }
    .toast.ok    { background: var(--olive-mid); color: var(--cream); }

    @keyframes fadeDown {
      from { opacity: 0; transform: translateY(-14px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes popIn {
      from { opacity: 0; transform: scale(0.92); }
      to   { opacity: 1; transform: scale(1); }
    }
    @keyframes slideRight {
      from { opacity: 0; transform: translateX(18px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    .ripple {
      position: absolute; border-radius: 50%;
      background: rgba(245,245,220,0.25);
      transform: scale(0);
      animation: rippleAnim 0.55s linear;
      pointer-events: none;
    }
    @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }

    .spinner {
      width: 18px; height: 18px;
      border: 2.5px solid rgba(245,245,220,0.3);
      border-top-color: var(--cream);
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 860px) {
      .wrapper { grid-template-columns: 1fr; }
      .panel-right { display: none; }
      .panel-left { padding: 2rem 1.75rem; }
    }
  </style>
</head>
<body>

<div class="wrapper">

  <!-- LEFT -->
  <div class="panel-left">
    <div class="logo-row">
      <div class="logo-mark">
        <img src="cpsulogo.png" alt="CPSU Logo" style="width: 44px; height: 44px; border-radius: 11px; object-fit: cover;"/>
      </div>
      <div class="logo-text">
        Cental Philippine State University
        <small>Student Portal</small>
      </div>
    </div>

    <div class="form-card" style="max-width:400px;position:relative;z-index:1;">
      <div class="eyebrow">Secure Access</div>
      <h1 class="form-headline">Sign in,<br/><em>Students.</em></h1>
      <p class="form-sub">Access your grades, announcements, and schedule all in one place.</p>

      <div class="fields">
        <div class="field">
          <label for="uid">Student ID</label>
          <div class="input-shell">
            <span class="icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
            </span>
            <input type="text" id="uid" placeholder="e.g. STU-2024-001" autocomplete="off"/>
          </div>
        </div>
        <div class="field">
          <label for="pw">Password</label>
          <div class="input-shell">
            <span class="icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="pw" placeholder="Enter your password"/>
            <button class="eye-btn" type="button" onclick="togglePw()" aria-label="Toggle password">
              <svg id="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <div class="util-row">
        <label class="check-label"><input type="checkbox"/> Keep me signed in</label>
        <a href="#" class="forgot-link">Forgot password?</a>
      </div>

      <button class="btn-cta" id="login-btn" onclick="handleLogin(event)">
        <span id="btn-label">Sign In</span>
        <svg id="btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="panel-right">
    <div class="deco-bg-letter">B</div>

    <div class="deco-top">
      <div class="seal-wrapper">
        <img src="cpsulogo.png" alt="CPSU Logo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; animation: rotateSeal 28s linear infinite;"/>
      </div>
      <div class="school-name">Cental Philippine State<br/><span>University</span></div>
      <div class="school-motto">Excellence · Integrity · Service</div>
    </div>

    <div class="stat-row">
      <div class="stat-card"><div class="stat-num"><?php echo $student_count; ?></div><div class="stat-lbl">Students</div></div>
      <div class="stat-card"><div class="stat-num"><?php echo $faculty_count; ?></div><div class="stat-lbl">Faculty</div></div>
      <div class="stat-card"><div class="stat-num">96%</div><div class="stat-lbl">Pass Rate</div></div>
    </div>

    <div class="feat-list">
      <div class="feat-item">
        <div class="feat-icon">📊</div>
        <div class="feat-text"><strong>Academic Records</strong><span>Grades, GPA & transcript access</span></div>
      </div>
      <div class="feat-item">
        <div class="feat-icon">📢</div>
        <div class="feat-text"><strong>Announcements</strong><span>Real-time school-wide memos</span></div>
      </div>
      <div class="feat-item">
        <div class="feat-icon">🗓️</div>
        <div class="feat-text"><strong>Events & Exams</strong><span>Calendar, schedules & deadlines</span></div>
      </div>
    </div>

    <div class="deco-footer">© 2025 Cental Philippine State University · All rights reserved</div>
  </div>

</div>

<div class="toast" id="toast"></div>

<script>
  function togglePw() {
    const pw = document.getElementById('pw');
    const hidden = pw.type === 'password';
    pw.type = hidden ? 'text' : 'password';
    document.getElementById('eye-icon').innerHTML = hidden
      ? `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
         <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
         <line x1="1" y1="1" x2="23" y2="23"/>`
      : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
         <circle cx="12" cy="12" r="3"/>`;
  }

  document.getElementById('login-btn').addEventListener('click', function(e) {
    const rect = this.getBoundingClientRect();
    const r = document.createElement('span');
    r.className = 'ripple';
    const size = Math.max(this.offsetWidth, this.offsetHeight);
    r.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px`;
    this.appendChild(r);
    setTimeout(() => r.remove(), 600);
  });

  function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + type;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3400);
  }

  async function handleLogin(e) {
    const id   = document.getElementById('uid').value.trim();
    const pw   = document.getElementById('pw').value.trim();

    if (!id) { showToast('⚠️ Please enter your ID', 'error'); return; }
    if (!pw) { showToast('⚠️ Please enter your password', 'error'); return; }

    const btn   = document.getElementById('login-btn');
    const label = document.getElementById('btn-label');
    label.textContent = 'Signing in…';
    document.getElementById('btn-arrow').outerHTML = '<div class="spinner" id="btn-arrow"></div>';
    btn.disabled = true;

    try {
      const fd = new FormData();
      fd.append('student_id', id);
      fd.append('password', pw);

      const res    = await fetch('login.php', { method: 'POST', body: fd });
      const result = await res.json();

      if (result.success) {
        showToast('✅ Welcome back! Loading dashboard…', 'ok');
        setTimeout(() => window.location.href = result.redirect, 1000);
      } else {
        showToast('❌ ' + result.message, 'error');
        label.textContent = 'Sign In';
        document.getElementById('btn-arrow').outerHTML = `<svg id="btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;
        btn.disabled = false;
      }
    } catch {
      showToast('❌ Connection error. Try again.', 'error');
      label.textContent = 'Sign In';
      document.getElementById('btn-arrow').outerHTML = `<svg id="btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;
      btn.disabled = false;
    }
  }
</script>

</body>
</html>