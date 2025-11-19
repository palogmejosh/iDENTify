<?php
require_once 'config.php';

// Redirect if already logged in
if (isAuthenticated()) {
    redirectToDashboard();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $user     = authenticateUser($username, $password);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        // Ensure user is marked as online after successful login
        setUserOnlineStatus($user['id'], 'online');
        redirectToDashboard();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDENTify - Login</title>

    <!-- Tailwind & Fonts -->
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: "#9B4C9C", secondary: "#F3E5F5" },
                    borderRadius: { button: "8px", "2xl": "24px" },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'pulse-glow': 'pulseGlow 2s ease-in-out infinite',
                        'float': 'float 3s ease-in-out infinite',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                    }
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode-override.css">

    <style>
    :where([class^="ri-"])::before { content: "\f3c2"; }

    /* Enhanced keyframe animations */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from { 
            opacity: 0; 
            transform: translateY(30px); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0); 
        }
    }

    @keyframes bounceIn {
        0% { 
            opacity: 0; 
            transform: scale(0.3) translateY(30px); 
        }
        50% { 
            opacity: 1; 
            transform: scale(1.05) translateY(-5px); 
        }
        70% { 
            transform: scale(0.9) translateY(0); 
        }
        100% { 
            opacity: 1; 
            transform: scale(1) translateY(0); 
        }
    }

    @keyframes pulseGlow {
        0%, 100% { 
            box-shadow: 0 0 5px rgba(155, 76, 156, 0.3); 
        }
        50% { 
            box-shadow: 0 0 20px rgba(155, 76, 156, 0.6), 0 0 30px rgba(155, 76, 156, 0.4); 
        }
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }

    @keyframes rotate { 
        from { transform: rotate(0deg); } 
        to { transform: rotate(360deg); } 
    }

    /* Enhanced gradient background with animation */
    body {
        background: linear-gradient(-45deg, #f5f5f5, #e8e8e8, #f0f0f0, #e0e0e0);
        background-size: 400% 400%;
        animation: gradientShift 15s ease infinite;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        position: relative;
        overflow: hidden;
    }

    /* Floating orbs background */
    .floating-orbs {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0;
    }

    .orb {
        position: absolute;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(155, 76, 156, 0.1) 0%, rgba(155, 76, 156, 0.05) 70%, transparent 100%);
        animation: floatOrb linear infinite;
    }

    .orb:nth-child(1) {
        width: 80px;
        height: 80px;
        left: 10%;
        animation-duration: 20s;
        animation-delay: 0s;
    }

    .orb:nth-child(2) {
        width: 60px;
        height: 60px;
        left: 20%;
        animation-duration: 25s;
        animation-delay: -5s;
    }

    .orb:nth-child(3) {
        width: 100px;
        height: 100px;
        left: 35%;
        animation-duration: 30s;
        animation-delay: -10s;
    }

    .orb:nth-child(4) {
        width: 70px;
        height: 70px;
        left: 50%;
        animation-duration: 22s;
        animation-delay: -8s;
    }

    .orb:nth-child(5) {
        width: 90px;
        height: 90px;
        left: 65%;
        animation-duration: 28s;
        animation-delay: -15s;
    }

    .orb:nth-child(6) {
        width: 50px;
        height: 50px;
        left: 80%;
        animation-duration: 24s;
        animation-delay: -12s;
    }

    .orb:nth-child(7) {
        width: 75px;
        height: 75px;
        left: 90%;
        animation-duration: 26s;
        animation-delay: -7s;
    }

    @keyframes floatOrb {
        0% {
            transform: translateY(100vh) scale(0);
            opacity: 0;
        }
        10% {
            opacity: 1;
            transform: translateY(90vh) scale(1);
        }
        90% {
            opacity: 1;
            transform: translateY(-10vh) scale(1);
        }
        100% {
            transform: translateY(-10vh) scale(0);
            opacity: 0;
        }
    }

    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .login-container {
        width: 100%;
        max-width: 900px;
        min-height: 480px;
        display: flex;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        animation: fade-in 0.8s ease-out;
        transition: all 0.3s ease;
    }

    .login-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }

    .login-form { 
        flex: 1; 
        padding: 2.5rem; 
        display: flex; 
        flex-direction: column; 
        justify-content: center;
        animation: slide-up 0.8s ease-out 0.2s both;
    }

    .login-image { 
        flex: 1; 
        background: linear-gradient(135deg, #F3E5F5 0%, #E1BEE7 100%); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        padding: 2.5rem;
        animation: fade-in 1s ease-out 0.4s both;
    }

    .tooth-icon { 
        width: 300px; 
        height: 300px; 
        position: relative;
        animation: float 4s ease-in-out infinite;
    }

    .tooth-orbit { 
        width: 100%; 
        height: 100%; 
        position: absolute; 
        top: 0; 
        left: 0; 
        animation: rotate 20s linear infinite; 
    }

    .tooth-satellite {
        width: 45px; 
        height: 45px; 
        background: white; 
        border-radius: 50%;
        position: absolute; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        animation: pulse-glow 3s ease-in-out infinite;
    }

    .tooth-satellite:hover {
        transform: scale(1.2);
        box-shadow: 0 6px 25px rgba(155, 76, 156, 0.3);
    }

    .tooth-satellite i { 
        font-size: 18px; 
        color: #9B4C9C;
        transition: all 0.3s ease;
    }

    .tooth-satellite:hover i {
        color: #7A3A7B;
        transform: scale(1.1);
    }

    /* FIXED: Enhanced form animations with proper initial states */
    .form-title {
        opacity: 1; /* Ensure initial visibility */
        animation: bounceIn 0.6s ease-out 0.3s both;
        font-family: 'Poppins', sans-serif; /* Professional font */
        font-weight: 700; /* Bold weight */
        letter-spacing: -0.02em; /* Tight letter spacing for modern look */
        color: #9B4C9C; /* Primary color */
        text-shadow: 0 2px 8px rgba(155, 76, 156, 0.15); /* Softer shadow */
    }
    
    .form-subtitle {
        opacity: 1; /* Changed to visible by default */
        animation: slide-up 0.6s ease-out 0.5s both;
        color: #4B5563 !important; /* Force dark gray color */
        font-weight: 600;
    }

    .form-group {
        opacity: 1; /* Changed to visible by default */
        animation: slide-up 0.6s ease-out both;
    }

    .form-group:nth-child(1) { animation-delay: 0.7s; }
    .form-group:nth-child(2) { animation-delay: 0.8s; }
    .form-group:nth-child(3) { animation-delay: 0.9s; }

    .enhanced-input {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent;
    }

    .enhanced-input:focus {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(155, 76, 156, 0.15);
        border-color: rgba(155, 76, 156, 0.3);
    }

    .enhanced-button {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .enhanced-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .enhanced-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(155, 76, 156, 0.3);
    }

    .enhanced-button:hover::before {
        left: 100%;
    }

    .enhanced-button:active {
        transform: translateY(0);
    }

    .error-alert {
        animation: bounceIn 0.5s ease-out;
    }

    .logo-center {
        animation: float 6s ease-in-out infinite;
        transition: all 0.3s ease;
    }

    .logo-center:hover {
        transform: translateY(-10px) scale(1.05);
    }

    /* Responsive animations */
    @media (max-width: 768px) {
        .login-container { 
            flex-direction: column;
            animation: fade-in 0.6s ease-out;
        }
        .login-image { display: none; }
        .login-form { 
            padding: 2rem;
            animation: slide-up 0.6s ease-out;
        }
        .form-title {
            font-size: 1.875rem; /* Ensure readability on mobile */
        }
    }

    /* Loading states */
    .loading {
        position: relative;
        pointer-events: none;
    }

    .loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        margin: auto;
        border: 2px solid transparent;
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Fallback styles for browsers that don't support animations */
    @media (prefers-reduced-motion: reduce) {
        .form-title,
        .form-subtitle,
        .form-group {
            opacity: 1 !important;
            animation: none !important;
        }
    }
</style>
</head>

<body>
    <div class="login-container">
        <!-- ========== FORM SIDE ========== -->
        <div class="login-form">
            <div class="mb-12">
                <!-- FIXED: Simplified h1 with guaranteed visibility -->
                <h1 class="form-title text-5xl font-bold text-primary mb-2 tracking-tight">
                    iDENTify
                </h1>
                <p class="form-subtitle mt-2 font-semibold tracking-wide text-lg" style="color: #4B5563 !important;">
                    Dental Patient Management System
                </p>
            </div>

            <?php if ($error): ?>
                <div class="error-alert bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4" id="loginForm">
                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" required
                           class="enhanced-input w-full px-4 py-3 rounded bg-gray-50 dark:bg-gray-700 dark:text-white border-none focus:ring-2 focus:ring-primary/50" />
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required
                           class="enhanced-input w-full px-4 py-3 rounded bg-gray-50 dark:bg-gray-700 dark:text-white border-none focus:ring-2 focus:ring-primary/50" />
                </div>
                <div class="form-group">
                    <button type="submit" id="loginBtn"
                            class="enhanced-button block w-full bg-primary text-white py-3 rounded-button hover:bg-primary/90">
                        Log In
                    </button>
                </div>
            </form>
        </div>

        <!-- ========== ANIMATED SIDE ========== -->
        <div class="login-image">
            <div class="tooth-icon">
                <!-- Center logo -->
                <div class="absolute inset-0 flex items-center justify-center z-10">
                    <img src="dentlogo.png" alt="Logo" class="logo-center h-40 w-40 object-contain">
                </div>

                <!-- Orbiting satellites -->
                <div class="tooth-orbit">
                    <?php
                    $icons = [
                        'ri-heart-pulse-line','ri-microscope-line','ri-capsule-line','ri-syringe-line',
                        'ri-stethoscope-line','ri-test-tube-line','ri-medicine-bottle-line','ri-mental-health-line'
                    ];
                    $positions = [
                        'top: 0; left: 50%; transform: translate(-50%, -50%)',
                        'top: 50%; right: 0; transform: translate(50%, -50%)',
                        'bottom: 0; left: 50%; transform: translate(-50%, 50%)',
                        'top: 50%; left: 0; transform: translate(-50%, -50%)',
                        'top: 15%; left: 15%; transform: translate(-50%, -50%)',
                        'top: 15%; right: 15%; transform: translate(50%, -50%)',
                        'bottom: 15%; right: 15%; transform: translate(50%, 50%)',
                        'bottom: 15%; left: 15%; transform: translate(-50%, 50%)'
                    ];
                    foreach ($icons as $i => $ic): ?>
                        <div class="tooth-satellite" style="<?= $positions[$i] ?>">
                            <i class="<?= $ic ?>"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced form interactions
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const inputs = document.querySelectorAll('.enhanced-input');

            // ADDED: Ensure all form elements are visible on load (fallback)
            const titleElement = document.querySelector('.form-title');
            const subtitleElement = document.querySelector('.form-subtitle');
            const formGroups = document.querySelectorAll('.form-group');
            
            if (titleElement) {
                // Force visibility after a short delay if animation fails
                setTimeout(() => {
                    titleElement.style.opacity = '1';
                    titleElement.style.visibility = 'visible';
                }, 100);
            }

            // Ensure all form elements are visible
            setTimeout(() => {
                if (subtitleElement) {
                    subtitleElement.style.opacity = '1';
                    subtitleElement.style.visibility = 'visible';
                }
                formGroups.forEach(group => {
                    group.style.opacity = '1';
                    group.style.visibility = 'visible';
                });
            }, 200);

            // Add loading animation on form submit
            form.addEventListener('submit', function() {
                loginBtn.classList.add('loading');
                loginBtn.textContent = 'Signing In...';
            });

            // Enhanced input focus effects
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Add subtle parallax effect on mouse move
            document.addEventListener('mousemove', function(e) {
                const container = document.querySelector('.login-container');
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;
                
                container.style.transform = `translateX(${x * 5}px) translateY(${y * 5}px)`;
            });
        });
    </script>
</body>
</html>