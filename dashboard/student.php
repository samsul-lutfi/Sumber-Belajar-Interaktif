<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
require_login();

if (is_admin()) {
    redirect('/dashboard/admin.php');
}

// Get latest materials
$latest_materials = get_records(
    "SELECT m.id, m.judul, m.deskripsi, m.kategori, m.created_at 
     FROM materi m 
     ORDER BY m.created_at DESC 
     LIMIT 3"
);

// Get ongoing quizzes
$ongoing_quizzes = get_records(
    "SELECT q.id, q.judul, q.deskripsi, q.durasi, q.created_at,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) as total_questions 
     FROM quiz q 
     WHERE q.id NOT IN (SELECT quiz_id FROM hasil_kuis WHERE user_id = ?) 
     ORDER BY q.created_at DESC 
     LIMIT 3",
    [$_SESSION['user_id']],
    "i"
);

// Get recent quiz results
$recent_results = get_records(
    "SELECT hk.id, hk.quiz_id, hk.score, hk.completed_at, q.judul 
     FROM hasil_kuis hk 
     JOIN quiz q ON hk.quiz_id = q.id 
     WHERE hk.user_id = ? 
     ORDER BY hk.completed_at DESC 
     LIMIT 3",
    [$_SESSION['user_id']],
    "i"
);

// Get user statistics
$quiz_stats = get_user_quiz_stats($_SESSION['user_id']);
$forum_activity = get_user_forum_activity($_SESSION['user_id']);

// Get recently viewed materials
$recent_views = get_records(
    "SELECT m.id, m.judul, m.kategori, al.created_at as viewed_at 
     FROM activity_logs al 
     JOIN materi m ON al.resource_id = m.id 
     WHERE al.user_id = ? AND al.module = 'materi' AND al.action = 'view' 
     GROUP BY m.id 
     ORDER BY al.created_at DESC 
     LIMIT 5",
    [$_SESSION['user_id']],
    "i"
);

// Get recent forum activity
$forum_posts = get_records(
    "SELECT 
        CASE 
            WHEN ft.id IS NOT NULL THEN 'topic' 
            ELSE 'reply' 
        END as type,
        COALESCE(ft.id, fr.topic_id) as topic_id,
        COALESCE(ft.title, (SELECT title FROM forum_topics WHERE id = fr.topic_id)) as title,
        COALESCE(ft.created_at, fr.created_at) as created_at
     FROM 
        (SELECT id, title, created_at FROM forum_topics WHERE user_id = ? ORDER BY created_at DESC LIMIT 3) ft
     LEFT JOIN 
        (SELECT topic_id, created_at FROM forum_replies WHERE user_id = ? ORDER BY created_at DESC LIMIT 3) fr
     ON 1=0
     
     UNION
     
     SELECT 
        CASE 
            WHEN ft.id IS NOT NULL THEN 'topic' 
            ELSE 'reply' 
        END as type,
        COALESCE(ft.id, fr.topic_id) as topic_id,
        COALESCE(ft.title, (SELECT title FROM forum_topics WHERE id = fr.topic_id)) as title,
        COALESCE(ft.created_at, fr.created_at) as created_at
     FROM 
        (SELECT id, title, created_at FROM forum_topics WHERE user_id = ? ORDER BY created_at DESC LIMIT 3) ft
     RIGHT JOIN 
        (SELECT topic_id, created_at FROM forum_replies WHERE user_id = ? ORDER BY created_at DESC LIMIT 3) fr
     ON 1=0
     
     ORDER BY created_at DESC
     LIMIT 5",
    [$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']],
    "iiii"
);

// Log the dashboard view
log_activity($_SESSION['user_id'], 'view', 'dashboard');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - Sumber Belajar Interaktif</title>
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
        <i class="fas fa-tachometer-alt me-2"></i>Dashboard Siswa
    </h1>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="dashboard-stats text-center">
                <i class="fas fa-book text-primary"></i>
                <div class="stat-value"><?= $quiz_stats['total_quizzes'] ?></div>
                <div class="stat-label">Kuis Diselesaikan</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-stats text-center">
                <i class="fas fa-chart-line text-success"></i>
                <div class="stat-value"><?= $quiz_stats['avg_score'] ?>%</div>
                <div class="stat-label">Nilai Rata-Rata</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-stats text-center">
                <i class="fas fa-comments text-info"></i>
                <div class="stat-value"><?= $forum_activity['total'] ?></div>
                <div class="stat-label">Partisipasi Forum</div>
            </div>
        </div>
    </div>
    
    <!-- Progress Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>Perkembangan Nilai
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="progressChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Distribusi Nilai
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="distributionChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Content Rows -->
    <div class="row">
        <!-- Latest Materials -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-book me-2"></i>Materi Terbaru
                    </h5>
                    <a href="../materi/index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($latest_materials)): ?>
                        <div class="list-group">
                            <?php foreach ($latest_materials as $materi): ?>
                                <a href="../materi/view.php?id=<?= $materi['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($materi['judul']) ?></h6>
                                        <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($materi['kategori']) ?></span>
                                    </div>
                                    <p class="mb-1 text-truncate"><?= htmlspecialchars($materi['deskripsi']) ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i><?= format_date($materi['created_at']) ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Belum ada materi tersedia.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Ongoing Quizzes -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>Kuis yang Tersedia
                    </h5>
                    <a href="../quiz/index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($ongoing_quizzes)): ?>
                        <div class="list-group">
                            <?php foreach ($ongoing_quizzes as $quiz): ?>
                                <a href="../quiz/take.php?id=<?= $quiz['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($quiz['judul']) ?></h6>
                                        <span class="badge bg-warning rounded-pill">
                                            <i class="fas fa-clock me-1"></i><?= $quiz['durasi'] ?> menit
                                        </span>
                                    </div>
                                    <p class="mb-1 text-truncate"><?= htmlspecialchars($quiz['deskripsi']) ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-question-circle me-1"></i><?= $quiz['total_questions'] ?> pertanyaan
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Tidak ada kuis yang tersedia saat ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Quiz Results -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>Hasil Kuis Terbaru
                    </h5>
                    <a href="../quiz/results.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_results)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Judul Kuis</th>
                                        <th>Nilai</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_results as $result): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($result['judul']) ?></td>
                                            <td>
                                                <span class="badge <?= $result['score'] >= 70 ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $result['score'] ?>%
                                                </span>
                                            </td>
                                            <td><?= format_date($result['completed_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Anda belum menyelesaikan kuis apapun.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Aktivitas Terbaru
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php if (!empty($recent_views)): ?>
                            <?php foreach ($recent_views as $view): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-book-open text-muted me-2"></i>
                                    Melihat materi <a href="../materi/view.php?id=<?= $view['id'] ?>"><?= htmlspecialchars($view['judul']) ?></a>
                                    <div class="small text-muted"><?= format_date($view['viewed_at']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($forum_posts)): ?>
                            <?php foreach ($forum_posts as $post): ?>
                                <li class="list-group-item">
                                    <i class="fas <?= $post['type'] == 'topic' ? 'fa-comment-dots' : 'fa-reply' ?> text-muted me-2"></i>
                                    <?= $post['type'] == 'topic' ? 'Membuat topik' : 'Membalas di' ?>
                                    <a href="../forum/view.php?id=<?= $post['topic_id'] ?>"><?= htmlspecialchars($post['title']) ?></a>
                                    <div class="small text-muted"><?= format_date($post['created_at']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (empty($recent_views) && empty($forum_posts)): ?>
                            <li class="list-group-item">
                                <i class="fas fa-info-circle text-info me-2"></i>
                                Belum ada aktivitas baru.
                            </li>
                        <?php endif; ?>
                    </ul>
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
    // Sample data for progress chart - replace with actual data from database
    const progressLabels = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
    const progressData = [65, 70, 75, 72, 80, 85];
    
    // Initialize progress chart
    initStudentProgressChart('progressChart', progressLabels, progressData);
    
    // Sample data for distribution chart - replace with actual data
    const distributionLabels = ['90-100', '80-89', '70-79', '< 70'];
    const distributionData = [10, 30, 40, 20];
    
    // Initialize distribution chart
    initQuizDistributionChart('distributionChart', distributionLabels, distributionData);
});
</script>

</body>
</html>
