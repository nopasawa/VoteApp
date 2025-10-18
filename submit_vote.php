<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_login();

// ตรวจสอบว่ามี contest_id ส่งมาใน URL หรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_GET['contest_id'])) {
    header('Location: index.php');
    exit();
}

$contest_id = $_GET['contest_id']; 
$user_id = $_SESSION['user_id'];
$scores_data = $_POST['scores'] ?? [];

$pdo->beginTransaction();

try {
    // --- NEW LOGIC FOR PART 1 (Checkboxes) ---
    // 1. Get all possible Part 1 keys from the database
    $stmt_part1_keys = $pdo->query("SELECT criterion_key FROM criteria WHERE part = 'part1'");
    $possible_part1_keys = $stmt_part1_keys->fetchAll(PDO::FETCH_COLUMN);

    // 2. Delete all existing Part 1 scores for this user and contest
    if (!empty($possible_part1_keys)) {
        $placeholders = implode(',', array_fill(0, count($possible_part1_keys), '?'));
        $stmt_delete_part1 = $pdo->prepare(
            "DELETE FROM scores WHERE user_id = ? AND entry_id = ? AND criterion_key IN ($placeholders)"
        );
        $params = array_merge([$user_id, $contest_id], $possible_part1_keys);
        $stmt_delete_part1->execute($params);
    }
    
    // 3. Insert the newly submitted Part 1 scores
    $submitted_part1_scores = $scores_data['part1'] ?? [];
    if (!empty($submitted_part1_scores) && is_array($submitted_part1_scores)) {
         $stmt_insert_part1 = $pdo->prepare(
            "INSERT INTO scores (user_id, entry_id, criterion_key, score) VALUES (:user_id, :entry_id, :criterion_key, :score)"
        );
        foreach ($submitted_part1_scores as $criterion_key => $score_value) {
            $stmt_insert_part1->execute([
                ':user_id' => $user_id,
                ':entry_id' => $contest_id,
                ':criterion_key' => $criterion_key,
                ':score' => $score_value
            ]);
        }
    }
    
    // --- LOGIC FOR PART 2 & 3 ---
    // (This also handles deleting Part 3 if it's not submitted)
    if (!isset($scores_data['part3_process'])) {
        $stmt_delete_part3_proc = $pdo->prepare(
            "DELETE FROM scores WHERE user_id = ? AND entry_id = ? AND criterion_key = 'part3_process'"
        );
        $stmt_delete_part3_proc->execute([$user_id, $contest_id]);
    }
    if (!isset($scores_data['part3_impact'])) {
        $stmt_delete_part3_imp = $pdo->prepare(
            "DELETE FROM scores WHERE user_id = ? AND entry_id = ? AND criterion_key = 'part3_impact'"
        );
        $stmt_delete_part3_imp->execute([$user_id, $contest_id]);
    }

    $sql_update = "INSERT INTO scores (user_id, entry_id, criterion_key, score) 
                   VALUES (:user_id, :entry_id, :criterion_key, :score)
                   ON DUPLICATE KEY UPDATE score = VALUES(score)";
    $stmt_update = $pdo->prepare($sql_update);
    
    foreach ($scores_data as $criterion_key => $score_value) {
        // Skip the 'part1' array we already processed
        if ($criterion_key === 'part1' || !is_scalar($score_value)) {
            continue;
        }

        if ($score_value !== '') {
             $stmt_update->execute([
                ':user_id'      => $user_id,
                ':entry_id'     => $contest_id,
                ':criterion_key'=> $criterion_key,
                ':score'        => $score_value
            ]);
        }
    }
    
    $pdo->commit();
    
    // บันทึก Log
    log_action('submit_vote', 'บันทึก/แก้ไขคะแนนให้กลุ่ม ID: ' . $contest_id);
    
    $_SESSION['message'] = "บันทึกคะแนนเรียบร้อยแล้ว!";

} catch (Exception $e) {
    $pdo->rollBack();
    die("เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage());
}

header("Location: vote.php?contest_id=" . $contest_id);
exit();
?>