<?php
/**
 * Forum Post
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    set_flash_message('Anda harus login untuk membuat topik forum.', 'warning');
    redirect('/auth/login.php');
}

// Initialize variables
$error_message = '';
$form_data = [
    'judul' => '',
    'konten' => '',
    'kategori' => '',
];

$categories = [
    'umum' => 'Umum',
    'pertanyaan' => 'Pertanyaan',
    'diskusi' => 'Diskusi',
    'pengumuman' => 'Pengumuman',
    'tutorial' => 'Tutorial'
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $form_data = [
        'judul' => sanitize($_POST['judul'] ?? ''),
        'konten' => sanitize($_POST['konten'] ?? '', false), // Don't strip tags for content
        'kategori' => sanitize($_POST['kategori'] ?? ''),
    ];
    
    // Validate form data
    if (empty($form_data['judul'])) {
        $error_message = 'Judul tidak boleh kosong.';
    } else if (strlen($form_data['judul']) < 5) {
        $error_message = 'Judul minimal 5 karakter.';
    } else if (empty($form_data['konten'])) {
        $error_message = 'Konten tidak boleh kosong.';
    } else if (strlen($form_data['konten']) < 10) {
        $error_message = 'Konten minimal 10 karakter.';
    } else if (empty($form_data['kategori']) || !array_key_exists($form_data['kategori'], $categories)) {
        $error_message = 'Kategori tidak valid.';
    }
    
    // If no error, insert forum topic
    if (empty($error_message)) {
        $sql = "INSERT INTO forum_topics (user_id, judul, konten, kategori, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $stmt = execute_query(
            $sql, 
            [
                $_SESSION['user_id'], 
                $form_data['judul'], 
                $form_data['konten'], 
                $form_data['kategori']
            ], 
            "isss"
        );
        
        if ($stmt === false) {
            $error_message = 'Terjadi kesalahan saat menyimpan topik forum. Silakan coba lagi.';
        } else {
            $topic_id = $conn->lastInsertRowID() ?? get_record("SELECT last_insert_rowid() as id")['id'];
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'add', 'forum_topic', $topic_id);
            
            // Set success message and redirect
            set_flash_message('Topik forum berhasil dibuat.', 'success');
            redirect("view.php?id=$topic_id");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Topik Forum - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- CKEditor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/35.0.1/classic/ckeditor.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Beranda</a></li>
            <li class="breadcrumb-item"><a href="index.php">Forum</a></li>
            <li class="breadcrumb-item active">Buat Topik</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Buat Topik Forum Baru</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Topik <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="judul" name="judul" value="<?= htmlspecialchars($form_data['judul']) ?>" required>
                            <div class="invalid-feedback">
                                Judul topik harus diisi (minimal 5 karakter).
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $form_data['kategori'] === $key ? 'selected' : '' ?>>
                                        <?= $value ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Kategori harus dipilih.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="konten" class="form-label">Isi Topik <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="konten" name="konten" rows="10" required><?= htmlspecialchars($form_data['konten']) ?></textarea>
                            <div class="invalid-feedback">
                                Isi topik harus diisi (minimal 10 karakter).
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Kirim Topik
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize CKEditor
    ClassicEditor
        .create(document.querySelector('#konten'))
        .catch(error => {
            console.error(error);
        });
});
</script>

</body>
</html>