<?php
include 'includes/header.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: /admin/index.php'); // Corrected path
    exit();
}

$user_id = $_SESSION['user_id'];

// ค้นหาว่าผู้ใช้นี้เคยลงคะแนนให้ contest ใดไปแล้วบ้าง
$stmt_voted = $pdo->prepare("SELECT DISTINCT entry_id FROM scores WHERE user_id = ?");
$stmt_voted->execute([$user_id]);
$voted_contests_map = array_flip($stmt_voted->fetchAll(PDO::FETCH_COLUMN));

// 1. ดึงวันที่ทั้งหมดที่มีการประกวด (สำหรับสร้างตัวกรอง)
$stmt_dates = $pdo->query("SELECT DISTINCT contest_date FROM contests WHERE contest_date IS NOT NULL ORDER BY contest_date ASC");
$available_dates = $stmt_dates->fetchAll(PDO::FETCH_COLUMN);

$today = date('Y-m-d'); // ดึงวันที่ปัจจุบัน

// 2. ตรวจสอบว่ามีการเลือกวันที่มาจากฟอร์ม (GET) หรือไม่
if (isset($_GET['date'])) {
    $selected_date = $_GET['date'];
    $_SESSION['selected_contest_date'] = $selected_date;
} 
elseif (isset($_SESSION['selected_contest_date'])) {
    $selected_date = $_SESSION['selected_contest_date'];
} 
else {
    $selected_date = $today;
}

// --- 1. ดึงข้อมูลการประกวดทั้งหมด (กรองตามวันที่ที่เลือก) ---
$sql = "SELECT * FROM contests";
$params = [];
$where_clauses = [];

if ($selected_date !== 'all' && !empty($selected_date)) {
    $where_clauses[] = "contest_date = ?";
    $params[] = $selected_date;
}

// Only show 'active' contests on the judge index page
$where_clauses[] = "status = 'active'"; // Filter only active contests

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY id ASC";

$stmt_contests = $pdo->prepare($sql);
$stmt_contests->execute($params);
$contests = $stmt_contests->fetchAll();

// --- 2. ดึงเกณฑ์ทั้งหมดจากฐานข้อมูล (Dynamic) ---
$stmt_criteria = $pdo->query("SELECT * FROM criteria ORDER BY part, display_order ASC");
$all_criteria = $stmt_criteria->fetchAll();
$criteria_by_part = [];
$criteria_map_by_id = [];
$part1_key_map = [];
foreach ($all_criteria as $criterion) {
    $criteria_by_part[$criterion['part']][] = $criterion;
    $criteria_map_by_id[$criterion['id']] = $criterion['title'];
    if ($criterion['part'] === 'part1') {
        $part1_key_map[$criterion['criterion_key']] = $criterion['title'];
    }
}

// --- 3. นิยามสูตรคำนวณ (ยังคงต้องใช้) ---
$prizeLevelMap = [ "Modify (ปรับปรุง)Division"=>"Silver", "Modify (ปรับปรุง)Sub-Business"=>"Silver", "Modify (ปรับปรุง)CGS"=>"Gold", "Improve (พัฒนา)Division"=>"Silver", "Improve (พัฒนา)Sub-Business"=>"Gold", "Improve (พัฒนา)CGS"=>"Gold", "Breakthrough (สร้างใหม่)Division"=>"Gold", "Breakthrough (สร้างใหม่)Sub-Business"=>"Gold", "Breakthrough (สร้างใหม่)CGS"=>"Platinum" ];
$prizeMoneyMap = [ "Modify (ปรับปรุง)Division"=>100000, "Modify (ปรับปรุง)Sub-Business"=>100000, "Modify (ปรับปรุง)CGS"=>150000, "Improve (พัฒนา)Division"=>100000, "Improve (พัฒนา)Sub-Business"=>150000, "Improve (พัฒนา)CGS"=>150000, "Breakthrough (สร้างใหม่)Division"=>150000, "Breakthrough (สร้างใหม่)Sub-Business"=>150000, "Breakthrough (สร้างใหม่)CGS"=>200000 ];
function find_most_frequent($arr) {
    if (empty($arr)) return null;
    $counts = array_count_values($arr);
    arsort($counts);
    return key($counts);
}
?>

<div class="container">
    <h1 class="my-4 text-center">📝 รายการประกวด</h1>
    <?php if (isset($_SESSION['message_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message_error']; unset($_SESSION['message_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/index.php" id="date-filter-form" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="date-filter" class="col-form-label"><strong><i class="bi bi-calendar-event"></i> กรองตามวันที่ประกวด:</strong></label>
                </div>
                <div class="col-auto">
                    <select name="date" id="date-filter" class="form-select">
                        <option value="all" <?php if ($selected_date === 'all') echo 'selected'; ?>>แสดงทุกวัน</option>
                        <?php foreach ($available_dates as $date): ?>
                            <option value="<?php echo $date; ?>" <?php if ($selected_date === $date) echo 'selected'; ?>>
                                <?php echo date("d F Y", strtotime($date)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                 
                    <a href="/index.php?date=all" class="btn btn-outline-secondary">ล้างค่า</a>
                </div>
            </form>
        </div>
    </div>


    <?php if (count($contests) > 0): ?>
        <div class="row">
            <?php 
            $row_number = 1; 
            foreach ($contests as $contest): 
            
                $contest_id = $contest['id'];
                $has_voted = isset($voted_contests_map[$contest_id]);
                
                $btn_link = "vote.php?contest_id=" . $contest_id;
                if ($contest['status'] === 'closed') {
                    $btn_class = 'btn-secondary disabled';
                    $btn_icon = 'bi-lock-fill';
                    $btn_text = 'ปิดรับคะแนน';
                    $btn_link = '#';
                } elseif ($has_voted) {
                    $btn_class = 'btn-success';
                    $btn_icon = 'bi-check-circle-fill';
                    $btn_text = 'ลงคะแนนแล้ว (แก้ไข)';
                } else {
                    $btn_class = 'btn-primary';
                    $btn_icon = 'bi-pencil-square';
                    $btn_text = 'เข้าสู่หน้าลงคะแนน';
                }
            ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm border-light">
                        <div class="card-body text-center d-flex flex-column p-4">
                            <div class="mb-3"><i class="bi bi-card-checklist text-primary" style="font-size: 3rem;"></i></div>
                            <p class="text-muted mb-2"><strong>กลุ่มที่ <?php echo $row_number; ?></strong></p>
                            <h5 class="card-title"><?php echo htmlspecialchars($contest['title']); ?></h5>
                            <p class="card-text text-muted small flex-grow-1"><?php echo nl2br(htmlspecialchars($contest['description'])); ?></p>
                            <div class="mt-4">
                                <a href="<?php echo $btn_link; ?>" class="btn <?php echo $btn_class; ?>">
                                    <i class="bi <?php echo $btn_icon; ?>"></i> <?php echo $btn_text; ?>
                                </a>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resultsModal-<?php echo $contest_id; ?>"><i class="bi bi-bar-chart-line-fill"></i> ดูผลสรุป</button>
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
        <div class="alert alert-info text-center" role="alert">
            <?php if ($selected_date !== 'all' && !empty($selected_date)): ?>
                ไม่พบรายการประกวดสำหรับวันที่ <?php echo date("d F Y", strtotime($selected_date)); ?>
            <?php else: ?>
                ยังไม่มีรายการประกวดที่เปิดให้ลงคะแนนในระบบ
            <?php endif; ?>
        </div>
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
        // Corrected check for Part 1 - using criterion_key directly
         if (isset($part1_key_map[$score['criterion_key']]) && $score['score'] == 1) {
            $part1_choices[] = $score['criterion_key']; // Store the key
        } 
        elseif (array_key_exists($score['criterion_key'], $part2_totals)) { $part2_totals[$score['criterion_key']] += $score['score']; } 
        elseif ($score['criterion_key'] == 'part3_process') { $part3_process_choices[] = $score['score']; } 
        elseif ($score['criterion_key'] == 'part3_impact') { $part3_impact_choices[] = $score['score']; }
    }
    
    // Get unique selected keys and then map them to titles
    $final_part1_selection_keys = array_keys(array_count_values($part1_choices));
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
                                    <td>Area & Topic ที่เลือก (ส่วนใหญ่)</td>
                                    <td class="text-center">
                                        <?php
                                        $selection_texts = [];
                                        foreach ($final_part1_selection_keys as $key) { $selection_texts[] = $part1_key_map[$key] ?? 'N/A'; }
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
                                    $final_process_text = $criteria_map_by_id[$final_part3_process] ?? '';
                                    $final_impact_text = $criteria_map_by_id[$final_part3_impact] ?? '';
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dateFilter = document.getElementById('date-filter');
    if (dateFilter) {
        dateFilter.addEventListener('change', function() {
            document.getElementById('date-filter-form').submit();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>