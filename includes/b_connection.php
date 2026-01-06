<?php
/**
 * Database Connection File
 * This file ensures $pdo is available globally
 */

// Check if config is already loaded
if (!isset($pdo)) {
    require_once 'config.php';
}

// Function to get database connection
function getDBConnection() {
    global $pdo;
    if (!$pdo) {
        require_once 'config.php';
    }
    return $pdo;
}

// Function to safely execute queries
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        return false;
    }
}

// Function to get last inserted ID
function getLastInsertId() {
    $pdo = getDBConnection();
    return $pdo->lastInsertId();
}

// Helper function for fetching single row
function fetchSingle($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

// Helper function for fetching all rows
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : false;
}

// Helper function for checking if record exists
function recordExists($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? ($stmt->rowCount() > 0) : false;
}

// Close database connection (usually not needed for PDO)
function closeConnection() {
    global $pdo;
    $pdo = null;
}
?>