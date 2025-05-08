<?php
/**
 * Common Functions
 * 
 * This file contains common utility functions used throughout the application
 */

/**
 * Sanitize data to prevent XSS attacks
 * 
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirect to another page
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set a flash message to be displayed on the next page
 * 
 * @param string $message Message to display
 * @param string $type Message type (success, danger, warning, info)
 */
function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Display flash message if exists and clear it
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
        echo $message;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

/**
 * Check if user is logged in
 * 
 * @return boolean True if user is logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 * 
 * @return boolean True if user is admin, false otherwise
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require user to be logged in, redirects to login page if not
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('Silakan login terlebih dahulu.', 'warning');
        redirect('/auth/login.php');
    }
}

/**
 * Require user to be admin, redirects to dashboard if not
 */
function require_admin() {
    require_login();
    
    if (!is_admin()) {
        set_flash_message('Anda tidak memiliki akses untuk halaman ini.', 'danger');
        redirect('/dashboard/student.php');
    }
}

/**
 * Format date to Indonesian format
 * 
 * @param string $date Date to format
 * @param boolean $include_time Whether to include time in the output
 * @return string Formatted date
 */
function format_date($date, $include_time = true) {
    $date_obj = new DateTime($date);
    $format = $include_time ? 'd F Y, H:i' : 'd F Y';
    
    // Convert month to Indonesian
    $months = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];
    
    $formatted = $date_obj->format($format);
    
    foreach ($months as $en => $id) {
        $formatted = str_replace($en, $id, $formatted);
    }
    
    return $formatted;
}

/**
 * Calculate the time elapsed since the given date
 * 
 * @param string $datetime Date and time
 * @param boolean $full Show full date
 * @return string Elapsed time in human readable format
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}

/**
 * Log user activity
 * 
 * @param int $user_id User ID
 * @param string $action Action (view, add, edit, delete, etc.)
 * @param string $module Module (materi, quiz, forum, etc.)
 * @param int $entity_id Entity ID (optional)
 */
function log_activity($user_id, $action, $module, $entity_id = null) {
    global $conn;
    
    $sql = "INSERT INTO activity_logs (user_id, action, module, entity_id) VALUES (?, ?, ?, ?)";
    execute_query($sql, [$user_id, $action, $module, $entity_id], "issi");
}

/**
 * Get categories
 * 
 * @return array Array of categories
 */
function get_categories() {
    return [
        'buku' => 'Buku',
        'video' => 'Video',
        'jurnal' => 'Jurnal',
        'internet' => 'Internet',
        'alam' => 'Alam'
    ];
}

/**
 * Format file size to human readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}

/**
 * Get file extension
 * 
 * @param string $filename Filename
 * @return string File extension
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file extension is allowed
 * 
 * @param string $extension File extension
 * @return boolean True if allowed, false otherwise
 */
function is_allowed_extension($extension) {
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif'];
    return in_array(strtolower($extension), $allowed_extensions);
}

/**
 * Generate a download token for file
 * 
 * @param int $file_id File ID
 * @param string $filename Filename
 * @return string Download token
 */
function generate_download_token($file_id, $filename) {
    return md5($file_id . $filename . $_SESSION['user_id'] . date('Ymd'));
}

/**
 * Calculate quiz score
 * 
 * @param array $user_answers User's answers
 * @param array $correct_answers Correct answers
 * @return float Score (0-100)
 */
function calculate_quiz_score($user_answers, $correct_answers) {
    $total_questions = count($correct_answers);
    if ($total_questions === 0) {
        return 0;
    }
    
    $correct_count = 0;
    foreach ($user_answers as $question_id => $answer) {
        if (isset($correct_answers[$question_id]) && $answer === $correct_answers[$question_id]) {
            $correct_count++;
        }
    }
    
    return round(($correct_count / $total_questions) * 100, 1);
}

/**
 * Get score badge class based on score
 * 
 * @param float $score Score
 * @return string Badge class
 */
function getScoreBadgeClass($score) {
    if ($score >= 80) {
        return 'bg-success';
    } elseif ($score >= 60) {
        return 'bg-primary';
    } elseif ($score >= 40) {
        return 'bg-warning';
    } else {
        return 'bg-danger';
    }
}

/**
 * Get score color class based on score
 * 
 * @param float $score Score
 * @return string Color class
 */
function getScoreColorClass($score) {
    if ($score >= 80) {
        return 'success';
    } elseif ($score >= 60) {
        return 'primary';
    } elseif ($score >= 40) {
        return 'warning';
    } else {
        return 'danger';
    }
}

/**
 * Get score message based on score
 * 
 * @param float $score Score
 * @return string Message
 */
function getScoreMessage($score) {
    if ($score >= 80) {
        return 'Sangat Baik! Anda telah menguasai materi dengan baik.';
    } elseif ($score >= 60) {
        return 'Baik. Anda memahami sebagian besar materi.';
    } elseif ($score >= 40) {
        return 'Cukup. Masih ada beberapa bagian materi yang perlu dipelajari lagi.';
    } else {
        return 'Perlu belajar lebih banyak. Silakan pelajari kembali materinya.';
    }
}

/**
 * Truncate text to specific length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to append if truncated
 * @return string Truncated text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Count users by role
 * 
 * @param string $role Role to count (admin, student, etc)
 * @return int Number of users with the specified role
 */
function count_users($role = null) {
    global $conn;
    
    if ($role) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE role = ?";
        $params = [$role];
        $types = "s";
    } else {
        $sql = "SELECT COUNT(*) as count FROM users";
        $params = [];
        $types = "";
    }
    
    $result = get_record($sql, $params, $types);
    return $result['count'] ?? 0;
}

/**
 * Get user quiz statistics
 * 
 * @param int $user_id User ID
 * @return array Quiz statistics for the user
 */
function get_user_quiz_stats($user_id) {
    $sql = "SELECT 
                COUNT(DISTINCT qr.quiz_id) as total_quizzes,
                SUM(CASE WHEN qr.status = 'completed' THEN 1 ELSE 0 END) as completed_quizzes,
                AVG(qr.score) as average_score,
                MAX(qr.score) as highest_score
            FROM quiz_results qr
            WHERE qr.user_id = ?";
    
    $stats = get_record($sql, [$user_id], "i");
    
    // Set defaults for empty results
    return [
        'total_quizzes' => $stats['total_quizzes'] ?? 0,
        'completed_quizzes' => $stats['completed_quizzes'] ?? 0,
        'average_score' => $stats['average_score'] ?? 0,
        'highest_score' => $stats['highest_score'] ?? 0,
    ];
}

/**
 * Get user forum activity
 * 
 * @param int $user_id User ID
 * @return array Forum activity statistics for the user
 */
function get_user_forum_activity($user_id) {
    $sql = "SELECT 
                COUNT(DISTINCT f.id) as total_topics,
                (SELECT COUNT(*) FROM forum_replies WHERE user_id = ?) as total_comments
            FROM forum_topics f
            WHERE f.user_id = ?";
    
    $stats = get_record($sql, [$user_id, $user_id], "ii");
    
    // Set defaults for empty results
    return [
        'total_topics' => $stats['total_topics'] ?? 0,
        'total_comments' => $stats['total_comments'] ?? 0,
    ];
}

/**
 * Get count of records from a SQL query
 * 
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @param string $types Parameter types
 * @return int Count of records
 */
function count_records($sql, $params = [], $types = "") {
    $result = get_record($sql, $params, $types);
    
    if (isset($result['count'])) {
        return $result['count'];
    }
    
    return 0;
}

/**
 * Get appropriate CSS class for a score
 * 
 * @param float $score Score value
 * @return string CSS class for the score
 */
function get_score_badge_class($score) {
    if ($score >= 90) {
        return 'bg-success';
    } elseif ($score >= 70) {
        return 'bg-primary';
    } elseif ($score >= 60) {
        return 'bg-info';
    } elseif ($score >= 50) {
        return 'bg-warning text-dark';
    } else {
        return 'bg-danger';
    }
}
?>