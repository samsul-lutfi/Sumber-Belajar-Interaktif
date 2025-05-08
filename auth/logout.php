<?php
session_start();
require_once '../includes/functions.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    require_once '../config/database.php';
    log_activity($_SESSION['user_id'], 'logout', 'auth');
    
    // Destroy the session
    session_unset();
    session_destroy();
    
    // Set success message
    session_start();
    set_flash_message('Anda telah berhasil logout.', 'success');
}

// Redirect to home page
redirect('/index.php');
