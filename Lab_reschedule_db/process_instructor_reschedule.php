<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $instructor_id = $_SESSION['user_id'];
    $request_id = (int)$_POST['request_id'];
    $new_date = $_POST['new_date'];
    $new_time = $_POST['new_time'];
    $location = trim($_POST['location']);
    
    // Validation
    if (empty($request_id) || empty($new_date) || empty($new_time) || empty($location)) {
        $_SESSION['error'] = 'All fields are required!';
        header('Location: instructor_dashboard.php');
        exit();
    }
    
    // Check if new date is not in the past
    if ($new_date < date('Y-m-d')) {
        $_SESSION['error'] = 'Reschedule date cannot be in the past!';
        header('Location: instructor_dashboard.php');
        exit();
    }
    
    try {
        // Get request details
        $stmt = $pdo->prepare("
            SELECT rr.*, s.Student_Name, s.Email as Student_Email,
                   ls.Lab_Name, sc.Coordinator_Name, sc.Email as Coordinator_Email
            FROM Reschedule_Request rr
            JOIN Student s ON rr.Student_ID = s.Student_ID
            JOIN Lab_Schedule_Table ls ON rr.Lab_ID = ls.Lab_ID
            JOIN Subject_Coordinator sc ON rr.Coordinator_ID = sc.Coordinator_ID
            WHERE rr.Request_ID = ? AND rr.Status = 'Approved'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            $_SESSION['error'] = 'Request not found or not approved!';
            header('Location: instructor_dashboard.php');
            exit();
        }
        
        // Insert into Reschedule_Table with 'Completed' status (no further action needed)
        $stmt = $pdo->prepare("
            INSERT INTO Reschedule_Table (Student_ID, Instructor_ID, Original_Lab_ID, New_Date, New_Time, Location, Status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Completed')
        ");
        $stmt->execute([$request['Student_ID'], $instructor_id, $request['Lab_ID'], $new_date, $new_time, $location]);
        
        // SIMPLIFIED NOTIFICATIONS ONLY
        
        // 1. Notify Student
        $student_message = "Your lab '{$request['Lab_Name']}' has been rescheduled to " . 
                          date('M j, Y', strtotime($new_date)) . " at " . 
                          date('H:i', strtotime($new_time)) . " in {$location}.";
        
        $stmt = $pdo->prepare("
            INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message) 
            VALUES (?, ?, 'Lab Rescheduled', ?)
        ");
        $stmt->execute([$request['Student_Name'], $request['Student_Email'], $student_message]);
        
        // 2. Notify Coordinator
        $coordinator_message = "Lab '{$request['Lab_Name']}' for student {$request['Student_Name']} has been rescheduled to " . 
                              date('M j, Y', strtotime($new_date)) . " at " . 
                              date('H:i', strtotime($new_time)) . " in {$location}.";
        
        $stmt = $pdo->prepare("
            INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message) 
            VALUES (?, ?, 'Lab Rescheduled', ?)
        ");
        $stmt->execute([$request['Coordinator_Name'], $request['Coordinator_Email'], $coordinator_message]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, 'Instructor', 'Lab Rescheduled', ?, NOW())
        ");
        $stmt->execute([$instructor_id, $request['Lab_Name']]);
        
        $_SESSION['success'] = 'Lab rescheduled successfully! Student and coordinator have been notified.';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: instructor_dashboard.php');
exit();
?>
