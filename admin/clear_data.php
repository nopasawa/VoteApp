<?php
session_start();
// ไม่ต้อง include header.php เพราะไฟล์นี้ทำงานเบื้องหลังอย่างเดียว
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$admin_password = $_POST['admin_password'] ?? null;

// อ่าน ID จาก cả GET และ POST
$contest_id_to_clear = $_POST['id'] ?? $_GET['id'] ?? null;

// --- 1. ตรวจสอบรหัสผ่านแอดมิน (ถ้ามีการส่งมา) ---
if ($admin_password !== null) {
    // ดึงรหัสผ่านของแอดมินที่ล็อกอินอยู่
    $stmt_admin = $pdo->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
    $stmt_admin->execute([$_SESSION['user_id']]);
    $admin_user = $stmt_admin->fetch();

    // ตรวจสอบรหัสผ่าน
    if (!$admin_user || !password_verify($admin_password, $admin_user['password'])) {
        $_SESSION['admin_message_error'] = "รหัสผ่านไม่ถูกต้อง! การดำเนินการถูกยกเลิก";
        // กำหนด redirect page ตาม action ที่พยายามจะทำ
        if ($action === 'clear_criteria') {
            header("Location: manage_criteria.php");
        } elseif ($action === 'clear_scores_for_contest') {
            header("Location: manage_contests.php");
        } else {
            header("Location: index.php");
        }
        exit();
    }
}

// --- 2. ดำเนินการลบข้อมูล (เมื่อรหัสผ่านถูกต้อง หรือไม่มีการส่งรหัสผ่าน) ---
if ($action) {
    
    $redirect_page = 'index.php';

    try {
        $pdo->beginTransaction();

        // --- เคลียร์คะแนนทั้งหมด ---
        if ($action === 'clear_scores') {
            $_SESSION['last_deleted_data'] = ['type' => 'scores', 'data' => $pdo->query("SELECT * FROM scores")->fetchAll(PDO::FETCH_ASSOC)];
            $pdo->exec("DELETE FROM scores");
            $_SESSION['admin_message'] = "ล้างข้อมูลคะแนนทั้งหมดสำเร็จ! <a href='undo_action.php?action=restore_data' class='alert-link'>คลิกที่นี่เพื่อยกเลิก</a>";
            $redirect_page = 'index.php';
        } 
        // --- เคลียร์คะแนนเฉพาะกลุ่ม ---
        elseif ($action === 'clear_scores_for_contest' && $contest_id_to_clear) {
            
            // 1. สำรองข้อมูลคะแนนที่จะลบ
            $stmt_backup = $pdo->prepare("SELECT * FROM scores WHERE entry_id = ?");
            $stmt_backup->execute([$contest_id_to_clear]);
            $_SESSION['last_deleted_data'] = ['type' => 'scores', 'data' => $stmt_backup->fetchAll(PDO::FETCH_ASSOC)];

            // 2. ลบข้อมูลคะแนน
            $stmt_delete = $pdo->prepare("DELETE FROM scores WHERE entry_id = ?");
            $stmt_delete->execute([$contest_id_to_clear]);
            $deleted_rows = $stmt_delete->rowCount();

            // 3. สร้างข้อความพร้อมลิงก์ Undo
            $_SESSION['admin_message'] = "ล้างคะแนน " . $deleted_rows . " รายการของกลุ่มที่ " . htmlspecialchars($contest_id_to_clear) . " สำเร็จ! <a href='undo_action.php?action=restore_data' class='alert-link'>คลิกที่นี่เพื่อยกเลิก</a>";
            $redirect_page = 'manage_contests.php';
        }
        // --- เคลียร์ข้อมูลทั้งหมด ---
        elseif ($action === 'clear_all') {
            $_SESSION['last_deleted_data'] = [
                'type' => 'all', 
                'data' => [
                    'scores' => $pdo->query("SELECT * FROM scores")->fetchAll(PDO::FETCH_ASSOC),
                    'entries' => $pdo->query("SELECT * FROM entries")->fetchAll(PDO::FETCH_ASSOC),
                    'contests' => $pdo->query("SELECT * FROM contests")->fetchAll(PDO::FETCH_ASSOC)
                ]
            ];
            $pdo->exec("DELETE FROM scores");
            $pdo->exec("DELETE FROM entries");
            $pdo->exec("DELETE FROM contests");
            $_SESSION['admin_message'] = "ล้างข้อมูลทั้งหมด (การประกวด, ผลงาน, คะแนน) สำเร็จ! <a href='undo_action.php?action=restore_data' class='alert-link'>คลิกที่นี่เพื่อยกเลิก</a>";
            $redirect_page = 'index.php';
        } 
        // --- เคลียร์เกณฑ์ ---
        elseif ($action === 'clear_criteria') {
             $_SESSION['last_deleted_data'] = ['type' => 'criteria', 'data' => $pdo->query("SELECT * FROM criteria")->fetchAll(PDO::FETCH_ASSOC)];
            $pdo->exec("DELETE FROM criteria");
            $_SESSION['admin_message'] = "ล้างข้อมูลเกณฑ์ทั้งหมดสำเร็จ! <a href='undo_action.php?action=restore_data' class='alert-link'>คลิกที่นี่เพื่อยกเลิก</a>";
            $redirect_page = 'manage_criteria.php'; 
        }

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_message_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
    
    header("Location: " . $redirect_page);
    exit();
}

// กรณีเข้าถึงหน้านี้โดยตรง
header("Location: index.php");
exit();
?>