<?php
/**
 * File untuk edit topik pada forum
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Cek login
if (!is_logged_in()) {
    set_flash_message('Anda harus login untuk mengedit topik.', 'warning');
    redirect('index.php');
}

// Ambil ID topik
$topic_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$topic_id) {
    set_flash_message('ID topik tidak valid.', 'error');
    redirect('index.php');
}

// Ambil data topik
$sql = "SELECT * FROM forum_topics WHERE id = ?";
$topic = get_record($sql, [$topic_id], "i");

// Jika topik tidak ditemukan
if (!$topic) {
    set_flash_message('Topik tidak ditemukan.', 'error');
    redirect('index.php');
}

// Cek apakah pengguna adalah pemilik topik atau admin
if ($_SESSION['user_id'] != $topic['user_id'] && !is_admin()) {
    set_flash_message('Anda tidak memiliki izin untuk mengedit topik ini.', 'warning');
    redirect('view.php?id=' . $topic_id);
}

// Ambil kategori yang tersedia
$categories = get_categories();

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $kategori = isset($_POST['kategori']) ? trim($_POST['kategori']) : '';
    
    // Validasi data
    $errors = [];
    
    if (empty($judul)) {
        $errors[] = 'Judul topik tidak boleh kosong.';
    } else if (strlen($judul) < 5) {
        $errors[] = 'Judul topik terlalu pendek (minimal 5 karakter).';
    } else if (strlen($judul) > 100) {
        $errors[] = 'Judul topik terlalu panjang (maksimal 100 karakter).';
    }
    
    if (empty($content)) {
        $errors[] = 'Isi topik tidak boleh kosong.';
    } else if (strlen($content) < 10) {
        $errors[] = 'Isi topik terlalu pendek (minimal 10 karakter).';
    } else if (strlen($content) > 10000) {
        $errors[] = 'Isi topik terlalu panjang (maksimal 10000 karakter).';
    }
    
    if (empty($kategori) || !array_key_exists($kategori, $categories)) {
        $errors[] = 'Kategori tidak valid.';
    }
    
    // Jika tidak ada error, update topik
    if (empty($errors)) {
        try {
            // Update topik
            $sql = "UPDATE forum_topics SET judul = ?, content = ?, kategori = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            execute_query($sql, [$judul, $content, $kategori, $topic_id], "sssi");
            
            // Log aktivitas
            log_activity($_SESSION['user_id'], 'edit', 'forum_topic', $topic_id);
            
            set_flash_message('Topik berhasil diperbarui.', 'success');
            redirect('view.php?id=' . $topic_id);
        } catch (Exception $e) {
            set_flash_message('Gagal memperbarui topik: ' . $e->getMessage(), 'danger');
        }
    } else {
        // Tampilkan error
        foreach ($errors as $error) {
            set_flash_message($error, 'danger');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Topik - Forum Diskusi</title>
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
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Beranda</a></li>
            <li class="breadcrumb-item"><a href="index.php">Forum Diskusi</a></li>
            <li class="breadcrumb-item"><a href="index.php?kategori=<?= $topic['kategori'] ?>"><?= $categories[$topic['kategori']] ?></a></li>
            <li class="breadcrumb-item"><a href="view.php?id=<?= $topic_id ?>"><?= htmlspecialchars($topic['judul']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Topik</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Topik
                    </h5>
                </div>
                <div class="card-body">
                    <form action="edit.php?id=<?= $topic_id ?>" method="POST">
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Topik</label>
                            <input type="text" class="form-control" id="judul" name="judul" value="<?= htmlspecialchars($topic['judul']) ?>" required>
                            <div class="form-text">Minimal 5 karakter, maksimal 100 karakter.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label">Kategori</label>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <?php foreach ($categories as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= ($topic['kategori'] === $key) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($value) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Isi Topik</label>
                            <textarea class="form-control" id="content" name="content" rows="10" required><?= htmlspecialchars($topic['content']) ?></textarea>
                            <div class="form-text">Minimal 10 karakter, maksimal 10000 karakter.</div>
                        </div>
                        
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                            <a href="view.php?id=<?= $topic_id ?>" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                        </div>
                    </form>
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

</body>
</html>