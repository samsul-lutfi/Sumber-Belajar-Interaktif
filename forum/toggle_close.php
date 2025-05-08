<?php
/**
 * Toggle status tutup/buka sebuah topik forum
 * Hanya administrator yang dapat melakukan tutup/buka topik
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Cek login dan role admin
if (!isset($_SESSION['user_id']) || !is_admin()) {
    set_flash_message('Anda tidak memiliki izin untuk menutup/membuka topik.', 'warning');
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

// Tentukan status tutup baru (toggle)
$is_closed = !$topic['is_closed'];

// Update status tutup
try {
    $sql = "UPDATE forum_topics SET is_closed = ? WHERE id = ?";
    execute_query($sql, [$is_closed ? 1 : 0, $topic_id], "ii");
    
    // Log aktivitas
    log_activity($_SESSION['user_id'], 'toggle_close', 'forum_topic', $topic_id);
    
    // Set pesan sukses
    if ($is_closed) {
        set_flash_message('Topik berhasil ditutup.', 'success');
    } else {
        set_flash_message('Topik berhasil dibuka kembali.', 'success');
    }
} catch (Exception $e) {
    set_flash_message('Terjadi kesalahan: ' . $e->getMessage(), 'error');
}

// Kembali ke halaman topik
redirect('view.php?id=' . $topic_id);