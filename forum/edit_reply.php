<?php
/**
 * File untuk edit balasan pada forum
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Cek login
if (!is_logged_in()) {
    set_flash_message('Anda harus login untuk mengedit balasan.', 'warning');
    redirect('index.php');
}

// Ambil ID balasan
$reply_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reply_id) {
    set_flash_message('ID balasan tidak valid.', 'error');
    redirect('index.php');
}

// Ambil data balasan
$sql = "SELECT r.*, f.judul as topic_title, f.id as topic_id 
         FROM forum_replies r 
         JOIN forum_topics f ON r.forum_id = f.id 
         WHERE r.id = ?";
$reply = get_record($sql, [$reply_id], "i");

// Jika balasan tidak ditemukan
if (!$reply) {
    set_flash_message('Balasan tidak ditemukan.', 'error');
    redirect('index.php');
}

// Cek apakah pengguna adalah pemilik balasan atau admin
if ($_SESSION['user_id'] != $reply['user_id'] && !is_admin()) {
    set_flash_message('Anda tidak memiliki izin untuk mengedit balasan ini.', 'warning');
    redirect('view.php?id=' . $reply['forum_id']);
}

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    
    // Validasi isi balasan
    if (empty($content)) {
        set_flash_message('Isi balasan tidak boleh kosong.', 'danger');
    } else if (strlen($content) < 5) {
        set_flash_message('Isi balasan terlalu pendek (minimal 5 karakter).', 'danger');
    } else if (strlen($content) > 5000) {
        set_flash_message('Isi balasan terlalu panjang (maksimal 5000 karakter).', 'danger');
    } else {
        try {
            // Update balasan
            $sql = "UPDATE forum_replies SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            execute_query($sql, [$content, $reply_id], "si");
            
            // Log aktivitas
            log_activity($_SESSION['user_id'], 'edit', 'forum_reply', $reply_id);
            
            set_flash_message('Balasan berhasil diperbarui.', 'success');
            redirect('view.php?id=' . $reply['forum_id'] . '#reply-' . $reply_id);
        } catch (Exception $e) {
            set_flash_message('Gagal memperbarui balasan: ' . $e->getMessage(), 'danger');
        }
    }
}

// Kategori untuk link breadcrumb
$categories = get_categories();
$topic_kategori = get_record("SELECT kategori FROM forum_topics WHERE id = ?", [$reply['forum_id']], "i")['kategori'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Balasan - Forum Diskusi</title>
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
            <li class="breadcrumb-item"><a href="index.php?kategori=<?= $topic_kategori ?>"><?= $categories[$topic_kategori] ?></a></li>
            <li class="breadcrumb-item"><a href="view.php?id=<?= $reply['forum_id'] ?>"><?= htmlspecialchars($reply['topic_title']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Balasan</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Balasan
                    </h5>
                </div>
                <div class="card-body">
                    <form action="edit_reply.php?id=<?= $reply_id ?>" method="POST">
                        <div class="mb-3">
                            <label for="content" class="form-label">Isi Balasan</label>
                            <textarea class="form-control" id="content" name="content" rows="8" required><?= htmlspecialchars($reply['content']) ?></textarea>
                            <div class="form-text">Minimal 5 karakter, maksimal 5000 karakter.</div>
                        </div>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                            <a href="view.php?id=<?= $reply['forum_id'] ?>#reply-<?= $reply_id ?>" class="btn btn-secondary ms-2">
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