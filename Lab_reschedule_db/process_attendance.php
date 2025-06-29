<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $instructor_id = $_SESSION['user_id'];
    $lab_id = (int)$_POST['lab_id'];
    $attendance_date = $_POST['attendance_date'];
    $attendance_data = $_POST['attendance'];
    
    if (empty($lab_id) || empty($attendance_date) || empty($attendance_data)) {
        $_SESSION['error'] = 'All fields are required!';
        header('Location: instructor_dashboard.php');
        exit();
    }
    
    try {
        // Get lab details for logging
        $stmt = $pdo->prepare("SELECT Lab_Name, Group_No FROM Lab_Schedule_Table WHERE Lab_ID = ?");
        $stmt->execute([$lab_id]);
        $lab = $stmt->fetch();
        
        $pdo->beginTransaction();
        
        $updated_count = 0;
        $inserted_count = 0;
        
        foreach ($attendance_data as $student_id => $status) {
            // Check if attendance record already exists
            $stmt = $pdo->prepare("
                SELECT Attendance_ID FROM Attendance_Table 
                WHERE Student_ID = ? AND Lab_ID = ? AND Date = ?
            ");
            $stmt->execute([$student_id, $lab_id, $attendance_date]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE Attendance_Table 
                    SET Attendance_Status = ?, Instructor_ID = ? 
                    WHERE Attendance_ID = ?
                ");
                $stmt->execute([$status, $instructor_id, $existing['Attendance_ID']]);
                $updated_count++;
            } else {
                // Insert new record
                $stmt = $pdo->prepare("
                    INSERT INTO Attendance_Table (Student_ID, Lab_ID, Instructor_ID, Attendance_Status, Group_No, Date) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $lab_id, $instructor_id, $status, $lab['Group_No'], $attendance_date]);
                $inserted_count++;
            }
        }
        
        // Log the action in Usage_Log
        $action_description = "Updated attendance for {$lab['Lab_Name']} - Group {$lab['Group_No']} ({$updated_count} updated, {$inserted_count} new records)";
        
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, 'Instructor', ?, ?, NOW())
        ");
        $stmt->execute([$instructor_id, $action_description, $lab['Lab_Name']]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Attendance updated successfully! {$updated_count} records updated, {$inserted_count} new records added.";
        
    } catch (PDOException $e) {
        $pdo->rollback();
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
}

header('Location: instructor_dashboard.php');
exit();
?>
