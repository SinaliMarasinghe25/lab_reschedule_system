<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $group_no = isset($_POST['group_no']) ? $_POST['group_no'] : null;
    
    // Validation
    if (empty($name) || empty($email) || empty($department) || empty($role) || empty($password)) {
        $_SESSION['error'] = 'All fields are required!';
        header('Location: register.php');
        exit();
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match!';
        header('Location: register.php');
        exit();
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters long!';
        header('Location: register.php');
        exit();
    }
    
    if ($role === 'student' && empty($group_no)) {
        $_SESSION['error'] = 'Group number is required for students!';
        header('Location: register.php');
        exit();
    }
    
    try {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if email exists
        $tables = ['Student', 'Lab_Instructor', 'Subject_Coordinator'];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT Email FROM $table WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Email already exists!';
                header('Location: register.php');
                exit();
            }
        }
        
        // Insert based on role
        switch ($role) {
            case 'student':
                $stmt = $pdo->prepare("INSERT INTO Student (Student_Name, Email, Department, Group_No, Password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $department, $group_no, $hashed_password]);
                break;
                
            case 'instructor':
                $stmt = $pdo->prepare("INSERT INTO Lab_Instructor (Instructor_Name, Email, Department, Password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $department, $hashed_password]);
                break;
                
            case 'coordinator':
                $stmt = $pdo->prepare("INSERT INTO Subject_Coordinator (Coordinator_Name, Email, Department, Password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $department, $hashed_password]);
                break;
        }
        
        $_SESSION['success'] = 'Registration successful! Please login with your credentials.';
        header('Location: index.php');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: register.php');
exit();
?>
