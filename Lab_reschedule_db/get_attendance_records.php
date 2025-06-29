<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    exit('Unauthorized');
}

$lab_id = !empty($_POST['lab_id']) ? (int)$_POST['lab_id'] : null;
$instructor_id = $_SESSION['user_id'];

try {
    $sql = "
        SELECT a.*, s.Student_Name, s.Group_No, ls.Lab_Name 
        FROM Attendance_Table a
        JOIN Student s ON a.Student_ID = s.Student_ID
        JOIN Lab_Schedule_Table ls ON a.Lab_ID = ls.Lab_ID
        WHERE a.Instructor_ID = ?
    ";
    $params = [$instructor_id];
    
    if ($lab_id) {
        $sql .= " AND a.Lab_ID = ?";
        $params[] = $lab_id;
    }
    
    $sql .= " ORDER BY a.Date DESC, s.Student_Name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    if (empty($records)) {
        echo '<p>No attendance records found.</p>';
        exit;
    }
    
    echo '<table class="table" style="margin-top: 15px;">';
    echo '<thead><tr><th>Student</th><th>Lab</th><th>Group</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($records as $record) {
        $statusClass = 'status-' . strtolower($record['Attendance_Status']);
        echo '<tr>';
        echo '<td>' . htmlspecialchars($record['Student_Name']) . '</td>';
        echo '<td>' . htmlspecialchars($record['Lab_Name']) . '</td>';
        echo '<td>' . $record['Group_No'] . '</td>';
        echo '<td>' . date('M j, Y', strtotime($record['Date'])) . '</td>';
        echo '<td><span class="status-badge ' . $statusClass . '">' . $record['Attendance_Status'] . '</span></td>';
        echo '<td><button class="btn btn-primary" onclick="editAttendance(' . $record['Attendance_ID'] . ', \'' . $record['Attendance_Status'] . '\')">Edit</button></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
} catch (PDOException $e) {
    echo '<p>Error loading records: ' . $e->getMessage() . '</p>';
}
?>
