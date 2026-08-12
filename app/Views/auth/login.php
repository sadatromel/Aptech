<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aptech ERP System</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Aptech/assets'; ?>/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Aptech/assets'; ?>/css/login.css?v=<?php echo time(); ?>">
    
    <style>
        /* Inline critical CSS for login specific overrides if needed */
        body.login-page {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            background-image: url('<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Aptech/assets'; ?>/img/login-bg-new.png');
            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-end; /* Moved to right */
            padding-right: 10%; /* Added spacing from right edge */
        }
        


        .login-container {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }



        .login-header h1 {
            color: #1a1a1a;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .login-header p {
            color: #666;
            margin: 5px 0 0;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .form-control {
            width: 100%;
            padding: 12px 12px 12px 40px; /* Space for icon */
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
            outline: none;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .forgot-password {
            color: #4a90e2;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        .error-message {
            background: #fde8e8;
            color: #c81e1e;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .login-footer a {
            color: #4a90e2;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-page">

    
    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <!-- Using a generic placeholder if the specific image is missing, or trying the expected path --> 
                <!-- Adjusting path to be more robust -->
                <img src="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Aptech/assets'; ?>/img/aptech-group.png" onerror="this.src='<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Aptech/assets'; ?>/img/aptech-group.png'" alt="Aptech Group" class="logo">
            </div>
            <h1>Welcome Back</h1>
            <p>Access your workspace</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Username or Email</label>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="email" name="email" class="form-control" required autofocus placeholder="name@company.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
            </div>
            
            <div class="form-actions">
                <div class="remember-me">
                    <input type="checkbox" id="remember">
                    <label for="remember">Remember me</label>
                </div>
                <a href="#" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="btn-login">
                Sign In <i class="fas fa-arrow-right"></i>
            </button>
        </form>
        
        <div class="login-footer">
            <p>Don't have an account? <a href="register">Create account</a></p>
        </div>
    </div>
</body>
</html>
