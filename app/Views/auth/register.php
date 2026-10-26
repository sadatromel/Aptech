<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - ERP System</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Aptech/assets'; ?>/css/theme.css">
    <link rel="stylesheet" href="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Aptech/assets'; ?>/css/login.css">
    
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
            max-width: 500px; /* Wider for register form */
            color: #1a1a1a; /* Dark text for light glass */
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-container {
            background: transparent !important;
        }

        .logo-container img {
            height: 100px; /* Smaller for register to fit form */
            margin-bottom: 15px;
            mix-blend-mode: multiply; /* Ensuring background is hidden */
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
        
        .form-group label i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
            color: #666;
        }

        .form-control {
            width: 100%;
            padding: 12px 12px 12px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            background: #fff; /* White background for inputs */
            color: #333;
        }
        
        .form-control::placeholder {
            color: #999;
        }

        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
            outline: none;
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
        }
        
        select.form-control option {
            background-color: #fff;
            color: #333;
        }

        .row {
            display: flex;
            gap: 15px;
        }
        
        .col {
            flex: 1;
        }

        .btn-submit {
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
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        .file-upload-wrapper {
            position: relative;
            width: 100%;
            height: 46px; /* Matching input height approx */
            border: 1px dashed #ccc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: rgba(255,255,255,0.5);
            transition: all 0.2s;
        }
        
        .file-upload-wrapper:hover {
            background: rgba(255,255,255,0.8);
            border-color: #4a90e2;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-upload-text {
            color: #666;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        
        .success-message {
            background: #def7ec;
            color: #03543f;
            border: 1px solid #bcf0da;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
    </style>
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-header">
            <?php if (!empty($settings['logo_url']) && stripos($settings['logo_url'], 'aptech') === false && stripos($settings['logo_url'], 'b1e1f7e0123dc037de3b69f7458cea10') === false): ?>
            <div class="logo-container">
                <img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" alt="Logo" class="logo">
            </div>
            <?php endif; ?>
            <h1>Create Account</h1>
            <p>Join the workspace</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="John Doe" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="john@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="row">
                <div class="col">
                    <div class="form-group">
                        <label for="employee_id"><i class="fas fa-id-badge"></i> Employee ID</label>
                        <input type="text" id="employee_id" name="employee_id" class="form-control" required placeholder="EMP-123" value="<?php echo isset($_POST['employee_id']) ? htmlspecialchars($_POST['employee_id']) : ''; ?>">
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label for="unit"><i class="fas fa-building"></i> Unit</label>
                        <select id="unit" name="unit" class="form-control" required>
                            <option value="" disabled selected>Select Unit</option>
                            <option value="Head Office">Head Office</option>
                            <option value="Factory 1">Factory 1</option>
                            <option value="Factory 2">Factory 2</option>
                            <option value="Warehouse">Warehouse</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="designation"><i class="fas fa-briefcase"></i> Designation</label>
                <input type="text" id="designation" name="designation" class="form-control" required placeholder="e.g. Senior Manager" value="<?php echo isset($_POST['designation']) ? htmlspecialchars($_POST['designation']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="department"><i class="fas fa-sitemap"></i> Department</label>
                <input type="text" id="department" name="department" class="form-control" required placeholder="e.g. Transport, HR, Store" value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="reason"><i class="fas fa-comment"></i> Reason for Access</label>
                <textarea id="reason" name="reason" class="form-control" rows="2" required placeholder="Brief explanation..."><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="profile_photo"><i class="fas fa-camera"></i> Profile Photo</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" onchange="updateFileName(this)">
                    <div class="file-upload-text">
                        <i class="fas fa-cloud-upload-alt"></i> <span id="file-name">Choose File</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                Submit Application
            </button>
        </form>
        
        <div class="login-footer">
            <p>Already have an account? <a href="login">Login here</a></p>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileNameSpan = document.getElementById('file-name');
            if (input.files && input.files.length > 0) {
                fileNameSpan.textContent = input.files[0].name;
            } else {
                fileNameSpan.textContent = 'Choose File';
            }
        }
    </script>
</body>
</html>
