<?php
include 'includes/header.php'; // Correct path assumed
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_login();

if (!isset($_GET['contest_id'])) {
    header("Location: /index.php"); // Use root path
    exit();
}
$contest_id = $_GET['contest_id'];
$user_id = $_SESSION['user_id'];

// --- 1. ดึงข้อมูลการประกวด ---
$stmt_contest = $pdo->prepare("SELECT * FROM contests WHERE id = ?");
$stmt_contest->execute([$contest_id]);
$contest = $stmt_contest->fetch();
if (!$contest) { die("ไม่พบการประกวดนี้"); }

// --- ตรวจสอบสถานะการประกวด ---
if ($contest['status'] === 'closed') {
    $_SESSION['message_error'] = "การประกวด '" . htmlspecialchars($contest['title']) . "' ได้ปิดรับคะแนนแล้ว";
    header("Location: /index.php"); // Use root path
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
    // Correctly handle Part 1 storage based on submit_vote.php logic
    if (strpos($score['criterion_key'], 'part1_') === 0 && $score['score'] == 1) {
        $previous_scores['part1'][$score['criterion_key']] = 1; // Mark as checked
    } elseif ($score['criterion_key'] === 'part3_process' || $score['criterion_key'] === 'part3_impact'){
        $previous_scores[$score['criterion_key']] = $score['score']; // Part 3 radios store the ID
    } else {
        $previous_scores[$score['criterion_key']] = $score['score']; // Other parts store score directly
    }
}
$part1_previous_selections = $previous_scores['part1'] ?? [];


// --- 4. เตรียมข้อมูลสำหรับ JavaScript ---
$js_process_map = [];
if (isset($criteria_by_part['part3_process'])) {
    foreach($criteria_by_part['part3_process'] as $c) {
        $js_process_map[$c['id']] = $c['title'];
    }
}
$js_impact_map = [];
if (isset($criteria_by_part['part3_impact'])) {
    foreach($criteria_by_part['part3_impact'] as $c) {
        $js_impact_map[$c['id']] = $c['title'];
    }
}

?>

<div class="container">
    <a href="/index.php" class="btn btn-secondary mb-3"><i class="bi bi-chevron-left"></i> กลับไปหน้ารายการ</a> {/* Use root path */}
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

            <form action="/submit_vote.php?contest_id=<?php echo $contest_id; ?>" method="post"> {/* Use root path */}
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
                                <?php if (isset($criteria_by_part['part1'])): ?>
                                    <?php foreach ($criteria_by_part['part1'] as $c): ?>
                                        <div class="form-check item-selectable d-flex justify-content-between align-items-center border-bottom py-3">
                                            <label class="form-check-label flex-grow-1" for="crit_<?php echo $c['criterion_key']; ?>">
                                                <strong class="d-block"><?php echo htmlspecialchars($c['title']); ?></strong>
                                                <small class="text-muted"><?php echo nl2br(htmlspecialchars($c['description'])); ?></small>
                                            </label>
                                            <input class="form-check-input custom-input-lg ms-3" type="checkbox" name="scores[part1][<?php echo $c['criterion_key']; ?>]" id="crit_<?php echo $c['criterion_key']; ?>" value="1" <?php if (isset($part1_previous_selections[$c['criterion_key']])) echo 'checked'; ?>>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-warning">ยังไม่ได้กำหนดเกณฑ์สำหรับ Part 1</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button fs-5 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                <i class="bi bi-sliders me-2"></i> Part 2 : ลงคะแนนเกณฑ์การตัดสิน
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#votingAccordion">
                            <div class="accordion-body">
                                <p class="text-muted">ให้คะแนนในแต่ละเกณฑ์ (ข้อละ 10 คะแนน)</p>
                                <?php if (isset($criteria_by_part['part2'])): ?>
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
                                                <strong><?php echo htmlspecialchars($c['title']); ?></strong>
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
                                            <h5 class="mb-0">รวม (เต็ม <?php echo isset($criteria_by_part['part2']) ? count($criteria_by_part['part2']) * 10 : 0; ?> คะแนน): <span id="part2_total" class="fw-bold">0</span></h5>
                                        </div>
                                        <div>
                                            <h5 class="mb-0">สรุปผล: <span id="part2_summary" class="fw-bold"></span></h5>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-warning">ยังไม่ได้กำหนดเกณฑ์สำหรับ Part 2</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item" id="part3_accordion_item"> {/* Added ID */}
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button fs-5 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                <i class="bi bi-trophy-fill me-2"></i> Part 3 : พิจารณาเงินรางวัล
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#votingAccordion">
                            <div class="accordion-body">
                                <?php if (isset($criteria_by_part['part3_process']) && isset($criteria_by_part['part3_impact'])): ?>
                                    <p><strong>โปรดเลือก Process Degrees ที่เข้าข่าย (เลือกได้ 1 ข้อ)</strong></p>
                                    <?php foreach ($criteria_by_part['part3_process'] as $c): ?>
                                    <div class="form-check item-selectable d-flex justify-content-between align-items-center border-bottom py-3">
                                        <label class="form-check-label d-flex align-items-center flex-grow-1" for="crit_<?php echo $c['id']; ?>">
                                            <?php if (!empty($c['image_path'])): ?>
                                            <img src="/assets/images/<?php echo $c['image_path']; ?>" alt="<?php echo htmlspecialchars($c['title']); ?>" style="width: 100px; height: auto; margin-right: 15px; border-radius: 4px;">
                                            <?php endif; ?>
                                            <span class="fw-bold"><?php echo htmlspecialchars($c['title']); ?></span>
                                        </label>
                                         {/* ****** ส่วนที่แก้ไข: ลบ required ****** */}
                                         <input class="form-check-input custom-input-lg ms-3 part3-radio" type="radio" name="scores[part3_process]" id="crit_<?php echo $c['id']; ?>" value="<?php echo $c['id']; ?>" <?php if (isset($previous_scores['part3_process']) && $previous_scores['part3_process'] == $c['id']) echo 'checked'; ?>>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <p class="mt-4"><strong>โปรดเลือก Impact Degrees ที่เข้าข่าย (เลือกได้ 1 ข้อ)</strong></p>
                                    <?php foreach ($criteria_by_part['part3_impact'] as $c): ?>
                                    <div class="form-check item-selectable d-flex justify-content-between align-items-center border-bottom py-3">
                                        <label class="form-check-label flex-grow-1" for="crit_<?php echo $c['id']; ?>">
                                            <?php echo htmlspecialchars($c['title']); ?>
                                            <small class="d-block text-muted"><?php echo nl2br(htmlspecialchars($c['description'])); ?></small>
                                        </label>
                                         {/* ****** ส่วนที่แก้ไข: ลบ required ****** */}
                                         <input class="form-check-input custom-input-lg ms-3 part3-radio" type="radio" name="scores[part3_impact]" id="crit_<?php echo $c['id']; ?>" value="<?php echo $c['id']; ?>" <?php if (isset($previous_scores['part3_impact']) && $previous_scores['part3_impact'] == $c['id']) echo 'checked'; ?>>
                                    </div>
                                    <?php endforeach; ?>

                                    <div class="mt-4 p-3 bg-light rounded">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tbody>
                                                <tr><td class="fw-bold">สรุป:</td><td id="part3_summary_text" class="fw-bold">-</td></tr>
                                                <tr><td class="fw-bold">พิจารณารางวัลในระดับ:</td><td id="part3_level" class="fw-bold text-primary fs-5">-</td></tr>
                                                <tr class="bg-dark text-white"><td class="fw-bold">เงินรางวัล (บาท):</td><td id="part3_prize" class="fw-bold fs-4">-</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                     <p class="text-warning">ยังไม่ได้กำหนดเกณฑ์สำหรับ Part 3 Process หรือ Part 3 Impact</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึกคะแนนทั้งหมด</button>
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
    
    // ****** ส่วนที่แก้ไข: เพิ่มตัวแปรสำหรับ Part 3 ******
    const part3AccordionItem = document.getElementById('part3_accordion_item');
    const part3Button = part3AccordionItem ? part3AccordionItem.querySelector('.accordion-button') : null;
    const part3Collapse = document.getElementById('collapseThree');
    const part3Radios = document.querySelectorAll('.part3-radio');
    const summaryTextEl = document.getElementById('part3_summary_text');
    const levelEl = document.getElementById('part3_level');
    const prizeEl = document.getElementById('part3_prize');

    function calculateTotalPart2() {
        let total = 0;
        scoreSelects.forEach(select => { total += Number(select.value) || 0; });
        totalElement.textContent = total;
        
        // ****** ส่วนที่แก้ไข: กำหนดค่า passing score ******
        const maxScorePart2 = (scoreSelects.length * 10);
        const passingScore = maxScorePart2 / 2; // Pass if score is 50% or more

        // ****** ส่วนที่แก้ไข: เพิ่ม Logic เปิด/ปิด Part 3 ******
        if (total >= passingScore) {
            summaryElement.textContent = 'ผ่าน';
            summaryElement.className = 'fw-bold text-success';
            if(part3AccordionItem) part3AccordionItem.classList.remove('disabled-accordion');
            if(part3Button) part3Button.disabled = false;
            // เปิดใช้งาน Radio buttons ใน Part 3
            part3Radios.forEach(radio => radio.disabled = false);
            calculatePrize(); // คำนวณรางวัลเมื่อผ่าน
        } else {
            summaryElement.textContent = 'ไม่ผ่าน';
            summaryElement.className = 'fw-bold text-danger';
            if(part3AccordionItem) part3AccordionItem.classList.add('disabled-accordion');
            if(part3Button) part3Button.disabled = true;
            // ปิดใช้งาน Radio buttons ใน Part 3 และล้างค่าที่เลือก
            part3Radios.forEach(radio => {
                radio.checked = false; 
                radio.disabled = true;
            }); 
            if(part3Collapse) {
                const bsCollapse = bootstrap.Collapse.getInstance(part3Collapse);
                if (bsCollapse) bsCollapse.hide();
                else if (part3Collapse.classList.contains('show')) {
                     part3Collapse.classList.remove('show');
                     if(part3Button) part3Button.classList.add('collapsed');
                     if(part3Button) part3Button.setAttribute('aria-expanded', 'false');
                }
            }
            // รีเซ็ตค่าสรุป Part 3
            if(summaryTextEl) summaryTextEl.textContent = '-';
            if(levelEl) levelEl.textContent = '-';
            if(prizeEl) prizeEl.textContent = '-';
        }
    }
    scoreSelects.forEach(select => select.addEventListener('change', calculateTotalPart2));
    

    const processMap = <?php echo json_encode($js_process_map); ?>;
    const impactMap = <?php echo json_encode($js_impact_map); ?>;

    const prizeLevelMap = { /* ... สูตรเดิม ... */ };
    const prizeMoneyMap = { /* ... สูตรเดิม ... */ };

    function calculatePrize() {
        // ****** ส่วนที่แก้ไข: ตรวจสอบก่อนว่า Part 3 ถูกเปิดใช้งานหรือไม่ ******
        if (part3AccordionItem && part3AccordionItem.classList.contains('disabled-accordion')) {
            if(summaryTextEl) summaryTextEl.textContent = '-'; 
            if(levelEl) levelEl.textContent = '-'; 
            if(prizeEl) prizeEl.textContent = '-';
            return; // ออกจากฟังก์ชันถ้า Part 3 ถูกปิด
        }
        
        const selectedProcess = document.querySelector('input[name="scores[part3_process]"]:checked');
        const selectedImpact = document.querySelector('input[name="scores[part3_impact]"]:checked');
        if (selectedProcess && selectedImpact) {
            const processText = processMap[selectedProcess.value];
            const impactText = impactMap[selectedImpact.value];
            if (!processText || !impactText) {
                 summaryTextEl.textContent = '-'; levelEl.textContent = '-'; prizeEl.textContent = '-';
                 return;
            }
            const combinationKey = `${processText}${impactText}`;
            summaryTextEl.textContent = `${processText}-${impactText}`;
            levelEl.textContent = prizeLevelMap[combinationKey] || '-';
            const prizeAmount = prizeMoneyMap[combinationKey] || 0;
            prizeEl.textContent = prizeAmount.toLocaleString('en-US');
        } else {
            summaryTextEl.textContent = '-'; levelEl.textContent = '-'; prizeEl.textContent = '-';
        }
    }
    part3Radios.forEach(radio => radio.addEventListener('change', calculatePrize));
    
    // ****** ส่วนที่แก้ไข: เรียก calculateTotalPart2() ก่อนเสมอ ******
    calculateTotalPart2(); // คำนวณ Part 2 และเปิด/ปิด Part 3 ก่อน
    calculatePrize(); // แล้วค่อยคำนวณ Part 3

    function updateSelectedBackground() { /* ... โค้ด UI เดิม ... */ }
    document.querySelectorAll('.form-check-input.custom-input-lg').forEach(input => { /* ... โค้ด UI เดิม ... */ });
    updateSelectedBackground();
});
</script>

<?php include 'includes/footer.php'; ?>