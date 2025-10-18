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

// --- 2. ดึงเกณฑ์ทั้งหมด ---
$stmt_criteria = $pdo->query("SELECT * FROM criteria ORDER BY part, display_order ASC");
$all_criteria = $stmt_criteria->fetchAll();
$criteria_map_by_id = [];
$part1_key_map = [];
$part2_keys = [];
foreach ($all_criteria as $criterion) {
    $criteria_map_by_id[$criterion['id']] = $criterion['title'];
    if ($criterion['part'] === 'part1') {
        $part1_key_map[$criterion['criterion_key']] = $criterion['title'];
    }
    if ($criterion['part'] === 'part2') {
        $part2_keys[] = $criterion['criterion_key'];
    }
}

// --- 3. นิยามสูตร ---
$prizeLevelMap = [ "Modify (ปรับปรุง)Division"=>"Silver", "Modify (ปรับปรุง)Sub-Business"=>"Silver", "Modify (ปรับปรุง)CGS"=>"Gold", "Improve (พัฒนา)Division"=>"Silver", "Improve (พัฒนา)Sub-Business"=>"Gold", "Improve (พัฒนา)CGS"=>"Gold", "Breakthrough (สร้างใหม่)Division"=>"Gold", "Breakthrough (สร้างใหม่)Sub-Business"=>"Gold", "Breakthrough (สร้างใหม่)CGS"=>"Platinum" ];
$prizeMoneyMap = [ "Modify (ปรับปรุง)Division"=>100000, "Modify (ปรับปรุง)Sub-Business"=>100000, "Modify (ปรับปรุง)CGS"=>150000, "Improve (พัฒนา)Division"=>100000, "Improve (พัฒนา)Sub-Business"=>150000, "Improve (พัฒนา)CGS"=>150000, "Breakthrough (สร้างใหม่)Division"=>150000, "Breakthrough (สร้างใหม่)Sub-Business"=>150000, "Breakthrough (สร้างใหม่)CGS"=>200000 ];

function find_most_frequent($arr) {
    if (empty($arr)) return null;
    $counts = array_count_values($arr);
    arsort($counts);
    return key($counts);
}

function truncate_text($text, $length = 50, $ending = '...') {
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . $ending;
    } else {
        return $text;
    }
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
        if (isset($part1_key_map[$score['criterion_key']])) {
            $part1_voted_keys[] = $score['criterion_key'];
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
$part2_judge_totals = [];
$judge_id_map = [];
$judge_counter = 1;
if (!empty($part2_keys)) {
    $placeholders = implode(',', array_fill(0, count($part2_keys), '?'));
    $sql = "SELECT entry_id, user_id, u.role, SUM(score) as total_part2
            FROM scores s
            JOIN users u ON s.user_id = u.id
            WHERE s.criterion_key IN ($placeholders) AND u.role = 'judge'
            GROUP BY s.entry_id, s.user_id";
    $stmt_part2 = $pdo->prepare($sql);
    $stmt_part2->execute($part2_keys);
    $part2_scores_raw = $stmt_part2->fetchAll();
    foreach ($part2_scores_raw as $row) {
        if (!isset($judge_id_map[$row['user_id']])) {
            $judge_id_map[$row['user_id']] = "กรรมการท่านที่ " . $judge_counter++;
        }
        $part2_judge_totals[$row['user_id']][$row['entry_id']] = $row['total_part2'];
    }
}

// --- 6. คำนวณค่าเฉลี่ย Part 2 ---
$part2_contest_stats = [];
foreach ($contests as $contest) {
    $scores_for_this_contest = [];
    foreach (array_keys($judge_id_map) as $user_id) {
        $scores_for_this_contest[] = $part2_judge_totals[$user_id][$contest['id']] ?? 0;
    }
    if (!empty($scores_for_this_contest)) {
        $min_score = min($scores_for_this_contest);
        $max_score = max($scores_for_this_contest);
        $sum_of_black = 0;
        $count_of_black = 0;
        $average = ($min_score == $max_score) ? $min_score : 0;
        if ($min_score != $max_score) {
            foreach ($scores_for_this_contest as $score) {
                if ($score != $min_score && $score != $max_score) {
                    $sum_of_black += $score;
                    $count_of_black++;
                }
            }
            $average = ($count_of_black > 0) ? ($sum_of_black / $count_of_black) : 0;
        }
        $part2_contest_stats[$contest['id']] = ['average' => $average];
    } else {
        $part2_contest_stats[$contest['id']] = ['average' => 0];
    }
}

// --- 7. เตรียมข้อมูลสำหรับสร้างไฟล์ CSV ---
$csv_data = [];

// Header Row
$header_row = ['เกณฑ์การประเมิน'];
$group_number = 1;
foreach ($contests as $contest) {
    $header_row[] = "กลุ่มที่ " . $group_number++ . ": " . $contest['title'];
}
$csv_data[] = $header_row;

// Part 1
$part1_row = ['Part 1: Area & Topic (เลือกมากที่สุด)'];
foreach ($contests as $contest) {
    $selected_key = $summary_data[$contest['id']]['part1_most_frequent_key'] ?? null;
    $part1_row[] = $part1_key_map[$selected_key] ?? '-';
}
$csv_data[] = $part1_row;

// Part 2
foreach ($judge_id_map as $user_id => $judge_name) {
    $judge_row = [$judge_name . ' (รวม Part 2)'];
    foreach ($contests as $contest) {
        $judge_row[] = $part2_judge_totals[$user_id][$contest['id']] ?? 0;
    }
    $csv_data[] = $judge_row;
}
$average_row = ['Part 2: ค่าเฉลี่ย (ไม่รวม Min/Max)'];
foreach ($contests as $contest) {
    $average_row[] = number_format($part2_contest_stats[$contest['id']]['average'] ?? 0, 2);
}
$csv_data[] = $average_row;

// Part 3
$process_row = ['Part 3: Process Degrees (เลือกมากที่สุด)'];
$impact_row = ['Part 3: Impact Degrees (เลือกมากที่สุด)'];
$level_row = ['Part 3: พิจารณารางวัลในระดับ'];
$base_prize_row = ['Part 3: เงินรางวัลตั้งต้น (100%)'];
$rate_row = ['Part 3: อัตราจ่าย (ตามคะแนนเฉลี่ย)'];
$final_prize_row = ['Part 3: เงินรางวัล (บาท) (สุทธิ)'];

foreach ($contests as $contest) {
    $process_id = $summary_data[$contest['id']]['part3_process'] ?? null;
    $impact_id = $summary_data[$contest['id']]['part3_impact'] ?? null;
    $process_text = $criteria_map_by_id[$process_id] ?? '';
    $impact_text = $criteria_map_by_id[$impact_id] ?? '';
    $combinationKey = $process_text . $impact_text;
    $level = $prizeLevelMap[$combinationKey] ?? '-';
    $original_amount = $prizeMoneyMap[$combinationKey] ?? 0;
    $average_score = $part2_contest_stats[$contest['id']]['average'] ?? 0;
    $factor = 0;
    if ($average_score >= 35) $factor = 1;
    elseif ($average_score >= 20) $factor = 0.75;
    $final_amount = $original_amount * $factor;

    $process_row[] = $process_text ?: '-';
    $impact_row[] = $impact_text ?: '-';
    $level_row[] = $level;
    $base_prize_row[] = $original_amount;
    $rate_row[] = ($factor * 100) . '%';
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