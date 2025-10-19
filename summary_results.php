<?php
include 'includes/header.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_login();

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

// --- 4. ประมวลผลข้อมูลสำหรับตารางสรุปรวม (เฉพาะ Part 1 และ 3) ---
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
        // Correct check using isset for Part 1 keys
        if (isset($part1_key_map[$score['criterion_key']]) && $score['score'] == 1) {
            $part1_voted_keys[] = $score['criterion_key'];
        }
        elseif ($score['criterion_key'] == 'part3_process') { $part3_process_choices[] = $score['score']; }
        elseif ($score['criterion_key'] == 'part3_impact') { $part3_impact_choices[] = $score['score']; }
    }
    
    $summary_data[$contest_id] = [
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
            GROUP BY s.entry_id, s.user_id, u.full_name"; // เพิ่ม full_name
    
    $stmt_part2 = $pdo->prepare($sql);
    $stmt_part2->execute($part2_keys);
    $part2_scores_raw = $stmt_part2->fetchAll();

    foreach ($part2_scores_raw as $row) {
        $user_id = $row['user_id'];
        $entry_id = $row['entry_id'];
        
        if (!isset($judge_id_map[$user_id])) {
            $judge_id_map[$user_id] = "กรรมการท่านที่ " . $judge_counter++; // ใช้ชื่อแฝงในหน้านี้
        }
        
        $part2_judge_totals[$user_id][$entry_id] = $row['total_part2'];
    }
}

// ****** ส่วนที่แก้ไข ******
// --- 6. คำนวณ Min/Max/Average ของแต่ละ Contest (ใช้สูตรใหม่) ---
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
        'min_to_cut' => $min_score_to_cut,
        'max_to_cut' => $max_score_to_cut,
        'average' => $average, // ค่าเฉลี่ยที่คำนวณตามเงื่อนไขใหม่
        'count_valid_scores' => $count_valid_scores 
    ];
}
// ****** สิ้นสุดส่วนที่แก้ไข ******

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

<div class="container-fluid">
    <a href="index.php" class="btn btn-secondary mb-3"><i class="bi bi-chevron-left"></i> กลับไปหน้ารายการ</a>
    <h1 class="mb-4">ผลสรุปคะแนนรวมทั้งหมด</h1>

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
                                <?php foreach ($contests as $contest): ?>
                                    <th class="text-center uniform-header-cell"><?php echo htmlspecialchars($contest['title']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-group-divider"><td colspan="<?php echo count($contests) + 1; ?>" class="fw-bold bg-light sticky-col">Part 1 : พิจารณา Area & Topic</td></tr>
                            <?php if (empty($judge_id_map)): ?>
                                <tr><td colspan="<?php echo count($contests) + 1; ?>" class="text-center text-muted">ยังไม่มีกรรมการลงคะแนน</td></tr>
                            <?php else: ?>
                                <?php foreach ($judge_id_map as $user_id => $judge_name): ?>
                                    <tr>
                                        <td class="sticky-col"><?php echo $judge_name; ?></td>
                                        <?php foreach ($contests as $contest): ?>
                                            <td class="text-center">
                                                <?php
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
                                                echo empty($selection_texts) ? '-' : implode(',<br>', $selection_texts);
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <tr class="table-group-divider"><td colspan="<?php echo count($contests) + 1; ?>" class="fw-bold bg-light sticky-col">Part 2 : ลงคะแนนเกณฑ์การตัดสิน</td></tr>
                            <?php if (isset($criteria_by_part['part2'])): ?>
                                <?php foreach ($criteria_by_part['part2'] as $c): ?>
                                <tr>
                                    <td class="sticky-col"><?php echo htmlspecialchars($c['title']); ?></td>
                                    <?php foreach ($contests as $contest): ?>
                                        <td class="text-center fw-bold"><?php echo $summary_data[$contest['id']]['part2_totals_by_criterion'][$c['criterion_key']] ?? 0; ?></td>
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
                             <tr class="table-group-divider"><td colspan="<?php echo count($contests) + 1; ?>" class="fw-bold bg-light sticky-col">Part 3 : พิจารณาเงินรางวัล</td></tr>
                            <tr class="fw-bold">
                                <td class="text-end sticky-col">สรุป</td>
                                <?php foreach ($contests as $contest): ?>
                                    <?php
                                        $process_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_process']] ?? '';
                                        $impact_text = $criteria_map_by_id[$summary_data[$contest['id']]['part3_impact']] ?? '';
                                        $selected_combination = $process_text . $impact_text;
                                    ?>
                                    <td class="text-center">
                                        <select class="form-select prize-summary-select" data-contest-id="<?php echo $contest['id']; ?>">
                                            <?php foreach ($prize_combinations as $combo): ?>
                                                <?php $combo_key = str_replace(["/CBM"], "", $combo); ?>
                                                <option value="<?php echo $combo_key; ?>" <?php if ($combo_key == $selected_combination) echo 'selected'; ?>>
                                                    <?php echo $combo; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr class="fw-bold">
                                <td class="text-end sticky-col">พิจารณารางวัลในระดับ</td>
                                <?php foreach ($contests as $contest): ?>
                                    <td class="text-center text-primary fs-5" id="prize-level-<?php echo $contest['id']; ?>">-</td>
                                <?php endforeach; ?>
                            </tr>
                            <tr class="fw-bold table-dark">
                                <td class="text-end sticky-col">เงินรางวัล (บาท)</td>
                                <?php foreach ($contests as $contest): ?>
                                    <td class="text-center fs-4" id="prize-money-<?php echo $contest['id']; ?>">-</td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
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
    increaseBtn.addEventListener('click', () => { /* ... */ });
    decreaseBtn.addEventListener('click', () => { /* ... */ });
    resetBtn.addEventListener('click', () => { /* ... */ });

    // JavaScript สำหรับอัปเดต Dropdown (เหมือนเดิม)
    const prizeLevelMap = <?php echo json_encode($prizeLevelMap); ?>;
    const prizeMoneyMap = <?php echo json_encode($prizeMoneyMap); ?>;
    const summarySelects = document.querySelectorAll('.prize-summary-select');

    function updatePrizeDetails(selectElement) {
        const selectedCombination = selectElement.value;
        const contestId = selectElement.dataset.contestId;
        const levelElement = document.getElementById(`prize-level-${contestId}`);
        const moneyElement = document.getElementById(`prize-money-${contestId}`);
        levelElement.textContent = prizeLevelMap[selectedCombination] || '-';
        const amount = prizeMoneyMap[selectedCombination] || 0;
        moneyElement.textContent = amount.toLocaleString('en-US');
    }

    summarySelects.forEach(select => {
        select.addEventListener('change', function() { updatePrizeDetails(this); });
        updatePrizeDetails(select); // คำนวณครั้งแรกเมื่อโหลด
    });
});
</script>

<?php include 'includes/footer.php'; ?>