<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// --- ดึงข้อมูล Log ที่เกี่ยวข้องกับการเข้าสู่ระบบ ---
$stmt = $pdo->query("SELECT * FROM logs 
                     WHERE action = 'login_success' OR action = 'logout' 
                     ORDER BY timestamp DESC");
$logs = $stmt->fetchAll();

// --- จัดการข้อมูลเพื่อจับคู่ Login/Logout ---
$sessions = [];
$session_data = [];

foreach ($logs as $log) {
    $user_id = $log['user_id'];
    
    if ($log['action'] === 'logout') {
        // ถ้าเจอ Logout ให้มองหา Login ล่าสุดของ User คนนี้ที่ยังไม่มีคู่
        if (isset($sessions[$user_id])) {
            $login_log = array_shift($sessions[$user_id]); // ดึง Login ล่าสุดออก
            
            $login_time = new DateTime($login_log['timestamp']);
            $logout_time = new DateTime($log['timestamp']);
            
            // คำนวณระยะเวลาจากรายละเอียด ถ้าไม่มี ให้คำนวณใหม่
            $duration = $log['details'];
            if (empty($duration)) {
                 $interval = $logout_time->diff($login_time);
                 $minutes_spent = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                 $duration = $minutes_spent . ' minutes';
            }

            $session_data[] = [
                'username' => $log['username'],
                'login_date' => $login_time->format('d M Y'),
                'login_time' => $login_time->format('H:i:s'),
                'logout_time' => $logout_time->format('H:i:s'),
                'duration' => $duration
            ];
        }
    } elseif ($log['action'] === 'login_success') {
        // ถ้าเจอ Login ให้เก็บไว้รอคู่ Logout
        $sessions[$user_id][] = $log;
    }
}
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
                    <?php if (empty($session_data)): ?>
                        <tr><td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูล Session</td></tr>
                    <?php else: ?>
                        <?php foreach ($session_data as $session): ?>
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