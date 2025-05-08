<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
require_admin();

// Get filter parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$module = isset($_GET['module']) ? sanitize($_GET['module']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Build the SQL query based on filters
$sql = "SELECT al.*, u.username, u.full_name, u.role 
         FROM activity_logs al
         JOIN users u ON al.user_id = u.id
         WHERE 1=1";
$params = [];
$types = "";

if ($user_id > 0) {
    $sql .= " AND al.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($action)) {
    $sql .= " AND al.action = ?";
    $params[] = $action;
    $types .= "s";
}

if (!empty($module)) {
    $sql .= " AND al.module = ?";
    $params[] = $module;
    $types .= "s";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Order by most recent first
$sql .= " ORDER BY al.created_at DESC";

// Add limit for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50; // 50 items per page
$offset = ($page - 1) * $limit;

$sql .= " LIMIT $limit OFFSET $offset";

// Execute the query
$activities = get_records($sql, $params, $types);

// Get count for pagination
$count_sql = "SELECT COUNT(*) as total FROM activity_logs al WHERE 1=1";
$count_params = [];
$count_types = "";

if ($user_id > 0) {
    $count_sql .= " AND al.user_id = ?";
    $count_params[] = $user_id;
    $count_types .= "i";
}

if (!empty($action)) {
    $count_sql .= " AND al.action = ?";
    $count_params[] = $action;
    $count_types .= "s";
}

if (!empty($module)) {
    $count_sql .= " AND al.module = ?";
    $count_params[] = $module;
    $count_types .= "s";
}

if (!empty($date_from)) {
    $count_sql .= " AND DATE(al.created_at) >= ?";
    $count_params[] = $date_from;
    $count_types .= "s";
}

if (!empty($date_to)) {
    $count_sql .= " AND DATE(al.created_at) <= ?";
    $count_params[] = $date_to;
    $count_types .= "s";
}

$total_activities = get_record($count_sql, $count_params, $count_types)['total'];
$total_pages = ceil($total_activities / $limit);

// Get all users for filter dropdown
$users = get_records("SELECT id, username, full_name, role FROM users ORDER BY full_name ASC");

// Get available modules for filter
$modules = get_records("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC");

// Get available actions for filter
$actions = get_records("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");

// Log the page view
log_activity($_SESSION['user_id'], 'view', 'activity_logs');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas Pengguna - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php include '../../includes/header.php'; ?>

<main class="container py-4">
    <!-- Page Header with Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">
                <i class="fas fa-history me-2"></i>Log Aktivitas Pengguna
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="../../dashboard/admin.php">Dashboard Admin</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Manajemen Pengguna</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Log Aktivitas</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>
    
    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-history text-primary"></i>
                <div class="stat-value"><?= $total_activities ?></div>
                <p class="stat-label">Total Aktivitas</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <?php
                $logins_count = get_record("SELECT COUNT(*) as count FROM activity_logs WHERE action = 'login'")['count'];
                ?>
                <i class="fas fa-sign-in-alt text-success"></i>
                <div class="stat-value"><?= $logins_count ?></div>
                <p class="stat-label">Total Login</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <?php
                $today_activities = get_record(
                    "SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = DATE('now')"
                )['count'];
                ?>
                <i class="fas fa-calendar-day text-warning"></i>
                <div class="stat-value"><?= $today_activities ?></div>
                <p class="stat-label">Aktivitas Hari Ini</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <?php
                $active_users = get_record(
                    "SELECT COUNT(DISTINCT user_id) as count FROM activity_logs WHERE DATE(created_at) = DATE('now')"
                )['count'];
                ?>
                <i class="fas fa-users text-info"></i>
                <div class="stat-value"><?= $active_users ?></div>
                <p class="stat-label">Pengguna Aktif Hari Ini</p>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filter Log Aktivitas
            </h5>
        </div>
        <div class="card-body">
            <form action="activities.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Pengguna</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Semua Pengguna</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_id == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['full_name']) ?> (<?= formatRole($user['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="action" class="form-label">Jenis Aksi</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($actions as $act): ?>
                            <option value="<?= $act['action'] ?>" <?= $action == $act['action'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($act['action']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="module" class="form-label">Modul</label>
                    <select class="form-select" id="module" name="module">
                        <option value="">Semua Modul</option>
                        <?php foreach ($modules as $mod): ?>
                            <option value="<?= $mod['module'] ?>" <?= $module == $mod['module'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mod['module']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label d-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                    <a href="activities.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-sync-alt me-2"></i>Reset Filter
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Activities List -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Daftar Aktivitas
            </h5>
            <span class="badge bg-primary"><?= number_format($total_activities) ?> total aktivitas</span>
        </div>
        <div class="card-body">
            <?php if (!empty($activities)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="15%">Waktu</th>
                                <th width="20%">Pengguna</th>
                                <th width="15%">Aksi</th>
                                <th width="15%">Modul</th>
                                <th width="10%">IP</th>
                                <th width="20%">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?= $activity['id'] ?></td>
                                    <td>
                                        <span title="<?= format_date($activity['created_at'], true) ?>">
                                            <?= format_date($activity['created_at']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs bg-<?= getRoleBadgeClass($activity['role']) ?> text-white me-2">
                                                <i class="fas fa-<?= getRoleIcon($activity['role']) ?>"></i>
                                            </div>
                                            <div>
                                                <span><?= htmlspecialchars($activity['full_name']) ?></span><br>
                                                <small class="text-muted">@<?= htmlspecialchars($activity['username']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $action_badges = [
                                                'login' => 'bg-success',
                                                'logout' => 'bg-secondary',
                                                'view' => 'bg-primary',
                                                'add' => 'bg-info',
                                                'edit' => 'bg-warning text-dark',
                                                'delete' => 'bg-danger',
                                                'password_change' => 'bg-danger',
                                                'profile_update' => 'bg-warning text-dark'
                                            ];
                                            
                                            $action_icons = [
                                                'login' => 'sign-in-alt',
                                                'logout' => 'sign-out-alt',
                                                'view' => 'eye',
                                                'add' => 'plus-circle',
                                                'edit' => 'edit',
                                                'delete' => 'trash-alt',
                                                'password_change' => 'key',
                                                'profile_update' => 'user-edit'
                                            ];
                                            
                                            $badge_class = isset($action_badges[$activity['action']]) ? $action_badges[$activity['action']] : 'bg-secondary';
                                            $icon = isset($action_icons[$activity['action']]) ? $action_icons[$activity['action']] : 'exclamation-circle';
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <i class="fas fa-<?= $icon ?> me-1"></i><?= htmlspecialchars($activity['action']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($activity['module']) ?></td>
                                    <td>
                                        <?php if (!empty($activity['ip_address'])): ?>
                                            <span class="badge bg-light text-dark">
                                                <?= htmlspecialchars($activity['ip_address']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($activity['details'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detailModal<?= $activity['id'] ?>">
                                                <i class="fas fa-info-circle me-1"></i>Lihat Detail
                                            </button>
                                            
                                            <!-- Detail Modal -->
                                            <div class="modal fade" id="detailModal<?= $activity['id'] ?>" tabindex="-1" aria-labelledby="detailModalLabel<?= $activity['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="detailModalLabel<?= $activity['id'] ?>">Detail Aktivitas #<?= $activity['id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <pre class="bg-light p-3 rounded"><?= htmlspecialchars(json_encode(json_decode($activity['details']), JSON_PRETTY_PRINT)) ?></pre>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Log pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                      <a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>
                                      </li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php if (!empty($user_id) || !empty($action) || !empty($module) || !empty($date_from) || !empty($date_to)): ?>
                        Tidak ada aktivitas yang sesuai dengan filter yang dipilih.
                    <?php else: ?>
                        Belum ada aktivitas yang tercatat.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../../assets/js/main.js"></script>

<style>
.avatar-xs {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
}
</style>

<?php
/**
 * Format role name for display
 * 
 * @param string $role Role name
 * @return string Formatted role name
 */
function formatRole($role) {
    switch ($role) {
        case 'admin':
            return 'Admin';
        case 'teacher':
            return 'Guru';
        case 'student':
            return 'Siswa';
        default:
            return ucfirst($role);
    }
}

/**
 * Get CSS class for role badge
 * 
 * @param string $role Role name
 * @return string CSS class
 */
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin':
            return 'danger';
        case 'teacher':
            return 'warning';
        case 'student':
            return 'success';
        default:
            return 'secondary';
    }
}

/**
 * Get Font Awesome icon for role
 * 
 * @param string $role Role name
 * @return string Icon name
 */
function getRoleIcon($role) {
    switch ($role) {
        case 'admin':
            return 'user-shield';
        case 'teacher':
            return 'chalkboard-teacher';
        case 'student':
            return 'user-graduate';
        default:
            return 'user';
    }
}
?>
</body>
</html>