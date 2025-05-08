<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sumber Belajar Interaktif - Beranda</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="container mt-4">
    <div class="p-4 p-md-5 mb-4 text-white rounded bg-primary">
        <div class="row">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold">Sumber Belajar Interaktif</h1>
                <p class="lead my-3">Platform belajar interaktif untuk membantu siswa dan guru dalam proses pembelajaran yang lebih menarik dan efektif.</p>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <p class="lead mb-0">
                        <a href="auth/register.php" class="btn btn-lg btn-light fw-bold">Daftar Sekarang</a>
                        <a href="auth/login.php" class="btn btn-lg btn-outline-light">Masuk</a>
                    </p>
                <?php else: ?>
                    <p class="lead mb-0">
                        <a href="<?= $_SESSION['role'] == 'admin' ? 'dashboard/admin.php' : 'dashboard/student.php' ?>" 
                           class="btn btn-lg btn-light fw-bold">
                            <i class="fas fa-tachometer-alt me-2"></i>Masuk ke Dashboard
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center">
                <img src="assets/images/student-library.jpg" 
                     alt="Siswa dalam Perpustakaan" class="img-fluid rounded" style="max-height: 300px;">
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-book fa-3x text-primary mb-3"></i>
                    <h4 class="card-title">Materi Berkualitas</h4>
                    <p class="card-text">Akses berbagai jenis materi pembelajaran dalam format teks, video, dan file interaktif.</p>
                    <a href="materi/index.php" class="btn btn-sm btn-outline-primary mt-2">Lihat Materi</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-tasks fa-3x text-primary mb-3"></i>
                    <h4 class="card-title">Kuis Interaktif</h4>
                    <p class="card-text">Uji pemahaman Anda dengan berbagai latihan dan kuis yang tersedia untuk setiap materi.</p>
                    <a href="quiz/index.php" class="btn btn-sm btn-outline-primary mt-2">Coba Kuis</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-comments fa-3x text-primary mb-3"></i>
                    <h4 class="card-title">Forum Diskusi</h4>
                    <p class="card-text">Diskusikan materi dengan sesama siswa dan guru untuk memperdalam pemahaman.</p>
                    <a href="forum/index.php" class="btn btn-sm btn-outline-primary mt-2">Masuk Forum</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-trophy fa-3x text-warning mb-3"></i>
                    <h4 class="card-title">Siswa Berprestasi</h4>
                    <p class="card-text">Lihat peringkat siswa terbaik berdasarkan performa di kuis dan keaktifan belajar.</p>
                    <a href="stats/top_students.php" class="btn btn-sm btn-outline-warning mt-2">Lihat Peringkat</a>
                </div>
            </div>
        </div>
    </div>

    <h2 class="mb-3">Materi Terbaru</h2>
    <div class="row mb-4">
        <?php
        // Fetch latest materials
        $sql = "SELECT * FROM materi ORDER BY created_at DESC LIMIT 6";
        $result = $conn->query($sql);

        // SQLite tidak memiliki properti num_rows, jadi kita gunakan pendekatan yang berbeda
        $has_data = false;
        if ($result) {
            // Periksa apakah ada data dengan mencoba fetch baris pertama
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row) {
                $has_data = true;
                // Tampilkan baris pertama
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($row['judul']) ?></h5>
                            <p class="card-text"><?= substr(htmlspecialchars($row['deskripsi']), 0, 100) ?>...</p>
                            <p class="text-muted">Kategori: <?= htmlspecialchars($row['kategori']) ?></p>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="materi/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Baca Selengkapnya</a>
                        </div>
                    </div>
                </div>
                <?php
                
                // Lanjutkan dengan baris-baris berikutnya
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($row['judul']) ?></h5>
                            <p class="card-text"><?= substr(htmlspecialchars($row['deskripsi']), 0, 100) ?>...</p>
                            <p class="text-muted">Kategori: <?= htmlspecialchars($row['kategori']) ?></p>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="materi/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Baca Selengkapnya</a>
                        </div>
                    </div>
                </div>
                <?php
                } // End while
            } // End if ($row)
        }
        
        // Tampilkan pesan jika tidak ada data
        if (!$has_data) {
            echo '<div class="col-12"><div class="alert alert-info">Belum ada materi tersedia.</div></div>';
        }
        ?>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Untuk Siswa</h3>
                    <p class="card-text">Dapatkan akses ke berbagai materi belajar, kerjakan latihan untuk menguji pemahaman, dan diskusikan dengan teman-teman serta guru.</p>
                    <img src="assets/images/student-library.jpg" 
                         alt="Siswa dalam Perpustakaan" class="img-fluid rounded mb-3" style="max-height: 200px;">
                    <a href="auth/register.php" class="btn btn-primary">Daftar sebagai Siswa</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Untuk Guru</h3>
                    <p class="card-text">Unggah dan kelola materi pembelajaran, buat kuis interaktif, dan pantau perkembangan siswa dengan berbagai statistik.</p>
                    <img src="assets/images/student-library.jpg" 
                         alt="Guru dalam Perpustakaan" class="img-fluid rounded mb-3" style="max-height: 200px;">
                    <a href="auth/register.php" class="btn btn-primary">Daftar sebagai Guru</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/main.js"></script>

</body>
</html>
