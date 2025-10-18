<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// --- ดึงข้อมูลสถิติ ---
try {
    $count_contests = $pdo->query("SELECT COUNT(*) FROM contests")->fetchColumn();
    $count_entries = $pdo->query("SELECT COUNT(*) FROM entries")->fetchColumn(); 
    $count_judges = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'judge'")->fetchColumn();
} catch (PDOException $e) {
    $count_entries = 0; 
}
?>

<div class="container-fluid">
    <?php if (isset($_SESSION['admin_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['admin_message']; unset($_SESSION['admin_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
     <?php if (isset($_SESSION['admin_message_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['admin_message_error']; unset($_SESSION['admin_message_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <h1 class="mb-4">แผงควบคุมผู้ดูแลระบบ</h1>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">หัวข้อประกวดทั้งหมด</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count_contests; ?></div>
                        </div>
                        <div class="col-auto"><i class="bi bi-card-checklist fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">ผลงานที่ส่ง (Entries)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count_entries; ?></div>
                        </div>
                        <div class="col-auto"><i class="bi bi-person-workspace fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">จำนวนกรรมการ</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count_judges; ?></div>
                        </div>
                        <div class="col-auto"><i class="bi bi-people-fill fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12"> <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-list-task me-2"></i>เมนูจัดการหลัก</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="manage_contests.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <span>
                                <i class="bi bi-card-checklist me-2 text-primary"></i> 
                                <strong>จัดการหัวข้อการประกวด</strong>
                            </span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="manage_judges.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <span>
                                <i class="bi bi-people-fill me-2 text-success"></i> 
                                <strong>จัดการกรรมการ</strong>
                            </span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="manage_criteria.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <span>
                                <i class="bi bi-sliders me-2 text-info"></i> 
                                <strong>จัดการเกณฑ์การตัดสิน</strong>
                            </span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="summary_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <span>
                                <i class="bi bi-bar-chart-line-fill me-2 text-warning"></i> 
                                <strong>ดูผลสรุปคะแนนรวม</strong>
                            </span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="view_logs.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <span>
                                <i class="bi bi-journal-text me-2 text-secondary"></i> 
                                <strong>ดู Log การใช้งาน</strong>
                            </span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                        <a href="session_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <span>
                                <i class="bi bi-clock-history me-2 text-info"></i> 
                                <strong>รายงานการเข้าใช้งาน (Session)</strong>
                            </span>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div> <div class="row mt-4"> 
        <div class="col-lg-12"> <div class="card shadow mb-4 border-danger">
                <a href="#collapseAdvanced" class="card-header py-3 bg-danger text-white text-decoration-none d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="collapseAdvanced">
                    <h6 class="m-0 font-weight-bold"><i class="bi bi-exclamation-triangle-fill"></i> การตั้งค่าขั้นสูง</h6>
                    <i class="bi bi-chevron-down"></i>
                </a>
                <div class="collapse" id="collapseAdvanced">
                    <div class="card-body text-center">
                        <p class="text-danger">การกระทำต่อไปนี้จะลบข้อมูลออกจากระบบอย่างถาวร</p>
                        <button id="clear-scores-btn" class="btn btn-warning"><i class="bi bi-eraser-fill"></i> เคลียร์ข้อมูลคะแนนทั้งหมด</button>
                        <button id="clear-all-btn" class="btn btn-danger"><i class="bi bi-trash3-fill"></i> เคลียร์ข้อมูลการประกวดและคะแนนทั้งหมด</button>
                    </div>
                </div>
            </div>
        </div>
    </div> </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function showPasswordConfirmDialog(options) {
        Swal.fire({
            title: options.title,
            text: options.text,
            icon: options.icon,
            html: `
                <p>${options.text}</p>
                <input type="password" id="admin-password" class="swal2-input" placeholder="กรอกรหัสผ่าน Admin เพื่อยืนยัน">
            `,
            showCancelButton: true,
            confirmButtonColor: options.confirmButtonColor,
            confirmButtonText: options.confirmButtonText,
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const password = document.getElementById('admin-password').value;
                if (!password) {
                    Swal.showValidationMessage('กรุณากรอกรหัสผ่าน');
                    return false;
                }
                return password;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'clear_data.php';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = options.action;
                form.appendChild(actionInput);

                const passwordInput = document.createElement('input');
                passwordInput.name = 'admin_password';
                passwordInput.value = result.value;
                form.appendChild(passwordInput);

                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    document.getElementById('clear-scores-btn').addEventListener('click', function() {
        showPasswordConfirmDialog({
            title: 'ยืนยันการล้างข้อมูลคะแนน?',
            text: "ข้อมูลคะแนนทั้งหมดจะถูกลบอย่างถาวร!",
            icon: 'warning',
            confirmButtonColor: '#d33',
            confirmButtonText: 'ใช่, ลบเลย!',
            action: 'clear_scores'
        });
    });

    document.getElementById('clear-all-btn').addEventListener('click', function() {
        showPasswordConfirmDialog({
            title: 'ยืนยันการล้างข้อมูลทั้งหมด?',
            text: "ข้อมูลการประกวดและคะแนนทั้งหมดจะถูกลบอย่างถาวร!",
            icon: 'error',
            confirmButtonColor: '#d33',
            confirmButtonText: 'ใช่, ลบทั้งหมด!',
            action: 'clear_all'
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>