<?php
// ****** นี่คือส่วนที่แก้ไข: เพิ่ม session_start() ******
session_start();

// หน้านี้ไม่จำเป็นต้อง include header/footer เพราะเป็นหน้า Fullscreen
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// ดึงข้อมูลทั้งหมดมาคำนวณเพื่อหา Top 3 (โค้ดส่วนนี้เหมือนกับ summary_report.php)
$stmt_contests = $pdo->prepare("SELECT * FROM contests ORDER BY id ASC");
$stmt_contests->execute();
$contests = $stmt_contests->fetchAll(PDO::FETCH_ASSOC);

$stmt_criteria = $pdo->query("SELECT * FROM criteria");
$all_criteria = $stmt_criteria->fetchAll();
$criteria_map_by_id = []; $part1_key_map = []; $part2_keys = [];
foreach ($all_criteria as $criterion) {
    $criteria_map_by_id[$criterion['id']] = $criterion['title'];
    if ($criterion['part'] === 'part1') $part1_key_map[$criterion['criterion_key']] = $criterion['title'];
    if ($criterion['part'] === 'part2') $part2_keys[] = $criterion['criterion_key'];
}

$prizeLevelMap = [ "Modify (ปรับปรุง)Division"=>"Silver", "Modify (ปรับปรุง)Sub-Business"=>"Silver", "Modify (ปรับปรุง)CGS"=>"Gold", "Improve (พัฒนา)Division"=>"Silver", "Improve (พัฒนา)Sub-Business"=>"Gold", "Improve (พัฒนา)CGS"=>"Gold", "Breakthrough (สร้างใหม่)Division"=>"Gold", "Breakthrough (สร้างใหม่)Sub-Business"=>"Gold", "Breakthrough (สร้างใหม่)CGS"=>"Platinum" ];
$prizeMoneyMap = [ "Modify (ปรับปรุง)Division"=>100000, "Modify (ปรับปรุง)Sub-Business"=>100000, "Modify (ปรับปรุง)CGS"=>150000, "Improve (พัฒนา)Division"=>100000, "Improve (พัฒนา)Sub-Business"=>150000, "Improve (พัฒนา)CGS"=>150000, "Breakthrough (สร้างใหม่)Division"=>150000, "Breakthrough (สร้างใหม่)Sub-Business"=>150000, "Breakthrough (สร้างใหม่)CGS"=>200000 ];

function find_most_frequent($arr) { if (empty($arr)) return null; $counts = array_count_values($arr); arsort($counts); return key($counts); }

$final_results = [];
foreach ($contests as $contest) {
    $stmt_scores = $pdo->prepare("SELECT user_id, criterion_key, score FROM scores WHERE entry_id = ?");
    $stmt_scores->execute([$contest['id']]);
    $all_scores = $stmt_scores->fetchAll();
    $scores_for_this_contest = [];
    if (!empty($part2_keys)) {
        $stmt_part2 = $pdo->prepare("SELECT user_id, SUM(score) as total_part2 FROM scores WHERE entry_id = ? AND criterion_key IN (" . implode(',', array_fill(0, count($part2_keys), '?')) . ") GROUP BY user_id");
        $stmt_part2->execute(array_merge([$contest['id']], $part2_keys));
        foreach($stmt_part2->fetchAll() as $row) { $scores_for_this_contest[] = $row['total_part2']; }
    }
    $average = 0;
    if (!empty($scores_for_this_contest)) {
        $min_score = min($scores_for_this_contest); $max_score = max($scores_for_this_contest);
        if ($min_score == $max_score) { $average = $min_score; } else {
            $sum_of_black = 0; $count_of_black = 0;
            foreach ($scores_for_this_contest as $score) { if ($score != $min_score && $score != $max_score) { $sum_of_black += $score; $count_of_black++; } }
            $average = ($count_of_black > 0) ? ($sum_of_black / $count_of_black) : 0;
        }
    }
    $part3_process_choices = []; $part3_impact_choices = [];
    foreach ($all_scores as $score) {
        if ($score['criterion_key'] == 'part3_process') $part3_process_choices[] = $score['score'];
        if ($score['criterion_key'] == 'part3_impact') $part3_impact_choices[] = $score['score'];
    }
    $process_text = $criteria_map_by_id[find_most_frequent($part3_process_choices)] ?? '';
    $impact_text = $criteria_map_by_id[find_most_frequent($part3_impact_choices)] ?? '';
    $original_amount = $prizeMoneyMap[$process_text . $impact_text] ?? 0;
    $final_amount = 0;
    if ($average >= 35) $final_amount = $original_amount * 1; elseif ($average >= 20) $final_amount = $original_amount * 0.75;
    
    $final_results[] = [
        'id' => $contest['id'],
        'title' => $contest['title'],
        'average_score' => $average,
        'final_amount' => $final_amount,
        'judge_scores' => $scores_for_this_contest
    ];
}

// จัดเรียงผู้ชนะตามเงินรางวัล -> คะแนนเฉลี่ย
usort($final_results, function($a, $b) {
    if ($a['final_amount'] == $b['final_amount']) {
        return $b['average_score'] <=> $a['average_score'];
    }
    return $b['final_amount'] <=> $a['final_amount'];
});

// ดึง Top 3
$winners = array_slice($final_results, 0, 3);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Presentation ผลการประกวด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; font-family: sans-serif; }
        .slide { width: 100vw; height: 100vh; display: none; justify-content: center; align-items: center; text-align: center; flex-direction: column; padding: 40px; background: #f0f2f5; }
        .slide.active { display: flex; }
        .slide-title { font-size: 4.5rem; font-weight: bold; color: #0d6efd; }
        .slide-subtitle { font-size: 2.5rem; color: #6c757d; }
        .winner-rank { font-size: 3rem; color: #6c757d; }
        .winner-title { font-size: 4rem; font-weight: bold; margin: 20px 0; }
        .ai-summary { font-size: 1.8rem; margin-top: 20px; color: #333; }
        .nav-btn { position: fixed; bottom: 20px; font-size: 2rem; padding: 10px 20px; z-index: 10; }
        #prev-btn { left: 20px; }
        #next-btn { right: 20px; }
        #loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 99; display: flex; justify-content: center; align-items: center; flex-direction: column; }
    </style>
</head>
<body>

    <div id="loading-overlay">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
        <p class="mt-3">AI กำลังสร้างบทสรุป, กรุณารอสักครู่...</p>
    </div>

    <div id="slide-container">
        </div>

    <button id="prev-btn" class="btn btn-secondary nav-btn">&laquo; ก่อนหน้า</button>
    <button id="next-btn" class="btn btn-primary nav-btn">ต่อไป &raquo;</button>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const winnersData = <?php echo json_encode($winners); ?>;
        const slideContainer = document.getElementById('slide-container');
        const loadingOverlay = document.getElementById('loading-overlay');
        let currentSlide = 0;
        let slides = [];

        // ฟังก์ชันสำหรับสร้างสไลด์
        function createSlides(aiSummaries) {
            let slideHTML = `
                <div class="slide active">
                    <h1 class="slide-title">ผลการประกวดนวัตกรรม</h1>
                    <p class="slide-subtitle">CPAC INCENTIVE 2025</p>
                </div>
            `;

            const ranks = ['รางวัลชนะเลิศอันดับ 3', 'รางวัลรองชนะเลิศอันดับ 2', 'รางวัลชนะเลิศ'];
            const winnerKeys = ['winner_3', 'winner_2', 'winner_1'];
            
            [...winnersData].reverse().forEach((winner, index) => {
                slideHTML += `
                    <div class="slide">
                        <p class="winner-rank">${ranks[index]}</p>
                        <h2 class="winner-title">${winner.title}</h2>
                        <div class="row w-100 align-items-center">
                            <div class="col-md-7">
                                <p class="ai-summary">${aiSummaries[winnerKeys[index]] || ''}</p>
                            </div>
                            <div class="col-md-5">
                                <canvas id="chart-${winner.id}"></canvas>
                            </div>
                        </div>
                    </div>
                `;
            });
             slideHTML += `
                <div class="slide">
                    <h1 class="slide-title">ขอแสดงความยินดี</h1>
                    <p class="slide-subtitle">กับทุกทีมที่ได้รับรางวัล</p>
                </div>
            `;

            slideContainer.innerHTML = slideHTML;
            slides = document.querySelectorAll('.slide');

            // สร้างกราฟ
            [...winnersData].reverse().forEach(winner => {
                const ctx = document.getElementById(`chart-${winner.id}`);
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: winner.judge_scores.map((_, i) => `กรรมการ ${i + 1}`),
                        datasets: [{
                            label: 'คะแนน Part 2',
                            data: winner.judge_scores,
                            backgroundColor: 'rgba(13, 110, 253, 0.6)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: { scales: { y: { beginAtZero: true, max: 40 } } }
                });
            });
        }

        // เรียก Gemini API
        fetch('ai_summarize.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contest_data: winnersData })
        })
        .then(response => response.json())
        .then(aiData => {
            createSlides(aiData);
            loadingOverlay.style.display = 'none';
        })
        .catch(error => {
            console.error('Error fetching AI summary:', error);
            createSlides({}); // สร้างสไลด์โดยไม่มีบทพูด AI
            loadingOverlay.style.display = 'none';
        });


        // ระบบ Navigation
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });
            prevBtn.disabled = index === 0;
            nextBtn.disabled = index === slides.length - 1;
        }

        nextBtn.addEventListener('click', () => {
            if (currentSlide < slides.length - 1) {
                currentSlide++;
                showSlide(currentSlide);
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentSlide > 0) {
                currentSlide--;
                showSlide(currentSlide);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') nextBtn.click();
            if (e.key === 'ArrowLeft') prevBtn.click();
        });
    });
    </script>
</body>
</html>