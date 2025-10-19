<?php
// ต้องเริ่ม session เพื่อตรวจสอบสิทธิ์
session_start();

require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// --- 1. ดึงข้อมูลการประกวดทั้งหมด ---
$stmt_contests = $pdo->prepare("SELECT id, title FROM contests WHERE status = 'active' ORDER BY id ASC");
$stmt_contests->execute();
$contests = $stmt_contests->fetchAll(PDO::FETCH_ASSOC);

// --- 2. ดึงเกณฑ์ทั้งหมดจากฐานข้อมูล (Dynamic) ---
$stmt_criteria = $pdo->query("SELECT * FROM criteria ORDER BY part, display_order ASC");
$all_criteria = $stmt_criteria->fetchAll();
$criteria_by_part = [];
$criteria_map_by_id = [];
$part1_key_map = []; // Map criterion_key => title for Part 1
foreach ($all_criteria as $criterion) {
    $criteria_by_part[$criterion['part']][] = $criterion;
    $criteria_map_by_id[$criterion['id']] = $criterion['title']; // Map ID => Title for Part 3
    if ($criterion['part'] === 'part1') {
        $part1_key_map[$criterion['criterion_key']] = $criterion['title'];
    }
}

// --- 3. นิยามสูตรคำนวณรางวัล ---
$prizeLevelMap = [ "Modify (ปรับปรุง)Division"=>"Silver", "Modify (ปรับปรุง)Sub-Business"=>"Silver", "Modify (ปรับปรุง)CGS"=>"Gold", "Improve (พัฒนา)Division"=>"Silver", "Improve (พัฒนา)Sub-Business"=>"Gold", "Improve (พัฒนา)CGS"=>"Gold", "Breakthrough (สร้างใหม่)Division"=>"Gold", "Breakthrough (สร้างใหม่)Sub-Business"=>"Gold", "Breakthrough (สร้างใหม่)CGS"=>"Platinum" ];
$prizeMoneyMap = [ "Modify (ปรับปรุง)Division"=>100000, "Modify (ปรับปรุง)Sub-Business"=>100000, "Modify (ปรับปรุง)CGS"=>150000, "Improve (พัฒนา)Division"=>100000, "Improve (พัฒนา)Sub-Business"=>150000, "Improve (พัฒนา)CGS"=>150000, "Breakthrough (สร้างใหม่)Division"=>150000, "Breakthrough (สร้างใหม่)Sub-Business"=>150000, "Breakthrough (สร้างใหม่)CGS"=>200000 ];

function find_most_frequent($arr) {
    if (empty($arr)) return null;
    $counts = array_count_values($arr);
    arsort($counts);
    return key($counts);
}

// --- 4. ประมวลผลข้อมูลสำหรับตารางสรุปรวม ---
$summary_data = [];
foreach ($contests as $contest) {
    $contest_id = $contest['id'];
    $stmt_scores = $pdo->prepare("SELECT criterion_key, score FROM scores WHERE entry_id = ?");
    $stmt_scores->execute([$contest_id]);
    $all_scores = $stmt_scores->fetchAll();
    
    $part1_voted_keys = [];
    $part3_process_choices = [];
    $part3_impact_choices = [];

    foreach ($all_scores as $score) {
        // Check if the key exists in the Part 1 map
        if (isset($part1_key_map[$score['criterion_key']])) {
             // Store the key if the score indicates it was checked (assuming score 1 means checked)
            if ($score['score'] == 1) {
                $part1_voted_keys[] = $score['criterion_key'];
            }
        }
        elseif ($score['criterion_key'] == 'part3_process') { $part3_process_choices[] = $score['score']; }
        elseif ($score['criterion_key'] == 'part3_impact') { $part3_impact_choices[] = $score['score']; }
    }
    
    $summary_data[$contest['id']] = [
        'part1_most_frequent_key' => find_most_frequent($part1_voted_keys),
        'part3_process' => find_most_frequent($part3_process_choices),
        'part3_impact' => find_most_frequent($part3_impact_choices),
    ];
}


// --- 5. ดึงคะแนน Part 2 แยกตามกรรมการ ---
$part2_keys = [];
if (isset($criteria_by_part['part2'])) {
    foreach ($criteria_by_part['part2'] as $c) {
        $part2_keys[] = $c['criterion_key'];
    }
}

$part2_judge_totals = [];
$judge_id_map = [];
$judge_counter = 1;
if (!empty($part2_keys)) {
    $placeholders = implode(',', array_fill(0, count($part2_keys), '?'));
    $sql = "SELECT entry_id, user_id, u.full_name, SUM(score) as total_part2
            FROM scores s
            JOIN users u ON s.user_id = u.id
            WHERE s.criterion_key IN ($placeholders) AND u.role = 'judge'
            GROUP BY s.entry_id, s.user_id, u.full_name"; // Include full_name
    $stmt_part2 = $pdo->prepare($sql);
    $stmt_part2->execute($part2_keys);
    $part2_scores_raw = $stmt_part2->fetchAll();
    foreach ($part2_scores_raw as $row) {
        if (!isset($judge_id_map[$row['user_id']])) {
             $judge_id_map[$row['user_id']] = $row['full_name']; // Use real name
        }
        $part2_judge_totals[$row['user_id']][$row['entry_id']] = $row['total_part2'];
    }
}

// ****** ส่วนที่แก้ไข ******
// --- 6. คำนวณค่าเฉลี่ย Part 2 (ตามเงื่อนไขใหม่) ---
$part2_contest_stats = [];
foreach ($contests as $contest) {
    $contest_id = $contest['id'];
    $scores_for_this_contest_raw = [];
    foreach (array_keys($judge_id_map) as $user_id) { // Use array_keys to ensure order matches judge_map
        $scores_for_this_contest_raw[] = $part2_judge_totals[$user_id][$contest_id] ?? 0;
    }

    // กรองคะแนนที่เป็น 0 ออก
    $scores_for_this_contest = array_filter($scores_for_this_contest_raw, function($score) {
        return $score > 0;
    });

    $count_valid_scores = count($scores_for_this_contest);
    $min_score_to_cut = null;
    $max_score_to_cut = null;
    $average = 0;

    if ($count_valid_scores === 0) {
        $average = 0;
    } elseif ($count_valid_scores <= 2) { // มีคะแนน 1 หรือ 2 ค่า ไม่ต้องตัด Min/Max
        $average = array_sum($scores_for_this_contest) / $count_valid_scores;
    } else { // มีคะแนน 3 ค่าขึ้นไป (หลังกรอง 0)
        $min_score_to_cut = min($scores_for_this_contest);
        $max_score_to_cut = max($scores_for_this_contest);

        $scores_for_avg = $scores_for_this_contest; // ใช้ scores ที่กรอง 0 แล้ว

        // หา Index ของค่า Max ตัวแรกสุดแล้วลบออก
        $max_key = array_search($max_score_to_cut, $scores_for_avg);
        if ($max_key !== false) {
            unset($scores_for_avg[$max_key]);
        }

        // หา Index ของค่า Min ตัวแรกสุดแล้วลบออก
        $min_key = array_search($min_score_to_cut, $scores_for_avg);
        if ($min_key !== false) {
            unset($scores_for_avg[$min_key]);
        }

        $remaining_count = count($scores_for_avg);
        $average = ($remaining_count > 0) ? (array_sum($scores_for_avg) / $remaining_count) : 0; // ป้องกันกรณีเหลือ 0 ค่า
    }

    $part2_contest_stats[$contest_id] = [
        'average' => $average // ค่าเฉลี่ยที่คำนวณตามเงื่อนไขใหม่
    ];
}
// ****** สิ้นสุดส่วนที่แก้ไข ******


// --- 7. เตรียมข้อมูลสำหรับสร้างไฟล์ CSV ---
$csv_data = [];

// Header Row
$header_row = ['เกณฑ์การประเมิน'];
$group_number = 1;
foreach ($contests as $contest) {
    // Make header shorter if title is long
    $header_title = $contest['title'];
    if (mb_strlen($header_title) > 30) {
        $header_title = mb_substr($header_title, 0, 30) . '...';
    }
    $header_row[] = "กลุ่มที่ " . $group_number++ . ": " . $header_title;
}
$csv_data[] = $header_row;

// Part 1
$csv_data[] = ['Part 1: Area & Topic (เลือกมากที่สุด)'];
foreach ($judge_id_map as $user_id => $judge_name) {
    $judge_part1_row = [$judge_name]; // Use real judge name
    foreach ($contests as $contest) {
        // Fetch Part 1 selections for this judge and contest
        $stmt_part1_scores = $pdo->prepare("SELECT criterion_key FROM scores WHERE entry_id = ? AND user_id = ? AND criterion_key LIKE 'part1_%' AND score = 1");
        $stmt_part1_scores->execute([$contest['id'], $user_id]);
        $selected_keys = $stmt_part1_scores->fetchAll(PDO::FETCH_COLUMN);

        $selection_texts = [];
        foreach($selected_keys as $key){
             if(isset($part1_key_map[$key])){
                 $selection_texts[] = $part1_key_map[$key];
             }
        }
        $judge_part1_row[] = empty($selection_texts) ? '-' : implode(', ', $selection_texts);
    }
    $csv_data[] = $judge_part1_row;
}

// Part 2
$csv_data[] = ['Part 2: ลงคะแนนเกณฑ์การตัดสิน'];
foreach ($judge_id_map as $user_id => $judge_name) {
    $judge_row = [$judge_name . ' (รวม Part 2)'];
    foreach ($contests as $contest) {
        $judge_row[] = $part2_judge_totals[$user_id][$contest['id']] ?? 0;
    }
    $csv_data[] = $judge_row;
}
$average_row = ['ค่าเฉลี่ย (ตามเงื่อนไขใหม่)'];
foreach ($contests as $contest) {
    $average_row[] = number_format($part2_contest_stats[$contest['id']]['average'] ?? 0, 2);
}
$csv_data[] = $average_row;

// Part 3
$csv_data[] = ['Part 3: พิจารณาเงินรางวัล'];
$process_row = ['Process Degrees (เลือกมากที่สุด)'];
$impact_row = ['Impact Degrees (เลือกมากที่สุด)'];
$level_row = ['พิจารณารางวัลในระดับ'];
$base_prize_row = ['เงินรางวัลตั้งต้น (100%)'];
$rate_row = ['อัตราจ่าย (ตามคะแนนเฉลี่ย)'];
$final_prize_row = ['เงินรางวัล (บาท) (สุทธิ)'];

foreach ($contests as $contest) {
    $process_id = $summary_data[$contest['id']]['part3_process'] ?? null;
    $impact_id = $summary_data[$contest['id']]['part3_impact'] ?? null;
    $process_text = $criteria_map_by_id[$process_id] ?? '';
    $impact_text = $criteria_map_by_id[$impact_id] ?? '';
    $combinationKey = $process_text . $impact_text;
    $level = $prizeLevelMap[$combinationKey] ?? '-';
    $original_amount = $prizeMoneyMap[$combinationKey] ?? 0;
    $average_score = $part2_contest_stats[$contest['id']]['average'] ?? 0; // Use the new average
    $factor = 0; $factor_text = '0%';
    if ($average_score >= 35) { $factor = 1; $factor_text = '100%'; }
    elseif ($average_score >= 20) { $factor = 0.75; $factor_text = '75%'; } // Corrected condition
    $final_amount = $original_amount * $factor;

    $process_row[] = $process_text ?: '-';
    $impact_row[] = $impact_text ?: '-';
    $level_row[] = $level;
    $base_prize_row[] = $original_amount;
    $rate_row[] = $factor_text;
    $final_prize_row[] = $final_amount;
}
$csv_data[] = $process_row;
$csv_data[] = $impact_row;
$csv_data[] = $level_row;
$csv_data[] = $base_prize_row;
$csv_data[] = $rate_row;
$csv_data[] = $final_prize_row;


// --- 8. สร้างและส่งไฟล์ CSV ---
$filename = "summary_report_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// เพิ่ม BOM เพื่อให้ Excel เปิดไฟล์ UTF-8 (ภาษาไทย) ได้ถูกต้อง
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

foreach ($csv_data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();

?>