<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Get top students based on quiz scores
$top_students = get_records(
    "SELECT u.id, u.full_name, u.username, 
     COUNT(hk.id) as quiz_count, 
     AVG(hk.score) as avg_score,
     MAX(hk.score) as max_score,
     (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as activity_count,
     (
         SELECT COUNT(DISTINCT entity_id) 
         FROM activity_logs 
         WHERE user_id = u.id AND module = 'materi'
     ) as materi_viewed,
     (
         SELECT MAX(created_at) 
         FROM activity_logs 
         WHERE user_id = u.id
     ) as last_activity
     FROM users u
     JOIN hasil_kuis hk ON u.id = hk.user_id
     WHERE u.role = 'student'
     GROUP BY u.id
     ORDER BY avg_score DESC
     LIMIT 20"
);

// Get total materials for percentage calculation
$total_materials = get_record("SELECT COUNT(*) as count FROM materi")['count'];

// Get some statistics about all students
$student_stats = get_record(
    "SELECT COUNT(*) as total_students,
     (SELECT COUNT(*) FROM hasil_kuis) as total_quiz_attempts,
     (SELECT AVG(score) FROM hasil_kuis) as avg_score_all,
     (SELECT COUNT(*) FROM forum_topics WHERE user_id IN (SELECT id FROM users WHERE role = 'student')) +
     (SELECT COUNT(*) FROM forum_replies WHERE user_id IN (SELECT id FROM users WHERE role = 'student')) as forum_activities
     FROM users WHERE role = 'student'"
);

// Log the page view if logged in
if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'view', 'top_students');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siswa Berprestasi - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .student-card {
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .student-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .student-rank {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            z-index: 1;
        }
        
        .rank-1 {
            background-color: var(--secondary-color);
            color: #000;
        }
        
        .rank-2 {
            background-color: #C0C0C0;
            color: #000;
        }
        
        .rank-3 {
            background-color: #CD7F32;
            color: #fff;
        }
        
        .student-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #fff;
        }
        
        .rank-badge {
            display: inline-block;
            min-width: 30px;
            text-align: center;
        }
        
        .trophy-icon {
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 2rem;
            transform: rotate(15deg);
        }
        
        .stat-highlight {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-trophy me-2 text-warning"></i>Siswa Berprestasi
        </h1>
        <div>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                <a href="../stats/admin.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-chart-bar me-2"></i>Statistik Lengkap
                </a>
            <?php endif; ?>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>Kembali ke Beranda
            </a>
        </div>
    </div>
    
    <!-- Overview Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-user-graduate text-primary"></i>
                <div class="stat-value"><?= $student_stats['total_students'] ?></div>
                <p class="stat-label">Total Siswa</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-tasks text-success"></i>
                <div class="stat-value"><?= $student_stats['total_quiz_attempts'] ?></div>
                <p class="stat-label">Total Kuis</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-star text-warning"></i>
                <div class="stat-value"><?= number_format($student_stats['avg_score_all'] ?: 0, 1) ?></div>
                <p class="stat-label">Rata-rata Nilai</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-comments text-info"></i>
                <div class="stat-value"><?= $student_stats['forum_activities'] ?></div>
                <p class="stat-label">Aktivitas Forum</p>
            </div>
        </div>
    </div>
    
    <!-- Top 3 Students Highlight -->
    <div class="row mb-5">
        <?php if (!empty($top_students) && count($top_students) >= 3): ?>
            <h2 class="mb-4">🏆 Peringkat Teratas</h2>
            
            <!-- Second Place -->
            <div class="col-md-4 mb-4">
                <div class="card student-card text-center position-relative">
                    <div class="student-rank rank-2">2</div>
                    <div class="card-body pt-5">
                        <div class="student-avatar bg-secondary mb-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4 class="mb-0"><?= htmlspecialchars($top_students[1]['full_name']) ?></h4>
                        <p class="text-muted">@<?= htmlspecialchars($top_students[1]['username']) ?></p>
                        
                        <div class="d-flex justify-content-around my-4">
                            <div class="text-center">
                                <div class="fs-4 fw-bold"><?= number_format($top_students[1]['avg_score'], 1) ?></div>
                                <small class="text-muted">Rata-rata</small>
                            </div>
                            <div class="text-center">
                                <div class="fs-4 fw-bold"><?= $top_students[1]['quiz_count'] ?></div>
                                <small class="text-muted">Kuis</small>
                            </div>
                        </div>
                        
                        <div class="text-secondary">
                            <i class="fas fa-medal fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- First Place (Center and larger) -->
            <div class="col-md-4 mb-4">
                <div class="card student-card text-center position-relative" style="transform: translateY(-15px);">
                    <div class="student-rank rank-1">1</div>
                    <i class="fas fa-crown text-warning trophy-icon"></i>
                    <div class="card-body pt-5">
                        <div class="student-avatar bg-warning mb-3" style="width: 120px; height: 120px; font-size: 3.5rem;">
                            <i class="fas fa-user"></i>
                        </div>
                        <h3 class="mb-0"><?= htmlspecialchars($top_students[0]['full_name']) ?></h3>
                        <p class="text-muted">@<?= htmlspecialchars($top_students[0]['username']) ?></p>
                        
                        <div class="d-flex justify-content-around my-4">
                            <div class="text-center">
                                <div class="fs-3 fw-bold"><?= number_format($top_students[0]['avg_score'], 1) ?></div>
                                <small class="text-muted">Rata-rata</small>
                            </div>
                            <div class="text-center">
                                <div class="fs-3 fw-bold"><?= $top_students[0]['quiz_count'] ?></div>
                                <small class="text-muted">Kuis</small>
                            </div>
                        </div>
                        
                        <div class="text-warning">
                            <i class="fas fa-trophy fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Third Place -->
            <div class="col-md-4 mb-4">
                <div class="card student-card text-center position-relative">
                    <div class="student-rank rank-3">3</div>
                    <div class="card-body pt-5">
                        <div class="student-avatar bg-success mb-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4 class="mb-0"><?= htmlspecialchars($top_students[2]['full_name']) ?></h4>
                        <p class="text-muted">@<?= htmlspecialchars($top_students[2]['username']) ?></p>
                        
                        <div class="d-flex justify-content-around my-4">
                            <div class="text-center">
                                <div class="fs-4 fw-bold"><?= number_format($top_students[2]['avg_score'], 1) ?></div>
                                <small class="text-muted">Rata-rata</small>
                            </div>
                            <div class="text-center">
                                <div class="fs-4 fw-bold"><?= $top_students[2]['quiz_count'] ?></div>
                                <small class="text-muted">Kuis</small>
                            </div>
                        </div>
                        
                        <div class="text-success">
                            <i class="fas fa-award fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Belum cukup data untuk menampilkan siswa berprestasi. Minimal diperlukan 3 siswa yang telah menyelesaikan kuis.
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Full Leaderboard -->
    <div class="row">
        <div class="col-md-12">
            <div class="card stats-container">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list-ol me-2"></i>Peringkat Lengkap
                    </h5>
                    <span class="badge bg-light text-dark"><?= count($top_students) ?> Siswa</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_students)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">Peringkat</th>
                                        <th width="30%">Nama</th>
                                        <th width="15%">Nilai Rata-rata</th>
                                        <th width="10%">Kuis</th>
                                        <th width="15%">Materi Dipelajari</th>
                                        <th width="15%">Aktivitas</th>
                                        <th width="10%">Terakhir Aktif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_students as $index => $student): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge 
                                                    <?php 
                                                    if ($index === 0) echo 'bg-warning text-dark';
                                                    else if ($index === 1) echo 'bg-secondary text-white';
                                                    else if ($index === 2) echo 'bg-success text-white';
                                                    else echo 'bg-light text-dark';
                                                    ?>">
                                                    <?= $index + 1 ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($student['full_name']) ?></td>
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
                                            <td><?= $student['quiz_count'] ?></td>
                                            <td>
                                                <?php 
                                                    $progress = $total_materials > 0 ? round(($student['materi_viewed'] / $total_materials) * 100) : 0;
                                                ?>
                                                <div class="progress">
                                                    <div class="progress-bar bg-info" 
                                                         role="progressbar" 
                                                         style="width: <?= $progress ?>%;" 
                                                         aria-valuenow="<?= $progress ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?= $progress ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= $student['activity_count'] ?></td>
                                            <td><?= time_elapsed_string($student['last_activity']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada data siswa yang telah menyelesaikan kuis.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Section -->
    <div class="mt-4 p-4 bg-light rounded">
        <h4><i class="fas fa-info-circle me-2"></i>Tentang Peringkat</h4>
        <p>Peringkat siswa dihitung berdasarkan rata-rata nilai semua kuis yang telah diselesaikan. Faktor lain yang mempengaruhi peringkat adalah jumlah materi yang telah dipelajari dan tingkat keaktifan dalam forum diskusi.</p>
        <p>Pastikan untuk menyelesaikan seluruh kuis dengan nilai terbaik untuk meningkatkan peringkat Anda!</p>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<?php
// Fungsi getScoreBadgeClass() sudah didefinisikan di includes/functions.php
?>
</body>
</html>