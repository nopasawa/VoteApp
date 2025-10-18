<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    
    // --- ส่วนที่เพิ่มเข้ามา ---
    $details = '';
    // 1. ค้นหา Log การล็อกอินครั้งล่าสุด
    $stmt = $pdo->prepare("SELECT timestamp FROM logs 
                          WHERE user_id = ? AND action = 'login_success' 
                          ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $login_time_row = $stmt->fetch();

    if ($login_time_row) {
        // 2. คำนวณระยะเวลา
        $login_time = new DateTime($login_time_row['timestamp']);
        $logout_time = new DateTime();
        $interval = $logout_time->diff($login_time);
        $minutes_spent = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        $details = $minutes_spent . ' minutes'; // บันทึกเวลาเป็นนาที
    }
    
    // 3. บันทึก Log การ Logout พร้อมรายละเอียด
    log_action('logout', $details);
}

// ทำลาย Session
session_unset();
session_destroy();

// ส่งกลับไปหน้า Login
header("Location: login.php");
exit();
?>