<?php
session_start();
require_once 'includes/db_connect.php';

// กำหนดตัวแปรสำหรับควบคุมการแสดงผลของฟอร์ม
$step = 1; // ขั้นตอนที่ 1: กรอกชื่อผู้ใช้, ขั้นตอนที่ 2: ตั้งรหัสผ่านใหม่
$username = '';
$error_message = '';

// --- จัดการการส่งข้อมูลจากฟอร์ม ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- จัดการเมื่อผู้ใช้กดปุ่ม "ยืนยันชื่อผู้ใช้" (ขั้นตอนที่ 1) ---
    if (isset($_POST['check_username'])) {
        $username = trim($_POST['username']);

        if (empty($username)) {
            $error_message = "กรุณากรอกชื่อผู้ใช้";
            $step = 1;
        } else {
            // ตรวจสอบว่ามีชื่อผู้ใช้นี้ในระบบหรือไม่
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                // หากพบผู้ใช้ ให้ไปยังขั้นตอนที่ 2
                $step = 2;
            } else {
                // หากไม่พบผู้ใช้ ให้แสดงข้อความผิดพลาด
                $error_message = "ไม่พบชื่อผู้ใช้ '{$username}' ในระบบ";
                $step = 1;
            }
        }
    }

    // --- จัดการเมื่อผู้ใช้กดปุ่ม "บันทึกรหัสผ่านใหม่" (ขั้นตอนที่ 2) ---
    elseif (isset($_POST['update_password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $step = 2; // กำหนดให้คงอยู่ที่ step 2 หากเกิด error

        if ($password !== $password_confirm) {
            $error_message = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
        } elseif (strlen($password) < 8) {
            $error_message = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
        } else {
            // ถ้าข้อมูลถูกต้อง ให้ทำการอัปเดต
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
            
            if ($stmt->execute([$hashed_password, $username])) {
                // อัปเดตสำเร็จ ส่งกลับไปหน้า Login พร้อมข้อความ
                $_SESSION['message'] = "ตั้งรหัสผ่านใหม่สำหรับผู้ใช้ '{$username}' สำเร็จแล้ว";
                header('Location: login.php');
                exit();
            } else {
                $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5 shadow-sm">
            <div class="card-header text-center bg-secondary text-white">
                <h3>รีเซ็ตรหัสผ่าน</h3>
            </div>
            <div class="card-body p-4">

                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php // --- แสดงฟอร์มตามขั้นตอน ($step) --- ?>

                <?php if ($step == 1): ?>
                
                <p class="text-muted text-center mb-4">กรอกชื่อผู้ใช้เพื่อดำเนินการต่อ</p>
                <form action="forgot_password.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="check_username" class="btn btn-primary">ดำเนินการต่อ</button>
                    </div>
                </form>

                <?php elseif ($step == 2): ?>

                <p class="text-center mb-4">ตั้งรหัสผ่านใหม่สำหรับผู้ใช้: <strong><?php echo htmlspecialchars($username); ?></strong></p>
                <form action="forgot_password.php" method="POST">
                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="update_password" class="btn btn-success">บันทึกรหัสผ่านใหม่</button>
                    </div>
                </form>

                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="login.php">กลับไปหน้าเข้าสู่ระบบ</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>