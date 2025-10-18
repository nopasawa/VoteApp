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

// --- 1. ดึงข้อมูลคะแนนทั้งหมดพร้อมชื่อกรรมการ ---
$sql = "SELECT s.user_id, u.full_name, s.criterion_key, s.score
        FROM scores s
        JOIN users u ON s.user_id = u.id
        WHERE s.entry_id = ? AND u.role = 'judge'
        ORDER BY s.user_id";
$stmt_scores = $pdo->prepare($sql);
$stmt_scores->execute([$contest_id]);
$all_scores = $stmt_scores->fetchAll();

// --- 2. จัดกลุ่มคะแนนและสร้าง Map ชื่อกรรมการ ---
$judge_scores = [];
$judge_map = []; // จะเก็บ user_id => full_name
foreach ($all_scores as $score) {
    // Group scores: [user_id][criterion_key] = score
    if ($score['criterion_key'] === 'part1_business_objective' || $score['criterion_key'] === 'part1_cgs_way_of_work' || $score['criterion_key'] === 'part1_organization_of_possibilities') {
        $judge_scores[$score['user_id']]['part1_selections'][] = $score['criterion_key'];
    } else {
        $judge_scores[$score['user_id']][$score['criterion_key']] = $score['score'];
    }
    
    // Map user_id to full_name if not already set
    if (!isset($judge_map[$score['user_id']])) {
        $judge_map[$score['user_id']] = $score['full_name'];
    }
}

// --- นิยามหัวข้อต่างๆ เพื่อใช้แสดงผล ---
$part1_topics = [
    'part1_business_objective' => 'Business Objective', 
    'part1_cgs_way_of_work' => 'CGS Way of Work', 
    'part1_organization_of_possibilities' => 'Organization of Possibilities'
];
$part2_topics = [
    'a_initiative_idea' => 'A) Initiative Idea', 
    'b_functionality' => 'B) Functionality', 
    'c_implement_effort' => 'C) Implement Effort', 
    'd_benefit_and_impact' => 'D) Benefit and Impact'
];

$stmt_criteria_part3 = $pdo->query("SELECT id, title FROM criteria WHERE part IN ('part3_process', 'part3_impact')");
$part3_map = $stmt_criteria_part3->fetchAll(PDO::FETCH_KEY_PAIR);


// สูตรคำนวณรางวัล
$prizeLevelMap = [ "Modify (ปรับปรุง)Division"=>"Silver", "Modify (ปรับปรุง)Sub-Business"=>"Silver", "Modify (ปรับปรุง)CGS"=>"Gold", "Improve (พัฒนา)Division"=>"Silver", "Improve (พัฒนา)Sub-Business"=>"Gold", "Improve (พัฒนา)CGS"=>"Gold", "Breakthrough (สร้างใหม่)Division"=>"Gold", "Breakthrough (สร้างใหม่)Sub-Business"=>"Gold", "Breakthrough (สร้างใหม่)CGS"=>"Platinum" ];
$prizeMoneyMap = [ "Modify (ปรับปรุง)Division"=>100000, "Modify (ปรับปรุง)Sub-Business"=>100000, "Modify (ปรับปรุง)CGS"=>150000, "Improve (พัฒนา)Division"=>100000, "Improve (พัฒนา)Sub-Business"=>150000, "Improve (พัฒนา)CGS"=>150000, "Breakthrough (สร้างใหม่)Division"=>150000, "Breakthrough (สร้างใหม่)Sub-Business"=>150000, "Breakthrough (สร้างใหม่)CGS"=>200000 ];
?>

<div class="container-fluid">
    <a href="manage_contests.php" class="btn btn-secondary mb-3"><i class="bi bi-chevron-left"></i> กลับไปหน้าจัดการประกวด</a>
    <h1 class="mb-4">ผลคะแนน: <?php echo htmlspecialchars($contest['title']); ?></h1>

    <div class="card">
        <div class="card-header">
            สรุปคะแนนรวม
        </div>
        <div class="card-body">
            <?php if (empty($judge_scores)): ?>
                <div class="alert alert-info">ยังไม่มีการลงคะแนนสำหรับการประกวดนี้</div>
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
                            <tr class="table-group-divider">
                                <td colspan="<?php echo count($judge_map) + 1; ?>" class="fw-bold bg-light">Part 1 : พิจารณา Area & Topic</td>
                            </tr>
                            <tr>
                                <td>Area & Topic ที่เลือก</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center">
                                        <?php
                                        $selections = [];
                                        if (isset($judge_scores[$user_id]['part1_selections'])) {
                                            foreach ($judge_scores[$user_id]['part1_selections'] as $key) {
                                                $selections[] = $part1_topics[$key] ?? 'N/A';
                                            }
                                        }
                                        echo empty($selections) ? '-' : implode(',<br>', $selections);
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <tr class="table-group-divider">
                                <td colspan="<?php echo count($judge_map) + 1; ?>" class="fw-bold bg-light">Part 2 : ลงคะแนนเกณฑ์การตัดสิน</td>
                            </tr>
                            <?php foreach ($part2_topics as $key => $title): ?>
                                <tr>
                                    <td><?php echo $title; ?></td>
                                    <?php foreach (array_keys($judge_map) as $user_id): ?>
                                        <td class="text-center"><?php echo $judge_scores[$user_id][$key] ?? '-'; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-warning fw-bold">
                                <td class="text-end">รวม Part 2 (เต็ม 40)</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center fs-5">
                                        <?php
                                        $total_part2 = 0;
                                        foreach ($part2_topics as $key => $title) {
                                            $total_part2 += (int)($judge_scores[$user_id][$key] ?? 0);
                                        }
                                        echo $total_part2;
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <tr class="table-group-divider">
                                <td colspan="<?php echo count($judge_map) + 1; ?>" class="fw-bold bg-light">Part 3 : พิจารณาเงินรางวัล</td>
                            </tr>
                            <tr>
                                <td>Process Degrees</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center"><?php echo $part3_map[$judge_scores[$user_id]['part3_process'] ?? 0] ?? '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td>Impact Degrees</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center"><?php echo $part3_map[$judge_scores[$user_id]['part3_impact'] ?? 0] ?? '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr class="fw-bold">
                                <td class="text-end">ระดับรางวัล</td>
                                <?php foreach (array_keys($judge_map) as $user_id): ?>
                                    <td class="text-center text-primary fs-5">
                                        <?php
                                        $process = $part3_map[$judge_scores[$user_id]['part3_process'] ?? 0] ?? '';
                                        $impact = $part3_map[$judge_scores[$user_id]['part3_impact'] ?? 0] ?? '';
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
                                        $process = $part3_map[$judge_scores[$user_id]['part3_process'] ?? 0] ?? '';
                                        $impact = $part3_map[$judge_scores[$user_id]['part3_impact'] ?? 0] ?? '';
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