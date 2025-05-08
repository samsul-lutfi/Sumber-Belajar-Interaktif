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

// Get material details to check if it exists
$materi = get_record(
    "SELECT id, judul FROM materi WHERE id = ?",
    [$materi_id],
    "i"
);

// If material not found
if (!$materi) {
    set_flash_message('Materi tidak ditemukan.', 'danger');
    redirect('index.php');
}

// Confirm deletion (if not already confirmed)
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    // Show confirmation page
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hapus Materi - Sumber Belajar Interaktif</title>
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
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Penghapusan</h4>
                    </div>
                    <div class="card-body text-center">
                        <p class="mb-3">Apakah Anda yakin ingin menghapus materi:</p>
                        <h5 class="mb-4"><?= htmlspecialchars($materi['judul']) ?></h5>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Tindakan ini tidak dapat dibatalkan. Semua file yang terkait dengan materi ini juga akan dihapus.
                        </div>
                        
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <a href="delete.php?id=<?= $materi_id ?>&confirm=yes" class="btn btn-danger">
                                <i class="fas fa-trash me-2"></i>Ya, Hapus Materi
                            </a>
                        </div>
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

    </body>
    </html>
    <?php
    exit;
}

// Process the deletion
try {
    // Get all files related to this material
    $files = get_records(
        "SELECT * FROM materi_files WHERE materi_id = ?",
        [$materi_id],
        "i"
    );
    
    // SQLite doesn't support transactions in the same way as MySQL
    // Start SQLite transaction using exec
    $conn->exec('BEGIN TRANSACTION');
    
    // Delete files from storage
    foreach ($files as $file) {
        $file_path = '../uploads/materi/' . $file['filepath'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete files from database
    execute_query(
        "DELETE FROM materi_files WHERE materi_id = ?",
        [$materi_id],
        "i"
    );
    
    // Delete related quiz results (if any)
    execute_query(
        "DELETE FROM hasil_kuis WHERE quiz_id IN (SELECT id FROM quiz WHERE materi_id = ?)",
        [$materi_id],
        "i"
    );
    
    // Delete related questions (if any)
    execute_query(
        "DELETE FROM quiz_questions WHERE quiz_id IN (SELECT id FROM quiz WHERE materi_id = ?)",
        [$materi_id],
        "i"
    );
    
    // Delete related quizzes (if any)
    execute_query(
        "DELETE FROM quiz WHERE materi_id = ?",
        [$materi_id],
        "i"
    );
    
    // Delete related forum replies (if any)
    execute_query(
        "DELETE FROM forum_replies WHERE topic_id IN (SELECT id FROM forum_topics WHERE materi_id = ?)",
        [$materi_id],
        "i"
    );
    
    // Delete related forum topics (if any)
    execute_query(
        "DELETE FROM forum_topics WHERE materi_id = ?",
        [$materi_id],
        "i"
    );
    
    // Delete the material itself
    execute_query(
        "DELETE FROM materi WHERE id = ?",
        [$materi_id],
        "i"
    );
    
    // Commit the SQLite transaction
    $conn->exec('COMMIT');
    
    // Log the deletion activity
    log_activity($_SESSION['user_id'], 'delete', 'materi', $materi_id);
    
    // Set success message and redirect
    set_flash_message('Materi berhasil dihapus.', 'success');
    redirect('index.php');
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->exec('ROLLBACK');
    
    // Set error message and redirect
    set_flash_message('Terjadi kesalahan saat menghapus materi: ' . $e->getMessage(), 'danger');
    redirect('index.php');
}
?>
