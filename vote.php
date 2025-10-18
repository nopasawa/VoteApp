<?php
include 'includes/header.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_login();

if (!isset($_GET['contest_id'])) {
    header("Location: index.php");
    exit();
}
$contest_id = $_GET['contest_id'];
$user_id = $_SESSION['user_id'];

// --- 1. ดึงข้อมูลการประกวด ---
$stmt_contest = $pdo->prepare("SELECT * FROM contests WHERE id = ?");
$stmt_contest->execute([$contest_id]);
$contest = $stmt_contest->fetch();
if (!$contest) { die("ไม่พบการประกวดนี้"); }

if ($contest['status'] === 'closed') {
    $_SESSION['message_error'] = "การประกวด '" . htmlspecialchars($contest['title']) . "' ได้ปิดรับคะแนนแล้ว";
    header("Location: index.php");
    exit();
}


// --- 2. ดึงเกณฑ์ทั้งหมดจากฐานข้อมูล ---
$stmt_criteria = $pdo->query("SELECT * FROM criteria ORDER BY part, display_order ASC");
$all_criteria = $stmt_criteria->fetchAll();
$criteria_by_part = [];
foreach ($all_criteria as $criterion) {
    $criteria_by_part[$criterion['part']][] = $criterion;
}

// --- 3. ดึงคะแนนที่เคยให้ไว้แล้ว ---
$stmt_scores = $pdo->prepare("SELECT criterion_key, score FROM scores WHERE user_id = ? AND entry_id = ?");
$stmt_scores->execute([$user_id, $contest_id]);
$previous_scores_raw = $stmt_scores->fetchAll();
$previous_scores = [];
foreach ($previous_scores_raw as $score) {
    $previous_scores[$score['criterion_key']] = $score['score'];
}


// --- 4. เตรียมข้อมูลสำหรับ JavaScript ---
$js_process_map = [];
foreach($criteria_by_part['part3_process'] as $c) {
    $js_process_map[$c['id']] = $c['title'];
}
$js_impact_map = [];
foreach($criteria_by_part['part3_impact'] as $c) {
    $js_impact_map[$c['id']] = $c['title'];
}

?>

<div class="container">
    <a href="index.php" class="btn btn-secondary mb-3"><i class="bi bi-chevron-left"></i> กลับไปหน้ารายการ</a>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white p-3">
             <h4 class="mb-0">แบบฟอร์มลงคะแนน</h4>
             <p class="mb-0">ทีม/หัวข้อ: <?php echo htmlspecialchars($contest['title']); ?></p>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="submit_vote.php?contest_id=<?php echo $contest_id; ?>" method="post">
                <div class="accordion" id="votingAccordion">

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button fs-5" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                <i class="bi bi-list-check me-2"></i> Part 1 : พิจารณา Area & Topic
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#votingAccordion">
                            <div class="accordion-body">
                                <p class="text-muted">โปรดเลือก Area & Topic ที่เข้าข่าย (เลือกได้มากกว่า 1 ข้อ)</p>
                                <?php foreach ($criteria_by_part['part1'] as $c): ?>
                                    <div class="form-check item-selectable d-flex justify-content-between align-items-center border-bottom py-3">
                                        <label class="form-check-label flex-grow-1" for="crit_<?php echo $c['id']; ?>">
                                            <strong class="d-block"><?php echo $c['title']; ?></strong>
                                            <small class="text-muted"><?php echo nl2br(htmlspecialchars($c['description'])); ?></small>
                                        </label>
                                        <input class="form-check-input custom-input-lg ms-3" type="checkbox" name="scores[part1][<?php echo $c['criterion_key']; ?>]" id="crit_<?php echo $c['id']; ?>" value="1" <?php if (isset($previous_scores[$c['criterion_key']])) echo 'checked'; ?>>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>


                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button fs-5" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
                                <i class="bi bi-sliders me-2"></i> Part 2 : ลงคะแนนเกณฑ์การตัดสิน
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo" data-bs-parent="#votingAccordion">
                            <div class="accordion-body">
                                <p class="text-muted">ให้คะแนนในแต่ละเกณฑ์ (ข้อละ 10 คะแนน)</p>
                                
                                <div class="p-2 mb-3 rounded border" style="background-color: #f8f9fa;">
                                    <small class="text-muted">
                                        <b>สเกลคะแนน (1-10):</b><br>
                                        <b>1-4 :</b> เข้าเกณฑ์บางส่วน<br>
                                        <b>5-7 :</b> เข้าเกณฑ์เกือบทั้งหมด<br>
                                        <b>8-10 :</b> เข้าเกณฑ์ทั้งหมด
                                    </small>
                                </div>
                                
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($criteria_by_part['part2'] as $c): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo $c['title']; ?></strong>
                                            <div class="text-muted small"><?php echo nl2br(htmlspecialchars($c['description'])); ?></div>
                                        </div>
                                        <div style="width: 100px;">
                                            <select name="scores[<?php echo $c['criterion_key']; ?>]" class="form-select part2-score-select" required>
                                                <option value="">-</option>
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php if (isset($previous_scores[$c['criterion_key']]) && $previous_scores[$c['criterion_key']] == $i) echo 'selected'; ?>><?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="d-flex justify-content-end align-items-center mt-4 p-3 bg-light rounded">
                                    <div class="me-4 text-end">
                                        <h5 class="mb-0">รวม (เต็ม 40 คะแนน): <span id="part2_total" class="fw-bold">0</span></h5>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">สรุปผล: <span id="part2_summary" class="fw-bold"></span></h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: none;">
                        <div class="accordion-item" id="part3_accordion_item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button fs-5 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    <i class="bi bi-trophy-fill me-2"></i> Part 3 : พิจารณาเงินรางวัล
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#votingAccordion">
                                <div class="accordion-body">
                                    <p><strong>โปรดเลือก Process Degrees ที่เข้าข่าย (เลือกได้ 1 ข้อ)</strong></p>
                                    <?php foreach ($criteria_by_part['part3_process'] as $c): ?>
                                    <div class="form-check item-selectable d-flex justify-content-between align-items-center border-bottom py-3">
                                        <label class="form-check-label d-flex align-items-center flex-grow-1" for="crit_<?php echo $c['id']; ?>">
                                            <?php if (!empty($c['image_path'])): ?>
                                            <img src="/assets/images/<?php echo $c['image_path']; ?>" alt="<?php echo $c['title']; ?>" style="width: 100px; height: auto; margin-right: 15px; border-radius: 4px;">
                                            <?php endif; ?>
                                            <span class="fw-bold"><?php echo $c['title']; ?></span>
                                        </label>
                                        <input class="form-check-input custom-input-lg ms-3 part3-radio" type="radio" name="scores[part3_process]" id="crit_<?php echo $c['id']; ?>" value="<?php echo $c['id']; ?>" <?php if (isset($previous_scores['part3_process']) && $previous_scores['part3_process'] == $c['id']) echo 'checked'; ?>>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <p class="mt-4"><strong>โปรดเลือก Impact Degrees ที่เข้าข่าย (เลือกได้ 1 ข้อ)</strong></p>
                                    <?php foreach ($criteria_by_part['part3_impact'] as $c): ?>
                                    <div class="form-check item-selectable d-flex justify-content-between align-items-center border-bottom py-3">
                                        <label class="form-check-label flex-grow-1" for="crit_<?php echo $c['id']; ?>">
                                            <?php echo $c['title']; ?>
                                            <small class="d-block text-muted"><?php echo nl2br(htmlspecialchars($c['description'])); ?></small>
                                        </label>
                                        <input class="form-check-input custom-input-lg ms-3 part3-radio" type="radio" name="scores[part3_impact]" id="crit_<?php echo $c['id']; ?>" value="<?php echo $c['id']; ?>" <?php if (isset($previous_scores['part3_impact']) && $previous_scores['part3_impact'] == $c['id']) echo 'checked'; ?>>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึกคะแนน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const scoreSelects = document.querySelectorAll('.part2-score-select');
    const totalElement = document.getElementById('part2_total');
    const summaryElement = document.getElementById('part2_summary');
    
    function calculateTotalPart2() {
        let total = 0;
        scoreSelects.forEach(select => { total += Number(select.value) || 0; });
        totalElement.textContent = total;

        if (total >= 20) {
            summaryElement.textContent = 'ผ่าน';
            summaryElement.className = 'fw-bold text-success';
        } else {
            summaryElement.textContent = 'ไม่ผ่าน';
            summaryElement.className = 'fw-bold text-danger';
        }
    }
    
    scoreSelects.forEach(select => select.addEventListener('change', calculateTotalPart2));
    calculateTotalPart2();
    
    function updateSelectedBackground() {
        document.querySelectorAll('.form-check.item-selectable').forEach(div => {
            const input = div.querySelector('.form-check-input');
            if (input && input.checked) {
                div.classList.add('is-checked');
            } else {
                div.classList.remove('is-checked');
            }
        });
    }
    
    document.querySelectorAll('.form-check-input.custom-input-lg').forEach(input => {
        input.addEventListener('change', function() {
            if (this.type === 'radio') {
                document.querySelectorAll(`input[name="${this.name}"]`).forEach(radio => {
                    const parentDiv = radio.closest('.form-check.item-selectable');
                    if (parentDiv) {
                        parentDiv.classList.toggle('is-checked', radio.checked);
                    }
                });
            } else {
                const parentDiv = this.closest('.form-check.item-selectable');
                if (parentDiv) {
                    parentDiv.classList.toggle('is-checked', this.checked);
                }
            }
        });
    });

    updateSelectedBackground();
});
</script>

<?php include 'includes/footer.php'; ?>