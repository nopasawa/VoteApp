<?php
session_start();
include 'includes/header.php';
require_once 'includes/db_connect.php';

// ถ้า login อยู่แล้ว ให้ redirect ไปหน้าหลัก
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$errors = []; // สร้าง array เพื่อเก็บข้อผิดพลาด

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // --- 1. ตรวจสอบข้อมูลเบื้องต้น (Validation) ---
    if (empty($full_name)) {
        $errors[] = "กรุณากรอกชื่อ-นามสกุล";
    }
    if (empty($username)) {
        $errors[] = "กรุณากรอกชื่อผู้ใช้";
    }
    if (empty($password)) {
        $errors[] = "กรุณากรอกรหัสผ่าน";
    }
    if ($password !== $password_confirm) {
        $errors[] = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
    }
    if (strlen($password) < 8 && !empty($password)) {
        $errors[] = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
    }

    // --- 2. ตรวจสอบว่ามีชื่อผู้ใช้นี้ในระบบแล้วหรือยัง ---
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "ชื่อผู้ใช้ (username) นี้มีคนใช้แล้ว";
        }
    }

    // --- 3. ถ้าไม่มีข้อผิดพลาด ให้บันทึกข้อมูล ---
    if (empty($errors)) {
        // เข้ารหัสผ่าน (Hashing) ก่อนบันทึกลงฐานข้อมูลเสมอ!
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // บัญชีใหม่ให้สิทธิ์เป็น 'judge'
        $role = 'judge';

        $sql = "INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql);
        
        if ($stmt_insert->execute([$full_name, $username, $hashed_password, $role])) {
            // สมัครสำเร็จ ให้ส่งไปหน้า login พร้อมข้อความแจ้งเตือน
            $_SESSION['message'] = "สมัครสมาชิกสำเร็จแล้ว! กรุณาเข้าสู่ระบบ";
            header('Location: login.php');
            exit();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองอีกครั้ง";
        }
    }
}


?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5">
            <div class="card-header text-center">
                <h3>📝 สมัครสมาชิก</h3>
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

                <form action="register.php" method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">ยืนยันรหัสผ่าน</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">สมัครสมาชิก</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <p>มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>