<?php
/**
 * Database Connection Configuration
 * 
 * This file handles the connection to the database
 */

// For simplicity in this demo, we'll use an SQLite database that doesn't require server setup
$db_path = __DIR__ . '/../db/sumber_belajar.sqlite';
$db_dir = dirname($db_path);

// Ensure the db directory exists
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0777, true);
}

// Open the database connection
$conn = new SQLite3($db_path);

if (!$conn) {
    die("SQLite Connection failed: " . $conn->lastErrorMsg());
}

// Set global variable to identify DB type
$DB_TYPE = 'sqlite';

/**
 * Helper function to safely execute SQL queries with error handling for SQLite
 * 
 * @param string $sql SQL query to execute
 * @param array $params Parameters to bind to the query
 * @param string $types Types of the parameters (not used in SQLite but kept for compatibility)
 * @return mixed Statement object or false on failure
 */
function execute_query($sql, $params = [], $types = "") {
    global $conn;
    
    try {
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("Error preparing query: " . $conn->lastErrorMsg());
            return false;
        }
        
        // In SQLite3, we bind parameters by position (1-based indexing)
        if (!empty($params)) {
            foreach ($params as $i => $param) {
                $index = $i + 1; // SQLite parameters are 1-indexed
                
                if (is_int($param)) {
                    $stmt->bindValue($index, $param, SQLITE3_INTEGER);
                } elseif (is_float($param)) {
                    $stmt->bindValue($index, $param, SQLITE3_FLOAT);
                } elseif (is_null($param)) {
                    $stmt->bindValue($index, null, SQLITE3_NULL);
                } else {
                    $stmt->bindValue($index, $param, SQLITE3_TEXT);
                }
            }
        }
        
        $result = $stmt->execute();
        
        if ($result === false) {
            error_log("Error executing query: " . $conn->lastErrorMsg());
            return false;
        }
        
        return ['stmt' => $stmt, 'result' => $result];
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a single record from the database
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @param string $types Types of the parameters (not used in SQLite)
 * @return array|null Result row as associative array or null if no results
 */
function get_record($sql, $params = [], $types = "") {
    $query = execute_query($sql, $params, $types);
    
    if ($query === false) {
        return null;
    }
    
    $row = $query['result']->fetchArray(SQLITE3_ASSOC);
    $query['result']->finalize();
    
    return $row === false ? null : $row;
}

/**
 * Get multiple records from the database
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @param string $types Types of the parameters (not used in SQLite)
 * @return array Result rows as associative arrays
 */
function get_records($sql, $params = [], $types = "") {
    $query = execute_query($sql, $params, $types);
    
    if ($query === false) {
        return [];
    }
    
    $rows = [];
    while ($row = $query['result']->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    
    $query['result']->finalize();
    
    return $rows;
}
