<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $coordinator_id = $_SESSION['user_id'];
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    
    // Validation
    if (empty($request_id) || !in_array($action, ['approve', 'reject'])) {
        $_SESSION['error'] = 'Invalid action!';
        header('Location: coordinator_dashboard.php');
        exit();
    }
    
    try {
        // Get request details
        $stmt = $pdo->prepare("
            SELECT rr.*, s.Student_Name, s.Email as Student_Email, s.Group_No,
                   ls.Lab_Name, ls.Date, ls.Time, ls.Location, ls.Instructor_ID,
                   li.Instructor_Name, li.Email as Instructor_Email,
                   sc.Coordinator_Name
            FROM Reschedule_Request rr
            JOIN Student s ON rr.Student_ID = s.Student_ID
            JOIN Lab_Schedule_Table ls ON rr.Lab_ID = ls.Lab_ID
            LEFT JOIN Lab_Instructor li ON ls.Instructor_ID = li.Instructor_ID
            JOIN Subject_Coordinator sc ON rr.Coordinator_ID = sc.Coordinator_ID
            WHERE rr.Request_ID = ? AND rr.Coordinator_ID = ? AND rr.Status = 'Pending'
        ");
        $stmt->execute([$request_id, $coordinator_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            $_SESSION['error'] = 'Request not found or already processed!';
            header('Location: coordinator_dashboard.php');
            exit();
        }
        
        // Update request status
        $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
        $stmt = $pdo->prepare("
            UPDATE Reschedule_Request 
            SET Status = ?, Updated_At = NOW() 
            WHERE Request_ID = ?
        ");
        $stmt->execute([$new_status, $request_id]);
        
        if ($action === 'approve') {
            // Send notification to instructor if assigned
            if ($request['Instructor_ID'] && $request['Instructor_Email']) {
                $instructor_message = "A reschedule request for lab '{$request['Lab_Name']}' has been approved by {$request['Coordinator_Name']}. Student: {$request['Student_Name']} (Group {$request['Group_No']}). Original date: " . 
                                     date('M j, Y', strtotime($request['Date'])) . " at " . 
                                     date('H:i', strtotime($request['Time'])) . ". Reason: {$request['Reason']}. Please schedule the new lab session.";
                
                $stmt = $pdo->prepare("
                    INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message, Request_ID) 
                    VALUES (?, ?, 'Approved Request - Action Required', ?, ?)
                ");
                $stmt->execute([$request['Instructor_Name'], $request['Instructor_Email'], $instructor_message, $request_id]);
            }
            
            // Send approval notification to student
            $student_message = "Great news! Your reschedule request for lab '{$request['Lab_Name']}' has been APPROVED by {$request['Coordinator_Name']}. The lab instructor will contact you with the new schedule details soon.";
            
            $_SESSION['success'] = 'Reschedule request approved successfully! Instructor has been notified.';
            
        } else {
            // Send rejection notification to student
            $student_message = "Your reschedule request for lab '{$request['Lab_Name']}' has been REJECTED by {$request['Coordinator_Name']}. Please contact your coordinator if you have any questions.";
            
            $_SESSION['success'] = 'Reschedule request rejected successfully! Student has been notified.';
        }
        
        // Send notification to student
        $stmt = $pdo->prepare("
            INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message, Request_ID) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $notification_type = ($action === 'approve') ? 'Request Approved' : 'Request Rejected';
        $stmt->execute([$request['Student_Name'], $request['Student_Email'], $notification_type, $student_message, $request_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, 'Coordinator', ?, ?, NOW())
        ");
        $action_log = ucfirst($action) . 'd Reschedule Request';
        $stmt->execute([$coordinator_id, $action_log, $request['Lab_Name']]);
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: coordinator_dashboard.php');
exit();
?>
