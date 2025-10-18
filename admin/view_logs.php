<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// ****** นี่คือส่วนที่แก้ไข ******
// 1. ตั้งค่า Timezone ให้เป็นเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// --- LOGIC FOR PAGINATION ---
$per_page = 25; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// --- FETCH LOGS WITH PAGINATION ---
$stmt = $pdo->prepare("SELECT * FROM logs ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// --- COUNT TOTAL LOGS FOR PAGINATION ---
$total_logs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

?>

<h1 class="mb-4">บันทึกการใช้งานระบบ (Logs)</h1>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th style="width: 20%;">เวลา</th>
                        <th style="width: 15%;">ผู้ใช้งาน</th>
                        <th style="width: 20%;">การกระทำ (Action)</th>
                        <th>รายละเอียด</th>
                        <th style="width: 15%;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูลบันทึก</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date("d M Y, H:i:s", strtotime($log['timestamp'])); ?></td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>


<?php include '../includes/footer.php'; ?>