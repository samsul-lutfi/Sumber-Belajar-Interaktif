<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in (but don't redirect)
$logged_in = is_logged_in();

// Get filter parameters
$kategori = isset($_GET['kategori']) ? sanitize($_GET['kategori']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$materi_id = isset($_GET['materi_id']) ? (int)$_GET['materi_id'] : 0;

// Build the SQL query based on filters
$sql = "SELECT ft.*, u.username, u.full_name, u.role, 
        (SELECT COUNT(*) FROM forum_replies fr WHERE fr.topic_id = ft.id) as reply_count,
        (SELECT MAX(fr.created_at) FROM forum_replies fr WHERE fr.topic_id = ft.id) as last_reply_at,
        m.judul as materi_judul
        FROM forum_topics ft 
        LEFT JOIN users u ON ft.user_id = u.id
        LEFT JOIN materi m ON ft.materi_id = m.id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($kategori)) {
    $sql .= " AND ft.kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (ft.title LIKE ? OR ft.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($materi_id > 0) {
    $sql .= " AND ft.materi_id = ?";
    $params[] = $materi_id;
    $types .= "i";
}

// Sort options: newest, active, popular
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

switch ($sort) {
    case 'active':
        $sql .= " ORDER BY COALESCE(last_reply_at, ft.created_at) DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY reply_count DESC, ft.created_at DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY ft.created_at DESC";
        break;
}

// Execute the query
$topics = get_records($sql, $params, $types);

// Get all categories
$categories = get_categories();

// Get recently active users
$active_users = get_records(
    "SELECT u.id, u.username, u.full_name, u.role, COUNT(DISTINCT ft.id) + COUNT(DISTINCT fr.id) as activity_count
     FROM users u
     LEFT JOIN forum_topics ft ON u.id = ft.user_id
     LEFT JOIN forum_replies fr ON u.id = fr.user_id
     WHERE (ft.id IS NOT NULL OR fr.id IS NOT NULL)
       AND (ft.created_at >= datetime('now', '-30 day') OR fr.created_at >= datetime('now', '-30 day'))
     GROUP BY u.id
     ORDER BY activity_count DESC
     LIMIT 5"
);

// Get hot topics (most replies in the last 7 days)
$hot_topics = get_records(
    "SELECT ft.id, ft.title, COUNT(fr.id) as reply_count
     FROM forum_topics ft
     JOIN forum_replies fr ON ft.id = fr.topic_id
     WHERE fr.created_at >= datetime('now', '-7 day')
     GROUP BY ft.id
     ORDER BY reply_count DESC
     LIMIT 5"
);

// Log the page view (only if logged in)
if ($logged_in) {
    log_activity($_SESSION['user_id'], 'view', 'forum_list');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi - Sumber Belajar Interaktif</title>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-comments me-2"></i>Forum Diskusi
        </h1>
        <?php if ($logged_in): ?>
            <a href="post.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Buat Topik Baru
            </a>
        <?php else: ?>
            <a href="../auth/login.php" class="btn btn-outline-primary">
                <i class="fas fa-sign-in-alt me-2"></i>Login untuk Diskusi
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="index.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Cari Diskusi</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Cari judul atau isi..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="kategori" class="form-label">Filter Kategori</label>
                    <select class="form-select" id="kategori" name="kategori" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $key => $value): ?>
                            <option value="<?= $key ?>" <?= $kategori === $key ? 'selected' : '' ?>>
                                <?= $value ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Urutkan</label>
                    <select class="form-select" id="sort" name="sort" onchange="this.form.submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="active" <?= $sort === 'active' ? 'selected' : '' ?>>Paling Aktif</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Paling Populer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-sync-alt me-2"></i>Reset
                        </a>
                    </div>
                </div>
                
                <?php if ($materi_id): ?>
                    <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Active Filter Message -->
    <?php if (!empty($kategori) || !empty($search) || $materi_id > 0): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-filter me-2"></i>
            Menampilkan hasil dengan filter:
            <?php if (!empty($kategori)): ?>
                <span class="badge bg-primary me-2">Kategori: <?= htmlspecialchars($categories[$kategori]) ?></span>
            <?php endif; ?>
            
            <?php if (!empty($search)): ?>
                <span class="badge bg-primary me-2">Pencarian: "<?= htmlspecialchars($search) ?>"</span>
            <?php endif; ?>
            
            <?php if ($materi_id > 0): 
                $materi_title = get_record("SELECT judul FROM materi WHERE id = ?", [$materi_id], "i");
                if ($materi_title):
                ?>
                <span class="badge bg-primary me-2">Materi: <?= htmlspecialchars($materi_title['judul']) ?></span>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="index.php" class="ms-2">Hapus Filter</a>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Topics List -->
        <div class="col-lg-8">
            <?php if (!empty($topics)): ?>
                <div class="list-group mb-4">
                    <?php foreach ($topics as $topic): ?>
                        <div class="list-group-item list-group-item-action forum-thread">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">
                                    <a href="view.php?id=<?= $topic['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($topic['title']) ?>
                                    </a>
                                </h5>
                                <small class="text-muted">
                                    <?= time_elapsed_string($topic['created_at']) ?>
                                </small>
                            </div>
                            
                            <div class="mb-1">
                                <span class="badge bg-primary me-2"><?= htmlspecialchars($topic['kategori']) ?></span>
                                
                                <?php if ($topic['materi_id']): ?>
                                    <span class="badge bg-info me-2">
                                        <i class="fas fa-book me-1"></i>
                                        <?= htmlspecialchars($topic['materi_judul']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($topic['is_pinned']): ?>
                                    <span class="badge bg-warning me-2">
                                        <i class="fas fa-thumbtack me-1"></i>Dipin
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($topic['is_closed']): ?>
                                    <span class="badge bg-secondary me-2">
                                        <i class="fas fa-lock me-1"></i>Ditutup
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="mb-1 text-truncate"><?= htmlspecialchars(substr($topic['content'], 0, 150)) ?>...</p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    <small class="text-muted">
                                        Oleh: 
                                        <span class="<?= $topic['role'] === 'admin' ? 'text-danger' : 'text-primary' ?>">
                                            <?= htmlspecialchars($topic['full_name']) ?> 
                                            <?= $topic['role'] === 'admin' ? '<i class="fas fa-chalkboard-teacher"></i>' : '' ?>
                                        </span>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-comment-alt me-1"></i><?= $topic['reply_count'] ?> balasan
                                    </span>
                                    
                                    <?php if ($topic['reply_count'] > 0 && $topic['last_reply_at']): ?>
                                        <small class="ms-2 text-muted">
                                            <i class="fas fa-history me-1"></i>
                                            Update terakhir: <?= time_elapsed_string($topic['last_reply_at']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php if (!empty($kategori) || !empty($search) || $materi_id > 0): ?>
                        Tidak ada topik diskusi yang sesuai dengan filter yang dipilih.
                    <?php else: ?>
                        Belum ada topik diskusi. Jadilah yang pertama membuat topik!
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Forum Stats -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Statistik Forum
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get forum statistics
                    $total_topics = get_record("SELECT COUNT(*) as total FROM forum_topics")['total'];
                    $total_replies = get_record("SELECT COUNT(*) as total FROM forum_replies")['total'];
                    $total_users = get_record("SELECT COUNT(DISTINCT user_id) as total FROM (SELECT user_id FROM forum_topics UNION SELECT user_id FROM forum_replies) as active_users")['total'];
                    ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <h4><?= $total_topics ?></h4>
                            <p class="text-muted">Topik</p>
                        </div>
                        <div class="col-4">
                            <h4><?= $total_replies ?></h4>
                            <p class="text-muted">Balasan</p>
                        </div>
                        <div class="col-4">
                            <h4><?= $total_users ?></h4>
                            <p class="text-muted">Peserta</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Users -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Pengguna Aktif
                    </h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (!empty($active_users)): ?>
                            <?php foreach ($active_users as $user): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle me-2"></i>
                                        <span class="<?= $user['role'] === 'admin' ? 'text-danger' : '' ?>">
                                            <?= htmlspecialchars($user['full_name']) ?>
                                            <?= $user['role'] === 'admin' ? ' <i class="fas fa-chalkboard-teacher"></i>' : '' ?>
                                        </span>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?= $user['activity_count'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center">Belum ada data pengguna aktif.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Hot Topics -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-fire me-2"></i>Topik Populer
                    </h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (!empty($hot_topics)): ?>
                            <?php foreach ($hot_topics as $hot): ?>
                                <li class="list-group-item">
                                    <a href="view.php?id=<?= $hot['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($hot['title']) ?>
                                    </a>
                                    <span class="badge bg-danger float-end">
                                        <i class="fas fa-comment-alt me-1"></i><?= $hot['reply_count'] ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center">Belum ada topik populer minggu ini.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Forum Guidelines -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Panduan Forum
                    </h5>
                </div>
                <div class="card-body">
                    <p><i class="fas fa-check-circle me-2"></i>Gunakan bahasa yang sopan dan menghargai.</p>
                    <p><i class="fas fa-check-circle me-2"></i>Berikan pertanyaan yang jelas dan spesifik.</p>
                    <p><i class="fas fa-check-circle me-2"></i>Bantu jawab pertanyaan jika Anda bisa.</p>
                    <p><i class="fas fa-times-circle me-2"></i>Hindari topik yang tidak relevan dengan pembelajaran.</p>
                    <p><i class="fas fa-times-circle me-2"></i>Jangan melakukan spam atau promosi.</p>
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

</body>
</html>

<?php
/**
 * Helper function to convert timestamp to "time ago" format
 * 
 * @param string $datetime MySQL datetime string
 * @return string Formatted time string (e.g., "2 hours ago")
 */
// Using the shared function from includes/functions.php instead of redefining it here
?>
