<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in (but don't require login)
$logged_in = is_logged_in();

// Get topic ID
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no topic ID provided, redirect to forum index
if ($topic_id <= 0) {
    redirect('index.php');
}

// Get topic details
$topic = get_record(
    "SELECT f.*, u.username, u.full_name, u.role,
     m.judul as materi_judul, m.id as materi_id
     FROM forum_topics f 
     LEFT JOIN users u ON f.user_id = u.id
     LEFT JOIN materi m ON f.materi_id = m.id
     WHERE f.id = ?",
    [$topic_id],
    "i"
);

// If topic not found, redirect to forum index
if (!$topic) {
    set_flash_message('Topik tidak ditemukan.', 'danger');
    redirect('index.php');
}

// Get replies
$replies = get_records(
    "SELECT r.*, u.username, u.full_name, u.role
     FROM forum_replies r
     JOIN users u ON r.user_id = u.id
     WHERE r.topic_id = ?
     ORDER BY r.created_at ASC",
    [$topic_id],
    "i"
);

// Get related topics (same category)
$related_topics = get_records(
    "SELECT f.id, f.title, 
     (SELECT COUNT(*) FROM forum_replies fr WHERE fr.topic_id = f.id) as reply_count
     FROM forum_topics f 
     WHERE f.kategori = ? AND f.id != ?
     ORDER BY f.created_at DESC
     LIMIT 5",
    [$topic['kategori'], $topic_id],
    "si"
);

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in) {
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    
    // Validate content
    if (empty($content)) {
        set_flash_message('Isi balasan tidak boleh kosong.', 'danger');
    } else if (strlen($content) < 5) {
        set_flash_message('Isi balasan terlalu pendek (minimal 5 karakter).', 'danger');
    } else if (strlen($content) > 5000) {
        set_flash_message('Isi balasan terlalu panjang (maksimal 5000 karakter).', 'danger');
    } else if ($topic['is_closed']) {
        set_flash_message('Topik ini telah ditutup, tidak dapat menambah balasan baru.', 'danger');
    } else {
        try {
            // Insert reply with SQLite-compatible syntax
            $sql = "INSERT INTO forum_replies (topic_id, user_id, content, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            execute_query($sql, [$topic_id, $_SESSION['user_id'], $content], "iis");
            
            // Get last insert ID
            $reply_id = $conn->lastInsertRowID();
            
            // Update last_activity for the topic
            execute_query("UPDATE forum_topics SET updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$topic_id], "i");
            
            // Log activity
            log_activity($_SESSION['user_id'], 'add', 'forum_reply', $reply_id);
            
            set_flash_message('Balasan berhasil ditambahkan.', 'success');
            redirect("view.php?id=$topic_id#reply-$reply_id");
        } catch (Exception $e) {
            set_flash_message('Gagal menambahkan balasan: ' . $e->getMessage(), 'danger');
        }
    }
}

// Log page view if logged in
if ($logged_in) {
    log_activity($_SESSION['user_id'], 'view', 'forum_topic');
}

// Cek dan set default untuk views jika belum ada
if (!isset($topic['views'])) {
    $topic['views'] = 0;
}

// Increment view count - pastikan kolom views ada di tabel
try {
    $query = "UPDATE forum_topics SET views = views + 1 WHERE id = ?";
    execute_query($query, [$topic_id], "i");
} catch (Exception $e) {
    // Jika kolom views tidak ada, kita bisa mengabaikan error
}

// Get categories for reference
$categories = get_categories();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($topic['title']) ?> - Forum Diskusi</title>
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
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($topic['title']) ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Topic -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= htmlspecialchars($topic['title']) ?></h5>
                    
                    <?php if ($logged_in && ($_SESSION['user_id'] == $topic['user_id'] || is_admin())): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary" type="button" id="topicActions" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="topicActions">
                                <li><a class="dropdown-item" href="edit.php?id=<?= $topic_id ?>"><i class="fas fa-edit me-2"></i>Edit Topik</a></li>
                                <?php if (is_admin()): ?>
                                    <li><a class="dropdown-item" href="toggle_pin.php?id=<?= $topic_id ?>">
                                        <?php if ($topic['is_pinned']): ?>
                                            <i class="fas fa-thumbtack me-2"></i>Lepas Pin
                                        <?php else: ?>
                                            <i class="fas fa-thumbtack me-2"></i>Pin Topik
                                        <?php endif; ?>
                                    </a></li>
                                    <li><a class="dropdown-item" href="toggle_close.php?id=<?= $topic_id ?>">
                                        <?php if ($topic['is_closed']): ?>
                                            <i class="fas fa-lock-open me-2"></i>Buka Topik
                                        <?php else: ?>
                                            <i class="fas fa-lock me-2"></i>Tutup Topik
                                        <?php endif; ?>
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="delete.php?id=<?= $topic_id ?>">
                                    <i class="fas fa-trash-alt me-2"></i>Hapus Topik
                                </a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge bg-primary me-2"><?= htmlspecialchars($categories[$topic['kategori']]) ?></span>
                        
                        <?php if ($topic['materi_id']): ?>
                            <a href="../materi/view.php?id=<?= $topic['materi_id'] ?>" class="badge bg-info text-decoration-none me-2">
                                <i class="fas fa-book me-1"></i><?= htmlspecialchars($topic['materi_judul']) ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($topic['is_pinned']): ?>
                            <span class="badge bg-warning text-dark me-2">
                                <i class="fas fa-thumbtack me-1"></i>Dipin
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($topic['is_closed']): ?>
                            <span class="badge bg-secondary me-2">
                                <i class="fas fa-lock me-1"></i>Ditutup
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <div class="user-avatar bg-primary text-white">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-0">
                                    <span class="<?= $topic['role'] === 'admin' ? 'text-danger' : 'text-primary' ?>">
                                        <?= htmlspecialchars($topic['full_name']) ?>
                                        <?= $topic['role'] === 'admin' ? ' <i class="fas fa-chalkboard-teacher"></i>' : '' ?>
                                    </span>
                                </h6>
                                <small class="text-muted"><?= format_date($topic['created_at']) ?></small>
                            </div>
                            <small class="text-muted mb-2 d-block">@<?= htmlspecialchars($topic['username']) ?></small>
                        </div>
                    </div>
                    
                    <div class="topic-content">
                        <?= nl2br(htmlspecialchars($topic['content'])) ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <span class="text-muted">
                                <i class="fas fa-eye me-1"></i><?= $topic['views'] + 1 ?> views
                            </span>
                            <span class="ms-3 text-muted">
                                <i class="fas fa-comment-alt me-1"></i><?= count($replies) ?> balasan
                            </span>
                        </div>
                        <?php if ($logged_in && !$topic['is_closed']): ?>
                            <a href="#reply-form" class="btn btn-sm btn-primary">
                                <i class="fas fa-reply me-1"></i>Balas
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Replies -->
            <?php if (!empty($replies)): ?>
                <h4 class="mb-3">
                    <i class="fas fa-comments me-2"></i>Balasan (<?= count($replies) ?>)
                </h4>
                
                <?php foreach ($replies as $index => $reply): ?>
                    <div class="card mb-3 forum-reply" id="reply-<?= $reply['id'] ?>">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="user-avatar bg-primary text-white">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-0">
                                            <span class="<?= $reply['role'] === 'admin' ? 'text-danger' : 'text-primary' ?>">
                                                <?= htmlspecialchars($reply['full_name']) ?>
                                                <?= $reply['role'] === 'admin' ? ' <i class="fas fa-chalkboard-teacher"></i>' : '' ?>
                                            </span>
                                        </h6>
                                        <div>
                                            <small class="text-muted"><?= format_date($reply['created_at']) ?></small>
                                            
                                            <?php if ($logged_in && ($_SESSION['user_id'] == $reply['user_id'] || is_admin())): ?>
                                                <div class="dropdown d-inline-block ms-2">
                                                    <button class="btn btn-sm text-muted p-0" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="edit_reply.php?id=<?= $reply['id'] ?>">
                                                            <i class="fas fa-edit me-2"></i>Edit Balasan
                                                        </a></li>
                                                        <li><a class="dropdown-item text-danger" href="delete_reply.php?id=<?= $reply['id'] ?>" 
                                                              onclick="return confirm('Apakah Anda yakin ingin menghapus balasan ini?')">
                                                            <i class="fas fa-trash-alt me-2"></i>Hapus Balasan
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted mb-2 d-block">@<?= htmlspecialchars($reply['username']) ?></small>
                                    <div class="mt-2">
                                        <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>Belum ada balasan untuk topik ini.
                </div>
            <?php endif; ?>
            
            <!-- Reply Form -->
            <?php if ($logged_in && !$topic['is_closed']): ?>
                <div class="card mt-4" id="reply-form">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-reply me-2"></i>Tambahkan Balasan
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="view.php?id=<?= $topic_id ?>" method="POST">
                            <div class="mb-3">
                                <label for="content" class="form-label">Isi Balasan</label>
                                <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                                <div class="form-text">Minimal 5 karakter, maksimal 5000 karakter.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Kirim Balasan
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif (!$logged_in): ?>
                <div class="alert alert-warning mt-4">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <a href="../auth/login.php" class="alert-link">Login</a> untuk menambahkan balasan.
                </div>
            <?php elseif ($topic['is_closed']): ?>
                <div class="alert alert-secondary mt-4">
                    <i class="fas fa-lock me-2"></i>Topik ini telah ditutup, tidak dapat menambah balasan baru.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Topic Info -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Info Topik
                    </h5>
                </div>
                <div class="card-body">
                    <p><strong>Dibuat oleh:</strong> <?= htmlspecialchars($topic['full_name']) ?></p>
                    <p><strong>Tanggal dibuat:</strong> <?= format_date($topic['created_at'], true) ?></p>
                    <p><strong>Kategori:</strong> <?= htmlspecialchars($categories[$topic['kategori']]) ?></p>
                    <p><strong>Status:</strong> 
                        <?php if ($topic['is_closed']): ?>
                            <span class="badge bg-secondary">Ditutup</span>
                        <?php else: ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Dilihat:</strong> <?= $topic['views'] + 1 ?> kali</p>
                    
                    <?php if ($topic['materi_id']): ?>
                        <div class="mt-3">
                            <a href="../materi/view.php?id=<?= $topic['materi_id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-book me-2"></i>Lihat Materi Terkait
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Related Topics -->
            <?php if (!empty($related_topics)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Topik Terkait
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($related_topics as $related): ?>
                                <li class="list-group-item">
                                    <a href="view.php?id=<?= $related['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($related['title']) ?>
                                    </a>
                                    <span class="badge bg-light text-dark float-end">
                                        <i class="fas fa-comment-alt me-1"></i><?= $related['reply_count'] ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Back to Forum -->
            <a href="index.php" class="btn btn-outline-primary w-100 mb-4">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Topik
            </a>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<style>
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
</style>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>
</body>
</html>