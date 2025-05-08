<?php
/**
 * File untuk menghapus balasan di forum
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Cek login
if (!is_logged_in()) {
    set_flash_message('Anda harus login untuk menghapus balasan.', 'warning');
    redirect('index.php');
}

// Ambil ID balasan
$reply_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reply_id) {
    set_flash_message('ID balasan tidak valid.', 'error');
    redirect('index.php');
}

// Ambil data balasan
$sql = "SELECT * FROM forum_replies WHERE id = ?";
$reply = get_record($sql, [$reply_id], "i");

// Jika balasan tidak ditemukan
if (!$reply) {
    set_flash_message('Balasan tidak ditemukan.', 'error');
    redirect('index.php');
}

// Cek apakah pengguna adalah pemilik balasan atau admin
if ($_SESSION['user_id'] != $reply['user_id'] && !is_admin()) {
    set_flash_message('Anda tidak memiliki izin untuk menghapus balasan ini.', 'warning');
    redirect('view.php?id=' . $reply['forum_id']);
}

try {
    // Mulai transaksi
    execute_query("BEGIN TRANSACTION");
    
    // Hapus balasan
    $sql = "DELETE FROM forum_replies WHERE id = ?";
    execute_query($sql, [$reply_id], "i");
    
    // Log aktivitas
    log_activity($_SESSION['user_id'], 'delete', 'forum_reply', $reply_id);
    
    // Commit transaksi
    execute_query("COMMIT");
    
    set_flash_message('Balasan berhasil dihapus.', 'success');
} catch (Exception $e) {
    // Rollback jika terjadi kesalahan
    execute_query("ROLLBACK");
    set_flash_message('Gagal menghapus balasan: ' . $e->getMessage(), 'danger');
}

// Kembali ke halaman topik
redirect('view.php?id=' . $reply['forum_id']);