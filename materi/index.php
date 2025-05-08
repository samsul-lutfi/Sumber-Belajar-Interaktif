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
$sql = "SELECT m.*, u.username as uploaded_by 
        FROM materi m 
        LEFT JOIN users u ON m.user_id = u.id 
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($kategori)) {
    $sql .= " AND m.kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (m.judul LIKE ? OR m.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$sql .= " ORDER BY m.created_at DESC";

// Execute the query
$materials = get_records($sql, $params, $types);

// Get all available categories for the filter
$categories = get_categories();

// Log the page view
log_activity($_SESSION['user_id'], 'view', 'materi_list');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Materi - Sumber Belajar Interaktif</title>
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
            <i class="fas fa-book me-2"></i>Daftar Materi
        </h1>
        <?php if (is_admin()): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Tambah Materi
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="index.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Cari Materi</label>
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
    
    <!-- Materials Cards -->
    <div class="row">
        <?php if (!empty($materials)): ?>
            <?php foreach ($materials as $materi): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 materi-item" data-category="<?= htmlspecialchars($materi['kategori']) ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($materi['judul']) ?></h5>
                            <span class="badge bg-primary mb-2"><?= htmlspecialchars($materi['kategori']) ?></span>
                            
                            <p class="card-text"><?= nl2br(htmlspecialchars(substr($materi['deskripsi'], 0, 150))) ?>...</p>
                            
                            <div class="text-muted small mb-3">
                                <i class="fas fa-user me-1"></i> <?= htmlspecialchars($materi['uploaded_by']) ?>
                                <span class="ms-3"><i class="fas fa-calendar me-1"></i> <?= format_date($materi['created_at'], false) ?></span>
                            </div>
                            
                            <a href="view.php?id=<?= $materi['id'] ?>" class="btn btn-outline-primary">
                                <i class="fas fa-book-open me-1"></i> Baca Materi
                            </a>
                            
                            <?php if (is_admin()): ?>
                                <a href="edit.php?id=<?= $materi['id'] ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?= $materi['id'] ?>" class="btn btn-outline-danger" 
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus materi ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php if (!empty($kategori) || !empty($search)): ?>
                        Tidak ada materi yang sesuai dengan filter yang dipilih.
                    <?php else: ?>
                        Belum ada materi yang tersedia.
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($kategori) || !empty($search)): ?>
                    <div class="text-center mb-4">
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>Lihat Semua Materi
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
            <button type="button" class="btn btn-outline-primary filter-btn active" data-category="all" onclick="filterMaterials('all')">
                Semua
            </button>
            <?php foreach ($categories as $key => $value): ?>
                <button type="button" class="btn btn-outline-primary filter-btn" data-category="<?= $key ?>" onclick="filterMaterials('<?= $key ?>')">
                    <?= $value ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Category Info Cards -->
    <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <div class="col">
            <div class="card h-100 border-primary">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-book text-primary me-2"></i>Buku</h5>
                    <p class="card-text">Kumpulan materi pembelajaran berbasis buku teks, e-book, dan referensi tertulis lainnya.</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 border-success">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-video text-success me-2"></i>Video</h5>
                    <p class="card-text">Materi pembelajaran berbasis video yang interaktif dan mudah dipahami.</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 border-info">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-file-alt text-info me-2"></i>Jurnal</h5>
                    <p class="card-text">Artikel ilmiah dan jurnal pendidikan untuk memperdalam pengetahuan.</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 border-warning">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-globe text-warning me-2"></i>Internet</h5>
                    <p class="card-text">Kumpulan sumber belajar online dari berbagai platform digital terpercaya.</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 border-danger">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-tree text-danger me-2"></i>Alam</h5>
                    <p class="card-text">Materi pembelajaran berbasis alam dan lingkungan sekitar.</p>
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
function filterMaterials(category) {
    const materials = document.querySelectorAll('.materi-item');
    
    materials.forEach(item => {
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
