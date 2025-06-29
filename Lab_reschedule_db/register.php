<?php
// Start session BEFORE any HTML output
session_start();

// Handle error and success messages
$error_message = '';
$success_message = '';

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Lab Reschedule System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow: hidden; /* Prevent page scroll */
        }

        body {
            background: linear-gradient(135deg, #06402B 0%, #0d2818 25%, #1a4c3a 50%, #2d5a4a 75%, #3d7c47 100%);
            height: 100vh;
            width: 100vw;
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed; /* Fix body to prevent scroll */
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        /* Enhanced Background Effects */
        .bg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(61, 124, 71, 0.2) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(13, 40, 24, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 40%, rgba(26, 76, 58, 0.15) 0%, transparent 50%);
            z-index: 1;
            pointer-events: none;
        }

        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 2;
            pointer-events: none;
        }

        .floating-shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(61, 124, 71, 0.08), rgba(26, 76, 58, 0.12));
            animation: float 15s ease-in-out infinite;
        }

        .shape-1 {
            width: 120px;
            height: 120px;
            top: 15%;
            left: 8%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 80px;
            height: 80px;
            top: 65%;
            right: 12%;
            animation-delay: 5s;
        }

        .shape-3 {
            width: 100px;
            height: 100px;
            bottom: 20%;
            left: 20%;
            animation-delay: 10s;
        }

        .shape-4 {
            width: 60px;
            height: 60px;
            top: 30%;
            right: 30%;
            animation-delay: 7s;
        }

        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
                opacity: 0.1; 
            }
            50% { 
                transform: translateY(-15px) rotate(180deg); 
                opacity: 0.2; 
            }
        }

        /* Container that fits screen perfectly */
        .container {
            background: rgba(255, 255, 255, 0.97);
            padding: 20px 18px;
            border-radius: 20px;
            box-shadow: 
                0 25px 50px rgba(6, 64, 43, 0.3),
                0 15px 30px rgba(13, 40, 24, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            text-align: center;
            width: 90%;
            max-width: 420px;
            max-height: 95vh; /* Ensure it fits screen height */
            backdrop-filter: blur(15px);
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow-y: auto; /* Allow internal scroll if needed */
            overflow-x: hidden;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logo-section {
            margin-bottom: 18px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #3d7c47 0%, #1a4c3a 50%, #06402B 100%);
            border-radius: 12px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1em;
            font-weight: 700;
            color: white;
            box-shadow: 0 6px 12px rgba(6, 64, 43, 0.25);
        }

        .system-title {
            font-size: 1.2em;
            font-weight: 700;
            background: linear-gradient(135deg, #06402B 0%, #1a4c3a 50%, #3d7c47 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
            line-height: 1.3;
        }

        .university-name {
            font-size: 0.75em;
            color: #4a6b4a;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 1em;
            color: #06402B;
            font-weight: 600;
            margin-bottom: 16px;
            opacity: 0.9;
        }

        .register-form {
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 12px;
            text-align: left;
        }

        .form-label {
            display: block;
            margin-bottom: 4px;
            color: #06402B;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid rgba(6, 64, 43, 0.1);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            color: #06402B;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #3d7c47;
            background: white;
            box-shadow: 0 0 0 3px rgba(61, 124, 71, 0.1);
            transform: translateY(-1px);
        }

        .form-input::placeholder {
            color: rgba(6, 64, 43, 0.5);
            font-weight: 400;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3d7c47 0%, #1a4c3a 50%, #06402B 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 5px 10px rgba(6, 64, 43, 0.25);
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #06402B 0%, #1a4c3a 50%, #3d7c47 100%);
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(6, 64, 43, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #3d7c47;
            padding: 8px 16px;
            border: 2px solid rgba(61, 124, 71, 0.8);
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-secondary:hover {
            background: #3d7c47;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(61, 124, 71, 0.25);
        }

        .form-divider {
            margin: 10px 0;
            text-align: center;
            position: relative;
        }

        .form-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(6, 64, 43, 0.2), transparent);
        }

        .divider-text {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 12px;
            color: #4a6b4a;
            font-weight: 500;
            font-size: 9px;
        }

        .alert-message {
            padding: 9px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 10px;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-left: 3px solid #dc3545;
        }

        .alert-success {
            background: rgba(61, 124, 71, 0.1);
            color: #3d7c47;
            border: 1px solid rgba(61, 124, 71, 0.2);
            border-left: 3px solid #3d7c47;
        }

        .back-home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 11px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 20;
        }

        .back-home-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-3px);
        }

        /* Custom Select Styling */
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2306402B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
        }

        /* Conditional Fields */
        .group-no-field, .coordinator-field {
            display: none;
            animation: slideDown 0.3s ease-out;
        }

        .group-no-field.show, .coordinator-field.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                max-height: 80px;
                transform: translateY(0);
            }
        }

        /* Loading State */
        .loading .container {
            opacity: 0.8;
            pointer-events: none;
        }

        .loading .btn-primary::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 12px;
            height: 12px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Responsive Design for Different Screen Sizes */
        @media (max-height: 700px) {
            .container {
                padding: 15px 15px;
                max-height: 98vh;
            }

            .logo-section {
                margin-bottom: 12px;
            }

            .form-group {
                margin-bottom: 10px;
            }

            .page-subtitle {
                margin-bottom: 12px;
            }
        }

        @media (max-height: 600px) {
            .container {
                padding: 12px 15px;
            }

            .system-title {
                font-size: 1.1em;
            }

            .page-subtitle {
                font-size: 0.9em;
                margin-bottom: 10px;
            }

            .form-input {
                padding: 8px 10px;
                font-size: 11px;
            }

            .form-group {
                margin-bottom: 8px;
            }

            .logo-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9em;
            }
        }

        @media (max-height: 500px) {
            .container {
                padding: 10px 12px;
            }

            .logo-section {
                margin-bottom: 8px;
            }

            .form-group {
                margin-bottom: 6px;
            }

            .btn-primary, .btn-secondary {
                padding: 8px 16px;
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            .container {
                width: 95%;
                max-width: 350px;
                padding: 18px 15px;
            }

            .back-home-btn {
                top: 15px;
                left: 15px;
                padding: 6px 12px;
                font-size: 10px;
            }
        }

        @media (max-width: 320px) {
            .container {
                width: 98%;
                padding: 15px 12px;
            }
        }

        /* Prevent zoom on mobile */
        @media (max-device-width: 768px) {
            input[type="text"], 
            input[type="email"], 
            input[type="password"], 
            input[type="number"],
            select {
                font-size: 16px !important; /* Prevent zoom on iOS */
            }
        }

        /* Password Match Indicator */
        .password-match {
            font-size: 9px;
            margin-top: 4px;
            transition: all 0.3s ease;
        }

        .password-match.success {
            color: #3d7c47;
        }

        .password-match.error {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Enhanced Background Effects -->
    <div class="bg-overlay"></div>
    <div class="floating-elements">
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>
        <div class="floating-shape shape-3"></div>
        <div class="floating-shape shape-4"></div>
    </div>

    <!-- Back to Home Button -->
    <a href="index.php" class="back-home-btn">← Home</a>

    <div class="container">
        <div class="logo-section">
            <div class="logo-icon">REG</div>
            <h1 class="system-title">LAB RESCHEDULE SYSTEM</h1>
            <p class="university-name">University of Jaffna</p>
            <h2 class="page-subtitle">Create New Account</h2>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert-message alert-error">
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-message alert-success">
                <strong>Success:</strong> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form class="register-form" action="register_process.php" method="POST" id="registerForm">
            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-input" required 
                       placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" required 
                       placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label class="form-label" for="department">Department</label>
                <input type="text" id="department" name="department" class="form-input" required 
                       placeholder="Enter your department">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="role">Register As</label>
                <select id="role" name="role" class="form-input form-select" required onchange="toggleFields()">
                    <option value="">Select your role</option>
                    <option value="student">Student</option>
                    <option value="instructor">Lab Instructor</option>
                    <option value="coordinator">Subject Coordinator</option>
                </select>
            </div>

            <div class="form-group group-no-field" id="groupField">
                <label class="form-label" for="group_no">Group Number</label>
                <input type="number" id="group_no" name="group_no" class="form-input" min="1" 
                       placeholder="Enter group number">
            </div>

            <div class="form-group coordinator-field" id="coordinatorField">
                <label class="form-label" for="coordinator_id">Select Coordinator</label>
                <select id="coordinator_id" name="coordinator_id" class="form-input form-select">
                    <option value="">Select coordinator (optional)</option>
                    <?php
                    // Add PHP code here to fetch coordinators from database
                    // Example:
                    // try {
                    //     $stmt = $pdo->query("SELECT Coordinator_ID, Coordinator_Name FROM Subject_Coordinator ORDER BY Coordinator_Name");
                    //     while ($row = $stmt->fetch()) {
                    //         echo "<option value='" . $row['Coordinator_ID'] . "'>" . htmlspecialchars($row['Coordinator_Name']) . "</option>";
                    //     }
                    // } catch (PDOException $e) {
                    //     // Handle error silently
                    // }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" required minlength="6" 
                       placeholder="Create password (min 6 chars)">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="6" 
                       placeholder="Confirm your password">
                <div id="passwordMatch" class="password-match"></div>
            </div>
            
            <button type="submit" class="btn-primary" id="registerBtn">
                Create Account
            </button>
        </form>

        <div class="form-divider">
            <span class="divider-text">Already have an account?</span>
        </div>

        <a href="login.php" class="btn-secondary">
            Login to Your Account
        </a>
    </div>

    <script>
        function toggleFields() {
            const role = document.getElementById('role').value;
            const groupField = document.getElementById('groupField');
            const coordinatorField = document.getElementById('coordinatorField');
            const groupInput = document.getElementById('group_no');
            const coordinatorInput = document.getElementById('coordinator_id');
            
            // Hide all conditional fields first
            groupField.classList.remove('show');
            coordinatorField.classList.remove('show');
            groupInput.required = false;
            coordinatorInput.required = false;
            
            // Show relevant fields based on role with animation
            setTimeout(() => {
                if (role === 'student') {
                    groupField.classList.add('show');
                    groupInput.required = true;
                } else if (role === 'instructor') {
                    coordinatorField.classList.add('show');
                    coordinatorInput.required = false; // Optional for instructors
                }
            }, 100);
            
            // Clear values when hiding fields
            if (role !== 'student') {
                groupInput.value = '';
            }
            if (role !== 'instructor') {
                coordinatorInput.value = '';
            }
        }

        // Enhanced form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const body = document.body;
            const registerBtn = document.getElementById('registerBtn');
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }

            // Add loading state
            body.classList.add('loading');
            registerBtn.textContent = 'Creating Account...';
            registerBtn.disabled = true;
        });

        // Enhanced focus animations
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transform = 'translateY(-1px)';
            });
            
            input.addEventListener('blur', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Prevent double submission
        let submitted = false;
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;
        });

        // Ensure container fits screen on load and resize
        function adjustContainerSize() {
            const container = document.querySelector('.container');
            const viewportHeight = window.innerHeight;
            
            // Ensure container never exceeds viewport height
            container.style.maxHeight = Math.min(viewportHeight * 0.95, 800) + 'px';
        }

        window.addEventListener('load', adjustContainerSize);
        window.addEventListener('resize', adjustContainerSize);

        // Prevent body scroll on mobile
        document.addEventListener('touchmove', function(e) {
            if (!e.target.closest('.container')) {
                e.preventDefault();
            }
        }, { passive: false });

        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(adjustContainerSize, 100);
        });

        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchIndicator = document.getElementById('passwordMatch');
            
            if (confirmPassword) {
                if (password === confirmPassword) {
                    matchIndicator.textContent = '✓ Passwords match';
                    matchIndicator.className = 'password-match success';
                    this.style.borderColor = 'rgba(61, 124, 71, 0.8)';
                } else {
                    matchIndicator.textContent = '✗ Passwords do not match';
                    matchIndicator.className = 'password-match error';
                    this.style.borderColor = 'rgba(220, 53, 69, 0.8)';
                }
            } else {
                matchIndicator.textContent = '';
                this.style.borderColor = 'rgba(6, 64, 43, 0.1)';
            }
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            if (password.length >= 6) {
                this.style.borderColor = 'rgba(61, 124, 71, 0.8)';
            } else if (password.length > 0) {
                this.style.borderColor = 'rgba(255, 193, 7, 0.8)';
            } else {
                this.style.borderColor = 'rgba(6, 64, 43, 0.1)';
            }
        });
    </script>
</body>
</html>
