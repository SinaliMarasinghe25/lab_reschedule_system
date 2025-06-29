<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    exit('Unauthorized');
}

$group_no = (int)$_POST['group_no'];
$lab_id = (int)$_POST['lab_id'];
$date = $_POST['date'];
$instructor_id = $_SESSION['user_id'];

try {
    // Get students in this group
    $stmt = $pdo->prepare("
        SELECT s.Student_ID, s.Student_Name, s.Email,
               a.Attendance_Status, a.Attendance_ID
        FROM Student s
        LEFT JOIN Attendance_Table a ON s.Student_ID = a.Student_ID 
                                     AND a.Lab_ID = ? 
                                     AND a.Date = ?
        WHERE s.Group_No = ?
        ORDER BY s.Student_Name
    ");
    $stmt->execute([$lab_id, $date, $group_no]);
    $students = $stmt->fetchAll();
    
    if (empty($students)) {
        echo '<p>No students found in this group.</p>';
        exit;
    }
    
    foreach ($students as $student) {
        $presentChecked = ($student['Attendance_Status'] === 'Present') ? 'checked' : '';
        $absentChecked = ($student['Attendance_Status'] === 'Absent') ? 'checked' : '';
        $lateChecked = ($student['Attendance_Status'] === 'Late') ? 'checked' : '';
        
        echo '<div style="border-bottom: 1px solid #eee; padding: 10px; display: flex; justify-content: space-between; align-items: center;">';
        echo '<div><strong>' . htmlspecialchars($student['Student_Name']) . '</strong><br><small>' . htmlspecialchars($student['Email']) . '</small></div>';
        echo '<div>';
        echo '<label style="margin-right: 15px;"><input type="radio" name="attendance[' . $student['Student_ID'] . ']" value="Present" ' . $presentChecked . '> Present</label>';
        echo '<label style="margin-right: 15px;"><input type="radio" name="attendance[' . $student['Student_ID'] . ']" value="Late" ' . $lateChecked . '> Late</label>';
        echo '<label><input type="radio" name="attendance[' . $student['Student_ID'] . ']" value="Absent" ' . $absentChecked . '> Absent</label>';
        echo '</div>';
        echo '</div>';
    }
    
} catch (PDOException $e) {
    echo '<p>Error loading students: ' . $e->getMessage() . '</p>';
}
?>
