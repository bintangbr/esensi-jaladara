<?php
/**
 * ========================================
 * DATABASE CONFIGURATION
 * ========================================
 * File konfigurasi koneksi database MySQL
 */

// Set timezone to Indonesia (UTC+7)
date_default_timezone_set('Asia/Jakarta');

// Database credentials
define('DB_HOST', '103.190.113.20');
define('DB_USER', 'esensi-bnt');
define('DB_PASSWORD', 'AsuKabeh123**');
define('DB_NAME', 'esensi-bnt');
define('DB_PORT', 3306);

// Create connection using mysqli
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Set timezone to Asia/Jakarta (UTC+7)
// Try multiple approaches to ensure timezone is set correctly
$conn->query("SET time_zone = '+07:00'");
$conn->query("SET SESSION time_zone = '+07:00'");

// Also set at query level using MySQL function
// This ensures consistency even if server timezone is UTC

// ========================================
// AUTO MIGRATION: Add WhatsApp column jika tidak ada
// ========================================
$sql_check = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='whatsapp_number'";
$result_check = $conn->query($sql_check);

if ($result_check && $result_check->num_rows === 0) {
    $sql_alter = "ALTER TABLE users ADD COLUMN whatsapp_number VARCHAR(20) DEFAULT NULL AFTER email";
    $conn->query($sql_alter);
}

// ========================================
// AUTO MIGRATION: Ensure WhatsApp settings exist
// ========================================
$settings_to_create = [
    ['whatsapp_api_key', ''],
    ['whatsapp_sender', ''],
    ['hrd_number', '6282225275438']
];

foreach ($settings_to_create as $setting) {
    $nama_setting = $setting[0];
    $default_value = $setting[1];
    
    // Check if setting exists
    $check_query = "SELECT id FROM settings WHERE nama_setting = '$nama_setting'";
    $check_result = $conn->query($check_query);
    
    // If not exists, insert it
    if ($check_result && $check_result->num_rows === 0) {
        $insert_query = "INSERT INTO settings (nama_setting, value) VALUES ('$nama_setting', '$default_value')";
        $conn->query($insert_query);
    }
}

// ========================================
// HELPER FUNCTION: Prepared Statements
// ========================================

/**
 * Execute query dengan prepared statement
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query dengan placeholder ?
 * @param array $params Parameter values
 * @param string $types Tipe data parameter (s=string, i=int, d=double, b=blob)
 * @return mixed Result if success, false if error
 */
function executeQuery($conn, $query, $params = [], $types = '') {
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    if (!empty($params)) {
        if (!$stmt->bind_param($types, ...$params)) {
            throw new Exception('Bind param failed: ' . $stmt->error);
        }
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    return $stmt;
}

/**
 * Get single row dari database
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query
 * @param array $params Parameter values
 * @param string $types Tipe data parameter
 * @return array|null Single row as associative array or null
 */
function getRow($conn, $query, $params = [], $types = '') {
    try {
        $stmt = executeQuery($conn, $query, $params, $types);
        
        if (!$stmt) {
            return null;
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row;
    } catch (Exception $e) {
        throw new Exception('Get row failed: ' . $e->getMessage());
    }
}

/**
 * Get multiple rows dari database
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query
 * @param array $params Parameter values
 * @param string $types Tipe data parameter
 * @return array Array of rows
 */
function getRows($conn, $query, $params = [], $types = '') {
    try {
        $stmt = executeQuery($conn, $query, $params, $types);
        
        if (!$stmt) {
            return [];
        }
        
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        
        return $rows;
    } catch (Exception $e) {
        throw new Exception('Get rows failed: ' . $e->getMessage());
    }
}

/**
 * Insert/Update/Delete query
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query
 * @param array $params Parameter values
 * @param string $types Tipe data parameter
 * @return bool True if success, false if error
 */
function executeModifyQuery($conn, $query, $params = [], $types = '') {
    try {
        $stmt = executeQuery($conn, $query, $params, $types);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->close();
        return true;
    } catch (Exception $e) {
        throw new Exception('Query execution failed: ' . $e->getMessage());
    }
}

/**
 * Get last insert ID
 * 
 * @param mysqli $conn Database connection
 * @return int Last insert ID
 */
function getLastInsertId($conn) {
    return $conn->insert_id;
}

/**
 * Get affected rows
 * 
 * @param mysqli $conn Database connection
 * @return int Number of affected rows
 */
function getAffectedRows($conn) {
    return $conn->affected_rows;
}

// ========================================
// GLOBAL DATABASE VARIABLE
// ========================================
// $conn siap digunakan di semua file yang include config/database.php