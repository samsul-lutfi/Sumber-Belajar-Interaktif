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

// Get user statistics
$stats = [
    'forum_topics' => get_record("SELECT COUNT(*) as count FROM forum_topics WHERE user_id = ?", [$user_id], "i")['count'],
    'forum_replies' => get_record("SELECT COUNT(*) as count FROM forum_replies WHERE user_id = ?", [$user_id], "i")['count'],
    'quiz_completed' => get_record("SELECT COUNT(*) as count FROM hasil_kuis WHERE user_id = ?", [$user_id], "i")['count'],
    'total_logins' => get_record("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ? AND action = 'login'", [$user_id], "i")['count'],
    'avg_quiz_score' => get_record("SELECT AVG(score) as avg FROM hasil_kuis WHERE user_id = ?", [$user_id], "i")['avg'] ?? 0,
    'last_activity' => get_record("SELECT created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", [$user_id], "i")['created_at'] ?? null
];

// Get recent activities
$recent_activities = get_records(
    "SELECT * FROM activity_logs 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 10",
    [$user_id],
    "i"
);

// Get quiz results
$quiz_results = get_records(
    "SELECT hk.*, q.judul 
     FROM hasil_kuis hk 
     JOIN quiz q ON hk.quiz_id = q.id 
     WHERE hk.user_id = ? 
     ORDER BY hk.created_at DESC 
     LIMIT 5",
    [$user_id],
    "i"
);

// Log the page view
log_activity($_SESSION['user_id'], 'view', 'user_details');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengguna - Admin Dashboard</title>
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
                <i class="fas fa-user me-2"></i>Detail Pengguna
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="../../dashboard/admin.php">Dashboard Admin</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Manajemen Pengguna</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Detail Pengguna</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <a href="edit.php?id=<?= $user_id ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Edit Pengguna
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- User Profile Card -->
        <div class="col-md-4 mb-4">
            <div class="card user-profile-card">
                <div class="card-header bg-<?= getRoleBadgeClass($user['role']) ?> text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-<?= getRoleIcon($user['role']) ?> me-2"></i>Profil Pengguna
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="avatar-lg mb-3 mx-auto bg-<?= getRoleBadgeClass($user['role']) ?> text-white">
                        <i class="fas fa-<?= getRoleIcon($user['role']) ?> fa-2x"></i>
                    </div>
                    
                    <h4 class="card-title"><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>
                    
                    <div class="mb-3">
                        <span class="badge bg-<?= getRoleBadgeClass($user['role']) ?>">
                            <?= formatRole($user['role']) ?>
                        </span>
                        <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $user['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                        </span>
                    </div>
                    
                    <div class="d-flex justify-content-around mb-3">
                        <div class="text-center">
                            <h5 class="mb-0"><?= $stats['quiz_completed'] ?></h5>
                            <small class="text-muted">Kuis</small>
                        </div>
                        <div class="text-center">
                            <h5 class="mb-0"><?= $stats['forum_topics'] ?></h5>
                            <small class="text-muted">Topik</small>
                        </div>
                        <div class="text-center">
                            <h5 class="mb-0"><?= $stats['forum_replies'] ?></h5>
                            <small class="text-muted">Balasan</small>
                        </div>
                    </div>
                    
                    <ul class="list-group list-group-flush mt-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-envelope me-2"></i>Email</span>
                            <span><?= htmlspecialchars($user['email']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-alt me-2"></i>Terdaftar Pada</span>
                            <span><?= format_date($user['created_at']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-sign-in-alt me-2"></i>Login Terakhir</span>
                            <span><?= $user['last_login'] ? format_date($user['last_login']) : '-' ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-clipboard-check me-2"></i>Total Login</span>
                            <span><?= $stats['total_logins'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-history me-2"></i>Aktivitas Terakhir</span>
                            <span><?= $stats['last_activity'] ? time_elapsed_string($stats['last_activity']) : '-' ?></span>
                        </li>
                    </ul>
                    
                    <div class="mt-3">
                        <a href="activities.php?user_id=<?= $user_id ?>" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i>Lihat Semua Aktivitas
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Statistics & Activities -->
        <div class="col-md-8">
            <!-- Quiz Performance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>Performa Kuis
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($stats['quiz_completed'] > 0): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="mb-1"><?= number_format($stats['avg_quiz_score'], 1) ?>%</h5>
                                        <p class="text-muted mb-0">Rata-rata Nilai Kuis</p>
                                        
                                        <div class="progress mt-2">
                                            <div class="progress-bar <?= getScoreBadgeClass($stats['avg_quiz_score']) ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= $stats['avg_quiz_score'] ?>%;" 
                                                 aria-valuenow="<?= $stats['avg_quiz_score'] ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="mb-1"><?= $stats['quiz_completed'] ?></h5>
                                        <p class="text-muted mb-0">Kuis Diselesaikan</p>
                                        
                                        <?php
                                        $total_quizzes = get_record("SELECT COUNT(*) as count FROM quiz")['count'];
                                        $completion_rate = $total_quizzes > 0 ? ($stats['quiz_completed'] / $total_quizzes) * 100 : 0;
                                        ?>
                                        <div class="progress mt-2">
                                            <div class="progress-bar bg-info" 
                                                 role="progressbar" 
                                                 style="width: <?= $completion_rate ?>%;" 
                                                 aria-valuenow="<?= $completion_rate ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?= number_format($completion_rate, 1) ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">dari total <?= $total_quizzes ?> kuis</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="border-bottom pb-2 mb-3">5 Hasil Kuis Terakhir</h6>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kuis</th>
                                        <th>Nilai</th>
                                        <th>Durasi</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quiz_results as $result): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($result['judul']) ?></td>
                                            <td>
                                                <span class="badge <?= getScoreBadgeClass($result['score']) ?>">
                                                    <?= number_format($result['score'], 1) ?>%
                                                </span>
                                            </td>
                                            <td><?= formatDuration($result['duration_seconds']) ?></td>
                                            <td><?= format_date($result['created_at'], false) ?></td>
                                            <td>
                                                <a href="../../quiz/results.php?id=<?= $result['quiz_id'] ?>&user_id=<?= $user_id ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Pengguna ini belum menyelesaikan kuis apapun.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Aktivitas Terakhir
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_activities)): ?>
                        <div class="activity-timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <?php 
                                        $action_colors = [
                                            'login' => 'success',
                                            'logout' => 'secondary',
                                            'view' => 'primary',
                                            'add' => 'info',
                                            'edit' => 'warning',
                                            'delete' => 'danger',
                                            'password_change' => 'danger',
                                            'profile_update' => 'warning'
                                        ];
                                        
                                        $action_icons = [
                                            'login' => 'sign-in-alt',
                                            'logout' => 'sign-out-alt',
                                            'view' => 'eye',
                                            'add' => 'plus-circle',
                                            'edit' => 'edit',
                                            'delete' => 'trash-alt',
                                            'password_change' => 'key',
                                            'profile_update' => 'user-edit'
                                        ];
                                        
                                        $color = isset($action_colors[$activity['action']]) ? $action_colors[$activity['action']] : 'secondary';
                                        $icon = isset($action_icons[$activity['action']]) ? $action_icons[$activity['action']] : 'exclamation-circle';
                                    ?>
                                    <div class="activity-icon bg-<?= $color ?>">
                                        <i class="fas fa-<?= $icon ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= formatActivityAction($activity['action']) ?> <?= htmlspecialchars($activity['module']) ?></strong>
                                            <span class="text-muted"><?= time_elapsed_string($activity['created_at']) ?></span>
                                        </div>
                                        <?php if (!empty($activity['ip_address'])): ?>
                                            <small class="text-muted">IP: <?= htmlspecialchars($activity['ip_address']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($activity['details'])): ?>
                                            <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#detailCollapse<?= $activity['id'] ?>">
                                                Lihat Detail
                                            </button>
                                            <div class="collapse mt-2" id="detailCollapse<?= $activity['id'] ?>">
                                                <div class="card card-body bg-light">
                                                    <pre class="mb-0" style="font-size: 0.8rem;"><?= htmlspecialchars(json_encode(json_decode($activity['details']), JSON_PRETTY_PRINT)) ?></pre>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada aktivitas yang tercatat untuk pengguna ini.
                        </div>
                    <?php endif; ?>
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

<style>
.avatar-lg {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.activity-timeline {
    position: relative;
    padding-left: 40px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.activity-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.activity-icon {
    position: absolute;
    left: -40px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.activity-content {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    background-color: #f8f9fa;
    border-left: 3px solid #dee2e6;
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

/**
 * Get CSS class for role badge
 * 
 * @param string $role Role name
 * @return string CSS class
 */
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin':
            return 'danger';
        case 'teacher':
            return 'warning';
        case 'student':
            return 'success';
        default:
            return 'secondary';
    }
}

/**
 * Get Font Awesome icon for role
 * 
 * @param string $role Role name
 * @return string Icon name
 */
function getRoleIcon($role) {
    switch ($role) {
        case 'admin':
            return 'user-shield';
        case 'teacher':
            return 'chalkboard-teacher';
        case 'student':
            return 'user-graduate';
        default:
            return 'user';
    }
}

/**
 * Format activity action for display
 * 
 * @param string $action Action name
 * @return string Formatted action name
 */
function formatActivityAction($action) {
    switch ($action) {
        case 'login':
            return 'Login ke';
        case 'logout':
            return 'Logout dari';
        case 'view':
            return 'Melihat';
        case 'add':
            return 'Menambahkan';
        case 'edit':
            return 'Mengedit';
        case 'delete':
            return 'Menghapus';
        case 'password_change':
            return 'Mengubah password di';
        case 'profile_update':
            return 'Memperbarui profil di';
        default:
            return ucfirst($action);
    }
}

// Use the getScoreBadgeClass function from includes/functions.php

/**
 * Format duration in seconds to human readable format
 * 
 * @param int $seconds Duration in seconds
 * @return string Formatted duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' detik';
    } else if ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . ' menit ' . $secs . ' detik';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . ' jam ' . $minutes . ' menit';
    }
}
?>
</body>
</html>