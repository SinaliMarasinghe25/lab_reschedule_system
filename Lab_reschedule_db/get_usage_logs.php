<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    exit('Unauthorized');
}

$user_type = $_POST['user_type'] ?? '';
$date = $_POST['date'] ?? '';
$lab_name = $_POST['lab_name'] ?? '';
$group_no = $_POST['group_no'] ?? '';
$instructor_id = $_SESSION['user_id'];

try {
    $sql = "
        SELECT DISTINCT
               ls.Group_No,
               ls.Lab_Name,
               CASE 
                   WHEN li.Instructor_Name IS NOT NULL THEN li.Instructor_Name
                   WHEN sc.Coordinator_Name IS NOT NULL THEN sc.Coordinator_Name
                   ELSE 'Not Assigned'
               END as Responsible_Person,
               CASE 
                   WHEN li.Instructor_Name IS NOT NULL THEN 'Instructor'
                   WHEN sc.Coordinator_Name IS NOT NULL THEN 'Coordinator'
                   ELSE 'Unknown'
               END as Person_Type,
               ul.Action,
               ul.Timestamp
        FROM Usage_Log ul
        JOIN Lab_Schedule_Table ls ON ul.Lab_Name = ls.Lab_Name
        LEFT JOIN Lab_Instructor li ON ls.Instructor_ID = li.Instructor_ID
        LEFT JOIN Subject_Coordinator sc ON ls.Coordinator_ID = sc.Coordinator_ID
        WHERE ls.Instructor_ID = ? OR ul.User_ID = ?
    ";
    $params = [$instructor_id, $instructor_id];
    
    if ($user_type) {
        if ($user_type === 'Instructor') {
            $sql .= " AND li.Instructor_Name IS NOT NULL";
        } elseif ($user_type === 'Coordinator') {
            $sql .= " AND sc.Coordinator_Name IS NOT NULL";
        }
    }
    
    if ($date) {
        $sql .= " AND DATE(ul.Timestamp) = ?";
        $params[] = $date;
    }
    
    if ($lab_name) {
        $sql .= " AND ls.Lab_Name = ?";
        $params[] = $lab_name;
    }
    
    if ($group_no) {
        $sql .= " AND ls.Group_No = ?";
        $params[] = $group_no;
    }
    
    $sql .= " ORDER BY ul.Timestamp DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo '<p>No usage logs found.</p>';
        exit;
    }
    
    echo '<table class="table" style="margin-top: 15px;">';
    echo '<thead><tr><th>Group Number</th><th>Lab Name</th><th>Responsible Person</th><th>Role</th><th>Action</th><th>Date & Time</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td><span class="badge badge-group">Group ' . htmlspecialchars($log['Group_No']) . '</span></td>';
        echo '<td><strong>' . htmlspecialchars($log['Lab_Name']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($log['Responsible_Person']) . '</td>';
        echo '<td><span class="badge badge-' . strtolower($log['Person_Type']) . '">' . $log['Person_Type'] . '</span></td>';
        echo '<td>' . htmlspecialchars($log['Action']) . '</td>';
        echo '<td>' . date('M j, Y H:i:s', strtotime($log['Timestamp'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
} catch (PDOException $e) {
    echo '<p>Error loading logs: ' . $e->getMessage() . '</p>';
}
?>
