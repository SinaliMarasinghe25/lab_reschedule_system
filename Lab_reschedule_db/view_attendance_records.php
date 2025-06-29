<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    exit('Unauthorized');
}

$instructor_id = $_SESSION['user_id'];
$lab_id = !empty($_POST['lab_id']) ? (int)$_POST['lab_id'] : null;
$group_no = !empty($_POST['group_no']) ? (int)$_POST['group_no'] : null;
$date = !empty($_POST['date']) ? $_POST['date'] : null;

try {
    $sql = "
        SELECT a.*, s.Student_Name, s.Group_No, ls.Lab_Name, ls.Date as Lab_Date, ls.Time as Lab_Time
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
    
    if ($group_no) {
        $sql .= " AND s.Group_No = ?";
        $params[] = $group_no;
    }
    
    if ($date) {
        $sql .= " AND a.Date = ?";
        $params[] = $date;
    }
    
    $sql .= " ORDER BY a.Date DESC, s.Student_Name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    if (empty($records)) {
        echo '<p style="text-align: center; color: #666; padding: 20px;">No attendance records found.</p>';
        exit;
    }
    
    echo '<div style="max-height: 400px; overflow-y: auto;">';
    echo '<table class="table" style="margin-top: 15px;">';
    echo '<thead><tr><th>Student</th><th>Lab</th><th>Group</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($records as $record) {
        $statusClass = 'status-' . strtolower($record['Attendance_Status']);
        echo '<tr>';
        echo '<td>' . htmlspecialchars($record['Student_Name']) . '</td>';
        echo '<td>' . htmlspecialchars($record['Lab_Name']) . '</td>';
        echo '<td><span class="badge badge-group">Group ' . $record['Group_No'] . '</span></td>';
        echo '<td>' . date('M j, Y', strtotime($record['Date'])) . '</td>';
        echo '<td>' . date('H:i', strtotime($record['Lab_Time'])) . '</td>';
        echo '<td><span class="status-badge ' . $statusClass . '">' . $record['Attendance_Status'] . '</span></td>';
        echo '<td><button class="btn btn-info btn-sm" onclick="editAttendance(' . $record['Attendance_ID'] . ', \'' . $record['Attendance_Status'] . '\')">Edit</button></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<p style="color: #dc3545;">Error loading records: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
