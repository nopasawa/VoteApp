<?php
include 'includes/header.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/index.php');
    exit();
}

// --- 1. ดึงข้อมูลการประกวดทั้งหมด ---
$stmt_contests = $pdo->prepare("SELECT * FROM contests WHERE status = 'active' ORDER BY id ASC");
$stmt_contests->execute();
$contests = $stmt_contests->fetchAll();

// --- 2. ดึงเกณฑ์ทั้งหมดจากฐานข้อมูล (Dynamic) ---
$stmt_criteria = $pdo->query("SELECT * FROM criteria ORDER BY part, display_order ASC");
$all_criteria = $stmt_criteria->fetchAll();
$criteria_by_part = [];
$criteria_map_by_id = []; // สำหรับแปลง ID เป็น Text
foreach ($all_criteria as $criterion) {
    $criteria_by_part[$criterion['part']][] = $criterion;
    $criteria_map_by_id[$criterion['id']] = $criterion['title'];
}

// --- 3. นิยามสูตรคำนวณ (ยังคงต้องใช้) ---
$prizeLevelMap = [ "Modify (ปรับปรุง)Division"=>"Silver", "Modify (ปรับปรุง)Sub-Business"=>"Silver", "Modify (ปรับปรุง)CGS"=>"Gold", "Improve (พัฒนา)Division"=>"Silver", "Improve (พัฒนา)Sub-Business"=>"Gold", "Improve (พัฒนา)CGS"=>"Gold", "Breakthrough (สร้างใหม่)Division"=>"Gold", "Breakthrough (สร้างใหม่)Sub-Business"=>"Gold", "Breakthrough (สร้างใหม่)CGS"=>"Platinum" ];
$prizeMoneyMap = [ "Modify (ปรับปรุง)Division"=>100000, "Modify (ปรับปรุง)Sub-Business"=>100000, "Modify (ปรับปรุง)CGS"=>200000, "Improve (พัฒนา)Division"=>100000, "Improve (พัฒนา)Sub-Business"=>200000, "Improve (พัฒนา)CGS"=>200000, "Breakthrough (สร้างใหม่)Division"=>200000, "Breakthrough (สร้างใหม่)Sub-Business"=>200000, "Breakthrough (สร้างใหม่)CGS"=>300000 ];
function find_most_frequent($arr) {
    if (empty($arr)) return null;
    $counts = array_count_values($arr);
    arsort($counts);
    return key($counts);
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center my-4">
        <h1 class="mb-0">📝 รายการประกวด</h1>
        <a href="summary_results.php" class="btn btn-primary btn-lg">
            <i class="bi bi-bar-chart-line-fill"></i> ดูผลสรุปคะแนนทั้งหมด
        </a>
    </div>
    <hr class="mb-5">

    <?php if (count($contests) > 0): ?>
        <div class="row">
            <?php 
            $row_number = 1; 
            foreach ($contests as $contest): 
            ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm border-light">
                        <div class="card-body text-center d-flex flex-column p-4">
                            <div class="mb-3"><i class="bi bi-card-checklist text-primary" style="font-size: 3rem;"></i></div>
                            <p class="text-muted mb-2"><strong>กลุ่มที่ <?php echo $row_number; ?></strong></p>
                            <h5 class="card-title"><?php echo htmlspecialchars($contest['title']); ?></h5>
                            <p class="card-text text-muted small flex-grow-1"><?php echo nl2br(htmlspecialchars($contest['description'])); ?></p>
                            <div class="mt-4">
                                <a href="vote.php?contest_id=<?php echo $contest['id']; ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> เข้าสู่หน้าลงคะแนน</a>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resultsModal-<?php echo $contest['id']; ?>"><i class="bi bi-bar-chart-line-fill"></i> ดูผลสรุป</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                $row_number++; 
            endforeach; 
            ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center" role="alert">ยังไม่มีรายการประกวดที่เปิดให้ลงคะแนนในขณะนี้</div>
    <?php endif; ?>
</div>

<?php foreach ($contests as $contest): ?>
    <?php
    // --- ประมวลผลข้อมูลสำหรับ Modal นี้โดยเฉพาะ ---
    $stmt_scores = $pdo->prepare("SELECT criterion_key, score FROM scores WHERE entry_id = ?");
    $stmt_scores->execute([$contest['id']]);
    $all_scores = $stmt_scores->fetchAll();

    $part1_choices = [];
    $part2_totals = [];
    if (isset($criteria_by_part['part2'])) {
        foreach ($criteria_by_part['part2'] as $c) {
            $part2_totals[$c['criterion_key']] = 0;
        }
    }
    $part3_process_choices = [];
    $part3_impact_choices = [];

    foreach ($all_scores as $score) {
        if ($score['criterion_key'] == 'part1_selection') { $part1_choices[] = $score['score']; } 
        elseif (array_key_exists($score['criterion_key'], $part2_totals)) { $part2_totals[$score['criterion_key']] += $score['score']; } 
        elseif ($score['criterion_key'] == 'part3_process') { $part3_process_choices[] = $score['score']; } 
        elseif ($score['criterion_key'] == 'part3_impact') { $part3_impact_choices[] = $score['score']; }
    }
    
    $final_part1_selections = array_keys(array_count_values($part1_choices));
    $final_part3_process = find_most_frequent($part3_process_choices);
    $final_part3_impact = find_most_frequent($part3_impact_choices);
    ?>

    <div class="modal fade" id="resultsModal-<?php echo $contest['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ผลสรุปคะแนน: <?php echo htmlspecialchars($contest['title']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($all_scores)): ?>
                        <div class="alert alert-info">ยังไม่มีการลงคะแนนสำหรับกลุ่มนี้</div>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead class="table-light"><tr><th style="width: 40%;">เกณฑ์การประเมิน</th><th class="text-center">ผลสรุป</th></tr></thead>
                            <tbody>
                                <tr class="table-group-divider"><td colspan="2" class="fw-bold bg-light">Part 1 : พิจารณา Area & Topic</td></tr>
                                <tr>
                                    <td>Area & Topic ที่เลือก</td>
                                    <td class="text-center">
                                        <?php
                                        $selection_texts = [];
                                        foreach ($final_part1_selections as $id) { $selection_texts[] = $criteria_map_by_id[$id] ?? 'N/A'; }
                                        echo empty($selection_texts) ? '-' : implode(', ', $selection_texts);
                                        ?>
                                    </td>
                                </tr>
                                <tr class="table-group-divider"><td colspan="2" class="fw-bold bg-light">Part 2 : ลงคะแนนเกณฑ์การตัดสิน</td></tr>
                                <?php if (isset($criteria_by_part['part2'])): ?>
                                    <?php foreach ($criteria_by_part['part2'] as $c): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($c['title']); ?></td>
                                        <td class="text-center fw-bold fs-5"><?php echo $part2_totals[$c['criterion_key']] ?? 0; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <tr class="table-warning fw-bold">
                                    <td class="text-end">รวม Part 2</td>
                                    <td class="text-center fs-4"><?php echo array_sum($part2_totals); ?></td>
                                </tr>
                                <tr class="table-group-divider"><td colspan="2" class="fw-bold bg-light">Part 3 : พิจารณาเงินรางวัล</td></tr>
                                <tr>
                                    <td>Process Degrees (ส่วนใหญ่เลือก)</td>
                                    <td class="text-center fw-bold"><?php echo $criteria_map_by_id[$final_part3_process] ?? '-'; ?></td>
                                </tr>
                                <tr>
                                    <td>Impact Degrees (ส่วนใหญ่เลือก)</td>
                                    <td class="text-center fw-bold"><?php echo $criteria_map_by_id[$final_part3_impact] ?? '-'; ?></td>
                                </tr>
                                <?php
                                    // ****** ส่วนที่แก้ไข ******
                                    $final_process_text = $criteria_map_by_id[$final_part3_process] ?? '';
                                    $final_impact_text = $criteria_map_by_id[$final_part3_impact] ?? '';
                                    // สร้าง Key โดยการต่อข้อความตรงๆ
                                    $final_combination = $final_process_text . $final_impact_text;
                                ?>
                                <tr class="fw-bold">
                                    <td class="text-end">ระดับรางวัล</td>
                                    <td class="text-center text-primary fs-5"><?php echo $prizeLevelMap[$final_combination] ?? '-'; ?></td>
                                </tr>
                                 <tr class="fw-bold table-dark">
                                    <td class="text-end">เงินรางวัล (บาท)</td>
                                    <td class="text-center fs-4"><?php echo number_format($prizeMoneyMap[$final_combination] ?? 0); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include 'includes/footer.php'; ?>