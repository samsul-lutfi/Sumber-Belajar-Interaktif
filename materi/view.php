<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
require_login();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID materi tidak valid.', 'danger');
    redirect('index.php');
}

$materi_id = (int)$_GET['id'];

// Get material details
$materi = get_record(
    "SELECT m.*, u.username as uploaded_by, u.full_name as uploaded_by_name 
     FROM materi m 
     LEFT JOIN users u ON m.user_id = u.id 
     WHERE m.id = ?",
    [$materi_id],
    "i"
);

// If material not found
if (!$materi) {
    set_flash_message('Materi tidak ditemukan.', 'danger');
    redirect('index.php');
}

// Get file attachments if any
$files = get_records(
    "SELECT * FROM materi_files WHERE materi_id = ? ORDER BY id ASC",
    [$materi_id],
    "i"
);

// Get related materials in the same category
$related_materials = get_records(
    "SELECT id, judul, kategori 
     FROM materi 
     WHERE kategori = ? AND id != ? 
     ORDER BY created_at DESC LIMIT 5",
    [$materi['kategori'], $materi_id],
    "si"
);

// Log the material view
log_activity($_SESSION['user_id'], 'view', 'materi', $materi_id);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($materi['judul']) ?> - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .materi-content {
            line-height: 1.8;
        }
        
        .materi-content img {
            max-width: 100%;
            height: auto;
            margin: 1rem 0;
        }
        
        .materi-content h2, .materi-content h3, .materi-content h4 {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .materi-content p {
            margin-bottom: 1rem;
        }
        
        .materi-content ul, .materi-content ol {
            margin-bottom: 1rem;
            padding-left: 2rem;
        }
        
        /* Style for embedded videos */
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            padding-top: 25px;
            height: 0;
            margin-bottom: 1.5rem;
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Beranda</a></li>
            <li class="breadcrumb-item"><a href="index.php">Materi</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($materi['judul']) ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h1 class="card-title mb-3"><?= htmlspecialchars($materi['judul']) ?></h1>
                    
                    <div class="d-flex mb-3">
                        <span class="badge bg-primary me-2"><?= htmlspecialchars($materi['kategori']) ?></span>
                        <span class="text-muted">
                            <i class="fas fa-user me-1"></i> <?= htmlspecialchars($materi['uploaded_by_name']) ?>
                        </span>
                        <span class="mx-2">•</span>
                        <span class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i> <?= format_date($materi['created_at']) ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($materi['thumbnail_url'])): ?>
                        <div class="text-center mb-4">
                            <img src="<?= htmlspecialchars($materi['thumbnail_url']) ?>" 
                                 alt="<?= htmlspecialchars($materi['judul']) ?>" 
                                 class="img-fluid rounded">
                        </div>
                    <?php endif; ?>
                    
                    <div class="materi-content">
                        <?php if ($materi['kategori'] === 'video' && !empty($materi['video_url'])): ?>
                            <div class="video-container mb-4">
                                <iframe src="<?= htmlspecialchars($materi['video_url']) ?>" 
                                        frameborder="0" allowfullscreen></iframe>
                            </div>
                        <?php endif; ?>
                        
                        <?= nl2br(htmlspecialchars($materi['deskripsi'])) ?>
                        
                        <div class="mt-3">
                            <?= nl2br(htmlspecialchars($materi['konten'])) ?>
                        </div>
                    </div>
                    
                    <!-- File Attachments Section -->
                    <?php if (!empty($files)): ?>
                        <div class="mt-4">
                            <h4><i class="fas fa-paperclip me-2"></i>File Lampiran</h4>
                            <div class="list-group">
                                <?php foreach ($files as $file): ?>
                                    <a href="download.php?id=<?= $file['id'] ?>&token=<?= generate_download_token($file['id'], $file['filename']) ?>" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-file me-2"></i>
                                            <?= htmlspecialchars($file['filename']) ?>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= format_file_size($file['filesize']) ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
                            </a>
                        </div>
                        <div>
                            <?php if (is_admin()): ?>
                                <a href="edit.php?id=<?= $materi_id ?>" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-edit me-2"></i>Edit
                                </a>
                                <a href="delete.php?id=<?= $materi_id ?>" class="btn btn-outline-danger"
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus materi ini?')">
                                    <i class="fas fa-trash me-2"></i>Hapus
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Discussion Forum Link -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5><i class="fas fa-comments me-2"></i>Diskusi Materi</h5>
                    <p>Ada pertanyaan atau tanggapan tentang materi ini? Diskusikan dengan siswa lain dan guru.</p>
                    <a href="../forum/post.php?type=materi&id=<?= $materi_id ?>" class="btn btn-primary">
                        <i class="fas fa-comment-dots me-2"></i>Mulai Diskusi
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Materials Sidebar -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-book me-2"></i>Materi Terkait
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($related_materials)): ?>
                        <div class="list-group">
                            <?php foreach ($related_materials as $related): ?>
                                <a href="view.php?id=<?= $related['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($related['judul']) ?></h6>
                                        <span class="badge bg-primary"><?= htmlspecialchars($related['kategori']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Tidak ada materi terkait.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Related Quizzes -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>Kuis Terkait
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get related quizzes for this material category
                    $related_quizzes = get_records(
                        "SELECT id, judul, durasi FROM quiz WHERE kategori = ? LIMIT 3",
                        [$materi['kategori']],
                        "s"
                    );
                    ?>
                    
                    <?php if (!empty($related_quizzes)): ?>
                        <div class="list-group">
                            <?php foreach ($related_quizzes as $quiz): ?>
                                <a href="../quiz/take.php?id=<?= $quiz['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($quiz['judul']) ?></h6>
                                        <span class="badge bg-warning rounded-pill">
                                            <i class="fas fa-clock me-1"></i><?= $quiz['durasi'] ?> menit
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Belum ada kuis terkait.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Material Category Info -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Tentang Kategori
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $category_info = [
                        'buku' => [
                            'icon' => 'book',
                            'desc' => 'Materi berbasis buku teks dan referensi tertulis untuk pembelajaran mendalam.'
                        ],
                        'video' => [
                            'icon' => 'video',
                            'desc' => 'Materi dalam format video interaktif untuk visualisasi konsep.'
                        ],
                        'jurnal' => [
                            'icon' => 'file-alt',
                            'desc' => 'Artikel ilmiah dan jurnal pendidikan untuk studi lanjutan.'
                        ],
                        'internet' => [
                            'icon' => 'globe',
                            'desc' => 'Sumber belajar digital dari berbagai platform online terpercaya.'
                        ],
                        'alam' => [
                            'icon' => 'tree',
                            'desc' => 'Pembelajaran berbasis alam dan lingkungan sekitar untuk pengalaman langsung.'
                        ]
                    ];
                    
                    $current_category = $materi['kategori'];
                    $category_data = isset($category_info[$current_category]) ? 
                                     $category_info[$current_category] : 
                                     ['icon' => 'question', 'desc' => 'Informasi kategori tidak tersedia.'];
                    ?>
                    
                    <h5><i class="fas fa-<?= $category_data['icon'] ?> me-2"></i><?= ucfirst(htmlspecialchars($current_category)) ?></h5>
                    <p><?= $category_data['desc'] ?></p>
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

</body>
</html>
