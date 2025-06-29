<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    try {
        $table = '';
        $id_field = '';
        $name_field = '';
        
        switch ($role) {
            case 'student':
                $table = 'Student';
                $id_field = 'Student_ID';
                $name_field = 'Student_Name';
                break;
            case 'instructor':
                $table = 'Lab_Instructor';
                $id_field = 'Instructor_ID';
                $name_field = 'Instructor_Name';
                break;
            case 'coordinator':
                $table = 'Subject_Coordinator';
                $id_field = 'Coordinator_ID';
                $name_field = 'Coordinator_Name';
                break;
        }
        
        $stmt = $pdo->prepare("SELECT $id_field, $name_field, Email, Password FROM $table WHERE Email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['Password'])) {
            $_SESSION['user_id'] = $user[$id_field];
            $_SESSION['user_name'] = $user[$name_field];
            $_SESSION['user_email'] = $user['Email'];
            $_SESSION['user_role'] = $role;
            
            // Redirect to appropriate dashboard
            switch ($role) {
                case 'student':
                    header('Location: student_dashboard.php');
                    break;
                case 'instructor':
                    header('Location: instructor_dashboard.php');
                    break;
                case 'coordinator':
                    header('Location: coordinator_dashboard.php');
                    break;
            }
            exit();
        } else {
            $_SESSION['error'] = 'Invalid email or password!';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
}

header('Location: index.php');
exit();
?>
