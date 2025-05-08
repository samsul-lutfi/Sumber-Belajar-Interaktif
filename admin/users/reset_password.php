<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
require_admin();

// Get users for dropdown
$users = get_records("SELECT id, username, full_name, role, is_active FROM users ORDER BY full_name ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $new_password = sanitize($_POST['new_password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    
    // Validate inputs
    $errors = [];
    
    // User ID validation
    if (empty($user_id)) {
        $errors[] = 'Pilih pengguna yang ingin di-reset passwordnya.';
    } else {
        $user = get_record("SELECT id, username, full_name FROM users WHERE id = ?", [$user_id], "i");
        if (!$user) {
            $errors[] = 'Pengguna tidak ditemukan.';
        }
    }
    
    // Password validation
    if (empty($new_password)) {
        $errors[] = 'Password baru tidak boleh kosong.';
    } else if (strlen($new_password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    } else if ($new_password !== $confirm_password) {
        $errors[] = 'Password dan konfirmasi password tidak cocok.';
    }
    
    // If no errors, reset the password
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Log activity
            log_activity($_SESSION['user_id'], 'password_change', 'user');
            
            $success_message = "Password untuk pengguna '{$user['full_name']}' berhasil di-reset.";
            
            // Redirect after successful reset
            set_flash_message($success_message, 'success');
            redirect('index.php');
        } else {
            $errors[] = 'Gagal mereset password: ' . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Pengguna - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php include '../../includes/header.php'; ?>

<main class="container py-4">
    <!-- Page Header with Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">
                <i class="fas fa-key me-2"></i>Reset Password Pengguna
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="../../dashboard/admin.php">Dashboard Admin</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Manajemen Pengguna</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Reset Password</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-key me-2"></i>Reset Password Pengguna
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Informasi:</strong> Gunakan fitur ini untuk mereset password pengguna jika mereka melupakan atau kehilangan akses ke akun mereka.
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Terdapat error:</strong>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="reset_password.php" method="POST">
                        <div class="mb-4">
                            <label for="user_id" class="form-label">Pilih Pengguna <span class="text-danger">*</span></label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">-- Pilih Pengguna --</option>
                                <?php foreach ($users as $user): ?>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <option value="<?= $user['id'] ?>" <?= (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['full_name']) ?> (@<?= htmlspecialchars($user['username']) ?>) 
                                            - <?= formatRole($user['role']) ?>
                                            <?= $user['is_active'] ? '' : ' (Tidak Aktif)' ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Pilih pengguna yang ingin di-reset passwordnya.</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">Password Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimal 6 karakter.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="password-strength-meter">
                                <div></div>
                            </div>
                            <small class="password-strength-text"></small>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Peringatan:</strong> Mereset password pengguna akan menyebabkan pengguna harus login dengan password baru.
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../../assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Password strength meter
    const passwordInput = document.getElementById('new_password');
    const strengthMeter = document.querySelector('.password-strength-meter div');
    const strengthText = document.querySelector('.password-strength-text');
    
    passwordInput.addEventListener('input', function() {
        const strength = calculatePasswordStrength(this.value);
        
        strengthMeter.style.width = strength + '%';
        
        if (strength < 25) {
            strengthMeter.style.backgroundColor = '#e74c3c';
            strengthText.textContent = 'Password sangat lemah';
            strengthText.style.color = '#e74c3c';
        } else if (strength < 50) {
            strengthMeter.style.backgroundColor = '#f39c12';
            strengthText.textContent = 'Password lemah';
            strengthText.style.color = '#f39c12';
        } else if (strength < 75) {
            strengthMeter.style.backgroundColor = '#3498db';
            strengthText.textContent = 'Password cukup kuat';
            strengthText.style.color = '#3498db';
        } else {
            strengthMeter.style.backgroundColor = '#2ecc71';
            strengthText.textContent = 'Password kuat';
            strengthText.style.color = '#2ecc71';
        }
    });
    
    function calculatePasswordStrength(password) {
        if (!password) return 0;
        
        let strength = 0;
        
        // Length contribution (up to 40%)
        const lengthContribution = Math.min(password.length * 3, 40);
        strength += lengthContribution;
        
        // Complexity contribution (up to 60%)
        if (/[A-Z]/.test(password)) strength += 10; // Uppercase letters
        if (/[a-z]/.test(password)) strength += 10; // Lowercase letters
        if (/[0-9]/.test(password)) strength += 10; // Numbers
        if (/[^A-Za-z0-9]/.test(password)) strength += 15; // Special characters
        if (/(.)\1\1/.test(password)) strength -= 10; // Penalize repeating characters
        
        return Math.max(0, Math.min(100, strength));
    }
});
</script>

<style>
.password-strength-meter {
    height: 5px;
    background-color: #e9ecef;
    margin-top: 5px;
    border-radius: 5px;
    overflow: hidden;
}

.password-strength-meter div {
    height: 100%;
    width: 0;
    transition: width 0.3s ease;
}
</style>

<?php
/**
 * Format role name for display
 * 
 * @param string $role Role name
 * @return string Formatted role name
 */
function formatRole($role) {
    switch ($role) {
        case 'admin':
            return 'Admin';
        case 'teacher':
            return 'Guru';
        case 'student':
            return 'Siswa';
        default:
            return ucfirst($role);
    }
}
?>
</body>
</html>