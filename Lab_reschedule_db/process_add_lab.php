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
    $lab_name = trim($_POST['lab_name']);
    $location = trim($_POST['location']);
    $group_no = (int)$_POST['group_no'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $instructor_id = !empty($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : null;
    
    // Validation
    if (empty($lab_name) || empty($location) || empty($group_no) || empty($date) || empty($time)) {
        $_SESSION['error'] = 'All required fields must be filled!';
        header('Location: coordinator_dashboard.php');
        exit();
    }
    
    // Check if date is not in the past
    if ($date < date('Y-m-d')) {
        $_SESSION['error'] = 'Lab date cannot be in the past!';
        header('Location: coordinator_dashboard.php');
        exit();
    }
    
    // Check for conflicting schedules
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM Lab_Schedule_Table 
            WHERE Date = ? AND Time = ? AND Location = ?
        ");
        $stmt->execute([$date, $time, $location]);
        $conflict = $stmt->fetch();
        
        if ($conflict['count'] > 0) {
            $_SESSION['error'] = 'A lab is already scheduled at this date, time, and location!';
            header('Location: coordinator_dashboard.php');
            exit();
        }
        
        // Insert new lab schedule
        $stmt = $pdo->prepare("
            INSERT INTO Lab_Schedule_Table (Lab_Name, Location, Group_No, Date, Time, Coordinator_ID, Instructor_ID) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$lab_name, $location, $group_no, $date, $time, $coordinator_id, $instructor_id]);
        
        $lab_id = $pdo->lastInsertId();
        
        // Log the action in Usage_Log
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, 'Coordinator', 'Added New Lab Schedule', ?, NOW())
        ");
        $stmt->execute([$coordinator_id, $lab_name]);
        
        // Send notification to instructor if assigned
        if ($instructor_id) {
            $stmt = $pdo->prepare("
                SELECT Instructor_Name, Email FROM Lab_Instructor WHERE Instructor_ID = ?
            ");
            $stmt->execute([$instructor_id]);
            $instructor = $stmt->fetch();
            
            if ($instructor) {
                $stmt = $pdo->prepare("
                    INSERT INTO Notification_Table (Recipient_Name, Recipient_Email, Notification_Type, Message) 
                    VALUES (?, ?, 'Lab Assignment', ?)
                ");
                $message = "You have been assigned to conduct '{$lab_name}' on " . date('M j, Y', strtotime($date)) . " at " . date('H:i', strtotime($time)) . " in {$location}.";
                $stmt->execute([$instructor['Instructor_Name'], $instructor['Email'], $message]);
            }
        }
        
        $_SESSION['success'] = 'Lab schedule added successfully!';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: coordinator_dashboard.php');
exit();
?>
