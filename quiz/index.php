<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
require_login();

// Get filter parameters
$kategori = isset($_GET['kategori']) ? sanitize($_GET['kategori']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build the SQL query based on filters
$sql = "SELECT q.*, u.username as created_by, 
        (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) as total_questions";

// Add sub-query to check if the current user has completed the quiz
if (!is_admin()) {
    $sql .= ", (SELECT COUNT(*) FROM hasil_kuis hk WHERE hk.quiz_id = q.id AND hk.user_id = ?) as completed";
}

$sql .= " FROM quiz q LEFT JOIN users u ON q.user_id = u.id WHERE 1=1";

$params = [];
$types = "";

// Add user_id parameter if student (for completed sub-query)
if (!is_admin()) {
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

if (!empty($kategori)) {
    $sql .= " AND q.kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (q.judul LIKE ? OR q.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$sql .= " ORDER BY q.created_at DESC";

// Execute the query
$quizzes = get_records($sql, $params, $types);

// Get all available categories for the filter
$categories = get_categories();

// Log the page view
log_activity($_SESSION['user_id'], 'view', 'quiz_list');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Kuis - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-tasks me-2"></i>Daftar Kuis
        </h1>
        <?php if (is_admin()): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Tambah Kuis
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="index.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Cari Kuis</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Cari judul atau deskripsi..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="kategori" class="form-label">Filter Kategori</label>
                    <select class="form-select" id="kategori" name="kategori" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $key => $value): ?>
                            <option value="<?= $key ?>" <?= $kategori === $key ? 'selected' : '' ?>>
                                <?= $value ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-sync-alt me-2"></i>Reset Filter
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Quiz Cards -->
    <div class="row">
        <?php if (!empty($quizzes)): ?>
            <?php foreach ($quizzes as $quiz): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 quiz-item" data-category="<?= htmlspecialchars($quiz['kategori']) ?>">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-tasks me-2"></i>Kuis
                            </span>
                            <span class="badge bg-warning">
                                <i class="fas fa-clock me-1"></i><?= $quiz['durasi'] ?> menit
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($quiz['judul']) ?></h5>
                            <span class="badge bg-primary mb-2"><?= htmlspecialchars($quiz['kategori']) ?></span>
                            
                            <?php if (!is_admin() && isset($quiz['completed']) && $quiz['completed'] > 0): ?>
                                <span class="badge bg-success mb-2 ms-2">
                                    <i class="fas fa-check-circle me-1"></i>Selesai
                                </span>
                            <?php endif; ?>
                            
                            <p class="card-text"><?= nl2br(htmlspecialchars(substr($quiz['deskripsi'], 0, 100))) ?>...</p>
                            
                            <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                                <span><i class="fas fa-question-circle me-1"></i><?= $quiz['total_questions'] ?> pertanyaan</span>
                                <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($quiz['created_by']) ?></span>
                            </div>
                            
                            <div class="d-grid">
                                <?php if (is_admin() || ($_SESSION['role'] === 'teacher' && $quiz['user_id'] == $_SESSION['user_id'])): ?>
                                    <a href="edit.php?id=<?= $quiz['id'] ?>" class="btn btn-outline-primary mb-2">
                                        <i class="fas fa-edit me-1"></i> Edit Kuis
                                    </a>
                                    <a href="results.php?id=<?= $quiz['id'] ?>" class="btn btn-outline-info mb-2">
                                        <i class="fas fa-poll me-1"></i> Lihat Hasil
                                    </a>
                                    <a href="delete.php?id=<?= $quiz['id'] ?>" class="btn btn-outline-danger">
                                        <i class="fas fa-trash-alt me-1"></i> Hapus Kuis
                                    </a>
                                <?php else: ?>
                                    <?php if (isset($quiz['completed']) && $quiz['completed'] > 0): ?>
                                        <a href="results.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-success">
                                            <i class="fas fa-eye me-1"></i> Lihat Hasil Saya
                                        </a>
                                    <?php else: ?>
                                        <a href="take.php?id=<?= $quiz['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-play-circle me-1"></i> Mulai Kuis
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <i class="fas fa-calendar-alt me-1"></i><?= format_date($quiz['created_at'], false) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php if (!empty($kategori) || !empty($search)): ?>
                        Tidak ada kuis yang sesuai dengan filter yang dipilih.
                    <?php else: ?>
                        Belum ada kuis yang tersedia.
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($kategori) || !empty($search)): ?>
                    <div class="text-center mb-4">
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>Lihat Semua Kuis
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Category Filter Buttons -->
    <div class="mt-4 mb-5">
        <h4>Filter Cepat Berdasarkan Kategori</h4>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-primary filter-btn active" data-category="all" onclick="filterQuizzes('all')">
                Semua
            </button>
            <?php foreach ($categories as $key => $value): ?>
                <button type="button" class="btn btn-outline-primary filter-btn" data-category="<?= $key ?>" onclick="filterQuizzes('<?= $key ?>')">
                    <?= $value ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Quiz Info Section -->
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Tentang Kuis</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-question-circle me-2"></i>Apa itu Kuis?</h5>
                    <p>Kuis adalah sarana pengujian pemahaman materi melalui serangkaian pertanyaan yang dirancang untuk menguji pengetahuan Anda tentang topik tertentu.</p>
                    
                    <h5><i class="fas fa-hourglass-half me-2"></i>Durasi Kuis</h5>
                    <p>Setiap kuis memiliki batas waktu yang berbeda. Pastikan Anda menyelesaikan kuis dalam waktu yang ditentukan untuk mendapatkan hasil terbaik.</p>
                </div>
                <div class="col-md-6">
                    <h5><i class="fas fa-star me-2"></i>Penilaian</h5>
                    <p>Nilai kuis dihitung berdasarkan jumlah jawaban benar. Hasil langsung ditampilkan setelah Anda menyelesaikan kuis.</p>
                    
                    <h5><i class="fas fa-graduation-cap me-2"></i>Tips Mengerjakan Kuis</h5>
                    <ul>
                        <li>Baca materi terkait terlebih dahulu</li>
                        <li>Siapkan waktu yang cukup tanpa gangguan</li>
                        <li>Baca pertanyaan dengan teliti sebelum menjawab</li>
                        <li>Jangan mengulur waktu terlalu lama pada satu soal</li>
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
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<script>
// JavaScript for client-side filtering
function filterQuizzes(category) {
    const quizzes = document.querySelectorAll('.quiz-item');
    
    quizzes.forEach(item => {
        if (category === 'all' || item.dataset.category === category) {
            item.closest('.col-md-4').style.display = 'block';
        } else {
            item.closest('.col-md-4').style.display = 'none';
        }
    });
    
    // Update active filter button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.filter-btn[data-category="${category}"]`).classList.add('active');
}
</script>

</body>
</html>
