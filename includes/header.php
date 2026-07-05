<!-- includes/header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Houra ⏰ - Community Time Bank</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation dengan Burger Menu -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <!-- Logo/Brand -->
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-clock-fill me-2"></i>
                <span class="fw-bold">Houra Community Time Bank</span>
            </a>
            
            <!-- Burger Button (Muncul pada mobile) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu Items -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php#home">
                            <i class="bi bi-house me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#how-it-works">
                            <i class="bi bi-info-circle me-1"></i>How It Works
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#why-join">
                            <i class="bi bi-question-circle me-1"></i>Why Join?
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light ms-2" href="register.php" style="color: var(--dark-purple);">
                            <i class="bi bi-person-plus me-1"></i>Sign Up
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Add padding untuk fixed navbar -->
    <div style="padding-top: 70px;"></div>
    
    <main class="container py-4">