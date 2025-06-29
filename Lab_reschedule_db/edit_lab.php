<?php
// edit_lab.php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['lab_id'])) {
    echo "Lab ID not specified.";
    exit();
}

$lab_id = intval($_GET['lab_id']);

// Fetch lab details
$stmt = $pdo->prepare("SELECT * FROM Lab_Schedule_Table WHERE Lab_ID = ?");
$stmt->execute([$lab_id]);
$lab = $stmt->fetch();

if (!$lab) {
    echo "Lab not found.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_name = $_POST['lab_name'];
    $location = $_POST['location'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $group_no = $_POST['group_no'];

    $stmt = $pdo->prepare("UPDATE Lab_Schedule_Table SET Lab_Name=?, Location=?, Date=?, Time=?, Group_No=? WHERE Lab_ID=?");
    $stmt->execute([$lab_name, $location, $date, $time, $group_no, $lab_id]);
    header("Location: coordinator_dashboard.php?success=Lab updated");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Lab Schedule</title>
</head>
<body>
    <h2>Edit Lab Schedule</h2>
    <form method="post">
        <label>Lab Name: <input type="text" name="lab_name" value="<?php echo htmlspecialchars($lab['Lab_Name']); ?>" required></label><br>
        <label>Location: <input type="text" name="location" value="<?php echo htmlspecialchars($lab['Location']); ?>" required></label><br>
        <label>Date: <input type="date" name="date" value="<?php echo htmlspecialchars($lab['Date']); ?>" required></label><br>
        <label>Time: <input type="time" name="time" value="<?php echo htmlspecialchars($lab['Time']); ?>" required></label><br>
        <label>Group No: <input type="number" name="group_no" value="<?php echo htmlspecialchars($lab['Group_No']); ?>" required></label><br>
        <button type="submit">Update</button>
    </form>
    <a href="coordinator_dashboard.php">Back to Dashboard</a>
</body>
</html>
