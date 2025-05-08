<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is student
require_login();

if ($_SESSION['role'] !== 'student') {
    set_flash_message('Anda tidak memiliki akses untuk halaman ini.', 'danger');
    redirect('/index.php');
}

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_record("SELECT * FROM users WHERE id = ?", [$user_id], "i");

// Get quiz statistics
$quiz_stats = get_record(
    "SELECT COUNT(*) as total_attempt, AVG(score) as avg_score, MAX(score) as best_score
     FROM hasil_kuis
     WHERE user_id = ?",
    [$user_id],
    "i"
);

// Get material progress
$materi_total = get_record("SELECT COUNT(*) as count FROM materi")['count'];
$materi_viewed = get_record(
    "SELECT COUNT(DISTINCT materi_id) as count 
     FROM activity_logs 
     WHERE user_id = ? AND module = 'materi_view'",
    [$user_id],
    "i"
)['count'];

$materi_progress = $materi_total > 0 ? round(($materi_viewed / $materi_total) * 100) : 0;

// Get quiz results with details
$quiz_results = get_records(
    "SELECT hk.*, q.judul, q.kategori
     FROM hasil_kuis hk
     JOIN quiz q ON hk.quiz_id = q.id
     WHERE hk.user_id = ?
     ORDER BY hk.created_at DESC",
    [$user_id],
    "i"
);

// Get activity history
$activity_history = get_records(
    "SELECT *
     FROM activity_logs
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20",
    [$user_id],
    "i"
);

// Get material categories
$kategori_progress = get_records(
    "SELECT m.kategori, COUNT(DISTINCT m.id) as total_count,
     (
         SELECT COUNT(DISTINCT al.materi_id)
         FROM activity_logs al
         WHERE al.user_id = ? AND al.module = 'materi_view' AND 
               al.materi_id IN (SELECT id FROM materi WHERE kategori = m.kategori)
     ) as viewed_count
     FROM materi m
     GROUP BY m.kategori",
    [$user_id],
    "i"
);

// Calculate progress percentages for each category
foreach ($kategori_progress as &$kategori) {
    $kategori['progress'] = $kategori['total_count'] > 0 
        ? round(($kategori['viewed_count'] / $kategori['total_count']) * 100) 
        : 0;
}

// Get last activity date
$last_activity = get_record(
    "SELECT MAX(created_at) as last_date
     FROM activity_logs
     WHERE user_id = ?",
    [$user_id],
    "i"
);

// Get days active (unique days with activity)
$days_active = get_record(
    "SELECT COUNT(DISTINCT date(created_at)) as count
     FROM activity_logs
     WHERE user_id = ?",
    [$user_id],
    "i"
);

// Get monthly activity data for chart
$monthly_activity = get_records(
    "SELECT strftime('%Y-%m', created_at) as month, COUNT(*) as activity_count
     FROM activity_logs
     WHERE user_id = ?
     GROUP BY strftime('%Y-%m', created_at)
     ORDER BY month
     LIMIT 6",
    [$user_id],
    "i"
);

// Log the page view
log_activity($user_id, 'view', 'student_stats');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Belajar Saya - Sumber Belajar Interaktif</title>
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
            <i class="fas fa-chart-line me-2"></i>Progress Belajar Saya
        </h1>
        <a href="../dashboard/student.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
        </a>
    </div>
    
    <!-- Overview Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-book text-primary"></i>
                <div class="stat-value"><?= $materi_progress ?>%</div>
                <p class="stat-label">Progress Materi</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-tasks text-success"></i>
                <div class="stat-value"><?= $quiz_stats['total_attempt'] ?: 0 ?></div>
                <p class="stat-label">Kuis Diselesaikan</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-award text-warning"></i>
                <div class="stat-value"><?= number_format($quiz_stats['avg_score'] ?: 0, 1) ?></div>
                <p class="stat-label">Rata-rata Nilai</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-calendar-day text-info"></i>
                <div class="stat-value"><?= $days_active['count'] ?: 0 ?></div>
                <p class="stat-label">Hari Aktif</p>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
        <!-- Material Progress Chart -->
        <div class="col-md-6 mb-4">
            <div class="card stats-container">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>Progress Per Kategori
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($kategori_progress)): ?>
                        <?php foreach ($kategori_progress as $kategori): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?= htmlspecialchars($kategori['kategori']) ?></span>
                                    <span>
                                        <strong><?= $kategori['viewed_count'] ?></strong> / <?= $kategori['total_count'] ?>
                                        (<?= $kategori['progress'] ?>%)
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar <?= getProgressBarClass($kategori['progress']) ?>" 
                                         role="progressbar" 
                                         style="width: <?= $kategori['progress'] ?>%;" 
                                         aria-valuenow="<?= $kategori['progress'] ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada data progress materi.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Activity History Chart -->
        <div class="col-md-6 mb-4">
            <div class="card stats-container">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Aktivitas Bulanan
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quiz Performance Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card stats-container">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>Performa Kuis
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($quiz_results)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Judul Kuis</th>
                                        <th>Kategori</th>
                                        <th>Nilai</th>
                                        <th>Tanggal</th>
                                        <th>Durasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quiz_results as $result): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($result['judul']) ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($result['kategori']) ?></span></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar <?= getScoreBadgeClass($result['score']) ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $result['score'] ?>%;" 
                                                         aria-valuenow="<?= $result['score'] ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?= $result['score'] ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= format_date($result['created_at'], false) ?></td>
                                            <td><?= $result['duration'] ?> detik</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Anda belum mengerjakan kuis apapun. <a href="../quiz/index.php" class="alert-link">Kerjakan kuis sekarang</a> untuk melihat performa Anda.
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
                    <?php if (!empty($activity_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Aksi</th>
                                        <th>Modul</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity_history as $activity): ?>
                                        <tr>
                                            <td><?= format_date($activity['created_at']) ?></td>
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
// Activity Chart
const activityCtx = document.getElementById('activityChart').getContext('2d');
const activityData = {
    labels: [
        <?php 
            $months = [];
            $data = [];
            
            if (!empty($monthly_activity)) {
                foreach ($monthly_activity as $item) {
                    $date = new DateTime($item['month'] . '-01');
                    $months[] = "'" . $date->format('M Y') . "'";
                    $data[] = $item['activity_count'];
                }
            } else {
                // If no data, show last 6 months
                $date = new DateTime();
                for ($i = 5; $i >= 0; $i--) {
                    $date->modify('-' . $i . ' months');
                    $months[] = "'" . $date->format('M Y') . "'";
                    $data[] = 0;
                    $date->modify('+' . $i . ' months');
                }
            }
            
            echo implode(', ', $months);
        ?>
    ],
    datasets: [{
        label: 'Aktivitas',
        data: [<?= implode(', ', $data) ?>],
        backgroundColor: 'rgba(0, 77, 64, 0.7)',
        borderColor: 'rgba(0, 77, 64, 1)',
        borderWidth: 2,
        tension: 0.3,
        fill: true
    }]
};

const activityChart = new Chart(activityCtx, {
    type: 'line',
    data: activityData,
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
                text: 'Aktivitas Bulanan'
            }
        }
    }
});
</script>

<?php
/**
 * Get the appropriate CSS class for progress bars based on percentage
 * 
 * @param int $percentage Progress percentage
 * @return string CSS class
 */
function getProgressBarClass($percentage) {
    if ($percentage >= 80) {
        return 'bg-success';
    } else if ($percentage >= 50) {
        return 'bg-info';
    } else if ($percentage >= 25) {
        return 'bg-warning';
    } else {
        return 'bg-danger';
    }
}

// Function getScoreBadgeClass moved to includes/functions.php
?>