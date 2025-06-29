<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'];

// Get student's group number and details
$stmt = $pdo->prepare("SELECT Group_No, Department, Email FROM Student WHERE Student_ID = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
$group_no = $student['Group_No'];
$student_department = $student['Department'];
$student_email = $student['Email'];

// Get lab schedules for student's group
$stmt = $pdo->prepare("
    SELECT ls.*, sc.Coordinator_Name, sc.Coordinator_ID, li.Instructor_Name 
    FROM Lab_Schedule_Table ls
    LEFT JOIN Subject_Coordinator sc ON ls.Coordinator_ID = sc.Coordinator_ID
    LEFT JOIN Lab_Instructor li ON ls.Instructor_ID = li.Instructor_ID
    WHERE ls.Group_No = ?
    ORDER BY ls.Date ASC, ls.Time ASC
");
$stmt->execute([$group_no]);
$lab_schedules = $stmt->fetchAll();

// Get student's reschedule requests
$stmt = $pdo->prepare("
    SELECT rr.*, ls.Lab_Name, ls.Date as Original_Date, ls.Time as Original_Time,
           sc.Coordinator_Name
    FROM Reschedule_Request rr
    JOIN Lab_Schedule_Table ls ON rr.Lab_ID = ls.Lab_ID
    JOIN Subject_Coordinator sc ON rr.Coordinator_ID = sc.Coordinator_ID
    WHERE rr.Student_ID = ?
    ORDER BY rr.Created_At DESC
");
$stmt->execute([$student_id]);
$reschedule_requests = $stmt->fetchAll();

// Get notifications for student
$stmt = $pdo->prepare("
    SELECT * FROM Notification_Table 
    WHERE Recipient_Email = ? 
    ORDER BY Created_At DESC 
    LIMIT 15
");
$stmt->execute([$student_email]);
$notifications = $stmt->fetchAll();

// Get rescheduled labs for student
$stmt = $pdo->prepare("
    SELECT rt.*, ls.Lab_Name, li.Instructor_Name
    FROM Reschedule_Table rt
    JOIN Lab_Schedule_Table ls ON rt.Original_Lab_ID = ls.Lab_ID
    LEFT JOIN Lab_Instructor li ON rt.Instructor_ID = li.Instructor_ID
    WHERE rt.Student_ID = ?
    ORDER BY rt.New_Date ASC
");
$stmt->execute([$student_id]);
$rescheduled_labs = $stmt->fetchAll();

// Get upcoming labs (within next 7 days)
$stmt = $pdo->prepare("
    SELECT ls.*, sc.Coordinator_Name, li.Instructor_Name 
    FROM Lab_Schedule_Table ls
    LEFT JOIN Subject_Coordinator sc ON ls.Coordinator_ID = sc.Coordinator_ID
    LEFT JOIN Lab_Instructor li ON ls.Instructor_ID = li.Instructor_ID
    WHERE ls.Group_No = ? AND ls.Date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ls.Date ASC, ls.Time ASC
    LIMIT 5
");
$stmt->execute([$group_no]);
$upcoming_labs = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_labs' => count($lab_schedules),
    'pending_requests' => count(array_filter($reschedule_requests, function($r) { return $r['Status'] === 'Pending'; })),
    'approved_requests' => count(array_filter($reschedule_requests, function($r) { return $r['Status'] === 'Approved'; })),
    'rescheduled_labs' => count($rescheduled_labs),
    'unread_notifications' => count(array_filter($notifications, function($n) { return !$n['Is_Read']; }))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Lab Reschedule System</title>
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
            padding: 12px 24px;
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
            border: 2px solid transparent;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #228B22, #2E8B57);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .quick-actions {
            display: grid;
            gap: 15px;
        }

        .quick-actions .btn {
            width: 100%;
            justify-self: stretch;
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
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-scheduled {
            background: #cce5f4;
            color: #0c5aa6;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* UPDATED MODAL STYLES */
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
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s ease-out;
        }

        @keyframes modalSlide {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-height: 600px) {
            .modal-content {
                margin: 1% auto;
                max-height: 95vh;
                padding: 15px;
            }
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
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2E8B57;
            box-shadow: 0 0 10px rgba(46, 139, 87, 0.3);
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
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

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            position: sticky;
            bottom: 0;
            background: white;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .btn-group .btn {
            flex: 1;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
        }

        .lab-details {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #90EE90;
            margin-bottom: 15px;
        }

        .char-counter {
            font-size: 0.85em;
            color: #666;
            text-align: right;
            margin-top: 5px;
        }

        .char-counter.warning {
            color: #ffc107;
        }

        .char-counter.danger {
            color: #dc3545;
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

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .user-info {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <h1>LAB RESCHEDULE SYSTEM</h1>
                <p>University of Jaffna - Student Portal</p>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <span>Group: <?php echo $group_no; ?></span>
                <span><?php echo htmlspecialchars($student_department); ?></span>
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
                <div class="stat-number"><?php echo $stats['total_labs']; ?></div>
                <div class="stat-label">Total Labs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">Pending Requests</div>
                <?php if ($stats['pending_requests'] > 0): ?>
                    <div class="stat-badge"><?php echo $stats['pending_requests']; ?></div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved_requests']; ?></div>
                <div class="stat-label">Approved Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rescheduled_labs']; ?></div>
                <div class="stat-label">Rescheduled Labs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unread_notifications']; ?></div>
                <div class="stat-label">Unread Notifications</div>
                <?php if ($stats['unread_notifications'] > 0): ?>
                    <div class="stat-badge"><?php echo $stats['unread_notifications']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions and Upcoming Labs -->
        <div class="dashboard-grid">
            <!-- Updated Quick Actions (Removed Mark Attendance) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="openModal('rescheduleModal')">
                        Request Reschedule
                    </button>
                    <button class="btn btn-secondary" onclick="refreshDashboard()">
                        Refresh Dashboard
                    </button>
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
                                    Instructor: <?php echo htmlspecialchars($lab['Instructor_Name'] ?? 'Not Assigned'); ?>
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

        <!-- Lab Schedule -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">My Lab Schedule</h3>
                <span class="badge badge-group">Group <?php echo $group_no; ?></span>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Lab Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Coordinator</th>
                            <th>Instructor</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lab_schedules)): ?>
                            <tr><td colspan="7" class="empty-state">No labs scheduled</td></tr>
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
                                    <td><?php echo htmlspecialchars($lab['Coordinator_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($lab['Instructor_Name'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <button class="btn btn-primary" 
                                                onclick="requestReschedule(<?php echo $lab['Lab_ID']; ?>, '<?php echo htmlspecialchars($lab['Lab_Name']); ?>', '<?php echo $lab['Date']; ?>', '<?php echo $lab['Time']; ?>', '<?php echo htmlspecialchars($lab['Location']); ?>', <?php echo $lab['Coordinator_ID']; ?>, '<?php echo htmlspecialchars($lab['Coordinator_Name']); ?>')">
                                            Request Reschedule
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reschedule Requests -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">My Reschedule Requests</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Lab Name</th>
                            <th>Original Date</th>
                            <th>Request Date</th>
                            <th>Reason</th>
                            <th>Coordinator</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reschedule_requests)): ?>
                            <tr><td colspan="6" class="empty-state">No reschedule requests</td></tr>
                        <?php else: ?>
                            <?php foreach ($reschedule_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['Lab_Name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['Original_Date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['Request_Date'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($request['Reason'], 0, 50)) . (strlen($request['Reason']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($request['Coordinator_Name']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($request['Status']); ?>">
                                            <?php echo $request['Status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rescheduled Labs -->
        <?php if (!empty($rescheduled_labs)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Rescheduled Labs</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Lab Name</th>
                            <th>New Date</th>
                            <th>New Time</th>
                            <th>Location</th>
                            <th>Instructor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rescheduled_labs as $lab): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lab['Lab_Name']); ?></td>
                                <td>
                                    <?php 
                                    $newDate = date('M j, Y', strtotime($lab['New_Date']));
                                    $isUpcoming = strtotime($lab['New_Date']) <= strtotime('+7 days');
                                    echo $newDate;
                                    if ($isUpcoming && strtotime($lab['New_Date']) >= strtotime('today')) {
                                        echo ' <span class="badge badge-urgent">Upcoming</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('H:i', strtotime($lab['New_Time'])); ?></td>
                                <td><?php echo htmlspecialchars($lab['Location']); ?></td>
                                <td><?php echo htmlspecialchars($lab['Instructor_Name'] ?? 'Not Assigned'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($lab['Status']); ?>">
                                        <?php echo $lab['Status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Enhanced Reschedule Request Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="color: #2E8B57; margin: 0; font-size: 1.3em;">Request Lab Reschedule</h2>
                <span class="close" onclick="closeModal('rescheduleModal')">&times;</span>
            </div>
            
            <form action="process_reschedule_request.php" method="POST" id="rescheduleForm">
                <div class="form-group">
                    <label for="lab_id">Select Lab:</label>
                    <select id="lab_id" name="lab_id" required onchange="updateCoordinator()">
                        <option value="">Select Lab</option>
                        <?php foreach ($lab_schedules as $lab): ?>
                            <option value="<?php echo $lab['Lab_ID']; ?>" 
                                    data-coordinator-id="<?php echo $lab['Coordinator_ID'] ?? ''; ?>"
                                    data-coordinator-name="<?php echo htmlspecialchars($lab['Coordinator_Name'] ?? 'Not Assigned'); ?>"
                                    data-date="<?php echo $lab['Date']; ?>"
                                    data-time="<?php echo $lab['Time']; ?>"
                                    data-location="<?php echo htmlspecialchars($lab['Location']); ?>"
                                    data-instructor="<?php echo htmlspecialchars($lab['Instructor_Name'] ?? 'Not Assigned'); ?>">
                                <?php echo htmlspecialchars($lab['Lab_Name']) . ' - ' . date('M j, Y', strtotime($lab['Date'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="coordinator_id">Coordinator:</label>
                    <select id="coordinator_id" name="coordinator_id" required>
                        <option value="">Select Lab First</option>
                    </select>
                </div>
                
                <div class="form-group" id="labDetailsGroup" style="display: none;">
                    <label>Current Lab Schedule:</label>
                    <div id="labDetails" class="lab-details">
                        <!-- Lab details will be populated here -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason for Reschedule:</label>
                    <textarea id="reason" name="reason" required minlength="10" maxlength="500" 
                             placeholder="Please provide a detailed reason (minimum 10 characters)"
                             oninput="updateCharCounter()"></textarea>
                    <div id="charCounter" class="char-counter">0/500</div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rescheduleModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Focus on first input for better accessibility
            setTimeout(() => {
                const firstInput = modal.querySelector('select, input, textarea');
                if (firstInput) firstInput.focus();
            }, 100);
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            
            if (modalId === 'rescheduleModal') {
                document.getElementById('rescheduleForm').reset();
                document.getElementById('coordinator_id').innerHTML = '<option value="">Select Lab First</option>';
                document.getElementById('labDetailsGroup').style.display = 'none';
                updateCharCounter();
            }
        }

        function requestReschedule(labId, labName, date, time, location, coordinatorId, coordinatorName) {
            document.getElementById('lab_id').value = labId;
            updateCoordinator();
            openModal('rescheduleModal');
        }

        function updateCoordinator() {
            const labSelect = document.getElementById('lab_id');
            const coordinatorSelect = document.getElementById('coordinator_id');
            const labDetailsGroup = document.getElementById('labDetailsGroup');
            const labDetails = document.getElementById('labDetails');
            
            const selectedOption = labSelect.options[labSelect.selectedIndex];
            
            if (selectedOption.value) {
                const coordinatorId = selectedOption.dataset.coordinatorId;
                const coordinatorName = selectedOption.dataset.coordinatorName;
                const date = selectedOption.dataset.date;
                const time = selectedOption.dataset.time;
                const location = selectedOption.dataset.location;
                const instructor = selectedOption.dataset.instructor;
                
                // Update coordinator dropdown
                coordinatorSelect.innerHTML = `<option value="${coordinatorId}">${coordinatorName}</option>`;
                coordinatorSelect.value = coordinatorId;
                
                // Show lab details
                labDetails.innerHTML = `
                    <strong>Lab:</strong> ${selectedOption.text.split(' - ')[0]}<br>
                    <strong>Current Date:</strong> ${new Date(date).toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })}<br>
                    <strong>Current Time:</strong> ${time}<br>
                    <strong>Location:</strong> ${location}<br>
                    <strong>Coordinator:</strong> ${coordinatorName}<br>
                    <strong>Instructor:</strong> ${instructor}
                `;
                labDetailsGroup.style.display = 'block';
            } else {
                coordinatorSelect.innerHTML = '<option value="">Select Lab First</option>';
                labDetailsGroup.style.display = 'none';
            }
        }

        function updateCharCounter() {
            const textarea = document.getElementById('reason');
            const counter = document.getElementById('charCounter');
            
            if (textarea && counter) {
                const charCount = textarea.value.length;
                counter.textContent = `${charCount}/500`;
                
                // Remove existing classes
                counter.classList.remove('warning', 'danger');
                textarea.style.borderColor = '#e9ecef';
                
                if (charCount < 10) {
                    textarea.style.borderColor = '#dc3545';
                    counter.classList.add('danger');
                } else if (charCount > 450) {
                    counter.classList.add('warning');
                    textarea.style.borderColor = '#ffc107';
                } else {
                    textarea.style.borderColor = '#28a745';
                }
            }
        }

        function refreshDashboard() {
            location.reload();
        }

        // Form validation
        document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('reason').value.trim();
            const labId = document.getElementById('lab_id').value;
            const coordinatorId = document.getElementById('coordinator_id').value;
            
            if (!labId) {
                e.preventDefault();
                alert('Please select a lab!');
                return false;
            }
            
            if (!coordinatorId) {
                e.preventDefault();
                alert('Please select a coordinator!');
                return false;
            }
            
            if (reason.length < 10) {
                e.preventDefault();
                alert('Please provide a detailed reason (minimum 10 characters)!');
                return false;
            }
            
            if (reason.length > 500) {
                e.preventDefault();
                alert('Reason is too long (maximum 500 characters)!');
                return false;
            }
            
            return confirm('Are you sure you want to submit this reschedule request?');
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        }

        // Handle escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="block"]');
                if (openModal) {
                    closeModal(openModal.id);
                }
            }
        });

        // Initialize character counter
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCounter();
        });
    </script>
</body>
</html>
