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
    $reschedule_id = (int)$_POST['reschedule_id'];
    $status = $_POST['status'];
    
    // Validation
    if (empty($reschedule_id) || !in_array($status, ['Completed', 'Cancelled'])) {
        $_SESSION['error'] = 'Invalid status update request!';
        header('Location: instructor_dashboard.php');
        exit();
    }
    
    try {
        // Verify that this reschedule belongs to the instructor
        $stmt = $pdo->prepare("
            SELECT rt.*, s.Student_Name, s.Email as Student_Email, ls.Lab_Name,
                   sc.Coordinator_Name, sc.Email as Coordinator_Email
            FROM Reschedule_Table rt
            JOIN Student s ON rt.Student_ID = s.Student_ID
            JOIN Lab_Schedule_Table ls ON rt.Original_Lab_ID = ls.Lab_ID
            LEFT JOIN Subject_Coordinator sc ON ls.Coordinator_ID = sc.Coordinator_ID
            WHERE rt.RescheduleLab_ID = ? AND rt.Instructor_ID = ?
        ");
        $stmt->execute([$reschedule_id, $instructor_id]);
        $reschedule = $stmt->fetch();
        
        if (!$reschedule) {
            $_SESSION['error'] = 'Reschedule not found or unauthorized access!';
            header('Location: instructor_dashboard.php');
            exit();
        }
        
        // Update the reschedule status
        $stmt = $pdo->prepare("
            UPDATE Reschedule_Table 
            SET Status = ?, Updated_At = NOW() 
            WHERE RescheduleLab_ID = ?
        ");
        $stmt->execute([$status, $reschedule_id]);
        
        // Send notification to student
        if ($status === 'Completed') {
            $message = "Your rescheduled lab '{$reschedule['Lab_Name']}' has been marked as completed by the instructor. Thank you for attending!";
            $notification_type = 'Lab Completed';
        } else {
            $message = "Your rescheduled lab '{$reschedule['Lab_Name']}' has been cancelled. Please contact your instructor for more information.";
            $notification_type = 'Lab Cancelled';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message, RescheduleLab_ID) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$reschedule['Student_Name'], $reschedule['Student_Email'], $notification_type, $message, $reschedule_id]);
        
        // Send notification to coordinator if exists
        if (!empty($reschedule['Coordinator_Name'])) {
            $coord_message = "Lab '{$reschedule['Lab_Name']}' for student {$reschedule['Student_Name']} has been marked as {$status} by the instructor.";
            
            $stmt = $pdo->prepare("
                INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message, RescheduleLab_ID) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$reschedule['Coordinator_Name'], $reschedule['Coordinator_Email'], $notification_type, $coord_message, $reschedule_id]);
        }
        
        // Update attendance table if completed
        if ($status === 'Completed') {
            $stmt = $pdo->prepare("
                UPDATE Attendance_Table 
                SET Attendance_Status = 'Present', Updated_At = NOW() 
                WHERE Student_ID = ? AND Lab_ID = (SELECT Original_Lab_ID FROM Reschedule_Table WHERE RescheduleLab_ID = ?)
            ");
            $stmt->execute([$reschedule['Student_ID'], $reschedule_id]);
            
            // If no attendance record exists, create one
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO Attendance_Table (Student_ID, Lab_ID, Instructor_ID, Attendance_Status, Group_No, Date) 
                SELECT ?, rt.Original_Lab_ID, rt.Instructor_ID, 'Present', 
                       (SELECT Group_No FROM Student WHERE Student_ID = ?), rt.New_Date
                FROM Reschedule_Table rt 
                WHERE rt.RescheduleLab_ID = ?
            ");
            $stmt->execute([$reschedule['Student_ID'], $reschedule['Student_ID'], $reschedule_id]);
        }
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, 'Instructor', ?, ?, NOW())
        ");
        $action = "Marked Lab as " . $status;
        $stmt->execute([$instructor_id, $action, $reschedule['Lab_Name']]);
        
        $_SESSION['success'] = "Lab successfully marked as {$status}! Student and coordinator have been notified.";
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: instructor_dashboard.php');
exit();
?>
