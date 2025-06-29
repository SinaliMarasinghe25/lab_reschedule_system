<?php
$host = 'localhost';
$port = '3307';  // Add your custom port
$dbname = 'lab_rescheduling_db';
$username = 'root';  // Change if needed
$password = '';      // Change if needed

try {
    // Include port in the DSN connection string
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable event scheduler
    $pdo->exec("SET GLOBAL event_scheduler = ON");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to log all database changes
function logDatabaseChange($pdo, $table, $action, $record_id, $user_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, 'System', ?, ?, NOW())
        ");
        $stmt->execute([$user_id, "$action on $table", "Record ID: $record_id"]);
    } catch (Exception $e) {
        error_log("Failed to log database change: " . $e->getMessage());
    }
}

// Function to sync notifications
function syncNotifications($pdo) {
    try {
        // Call stored procedure to sync notifications
        $pdo->exec("CALL sp_sync_attendance_with_reschedule()");
    } catch (Exception $e) {
        error_log("Failed to sync notifications: " . $e->getMessage());
    }
}
?>
