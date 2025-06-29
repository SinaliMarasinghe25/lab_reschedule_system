<?php
session_start();

// Get user info before destroying session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

// Log logout action to database
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    try {
        require_once 'config.php';
        
        $user_id = $_SESSION['user_id'];
        
        // Convert role for database
        $user_type_map = [
            'student' => 'Student',
            'instructor' => 'Instructor', 
            'coordinator' => 'Coordinator'
        ];
        
        $user_type = $user_type_map[$user_role] ?? 'Unknown';
        
        // Insert logout log
        $stmt = $pdo->prepare("
            INSERT INTO Usage_Log (User_ID, User_Type, Action, Lab_Name, Timestamp) 
            VALUES (?, ?, 'User Logout', ?, NOW())
        ");
        $stmt->execute([$user_id, $user_type, "Logout from " . ucfirst($user_role) . " Dashboard"]);
        
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Start new session for logout message
session_start();

// Set role-specific logout message
$role_messages = [
    'student' => 'Student logged out successfully. Thank you for using the Lab Reschedule System!',
    'instructor' => 'Instructor logged out successfully. All lab sessions have been saved.',
    'coordinator' => 'Coordinator logged out successfully. All pending requests have been preserved.',
    '' => 'You have been successfully logged out.'
];

$_SESSION['success'] = $role_messages[$user_role] ?? $role_messages[''];

// Redirect to login page
header('Location: index.php');
exit();
?>
