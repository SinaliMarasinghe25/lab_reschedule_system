<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    header('Location: index.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['user_name'];

// Get instructor details
$stmt = $pdo->prepare("SELECT Department, Email FROM Lab_Instructor WHERE Instructor_ID = ?");
$stmt->execute([$instructor_id]);
$instructor = $stmt->fetch();
$instructor_department = $instructor['Department'];
$instructor_email = $instructor['Email'];

// Get lab schedules assigned to this instructor
$stmt = $pdo->prepare("
    SELECT ls.*, sc.Coordinator_Name
    FROM Lab_Schedule_Table ls
    LEFT JOIN Subject_Coordinator sc ON ls.Coordinator_ID = sc.Coordinator_ID
    WHERE ls.Instructor_ID = ?
    ORDER BY ls.Date ASC, ls.Time ASC
");
$stmt->execute([$instructor_id]);
$lab_schedules = $stmt->fetchAll();

// SIMPLIFIED: Get all approved reschedule requests for this instructor's labs
$stmt = $pdo->prepare("
    SELECT DISTINCT rr.*, s.Student_Name, s.Group_No, s.Email as Student_Email, 
           ls.Lab_Name, ls.Date as Original_Date, ls.Time as Original_Time, ls.Location as Original_Location,
           sc.Coordinator_Name,
           (SELECT COUNT(*) FROM Reschedule_Table rt 
            WHERE rt.Student_ID = rr.Student_ID 
            AND rt.Original_Lab_ID = rr.Lab_ID) as Is_Rescheduled
    FROM Reschedule_Request rr
    JOIN Student s ON rr.Student_ID = s.Student_ID
    JOIN Lab_Schedule_Table ls ON rr.Lab_ID = ls.Lab_ID
    JOIN Subject_Coordinator sc ON rr.Coordinator_ID = sc.Coordinator_ID
    WHERE ls.Instructor_ID = ? AND rr.Status = 'Approved'
    ORDER BY rr.Created_At ASC
");
$stmt->execute([$instructor_id]);
$all_approved_requests = $stmt->fetchAll();

// Filter out already rescheduled requests
$approved_requests = array_filter($all_approved_requests, function($request) {
    return $request['Is_Rescheduled'] == 0;
});

// Get reschedules created by this instructor
$stmt = $pdo->prepare("
    SELECT rt.*, s.Student_Name, s.Group_No, ls.Lab_Name
    FROM Reschedule_Table rt
    JOIN Student s ON rt.Student_ID = s.Student_ID
    JOIN Lab_Schedule_Table ls ON rt.Original_Lab_ID = ls.Lab_ID
    WHERE rt.Instructor_ID = ?
    ORDER BY rt.New_Date ASC
");
$stmt->execute([$instructor_id]);
$reschedules = $stmt->fetchAll();

// Get notifications for instructor
$stmt = $pdo->prepare("
    SELECT * FROM Notification_Table 
    WHERE Recipient_Email = ? 
    ORDER BY Created_At DESC 
    LIMIT 10
");
$stmt->execute([$instructor_email]);
$notifications = $stmt->fetchAll();

// Get upcoming labs (within next 7 days)
$stmt = $pdo->prepare("
    SELECT ls.*, sc.Coordinator_Name 
    FROM Lab_Schedule_Table ls
    LEFT JOIN Subject_Coordinator sc ON ls.Coordinator_ID = sc.Coordinator_ID
    WHERE ls.Instructor_ID = ? AND ls.Date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ls.Date ASC, ls.Time ASC
    LIMIT 5
");
$stmt->execute([$instructor_id]);
$upcoming_labs = $stmt->fetchAll();

// Get unique group numbers for this instructor's labs (for dropdowns)
$stmt = $pdo->prepare("SELECT DISTINCT Group_No FROM Lab_Schedule_Table WHERE Instructor_ID = ?");
$stmt->execute([$instructor_id]);
$groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats = [
    'assigned_labs' => count($lab_schedules),
    'pending_reschedules' => count($approved_requests),
    'completed_reschedules' => count(array_filter($reschedules, function($r) { return $r['Status'] === 'Completed'; })),
    'total_reschedules' => count($reschedules),
    'unread_notifications' => count(array_filter($notifications, function($n) { return !$n['Is_Read']; }))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Instructor Dashboard - Lab Reschedule System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(135deg, #2E8B57, #228B22);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo h1 {
            font-size: 1.8em;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .logo p {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-info span {
            font-weight: 500;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            border: 1px solid transparent;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            position: relative;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #2E8B57;
        }
        .stat-label {
            color: #666;
            margin-top: 8px;
            font-weight: 500;
        }
        .stat-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #90EE90;
        }
        .card-title {
            color: #2E8B57;
            font-size: 1.4em;
            font-weight: 600;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            margin: 3px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #2E8B57, #228B22);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #228B22, #2E8B57);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.4);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .quick-actions {
            display: grid;
            gap: 15px;
        }
        .quick-actions .btn {
            width: 100%;
        }
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin: 0;
        }
        .table th, .table td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .table th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #2E8B57;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-scheduled {
            background: #cce5f4;
            color: #0c5aa6;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .status-present {
            background: #d4edda;
            color: #155724;
        }
        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }
        .status-late {
            background: #fff3cd;
            color: #856404;
        }
        .notification-item {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-left: 4px solid #2E8B57;
            margin-bottom: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
        }
        .notification-item:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .notification-item.unread {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-left-color: #28a745;
        }
        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 10px;
            right: 10px;
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
        }
        .notification-meta {
            font-size: 0.85em;
            color: #666;
            margin-top: 8px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
        }
        .upcoming-lab {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 10px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-group {
            background: #e7f3ff;
            color: #0066cc;
        }
        .badge-urgent {
            background: #ffebee;
            color: #d32f2f;
        }
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .reschedule-row.processing {
            opacity: 0.6;
            background: #fffbf0 !important;
        }
        .reschedule-row.removing {
            animation: slideOut 0.5s ease-out forwards;
        }
        @keyframes slideOut {
            0% { opacity: 1; transform: translateX(0); max-height: 80px; }
            50% { opacity: 0.5; transform: translateX(-20px); }
            100% { opacity: 0; transform: translateX(-100%); max-height: 0; padding-top: 0; padding-bottom: 0; margin: 0; }
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s ease-out;
        }
        @keyframes modalSlide {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .close:hover {
            color: #2E8B57;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2E8B57;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2E8B57;
            box-shadow: 0 0 10px rgba(46, 139, 87, 0.3);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-group .btn {
            flex: 1;
        }
        /* SCROLL BAR FIX FOR ATTENDANCE MODAL */
        #attendanceModal .modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }
        #studentsList {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        /* For larger view modals */
        #viewAttendanceModal .modal-content,
        #viewUsageLogModal .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }
        #attendanceResults,
        #usageLogResults {
            max-height: 400px;
            overflow-y: auto;
        }
        @media (max-width: 768px) {
            .container { padding: 0 15px; }
            .header-content { flex-direction: column; gap: 15px; text-align: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .btn-group { flex-direction: column; }
            .modal-content { max-width: 95%; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <h1>LAB RESCHEDULE SYSTEM</h1>
                <p>University of Jaffna - Instructor Portal</p>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($instructor_name); ?></span>
                <span>Role: Lab Instructor</span>
                <span><?php echo htmlspecialchars($instructor_department); ?></span>
                <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['assigned_labs']; ?></div>
                <div class="stat-label">Assigned Labs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_reschedules']; ?></div>
                <div class="stat-label">Pending Reschedules</div>
                <?php if ($stats['pending_reschedules'] > 0): ?>
                    <div class="stat-badge"><?php echo $stats['pending_reschedules']; ?></div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed_reschedules']; ?></div>
                <div class="stat-label">Completed Reschedules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_reschedules']; ?></div>
                <div class="stat-label">Total Reschedules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unread_notifications']; ?></div>
                <div class="stat-label">Unread Notifications</div>
                <?php if ($stats['unread_notifications'] > 0): ?>
                    <div class="stat-badge"><?php echo $stats['unread_notifications']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions Grid -->
        <div class="dashboard-grid">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="openModal('attendanceModal')">Mark Attendance</button>
                    <button class="btn btn-secondary" onclick="openModal('usageLogModal')">Log Lab Usage</button>
                    <button class="btn btn-info" onclick="openModal('viewAttendanceModal')">View Attendance Records</button>
                    <button class="btn btn-info" onclick="openModal('viewUsageLogModal')">View Usage Logs</button>
                    <button class="btn btn-secondary" onclick="refreshDashboard()">Refresh Dashboard</button>
                </div>
            </div>

            <!-- Upcoming Labs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upcoming Labs</h3>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($upcoming_labs)): ?>
                        <div class="empty-state">No upcoming labs in the next 7 days</div>
                    <?php else: ?>
                        <?php foreach ($upcoming_labs as $lab): ?>
                            <div class="upcoming-lab">
                                <strong><?php echo htmlspecialchars($lab['Lab_Name']); ?></strong>
                                <br>
                                <small>
                                    <?php echo date('M j, Y', strtotime($lab['Date'])); ?> at 
                                    <?php echo date('H:i', strtotime($lab['Time'])); ?>
                                    <br>
                                    Location: <?php echo htmlspecialchars($lab['Location']); ?>
                                    <br>
                                    Group: <?php echo $lab['Group_No']; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Notifications -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Notifications</h3>
                    <?php if ($stats['unread_notifications'] > 0): ?>
                        <span class="badge badge-urgent"><?php echo $stats['unread_notifications']; ?> New</span>
                    <?php endif; ?>
                </div>
                <div style="max-height: 350px; overflow-y: auto;">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">No notifications available</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo !$notification['Is_Read'] ? 'unread' : ''; ?>">
                                <strong><?php echo htmlspecialchars($notification['Notification_Type']); ?></strong>
                                <p><?php echo htmlspecialchars($notification['Message']); ?></p>
                                <div class="notification-meta">
                                    <?php echo date('M j, Y H:i', strtotime($notification['Created_At'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Approved Reschedule Requests -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Approved Reschedule Requests - Action Required</h3>
                <?php if (count($approved_requests) > 0): ?>
                    <span class="badge badge-urgent" id="pendingBadge"><?php echo count($approved_requests); ?> Pending</span>
                <?php endif; ?>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Group</th>
                            <th>Lab Name</th>
                            <th>Original Date</th>
                            <th>Original Time</th>
                            <th>Reason</th>
                            <th>Coordinator</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="approvedRequestsTable">
                        <?php if (empty($approved_requests)): ?>
                            <tr id="emptyStateRow"><td colspan="8" class="empty-state">No approved requests requiring action</td></tr>
                        <?php else: ?>
                            <?php foreach ($approved_requests as $request): ?>
                                <tr class="reschedule-row" id="request-row-<?php echo $request['Request_ID']; ?>">
                                    <td><?php echo htmlspecialchars($request['Student_Name']); ?></td>
                                    <td><span class="badge badge-group"><?php echo $request['Group_No']; ?></span></td>
                                    <td><?php echo htmlspecialchars($request['Lab_Name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['Original_Date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($request['Original_Time'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($request['Reason'], 0, 30)) . (strlen($request['Reason']) > 30 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($request['Coordinator_Name']); ?></td>
                                    <td>
                                        <button class="btn btn-success" 
                                                onclick="rescheduleRequest(<?php echo $request['Request_ID']; ?>, '<?php echo htmlspecialchars($request['Lab_Name']); ?>', '<?php echo htmlspecialchars($request['Student_Name']); ?>', '<?php echo $request['Student_ID']; ?>', '<?php echo $request['Lab_ID']; ?>')">
                                            Reschedule
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- My Lab Schedules -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">My Lab Schedules</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Lab Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Group</th>
                            <th>Coordinator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lab_schedules)): ?>
                            <tr><td colspan="6" class="empty-state">No lab schedules assigned</td></tr>
                        <?php else: ?>
                            <?php foreach ($lab_schedules as $lab): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lab['Lab_Name']); ?></td>
                                    <td>
                                        <?php 
                                        $labDate = date('M j, Y', strtotime($lab['Date']));
                                        $isUpcoming = strtotime($lab['Date']) <= strtotime('+7 days');
                                        echo $labDate;
                                        if ($isUpcoming && strtotime($lab['Date']) >= strtotime('today')) {
                                            echo ' <span class="badge badge-urgent">Upcoming</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($lab['Time'])); ?></td>
                                    <td><?php echo htmlspecialchars($lab['Location']); ?></td>
                                    <td><span class="badge badge-group"><?php echo $lab['Group_No']; ?></span></td>
                                    <td><?php echo htmlspecialchars($lab['Coordinator_Name'] ?? 'Not Assigned'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reschedule Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Reschedule Table</h3>
                <span style="font-size: 0.9em; color: #666;">Labs rescheduled for students</span>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Group</th>
                            <th>Lab Name</th>
                            <th>New Date</th>
                            <th>New Time</th>
                            <th>Location</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reschedules)): ?>
                            <tr><td colspan="7" class="empty-state">No reschedules found</td></tr>
                        <?php else: ?>
                            <?php foreach ($reschedules as $reschedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reschedule['Student_Name']); ?></td>
                                    <td><span class="badge badge-group"><?php echo $reschedule['Group_No']; ?></span></td>
                                    <td><?php echo htmlspecialchars($reschedule['Lab_Name']); ?></td>
                                    <td>
                                        <?php 
                                        $newDate = date('M j, Y', strtotime($reschedule['New_Date']));
                                        $isUpcoming = strtotime($reschedule['New_Date']) <= strtotime('+7 days');
                                        echo $newDate;
                                        if ($isUpcoming && strtotime($reschedule['New_Date']) >= strtotime('today')) {
                                            echo ' <span class="badge badge-urgent">Upcoming</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($reschedule['New_Time'])); ?></td>
                                    <td><?php echo htmlspecialchars($reschedule['Location']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($reschedule['Status']); ?>">
                                            <?php echo $reschedule['Status']; ?>
                                        </span>
                                        <br>
                                        <small style="color: #666; font-size: 0.8em;">
                                            Notified: Student & Coordinator
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="color: #2E8B57; margin: 0; font-size: 1.3em;">Mark Attendance</h2>
                <span class="close" onclick="closeModal('attendanceModal')">&times;</span>
            </div>
            <form action="process_attendance.php" method="POST" id="attendanceForm">
                <div class="form-group">
                    <label for="lab_id">Lab:</label>
                    <select name="lab_id" id="lab_id" required onchange="loadStudents()">
                        <option value="">Select Lab</option>
                        <?php foreach ($lab_schedules as $lab): ?>
                            <option value="<?php echo $lab['Lab_ID']; ?>">
                                <?php echo htmlspecialchars($lab['Lab_Name']) . " (Group " . htmlspecialchars($lab['Group_No']) . ") - " . htmlspecialchars($lab['Date']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="attendance_date">Date:</label>
                    <input type="date" name="attendance_date" id="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group" id="studentsList">
                    <!-- Students will be loaded here -->
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Submit Attendance</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('attendanceModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Usage Log Modal -->
    <div id="usageLogModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="color: #2E8B57; margin: 0; font-size: 1.3em;">Log Lab Usage</h2>
                <span class="close" onclick="closeModal('usageLogModal')">&times;</span>
            </div>
            <form action="process_usage_log.php" method="POST" id="usageLogForm">
                <div class="form-group">
                    <label for="usage_lab_name">Lab Name:</label>
                    <input type="text" id="usage_lab_name" name="lab_name" required>
                </div>
                <div class="form-group">
                    <label for="usage_group_no">Group Number:</label>
                    <select id="usage_group_no" name="group_no" required>
                        <option value="">Select Group</option>
                        <?php foreach ($groups as $group_no): ?>
                            <option value="<?php echo $group_no; ?>"><?php echo $group_no; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="usage_date">Date:</label>
                    <input type="date" id="usage_date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="usage_time">Time:</label>
                    <input type="time" id="usage_time" name="time" required>
                </div>
                <input type="hidden" name="instructor_id" value="<?php echo $instructor_id; ?>">
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Submit Usage Log</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('usageLogModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Attendance Modal -->
    <div id="viewAttendanceModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="color: #2E8B57; margin: 0; font-size: 1.3em;">View Attendance Records</h2>
                <span class="close" onclick="closeModal('viewAttendanceModal')">&times;</span>
            </div>
            
            <!-- Filter Form -->
            <form id="attendanceFilterForm" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="filter_lab_id">Lab:</label>
                        <select id="filter_lab_id" name="lab_id">
                            <option value="">All Labs</option>
                            <?php foreach ($lab_schedules as $lab): ?>
                                <option value="<?php echo $lab['Lab_ID']; ?>">
                                    <?php echo htmlspecialchars($lab['Lab_Name']) . " (Group " . htmlspecialchars($lab['Group_No']) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter_group_no">Group:</label>
                        <select id="filter_group_no" name="group_no">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $group_no): ?>
                                <option value="<?php echo $group_no; ?>">Group <?php echo $group_no; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="filter_date">Date:</label>
                        <input type="date" id="filter_date" name="date">
                    </div>
                    <div class="form-group" style="display: flex; align-items: end;">
                        <button type="button" class="btn btn-primary" onclick="loadAttendanceRecords()">Filter Records</button>
                    </div>
                </div>
            </form>
            
            <!-- Results Container -->
            <div id="attendanceResults">
                <p style="text-align: center; color: #666;">Click "Filter Records" to view attendance data</p>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewAttendanceModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- View Usage Logs Modal -->
    <div id="viewUsageLogModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="color: #2E8B57; margin: 0; font-size: 1.3em;">View Lab Usage Logs</h2>
                <span class="close" onclick="closeModal('viewUsageLogModal')">&times;</span>
            </div>
            
            <!-- Filter Form -->
            <form id="usageLogFilterForm" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="usage_filter_lab_name">Lab Name:</label>
                        <input type="text" id="usage_filter_lab_name" name="lab_name" placeholder="Search by lab name...">
                    </div>
                    <div class="form-group">
                        <label for="usage_filter_group_no">Group:</label>
                        <select id="usage_filter_group_no" name="group_no">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $group_no): ?>
                                <option value="<?php echo $group_no; ?>">Group <?php echo $group_no; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="usage_filter_date">Date:</label>
                        <input type="date" id="usage_filter_date" name="date">
                    </div>
                    <div class="form-group" style="display: flex; align-items: end;">
                        <button type="button" class="btn btn-primary" onclick="loadUsageLogs()">Filter Logs</button>
                    </div>
                </div>
            </form>
            
            <!-- Results Container -->
            <div id="usageLogResults">
                <p style="text-align: center; color: #666;">Click "Filter Logs" to view usage data</p>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewUsageLogModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div id="editAttendanceModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="color: #2E8B57; margin: 0; font-size: 1.3em;">Edit Attendance</h2>
                <span class="close" onclick="closeModal('editAttendanceModal')">&times;</span>
            </div>
            <form action="update_attendance.php" method="POST" id="editAttendanceForm">
                <input type="hidden" id="edit_attendance_id" name="attendance_id">
                <div class="form-group">
                    <label for="edit_attendance_status">Attendance Status:</label>
                    <select id="edit_attendance_status" name="attendance_status" required>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Update Attendance</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editAttendanceModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="color: #2E8B57; margin: 0; font-size: 1.3em;">Reschedule Lab</h2>
                <span class="close" onclick="closeModal('rescheduleModal')">&times;</span>
            </div>
            <form action="process_instructor_reschedule.php" method="POST" id="rescheduleForm">
                <input type="hidden" id="request_id" name="request_id">
                <input type="hidden" id="student_id" name="student_id">
                <input type="hidden" id="lab_id" name="lab_id">
                <div class="form-group">
                    <label for="student_name">Student:</label>
                    <input type="text" id="student_name" readonly style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label for="lab_name">Lab:</label>
                    <input type="text" id="lab_name" readonly style="background: #f8f9fa;">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_date">New Date:</label>
                        <input type="date" id="new_date" name="new_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="new_time">New Time:</label>
                        <input type="time" id="new_time" name="new_time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="location">Location:</label>
                    <input type="text" id="location" name="location" required placeholder="Enter new lab location">
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Schedule Reschedule</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rescheduleModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentRequestId = null;
        let isSubmitting = false;

        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Auto-load data for view modals
            if (modalId === 'viewAttendanceModal') {
                setTimeout(() => loadAttendanceRecords(), 300);
            } else if (modalId === 'viewUsageLogModal') {
                setTimeout(() => loadUsageLogs(), 300);
            }
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            if (modalId === 'attendanceModal') {
                document.getElementById('studentsList').innerHTML = '';
                document.getElementById('lab_id').selectedIndex = 0;
            }
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="block"]');
                if (openModal) {
                    closeModal(openModal.id);
                }
            }
        });

        // Load attendance records with filters
        function loadAttendanceRecords() {
            const formData = new FormData(document.getElementById('attendanceFilterForm'));
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'view_attendance_records.php', true);
            xhr.onload = function() {
                document.getElementById('attendanceResults').innerHTML = xhr.responseText;
            };
            xhr.send(formData);
        }

        // Load usage logs with filters
        function loadUsageLogs() {
            const formData = new FormData(document.getElementById('usageLogFilterForm'));
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'view_usage_logs.php', true);
            xhr.onload = function() {
                document.getElementById('usageLogResults').innerHTML = xhr.responseText;
            };
            xhr.send(formData);
        }

        // Edit attendance function
        function editAttendance(attendanceId, currentStatus) {
            document.getElementById('edit_attendance_id').value = attendanceId;
            document.getElementById('edit_attendance_status').value = currentStatus;
            openModal('editAttendanceModal');
        }

        // AJAX to load students for attendance
        function loadStudents() {
            var lab_id = document.getElementById('lab_id').value;
            var attendance_date = document.getElementById('attendance_date').value;
            if (!lab_id) {
                document.getElementById('studentsList').innerHTML = '';
                return;
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'get_students.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                document.getElementById('studentsList').innerHTML = xhr.responseText;
            };
            // Extract group_no from lab_id dropdown text (e.g., "Lab Name (Group 5) - Date")
            var group_no = document.getElementById('lab_id').selectedOptions[0].text.match(/Group (\d+)/)[1];
            xhr.send('lab_id=' + encodeURIComponent(lab_id) + '&date=' + encodeURIComponent(attendance_date) + '&group_no=' + encodeURIComponent(group_no));
        }

        // Reschedule Functions
        function rescheduleRequest(requestId, labName, studentName, studentId, labId) {
            currentRequestId = requestId;
            document.getElementById('request_id').value = requestId;
            document.getElementById('student_id').value = studentId;
            document.getElementById('lab_id').value = labId;
            document.getElementById('lab_name').value = labName;
            document.getElementById('student_name').value = studentName;
            openModal('rescheduleModal');
        }

        // Function to remove row with animation
        function removeRequestRow(requestId) {
            const row = document.getElementById('request-row-' + requestId);
            if (row) {
                row.classList.add('removing');
                setTimeout(() => {
                    row.remove();
                    updateTableAndStats();
                }, 500);
            }
        }

        function updateTableAndStats() {
            const tbody = document.getElementById('approvedRequestsTable');
            const remainingRows = tbody.querySelectorAll('tr:not(#emptyStateRow)');
            if (remainingRows.length === 0) {
                tbody.innerHTML = '<tr id="emptyStateRow"><td colspan="8" class="empty-state">No approved requests requiring action</td></tr>';
                const badge = document.getElementById('pendingBadge');
                if (badge) badge.remove();
            } else {
                // Update counts
                const remainingCount = remainingRows.length;
                const badge = document.getElementById('pendingBadge');
                if (badge) badge.textContent = remainingCount + ' Pending';
            }
        }

        function viewLabSchedules() {
            document.querySelector('.card:nth-last-child(2)').scrollIntoView({ behavior: 'smooth' });
        }

        function refreshDashboard() {
            location.reload();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('new_date').min = today;
        });
    </script>
</body>
</html>
