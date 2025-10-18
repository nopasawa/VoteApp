<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// --- LOGIC FOR ADD/EDIT/DELETE ---
$edit_mode = false;
$edit_data = null;

// Handle POST request (Add/Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_criterion']) || isset($_POST['add_criterion'])) {
        
        $image_path = $_POST['existing_image_path'] ?? ''; // ใช้รูปเดิมเป็นค่าเริ่มต้น

        // ****** ส่วนจัดการการอัปโหลดรูปภาพ ******
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
            if (in_array($_FILES['image_file']['type'], $allowed_types)) {
                
                $upload_dir = '../assets/images/';
                // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน (เช่น timestamp + ชื่อเดิม)
                $file_extension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
                $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
                    // ถ้าอัปโหลดสำเร็จ ให้ใช้ชื่อไฟล์ใหม่
                    $image_path = $new_filename;
                }
            }
        }
        // ****** สิ้นสุดส่วนจัดการรูปภาพ ******

        $part = $_POST['part'];
        $criterion_key = $_POST['criterion_key'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $type = $_POST['type'];
        $display_order = $_POST['display_order'];

        if (isset($_POST['update_criterion'])) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE criteria SET part=?, criterion_key=?, title=?, description=?, type=?, image_path=?, display_order=? WHERE id=?");
            $stmt->execute([$part, $criterion_key, $title, $description, $type, $image_path, $display_order, $id]);
        } elseif (isset($_POST['add_criterion'])) {
            $stmt = $pdo->prepare("INSERT INTO criteria (part, criterion_key, title, description, type, image_path, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$part, $criterion_key, $title, $description, $type, $image_path, $display_order]);
        }
    }
    header("Location: manage_criteria.php");
    exit();
}

// Handle GET request (Edit/Delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    if ($action == 'edit') {
        $edit_mode = true;
        $stmt = $pdo->prepare("SELECT * FROM criteria WHERE id = ?");
        $stmt->execute([$id]);
        $edit_data = $stmt->fetch();
    } elseif ($action == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM criteria WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: manage_criteria.php");
        exit();
    }
}

// Fetch all criteria for display
$stmt = $pdo->query("SELECT * FROM criteria ORDER BY display_order ASC, part ASC");
$all_criteria = $stmt->fetchAll();
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

    <h1 class="mb-4">จัดการเกณฑ์การตัดสินทั้งหมด</h1>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi <?php echo $edit_mode ? 'bi-pencil-square' : 'bi-plus-circle-fill'; ?>"></i> <?php echo $edit_mode ? 'แก้ไขเกณฑ์' : 'เพิ่มเกณฑ์ใหม่'; ?>
                </div>
                <div class="card-body">
                    <form action="manage_criteria.php" method="POST" enctype="multipart/form-data">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                            <input type="hidden" name="existing_image_path" value="<?php echo $edit_data['image_path'] ?? ''; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Part</label>
                            <select name="part" id="part-select" class="form-select" required>
                                <option value="part1" <?php if($edit_mode && $edit_data['part'] == 'part1') echo 'selected'; ?>>Part 1</option>
                                <option value="part2" <?php if($edit_mode && $edit_data['part'] == 'part2') echo 'selected'; ?>>Part 2</option>
                                <option value="part3_process" <?php if($edit_mode && $edit_data['part'] == 'part3_process') echo 'selected'; ?>>Part 3 (Process)</option>
                                <option value="part3_impact" <?php if($edit_mode && $edit_data['part'] == 'part3_impact') echo 'selected'; ?>>Part 3 (Impact)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Criterion Key</label>
                            <input type="text" name="criterion_key" id="criterion-key-input" class="form-control" value="<?php echo $edit_data['criterion_key'] ?? ''; ?>" <?php if ($edit_mode) echo 'readonly'; ?> required>
                            <div class="form-text">จะสร้างให้อัตโนมัติเมื่อพิมพ์หัวข้อ</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">หัวข้อ</label>
                            <input type="text" name="title" id="title-input" class="form-control" value="<?php echo $edit_data['title'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">คำอธิบาย</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $edit_data['description'] ?? ''; ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ประเภท Input</label>
                            <select name="type" class="form-select" required>
                                <option value="checkbox" <?php if($edit_mode && $edit_data['type'] == 'checkbox') echo 'selected'; ?>>Checkbox</option>
                                <option value="select_10" <?php if($edit_mode && $edit_data['type'] == 'select_10') echo 'selected'; ?>>Dropdown (1-10)</option>
                                <option value="radio" <?php if($edit_mode && $edit_data['type'] == 'radio') echo 'selected'; ?>>Radio</option>
                            </select>
                        </div>
                         
                         <div class="mb-3">
                            <label class="form-label">อัปโหลดรูปภาพ (ถ้ามี)</label>
                            <input type="file" name="image_file" class="form-control">
                            <?php if ($edit_mode && !empty($edit_data['image_path'])): ?>
                                <div class="mt-2">
                                    <small>รูปภาพปัจจุบัน:</small>
                                    <img src="../assets/images/<?php echo $edit_data['image_path']; ?>" style="max-width: 100px; height: auto;">
                                </div>
                            <?php endif; ?>
                         </div>

                         <div class="mb-3">
                            <label class="form-label">ลำดับการแสดงผล</label>
                            <input type="number" name="display_order" class="form-control" value="<?php echo $edit_data['display_order'] ?? '0'; ?>" required>
                        </div>
                        
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update_criterion" class="btn btn-primary">อัปเดตเกณฑ์</button>
                            <a href="manage_criteria.php" class="btn btn-secondary">ยกเลิก</a>
                        <?php else: ?>
                            <button type="submit" name="add_criterion" class="btn btn-success">เพิ่มเกณฑ์</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="card border-danger mt-4">
                <div class="card-header bg-danger text-white"><i class="bi bi-exclamation-triangle-fill"></i> การตั้งค่าขั้นสูง</div>
                <div class="card-body text-center">
                    <p class="text-danger">การกระทำนี้จะล้างข้อมูลเกณฑ์ทั้งหมด</p>
                    <button id="clear-criteria-btn" class="btn btn-danger"><i class="bi bi-trash3-fill"></i> เคลียร์ข้อมูลเกณฑ์ทั้งหมด</button>
                </div>
            </div>
            <form id="clear-criteria-form" action="clear_data.php" method="POST" style="display:none;"><input type="hidden" name="action" value="clear_criteria"></form>
        </div>

        <div class="col-lg-8">
             <div class="card">
                <div class="card-header"><i class="bi bi-list-ul"></i> เกณฑ์ทั้งหมดในระบบ</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead><tr><th>ลำดับ</th><th>Part</th><th>หัวข้อ</th><th>ประเภท</th><th>จัดการ</th></tr></thead>
                            <tbody>
                                <?php if (empty($all_criteria)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูล</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_criteria as $c): ?>
                                    <tr>
                                        <td><?php echo $c['display_order']; ?></td>
                                        <td><?php echo $c['part']; ?></td>
                                        <td><?php echo $c['title']; ?></td>
                                        <td><span class="badge bg-info"><?php echo $c['type']; ?></span></td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-secondary" title="แก้ไข"><i class="bi bi-pencil-square"></i></a>
                                            <a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger delete-btn" title="ลบ"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const deleteUrl = this.href;
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "การลบเกณฑ์นี้อาจส่งผลต่อคะแนนที่เคยบันทึกไว้!",
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
    
    document.getElementById('clear-criteria-btn').addEventListener('click', function() {
        Swal.fire({
            title: 'ยืนยันการล้างข้อมูลเกณฑ์ทั้งหมด?',
            text: "ข้อมูลเกณฑ์การตัดสินทั้งหมดจะถูกลบอย่างถาวร! คุณจะต้องเพิ่มเกณฑ์ทั้งหมดเข้ามาใหม่",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ล้างข้อมูลทั้งหมด!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('clear-criteria-form').submit();
            }
        });
    });
    
    const keyInput = document.getElementById('criterion-key-input');
    const titleInput = document.getElementById('title-input');
    if (!keyInput.hasAttribute('readonly')) {
        function updateCriterionKey() {
            const titleValue = titleInput.value;
            if (titleValue.trim() !== '') {
                const sanitizedTitle = titleValue.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
                keyInput.value = sanitizedTitle;
            } else {
                keyInput.value = '';
            }
        }
        titleInput.addEventListener('input', updateCriterionKey);
    }
});
</script>