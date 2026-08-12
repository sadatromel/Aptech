<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - Aptech ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <div class="error-icon mb-4">
                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 5rem;"></i>
            </div>
            <h1 class="display-4 fw-bold text-danger">500</h1>
            <h2 class="mb-3">Server Error</h2>
            <p class="lead text-muted mb-4">Something went wrong on our end. Please try again later.</p>
            <a href="javascript:history.back()" class="btn btn-primary me-2">
                <i class="fas fa-arrow-left me-2"></i>Go Back
            </a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/Aptech'; ?>/dashboard" class="btn btn-outline-primary">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
        </div>
    </div>
</body>
</html>
