<?php
// บังคับให้เริ่ม session ในทุกหน้าที่เรียกใช้ header
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบลงคะแนนประกวดผลงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="/index.php">🏆 Voting App</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <?php if (isset($_SESSION['user_id'])): ?>
            <li class="nav-item">
              <a class="nav-link">สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name']); ?></a>
            </li>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/index.php">แผงควบคุมแอดมิน</a>
                </li>
            <?php endif; ?>
             <li class="nav-item">
                <a class="nav-link" href="/index.php">หน้าหลัก</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/logout.php">ออกจากระบบ</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="/login.php">เข้าสู่ระบบ</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container mt-4">