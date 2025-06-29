<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Reschedule System - University of Jaffna</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        .hero-section {
            min-height: 100vh;
            height: auto;
            background: linear-gradient(rgba(20, 83, 45, 0.8), rgba(6, 68, 32, 0.8)),
                        url('images/lab_background.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px 0;
        }

        .hero-content {
            text-align: center;
            color: white;
            max-width: 900px;
            width: 100%;
            padding: 20px;
            animation: fadeInUp 1s ease-out;
        }

        .university-logo {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            font-weight: bold;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .hero-title {
            font-size: 3em;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.2em;
            margin-bottom: 15px;
            opacity: 0.95;
            font-weight: 300;
        }

        .hero-description {
            font-size: 1em;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 30px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 30px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .feature-icon {
            font-size: 2em;
            margin-bottom: 10px;
            display: block;
        }

        .feature-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .feature-text {
            font-size: 0.9em;
            opacity: 0.9;
            line-height: 1.4;
        }

        .auth-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            max-width: 500px;
            margin: 0 auto;
        }

        .btn {
            padding: 12px 35px;
            border: none;
            border-radius: 50px;
            font-size: 1em;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            border: 2px solid transparent;
            min-width: 180px;
            text-align: center;
        }
        .btn-primary {
            background: rgba(255, 255, 255, 0.95);
            color: #2E8B57;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-primary:hover {
            background: rgba(255, 255, 255, 1);
            color: #2E8B57;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.8);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-2px);
            border-color: white;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-section {
                min-height: 100vh;
                padding: 15px 0;
            }
            .hero-title {
                font-size: 2.2em;
            }
            .hero-subtitle {
                font-size: 1.1em;
            }
            .hero-description {
                font-size: 0.95em;
                margin-bottom: 25px;
            }
            .features-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 25px;
            }
            .feature-card {
                padding: 18px;
            }
            .auth-buttons {
                flex-direction: column;
                align-items: center;
                gap: 12px;
            }
            .btn {
                width: 100%;
                max-width: 280px;
                padding: 14px 30px;
            }
        }
        @media (max-width: 480px) {
            .hero-title {
                font-size: 1.8em;
            }
            .hero-subtitle {
                font-size: 1em;
            }
            .university-logo {
                width: 60px;
                height: 60px;
                font-size: 1.5em;
            }
            .feature-card {
                padding: 15px;
            }
            .feature-icon {
                font-size: 1.8em;
            }
            .btn {
                padding: 12px 25px;
                font-size: 0.95em;
            }
        }
        @media (max-width: 320px) {
            .hero-content {
                padding: 15px;
            }
            .btn {
                min-width: 150px;
                padding: 10px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="university-logo">UJ</div>
            <h1 class="hero-title">Lab Reschedule System</h1>
            <h2 class="hero-subtitle">University of Jaffna</h2>
            <p class="hero-description">
                Streamline your laboratory scheduling with our intelligent reschedule management system. 
                Easy, efficient, and designed for the modern academic environment.
            </p>
            <div class="features-grid">
                <div class="feature-card">
                    <span class="feature-icon">📅</span>
                    <h3 class="feature-title">Easy Scheduling</h3>
                    <p class="feature-text">Request lab reschedules with just a few clicks</p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">⚡</span>
                    <h3 class="feature-title">Instant Approval</h3>
                    <p class="feature-text">Real-time notifications and quick approval process</p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">📊</span>
                    <h3 class="feature-title">Smart Analytics</h3>
                    <p class="feature-text">Track attendance and monitor lab usage efficiently</p>
                </div>
            </div>
            <div class="auth-buttons">
                <a href="login.php" class="btn btn-primary">
                    🔐 Login to Your Account
                </a>
                <a href="register.php" class="btn btn-secondary">
                    ✨ Register Now
                </a>
            </div>
        </div>
    </section>
</body>
</html>
