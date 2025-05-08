<?php
/**
 * Hapus topik forum
 * Hanya administrator atau pemilik topik yang dapat menghapus.
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    set_flash_message('Anda harus login untuk menghapus topik forum.', 'warning');
    redirect('/auth/login.php');
}

// Cek ID topik
$topic_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$topic_id) {
    set_flash_message('ID topik tidak valid.', 'error');
    redirect('index.php');
}

// Dapatkan data topik
$sql = "SELECT * FROM forum_topics WHERE id = ?";
$topic = get_record($sql, [$topic_id], "i");

if (!$topic) {
    set_flash_message('Topik tidak ditemukan.', 'error');
    redirect('index.php');
}

// Cek apakah pengguna memiliki hak untuk menghapus topik
if ($_SESSION['user_id'] != $topic['user_id'] && !is_admin()) {
    set_flash_message('Anda tidak memiliki hak untuk menghapus topik ini.', 'error');
    redirect('index.php');
}

// Proses penghapusan dengan transaksi
try {
    // Mulai transaksi
    $conn->exec('BEGIN TRANSACTION');
    
    // Hapus semua balasan terlebih dahulu
    $sql_delete_replies = "DELETE FROM forum_replies WHERE forum_id = ?";
    execute_query($sql_delete_replies, [$topic_id], "i");
    
    // Hapus topik
    $sql_delete_topic = "DELETE FROM forum_topics WHERE id = ?";
    execute_query($sql_delete_topic, [$topic_id], "i");
    
    // Log aktivitas
    log_activity($_SESSION['user_id'], 'delete', 'forum_topic', $topic_id);
    
    // Commit transaksi
    $conn->exec('COMMIT');
    
    // Set pesan sukses
    set_flash_message('Topik forum berhasil dihapus.', 'success');
    
} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    $conn->exec('ROLLBACK');
    
    // Set pesan error
    set_flash_message('Terjadi kesalahan saat menghapus topik forum. ' . $e->getMessage(), 'error');
}

// Redirect ke halaman daftar forum
redirect('index.php');