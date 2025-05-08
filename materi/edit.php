<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
require_admin();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID materi tidak valid.', 'danger');
    redirect('index.php');
}

$materi_id = (int)$_GET['id'];

// Get material details
$materi = get_record(
    "SELECT * FROM materi WHERE id = ?",
    [$materi_id],
    "i"
);

// If material not found
if (!$materi) {
    set_flash_message('Materi tidak ditemukan.', 'danger');
    redirect('index.php');
}

// Get all categories
$categories = get_categories();

// Get existing files
$existing_files = get_records(
    "SELECT * FROM materi_files WHERE materi_id = ? ORDER BY id ASC",
    [$materi_id],
    "i"
);

$error_message = '';
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $judul = sanitize($_POST['judul'] ?? '');
    $kategori = sanitize($_POST['kategori'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $konten = sanitize($_POST['konten'] ?? '');
    $thumbnail_url = sanitize($_POST['thumbnail_url'] ?? '');
    $video_url = sanitize($_POST['video_url'] ?? '');
    
    // Basic validation
    if (empty($judul) || empty($kategori) || empty($deskripsi)) {
        $error_message = 'Judul, kategori, dan deskripsi harus diisi.';
    } else {
        // Update in database
        $sql = "UPDATE materi SET judul = ?, kategori = ?, deskripsi = ?, konten = ?, 
                thumbnail_url = ?, video_url = ?, updated_at = NOW() WHERE id = ?";
        
        $stmt = execute_query(
            $sql, 
            [$judul, $kategori, $deskripsi, $konten, $thumbnail_url, $video_url, $materi_id], 
            "ssssssi"
        );
        
        if ($stmt === false) {
            $error_message = 'Terjadi kesalahan saat memperbarui materi. Silakan coba lagi.';
        } else {
            // Handle file deletion if any
            if (!empty($_POST['delete_files'])) {
                foreach ($_POST['delete_files'] as $file_id) {
                    // Get file info
                    $file_info = get_record(
                        "SELECT * FROM materi_files WHERE id = ? AND materi_id = ?",
                        [(int)$file_id, $materi_id],
                        "ii"
                    );
                    
                    if ($file_info) {
                        // Delete file from storage
                        $file_path = '../uploads/materi/' . $file_info['filepath'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                        
                        // Delete from database
                        execute_query(
                            "DELETE FROM materi_files WHERE id = ?",
                            [(int)$file_id],
                            "i"
                        );
                    }
                }
            }
            
            // Handle new file uploads
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
                                // Save file info to database
                                $file_sql = "INSERT INTO materi_files (materi_id, filename, filepath, filesize, uploaded_at) 
                                             VALUES (?, ?, ?, ?, NOW())";
                                execute_query($file_sql, [$materi_id, $filename, $new_filename, $file_size], "issi");
                            }
                        }
                    }
                }
            }
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'edit', 'materi', $materi_id);
            
            // Set success message and redirect
            set_flash_message('Materi berhasil diperbarui.', 'success');
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
    <title>Edit Materi - Sumber Belajar Interaktif</title>
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
            <li class="breadcrumb-item"><a href="view.php?id=<?= $materi_id ?>"><?= htmlspecialchars($materi['judul']) ?></a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Materi</h4>
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
                    
                    <form action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $materi_id ?>" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="judul" name="judul" value="<?= htmlspecialchars($materi['judul']) ?>" required>
                            <div class="invalid-feedback">
                                Judul materi harus diisi.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <?php foreach ($categories as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $materi['kategori'] === $key ? 'selected' : '' ?>>
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
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                            <div class="invalid-feedback">
                                Deskripsi singkat harus diisi.
                            </div>
                            <div class="form-text">
                                Berikan penjelasan singkat tentang materi ini (maksimal 300 karakter).
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="konten" class="form-label">Konten Materi</label>
                            <textarea class="form-control" id="konten" name="konten" rows="10"><?= htmlspecialchars($materi['konten']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="thumbnail_url" class="form-label">URL Gambar Thumbnail</label>
                            <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" value="<?= htmlspecialchars($materi['thumbnail_url']) ?>">
                            <div class="form-text">
                                Masukkan URL gambar untuk thumbnail materi (opsional).
                            </div>
                        </div>
                        
                        <div id="videoUrlContainer" class="mb-3 <?= $materi['kategori'] !== 'video' ? 'd-none' : '' ?>">
                            <label for="video_url" class="form-label">URL Video</label>
                            <input type="url" class="form-control" id="video_url" name="video_url" value="<?= htmlspecialchars($materi['video_url']) ?>">
                            <div class="form-text">
                                Masukkan URL video (YouTube, Vimeo, dll) jika materi berupa video.
                            </div>
                        </div>
                        
                        <!-- Existing Files Section -->
                        <?php if (!empty($existing_files)): ?>
                            <div class="mb-3">
                                <label class="form-label">File Terlampir</label>
                                <div class="list-group">
                                    <?php foreach ($existing_files as $file): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-file me-2"></i>
                                                <?= htmlspecialchars($file['filename']) ?>
                                                <span class="ms-2 text-muted">(<?= format_file_size($file['filesize']) ?>)</span>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="delete_file_<?= $file['id'] ?>" name="delete_files[]" value="<?= $file['id'] ?>">
                                                <label class="form-check-label text-danger" for="delete_file_<?= $file['id'] ?>">Hapus</label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">
                                    Centang kotak di samping file untuk menghapusnya saat menyimpan perubahan.
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label for="files" class="form-label">Unggah File Baru</label>
                            <input type="file" class="form-control" id="files" name="files[]" multiple>
                            <div class="form-text">
                                Unggah file pendukung tambahan (PDF, DOC, PPT, dll). Maksimal 5 file.
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?= $materi_id ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
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
