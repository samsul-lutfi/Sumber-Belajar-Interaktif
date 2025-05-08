<?php
// Get current page for active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/index.php">
                <i class="fas fa-book-open me-2"></i>
                Sumber<span>Belajar</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="/index.php">
                            <i class="fas fa-home me-1"></i> Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_page, 'materi') !== false ? 'active' : '' ?>" href="/materi/index.php">
                            <i class="fas fa-book me-1"></i> Materi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_page, 'quiz') !== false ? 'active' : '' ?>" href="/quiz/index.php">
                            <i class="fas fa-tasks me-1"></i> Kuis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_page, 'forum') !== false ? 'active' : '' ?>" href="/forum/index.php">
                            <i class="fas fa-comments me-1"></i> Forum
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?= htmlspecialchars($_SESSION['username']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><h6 class="dropdown-header">Login sebagai <?= htmlspecialchars($_SESSION['role']); ?></h6></li>
                                <li><a class="dropdown-item" href="/dashboard/<?= $_SESSION['role'] == 'admin' ? 'admin' : 'student' ?>.php">
                                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                </a></li>
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                    <li><a class="dropdown-item" href="/stats/admin.php">
                                        <i class="fas fa-chart-bar me-2"></i> Statistik
                                    </a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="/stats/student.php">
                                        <i class="fas fa-chart-line me-2"></i> Progress
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="/auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login.php" class="btn btn-outline-light me-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                        <a href="/auth/register.php" class="btn btn-light">
                            <i class="fas fa-user-plus me-1"></i> Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <?php
    // Display flash messages if they exist
    if (isset($_SESSION['flash_message'])) {
        $message_type = isset($_SESSION['flash_message_type']) ? $_SESSION['flash_message_type'] : 'info';
        echo '<div class="container mt-3"><div class="alert alert-' . $message_type . ' alert-dismissible fade show" role="alert">';
        echo $_SESSION['flash_message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div></div>';
        
        // Clear the flash message
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_message_type']);
    }
    ?>
</header>
