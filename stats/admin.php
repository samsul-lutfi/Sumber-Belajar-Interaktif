<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
require_admin();

// Get statistics
$total_students = get_record("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'];
$total_admins = get_record("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'];
$total_materials = get_record("SELECT COUNT(*) as count FROM materi")['count'];
$total_quizzes = get_record("SELECT COUNT(*) as count FROM quiz")['count'];
$total_forum_topics = get_record("SELECT COUNT(*) as count FROM forum_topics")['count'];
$total_quiz_results = get_record("SELECT COUNT(*) as count FROM hasil_kuis")['count'];

// Get top students based on quiz scores
$top_students = get_records(
    "SELECT u.id, u.full_name, u.username, 
     COUNT(hk.id) as quiz_count, 
     AVG(hk.score) as avg_score 
     FROM users u
     JOIN hasil_kuis hk ON u.id = hk.user_id
     WHERE u.role = 'student'
     GROUP BY u.id
     ORDER BY avg_score DESC
     LIMIT 10"
);

// Get recent activities
$recent_activities = get_records(
    "SELECT al.id, al.action, al.module, al.created_at, u.full_name, u.username 
     FROM activity_logs al
     JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC
     LIMIT 20"
);

// Get materials by category
$material_by_category = get_records(
    "SELECT kategori, COUNT(*) as count 
     FROM materi 
     GROUP BY kategori"
);

// Log the page view
log_activity($_SESSION['user_id'], 'view', 'stats_admin');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik Admin - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-chart-line me-2"></i>Statistik Admin
        </h1>
        <a href="../dashboard/admin.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
        </a>
    </div>
    
    <!-- Overview Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-user-graduate text-primary"></i>
                <div class="stat-value"><?= $total_students ?></div>
                <p class="stat-label">Siswa</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-book text-success"></i>
                <div class="stat-value"><?= $total_materials ?></div>
                <p class="stat-label">Materi</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-question-circle text-warning"></i>
                <div class="stat-value"><?= $total_quizzes ?></div>
                <p class="stat-label">Kuis</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-comments text-info"></i>
                <div class="stat-value"><?= $total_forum_topics ?></div>
                <p class="stat-label">Topik Forum</p>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
        <!-- User Activity Chart -->
        <div class="col-md-6 mb-4">
            <div class="card stats-container">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Distribusi Pengguna
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="userChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Material by Category Chart -->
        <div class="col-md-6 mb-4">
            <div class="card stats-container">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>Materi per Kategori
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="materialChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Students Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card stats-container">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy me-2"></i>Siswa Berprestasi
                    </h5>
                    <a href="top_students.php" class="btn btn-sm btn-dark">
                        <i class="fas fa-external-link-alt me-1"></i>Lihat Lengkap
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_students)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Peringkat</th>
                                        <th>Nama</th>
                                        <th>Username</th>
                                        <th>Jumlah Kuis</th>
                                        <th>Rata-rata Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_students as $index => $student): ?>
                                        <tr>
                                            <td>
                                                <?php if($index < 3): ?>
                                                    <span class="badge <?= $index === 0 ? 'bg-warning text-dark' : ($index === 1 ? 'bg-secondary' : 'bg-success') ?>">
                                                        <?= $index + 1 ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?= $index + 1 ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($student['full_name']) ?></td>
                                            <td><?= htmlspecialchars($student['username']) ?></td>
                                            <td><?= $student['quiz_count'] ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar <?= getScoreBadgeClass($student['avg_score']) ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $student['avg_score'] ?>%;" 
                                                         aria-valuenow="<?= $student['avg_score'] ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?= number_format($student['avg_score'], 1) ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada data hasil kuis.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="card stats-container">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Aktivitas Terbaru
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_activities)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Pengguna</th>
                                        <th>Aksi</th>
                                        <th>Modul</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <tr>
                                            <td><?= time_elapsed_string($activity['created_at']) ?></td>
                                            <td><?= htmlspecialchars($activity['full_name']) ?></td>
                                            <td>
                                                <?php 
                                                    $action_badges = [
                                                        'login' => 'bg-success',
                                                        'logout' => 'bg-secondary',
                                                        'view' => 'bg-primary',
                                                        'add' => 'bg-info',
                                                        'edit' => 'bg-warning text-dark',
                                                        'delete' => 'bg-danger',
                                                        'password_change' => 'bg-danger',
                                                        'profile_update' => 'bg-warning text-dark'
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
                                                    
                                                    $badge_class = isset($action_badges[$activity['action']]) ? $action_badges[$activity['action']] : 'bg-secondary';
                                                    $icon = isset($action_icons[$activity['action']]) ? $action_icons[$activity['action']] : 'exclamation-circle';
                                                ?>
                                                <span class="badge <?= $badge_class ?>">
                                                    <i class="fas fa-<?= $icon ?> me-1"></i><?= htmlspecialchars($activity['action']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($activity['module']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada data aktivitas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<script>
// User distribution chart
const userCtx = document.getElementById('userChart').getContext('2d');
const userChart = new Chart(userCtx, {
    type: 'pie',
    data: {
        labels: ['Siswa', 'Admin'],
        datasets: [{
            label: 'Jumlah Pengguna',
            data: [<?= $total_students ?>, <?= $total_admins ?>],
            backgroundColor: [
                'rgba(0, 77, 64, 0.7)',   // Dark Green (Primary color)
                'rgba(255, 193, 7, 0.7)'  // Amber (Secondary color)
            ],
            borderColor: [
                'rgba(0, 77, 64, 1)',
                'rgba(255, 193, 7, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Distribusi Pengguna'
            }
        }
    }
});

// Material by category chart
const materialCtx = document.getElementById('materialChart').getContext('2d');
const materialChart = new Chart(materialCtx, {
    type: 'bar',
    data: {
        labels: [
            <?php 
                foreach ($material_by_category as $category) {
                    echo "'" . $category['kategori'] . "', ";
                }
            ?>
        ],
        datasets: [{
            label: 'Jumlah Materi',
            data: [
                <?php 
                    foreach ($material_by_category as $category) {
                        echo $category['count'] . ", ";
                    }
                ?>
            ],
            backgroundColor: 'rgba(255, 193, 7, 0.7)',
            borderColor: 'rgba(255, 193, 7, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Materi per Kategori'
            }
        }
    }
});
</script>
</body>
</html>