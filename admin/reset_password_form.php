<?php
session_start();
include '../includes/header.php';
require_once '../includes/db_connect.php';

$error = '';
$token_valid = false;
$token = $_GET['token'] ?? null;

if (!$token) {
    die("Invalid request. Token is missing.");
}

// 1. Hash the token from the URL to match the one in the database
$tokenHash = hash('sha256', $token);

// 2. Check if the hashed token exists and has not expired
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->execute([$tokenHash]);
$user = $stmt->fetch();

if ($user) {
    $token_valid = true;
} else {
    $error = "This password reset link is invalid or has expired. Please initiate the reset process again.";
}

// 3. Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "The new passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "The password must be at least 8 characters long.";
    } else {
        // All good, update the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear the reset token so it can't be used again
        $stmt_update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $stmt_update->execute([$hashed_password, $user['id']]);

        $_SESSION['message'] = "Admin password has been updated successfully! You can now log in.";
        header('Location: ../login.php');
        exit();
    }
}


?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5">
            <div class="card-header text-center">
                <h3>Reset Admin Password</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($token_valid): ?>
                    <form method="POST" action="reset_password_form.php?token=<?php echo htmlspecialchars($token); ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Confirm New Password</label>
                            <input type="password" name="password_confirm" id="password_confirm" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Save New Password</button>
                        </div>
                    </form>
                <?php else: ?>
                     <div class="text-center">
                        <p>To get a new link, you must run the initiation script on the server.</p>
                        <a href="../login.php" class="btn btn-secondary">Back to Login</a>
                     </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>