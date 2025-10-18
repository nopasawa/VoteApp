<?php
// ไฟล์นี้สามารถใช้เก็บฟังก์ชันที่ใช้บ่อยๆ

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }
}

function require_admin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: /index.php");
        exit();
    }
}

// ****** นี่คือฟังก์ชันที่เพิ่มเข้ามา ******
function log_action($action, $details = '') {
    global $pdo; // เรียกใช้การเชื่อมต่อฐานข้อมูล
    
    if (!$pdo) {
        return; // ออกจากฟังก์ชันถ้าไม่มีการเชื่อมต่อ
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Guest';
    $ip_address = $_SERVER['REMOTE_ADDR'];

    try {
        $sql = "INSERT INTO logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $username, $action, $details, $ip_address]);
    } catch (PDOException $e) {
        // error_log("Failed to log action: " . $e->getMessage());
    }
}
?>