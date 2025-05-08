<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
require_admin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    $role = sanitize($_POST['role']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    
    // Username validation
    if (empty($username)) {
        $errors[] = 'Username tidak boleh kosong.';
    } else if (strlen($username) < 3) {
        $errors[] = 'Username minimal 3 karakter.';
    } else if (strlen($username) > 20) {
        $errors[] = 'Username maksimal 20 karakter.';
    } else if (!preg_match('/^[a-zA-Z0-9._]+$/', $username)) {
        $errors[] = 'Username hanya boleh berisi huruf, angka, titik (.) dan underscore (_).';
    } else {
        // Check if username already exists
        $check_user = get_record("SELECT id FROM users WHERE username = ?", [$username], "s");
        if ($check_user) {
            $errors[] = 'Username sudah digunakan.';
        }
    }
    
    // Email validation
    if (empty($email)) {
        $errors[] = 'Email tidak boleh kosong.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    } else {
        // Check if email already exists
        $check_email = get_record("SELECT id FROM users WHERE email = ?", [$email], "s");
        if ($check_email) {
            $errors[] = 'Email sudah digunakan.';
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
    
    // Password validation
    if (empty($password)) {
        $errors[] = 'Password tidak boleh kosong.';
    } else if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    } else if ($password !== $confirm_password) {
        $errors[] = 'Password dan konfirmasi password tidak cocok.';
    }
    
    // Role validation
    if (!in_array($role, ['admin', 'teacher', 'student'])) {
        $errors[] = 'Peran tidak valid.';
    }
    
    // If no errors, insert new user
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate activation code
        $activation_code = md5(uniqid(rand(), true));
        
        // Prepare and execute statement
        $sql = "INSERT INTO users (username, email, password, full_name, role, is_active, activation_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))";
        $stmt = execute_query(
            $sql,
            [$username, $email, $hashed_password, $full_name, $role, $is_active, $activation_code],
            "sssssss"
        );
        
        if ($stmt) {
            $user_id = $conn->lastInsertRowID() ?? get_record("SELECT last_insert_rowid() as id")['id'];
            
            // Log activity
            log_activity($_SESSION['user_id'], 'add', 'user', $user_id);
            
            set_flash_message("Pengguna $full_name berhasil ditambahkan.", 'success');
            redirect('index.php');
        } else {
            $errors[] = 'Gagal menambahkan pengguna. Silakan coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengguna - Admin Dashboard</title>
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
                <i class="fas fa-user-plus me-2"></i>Tambah Pengguna
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="../../dashboard/admin.php">Dashboard Admin</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Manajemen Pengguna</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah Pengguna</li>
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
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>Formulir Tambah Pengguna
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
                    
                    <form action="add.php" method="POST">
                        <!-- Basic Info -->
                        <h6 class="mt-3 mb-3 border-bottom pb-2">Informasi Dasar</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>
                                </div>
                                <div class="form-text">Minimal 3 karakter, hanya boleh berisi huruf, angka, titik (.) dan underscore (_).</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= isset($full_name) ? htmlspecialchars($full_name) : '' ?>" required>
                            </div>
                        </div>
                        
                        <!-- Password -->
                        <h6 class="mt-4 mb-3 border-bottom pb-2">Password</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimal 6 karakter.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
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
                        
                        <!-- Role and Status -->
                        <h6 class="mt-4 mb-3 border-bottom pb-2">Peran dan Status</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="role" class="form-label">Peran <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">-- Pilih Peran --</option>
                                    <option value="student" <?= (isset($role) && $role === 'student') ? 'selected' : '' ?>>Siswa</option>
                                    <option value="teacher" <?= (isset($role) && $role === 'teacher') ? 'selected' : '' ?>>Guru</option>
                                    <option value="admin" <?= (isset($role) && $role === 'admin') ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status Akun</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= (!isset($is_active) || $is_active) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">Akun Aktif</label>
                                </div>
                                <div class="form-text">Jika tidak dicentang, pengguna tidak dapat login.</div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Pengguna
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
</body>
</html>