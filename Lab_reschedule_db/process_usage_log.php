<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instructor_id = $_SESSION['user_id'];
    $lab_name = trim($_POST['lab_name']);
    $group_no = (int)$_POST['group_no'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    
    // Validate input
    if (empty($lab_name) || empty($group_no) || empty($date) || empty($time)) {
        $_SESSION['error'] = 'All fields are required!';
        header('Location: instructor_dashboard.php');
        exit();
    }
    
    // Validate date is not in the past
    if (strtotime($date) < strtotime('today')) {
        $_SESSION['error'] = 'Date cannot be in the past!';
        header('Location: instructor_dashboard.php');
        exit();
    }
    
    try {
        // Get instructor details for logging
        $stmt = $pdo->prepare("SELECT Instructor_Name FROM Lab_Instructor WHERE Instructor_ID = ?");
        $stmt->execute([$instructor_id]);
        $instructor = $stmt->fetch();
        
        // Create action description
        $action_description = "Lab Usage: {$lab_name} - Group {$group_no} on {$date} at {$time}";
        
        // Insert into Usage_Log table
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (Instructor_ID, Group_No, Lab_Name, Lab_Type, Action, Timestamp, User_ID, User_Type) 
            VALUES (?, ?, ?, 'Regular Lab', ?, NOW(), ?, 'Instructor')
        ");
        $stmt->execute([$instructor_id, $group_no, $lab_name, $action_description, $instructor_id]);
        
        $_SESSION['success'] = "Lab usage logged successfully for {$lab_name} - Group {$group_no}!";
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: instructor_dashboard.php');
exit();
?>
