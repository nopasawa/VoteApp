<?php
// ไฟล์นี้สามารถใช้เก็บฟังก์ชันที่ใช้บ่อยๆ
// เช่น การตรวจสอบสิทธิ์การเข้าถึง

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }
}

function log_action($action, $details = '') {
    global $pdo; // เรียกใช้การเชื่อมต่อฐานข้อมูล
    
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Guest';
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $sql = "INSERT INTO logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $username, $action, $details, $ip_address]);
}

function require_admin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        // อาจจะแสดงหน้า error หรือ redirect ไปหน้าหลัก
        header("Location: /index.php");
        exit();
    }
}
?>