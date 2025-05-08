<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
require_admin();

// Get all categories
$categories = get_categories();

$error_message = '';
$success_message = '';
$form_data = [
    'judul' => '',
    'kategori' => 'buku',
    'deskripsi' => '',
    'konten' => '',
    'thumbnail_url' => '',
    'video_url' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $form_data = [
        'judul' => sanitize($_POST['judul'] ?? ''),
        'kategori' => sanitize($_POST['kategori'] ?? ''),
        'deskripsi' => sanitize($_POST['deskripsi'] ?? ''),
        'konten' => sanitize($_POST['konten'] ?? ''),
        'thumbnail_url' => sanitize($_POST['thumbnail_url'] ?? ''),
        'video_url' => sanitize($_POST['video_url'] ?? '')
    ];
    
    // Basic validation
    if (empty($form_data['judul']) || empty($form_data['kategori']) || empty($form_data['deskripsi'])) {
        $error_message = 'Judul, kategori, dan deskripsi harus diisi.';
    } else {
        // Insert into database (using CURRENT_TIMESTAMP for SQLite)
        $sql = "INSERT INTO materi (judul, kategori, deskripsi, konten, thumbnail_url, video_url, user_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        
        $stmt = execute_query(
            $sql, 
            [
                $form_data['judul'], 
                $form_data['kategori'], 
                $form_data['deskripsi'], 
                $form_data['konten'], 
                $form_data['thumbnail_url'], 
                $form_data['video_url'], 
                $_SESSION['user_id']
            ], 
            "ssssssi"
        );
        
        if ($stmt === false) {
            $error_message = 'Terjadi kesalahan saat menyimpan materi. Silakan coba lagi.';
        } else {
            $materi_id = $conn->lastInsertRowID() ?? get_record("SELECT last_insert_rowid() as id")['id'];
            
            // Handle file uploads
            if (!empty($_FILES['files']['name'][0])) {
                $upload_dir = '../uploads/materi/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Process each uploaded file
                foreach ($_FILES['files']['name'] as $key => $filename) {
                    if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                        $temp = $_FILES['files']['tmp_name'][$key];
                        $file_size = $_FILES['files']['size'][$key];
                        $file_extension = get_file_extension($filename);
                        
                        // Check if extension is allowed
                        if (is_allowed_extension($file_extension)) {
                            // Generate a unique filename to prevent overwrites
                            $new_filename = uniqid() . '_' . $filename;
                            $file_path = $upload_dir . $new_filename;
                            
                            // Move file to uploads directory
                            if (move_uploaded_file($temp, $file_path)) {
                                // Save file info to database (using CURRENT_TIMESTAMP for SQLite)
                                $file_sql = "INSERT INTO materi_files (materi_id, filename, filepath, filesize, uploaded_at) 
                                             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
                                execute_query($file_sql, [$materi_id, $filename, $new_filename, $file_size], "issi");
                            }
                        }
                    }
                }
            }
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'add', 'materi', $materi_id);
            
            // Set success message and redirect
            set_flash_message('Materi berhasil ditambahkan.', 'success');
            redirect("view.php?id=$materi_id");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Materi - Sumber Belajar Interaktif</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Rich Text Editor - CKEditor CDN -->
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
            <li class="breadcrumb-item"><a href="index.php">Materi</a></li>
            <li class="breadcrumb-item active">Tambah Materi</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Tambah Materi Baru</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="judul" name="judul" value="<?= htmlspecialchars($form_data['judul']) ?>" required>
                            <div class="invalid-feedback">
                                Judul materi harus diisi.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="kategori" name="kategori" required>
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
                        
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi Singkat <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required><?= htmlspecialchars($form_data['deskripsi']) ?></textarea>
                            <div class="invalid-feedback">
                                Deskripsi singkat harus diisi.
                            </div>
                            <div class="form-text">
                                Berikan penjelasan singkat tentang materi ini (maksimal 300 karakter).
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="konten" class="form-label">Konten Materi</label>
                            <textarea class="form-control" id="konten" name="konten" rows="10"><?= htmlspecialchars($form_data['konten']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="thumbnail_url" class="form-label">URL Gambar Thumbnail</label>
                            <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" value="<?= htmlspecialchars($form_data['thumbnail_url']) ?>">
                            <div class="form-text">
                                Masukkan URL gambar untuk thumbnail materi (opsional).
                            </div>
                        </div>
                        
                        <div id="videoUrlContainer" class="mb-3 <?= $form_data['kategori'] !== 'video' ? 'd-none' : '' ?>">
                            <label for="video_url" class="form-label">URL Video</label>
                            <input type="url" class="form-control" id="video_url" name="video_url" value="<?= htmlspecialchars($form_data['video_url']) ?>">
                            <div class="form-text">
                                Masukkan URL video (YouTube, Vimeo, dll) jika materi berupa video.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="files" class="form-label">Unggah File</label>
                            <input type="file" class="form-control" id="files" name="files[]" multiple>
                            <div class="form-text">
                                Unggah file pendukung materi (PDF, DOC, PPT, dll). Maksimal 5 file.
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Materi
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
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    
    // Toggle video URL field based on category
    const kategoriSelect = document.getElementById('kategori');
    const videoUrlContainer = document.getElementById('videoUrlContainer');
    
    kategoriSelect.addEventListener('change', function() {
        if (this.value === 'video') {
            videoUrlContainer.classList.remove('d-none');
        } else {
            videoUrlContainer.classList.add('d-none');
        }
    });
    
    // File upload validation
    const fileInput = document.getElementById('files');
    fileInput.addEventListener('change', function() {
        if (this.files.length > 5) {
            alert('Maksimal 5 file yang dapat diunggah.');
            this.value = '';
        }
        
        // Check file size (max 5MB each)
        for (let i = 0; i < this.files.length; i++) {
            if (this.files[i].size > 5 * 1024 * 1024) {
                alert('Ukuran file tidak boleh lebih dari 5MB.');
                this.value = '';
                break;
            }
        }
    });
});
</script>

</body>
</html>
