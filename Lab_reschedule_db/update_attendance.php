<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_id = (int)$_POST['attendance_id'];
    $new_status = $_POST['attendance_status'];
    $instructor_id = $_SESSION['user_id'];
    
    if (empty($attendance_id) || empty($new_status)) {
        $_SESSION['error'] = 'Invalid data provided!';
        header('Location: instructor_dashboard.php');
        exit();
    }
    
    try {
        // Verify this attendance record belongs to this instructor
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Attendance_Table WHERE Attendance_ID = ? AND Instructor_ID = ?");
        $stmt->execute([$attendance_id, $instructor_id]);
        
        if ($stmt->fetchColumn() == 0) {
            $_SESSION['error'] = 'You can only edit your own attendance records!';
            header('Location: instructor_dashboard.php');
            exit();
        }
        
        // Update attendance status
        $stmt = $pdo->prepare("UPDATE Attendance_Table SET Attendance_Status = ? WHERE Attendance_ID = ?");
        $stmt->execute([$new_status, $attendance_id]);
        
        $_SESSION['success'] = "Attendance status updated to {$new_status}!";
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
}

header('Location: instructor_dashboard.php');
exit();
?>
