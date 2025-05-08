<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] == 'admin' ? '/dashboard/admin.php' : '/dashboard/student.php');
}

$error_message = '';
$form_data = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'role' => 'student'
];

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $form_data = [
        'username' => sanitize($_POST['username']),
        'email' => sanitize($_POST['email']),
        'full_name' => sanitize($_POST['full_name']),
        'role' => isset($_POST['role']) && $_POST['role'] == 'admin' ? 'admin' : 'student'
    ];
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($form_data['username']) || empty($password) || empty($confirm_password) || 
        empty($form_data['email']) || empty($form_data['full_name'])) {
        $error_message = 'Semua field harus diisi.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password harus minimal 6 karakter.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Password dan konfirmasi password tidak sama.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid.';
    } else {
        // Attempt to register the user
        $result = register_user(
            $form_data['username'],
            $password,
            $form_data['email'],
            $form_data['full_name'],
            $form_data['role']
        );
        
        if ($result['success']) {
            // Set success message and redirect to login page
            set_flash_message($result['message'], 'success');
            redirect('/auth/login.php');
        } else {
            $error_message = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<?php include '../includes/header.php'; ?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="auth-form">
                <h2 class="text-center mb-4">
                    <i class="fas fa-user-plus me-2"></i>Daftar Akun
                </h2>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                    </div>
                <?php endif; ?>
                
                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($form_data['username']) ?>" required>
                                <div class="invalid-feedback">
                                    Username harus diisi.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Nama Lengkap</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                                <div class="invalid-feedback">
                                    Nama lengkap harus diisi.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($form_data['email']) ?>" required>
                            <div class="invalid-feedback">
                                Email harus diisi dengan format yang benar.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control password-with-strength" id="password" 
                                   name="password" required minlength="6">
                            <span class="input-group-text password-toggle-icon" style="cursor: pointer;">
                                <i class="fas fa-eye"></i>
                            </span>
                            <div class="invalid-feedback">
                                Password harus minimal 6 karakter.
                            </div>
                        </div>
                        <div class="password-strength-meter mt-2"><div></div></div>
                        <div class="password-strength-text small text-muted"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                            <span class="input-group-text password-toggle-icon" style="cursor: pointer;">
                                <i class="fas fa-eye"></i>
                            </span>
                            <div class="invalid-feedback">
                                Konfirmasi password harus diisi dan sama dengan password.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Daftar Sebagai</label>
                        <div class="d-flex">
                            <div class="form-check me-4">
                                <input class="form-check-input" type="radio" name="role" id="role_student" 
                                       value="student" <?= $form_data['role'] == 'student' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="role_student">
                                    <i class="fas fa-user-graduate me-1"></i>Siswa
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="role" id="role_admin" 
                                       value="admin" <?= $form_data['role'] == 'admin' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="role_admin">
                                    <i class="fas fa-chalkboard-teacher me-1"></i>Guru
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            Saya menyetujui <a href="#" target="_blank">ketentuan layanan</a>
                        </label>
                        <div class="invalid-feedback">
                            Anda harus menyetujui ketentuan layanan untuk melanjutkan.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-user-plus me-2"></i>Daftar
                    </button>
                </form>
                
                <div class="mt-4 text-center">
                    <p>Sudah punya akun? <a href="login.php">Login sekarang</a></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<script>
// Additional validation for password confirmation
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswordMatch() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Password dan konfirmasi password tidak sama.');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    password.addEventListener('change', validatePasswordMatch);
    confirmPassword.addEventListener('keyup', validatePasswordMatch);
});
</script>

</body>
</html>
