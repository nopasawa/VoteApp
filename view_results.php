<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

if (!isset($_GET['contest_id'])) {
    header("Location: manage_contests.php");
    exit();
}
$contest_id = $_GET['contest_id'];

// ดึงชื่อการประกวด
$stmt_contest = $pdo->prepare("SELECT title FROM contests WHERE id = ?");
$stmt_contest->execute([$contest_id]);
$contest = $stmt_contest->fetch();
if (!$contest) { die("ไม่พบการประกวดนี้"); }

// --- ดึงเกณฑ์ทั้งหมดจากฐานข้อมูล ---
$stmt_criteria = $pdo->query("SELECT * FROM criteria ORDER BY part, display_order ASC");
$all_criteria = $stmt_criteria->fetchAll();
$criteria_by_part = [];
$criteria_map_by_id = [];
foreach ($all_criteria as $criterion) {
    $criteria_by_part[$criterion['part']][] = $criterion;
    $criteria_map_by_id[$criterion['id']] = $criterion['title'];
}

// --- ดึงข้อมูลคะแนนทั้งหมดพร้อมชื่อกรรมการ ---
$sql = "SELECT s.user_id, u.full_name, s.criterion_key, s.score FROM scores s JOIN users u ON s.user_id = u.id WHERE s.entry_id = ? AND u.role = 'judge' ORDER BY s.user_id";
$stmt_scores = $pdo->prepare($sql);
$stmt_scores->execute([$contest_id]);
$all_scores = $stmt_scores->fetchAll();

// --- จัดกลุ่มคะแนนและสร้าง Map ชื่อกรรมการ ---
$judge_scores = [];
$judge_map = [];
foreach ($all_scores as $score) {
    $judge_scores[$score['user_id']][$score['criterion_key']] = $score['score'];
    if (!isset($judge_map[$score['user_id']])) {
        $judge_map[$score['user_id']] = $score['full_name'];
    }
}

// สูตรคำนวณรางวัล
$prizeLevelMap = [ "Modify (ปรับปรุง)Division"=>"Silver", "Modify (ปรับปรุง)Sub-Business"=>"Silver", "Modify (ปรับปรุง)CGS"=>"Gold", "Improve (พัฒนา)Division"=>"Silver", "Improve (พัฒนา)Sub-Business"=>"Gold", "Improve (พัฒนา)CGS"=>"Gold", "Breakthrough (สร้างใหม่)Division"=>"Gold", "Breakthrough (สร้างใหม่)Sub-Business"=>"Gold", "Breakthrough (สร้างใหม่)CGS"=>"Platinum" ];
$prizeMoneyMap = [ "Modify (ปรับปรุง)Division"=>100000, "Modify (ปรับปรุง)Sub-Business"=>100000, "Modify (ปรับปรุง)CGS"=>200000, "Improve (พัฒนา)Division"=>100000, "Improve (พัฒนา)Sub-Business"=>200000, "Improve (พัฒนา)CGS"=>200000, "Breakthrough (สร้างใหม่)Division"=>200000, "Breakthrough (สร้างใหม่)Sub-Business"=>200000, "Breakthrough (สร้างใหม่)CGS"=>300000 ];
?>

<div class="container-fluid">
    <a href="manage_contests.php" class="btn btn-secondary mb-3"><i class="bi bi-chevron-left"></i> กลับไปหน้าจัดการประกวด</a>
    <h1 class="mb-4">ผลคะแนน: <?php echo htmlspecialchars($contest['title']); ?></h1>

    <div class="card">
        <div class="card-header">สรุปคะแนนรวม</div>
        <div class="card-body">
            <?php if (empty($judge_scores)): ?>
                <div class="alert alert-info">ยังไม่มีการลงคะแนน</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light text-center">
                            <tr>
                                <th class="text-start">เกณฑ์การประเมิน</th>
                                <?php foreach ($judge_map as $name): ?>
                                    <th><?php echo htmlspecialchars($name); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-group-divider"><td colspan="<?php echo count($judge_map) + 1; ?>" class="fw-bold bg-light">Part 1 : พิจารณา Area & Topic</td></tr>
                            <tr>
                                <td>Area & Topic ที่เลือก</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center">
                                        <?php
                                        $selections = [];
                                        if (isset($judge_scores[$user_id])) {
                                            foreach ($judge_scores[$user_id] as $key => $value) {
                                                if ($key === 'part1_selection') { $selections[] = $criteria_map_by_id[$value] ?? 'N/A'; }
                                            }
                                        }
                                        echo empty($selections) ? '-' : implode(',<br>', $selections);
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <tr class="table-group-divider"><td colspan="<?php echo count($judge_map) + 1; ?>" class="fw-bold bg-light">Part 2 : ลงคะแนนเกณฑ์การตัดสิน</td></tr>
                            <?php if (isset($criteria_by_part['part2'])): ?>
                            <?php foreach ($criteria_by_part['part2'] as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['title']); ?></td>
                                    <?php foreach (array_keys($judge_map) as $user_id): ?>
                                        <td class="text-center"><?php echo $judge_scores[$user_id][$c['criterion_key']] ?? '-'; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <tr class="table-warning fw-bold">
                                <td class="text-end">รวม Part 2</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center fs-5">
                                        <?php
                                        $total_part2 = 0;
                                        if (isset($criteria_by_part['part2'])) {
                                            foreach ($criteria_by_part['part2'] as $c) {
                                                $total_part2 += (int)($judge_scores[$user_id][$c['criterion_key']] ?? 0);
                                            }
                                        }
                                        echo $total_part2;
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <tr class="table-group-divider"><td colspan="<?php echo count($judge_map) + 1; ?>" class="fw-bold bg-light">Part 3 : พิจารณาเงินรางวัล</td></tr>
                            <tr>
                                <td>Process Degrees</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center"><?php echo $criteria_map_by_id[$judge_scores[$user_id]['part3_process'] ?? 0] ?? '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td>Impact Degrees</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center"><?php echo $criteria_map_by_id[$judge_scores[$user_id]['part3_impact'] ?? 0] ?? '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr class="fw-bold">
                                <td class="text-end">ระดับรางวัล</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center text-primary fs-5">
                                        <?php
                                        $process = $criteria_map_by_id[$judge_scores[$user_id]['part3_process'] ?? 0] ?? '';
                                        $impact = $criteria_map_by_id[$judge_scores[$user_id]['part3_impact'] ?? 0] ?? '';
                                        echo $prizeLevelMap[$process . $impact] ?? '-';
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                             <tr class="fw-bold table-dark">
                                <td class="text-end">เงินรางวัล (บาท)</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center fs-4">
                                         <?php
                                        $process = $criteria_map_by_id[$judge_scores[$user_id]['part3_process'] ?? 0] ?? '';
                                        $impact = $criteria_map_by_id[$judge_scores[$user_id]['part3_impact'] ?? 0] ?? '';
                                        $amount = $prizeMoneyMap[$process . $impact] ?? 0;
                                        echo number_format($amount);
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>