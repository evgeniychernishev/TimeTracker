<?php
require_once 'config.php';
require_once 'includes/header.php';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM timetrack_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = '';//sanitize($_POST['first_name']);
    $last_name = '';//sanitize($_POST['last_name']);
    $stage = '';//sanitize($_POST['stage']);
    $description = sanitize($_POST['description']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $error = '';
    $success = '';

    try {
        // Update basic info
        $stmt = $pdo->prepare("
            UPDATE timetrack_users 
            SET first_name = ?, last_name = ?, stage = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$first_name, $last_name, $stage, $description, $_SESSION['user_id']]);

        // Update password if provided
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if (!password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE timetrack_users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $success = "Password updated successfully";
            }
        }

        if (empty($error)) {
            $success = "Profile updated successfully";
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM timetrack_users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        }
    } catch(PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Редактирование профиля</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="row">
                        
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $user['description']; ?></textarea>
                    </div>
                    <hr>
                    <h6>Смена пароля</h6>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Текущий пароль</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Новый пароль</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Подтверждение нового пароля</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 