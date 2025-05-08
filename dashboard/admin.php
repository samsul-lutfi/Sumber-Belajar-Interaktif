<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
require_admin();

// Get total students count
$students_count = get_record(
    "SELECT COUNT(*) as total FROM users WHERE role = 'student'"
);

// Get total materials count
$materials_count = get_record(
    "SELECT COUNT(*) as total FROM materi"
);

// Get total quizzes count
$quizzes_count = get_record(
    "SELECT COUNT(*) as total FROM quiz"
);

// Get recent materials
$recent_materials = get_records(
    "SELECT id, judul, kategori, created_at FROM materi ORDER BY created_at DESC LIMIT 5"
);

// Get recent quizzes
$recent_quizzes = get_records(
    "SELECT id, judul, durasi, created_at FROM quiz ORDER BY created_at DESC LIMIT 5"
);

// Get recent forum topics
$recent_topics = get_records(
    "SELECT ft.id, ft.title, ft.created_at, u.username 
     FROM forum_topics ft 
     JOIN users u ON ft.user_id = u.id 
     ORDER BY ft.created_at DESC 
     LIMIT 5"
);

// Get top performing students (highest average quiz scores)
$top_students = get_records(
    "SELECT u.id, u.username, u.full_name, AVG(hk.score) as avg_score, COUNT(hk.id) as quiz_count 
     FROM users u 
     JOIN hasil_kuis hk ON u.id = hk.user_id 
     WHERE u.role = 'student' 
     GROUP BY u.id 
     ORDER BY avg_score DESC 
     LIMIT 5"
);

// Log the dashboard view
log_activity($_SESSION['user_id'], 'view', 'admin_dashboard');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sumber Belajar Interaktif</title>
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
    <h1 class="mb-4">
        <i class="fas fa-tachometer-alt me-2"></i>Dashboard Admin
    </h1>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="dashboard-stats text-center">
                <i class="fas fa-user-graduate text-primary"></i>
                <div class="stat-value"><?= $students_count['total'] ?></div>
                <div class="stat-label">Total Siswa</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="dashboard-stats text-center">
                <i class="fas fa-book text-success"></i>
                <div class="stat-value"><?= $materials_count['total'] ?></div>
                <div class="stat-label">Total Materi</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="dashboard-stats text-center">
                <i class="fas fa-tasks text-warning"></i>
                <div class="stat-value"><?= $quizzes_count['total'] ?></div>
                <div class="stat-label">Total Kuis</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="dashboard-stats text-center">
                <i class="fas fa-comments text-info"></i>
                <div class="stat-value"><?= count($recent_topics) ?></div>
                <div class="stat-label">Forum Aktif</div>
            </div>
        </div>
    </div>
    
    <!-- Quick Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap gap-2">
                    <a href="../admin/users/index.php" class="btn btn-danger">
                        <i class="fas fa-users me-2"></i>Kelola Pengguna
                    </a>
                    <a href="../materi/add.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Materi
                    </a>
                    <a href="../quiz/add.php" class="btn btn-success">
                        <i class="fas fa-plus-circle me-2"></i>Buat Kuis Baru
                    </a>
                    <a href="../stats/admin.php" class="btn btn-info text-white">
                        <i class="fas fa-chart-bar me-2"></i>Lihat Statistik
                    </a>
                    <a href="../forum/index.php" class="btn btn-secondary">
                        <i class="fas fa-comments me-2"></i>Kelola Forum
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Analytics Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>Statistik Aktivitas
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="activityChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Distribusi Materi
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="materiDistributionChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Materials -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-book me-2"></i>Materi Terbaru
                    </h5>
                    <a href="../materi/index.php" class="btn btn-sm btn-outline-primary">Kelola Materi</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Kategori</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_materials)): ?>
                                    <?php foreach ($recent_materials as $materi): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($materi['judul']) ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($materi['kategori']) ?></span></td>
                                            <td><?= format_date($materi['created_at'], false) ?></td>
                                            <td>
                                                <a href="../materi/view.php?id=<?= $materi['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../materi/edit.php?id=<?= $materi['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Belum ada materi tersedia.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Quizzes -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>Kuis Terbaru
                    </h5>
                    <a href="../quiz/index.php" class="btn btn-sm btn-outline-primary">Kelola Kuis</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Durasi</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_quizzes)): ?>
                                    <?php foreach ($recent_quizzes as $quiz): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($quiz['judul']) ?></td>
                                            <td><?= $quiz['durasi'] ?> menit</td>
                                            <td><?= format_date($quiz['created_at'], false) ?></td>
                                            <td>
                                                <a href="../quiz/results.php?id=<?= $quiz['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Hasil">
                                                    <i class="fas fa-poll"></i>
                                                </a>
                                                <a href="../quiz/edit.php?id=<?= $quiz['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Belum ada kuis tersedia.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Forum Topics -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-comments me-2"></i>Topik Forum Terbaru
                    </h5>
                    <a href="../forum/index.php" class="btn btn-sm btn-outline-primary">Lihat Forum</a>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php if (!empty($recent_topics)): ?>
                            <?php foreach ($recent_topics as $topic): ?>
                                <a href="../forum/view.php?id=<?= $topic['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($topic['title']) ?></h6>
                                        <small><?= format_date($topic['created_at'], false) ?></small>
                                    </div>
                                    <p class="mb-1">Oleh: <?= htmlspecialchars($topic['username']) ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Belum ada topik forum.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Performing Students -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-award me-2"></i>Siswa Berprestasi
                    </h5>
                    <a href="../stats/admin.php" class="btn btn-sm btn-outline-primary">Lihat Statistik Lengkap</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Rata-rata Nilai</th>
                                    <th>Kuis Selesai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($top_students)): ?>
                                    <?php foreach ($top_students as $student): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($student['full_name']) ?></td>
                                            <td>
                                                <span class="badge <?= $student['avg_score'] >= 80 ? 'bg-success' : ($student['avg_score'] >= 70 ? 'bg-primary' : 'bg-warning') ?>">
                                                    <?= number_format($student['avg_score'], 1) ?>%
                                                </span>
                                            </td>
                                            <td><?= $student['quiz_count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Belum ada data siswa.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
<!-- Chart.js Config -->
<script src="../assets/js/chart-config.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activity Chart
    const ctx1 = document.getElementById('activityChart').getContext('2d');
    const activityChart = new Chart(ctx1, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
            datasets: [
                {
                    label: 'Materi Dilihat',
                    data: [45, 55, 65, 70, 80, 90],
                    borderColor: 'rgba(52, 152, 219, 1)',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Kuis Selesai',
                    data: [30, 40, 45, 60, 70, 80],
                    borderColor: 'rgba(46, 204, 113, 1)',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Forum Posts',
                    data: [20, 25, 30, 35, 40, 45],
                    borderColor: 'rgba(155, 89, 182, 1)',
                    backgroundColor: 'rgba(155, 89, 182, 0.1)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Material Distribution Chart
    const ctx2 = document.getElementById('materiDistributionChart').getContext('2d');
    const materiDistributionChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Buku', 'Video', 'Jurnal', 'Internet', 'Alam'],
            datasets: [{
                data: [40, 25, 15, 10, 10],
                backgroundColor: [
                    'rgba(52, 152, 219, 0.8)',
                    'rgba(46, 204, 113, 0.8)',
                    'rgba(155, 89, 182, 0.8)',
                    'rgba(241, 196, 15, 0.8)',
                    'rgba(231, 76, 60, 0.8)'
                ],
                borderColor: [
                    'rgba(52, 152, 219, 1)',
                    'rgba(46, 204, 113, 1)',
                    'rgba(155, 89, 182, 1)',
                    'rgba(241, 196, 15, 1)',
                    'rgba(231, 76, 60, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            },
            cutout: '70%'
        }
    });
});
</script>

</body>
</html>
