<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
require_admin();

// Get filter parameters
$role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build the SQL query based on filters
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($role)) {
    $sql .= " AND role = ?";
    $params[] = $role;
    $types .= "s";
}

if (!empty($status)) {
    $active = ($status == 'active') ? 1 : 0;
    $sql .= " AND is_active = ?";
    $params[] = $active;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Sort options
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

switch ($sort) {
    case 'name':
        $sql .= " ORDER BY full_name ASC";
        break;
    case 'role':
        $sql .= " ORDER BY role ASC, full_name ASC";
        break;
    case 'activity':
        $sql .= " ORDER BY last_login DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

// Execute the query
$users = get_records($sql, $params, $types);

// Get counts for summary
$total_users = get_record("SELECT COUNT(*) as count FROM users")['count'];
$total_students = get_record("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'];
$total_teachers = get_record("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")['count'];
$total_admins = get_record("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'];

// Get activity data
$active_today = get_record(
    "SELECT COUNT(DISTINCT user_id) as count FROM activity_logs 
     WHERE DATE(created_at) = DATE('now')"
)['count'];

// Log the page view
log_activity($_SESSION['user_id'], 'view', 'admin_users');

// Handle user deletion if confirmed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $user_id = (int)$_POST['delete_user_id'];
    
    // Can't delete own account
    if ($user_id === $_SESSION['user_id']) {
        set_flash_message('Anda tidak dapat menghapus akun sendiri.', 'danger');
    } else {
        // Check if user exists
        $user = get_record("SELECT * FROM users WHERE id = ?", [$user_id], "i");
        
        if ($user) {
            // Delete associated data first (avoid foreign key constraints)
            // Delete activity logs
            execute_query("DELETE FROM activity_logs WHERE user_id = ?", [$user_id]);
            
            // Delete quiz results
            execute_query("DELETE FROM hasil_kuis WHERE user_id = ?", [$user_id]);
            
            // Delete forum replies
            execute_query("DELETE FROM forum_replies WHERE user_id = ?", [$user_id]);
            
            // Delete forum topics
            execute_query("DELETE FROM forum_topics WHERE user_id = ?", [$user_id]);
            
            // Finally delete the user
            $result = execute_query("DELETE FROM users WHERE id = ?", [$user_id]);
            
            if ($result) {
                log_activity($_SESSION['user_id'], 'delete', 'user');
                set_flash_message("Pengguna '{$user['full_name']}' berhasil dihapus.", 'success');
                redirect('index.php');
            } else {
                set_flash_message('Gagal menghapus pengguna: ' . $stmt->error, 'danger');
            }
        } else {
            set_flash_message('Pengguna tidak ditemukan.', 'danger');
        }
    }
}

// Handle user activation/deactivation
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = (int)$_GET['id'];
    
    // Can't deactivate own account
    if ($user_id === $_SESSION['user_id']) {
        set_flash_message('Anda tidak dapat mengubah status akun sendiri.', 'danger');
    } else {
        if ($action === 'activate') {
            $result = execute_query("UPDATE users SET is_active = 1 WHERE id = ?", [$user_id]);
            
            if ($result) {
                log_activity($_SESSION['user_id'], 'edit', 'user_activate');
                set_flash_message('Pengguna berhasil diaktifkan.', 'success');
                redirect('index.php');
            } else {
                set_flash_message('Gagal mengaktifkan pengguna.', 'danger');
            }
        } else if ($action === 'deactivate') {
            $result = execute_query("UPDATE users SET is_active = 0 WHERE id = ?", [$user_id]);
            
            if ($result) {
                log_activity($_SESSION['user_id'], 'edit', 'user_deactivate');
                set_flash_message('Pengguna berhasil dinonaktifkan.', 'success');
                redirect('index.php');
            } else {
                set_flash_message('Gagal menonaktifkan pengguna.', 'danger');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Admin Dashboard</title>
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
                <i class="fas fa-users me-2"></i>Manajemen Pengguna
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="../../dashboard/admin.php">Dashboard Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Manajemen Pengguna</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Tambah Pengguna
            </a>
        </div>
    </div>
    
    <!-- User Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-users text-primary"></i>
                <div class="stat-value"><?= $total_users ?></div>
                <p class="stat-label">Total Pengguna</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-user-graduate text-success"></i>
                <div class="stat-value"><?= $total_students ?></div>
                <p class="stat-label">Siswa</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-chalkboard-teacher text-warning"></i>
                <div class="stat-value"><?= $total_teachers ?></div>
                <p class="stat-label">Guru</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stats text-center">
                <i class="fas fa-user-shield text-danger"></i>
                <div class="stat-value"><?= $total_admins ?></div>
                <p class="stat-label">Admin</p>
            </div>
        </div>
    </div>
    
    <!-- User Management Navigation -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap gap-2">
                    <a href="index.php" class="btn <?= empty($role) ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-users me-2"></i>Semua Pengguna
                    </a>
                    <a href="index.php?role=student" class="btn <?= $role === 'student' ? 'btn-success' : 'btn-outline-success' ?>">
                        <i class="fas fa-user-graduate me-2"></i>Siswa
                    </a>
                    <a href="index.php?role=teacher" class="btn <?= $role === 'teacher' ? 'btn-warning text-dark' : 'btn-outline-warning' ?>">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Guru
                    </a>
                    <a href="index.php?role=admin" class="btn <?= $role === 'admin' ? 'btn-danger' : 'btn-outline-danger' ?>">
                        <i class="fas fa-user-shield me-2"></i>Admin
                    </a>
                    <a href="activities.php" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i>Log Aktivitas
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="index.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Cari Pengguna</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Nama, username, atau email..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Filter Peran</label>
                    <select class="form-select" id="role" name="role" onchange="this.form.submit()">
                        <option value="">Semua Peran</option>
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Siswa</option>
                        <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Guru</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status Akun</label>
                    <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Tidak Aktif</option>
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
            </form>
        </div>
    </div>
    
    <!-- Users List -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Daftar Pengguna
            </h5>
            <span class="badge bg-primary"><?= count($users) ?> pengguna</span>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Peran</th>
                                <th>Status</th>
                                <th>Terdaftar</th>
                                <th>Login Terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-<?= getRoleBadgeClass($user['role']) ?> text-white me-2">
                                                <i class="fas fa-<?= getRoleIcon($user['role']) ?>"></i>
                                            </div>
                                            <?= htmlspecialchars($user['full_name']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= getRoleBadgeClass($user['role']) ?>">
                                            <?= formatRole($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= isset($user['is_active']) && $user['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= isset($user['is_active']) && $user['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                                        </span>
                                    </td>
                                    <td><?= format_date($user['created_at'], false) ?></td>
                                    <td><?= isset($user['last_login']) && $user['last_login'] ? format_date($user['last_login'], false) : '<span class="text-muted">Belum pernah</span>' ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info text-white" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <?php if (isset($user['is_active']) && $user['is_active']): ?>
                                                    <a href="index.php?action=deactivate&id=<?= $user['id'] ?>" 
                                                      class="btn btn-sm btn-warning" 
                                                      title="Nonaktifkan"
                                                      onclick="return confirm('Apakah Anda yakin ingin menonaktifkan pengguna ini?')">
                                                        <i class="fas fa-user-slash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="index.php?action=activate&id=<?= $user['id'] ?>" 
                                                      class="btn btn-sm btn-success" 
                                                      title="Aktifkan"
                                                      onclick="return confirm('Apakah Anda yakin ingin mengaktifkan pengguna ini?')">
                                                        <i class="fas fa-user-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        title="Hapus" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?= $user['id'] ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Delete confirmation modal -->
                                        <div class="modal fade" id="deleteModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $user['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?= $user['id'] ?>">Konfirmasi Hapus Pengguna</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Apakah Anda yakin ingin menghapus pengguna <strong><?= htmlspecialchars($user['full_name']) ?></strong>?</p>
                                                        <div class="alert alert-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            <strong>Peringatan:</strong> Tindakan ini akan menghapus semua data yang terkait dengan pengguna ini, termasuk hasil kuis, aktivitas forum, dan log aktivitas. Tindakan ini tidak dapat dibatalkan.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <form action="index.php" method="POST">
                                                            <input type="hidden" name="delete_user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" class="btn btn-danger">Hapus Pengguna</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php if (!empty($search) || !empty($role) || !empty($status)): ?>
                        Tidak ada pengguna yang sesuai dengan filter yang dipilih.
                    <?php else: ?>
                        Belum ada pengguna yang terdaftar.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Password Reset Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-key me-2"></i>Reset Password
            </h5>
        </div>
        <div class="card-body">
            <p>Gunakan fitur ini untuk mereset password pengguna jika mereka melupakan atau kehilangan akses ke akun mereka.</p>
            <a href="reset_password.php" class="btn btn-warning">
                <i class="fas fa-unlock-alt me-2"></i>Reset Password Pengguna
            </a>
        </div>
    </div>
    
</main>

<?php include '../../includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../../assets/js/main.js"></script>

<style>
.avatar-sm {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
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