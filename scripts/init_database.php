<?php
/**
 * Database Initialization Script
 * 
 * This script creates the necessary tables in the SQLite database
 */

require_once '../config/database.php';

// Define table creation SQL queries
$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'student')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'materi' => "CREATE TABLE IF NOT EXISTS materi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL,
        kategori TEXT NOT NULL,
        deskripsi TEXT NOT NULL,
        konten TEXT,
        thumbnail_url TEXT,
        video_url TEXT,
        user_id INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    'materi_files' => "CREATE TABLE IF NOT EXISTS materi_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        materi_id INTEGER NOT NULL,
        filename TEXT NOT NULL,
        filepath TEXT NOT NULL,
        filesize INTEGER NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (materi_id) REFERENCES materi(id) ON DELETE CASCADE
    )",
    
    'quiz' => "CREATE TABLE IF NOT EXISTS quiz (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL,
        kategori TEXT NOT NULL,
        deskripsi TEXT NOT NULL,
        durasi INTEGER NOT NULL,
        materi_id INTEGER,
        user_id INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (materi_id) REFERENCES materi(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    'quiz_questions' => "CREATE TABLE IF NOT EXISTS quiz_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        pertanyaan TEXT NOT NULL,
        pilihan_a TEXT NOT NULL,
        pilihan_b TEXT NOT NULL,
        pilihan_c TEXT,
        pilihan_d TEXT,
        pilihan_e TEXT,
        jawaban_benar TEXT NOT NULL CHECK(jawaban_benar IN ('a', 'b', 'c', 'd', 'e')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quiz(id) ON DELETE CASCADE
    )",
    
    'hasil_kuis' => "CREATE TABLE IF NOT EXISTS hasil_kuis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        quiz_id INTEGER NOT NULL,
        score REAL NOT NULL,
        jawaban TEXT NOT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (quiz_id) REFERENCES quiz(id)
    )",
    
    'forum_topics' => "CREATE TABLE IF NOT EXISTS forum_topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        kategori TEXT NOT NULL,
        materi_id INTEGER,
        is_pinned INTEGER DEFAULT 0,
        is_closed INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (materi_id) REFERENCES materi(id)
    )",
    
    'forum_replies' => "CREATE TABLE IF NOT EXISTS forum_replies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        topic_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    'activity_logs' => "CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        module TEXT NOT NULL,
        entity_id INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )"
];

// Create tables
$success = true;
foreach ($tables as $table_name => $create_query) {
    if (!$conn->exec($create_query)) {
        echo "Error creating table '$table_name': " . $conn->lastErrorMsg() . "\n";
        $success = false;
    } else {
        echo "Table '$table_name' created or already exists\n";
    }
}

// Create a default admin user if none exists
$admin_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$admin_exists = $admin_check->fetchArray(SQLITE3_ASSOC)['count'] > 0;

if (!$admin_exists) {
    // Add default admin user (username: admin, password: admin123)
    $default_admin = [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'email' => 'admin@example.com',
        'full_name' => 'Administrator',
        'role' => 'admin'
    ];
    
    $admin_query = "INSERT INTO users (username, password, email, full_name, role) 
                   VALUES (:username, :password, :email, :full_name, :role)";
    
    $stmt = $conn->prepare($admin_query);
    $stmt->bindValue(':username', $default_admin['username'], SQLITE3_TEXT);
    $stmt->bindValue(':password', $default_admin['password'], SQLITE3_TEXT);
    $stmt->bindValue(':email', $default_admin['email'], SQLITE3_TEXT);
    $stmt->bindValue(':full_name', $default_admin['full_name'], SQLITE3_TEXT);
    $stmt->bindValue(':role', $default_admin['role'], SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        echo "Default admin user created (username: admin, password: admin123)\n";
    } else {
        echo "Error creating default admin user: " . $conn->lastErrorMsg() . "\n";
        $success = false;
    }
}

// Sample data for demonstration purposes
if ($success) {
    // Sample categories function (moved from functions.php)
    function get_categories() {
        return [
            'buku' => 'Buku',
            'video' => 'Video',
            'jurnal' => 'Jurnal',
            'internet' => 'Internet',
            'alam' => 'Alam'
        ];
    }
    
    // Check if we already have sample data
    $material_check = $conn->query("SELECT COUNT(*) as count FROM materi");
    $has_materials = $material_check->fetchArray(SQLITE3_ASSOC)['count'] > 0;
    
    if (!$has_materials) {
        // Add sample learning materials
        $sample_materials = [
            [
                'judul' => 'Pengantar Matematika Dasar',
                'kategori' => 'buku',
                'deskripsi' => 'Materi dasar untuk memahami konsep matematika tingkat sekolah menengah.',
                'konten' => "# Matematika Dasar\n\nMatematika adalah ilmu yang mempelajari besaran, struktur, ruang, dan perubahan. Para matematikawan mencari pola, merumuskan dugaan baru, dan membuktikan kebenaran dengan deduksi yang ketat dari aksioma dan definisi yang dipilih dengan tepat.\n\n## Aljabar Dasar\n\nAljabar adalah cabang matematika yang mempelajari simbol dan aturan untuk memanipulasi simbol-simbol tersebut. Aljabar dasar mencakup:\n\n1. Persamaan linear\n2. Sistem persamaan\n3. Persamaan kuadrat\n\n## Geometri\n\nGeometri adalah cabang matematika yang berkaitan dengan pertanyaan tentang ukuran, bentuk, posisi relatif gambar, dan sifat ruang.",
                'user_id' => 1
            ],
            [
                'judul' => 'Tutorial Bahasa Inggris untuk Pemula',
                'kategori' => 'video',
                'deskripsi' => 'Video pembelajaran bahasa Inggris untuk pemula dengan penjelasan tentang tata bahasa dasar dan percakapan sehari-hari.',
                'konten' => "# Belajar Bahasa Inggris untuk Pemula\n\nMaterial ini berisi dasar-dasar untuk memulai belajar bahasa Inggris, mulai dari pengenalan vocabulary hingga simple conversation.\n\n## Grammar Dasar\n\n- Simple Present Tense\n- Simple Past Tense\n- Future Tense\n\n## Percakapan Sehari-hari\n\n- Greetings (Salam)\n- Introducing Yourself (Memperkenalkan Diri)\n- Asking and Giving Directions (Bertanya dan Memberi Petunjuk Arah)",
                'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'user_id' => 1
            ],
            [
                'judul' => 'Penelitian Ilmiah: Perubahan Iklim',
                'kategori' => 'jurnal',
                'deskripsi' => 'Jurnal penelitian tentang dampak perubahan iklim terhadap lingkungan dan kehidupan manusia.',
                'konten' => "# Dampak Perubahan Iklim\n\nPerubahan iklim merupakan salah satu tantangan terbesar yang dihadapi oleh umat manusia saat ini. Jurnal ini membahas berbagai aspek perubahan iklim dan dampaknya.\n\n## Penyebab Perubahan Iklim\n\n- Emisi gas rumah kaca\n- Deforestasi\n- Aktivitas industri\n\n## Dampak Terhadap Lingkungan\n\n- Mencairnya es di kutub\n- Kenaikan permukaan air laut\n- Perubahan pola cuaca\n- Kepunahan spesies\n\n## Solusi Potensial\n\n- Energi terbarukan\n- Konservasi hutan\n- Pengurangan emisi karbon",
                'user_id' => 1
            ]
        ];
        
        foreach ($sample_materials as $material) {
            $material_query = "INSERT INTO materi (judul, kategori, deskripsi, konten, video_url, user_id) 
                              VALUES (:judul, :kategori, :deskripsi, :konten, :video_url, :user_id)";
            
            $stmt = $conn->prepare($material_query);
            $stmt->bindValue(':judul', $material['judul'], SQLITE3_TEXT);
            $stmt->bindValue(':kategori', $material['kategori'], SQLITE3_TEXT);
            $stmt->bindValue(':deskripsi', $material['deskripsi'], SQLITE3_TEXT);
            $stmt->bindValue(':konten', $material['konten'], SQLITE3_TEXT);
            $stmt->bindValue(':video_url', $material['video_url'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $material['user_id'], SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                echo "Sample material '{$material['judul']}' added\n";
            } else {
                echo "Error adding sample material: " . $conn->lastErrorMsg() . "\n";
            }
        }
        
        // Add sample quiz
        $quiz_query = "INSERT INTO quiz (judul, kategori, deskripsi, durasi, materi_id, user_id) 
                      VALUES ('Kuis Matematika Dasar', 'buku', 'Tes pemahaman konsep dasar matematika', 30, 1, 1)";
        
        if ($conn->exec($quiz_query)) {
            $quiz_id = $conn->lastInsertRowID();
            echo "Sample quiz added\n";
            
            // Add sample questions
            $sample_questions = [
                [
                    'pertanyaan' => 'Berapakah hasil dari 5 × (3 + 2)?',
                    'pilihan_a' => '15',
                    'pilihan_b' => '25',
                    'pilihan_c' => '10',
                    'pilihan_d' => '20',
                    'jawaban_benar' => 'b'
                ],
                [
                    'pertanyaan' => 'Berapa akar kuadrat dari 81?',
                    'pilihan_a' => '8',
                    'pilihan_b' => '9',
                    'pilihan_c' => '10',
                    'pilihan_d' => '7',
                    'jawaban_benar' => 'b'
                ],
                [
                    'pertanyaan' => 'Jika x + 5 = 12, maka x = ?',
                    'pilihan_a' => '5',
                    'pilihan_b' => '6',
                    'pilihan_c' => '7',
                    'pilihan_d' => '8',
                    'jawaban_benar' => 'c'
                ]
            ];
            
            foreach ($sample_questions as $question) {
                $question_query = "INSERT INTO quiz_questions (quiz_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar) 
                                  VALUES (:quiz_id, :pertanyaan, :pilihan_a, :pilihan_b, :pilihan_c, :pilihan_d, :jawaban_benar)";
                
                $stmt = $conn->prepare($question_query);
                $stmt->bindValue(':quiz_id', $quiz_id, SQLITE3_INTEGER);
                $stmt->bindValue(':pertanyaan', $question['pertanyaan'], SQLITE3_TEXT);
                $stmt->bindValue(':pilihan_a', $question['pilihan_a'], SQLITE3_TEXT);
                $stmt->bindValue(':pilihan_b', $question['pilihan_b'], SQLITE3_TEXT);
                $stmt->bindValue(':pilihan_c', $question['pilihan_c'], SQLITE3_TEXT);
                $stmt->bindValue(':pilihan_d', $question['pilihan_d'], SQLITE3_TEXT);
                $stmt->bindValue(':jawaban_benar', $question['jawaban_benar'], SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    echo "Sample question added\n";
                } else {
                    echo "Error adding sample question: " . $conn->lastErrorMsg() . "\n";
                }
            }
        } else {
            echo "Error adding sample quiz: " . $conn->lastErrorMsg() . "\n";
        }
        
        // Add sample forum topic
        $forum_query = "INSERT INTO forum_topics (title, content, user_id, kategori, materi_id) 
                       VALUES ('Pertanyaan tentang Aljabar Dasar', 'Bagaimana cara menyelesaikan persamaan kuadrat secara mudah?', 1, 'buku', 1)";
        
        if ($conn->exec($forum_query)) {
            $topic_id = $conn->lastInsertRowID();
            echo "Sample forum topic added\n";
            
            // Add sample forum reply
            $reply_query = "INSERT INTO forum_replies (topic_id, user_id, content) 
                           VALUES ($topic_id, 1, 'Persamaan kuadrat dapat diselesaikan dengan rumus abc: x = (-b ± √(b² - 4ac)) / 2a dimana ax² + bx + c = 0')";
            
            if ($conn->exec($reply_query)) {
                echo "Sample forum reply added\n";
            } else {
                echo "Error adding sample forum reply: " . $conn->lastErrorMsg() . "\n";
            }
        } else {
            echo "Error adding sample forum topic: " . $conn->lastErrorMsg() . "\n";
        }
    } else {
        echo "Sample data already exists - skipping\n";
    }
}

echo "\nDatabase initialization " . ($success ? "completed successfully" : "failed") . "\n";
?>