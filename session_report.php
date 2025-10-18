<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// ****** นี่คือส่วนที่แก้ไข ******
// 1. ตั้งค่า Timezone ให้เป็นเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// --- ดึงข้อมูล Log ที่เกี่ยวข้อง โดยเรียงจาก "เก่าไปใหม่" ---
$stmt = $pdo->query("SELECT * FROM logs 
                     WHERE action = 'login_success' OR action = 'logout' 
                     ORDER BY timestamp ASC");
$logs = $stmt->fetchAll();

// --- จัดการข้อมูลเพื่อจับคู่ Login/Logout ---
$active_sessions = []; // เก็บ login ที่ยังไม่มี logout มาคู่
$completed_sessions = []; // เก็บ session ที่สมบูรณ์แล้ว

foreach ($logs as $log) {
    $user_id = $log['user_id'];
    
    if ($log['action'] === 'login_success') {
        $active_sessions[$user_id] = $log;
    } 
    elseif ($log['action'] === 'logout') {
        if (isset($active_sessions[$user_id])) {
            $login_log = $active_sessions[$user_id];
            $logout_log = $log;
            
            $login_time = new DateTime($login_log['timestamp']);
            $logout_time = new DateTime($logout_log['timestamp']);
            
            $duration = $logout_log['details'];
            if (empty($duration) || $duration == '0 minutes') {
                 $interval = $logout_time->diff($login_time);
                 $minutes_spent = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                 $duration = $minutes_spent . ' minutes';
            }

            $completed_sessions[] = [
                'username' => $login_log['username'],
                'login_date' => $login_time->format('d M Y'),
                // ****** นี่คือส่วนที่แก้ไข: ใช้ H:i:s (24 ชั่วโมง) ******
                'login_time' => $login_time->format('H:i:s'),
                'logout_time' => $logout_time->format('H:i:s'),
                'duration' => $duration
            ];
            
            unset($active_sessions[$user_id]);
        }
    }
}

// เรียงลำดับข้อมูลที่สมบูรณ์แล้ว ให้แสดงผลล่าสุดก่อน
usort($completed_sessions, function($a, $b) {
    return strtotime($b['login_date'] . ' ' . $b['login_time']) <=> strtotime($a['login_date'] . ' ' . $a['login_time']);
});

?>

<h1 class="mb-4">รายงานการเข้าใช้งานระบบ (Sessions)</h1>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ผู้ใช้งาน</th>
                        <th>วันที่ Login</th>
                        <th>เวลาที่ Login</th>
                        <th>เวลาที่ Logout</th>
                        <th>เวลาที่ออนไลน์ทั้งหมด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completed_sessions)): ?>
                        <tr><td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูล Session ที่สมบูรณ์ (มีการ Logout)</td></tr>
                    <?php else: ?>
                        <?php foreach ($completed_sessions as $session): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($session['username']); ?></td>
                            <td><?php echo $session['login_date']; ?></td>
                            <td><?php echo $session['login_time']; ?></td>
                            <td><?php echo $session['logout_time']; ?></td>
                            <td><?php echo htmlspecialchars($session['duration']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-info mt-3">
            <strong>หมายเหตุ:</strong> "เวลาที่ออนไลน์ทั้งหมด" จะถูกคำนวณเมื่อผู้ใช้กดปุ่ม "ออกจากระบบ" เท่านั้น หากผู้ใช้ปิดเบราว์เซอร์ไปโดยไม่กดออกจากระบบ จะไม่มีการบันทึกเวลา Logout
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>