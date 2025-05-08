<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
require_login();

// Determine which view to show
$view_mode = '';
$single_result = false;

// Case 1: Admin viewing all results for a specific quiz
if (is_admin() && isset($_GET['id']) && !empty($_GET['id'])) {
    $view_mode = 'admin_quiz';
    $quiz_id = (int)$_GET['id'];
    
    // Get quiz details
    $quiz = get_record(
        "SELECT * FROM quiz WHERE id = ?",
        [$quiz_id],
        "i"
    );
    
    // If quiz not found
    if (!$quiz) {
        set_flash_message('Kuis tidak ditemukan.', 'danger');
        redirect('index.php');
    }
    
    // Get all results for this quiz
    $results = get_records(
        "SELECT hk.*, u.username, u.full_name 
         FROM hasil_kuis hk 
         JOIN users u ON hk.user_id = u.id 
         WHERE hk.quiz_id = ? 
         ORDER BY hk.completed_at DESC",
        [$quiz_id],
        "i"
    );
}
// Case 2: Student viewing their results for a specific quiz
elseif (!is_admin() && isset($_GET['quiz_id']) && !empty($_GET['quiz_id'])) {
    $view_mode = 'student_quiz';
    $quiz_id = (int)$_GET['quiz_id'];
    
    // Get quiz details
    $quiz = get_record(
        "SELECT * FROM quiz WHERE id = ?",
        [$quiz_id],
        "i"
    );
    
    // If quiz not found
    if (!$quiz) {
        set_flash_message('Kuis tidak ditemukan.', 'danger');
        redirect('index.php');
    }
    
    // Get student's result for this quiz
    $result = get_record(
        "SELECT * FROM hasil_kuis WHERE user_id = ? AND quiz_id = ?",
        [$_SESSION['user_id'], $quiz_id],
        "ii"
    );
    
    if (!$result) {
        set_flash_message('Anda belum mengerjakan kuis ini.', 'warning');
        redirect('index.php');
    }
    
    $single_result = true;
}
// Case 3: Student viewing a specific result
elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $view_mode = 'single_result';
    $result_id = (int)$_GET['id'];
    
    // Get result details
    $result = get_record(
        "SELECT hk.*, q.judul, q.kategori, q.durasi 
         FROM hasil_kuis hk 
         JOIN quiz q ON hk.quiz_id = q.id 
         WHERE hk.id = ?",
        [$result_id],
        "i"
    );
    
    // If result not found
    if (!$result) {
        set_flash_message('Hasil kuis tidak ditemukan.', 'danger');
        redirect('index.php');
    }
    
    // Check if user has permission to view this result
    if (!is_admin() && $result['user_id'] != $_SESSION['user_id']) {
        set_flash_message('Anda tidak memiliki akses untuk melihat hasil ini.', 'danger');
        redirect('index.php');
    }
    
    $single_result = true;
    $quiz_id = $result['quiz_id'];
}
// Case 4: Student viewing all their results
elseif (!is_admin()) {
    $view_mode = 'student_all';
    
    // Get all results for this student
    $results = get_records(
        "SELECT hk.*, q.judul, q.kategori, q.durasi 
         FROM hasil_kuis hk 
         JOIN quiz q ON hk.quiz_id = q.id 
         WHERE hk.user_id = ? 
         ORDER BY hk.completed_at DESC",
        [$_SESSION['user_id']],
        "i"
    );
}
// Case 5: Admin viewing all results (dashboard)
else {
    $view_mode = 'admin_all';
    
    // Get latest results
    $results = get_records(
        "SELECT hk.*, q.judul, q.kategori, u.username, u.full_name 
         FROM hasil_kuis hk 
         JOIN quiz q ON hk.quiz_id = q.id 
         JOIN users u ON hk.user_id = u.id 
         ORDER BY hk.completed_at DESC 
         LIMIT 20"
    );
}

// Get question details if viewing a single result
$questions = [];
if ($single_result && isset($result)) {
    // Parse answers JSON
    $user_answers = json_decode($result['jawaban'], true) ?? [];
    
    // Get questions for this quiz
    $questions = get_records(
        "SELECT * FROM quiz_questions WHERE quiz_id = ?",
        [$quiz_id],
        "i"
    );
    
    // Add user's answers to questions array
    foreach ($questions as &$question) {
        $question['user_answer'] = $user_answers[$question['id']] ?? '';
    }
}

// Log the page view
log_activity($_SESSION['user_id'], 'view', 'quiz_results');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kuis - Sumber Belajar Interaktif</title>
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
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Beranda</a></li>
            <li class="breadcrumb-item"><a href="index.php">Kuis</a></li>
            <?php if ($view_mode == 'admin_quiz' || $view_mode == 'student_quiz'): ?>
                <li class="breadcrumb-item"><a href="index.php"><?= htmlspecialchars($quiz['judul']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Hasil Kuis</li>
        </ol>
    </nav>
    
    <?php if ($view_mode == 'admin_quiz'): ?>
        <!-- Admin viewing results for a specific quiz -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-poll me-2"></i>Hasil Kuis: <?= htmlspecialchars($quiz['judul']) ?></h4>
                <a href="edit.php?id=<?= $quiz_id ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-edit me-1"></i> Edit Kuis
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Kategori:</strong> <?= htmlspecialchars($quiz['kategori']) ?></p>
                        <p><strong>Durasi:</strong> <?= $quiz['durasi'] ?> menit</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total Partisipan:</strong> <?= count($results) ?></p>
                        <?php if (!empty($results)): ?>
                            <p><strong>Nilai Rata-rata:</strong> 
                                <?= number_format(array_sum(array_column($results, 'score')) / count($results), 1) ?>%
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($results)): ?>
                    <!-- Score Distribution Chart -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="stats-container">
                                <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Distribusi Nilai</h5>
                                <canvas id="scoreDistributionChart" height="250"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-container">
                                <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Tren Nilai</h5>
                                <canvas id="scoreTrendChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Results Table -->
                    <h5 class="mb-3"><i class="fas fa-list me-2"></i>Daftar Hasil Kuis</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>Nilai</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['full_name']) ?> (<?= htmlspecialchars($item['username']) ?>)</td>
                                        <td>
                                            <span class="badge <?= getScoreBadgeClass($item['score']) ?>">
                                                <?= $item['score'] ?>%
                                            </span>
                                        </td>
                                        <td><?= format_date($item['completed_at']) ?></td>
                                        <td>
                                            <a href="results.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Belum ada siswa yang mengerjakan kuis ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($view_mode == 'student_quiz' || $view_mode == 'single_result'): ?>
        <!-- Student viewing their result for a specific quiz OR single result view -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-poll me-2"></i>Hasil Kuis: 
                    <?= isset($quiz) ? htmlspecialchars($quiz['judul']) : htmlspecialchars($result['judul']) ?>
                </h4>
            </div>
            <div class="card-body">
                <!-- Result Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Informasi Kuis</h5>
                                <p><strong>Kategori:</strong> 
                                    <?= isset($quiz) ? htmlspecialchars($quiz['kategori']) : htmlspecialchars($result['kategori']) ?>
                                </p>
                                <p><strong>Durasi:</strong> 
                                    <?= isset($quiz) ? $quiz['durasi'] : $result['durasi'] ?> menit
                                </p>
                                <p><strong>Selesai pada:</strong> <?= format_date($result['completed_at']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-center border-0 bg-light">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-award me-2"></i>Nilai Anda</h5>
                                <div class="display-1 fw-bold text-<?= getScoreColorClass($result['score']) ?>">
                                    <?= $result['score'] ?>%
                                </div>
                                <p class="mt-2">
                                    <?= getScoreMessage($result['score']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Score Visualization -->
                <div class="mb-4">
                    <div class="stats-container">
                        <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Hasil Kuis</h5>
                        <canvas id="resultChart" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Questions and Answers Review -->
                <h5 class="mb-3"><i class="fas fa-question-circle me-2"></i>Detail Jawaban</h5>
                
                <?php if (!empty($questions)): ?>
                    <div class="accordion" id="questionsAccordion">
                        <?php foreach ($questions as $index => $question): ?>
                            <?php 
                            $isCorrect = $question['user_answer'] === $question['jawaban_benar'];
                            $accordionClass = $isCorrect ? 'accordion-success' : 'accordion-danger';
                            ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?= $index ?>">
                                    <button class="accordion-button <?= $isCorrect ? 'text-success' : 'text-danger' ?> collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="false" aria-controls="collapse<?= $index ?>">
                                        <div class="d-flex justify-content-between w-100 me-3">
                                            <div>
                                                <span class="badge bg-secondary me-2"><?= $index + 1 ?></span>
                                                <?= htmlspecialchars($question['pertanyaan']) ?>
                                            </div>
                                            <div>
                                                <?php if ($isCorrect): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Benar</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Salah</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?= $index ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $index ?>" data-bs-parent="#questionsAccordion">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Jawaban Anda</h6>
                                                <div class="option-display mb-3">
                                                    <?php 
                                                    $user_option = strtoupper($question['user_answer'] ?: '-');
                                                    echo "<strong>$user_option.</strong> ";
                                                    echo htmlspecialchars($question["pilihan_$question[user_answer]"] ?? 'Tidak dijawab');
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Jawaban Benar</h6>
                                                <div class="option-display">
                                                    <?php 
                                                    $correct_option = strtoupper($question['jawaban_benar']);
                                                    echo "<strong>$correct_option.</strong> ";
                                                    echo htmlspecialchars($question["pilihan_$question[jawaban_benar]"]);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Detail jawaban tidak tersedia.
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Kuis
                    </a>
                    <?php if ($view_mode == 'student_quiz'): ?>
                        <a href="../stats/student.php" class="btn btn-primary">
                            <i class="fas fa-chart-line me-2"></i>Lihat Statistik Saya
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php elseif ($view_mode == 'student_all'): ?>
        <!-- Student viewing all their results -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-poll me-2"></i>Hasil Kuis Saya</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($results)): ?>
                    <!-- Score Summary -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card text-center border-0 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Total Kuis</h5>
                                    <div class="display-3 fw-bold text-primary"><?= count($results) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center border-0 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Rata-rata Nilai</h5>
                                    <div class="display-3 fw-bold text-success">
                                        <?= number_format(array_sum(array_column($results, 'score')) / count($results), 1) ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center border-0 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-trophy me-2"></i>Nilai Tertinggi</h5>
                                    <div class="display-3 fw-bold text-warning">
                                        <?= number_format(max(array_column($results, 'score')), 1) ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Score Distribution Chart -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="stats-container">
                                <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Distribusi Nilai</h5>
                                <canvas id="scoreDistributionChart" height="250"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-container">
                                <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Tren Nilai</h5>
                                <canvas id="scoreTrendChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Results Table -->
                    <h5 class="mb-3"><i class="fas fa-list me-2"></i>Riwayat Kuis</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>No</th>
                                    <th>Judul Kuis</th>
                                    <th>Kategori</th>
                                    <th>Nilai</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['judul']) ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($item['kategori']) ?></span></td>
                                        <td>
                                            <span class="badge <?= getScoreBadgeClass($item['score']) ?>">
                                                <?= $item['score'] ?>%
                                            </span>
                                        </td>
                                        <td><?= format_date($item['completed_at']) ?></td>
                                        <td>
                                            <a href="results.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Anda belum mengerjakan kuis apapun.
                    </div>
                    <div class="text-center mt-3">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-tasks me-2"></i>Lihat Daftar Kuis
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($view_mode == 'admin_all'): ?>
        <!-- Admin viewing all results (dashboard) -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-poll me-2"></i>Semua Hasil Kuis</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($results)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Menampilkan 20 hasil kuis terbaru. Untuk melihat semua hasil dari kuis tertentu, silakan kunjungi halaman kuis yang bersangkutan.
                    </div>
                    
                    <!-- Results Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>No</th>
                                    <th>Siswa</th>
                                    <th>Judul Kuis</th>
                                    <th>Kategori</th>
                                    <th>Nilai</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['full_name']) ?> (<?= htmlspecialchars($item['username']) ?>)</td>
                                        <td><?= htmlspecialchars($item['judul']) ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($item['kategori']) ?></span></td>
                                        <td>
                                            <span class="badge <?= getScoreBadgeClass($item['score']) ?>">
                                                <?= $item['score'] ?>%
                                            </span>
                                        </td>
                                        <td><?= format_date($item['completed_at']) ?></td>
                                        <td>
                                            <a href="results.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Belum ada hasil kuis yang tersedia.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
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
    <?php if ($view_mode == 'student_quiz' || $view_mode == 'single_result'): ?>
        // Result Pie Chart for Single Result
        const ctxResult = document.getElementById('resultChart').getContext('2d');
        const resultChart = new Chart(ctxResult, {
            type: 'pie',
            data: {
                labels: ['Benar', 'Salah'],
                datasets: [{
                    data: [<?= $result['score'] ?>, <?= 100 - $result['score'] ?>],
                    backgroundColor: [
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                    ],
                    borderColor: [
                        'rgba(46, 204, 113, 1)',
                        'rgba(231, 76, 60, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw}%`;
                            }
                        }
                    }
                }
            }
        });
    <?php endif; ?>
    
    <?php if (($view_mode == 'admin_quiz' || $view_mode == 'student_all') && !empty($results)): ?>
        // Score Distribution Chart
        const ctxDistribution = document.getElementById('scoreDistributionChart').getContext('2d');
        
        // Calculate score distribution
        const scoreRanges = {
            '90-100': 0,
            '80-89': 0,
            '70-79': 0,
            '60-69': 0,
            '0-59': 0
        };
        
        <?php foreach ($results as $item): ?>
            const score = <?= $item['score'] ?>;
            if (score >= 90) scoreRanges['90-100']++;
            else if (score >= 80) scoreRanges['80-89']++;
            else if (score >= 70) scoreRanges['70-79']++;
            else if (score >= 60) scoreRanges['60-69']++;
            else scoreRanges['0-59']++;
        <?php endforeach; ?>
        
        const distributionChart = new Chart(ctxDistribution, {
            type: 'doughnut',
            data: {
                labels: Object.keys(scoreRanges),
                datasets: [{
                    data: Object.values(scoreRanges),
                    backgroundColor: [
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(241, 196, 15, 0.8)',
                        'rgba(230, 126, 34, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                    ],
                    borderColor: [
                        'rgba(46, 204, 113, 1)',
                        'rgba(52, 152, 219, 1)',
                        'rgba(241, 196, 15, 1)',
                        'rgba(230, 126, 34, 1)',
                        'rgba(231, 76, 60, 1)',
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
                }
            }
        });
        
        // Score Trend Chart
        const ctxTrend = document.getElementById('scoreTrendChart').getContext('2d');
        
        // Get latest 10 results (in chronological order)
        const trendData = <?= json_encode(array_reverse(array_slice(array_reverse(array_column($results, 'score')), 0, 10))) ?>;
        const trendLabels = <?= json_encode(array_map(function($date) { 
            return date('d/m', strtotime($date)); 
        }, array_reverse(array_slice(array_reverse(array_column($results, 'completed_at')), 0, 10)))) ?>;
        
        const trendChart = new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Nilai',
                    data: trendData,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20
                        }
                    }
                }
            }
        });
    <?php endif; ?>
});

// Helper function to get score badge class
function getScoreBadgeClass(score) {
    if (score >= 90) return 'bg-success';
    if (score >= 80) return 'bg-primary';
    if (score >= 70) return 'bg-info';
    if (score >= 60) return 'bg-warning';
    return 'bg-danger';
}

// Helper function to get score color class
function getScoreColorClass(score) {
    if (score >= 90) return 'success';
    if (score >= 80) return 'primary';
    if (score >= 70) return 'info';
    if (score >= 60) return 'warning';
    return 'danger';
}

// Helper function to get score message
function getScoreMessage(score) {
    if (score >= 90) return 'Luar Biasa! Penguasaan materi Anda sangat baik.';
    if (score >= 80) return 'Bagus! Anda telah menguasai sebagian besar materi.';
    if (score >= 70) return 'Cukup baik. Anda telah menguasai materi dasar.';
    if (score >= 60) return 'Cukup. Masih ada beberapa materi yang perlu dipelajari lagi.';
    return 'Perlu belajar lebih banyak. Jangan menyerah!';
}
</script>

</body>
</html>
