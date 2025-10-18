<?php
session_start();
require_once 'includes/db_connect.php';

// ป้องกันการเข้าถึงไฟล์โดยตรง ต้องเป็นการส่งข้อมูลแบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit();
}

$username = trim($_POST['username']);

// เตรียมคำสั่ง SQL เพื่อค้นหาผู้ใช้จาก username
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

// ตรวจสอบว่ามีผู้ใช้ในระบบหรือไม่
if ($user) {
    // 1. สร้าง Token ที่ปลอดภัยและคาดเดาได้ยาก
    // random_bytes สร้างข้อมูลไบนารีแบบสุ่ม, bin2hex แปลงเป็นสตริงเลขฐาน 16
    $token = bin2hex(random_bytes(32));

    // 2. Hash Token ก่อนเก็บลงฐานข้อมูล เพื่อความปลอดภัยสูงสุด
    // หากฐานข้อมูลรั่วไหล Token จริงก็จะไม่ถูกเปิดเผย
    $token_hash = hash('sha256', $token);

    // 3. กำหนดเวลาหมดอายุของ Token (เช่น 15 นาทีจากนี้)
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // 4. บันทึก Token (ที่ hash แล้ว) และเวลาหมดอายุลงในฐานข้อมูล
    $stmt_update = $pdo->prepare(
        "UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?"
    );
    $stmt_update->execute([$token_hash, $expires_at, $user['id']]);

    // 5. สร้างลิงก์สำหรับรีเซ็ตรหัสผ่าน (ส่ง Token ตัวจริง ไม่ใช่ตัวที่ hash)
    // ในระบบจริง ส่วนนี้จะใช้ส่งอีเมล แต่ใน XAMPP เราจะจำลองโดยการแสดงลิงก์ขึ้นมา
    $reset_link = "http://localhost/voting_app/reset_password.php?token=" . $token;

    // เตรียมข้อความเพื่อแสดงผลในหน้า forgot_password.php
    $_SESSION['message_type'] = "success";
    $_SESSION['message'] = "หากชื่อผู้ใช้นี้มีอยู่ในระบบ ลิงก์สำหรับรีเซ็ตรหัสผ่านได้ถูกสร้างขึ้นแล้ว (ลิงก์มีอายุ 15 นาที) <br><br><strong>🔗 ลิงก์สำหรับทดสอบ:</strong> <a href='{$reset_link}' class='alert-link'>คลิกที่นี่เพื่อตั้งรหัสผ่านใหม่</a>";
    
} else {
    // เพื่อความปลอดภัย เราควรแสดงข้อความเดียวกันเสมอ ไม่ว่าผู้ใช้จะมีอยู่จริงหรือไม่
    // เพื่อป้องกันการเดาชื่อผู้ใช้ในระบบ (User Enumeration Attack)
    $_SESSION['message_type'] = "info";
    $_SESSION['message'] = "หากชื่อผู้ใช้นี้มีอยู่ในระบบ เราได้ดำเนินการตามคำขอของท่านแล้ว กรุณาตรวจสอบลิงก์ (ที่ควรจะส่งไปทางอีเมล)";
}

// ส่งผู้ใช้กลับไปที่หน้า forgot_password.php เพื่อแสดงผลลัพธ์
header('Location: forgot_password.php');
exit();
?>