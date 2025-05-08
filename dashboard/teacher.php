<?php
/**
 * Teacher Dashboard
 */
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    set_flash_message('Anda harus login sebagai guru untuk mengakses halaman ini.', 'warning');
    redirect('/auth/login.php');
}

// Get teacher data
$teacher = get_user_by_id($_SESSION['user_id']);

// Get materials created by this teacher
$materials = get_records(
    "SELECT * FROM materi WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$_SESSION['user_id']],
    "i"
);

// Get quizzes created by this teacher
$quizzes = get_records(
    "SELECT q.*, 
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as total_questions,
            (SELECT COUNT(*) FROM hasil_kuis WHERE quiz_id = q.id) as total_attempts
     FROM quiz q 
     WHERE q.user_id = ? 
     ORDER BY q.created_at DESC LIMIT 5",
    [$_SESSION['user_id']],
    "i"
);

// Get total counts
$total_materials = get_record("SELECT COUNT(*) as count FROM materi WHERE user_id = ?", [$_SESSION['user_id']], "i")['count'] ?? 0;
$total_quizzes = get_record("SELECT COUNT(*) as count FROM quiz WHERE user_id = ?", [$_SESSION['user_id']], "i")['count'] ?? 0;
$total_forum_topics = get_record("SELECT COUNT(*) as count FROM forum WHERE user_id = ?", [$_SESSION['user_id']], "i")['count'] ?? 0;

// Get student stats
$student_stats = get_records(
    "SELECT 
        u.username, 
        COUNT(DISTINCT q.id) as quizzes_taken,
        AVG(hk.score) as avg_score,
        MAX(hk.completed_at) as last_activity
     FROM users u
     JOIN hasil_kuis hk ON u.id = hk.user_id
     JOIN quiz q ON hk.quiz_id = q.id AND q.user_id = ?
     WHERE u.role = 'student'
     GROUP BY u.id
     ORDER BY last_activity DESC
     LIMIT 10",
    [$_SESSION['user_id']],
    "i"
);

// Get activity data for the last 30 days
$activity_data = get_records(
    "SELECT 
        date(a.created_at) as date, 
        COUNT(*) as count
     FROM activity_logs a
     WHERE a.user_id = ? AND a.created_at >= date('now', '-30 days')
     GROUP BY date(a.created_at)
     ORDER BY date(a.created_at)",
    [$_SESSION['user_id']],
    "i"
);

// Format activity data for chart
$activity_labels = [];
$activity_values = [];

foreach ($activity_data as $data) {
    $activity_labels[] = format_date($data['date'], false);
    $activity_values[] = $data['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">
            <i class="fas fa-chalkboard-teacher me-2"></i>Dashboard Guru
        </h1>
        <div>
            <span class="text-muted">Selamat datang, </span>
            <span class="fw-bold"><?= htmlspecialchars($teacher['fullname']) ?></span>
        </div>
    </div>

    <!-- Teacher Stats Cards -->
    <div class="row stats-cards mb-4">
        <div class="col-md-4">
            <div class="card card-stats mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-bg bg-primary me-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Total Materi</h6>
                            <h3 class="mb-0"><?= $total_materials ?></h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="../materi/index.php?creator=me" class="btn btn-sm btn-primary">
                            <i class="fas fa-list me-2"></i>Lihat Semua Materi
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stats mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-bg bg-success me-3">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Total Kuis</h6>
                            <h3 class="mb-0"><?= $total_quizzes ?></h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="../quiz/index.php?creator=me" class="btn btn-sm btn-success">
                            <i class="fas fa-list me-2"></i>Lihat Semua Kuis
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stats mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-bg bg-info me-3">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div>
                            <h6 class="card-title text-muted mb-1">Total Topik Forum</h6>
                            <h3 class="mb-0"><?= $total_forum_topics ?></h3>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="../forum/index.php?creator=me" class="btn btn-sm btn-info">
                            <i class="fas fa-list me-2"></i>Lihat Semua Topik
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Recent Materials -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>Materi Terbaru
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($materials) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($materials as $materi): ?>
                                <a href="../materi/view.php?id=<?= $materi['id'] ?>" class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($materi['judul']) ?></h6>
                                        <small class="text-muted"><?= format_date($materi['created_at']) ?></small>
                                    </div>
                                    <div class="d-flex align-items-center mt-2">
                                        <span class="badge bg-secondary me-2"><?= htmlspecialchars($materi['kategori']) ?></span>
                                        <small class="text-muted"><i class="fas fa-eye me-1"></i><?= $materi['views'] ?? 0 ?> dilihat</small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center">
                            <p class="text-muted mb-0">Belum ada materi yang dibuat.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light">
                    <a href="../materi/add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Materi Baru
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Quizzes -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>Kuis Terbaru
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($quizzes) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($quizzes as $quiz): ?>
                                <a href="../quiz/edit.php?id=<?= $quiz['id'] ?>" class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($quiz['judul']) ?></h6>
                                        <small class="text-muted"><?= format_date($quiz['created_at']) ?></small>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mt-2">
                                        <div>
                                            <span class="badge bg-secondary me-2"><?= htmlspecialchars($quiz['kategori']) ?></span>
                                            <small class="text-muted"><i class="fas fa-question-circle me-1"></i><?= $quiz['total_questions'] ?> pertanyaan</small>
                                        </div>
                                        <small class="text-muted"><i class="fas fa-users me-1"></i><?= $quiz['total_attempts'] ?? 0 ?> mengerjakan</small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center">
                            <p class="text-muted mb-0">Belum ada kuis yang dibuat.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light">
                    <a href="../quiz/add.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Kuis Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Activity Chart -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>Aktivitas 30 Hari Terakhir
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Performance -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-user-graduate me-2"></i>Performa Siswa
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($student_stats) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Siswa</th>
                                        <th class="text-center">Kuis</th>
                                        <th class="text-center">Rata-rata Nilai</th>
                                        <th>Terakhir Aktif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_stats as $student): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($student['username']) ?></td>
                                            <td class="text-center"><?= $student['quizzes_taken'] ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= getScoreBadgeClass($student['avg_score']) ?>">
                                                    <?= number_format($student['avg_score'], 1) ?>
                                                </span>
                                            </td>
                                            <td><?= format_date($student['last_activity']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center">
                            <p class="text-muted mb-0">Belum ada data performa siswa.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-link me-2"></i>Tautan Cepat
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 col-6 mb-3">
                            <a href="../materi/add.php" class="quick-link">
                                <div class="quick-link-icon mb-2">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <span>Tambah Materi</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="../quiz/add.php" class="quick-link">
                                <div class="quick-link-icon mb-2">
                                    <i class="fas fa-question-circle"></i>
                                </div>
                                <span>Tambah Kuis</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="../forum/post.php" class="quick-link">
                                <div class="quick-link-icon mb-2">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <span>Buat Topik Forum</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="../profile/edit.php" class="quick-link">
                                <div class="quick-link-icon mb-2">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                <span>Edit Profil</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="../assets/js/chart-config.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize activity chart
    const activityLabels = <?= json_encode($activity_labels) ?>;
    const activityData = <?= json_encode($activity_values) ?>;
    
    initActivityChart('activityChart', activityLabels, activityData);
});
</script>

</body>
</html>