<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
require_admin();

// Get all categories
$categories = get_categories();

// Get all materials for association
$materials = get_records(
    "SELECT id, judul, kategori FROM materi ORDER BY judul ASC"
);

$error_message = '';
$success_message = '';
$form_data = [
    'judul' => '',
    'kategori' => 'buku',
    'deskripsi' => '',
    'durasi' => 30,
    'materi_id' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $form_data = [
        'judul' => sanitize($_POST['judul'] ?? ''),
        'kategori' => sanitize($_POST['kategori'] ?? ''),
        'deskripsi' => sanitize($_POST['deskripsi'] ?? ''),
        'durasi' => (int)($_POST['durasi'] ?? 30),
        'materi_id' => !empty($_POST['materi_id']) ? (int)$_POST['materi_id'] : null
    ];
    
    // Basic validation
    if (empty($form_data['judul']) || empty($form_data['kategori']) || empty($form_data['deskripsi'])) {
        $error_message = 'Judul, kategori, dan deskripsi harus diisi.';
    } elseif ($form_data['durasi'] < 5 || $form_data['durasi'] > 120) {
        $error_message = 'Durasi kuis harus antara 5 - 120 menit.';
    } else {
        // Start transaction (we don't use transactions for SQLite in this app)
        
        try {
            // Insert quiz into database (using CURRENT_TIMESTAMP for SQLite)
            $sql = "INSERT INTO quiz (judul, kategori, deskripsi, durasi, materi_id, user_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            
            $stmt = execute_query(
                $sql, 
                [
                    $form_data['judul'], 
                    $form_data['kategori'], 
                    $form_data['deskripsi'], 
                    $form_data['durasi'], 
                    $form_data['materi_id'], 
                    $_SESSION['user_id']
                ], 
                "sssiii"
            );
            
            if ($stmt === false) {
                throw new Exception('Terjadi kesalahan saat menyimpan kuis.');
            }
            
            $quiz_id = $conn->lastInsertRowID() ?? get_record("SELECT last_insert_rowid() as id")['id'];
            
            // Process quiz questions
            if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                foreach ($_POST['questions'] as $index => $question_data) {
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
                    
                    // Insert question (using CURRENT_TIMESTAMP for SQLite)
                    $q_sql = "INSERT INTO quiz_questions (quiz_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, jawaban_benar, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                    
                    $q_stmt = execute_query(
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
                    
                    if ($q_stmt === false) {
                        throw new Exception('Terjadi kesalahan saat menyimpan pertanyaan kuis.');
                    }
                }
            }
            
            // No need for commit in SQLite
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'add', 'quiz', $quiz_id);
            
            // Set success message and redirect
            set_flash_message('Kuis berhasil ditambahkan.', 'success');
            redirect("edit.php?id=$quiz_id");
            
        } catch (Exception $e) {
            // No rollback needed for SQLite
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
    <title>Tambah Kuis - Sumber Belajar Interaktif</title>
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
            <li class="breadcrumb-item active">Tambah Kuis</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Tambah Kuis Baru</h4>
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
                    
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Kuis <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="judul" name="judul" value="<?= htmlspecialchars($form_data['judul']) ?>" required>
                            <div class="invalid-feedback">
                                Judul kuis harus diisi.
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
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
                            
                            <div class="col-md-6">
                                <label for="durasi" class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="durasi" name="durasi" value="<?= $form_data['durasi'] ?>" min="5" max="120" required>
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
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required><?= htmlspecialchars($form_data['deskripsi']) ?></textarea>
                            <div class="invalid-feedback">
                                Deskripsi kuis harus diisi.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="materi_id" class="form-label">Terkait dengan Materi</label>
                            <select class="form-select" id="materi_id" name="materi_id">
                                <option value="">-- Pilih Materi (Opsional) --</option>
                                <?php foreach ($materials as $materi): ?>
                                    <option value="<?= $materi['id'] ?>" <?= $form_data['materi_id'] == $materi['id'] ? 'selected' : '' ?>>
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
                            Tambahkan minimal 1 pertanyaan untuk kuis. Pertanyaan dapat ditambahkan lebih banyak setelah kuis dibuat.
                        </div>
                        
                        <div id="questions-container">
                            <!-- Question template will be added here -->
                            <div class="question-item card mb-4">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Pertanyaan #1</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="questions[0][pertanyaan]" rows="2" required></textarea>
                                        <div class="invalid-feedback">
                                            Pertanyaan harus diisi.
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Pilihan A <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="questions[0][pilihan_a]" required>
                                            <div class="invalid-feedback">
                                                Pilihan A harus diisi.
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Pilihan B <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="questions[0][pilihan_b]" required>
                                            <div class="invalid-feedback">
                                                Pilihan B harus diisi.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Pilihan C</label>
                                            <input type="text" class="form-control" name="questions[0][pilihan_c]">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Pilihan D</label>
                                            <input type="text" class="form-control" name="questions[0][pilihan_d]">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Pilihan E</label>
                                            <input type="text" class="form-control" name="questions[0][pilihan_e]">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Jawaban Benar <span class="text-danger">*</span></label>
                                            <select class="form-select" name="questions[0][jawaban_benar]" required>
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
                        </div>
                        
                        <div class="mb-4">
                            <button type="button" id="add-question" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Tambah Pertanyaan Lain
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Kuis
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
    const questionsContainer = document.getElementById('questions-container');
    let questionCount = 1;
    
    addQuestionBtn.addEventListener('click', function() {
        questionCount++;
        
        const questionTemplate = `
            <div class="question-item card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pertanyaan #${questionCount}</h5>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-question">
                        <i class="fas fa-times"></i> Hapus
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="questions[${questionCount-1}][pertanyaan]" rows="2" required></textarea>
                        <div class="invalid-feedback">
                            Pertanyaan harus diisi.
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pilihan A <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="questions[${questionCount-1}][pilihan_a]" required>
                            <div class="invalid-feedback">
                                Pilihan A harus diisi.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pilihan B <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="questions[${questionCount-1}][pilihan_b]" required>
                            <div class="invalid-feedback">
                                Pilihan B harus diisi.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pilihan C</label>
                            <input type="text" class="form-control" name="questions[${questionCount-1}][pilihan_c]">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pilihan D</label>
                            <input type="text" class="form-control" name="questions[${questionCount-1}][pilihan_d]">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Pilihan E</label>
                            <input type="text" class="form-control" name="questions[${questionCount-1}][pilihan_e]">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jawaban Benar <span class="text-danger">*</span></label>
                            <select class="form-select" name="questions[${questionCount-1}][jawaban_benar]" required>
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
        questionsContainer.insertAdjacentHTML('beforeend', questionTemplate);
        
        // Add event listeners to the new remove button
        addRemoveQuestionHandlers();
    });
    
    // Function to handle removing questions
    function addRemoveQuestionHandlers() {
        const removeButtons = document.querySelectorAll('.remove-question');
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.question-item').remove();
                
                // Renumber the questions
                const questionItems = document.querySelectorAll('.question-item');
                questionItems.forEach((item, index) => {
                    item.querySelector('h5').textContent = `Pertanyaan #${index + 1}`;
                });
                
                questionCount = questionItems.length;
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
});
</script>

</body>
</html>
