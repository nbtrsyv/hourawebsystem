<!-- includes/header_user.php -->
<?php
require_once 'config/settings.php'; // Include settings
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- GUNA SITE_NAME DARI SETTINGS -->
    <title>Houra ⏰ - Community Time Bank</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation untuk User yang sudah login -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <!-- Logo/Brand - GUNA SITE_NAME -->
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-clock-fill me-2"></i>
                <span class="fw-bold">Houra Community Time Bank</span>
            </a>
            
            <!-- Burger Button (Mobile) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu Items untuk User -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">
                            <i class="bi bi-search me-1"></i>Browse Services
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="my_requests.php">
                            <i class="bi bi-list-check me-1"></i>My Requests
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="chat.php">
                            <i class="bi bi-chat-text me-1"></i>Messages
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="time_wallet.php">
                            <i class="bi bi-wallet me-1"></i>Wallet
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person me-1"></i>Profile
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="btn btn-light ms-2" href="logout.php" style="color: var(--dark-purple);">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Add padding untuk fixed navbar -->
    <div style="padding-top: 70px;"></div>
    
    <main class="container py-4">