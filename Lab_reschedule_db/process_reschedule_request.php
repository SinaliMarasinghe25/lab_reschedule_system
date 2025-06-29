<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_SESSION['user_id'];
    $lab_id = (int)$_POST['lab_id'];
    $coordinator_id = (int)$_POST['coordinator_id'];
    $reason = trim($_POST['reason']);
    $request_date = date('Y-m-d');
    
    // Validation
    if (empty($lab_id) || empty($coordinator_id) || empty($reason)) {
        $_SESSION['error'] = 'All fields are required!';
        header('Location: student_dashboard.php');
        exit();
    }
    
    if (strlen($reason) < 10) {
        $_SESSION['error'] = 'Please provide a detailed reason (minimum 10 characters)!';
        header('Location: student_dashboard.php');
        exit();
    }
    
    try {
        // Check if student already has a pending request for this lab
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM Reschedule_Request 
            WHERE Student_ID = ? AND Lab_ID = ? AND Status = 'Pending'
        ");
        $stmt->execute([$student_id, $lab_id]);
        $existing = $stmt->fetch();
        
        if ($existing['count'] > 0) {
            $_SESSION['error'] = 'You already have a pending reschedule request for this lab!';
            header('Location: student_dashboard.php');
            exit();
        }
        
        // Get lab and student details for notifications
        $stmt = $pdo->prepare("
            SELECT ls.Lab_Name, ls.Date, ls.Time, s.Student_Name, s.Email as Student_Email,
                   sc.Coordinator_Name, sc.Email as Coordinator_Email
            FROM Lab_Schedule_Table ls
            JOIN Student s ON s.Student_ID = ?
            JOIN Subject_Coordinator sc ON sc.Coordinator_ID = ?
            WHERE ls.Lab_ID = ?
        ");
        $stmt->execute([$student_id, $coordinator_id, $lab_id]);
        $details = $stmt->fetch();
        
        if (!$details) {
            $_SESSION['error'] = 'Invalid lab or coordinator selection!';
            header('Location: student_dashboard.php');
            exit();
        }
        
        // Insert reschedule request
        $stmt = $pdo->prepare("
            INSERT INTO Reschedule_Request (Request_Date, Reason, Status, Student_ID, Coordinator_ID, Lab_ID) 
            VALUES (?, ?, 'Pending', ?, ?, ?)
        ");
        $stmt->execute([$request_date, $reason, $student_id, $coordinator_id, $lab_id]);
        
        $request_id = $pdo->lastInsertId();
        
        // Send notification to coordinator
        $coordinator_message = "New reschedule request from {$details['Student_Name']} for lab '{$details['Lab_Name']}' scheduled on " . 
                              date('M j, Y', strtotime($details['Date'])) . " at " . 
                              date('H:i', strtotime($details['Time'])) . ". Reason: {$reason}";
        
        $stmt = $pdo->prepare("
            INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message, Request_ID) 
            VALUES (?, ?, 'Reschedule Request', ?, ?)
        ");
        $stmt->execute([$details['Coordinator_Name'], $details['Coordinator_Email'], $coordinator_message, $request_id]);
        
        // Send confirmation notification to student
        $student_message = "Your reschedule request for lab '{$details['Lab_Name']}' has been submitted successfully and is pending review by {$details['Coordinator_Name']}.";
        
        $stmt = $pdo->prepare("
            INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message, Request_ID) 
            VALUES (?, ?, 'Request Submitted', ?, ?)
        ");
        $stmt->execute([$details['Student_Name'], $details['Student_Email'], $student_message, $request_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, 'Student', 'Submitted Reschedule Request', ?, NOW())
        ");
        $stmt->execute([$student_id, $details['Lab_Name']]);
        
        $_SESSION['success'] = 'Reschedule request submitted successfully! You will be notified once it is reviewed.';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: student_dashboard.php');
exit();
?>
