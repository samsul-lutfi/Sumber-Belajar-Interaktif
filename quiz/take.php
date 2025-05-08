<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
require_login();

if (is_admin()) {
    set_flash_message('Admin tidak dapat mengerjakan kuis.', 'warning');
    redirect('index.php');
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID kuis tidak valid.', 'danger');
    redirect('index.php');
}

$quiz_id = (int)$_GET['id'];

// Get quiz details
$quiz = get_record(
    "SELECT q.*, m.judul as materi_judul, m.id as materi_id 
     FROM quiz q
     LEFT JOIN materi m ON q.materi_id = m.id
     WHERE q.id = ?",
    [$quiz_id],
    "i"
);

// If quiz not found
if (!$quiz) {
    set_flash_message('Kuis tidak ditemukan.', 'danger');
    redirect('index.php');
}

// Check if user has already completed this quiz
$existing_result = get_record(
    "SELECT id, score FROM hasil_kuis WHERE user_id = ? AND quiz_id = ?",
    [$_SESSION['user_id'], $quiz_id],
    "ii"
);

if ($existing_result) {
    set_flash_message('Anda sudah mengerjakan kuis ini. Nilai Anda: ' . $existing_result['score'] . '%', 'info');
    redirect('results.php?quiz_id=' . $quiz_id);
}

// Get quiz questions - SQLite doesn't have RAND(), using RANDOM() instead
$questions = get_records(
    "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY RANDOM()",
    [$quiz_id],
    "i"
);

// If no questions found
if (empty($questions)) {
    set_flash_message('Tidak ada pertanyaan untuk kuis ini.', 'warning');
    redirect('index.php');
}

$error_message = '';
$success_message = '';

// Process quiz submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_quiz'])) {
    // Verify time constraints
    if (isset($_POST['start_time'])) {
        $start_time = (int)$_POST['start_time'];
        $current_time = time();
        $time_spent = $current_time - $start_time;
        $max_time = $quiz['durasi'] * 60; // convert minutes to seconds
        
        // If time limit exceeded (add a small buffer of 10 seconds for form submission)
        if ($time_spent > ($max_time + 10)) {
            $time_expired = true;
        }
    }
    
    // Build answers array and correct answers array
    $user_answers = [];
    $correct_answers = [];
    
    foreach ($questions as $question) {
        $question_id = $question['id'];
        $user_answer = isset($_POST['question_' . $question_id]) ? $_POST['question_' . $question_id] : '';
        
        $user_answers[$question_id] = $user_answer;
        $correct_answers[$question_id] = $question['jawaban_benar'];
    }
    
    // Calculate score
    $score = calculate_quiz_score($user_answers, $correct_answers);
    
    // Insert result into database
    $sql = "INSERT INTO hasil_kuis (user_id, quiz_id, score, jawaban, completed_at) VALUES (?, ?, ?, ?, NOW())";
    $jawaban_json = json_encode($user_answers);
    
    $stmt = execute_query($sql, [$_SESSION['user_id'], $quiz_id, $score, $jawaban_json], "iids");
    
    if ($stmt === false) {
        $error_message = 'Terjadi kesalahan saat menyimpan hasil kuis. Silakan coba lagi.';
    } else {
        $result_id = $conn->lastInsertRowID() ?? get_record("SELECT last_insert_rowid() as id")['id'];
        
        // Log the activity
        log_activity($_SESSION['user_id'], 'complete', 'quiz', $quiz_id);
        
        // Redirect to results page
        set_flash_message('Kuis berhasil diselesaikan. Nilai Anda: ' . $score . '%', 'success');
        redirect('results.php?id=' . $result_id);
    }
}

// Log the quiz view
log_activity($_SESSION['user_id'], 'start', 'quiz', $quiz_id);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mengerjakan Kuis: <?= htmlspecialchars($quiz['judul']) ?> - Sumber Belajar Interaktif</title>
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
            <li class="breadcrumb-item active">Mengerjakan Kuis</li>
        </ol>
    </nav>
    
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
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-tasks me-2"></i><?= htmlspecialchars($quiz['judul']) ?></h4>
            <div class="quiz-timer text-white bg-warning p-2 rounded" data-duration="<?= $quiz['durasi'] * 60 ?>">
                <?= sprintf('%02d:%02d', $quiz['durasi'], 0) ?>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-primary"><?= htmlspecialchars($quiz['kategori']) ?></span>
                    <span class="text-muted">
                        <i class="fas fa-question-circle me-1"></i><?= count($questions) ?> pertanyaan
                    </span>
                </div>
                
                <p><?= nl2br(htmlspecialchars($quiz['deskripsi'])) ?></p>
                
                <?php if ($quiz['materi_id']): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Kuis ini terkait dengan materi: 
                        <a href="../materi/view.php?id=<?= $quiz['materi_id'] ?>" target="_blank" class="alert-link">
                            <?= htmlspecialchars($quiz['materi_judul']) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <form id="quizForm" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $quiz_id ?>" method="post" class="quiz-form needs-validation" novalidate>
                <input type="hidden" name="start_time" value="<?= time() ?>">
                
                <?php foreach ($questions as $index => $question): ?>
                    <div class="quiz-container">
                        <h5 class="mb-3">
                            <span class="badge bg-secondary me-2"><?= $index + 1 ?></span>
                            <?= htmlspecialchars($question['pertanyaan']) ?>
                        </h5>
                        
                        <div class="mb-4">
                            <?php
                            // Prepare options
                            $options = [
                                'a' => $question['pilihan_a'],
                                'b' => $question['pilihan_b'],
                                'c' => $question['pilihan_c'],
                                'd' => $question['pilihan_d']
                            ];
                            
                            // If option e exists and is not empty
                            if (!empty($question['pilihan_e'])) {
                                $options['e'] = $question['pilihan_e'];
                            }
                            ?>
                            
                            <?php foreach ($options as $key => $value): ?>
                                <?php if (!empty($value)): ?>
                                    <div class="quiz-option">
                                        <input type="radio" id="question_<?= $question['id'] ?>_<?= $key ?>" 
                                               name="question_<?= $question['id'] ?>" value="<?= $key ?>" 
                                               class="form-check-input me-2" required>
                                        <label for="question_<?= $question['id'] ?>_<?= $key ?>" class="form-check-label w-100">
                                            <strong><?= strtoupper($key) ?>.</strong> <?= htmlspecialchars($value) ?>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <div class="invalid-feedback">
                                Silakan pilih salah satu jawaban.
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($index < count($questions) - 1): ?>
                        <hr class="my-4">
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-outline-secondary" id="cancelButton">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                    <button type="submit" name="submit_quiz" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Selesaikan Kuis
                    </button>
                </div>
            </form>
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
    // Quiz timer
    const timerElement = document.querySelector('.quiz-timer');
    if (timerElement) {
        const duration = parseInt(timerElement.dataset.duration || 0, 10);
        let timer = duration;
        
        const interval = setInterval(() => {
            const minutes = Math.floor(timer / 60);
            const seconds = timer % 60;
            
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (--timer < 0) {
                clearInterval(interval);
                alert('Waktu habis! Kuis akan otomatis diserahkan.');
                document.getElementById('quizForm').submit();
            }
            
            // Warning when less than 1 minute remains
            if (timer < 60) {
                timerElement.classList.add('bg-danger');
                timerElement.classList.remove('bg-warning');
            }
        }, 1000);
    }
    
    // Styling for quiz options
    const quizOptions = document.querySelectorAll('.quiz-option');
    quizOptions.forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                
                // Remove selected class from all options in the same group
                const name = radio.getAttribute('name');
                document.querySelectorAll(`input[name="${name}"]`).forEach(input => {
                    input.closest('.quiz-option').classList.remove('selected');
                });
                
                // Add selected class to this option
                this.classList.add('selected');
            }
        });
    });
    
    // Confirmation before leaving page
    const quizForm = document.getElementById('quizForm');
    const cancelButton = document.getElementById('cancelButton');
    
    window.addEventListener('beforeunload', function(e) {
        // If form is dirty (user has started the quiz)
        if (isFormDirty(quizForm)) {
            e.preventDefault();
            e.returnValue = 'Anda memiliki kuis yang belum selesai. Yakin ingin meninggalkan halaman ini?';
        }
    });
    
    cancelButton.addEventListener('click', function(e) {
        if (isFormDirty(quizForm)) {
            if (!confirm('Anda memiliki kuis yang belum selesai. Yakin ingin meninggalkan halaman ini?')) {
                e.preventDefault();
            }
        }
    });
    
    // Function to check if form has any filled values
    function isFormDirty(form) {
        const radios = form.querySelectorAll('input[type="radio"]');
        for (let i = 0; i < radios.length; i++) {
            if (radios[i].checked) {
                return true;
            }
        }
        return false;
    }
    
    // Form submission confirmation
    quizForm.addEventListener('submit', function(e) {
        const radioGroups = {};
        const requiredRadios = quizForm.querySelectorAll('input[type="radio"][required]');
        
        // Group radios by name
        requiredRadios.forEach(radio => {
            if (!radioGroups[radio.name]) {
                radioGroups[radio.name] = [];
            }
            radioGroups[radio.name].push(radio);
        });
        
        // Check if all radio groups have at least one selected
        let allAnswered = true;
        for (const groupName in radioGroups) {
            if (!radioGroups[groupName].some(radio => radio.checked)) {
                allAnswered = false;
                // Find the first unanswered question and scroll to it
                const container = radioGroups[groupName][0].closest('.quiz-container');
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                break;
            }
        }
        
        if (!allAnswered) {
            e.preventDefault();
            alert('Harap jawab semua pertanyaan sebelum menyelesaikan kuis.');
        } else if (!confirm('Apakah Anda yakin ingin menyelesaikan kuis ini?')) {
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>
