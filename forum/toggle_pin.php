<?php
/**
 * Toggle status pin sebuah topik forum
 * Hanya administrator yang dapat melakukan pin/unpin topik
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Cek login dan role admin
if (!isset($_SESSION['user_id']) || !is_admin()) {
    set_flash_message('Anda tidak memiliki izin untuk melakukan pin/unpin topik.', 'warning');
    redirect('index.php');
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

// Tentukan status pin baru (toggle)
$is_pinned = !$topic['is_pinned'];

// Update status pin
try {
    $sql = "UPDATE forum_topics SET is_pinned = ? WHERE id = ?";
    execute_query($sql, [$is_pinned ? 1 : 0, $topic_id], "ii");
    
    // Log aktivitas
    log_activity($_SESSION['user_id'], 'toggle_pin', 'forum_topic', $topic_id);
    
    // Set pesan sukses
    if ($is_pinned) {
        set_flash_message('Topik berhasil dipin.', 'success');
    } else {
        set_flash_message('Pin topik berhasil dihapus.', 'success');
    }
} catch (Exception $e) {
    set_flash_message('Terjadi kesalahan: ' . $e->getMessage(), 'error');
}

// Kembali ke halaman topik
redirect('view.php?id=' . $topic_id);