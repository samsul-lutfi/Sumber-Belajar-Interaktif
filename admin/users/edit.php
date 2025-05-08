<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
require_admin();

// Check if user ID is provided
if (!isset($_GET['id'])) {
    set_flash_message('ID pengguna tidak ditemukan.', 'danger');
    redirect('index.php');
}

$user_id = (int)$_GET['id'];

// Get user data
$user = get_record("SELECT * FROM users WHERE id = ?", [$user_id], "i");

if (!$user) {
    set_flash_message('Pengguna tidak ditemukan.', 'danger');
    redirect('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $role = sanitize($_POST['role']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Password is optional, only update if provided
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    
    // Validate inputs
    $errors = [];
    
    // Email validation
    if (empty($email)) {
        $errors[] = 'Email tidak boleh kosong.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    } else {
        // Check if email already exists (but not for this user)
        $check_email = get_record("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id], "si");
        if ($check_email) {
            $errors[] = 'Email sudah digunakan oleh pengguna lain.';
        }
    }
    
    // Full name validation
    if (empty($full_name)) {
        $errors[] = 'Nama lengkap tidak boleh kosong.';
    } else if (strlen($full_name) < 3) {
        $errors[] = 'Nama lengkap minimal 3 karakter.';
    } else if (strlen($full_name) > 50) {
        $errors[] = 'Nama lengkap maksimal 50 karakter.';
    }
    
    // Password validation (only if provided)
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        } else if ($password !== $confirm_password) {
            $errors[] = 'Password dan konfirmasi password tidak cocok.';
        }
    }
    
    // Role validation
    if (!in_array($role, ['admin', 'teacher', 'student'])) {
        $errors[] = 'Peran tidak valid.';
    }
    
    // If no errors, update the user
    if (empty($errors)) {
        // Prepare SQL
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET email = ?, full_name = ?, role = ?, is_active = ?, password = ? WHERE id = ?";
            $stmt = execute_query(
                $sql,
                [$email, $full_name, $role, $is_active, $hashed_password, $user_id],
                "sssisi"
            );
        } else {
            // Update without changing password
            $sql = "UPDATE users SET email = ?, full_name = ?, role = ?, is_active = ? WHERE id = ?";
            $stmt = execute_query(
                $sql,
                [$email, $full_name, $role, $is_active, $user_id],
                "sssii"
            );
        }
        
        if ($stmt) {
            // Log activity
            log_activity($_SESSION['user_id'], 'edit', 'user', $user_id);
            
            set_flash_message("Pengguna $full_name berhasil diperbarui.", 'success');
            redirect('index.php');
        } else {
            $errors[] = 'Gagal memperbarui pengguna. Silakan coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna - Admin Dashboard</title>
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
                <i class="fas fa-user-edit me-2"></i>Edit Pengguna
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="../../dashboard/admin.php">Dashboard Admin</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Manajemen Pengguna</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Pengguna</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <a href="view.php?id=<?= $user_id ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-2"></i>Lihat Detail
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>Edit Pengguna: <?= htmlspecialchars($user['full_name']) ?>
                    </h5>
                </div>
                <div class="card-body">
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
                    
                    <form action="edit.php?id=<?= $user_id ?>" method="POST">
                        <!-- Basic Info -->
                        <h6 class="mt-3 mb-3 border-bottom pb-2">Informasi Dasar</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                </div>
                                <div class="form-text">Username tidak dapat diubah.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                        </div>
                        
                        <!-- Password -->
                        <h6 class="mt-4 mb-3 border-bottom pb-2">Password</h6>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Biarkan kosong jika tidak ingin mengubah password.
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password Baru</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimal 6 karakter.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
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
                        
                        <!-- Role and Status -->
                        <h6 class="mt-4 mb-3 border-bottom pb-2">Peran dan Status</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="role" class="form-label">Peran <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required <?= $user_id === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                    <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Siswa</option>
                                    <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>Guru</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <?php if ($user_id === $_SESSION['user_id']): ?>
                                    <div class="form-text">Anda tidak dapat mengubah peran Anda sendiri.</div>
                                    <input type="hidden" name="role" value="<?= $user['role'] ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status Akun</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $user['is_active'] ? 'checked' : '' ?> <?= $user_id === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="is_active">Akun Aktif</label>
                                </div>
                                <?php if ($user_id === $_SESSION['user_id']): ?>
                                    <div class="form-text">Anda tidak dapat menonaktifkan akun Anda sendiri.</div>
                                    <input type="hidden" name="is_active" value="1">
                                <?php else: ?>
                                    <div class="form-text">Jika tidak dicentang, pengguna tidak dapat login.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Account Information -->
                        <h6 class="mt-4 mb-3 border-bottom pb-2">Informasi Akun</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Pendaftaran</label>
                                <input type="text" class="form-control" value="<?= format_date($user['created_at'], true) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Login Terakhir</label>
                                <input type="text" class="form-control" value="<?= $user['last_login'] ? format_date($user['last_login'], true) : 'Belum pernah login' ?>" disabled>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
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
    const passwordInput = document.getElementById('password');
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
</body>
</html>