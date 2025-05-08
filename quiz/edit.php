<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
require_admin();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID kuis tidak valid.', 'danger');
    redirect('index.php');
}

$quiz_id = (int)$_GET['id'];

// Get quiz details
$quiz = get_record(
    "SELECT * FROM quiz WHERE id = ?",
    [$quiz_id],
    "i"
);

// If quiz not found
if (!$quiz) {
    set_flash_message('Kuis tidak ditemukan.', 'danger');
    redirect('index.php');
}

// Get all categories
$categories = get_categories();

// Get all materials for association
$materials = get_records(
    "SELECT id, judul, kategori FROM materi ORDER BY judul ASC"
);

// Get existing questions
$questions = get_records(
    "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC",
    [$quiz_id],
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
    $durasi = (int)($_POST['durasi'] ?? 30);
    $materi_id = !empty($_POST['materi_id']) ? (int)$_POST['materi_id'] : null;
    
    // Basic validation
    if (empty($judul) || empty($kategori) || empty($deskripsi)) {
        $error_message = 'Judul, kategori, dan deskripsi harus diisi.';
    } elseif ($durasi < 5 || $durasi > 120) {
        $error_message = 'Durasi kuis harus antara 5 - 120 menit.';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update quiz in database
            $sql = "UPDATE quiz SET judul = ?, kategori = ?, deskripsi = ?, durasi = ?, 
                    materi_id = ?, updated_at = NOW() WHERE id = ?";
            
            $stmt = execute_query(
                $sql, 
                [$judul, $kategori, $deskripsi, $durasi, $materi_id, $quiz_id], 
                "sssiii"
            );
            
            if ($stmt === false) {
                throw new Exception('Terjadi kesalahan saat memperbarui kuis.');
            }
            
            // Handle question deletion if any
            if (!empty($_POST['delete_questions'])) {
                foreach ($_POST['delete_questions'] as $question_id) {
                    execute_query(
                        "DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?",
                        [(int)$question_id, $quiz_id],
                        "ii"
                    );
                }
            }
            
            // Process existing questions updates
            if (isset($_POST['existing_questions']) && is_array($_POST['existing_questions'])) {
                foreach ($_POST['existing_questions'] as $question_id => $question_data) {
                    $pertanyaan = sanitize($question_data['pertanyaan']);
                    $pilihan_a = sanitize($question_data['pilihan_a'] ?? '');
                    $pilihan_b = sanitize($question_data['pilihan_b'] ?? '');
                    $pilihan_c = sanitize($question_data['pilihan_c'] ?? '');
                    $pilihan_d = sanitize($question_data['pilihan_d'] ?? '');
                    $pilihan_e = sanitize($question_data['pilihan_e'] ?? '');
                    $jawaban_benar = sanitize($question_data['jawaban_benar'] ?? '');
                    
                    // Validate required fields
                    if (empty($pertanyaan) || empty($pilihan_a) || empty($pilihan_b) || empty($jawaban_benar)) {
                        throw new Exception('Pertanyaan, pilihan A, pilihan B, dan jawaban benar harus diisi.');
                    }
                    
                    // Update question
                    $update_sql = "UPDATE quiz_questions SET pertanyaan = ?, pilihan_a = ?, pilihan_b = ?, 
                                   pilihan_c = ?, pilihan_d = ?, pilihan_e = ?, jawaban_benar = ? 
                                   WHERE id = ? AND quiz_id = ?";
                    
                    execute_query(
                        $update_sql, 
                        [
                            $pertanyaan, 
                            $pilihan_a, 
                            $pilihan_b, 
                            $pilihan_c, 
                            $pilihan_d, 
                            $pilihan_e, 
                            $jawaban_benar, 
                            (int)$question_id, 
                            $quiz_id
                        ], 
                        "sssssssii"
                    );
                }
            }
            
            // Process new questions
            if (isset($_POST['new_questions']) && is_array($_POST['new_questions'])) {
                foreach ($_POST['new_questions'] as $question_data) {
                    // Skip empty questions
                    if (empty($question_data['pertanyaan'])) {
                        continue;
                    }
                    
                    $pertanyaan = sanitize($question_data['pertanyaan']);
                    $pilihan_a = sanitize($question_data['pilihan_a'] ?? '');
                    $pilihan_b = sanitize($question_data['pilihan_b'] ?? '');
                    $pilihan_c = sanitize($question_data['pilihan_c'] ?? '');
                    $pilihan_d = sanitize($question_data['pilihan_d'] ?? '');
                    $pilihan_e = sanitize($question_data['pilihan_e'] ?? '');
                    $jawaban_benar = sanitize($question_data['jawaban_benar'] ?? '');
                    
                    // Validate required fields
                    if (empty($pertanyaan) || empty($pilihan_a) || empty($pilihan_b) || empty($jawaban_benar)) {
                        throw new Exception('Pertanyaan, pilihan A, pilihan B, dan jawaban benar harus diisi.');
                    }
                    
                    // Insert question
                    $q_sql = "INSERT INTO quiz_questions (quiz_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, jawaban_benar, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    execute_query(
                        $q_sql, 
                        [
                            $quiz_id, 
                            $pertanyaan, 
                            $pilihan_a, 
                            $pilihan_b, 
                            $pilihan_c, 
                            $pilihan_d, 
                            $pilihan_e, 
                            $jawaban_benar
                        ], 
                        "isssssss"
                    );
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'edit', 'quiz', $quiz_id);
            
            // Set success message
            $success_message = 'Kuis berhasil diperbarui.';
            
            // Refresh quiz and questions data
            $quiz = get_record(
                "SELECT * FROM quiz WHERE id = ?",
                [$quiz_id],
                "i"
            );
            
            $questions = get_records(
                "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC",
                [$quiz_id],
                "i"
            );
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kuis - Sumber Belajar Interaktif</title>
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
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Beranda</a></li>
            <li class="breadcrumb-item"><a href="index.php">Kuis</a></li>
            <li class="breadcrumb-item active">Edit Kuis</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Kuis</h4>
                    <a href="results.php?id=<?= $quiz_id ?>" class="btn btn-light btn-sm">
                        <i class="fas fa-poll me-1"></i> Lihat Hasil
                    </a>
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
                    
                    <form action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $quiz_id ?>" method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Kuis <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="judul" name="judul" value="<?= htmlspecialchars($quiz['judul']) ?>" required>
                            <div class="invalid-feedback">
                                Judul kuis harus diisi.
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                                <select class="form-select" id="kategori" name="kategori" required>
                                    <?php foreach ($categories as $key => $value): ?>
                                        <option value="<?= $key ?>" <?= $quiz['kategori'] === $key ? 'selected' : '' ?>>
                                            <?= $value ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Kategori harus dipilih.
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="durasi" class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="durasi" name="durasi" value="<?= $quiz['durasi'] ?>" min="5" max="120" required>
                                <div class="invalid-feedback">
                                    Durasi harus antara 5 - 120 menit.
                                </div>
                                <div class="form-text">
                                    Waktu yang diberikan untuk menyelesaikan kuis (5 - 120 menit).
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi Kuis <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required><?= htmlspecialchars($quiz['deskripsi']) ?></textarea>
                            <div class="invalid-feedback">
                                Deskripsi kuis harus diisi.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="materi_id" class="form-label">Terkait dengan Materi</label>
                            <select class="form-select" id="materi_id" name="materi_id">
                                <option value="">-- Pilih Materi (Opsional) --</option>
                                <?php foreach ($materials as $materi): ?>
                                    <option value="<?= $materi['id'] ?>" <?= $quiz['materi_id'] == $materi['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($materi['judul']) ?> (<?= htmlspecialchars($materi['kategori']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Pilih materi yang terkait dengan kuis ini (opsional).
                            </div>
                        </div>
                        
                        <h5 class="mt-4 mb-3"><i class="fas fa-question-circle me-2"></i>Pertanyaan Kuis</h5>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Kuis harus memiliki minimal 1 pertanyaan. Perubahan pada pertanyaan dapat mempengaruhi hasil kuis yang sudah ada.
                        </div>
                        
                        <!-- Existing Questions -->
                        <div id="existing-questions-container">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-item card mb-4">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Pertanyaan #<?= $index + 1 ?></h5>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="delete_question_<?= $question['id'] ?>" name="delete_questions[]" value="<?= $question['id'] ?>">
                                            <label class="form-check-label text-danger" for="delete_question_<?= $question['id'] ?>">Hapus</label>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <input type="hidden" name="existing_questions[<?= $question['id'] ?>][id]" value="<?= $question['id'] ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="existing_questions[<?= $question['id'] ?>][pertanyaan]" rows="2" required><?= htmlspecialchars($question['pertanyaan']) ?></textarea>
                                            <div class="invalid-feedback">
                                                Pertanyaan harus diisi.
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Pilihan A <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="existing_questions[<?= $question['id'] ?>][pilihan_a]" value="<?= htmlspecialchars($question['pilihan_a']) ?>" required>
                                                <div class="invalid-feedback">
                                                    Pilihan A harus diisi.
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Pilihan B <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="existing_questions[<?= $question['id'] ?>][pilihan_b]" value="<?= htmlspecialchars($question['pilihan_b']) ?>" required>
                                                <div class="invalid-feedback">
                                                    Pilihan B harus diisi.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Pilihan C</label>
                                                <input type="text" class="form-control" name="existing_questions[<?= $question['id'] ?>][pilihan_c]" value="<?= htmlspecialchars($question['pilihan_c']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Pilihan D</label>
                                                <input type="text" class="form-control" name="existing_questions[<?= $question['id'] ?>][pilihan_d]" value="<?= htmlspecialchars($question['pilihan_d']) ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <label class="form-label">Pilihan E</label>
                                                <input type="text" class="form-control" name="existing_questions[<?= $question['id'] ?>][pilihan_e]" value="<?= htmlspecialchars($question['pilihan_e']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Jawaban Benar <span class="text-danger">*</span></label>
                                                <select class="form-select" name="existing_questions[<?= $question['id'] ?>][jawaban_benar]" required>
                                                    <option value="">-- Pilih Jawaban --</option>
                                                    <option value="a" <?= $question['jawaban_benar'] === 'a' ? 'selected' : '' ?>>A</option>
                                                    <option value="b" <?= $question['jawaban_benar'] === 'b' ? 'selected' : '' ?>>B</option>
                                                    <option value="c" <?= $question['jawaban_benar'] === 'c' ? 'selected' : '' ?>>C</option>
                                                    <option value="d" <?= $question['jawaban_benar'] === 'd' ? 'selected' : '' ?>>D</option>
                                                    <option value="e" <?= $question['jawaban_benar'] === 'e' ? 'selected' : '' ?>>E</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Jawaban benar harus dipilih.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- New Questions Container -->
                        <div id="new-questions-container">
                            <!-- New questions will be added here -->
                        </div>
                        
                        <div class="mb-4">
                            <button type="button" id="add-question" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Tambah Pertanyaan Baru
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
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
    // Handle adding new questions
    const addQuestionBtn = document.getElementById('add-question');
    const newQuestionsContainer = document.getElementById('new-questions-container');
    let newQuestionCount = 0;
    
    addQuestionBtn.addEventListener('click', function() {
        newQuestionCount++;
        
        const questionTemplate = `
            <div class="question-item card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pertanyaan Baru #${newQuestionCount}</h5>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-question">
                        <i class="fas fa-times"></i> Hapus
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="new_questions[${newQuestionCount-1}][pertanyaan]" rows="2" required></textarea>
                        <div class="invalid-feedback">
                            Pertanyaan harus diisi.
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pilihan A <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="new_questions[${newQuestionCount-1}][pilihan_a]" required>
                            <div class="invalid-feedback">
                                Pilihan A harus diisi.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pilihan B <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="new_questions[${newQuestionCount-1}][pilihan_b]" required>
                            <div class="invalid-feedback">
                                Pilihan B harus diisi.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pilihan C</label>
                            <input type="text" class="form-control" name="new_questions[${newQuestionCount-1}][pilihan_c]">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pilihan D</label>
                            <input type="text" class="form-control" name="new_questions[${newQuestionCount-1}][pilihan_d]">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Pilihan E</label>
                            <input type="text" class="form-control" name="new_questions[${newQuestionCount-1}][pilihan_e]">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jawaban Benar <span class="text-danger">*</span></label>
                            <select class="form-select" name="new_questions[${newQuestionCount-1}][jawaban_benar]" required>
                                <option value="">-- Pilih Jawaban --</option>
                                <option value="a">A</option>
                                <option value="b">B</option>
                                <option value="c">C</option>
                                <option value="d">D</option>
                                <option value="e">E</option>
                            </select>
                            <div class="invalid-feedback">
                                Jawaban benar harus dipilih.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Append the new question
        newQuestionsContainer.insertAdjacentHTML('beforeend', questionTemplate);
        
        // Add event listeners to the new remove button
        addRemoveQuestionHandlers();
    });
    
    // Function to handle removing questions
    function addRemoveQuestionHandlers() {
        const removeButtons = document.querySelectorAll('.remove-question');
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.question-item').remove();
                
                // Renumber the new questions
                const newQuestionItems = document.querySelectorAll('#new-questions-container .question-item');
                newQuestionItems.forEach((item, index) => {
                    item.querySelector('h5').textContent = `Pertanyaan Baru #${index + 1}`;
                });
                
                newQuestionCount = newQuestionItems.length;
            });
        });
    }
    
    // Initial setup for any remove buttons
    addRemoveQuestionHandlers();
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        this.classList.add('was-validated');
    });
    
    // Warn if deleting questions
    form.addEventListener('submit', function(event) {
        const deleteCheckboxes = document.querySelectorAll('input[name="delete_questions[]"]:checked');
        if (deleteCheckboxes.length > 0) {
            if (!confirm(`Anda akan menghapus ${deleteCheckboxes.length} pertanyaan. Lanjutkan?`)) {
                event.preventDefault();
            }
        }
    });
});
</script>

</body>
</html>
