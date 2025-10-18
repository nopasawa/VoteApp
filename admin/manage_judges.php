<?php
include '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

// --- LOGIC FOR ADD/EDIT/DELETE ---
$edit_mode = false;
$user_to_edit = null;
$errors = [];

// --- Handle POST request (Add/Update) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // --- ADD USER ---
    if (isset($_POST['add_user'])) {
        // Validation
        if (empty($full_name) || empty($username) || empty($password)) {
            $errors[] = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
        }
        if (strlen($password) < 8) {
            $errors[] = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
        }

        // Check for duplicate username
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetch()) {
            $errors[] = "ชื่อผู้ใช้ (username) นี้มีคนใช้แล้ว";
        }

        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, 'judge')");
            $stmt_insert->execute([$full_name, $username, $hashed_password]);
            
            log_action('add_judge', 'Added new judge: ' . $username);

            $_SESSION['admin_message'] = "เพิ่มกรรมการสำเร็จ!";
            header("Location: manage_judges.php");
            exit();
        }
    } 
    // --- UPDATE USER ---
    elseif (isset($_POST['update_user'])) {
        $id = $_POST['id'];
        if (empty($full_name)) {
            $errors[] = "กรุณากรอกชื่อ-นามสกุล";
        }

        if (empty($errors)) {
            // Update password only if a new one is provided
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $errors[] = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_update = $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE id = ?");
                    $stmt_update->execute([$full_name, $hashed_password, $id]);
                    log_action('update_judge', 'Updated password for judge ID: ' . $id);
                }
            } else {
                // Update only full name
                $stmt_update = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                $stmt_update->execute([$full_name, $id]);
            }

            if (empty($errors)) {
                log_action('update_judge', 'Updated profile for judge ID: ' . $id);
                $_SESSION['admin_message'] = "อัปเดตข้อมูลกรรมการสำเร็จ!";
                header("Location: manage_judges.php");
                exit();
            }
        }
    }
}

// --- Handle GET request (Edit/Delete) ---
if (isset($_GET['action'])) {
    $id = $_GET['id'];
    if ($_GET['action'] == 'edit') {
        $edit_mode = true;
        $stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id = ? AND role = 'judge'");
        $stmt->execute([$id]);
        $user_to_edit = $stmt->fetch();
    } elseif ($_GET['action'] == 'delete') {
        $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_user->execute([$id]);
        $deleted_username = $stmt_user->fetchColumn();

        $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'judge'");
        $stmt_delete->execute([$id]);

        log_action('delete_judge', 'Deleted judge: ' . $deleted_username . ' (ID: ' . $id . ')');

        $_SESSION['admin_message'] = "ลบกรรมการสำเร็จ!";
        header("Location: manage_judges.php");
        exit();
    }
}

// --- Fetch all judges for display ---
$stmt_judges = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'judge' ORDER BY id ASC");
$judges = $stmt_judges->fetchAll();
?>

<h1 class="mb-4">จัดการกรรมการ (Judges)</h1>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi <?php echo $edit_mode ? 'bi-pencil-square' : 'bi-plus-circle-fill'; ?>"></i> <?php echo $edit_mode ? 'แก้ไขข้อมูลกรรมการ' : 'เพิ่มกรรมการใหม่'; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form action="manage_judges.php" method="POST">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?php echo $user_to_edit['id']; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $edit_mode ? htmlspecialchars($user_to_edit['full_name']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo $edit_mode ? htmlspecialchars($user_to_edit['username']) : ''; ?>" <?php if ($edit_mode) echo 'readonly'; ?> required>
                         <?php if ($edit_mode): ?>
                            <div class="form-text">ไม่สามารถแก้ไข Username ได้</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" <?php if (!$edit_mode) echo 'required'; ?>>
                        <?php if ($edit_mode): ?>
                            <div class="form-text">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</div>
                        <?php else: ?>
                             <div class="form-text">ต้องมีความยาวอย่างน้อย 8 ตัวอักษร</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($edit_mode): ?>
                        <button type="submit" name="update_user" class="btn btn-primary">อัปเดตข้อมูล</button>
                        <a href="manage_judges.php" class="btn btn-secondary">ยกเลิก</a>
                    <?php else: ?>
                        <button type="submit" name="add_user" class="btn btn-success">เพิ่มกรรมการ</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul"></i> รายชื่อกรรมการทั้งหมด</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ชื่อ-นามสกุล</th>
                                <th>Username</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($judges)): ?>
                                <tr><td colspan="3" class="text-center text-muted">ยังไม่มีข้อมูลกรรมการ</td></tr>
                            <?php else: ?>
                                <?php foreach ($judges as $judge): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($judge['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($judge['username']); ?></td>
                                    <td class="text-center">
                                        <a href="?action=edit&id=<?php echo $judge['id']; ?>" class="btn btn-sm btn-secondary" title="แก้ไข"><i class="bi bi-pencil-square"></i></a>
                                        <a href="?action=delete&id=<?php echo $judge['id']; ?>" class="btn btn-sm btn-danger delete-btn" title="ลบ"><i class="bi bi-trash"></i></a>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const deleteUrl = this.href;
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "ข้อมูลของกรรมการท่านนี้จะถูกลบอย่างถาวร!",
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
});
</script>

<?php include '../includes/footer.php'; ?>