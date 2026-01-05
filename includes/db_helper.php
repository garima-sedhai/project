<?php
class DBHelper {
    
    public static function checkTableExists($pdo, $tableName) {
        try {
            $result = $pdo->query("SELECT 1 FROM $tableName LIMIT 1");
            return true;
        } catch (Exception $e) {
            error_log("Table check failed for $tableName: " . $e->getMessage());
            return false;
        }
    }
    
    public static function checkColumnExists($pdo, $tableName, $columnName) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM $tableName LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Column check failed for $tableName.$columnName: " . $e->getMessage());
            return false;
        }
    }
    
    public static function safeQuery($pdo, $sql, $params = []) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            error_log("SQL: $sql");
            error_log("Params: " . print_r($params, true));
            return false;
        }
    }
    
    public static function insertWithValidation($pdo, $table, $data) {
        // Validate table exists
        if (!self::checkTableExists($pdo, $table)) {
            throw new Exception("Table '$table' does not exist");
        }
        
        // Build query
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            return $pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Insert error: " . $e->getMessage());
            throw new Exception("Database insert failed");
        }
    }
    
    public static function createNotification($pdo, $user_id, $title, $message, $type = 'system') {
        // Check if notifications table has required columns
        if (!self::checkTableExists($pdo, 'notifications')) {
            error_log("Notifications table does not exist");
            return false;
        }
        
        // Check for title column (which was missing)
        if (!self::checkColumnExists($pdo, 'notifications', 'title')) {
            error_log("Notifications table missing 'title' column");
            // Fallback: insert without title if column doesn't exist
            try {
                $sql = "INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([$user_id, $message, $type]);
            } catch (Exception $e) {
                error_log("Fallback notification failed: " . $e->getMessage());
                return false;
            }
        }
        
        // Standard insertion with all columns
        try {
            $sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$user_id, $title, $message, $type]);
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
}
?>