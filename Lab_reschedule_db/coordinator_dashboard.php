<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header('Location: index.php');
    exit();
}

$coordinator_id = $_SESSION['user_id'];
$coordinator_name = $_SESSION['user_name'];

// Get pending reschedule requests
$stmt = $pdo->prepare("
    SELECT rr.*, s.Student_Name, s.Group_No as Student_Group, ls.Lab_Name, ls.Date as Original_Date, ls.Time as Original_Time, ls.Location as Original_Location
    FROM Reschedule_Request rr
    JOIN Student s ON rr.Student_ID = s.Student_ID
    JOIN Lab_Schedule_Table ls ON rr.Lab_ID = ls.Lab_ID
    WHERE rr.Coordinator_ID = ? AND rr.Status = 'Pending'
    ORDER BY rr.Created_At ASC
");
$stmt->execute([$coordinator_id]);
$pending_requests = $stmt->fetchAll();

// Get all reschedule requests for this coordinator
$stmt = $pdo->prepare("
    SELECT rr.*, s.Student_Name, s.Group_No as Student_Group, ls.Lab_Name, ls.Date as Original_Date, ls.Time as Original_Time
    FROM Reschedule_Request rr
    JOIN Student s ON rr.Student_ID = s.Student_ID
    JOIN Lab_Schedule_Table ls ON rr.Lab_ID = ls.Lab_ID
    WHERE rr.Coordinator_ID = ?
    ORDER BY rr.Created_At DESC
    LIMIT 50
");
$stmt->execute([$coordinator_id]);
$all_requests = $stmt->fetchAll();

// Get lab schedules managed by this coordinator
$stmt = $pdo->prepare("
    SELECT ls.*, li.Instructor_Name
    FROM Lab_Schedule_Table ls
    LEFT JOIN Lab_Instructor li ON ls.Instructor_ID = li.Instructor_ID
    WHERE ls.Coordinator_ID = ?
    ORDER BY ls.Date ASC, ls.Time ASC
");
$stmt->execute([$coordinator_id]);
$lab_schedules = $stmt->fetchAll();

// Get instructors for dropdown
$instructors = $pdo->query("SELECT Instructor_ID, Instructor_Name, Department FROM Lab_Instructor ORDER BY Instructor_Name")->fetchAll();

// Get notifications for coordinator
$stmt = $pdo->prepare("
    SELECT * FROM Notification_Table 
    WHERE Recipient_Email = ? 
    ORDER BY Created_At DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_email']]);
$notifications = $stmt->fetchAll();

// Get reschedule table entries
$stmt = $pdo->prepare("
    SELECT rt.*, s.Student_Name, s.Group_No as Student_Group, ls.Lab_Name, li.Instructor_Name
    FROM Reschedule_Table rt
    JOIN Student s ON rt.Student_ID = s.Student_ID
    JOIN Lab_Schedule_Table ls ON rt.Original_Lab_ID = ls.Lab_ID
    LEFT JOIN Lab_Instructor li ON rt.Instructor_ID = li.Instructor_ID
    WHERE ls.Coordinator_ID = ?
    ORDER BY rt.New_Date ASC
");
$stmt->execute([$coordinator_id]);
$reschedule_table = $stmt->fetchAll();

// Get statistics
$stats = [
    'pending_requests' => count($pending_requests),
    'total_labs' => count($lab_schedules),
    'total_requests' => count($all_requests),
    'completed_reschedules' => count(array_filter($reschedule_table, function($r) { return $r['Status'] === 'Completed'; }))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard - Lab Reschedule System</title>
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
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
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

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
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
            margin: 3% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s ease-out;
        }

        @keyframes modalSlide {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2E8B57;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2E8B57;
            box-shadow: 0 0 10px rgba(46, 139, 87, 0.3);
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 32px;
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
        }

        .notification-item:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .notification-item.unread {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-left-color: #28a745;
        }

        .notification-meta {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-group .btn {
            flex: 1;
        }

        .quick-actions {
            display: grid;
            gap: 15px;
        }

        .quick-actions .btn {
            width: 100%;
            justify-self: stretch;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <h1>LAB RESCHEDULE SYSTEM</h1>
                <p>University of Jaffna - Coordinator Portal</p>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($coordinator_name); ?></span>
                <span>Role: Subject Coordinator</span>
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
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_labs']; ?></div>
                <div class="stat-label">Total Labs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                <div class="stat-label">All Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed_reschedules']; ?></div>
                <div class="stat-label">Completed Reschedules</div>
            </div>
        </div>

        <!-- Quick Actions and Notifications -->
        <div class="dashboard-grid">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="openModal('addLabModal')">
                        Add New Lab Schedule
                    </button>
                    <button class="btn btn-info" onclick="viewRescheduleTable()">
                        View Reschedule Table
                    </button>
                    <button class="btn btn-secondary" onclick="refreshDashboard()">
                        Refresh Dashboard
                    </button>
                </div>
            </div>

            <!-- Recent Notifications -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Notifications</h3>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
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

        <!-- Pending Reschedule Requests -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Reschedule Requests</h3>
                <span class="badge badge-group">Requires Action</span>
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
                            <th>Request Date</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_requests)): ?>
                            <tr><td colspan="8" class="empty-state">No pending requests</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['Student_Name']); ?></td>
                                    <td><span class="badge badge-group"><?php echo $request['Student_Group']; ?></span></td>
                                    <td><?php echo htmlspecialchars($request['Lab_Name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['Original_Date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($request['Original_Time'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['Request_Date'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($request['Reason'], 0, 50)) . (strlen($request['Reason']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <form method="POST" action="process_request_action.php" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['Request_ID']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success" onclick="return confirm('Approve this reschedule request?')">Approve</button>
                                        </form>
                                        <form method="POST" action="process_request_action.php" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['Request_ID']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this reschedule request?')">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lab Schedules -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Lab Schedules</h3>
                <button class="btn btn-primary" onclick="openModal('addLabModal')">Add New Lab</button>
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
                            <th>Instructor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lab_schedules)): ?>
                            <tr><td colspan="7" class="empty-state">No lab schedules found</td></tr>
                        <?php else: ?>
                            <?php foreach ($lab_schedules as $lab): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lab['Lab_Name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($lab['Date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($lab['Time'])); ?></td>
                                    <td><?php echo htmlspecialchars($lab['Location']); ?></td>
                                    <td><span class="badge badge-group"><?php echo $lab['Group_No']; ?></span></td>
                                    <td><?php echo htmlspecialchars($lab['Instructor_Name'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <button class="btn btn-info" onclick="editLab(<?php echo $lab['Lab_ID']; ?>, '<?php echo htmlspecialchars($lab['Lab_Name']); ?>', '<?php echo $lab['Date']; ?>', '<?php echo $lab['Time']; ?>', '<?php echo htmlspecialchars($lab['Location']); ?>', <?php echo $lab['Group_No']; ?>, <?php echo $lab['Instructor_ID'] ?? 'null'; ?>)">Edit</button>
                                    </td>
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
                            <th>Instructor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reschedule_table)): ?>
                            <tr><td colspan="8" class="empty-state">No reschedules found</td></tr>
                        <?php else: ?>
                            <?php foreach ($reschedule_table as $reschedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reschedule['Student_Name']); ?></td>
                                    <td><span class="badge badge-group"><?php echo $reschedule['Student_Group']; ?></span></td>
                                    <td><?php echo htmlspecialchars($reschedule['Lab_Name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($reschedule['New_Date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($reschedule['New_Time'])); ?></td>
                                    <td><?php echo htmlspecialchars($reschedule['Location']); ?></td>
                                    <td><?php echo htmlspecialchars($reschedule['Instructor_Name'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($reschedule['Status']); ?>">
                                            <?php echo $reschedule['Status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- All Reschedule Requests History -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Reschedule Requests</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Group</th>
                            <th>Lab Name</th>
                            <th>Request Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_requests)): ?>
                            <tr><td colspan="7" class="empty-state">No requests found</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['Student_Name']); ?></td>
                                    <td><span class="badge badge-group"><?php echo $request['Student_Group']; ?></span></td>
                                    <td><?php echo htmlspecialchars($request['Lab_Name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['Request_Date'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($request['Reason'], 0, 40)) . (strlen($request['Reason']) > 40 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($request['Status']); ?>">
                                            <?php echo $request['Status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($request['Updated_At'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Lab Modal -->
    <div id="addLabModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addLabModal')">&times;</span>
            <h2 style="color: #2E8B57; margin-bottom: 25px;">Add New Lab Schedule</h2>
            <form action="process_add_lab.php" method="POST" id="addLabForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="lab_name">Lab Name:</label>
                        <input type="text" id="lab_name" name="lab_name" required placeholder="Enter lab name">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location:</label>
                        <input type="text" id="location" name="location" required placeholder="Enter lab location">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="group_no">Group Number:</label>
                        <input type="number" id="group_no" name="group_no" min="1" required placeholder="Enter group number">
                    </div>
                    
                    <div class="form-group">
                        <label for="instructor_id">Instructor (Optional):</label>
                        <select id="instructor_id" name="instructor_id">
                            <option value="">Select Instructor</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?php echo $instructor['Instructor_ID']; ?>">
                                    <?php echo htmlspecialchars($instructor['Instructor_Name']) . ' - ' . htmlspecialchars($instructor['Department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" id="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="time">Time:</label>
                        <input type="time" id="time" name="time" required>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Add Lab Schedule</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addLabModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Lab Modal -->
    <div id="editLabModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editLabModal')">&times;</span>
            <h2 style="color: #2E8B57; margin-bottom: 25px;">Edit Lab Schedule</h2>
            <form action="process_edit_lab.php" method="POST" id="editLabForm">
                <input type="hidden" id="edit_lab_id" name="lab_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_lab_name">Lab Name:</label>
                        <input type="text" id="edit_lab_name" name="lab_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_location">Location:</label>
                        <input type="text" id="edit_location" name="location" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_group_no">Group Number:</label>
                        <input type="number" id="edit_group_no" name="group_no" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_instructor_id">Instructor:</label>
                        <select id="edit_instructor_id" name="instructor_id">
                            <option value="">Select Instructor</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?php echo $instructor['Instructor_ID']; ?>">
                                    <?php echo htmlspecialchars($instructor['Instructor_Name']) . ' - ' . htmlspecialchars($instructor['Department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_date">Date:</label>
                        <input type="date" id="edit_date" name="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_time">Time:</label>
                        <input type="time" id="edit_time" name="time" required>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Update Lab Schedule</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editLabModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Reset forms when closing
            if (modalId === 'addLabModal') {
                document.getElementById('addLabForm').reset();
            } else if (modalId === 'editLabModal') {
                document.getElementById('editLabForm').reset();
            }
        }

        function editLab(labId, labName, date, time, location, groupNo, instructorId) {
            document.getElementById('edit_lab_id').value = labId;
            document.getElementById('edit_lab_name').value = labName;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_time').value = time;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_group_no').value = groupNo;
            document.getElementById('edit_instructor_id').value = instructorId || '';
            openModal('editLabModal');
        }

        function viewRescheduleTable() {
            // Scroll to reschedule table
            document.querySelector('.card:nth-of-type(4)').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function refreshDashboard() {
            location.reload();
        }

        // Form validation for Add Lab
        document.getElementById('addLabForm').addEventListener('submit', function(e) {
            const labName = document.getElementById('lab_name').value.trim();
            const location = document.getElementById('location').value.trim();
            const groupNo = document.getElementById('group_no').value;
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            
            if (!labName || !location || !groupNo || !date || !time) {
                e.preventDefault();
                alert('Please fill in all required fields!');
                return false;
            }
            
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                e.preventDefault();
                alert('Lab date cannot be in the past!');
                return false;
            }
            
            if (groupNo < 1) {
                e.preventDefault();
                alert('Group number must be greater than 0!');
                return false;
            }
            
            return confirm('Are you sure you want to add this lab schedule?');
        });

        // Form validation for Edit Lab
        document.getElementById('editLabForm').addEventListener('submit', function(e) {
            return confirm('Are you sure you want to update this lab schedule?');
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

        // Auto-refresh notifications every 5 minutes
        setInterval(function() {
            // You can implement AJAX call here to refresh notifications
        }, 300000);

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date for date inputs to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').min = today;
            document.getElementById('edit_date').min = today;
        });
    </script>
</body>
</html>
