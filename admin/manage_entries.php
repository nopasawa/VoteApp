<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// ตรวจสอบว่ามี contest_id ส่งมาหรือไม่ ถ้าไม่มีให้กลับไปหน้าหลัก
if (!isset($_GET['contest_id'])) {
    header("Location: manage_contests.php");
    exit();
}
$contest_id = $_GET['contest_id'];

// ดึงชื่อการประกวดมาแสดง
$stmt_contest = $pdo->prepare("SELECT title FROM contests WHERE id = ?");
$stmt_contest->execute([$contest_id]);
$contest = $stmt_contest->fetch();
if (!$contest) {
    die("ไม่พบการประกวดนี้");
}

// --- LOGIC FOR ADD/EDIT/DELETE ---
$edit_mode = false;
$entry_to_edit = null;

// Handle POST request (Add/Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_entry'])) {
        $name = $_POST['name'];
        $details = $_POST['details'];
        $stmt = $pdo->prepare("INSERT INTO entries (contest_id, name, details) VALUES (?, ?, ?)");
        $stmt->execute([$contest_id, $name, $details]);
    } elseif (isset($_POST['update_entry'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $details = $_POST['details'];
        $stmt = $pdo->prepare("UPDATE entries SET name = ?, details = ? WHERE id = ?");
        $stmt->execute([$name, $details, $id]);
    }
    header("Location: manage_entries.php?contest_id=" . $contest_id);
    exit();
}

// Handle GET request (Edit/Delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action == 'edit') {
        $edit_mode = true;
        $stmt = $pdo->prepare("SELECT * FROM entries WHERE id = ? AND contest_id = ?");
        $stmt->execute([$id, $contest_id]);
        $entry_to_edit = $stmt->fetch();
    } elseif ($action == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM entries WHERE id = ? AND contest_id = ?");
        $stmt->execute([$id, $contest_id]);
        header("Location: manage_entries.php?contest_id=" . $contest_id);
        exit();
    }
}

// --- FETCH ALL ENTRIES FOR THIS CONTEST ---
$stmt = $pdo->prepare("SELECT * FROM entries WHERE contest_id = ? ORDER BY name ASC");
$stmt->execute([$contest_id]);
$entries = $stmt->fetchAll();


?>

<a href="manage_contests.php" class="btn btn-secondary mb-3">&laquo; กลับไปหน้าจัดการประกวด</a>
<h1 class="mb-4">จัดการผลงานสำหรับ: <span class="text-primary"><?php echo htmlspecialchars($contest['title']); ?></span></h1>

<div class="card mb-4">
    <div class="card-header">
        <h5><?php echo $edit_mode ? 'แก้ไขผลงาน' : 'เพิ่มผลงานใหม่'; ?></h5>
    </div>
    <div class="card-body">
        <form action="manage_entries.php?contest_id=<?php echo $contest_id; ?>" method="POST">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $entry_to_edit['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="name" class="form-label">ชื่อกลุ่ม / ชื่อผลงาน</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo $edit_mode ? htmlspecialchars($entry_to_edit['name']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="details" class="form-label">รายละเอียด (ถ้ามี)</label>
                <textarea class="form-control" id="details" name="details" rows="3"><?php echo $edit_mode ? htmlspecialchars($entry_to_edit['details']) : ''; ?></textarea>
            </div>
            <?php if ($edit_mode): ?>
                <button type="submit" name="update_entry" class="btn btn-primary">อัปเดตผลงาน</button>
                <a href="manage_entries.php?contest_id=<?php echo $contest_id; ?>" class="btn btn-secondary">ยกเลิก</a>
            <?php else: ?>
                <button type="submit" name="add_entry" class="btn btn-success">เพิ่มผลงาน</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>รายการผลงานทั้งหมด</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ชื่อผลงาน / กลุ่ม</th>
                        <th>รายละเอียด</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?php echo $entry['id']; ?></td>
                        <td><?php echo htmlspecialchars($entry['name']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($entry['details'])); ?></td>
                        <td>
                            <a href="manage_entries.php?contest_id=<?php echo $contest_id; ?>&action=edit&id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-secondary">แก้ไข</a>
                            <a href="manage_entries.php?contest_id=<?php echo $contest_id; ?>&action=delete&id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบรายการนี้?');">ลบ</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>