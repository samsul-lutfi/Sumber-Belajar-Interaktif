<?php
/**
 * Quiz Delete
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin or teacher
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    set_flash_message('Anda tidak memiliki izin untuk menghapus kuis.', 'danger');
    redirect('index.php');
}

// Check if ID parameter exists
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('ID kuis tidak valid.', 'danger');
    redirect('index.php');
}

$quiz_id = (int) $_GET['id'];

// Get quiz details
$quiz = get_record("SELECT q.*, u.username as created_by FROM quiz q 
                   LEFT JOIN users u ON q.user_id = u.id 
                   WHERE q.id = ?", [$quiz_id], "i");

if (!$quiz) {
    set_flash_message('Kuis tidak ditemukan.', 'danger');
    redirect('index.php');
}

// If not admin, check if user is the creator of the quiz
if ($_SESSION['role'] !== 'admin' && $quiz['user_id'] != $_SESSION['user_id']) {
    set_flash_message('Anda hanya dapat menghapus kuis yang Anda buat.', 'danger');
    redirect('index.php');
}

// If confirmation not yet submitted, show confirmation page
if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== '1') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi Penghapusan - Sumber Belajar Interaktif</title>
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Penghapusan</h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">Apakah Anda yakin ingin menghapus kuis:</p>
                        <h4 class="mb-3"><?= htmlspecialchars($quiz['judul']) ?></h4>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Tindakan ini tidak dapat dibatalkan. Semua data yang terkait dengan kuis ini juga akan dihapus, termasuk:</p>
                            <ul>
                                <li>Pertanyaan kuis</li>
                                <li>Hasil kuis</li>
                                <li>Statistik kuis</li>
                            </ul>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Batal
                            </a>
                            
                            <form action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $quiz_id ?>" method="post">
                                <input type="hidden" name="confirm_delete" value="1">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash-alt me-2"></i>Ya, Hapus Kuis
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Process the deletion
try {
    // Get all questions related to this quiz
    $questions = get_records(
        "SELECT * FROM quiz_questions WHERE quiz_id = ?",
        [$quiz_id],
        "i"
    );
    
    // Output debug info
    error_log("Deleting quiz ID: $quiz_id with " . count($questions) . " questions");
    
    // Delete quiz results directly without transaction
    $delete_results = "DELETE FROM hasil_kuis WHERE quiz_id = ?";
    $stmt = $conn->prepare($delete_results);
    $stmt->bindValue(1, $quiz_id, SQLITE3_INTEGER);
    $stmt->execute();
    error_log("Deleted quiz results");
    
    // Delete quiz questions directly
    $delete_questions = "DELETE FROM quiz_questions WHERE quiz_id = ?";
    $stmt = $conn->prepare($delete_questions);
    $stmt->bindValue(1, $quiz_id, SQLITE3_INTEGER);
    $stmt->execute();
    error_log("Deleted quiz questions");
    
    // Delete the quiz itself directly
    $delete_quiz = "DELETE FROM quiz WHERE id = ?";
    $stmt = $conn->prepare($delete_quiz);
    $stmt->bindValue(1, $quiz_id, SQLITE3_INTEGER);
    $stmt->execute();
    error_log("Deleted quiz itself");
    
    // Log the deletion activity
    log_activity($_SESSION['user_id'], 'delete', 'quiz', $quiz_id);
    
    // Set success message and redirect
    set_flash_message('Kuis berhasil dihapus.', 'success');
    error_log("Quiz deletion complete, redirecting");
    redirect('index.php');
    
} catch (Exception $e) {
    // Set error message and redirect
    error_log("Error in quiz deletion: " . $e->getMessage());
    set_flash_message('Terjadi kesalahan saat menghapus kuis: ' . $e->getMessage(), 'danger');
    redirect('index.php');
}
?>