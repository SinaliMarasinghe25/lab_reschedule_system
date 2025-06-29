<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    exit('Unauthorized');
}

$instructor_id = $_SESSION['user_id'];
$date = !empty($_POST['date']) ? $_POST['date'] : null;
$lab_name = !empty($_POST['lab_name']) ? $_POST['lab_name'] : null;
$group_no = !empty($_POST['group_no']) ? (int)$_POST['group_no'] : null;

try {
    $sql = "
        SELECT ul.*, li.Instructor_Name
        FROM Usage_Log ul
        JOIN Lab_Instructor li ON ul.Instructor_ID = li.Instructor_ID
        WHERE ul.Instructor_ID = ?
    ";
    $params = [$instructor_id];
    
    if ($date) {
        $sql .= " AND DATE(ul.Timestamp) = ?";
        $params[] = $date;
    }
    
    if ($lab_name) {
        $sql .= " AND ul.Lab_Name LIKE ?";
        $params[] = '%' . $lab_name . '%';
    }
    
    if ($group_no) {
        $sql .= " AND ul.Group_No = ?";
        $params[] = $group_no;
    }
    
    $sql .= " ORDER BY ul.Timestamp DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo '<p style="text-align: center; color: #666; padding: 20px;">No usage logs found.</p>';
        exit;
    }
    
    echo '<div style="max-height: 400px; overflow-y: auto;">';
    echo '<table class="table" style="margin-top: 15px;">';
    echo '<thead><tr><th>Lab Name</th><th>Group</th><th>Lab Type</th><th>Date & Time</th><th>Action</th><th>Notes</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($log['Lab_Name']) . '</strong></td>';
        echo '<td><span class="badge badge-group">Group ' . htmlspecialchars($log['Group_No']) . '</span></td>';
        echo '<td><span class="badge badge-info">' . htmlspecialchars($log['Lab_Type'] ?? 'Regular Lab') . '</span></td>';
        echo '<td>' . date('M j, Y H:i', strtotime($log['Timestamp'])) . '</td>';
        echo '<td>' . htmlspecialchars(substr($log['Action'], 0, 50)) . (strlen($log['Action']) > 50 ? '...' : '') . '</td>';
        echo '<td>' . htmlspecialchars($log['Notes'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<p style="color: #dc3545;">Error loading logs: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
