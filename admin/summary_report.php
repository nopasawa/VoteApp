<?php
include '../includes/header.php';
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
$part1_key_map = []; // สร้าง Map ใหม่สำหรับ Part 1: [criterion_key => title]
foreach ($all_criteria as $criterion) {
    $criteria_by_part[$criterion['part']][] = $criterion;
    $criteria_map_by_id[$criterion['id']] = $criterion['title'];
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

    $part2_totals_by_criterion = [];
    if (isset($criteria_by_part['part2'])) {
        foreach ($criteria_by_part['part2'] as $c) {
            $part2_totals_by_criterion[$c['criterion_key']] = 0;
        }
    }
    
    $part1_voted_keys = [];
    $part3_process_choices = [];
    $part3_impact_choices = [];

    foreach ($all_scores as $score) {
        if (isset($part1_key_map[$score['criterion_key']])) {
            $part1_voted_keys[] = $score['criterion_key'];
        }
        elseif (array_key_exists($score['criterion_key'], $part2_totals_by_criterion)) {
            $part2_totals_by_criterion[$score['criterion_key']] += $score['score'];
        }
        elseif ($score['criterion_key'] == 'part3_process') { $part3_process_choices[] = $score['score']; }
        elseif ($score['criterion_key'] == 'part3_impact') { $part3_impact_choices[] = $score['score']; }
    }
    
    $summary_data[$contest_id] = [
        'part1_most_frequent_key' => find_most_frequent($part1_voted_keys),
        'part2_totals_by_criterion' => $part2_totals_by_criterion,
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
            GROUP BY s.entry_id, s.user_id, u.full_name"; // เพิ่ม full_name
    
    $stmt_part2 = $pdo->prepare($sql);
    $stmt_part2->execute($part2_keys);
    $part2_scores_raw = $stmt_part2->fetchAll();

    foreach ($part2_scores_raw as $row) {
        $user_id = $row['user_id'];
        $entry_id = $row['entry_id'];
        
        if (!isset($judge_id_map[$user_id])) {
            $judge_id_map[$user_id] = $row['full_name']; // ใช้ชื่อกรรมการจริง
        }
        
        $part2_judge_totals[$user_id][$entry_id] = $row['total_part2'];
    }
}

// --- 6. คำนวณ Min/Max/Average ของแต่ละ Contest ---
$part2_contest_stats = [];
foreach ($contests as $contest) {
    $contest_id = $contest['id'];
    $scores_for_this_contest_raw = [];
    foreach ($judge_id_map as $user_id => $judge_name) {
        $scores_for_this_contest_raw[] = $part2_judge_totals[$user_id][$contest_id] ?? 0;
    }

    $scores_for_this_contest = array_filter($scores_for_this_contest_raw, function($score) { return $score > 0; });

    $count_valid_scores = count($scores_for_this_contest);
    $min_score_to_cut = null;
    $max_score_to_cut = null;
    $average = 0;

    if ($count_valid_scores === 0) {
        $average = 0;
    } elseif ($count_valid_scores <= 2) {
        $average = array_sum($scores_for_this_contest) / $count_valid_scores;
    } else {
        $min_score_to_cut = min($scores_for_this_contest);
        $max_score_to_cut = max($scores_for_this_contest);
        $scores_for_avg = $scores_for_this_contest;
        $max_key = array_search($max_score_to_cut, $scores_for_avg); if ($max_key !== false) unset($scores_for_avg[$max_key]);
        $min_key = array_search($min_score_to_cut, $scores_for_avg); if ($min_key !== false) unset($scores_for_avg[$min_key]);
        $remaining_count = count($scores_for_avg);
        $average = ($remaining_count > 0) ? (array_sum($scores_for_avg) / $remaining_count) : 0;
    }

    $part2_contest_stats[$contest_id] = [
        'min_to_cut' => $min_score_to_cut,
        'max_to_cut' => $max_score_to_cut,
        'average' => $average,
        'count_valid_scores' => $count_valid_scores 
    ];
}
?>

<style>
    /* ... CSS styles ... */
    .sticky-col { position: sticky; left: 0; z-index: 2; vertical-align: middle; }
    thead th { position: sticky; top: 0; z-index: 3; }
    thead .sticky-col { z-index: 4 !important; }
    .table-light .sticky-col { background-color: #f8f9fa; }
    tbody .sticky-col { background-color: #ffffff; }
    .table-group-divider > .sticky-col { background-color: #f8f9fa; }
    .table-info .sticky-col { background-color: #cff4fc; }
    .table-dark .sticky-col { background-color: #212529; }
    .uniform-header-cell { width: 200px; min-width: 200px; max-width: 200px; white-space: normal; vertical-align: middle; }
    .table-responsive-sticky-header { max-height: 80vh; overflow-y: auto; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">ผลสรุปคะแนนรวมทั้งหมด (Admin View)</h1>
    
    <div class="d-flex align-items-center">
        <div class="btn-group me-3" role="group" aria-label="Font size controls">
            <button type="button" id="font-decrease-btn" class="btn btn-outline-secondary" title="ลดขนาดตัวอักษร"><i class="bi bi-zoom-out"></i></button>
            <button type="button" id="font-reset-btn" class="btn btn-outline-secondary" title="รีเซ็ตขนาด"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button type="button" id="font-increase-btn" class="btn btn-outline-secondary" title="เพิ่มขนาดตัวอักษร"><i class="bi bi-zoom-in"></i></button>
        </div>
        <a href="export_summary.php" class="btn btn-success btn-lg">
            <i class="bi bi-file-earmark-excel-fill"></i> Export to CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($contests)): ?>
            <div class="alert alert-info">ยังไม่มีข้อมูลการประกวด</div>
        <?php else: ?>
            <div class="table-responsive table-responsive-sticky-header" id="report-table-wrapper">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start sticky-col" style="width: 25%;">เกณฑ์การประเมิน</th>
                            <?php 
                            $group_number = 1; 
                            foreach ($contests as $contest): 
                            ?>
                                <th class="text-center uniform-header-cell">
                                    <strong>กลุ่มที่ <?php echo $group_number; ?>:</strong><br>
                                    <?php echo htmlspecialchars(truncate_text($contest['title'], 35)); ?>
                                </th>
                            <?php 
                            $group_number++;
                            endforeach; 
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-group-divider">
                            <td class="fw-bold bg-light sticky-col">Part 1 : พิจารณา Area & Topic</td>
                            <td colspan="<?php echo count($contests); ?>" class="bg-light"></td>
                        </tr>
                        <tr>
                            <td class="sticky-col">Area & Topic (เลือกมากที่สุด)</td>
                            <?php foreach ($contests as $contest): ?>
                                <td class="text-center">
                                    <?php
                                    $selected_key = $summary_data[$contest['id']]['part1_most_frequent_key'] ?? null;
                                    echo $part1_key_map[$selected_key] ?? '-';
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <tr class="table-group-divider">
                            <td class="fw-bold bg-light sticky-col">Part 2 : ลงคะแนนเกณฑ์การตัดสิน</td>
                            <td colspan="<?php echo count($contests); ?>" class="bg-light"></td>
                        </tr>
                        
                        <?php if (empty($judge_id_map)): ?>
                            <tr><td colspan="<?php echo count($contests) + 1; ?>" class="text-center text-muted">ยังไม่มีกรรมการลงคะแนนในส่วนนี้</td></tr>
                        <?php else: ?>
                            <?php 
                            // ****** ส่วนที่แก้ไข: สร้าง flag นอก loop ******
                            $max_highlighted_flags = array_fill_keys(array_column($contests, 'id'), false);
                            $min_highlighted_flags = array_fill_keys(array_column($contests, 'id'), false);
                            ?>
                            <?php foreach ($judge_id_map as $user_id => $judge_name): ?>
                            <tr>
                                <td class="sticky-col"><?php echo $judge_name; ?> (รวม Part 2)</td>
                                
                                <?php foreach ($contests as $contest): ?>
                                    <?php
                                    $score = $part2_judge_totals[$user_id][$contest['id']] ?? 0;
                                    $contest_id = $contest['id'];
                                    
                                    $min_to_cut = $part2_contest_stats[$contest_id]['min_to_cut']; 
                                    $max_to_cut = $part2_contest_stats[$contest_id]['max_to_cut'];
                                    $count_valid = $part2_contest_stats[$contest_id]['count_valid_scores'];

                                    $style_class = '';
                                    
                                    if ($score > 0 && $count_valid >= 3) {
                                        // Highlight Max (Blue) only once per contest column
                                        if ($max_to_cut !== null && $score == $max_to_cut && !$max_highlighted_flags[$contest_id]) {
                                            $style_class = 'text-primary';
                                            $max_highlighted_flags[$contest_id] = true; // Mark as highlighted for this column
                                        }
                                        // Highlight Min (Red) only once per contest column
                                        elseif ($min_to_cut !== null && $score == $min_to_cut && !$min_highlighted_flags[$contest_id]) {
                                            $style_class = 'text-danger';
                                            $min_highlighted_flags[$contest_id] = true; // Mark as highlighted for this column
                                        }
                                    }
                                    ?>
                                    <td class="text-center fw-bold <?php echo $style_class; ?>">
                                        <?php echo $score; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <tr class="table-info fw-bold">
                            <td class="text-end sticky-col">ค่าเฉลี่ย (ตามเงื่อนไขใหม่)</td>
                            <?php foreach ($contests as $contest): ?>
                                <td class="text-center">
                                    <?php
                                    $average = $part2_contest_stats[$contest['id']]['average'] ?? 0;
                                    echo number_format($average, 2);
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <tr class="table-group-divider">
                             <td class="fw-bold bg-light p-0 sticky-col">
                                <a data-bs-toggle="collapse" href=".part3-row" role="button" aria-expanded="false" class="d-block p-2 text-decoration-none text-dark d-flex justify-content-between">
                                    <span>Part 3 : พิจารณาเงินรางวัล</span>
                                    <i class="bi bi-chevron-down"></i>
                                </a>
                             </td>
                            <td colspan="<?php echo count($contests); ?>" class="bg-light"></td>
                        </tr>
                        <tr class="fw-bold collapse part3-row">
                            <td class="text-end sticky-col">Process Degrees (เลือกมากที่สุด)</td>
                            <?php foreach ($contests as $contest): ?>
                                <td class="text-center">
                                    <?php
                                    $process_id = $summary_data[$contest['id']]['part3_process'] ?? null;
                                    echo $criteria_map_by_id[$process_id] ?? '-';
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                         <tr class="fw-bold collapse part3-row">
                            <td class="text-end sticky-col">Impact Degrees (เลือกมากที่สุด)</td>
                            <?php foreach ($contests as $contest): ?>
                                <td class="text-center">
                                    <?php
                                    $impact_id = $summary_data[$contest['id']]['part3_impact'] ?? null;
                                    echo $criteria_map_by_id[$impact_id] ?? '-';
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="fw-bold collapse part3-row">
                            <td class="text-end sticky-col">พิจารณารางวัลในระดับ</td>
                            <?php foreach ($contests as $contest): ?>
                                <?php
                                    $process_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_process']] ?? '';
                                    $impact_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_impact']] ?? '';
                                    $combinationKey = $process_text . $impact_text;
                                    $level = $prizeLevelMap[$combinationKey] ?? '-';
                                ?>
                                <td class="text-center text-primary"><?php echo $level; ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="fw-bold collapse part3-row">
                            <td class="text-end sticky-col">เงินรางวัลตั้งต้น (100%)</td>
                            <?php foreach ($contests as $contest): ?>
                                <?php
                                    $process_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_process']] ?? '';
                                    $impact_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_impact']] ?? '';
                                    $combinationKey = $process_text . $impact_text;
                                    $original_amount = $prizeMoneyMap[$combinationKey] ?? 0;
                                ?>
                                <td class="text-center"><?php echo number_format($original_amount); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="fw-bold collapse part3-row">
                            <td class="text-end sticky-col">อัตราจ่าย (ตามคะแนนเฉลี่ย)</td>
                            <?php foreach ($contests as $contest): ?>
                                <?php
                                    $average_score = $part2_contest_stats[$contest['id']]['average'] ?? 0;
                                    
                                    $factor_text = '0%';
                                    if ($average_score >= 35) {
                                        $factor_text = '100%';
                                    } elseif ($average_score >= 20 && $average_score < 35) {
                                        $factor_text = '75%';
                                    }
                                ?>
                                <td class="text-center text-info"><?php echo $factor_text; ?> <small>(เฉลี่ย <?php echo number_format($average_score, 2); ?>)</small></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="fw-bold table-dark collapse part3-row">
                            <td class="text-end sticky-col">เงินรางวัล (บาท) (สุทธิ)</td>
                            <?php foreach ($contests as $contest): ?>
                                 <?php
                                    $process_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_process']] ?? '';
                                    $impact_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_impact']] ?? '';
                                    $combinationKey = $process_text . $impact_text;
                                    $original_amount = $prizeMoneyMap[$combinationKey] ?? 0;
                                    
                                    $average_score = $part2_contest_stats[$contest['id']]['average'] ?? 0;
                                    
                                    $final_amount = 0;
                                    if ($average_score >= 35) {
                                        $final_amount = $original_amount * 1;
                                    } elseif ($average_score >= 20 && $average_score < 35) {
                                        $final_amount = $original_amount * 0.75;
                                    }
                                ?>
                                <td class="text-center"><?php echo number_format($final_amount); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... JavaScript เดิมสำหรับ Font Size ...
    const mainContainer = document.querySelector('main.container');
    if (mainContainer) {
        mainContainer.classList.remove('container');
        mainContainer.classList.add('container-fluid');
    }
    const target = document.getElementById('report-table-wrapper');
    const increaseBtn = document.getElementById('font-increase-btn');
    const decreaseBtn = document.getElementById('font-decrease-btn');
    const resetBtn = document.getElementById('font-reset-btn');
    const FONT_STORAGE_KEY = 'summary_report_font_size';
    const FONT_STEP = 1;
    function applyFontSize(size) {
        target.style.fontSize = size + 'px';
        localStorage.setItem(FONT_STORAGE_KEY, size);
    }
    const savedSize = localStorage.getItem(FONT_STORAGE_KEY);
    if (savedSize) {
        target.style.fontSize = savedSize + 'px';
    }
    increaseBtn.addEventListener('click', () => {
        let currentSize = parseFloat(window.getComputedStyle(target, null).getPropertyValue('font-size'));
        applyFontSize(currentSize + FONT_STEP);
    });
    decreaseBtn.addEventListener('click', () => {
        let currentSize = parseFloat(window.getComputedStyle(target, null).getPropertyValue('font-size'));
        applyFontSize(currentSize - FONT_STEP);
    });
    resetBtn.addEventListener('click', () => {
        target.style.fontSize = '';
        localStorage.removeItem(FONT_STORAGE_KEY);
    });
});
</script>

<?php include '../includes/footer.php'; ?>