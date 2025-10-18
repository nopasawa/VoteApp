<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

$redirect_page = 'index.php'; // หน้าเริ่มต้น

if (isset($_GET['action']) && $_GET['action'] === 'restore_data' && isset($_SESSION['last_deleted_data'])) {
    
    $backup = $_SESSION['last_deleted_data'];
    $type = $backup['type'];
    $data = $backup['data'];

    if (!empty($data)) {
        $pdo->beginTransaction();
        try {
            if ($type === 'scores') {
                $sql = "INSERT INTO scores (id, user_id, entry_id, criterion_key, score, submitted_at) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($data as $row) {
                    $stmt->execute([$row['id'], $row['user_id'], $row['entry_id'], $row['criterion_key'], $row['score'], $row['submitted_at']]);
                }
                $redirect_page = 'manage_contests.php';
            } 
            elseif ($type === 'criteria') {
                $sql = "INSERT INTO criteria (id, part, criterion_key, title, description, type, image_path, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                 $stmt = $pdo->prepare($sql);
                 foreach ($data as $row) {
                    $stmt->execute([$row['id'], $row['part'], $row['criterion_key'], $row['title'], $row['description'], $row['type'], $row['image_path'], $row['display_order']]);
                }
                $redirect_page = 'manage_criteria.php';
            }
            elseif ($type === 'all') {
                // กู้คืน contests
                $sql_c = "INSERT INTO contests (id, title, description, status, created_at, contest_date, full_name, department, division, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_c = $pdo->prepare($sql_c);
                foreach($data['contests'] as $row) {
                     $stmt_c->execute([$row['id'], $row['title'], $row['description'], $row['status'], $row['created_at'], $row['contest_date'], $row['full_name'], $row['department'], $row['division'], $row['phone'], $row['email']]);
                }
                // กู้คืน entries (ถ้ามี)
                 if(!empty($data['entries'])) {
                    $sql_e = "INSERT INTO entries (id, contest_id, name, details) VALUES (?, ?, ?, ?)";
                    $stmt_e = $pdo->prepare($sql_e);
                    foreach($data['entries'] as $row) {
                        $stmt_e->execute([$row['id'], $row['contest_id'], $row['name'], $row['details']]);
                    }
                 }
                // กู้คืน scores
                $sql_s = "INSERT INTO scores (id, user_id, entry_id, criterion_key, score, submitted_at) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_s = $pdo->prepare($sql_s);
                foreach($data['scores'] as $row) {
                    $stmt_s->execute([$row['id'], $row['user_id'], $row['entry_id'], $row['criterion_key'], $row['score'], $row['submitted_at']]);
                }
            }
            
            $pdo->commit();
            $_SESSION['admin_message'] = "กู้คืนข้อมูลสำเร็จแล้ว!";

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_message_error'] = "เกิดข้อผิดพลาดในการกู้คืน: " . $e->getMessage();
        }
    }
    
    unset($_SESSION['last_deleted_data']);
}

header("Location: " . $redirect_page);
exit();
?>