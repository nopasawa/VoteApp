<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// --- LOGIC FOR ADD/EDIT/DELETE ---
$edit_mode = false;
$contest_to_edit = null;

// Handle POST request (Add/Update)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_FILES['csv_file'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    $contest_date = !empty($_POST['contest_date']) ? $_POST['contest_date'] : null;
    $full_name = $_POST['full_name'];
    $department = $_POST['department'];
    $division = $_POST['division'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    if (isset($_POST['add_contest'])) {
        $sql = "INSERT INTO contests (title, description, status, contest_date, full_name, department, division, phone, email) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $status, $contest_date, $full_name, $department, $division, $phone, $email]);
        log_action('add_contest', 'Title: ' . $title);

    } elseif (isset($_POST['update_contest'])) {
        $id = $_POST['id'];
        $sql = "UPDATE contests SET title = ?, description = ?, status = ?, contest_date = ?, full_name = ?, department = ?, division = ?, phone = ?, email = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $status, $contest_date, $full_name, $department, $division, $phone, $email, $id]);
        log_action('update_contest', 'Updated contest ID: ' . $id);
    }
    header("Location: manage_contests.php");
    exit();
}

// Handle GET request (Edit/Delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action == 'edit') {
        $edit_mode = true;
        $stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ?");
        $stmt->execute([$id]);
        $contest_to_edit = $stmt->fetch();
    } elseif ($action == 'delete') {
        $stmt_title = $pdo->prepare("SELECT title FROM contests WHERE id = ?");
        $stmt_title->execute([$id]);
        $deleted_title = $stmt_title->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM contests WHERE id = ?");
        $stmt->execute([$id]);
        
        log_action('delete_contest', 'Deleted contest: ' . $deleted_title . ' (ID: ' . $id . ')');
        $_SESSION['admin_message'] = "ลบการประกวดสำเร็จ!";
        header("Location: manage_contests.php");
        exit();
    }
}

// --- FETCH ALL CONTESTS ---
$stmt = $pdo->prepare("SELECT * FROM contests ORDER BY contest_date ASC, id ASC");
$stmt->execute();
$all_contests = $stmt->fetchAll();

$contests_by_date = [];
foreach ($all_contests as $contest) {
    $date = $contest['contest_date'] ?? 'ไม่ระบุวันที่';
    $contests_by_date[$date][] = $contest;
}

?>

<h1 class="mb-4">จัดการหัวข้อการประกวด</h1>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-file-earmark-arrow-up-fill"></i> Import ข้อมูลจากไฟล์ CSV
            </div>
            <div class="card-body">
                <form action="import_contests.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">เลือกไฟล์ CSV (UTF-8)</label>
                        <input class="form-control" type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <div class="form-text">ไฟล์ต้องมีคอลัมน์เรียงตามลำดับดังนี้: Project, ชื่อ-นามสกุล, Department, Division, เบอร์โทร, E-mail, วันที่ประกวด (YYYY-MM-DD)</div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload"></i> อัปโหลดและนำเข้าข้อมูล
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-4">
            
            <a href="#collapseAddForm" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo $edit_mode ? 'true' : 'false'; ?>" aria-controls="collapseAddForm" class="card-header text-decoration-none d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi <?php echo $edit_mode ? 'bi-pencil-square' : 'bi-plus-circle-fill'; ?>"></i> <?php echo $edit_mode ? 'แก้ไขการประกวด' : 'เพิ่มการประกวด (ทีละรายการ)'; ?>
                </span>
                <i class="bi bi-chevron-down"></i>
            </a>
            
            <div class="collapse <?php if ($edit_mode) echo 'show'; ?>" id="collapseAddForm">
                <div class="card-body">
                    <form action="manage_contests.php" method="POST">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $contest_to_edit['id']; ?>">
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">ชื่อหัวข้อการประกวด</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_mode ? htmlspecialchars($contest_to_edit['title']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $edit_mode ? htmlspecialchars($contest_to_edit['full_name']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?php echo $edit_mode ? htmlspecialchars($contest_to_edit['department']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="division" class="form-label">Division</label>
                                <input type="text" class="form-control" id="division" name="division" value="<?php echo $edit_mode ? htmlspecialchars($contest_to_edit['division']) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์มือถือ</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $edit_mode ? htmlspecialchars($contest_to_edit['phone']) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $edit_mode ? htmlspecialchars($contest_to_edit['email']) : ''; ?>">
                            </div>
                             <div class="col-md-4 mb-3">
                                <label for="contest_date" class="form-label">วันที่ประกวด</label>
                                <input type="date" class="form-control" id="contest_date" name="contest_date" value="<?php echo $edit_mode ? htmlspecialchars($contest_to_edit['contest_date']) : ''; ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="description" class="form-label">คำอธิบาย (ถ้ามี)</label>
                                <textarea class="form-control" id="description" name="description" rows="2"><?php echo $edit_mode ? htmlspecialchars($contest_to_edit['description']) : ''; ?></textarea>
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">สถานะ</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo ($edit_mode && $contest_to_edit['status'] == 'active') ? 'selected' : ''; ?>>Active (เปิดให้ลงคะแนน)</option>
                                    <option value="closed" <?php echo ($edit_mode && $contest_to_edit['status'] == 'closed') ? 'selected' : ''; ?>>Closed (ปิดรับคะแนน)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                 <?php if ($edit_mode): ?>
                                    <button type="submit" name="update_contest" class="btn btn-primary me-2">อัปเดต</button>
                                    <a href="manage_contests.php" class="btn btn-secondary">ยกเลิก</a>
                                <?php else: ?>
                                    <button type="submit" name="add_contest" class="btn btn-success">เพิ่มการประกวด</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <?php if (empty($contests_by_date)): ?>
            <div class="card">
                <div class="card-header"><i class="bi bi-list-ul"></i> รายการประกวดทั้งหมด</div>
                <div class="card-body text-center">ยังไม่มีข้อมูลการประกวด</div>
            </div>
        <?php else: ?>
            <?php $row_number = 1; ?>
            <?php foreach ($contests_by_date as $date => $contests): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-calendar-event"></i> 
                        <strong>
                            <?php echo $date === 'ไม่ระบุวันที่' ? 'ไม่ระบุวันที่' : 'รายการประกวด วันที่ ' . date("d F Y", strtotime($date)); ?>
                        </strong>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">ลำดับ</th>
                                        <th>หัวข้อ / ผู้ส่งประกวด</th>
                                        <th class="text-center" style="width: 15%;">สถานะ</th>
                                        <th class="text-center" style="width: 25%;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contests as $contest): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $row_number++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($contest['title']); ?></strong>
                                            <?php if(!empty($contest['full_name'])): ?>
                                                <small class="d-block text-muted">โดย: <?php echo htmlspecialchars($contest['full_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($contest['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view_results.php?contest_id=<?php echo $contest['id']; ?>" class="btn btn-sm btn-outline-primary" title="ดูผลคะแนน"><i class="bi bi-bar-chart-line-fill"></i></a>
                                                
                                                <a href="clear_data.php?action=clear_scores_for_contest&id=<?php echo $contest['id']; ?>" class="btn btn-sm btn-outline-warning clear-scores-btn" title="เคลียร์คะแนนกลุ่มนี้"><i class="bi bi-eraser"></i></a>

                                                <a href="manage_contests.php?action=edit&id=<?php echo $contest['id']; ?>" class="btn btn-sm btn-secondary" title="แก้ไข"><i class="bi bi-pencil-square"></i></a>
                                                <a href="manage_contests.php?action=delete&id=<?php echo $contest['id']; ?>" class="btn btn-sm btn-danger delete-btn" title="ลบ"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


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
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = options.action;
                form.appendChild(actionInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = options.id;
                form.appendChild(idInput);

                const passwordInput = document.createElement('input');
                passwordInput.type = 'hidden';
                passwordInput.name = 'admin_password';
                passwordInput.value = result.value;
                form.appendChild(passwordInput);

                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const deleteUrl = this.href;
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณจะไม่สามารถกู้คืนข้อมูลนี้ได้!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = deleteUrl;
                }
            });
        });
    });

    document.querySelectorAll('.clear-scores-btn').forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const url = new URL(this.href);
            showPasswordConfirmDialog({
                title: 'ยืนยันการเคลียร์คะแนน?',
                text: "คะแนนทั้งหมดของกลุ่มนี้จะถูกลบอย่างถาวร!",
                icon: 'warning',
                confirmButtonColor: '#f0ad4e',
                confirmButtonText: 'ใช่, เคลียร์คะแนน!',
                action: 'clear_scores_for_contest',
                id: url.searchParams.get('id')
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>