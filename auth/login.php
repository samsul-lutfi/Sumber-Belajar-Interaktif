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
$username = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi.';
    } else {
        // Attempt to log in the user
        $result = login_user($username, $password);
        
        if ($result['success']) {
            // Set session variables
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['username'] = $result['user']['username'];
            $_SESSION['email'] = $result['user']['email'];
            $_SESSION['full_name'] = $result['user']['full_name'];
            $_SESSION['role'] = $result['user']['role'];
            
            // Set success message and redirect
            set_flash_message($result['message'], 'success');
            
            // Redirect to appropriate dashboard based on role
            $dashboard_path = '/dashboard/student.php'; // Default for students
            
            if ($_SESSION['role'] == 'admin') {
                $dashboard_path = '/dashboard/admin.php';
            } elseif ($_SESSION['role'] == 'teacher') {
                $dashboard_path = '/dashboard/teacher.php';
            }
            
            redirect($dashboard_path);
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
    <title>Login - Sumber Belajar Interaktif</title>
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
        <div class="col-md-6 col-lg-5">
            <div class="auth-form">
                <h2 class="text-center mb-4">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </h2>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                    </div>
                <?php endif; ?>
                
                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($username) ?>" required autofocus>
                            <div class="invalid-feedback">
                                Username harus diisi.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="input-group-text password-toggle-icon" style="cursor: pointer;">
                                <i class="fas fa-eye"></i>
                            </span>
                            <div class="invalid-feedback">
                                Password harus diisi.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Ingat saya</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
                
                <div class="mt-4 text-center">
                    <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
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

</body>
</html>
