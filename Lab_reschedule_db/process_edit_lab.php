<?php
session_start();
require_once 'config.php';

// Only allow coordinators
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_id = $_POST['lab_id'];
    $lab_name = $_POST['lab_name'];
    $location = $_POST['location'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $group_no = $_POST['group_no'];
    $instructor_id = $_POST['instructor_id'] ?: null;

    // Update the lab schedule
    $stmt = $pdo->prepare("UPDATE Lab_Schedule_Table SET Lab_Name=?, Location=?, Date=?, Time=?, Group_No=?, Instructor_ID=? WHERE Lab_ID=?");
    $stmt->execute([$lab_name, $location, $date, $time, $group_no, $instructor_id, $lab_id]);

    $_SESSION['success'] = "Lab schedule updated successfully.";
    header("Location: coordinator_dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: coordinator_dashboard.php");
    exit();
}
?>
