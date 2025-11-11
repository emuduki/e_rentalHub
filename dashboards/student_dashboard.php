<?php
session_start();
include("../config/db.php");

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'student') {
    header("Location: ../login.html");
    exit();
}

$student_id = $_SESSION['user_id'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { min-height: 100vh; display: flex; flex-direction: column; }

            .navbar{
            z-index: 1100;
        }

        /* Sidebar */
        .sidebar {
            min-width: 260px;
            background-color: #ffffff; /* darker shade than navbar */
            padding-top: 6px 0;           /* reduced padding to fit everything */
            height: calc(100vh - 56px); /* full height minus navbar */
            position: fixed;
            z-index: 1000;
            top: 56px; /* same height as navbar */
            left: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;       /* ensure no scrollbar */
            border-right: 1px solid #dee2e6;
            box-shadow: 2px 0 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
            /* When collapsed */
         .sidebar.collapsed {
            transform: translateX(-260px);
        }
        .sidebar .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            color: #12be82;
            margin-bottom: 10px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin-top: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start; /* keep items stacked without large gaps */
            gap: 10px; /* slightly larger vertical gap between items */
            padding-top: 18px; /* increase top padding slightly for balance */
        }
        .sidebar ul li {
            margin: 0 8px; /* horizontal padding between link and sidebar edges */
        }
        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 7px 10px; /*smaller height */
            text-decoration: none;
            color: #212529;
            border-radius: 6px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.25s ease;
            position: relative;
            }

        .sidebar ul li a:hover {
            background-color: #f2f2f2;
            color: #12be82;
        }
        
        .sidebar ul li a.active {
            background-color: #000;
            color: #fff !important;
            font-weight: 600;
        }
        .sidebar .badge {
            font-size: 0.75rem;
            border-radius: 10px;
            padding: 3px 7px;
            position: absolute;
            right: 12px;
        }

        .bottom-section {
            border-top: 1px solid #e9ecef;
            padding: 10px 15px;
            background-color: #fff;
            flex-shrink: 0; /* Prevent it from being pushed offscreen */
            margin-bottom: 18px; /* lift the bottom section slightly above the viewport bottom */
        }

        .bottom-section .d-flex {
                align-items: center;
        }

        .bottom-section a {
                text-decoration: none;
                color: #000;
                font-weight: 500;
                display: flex;
                align-items: center;
                margin-top: 8px;
        }

        .bottom-section a:hover {
                color: #12be82;
        }
        /* Logout Button Styling */
        .bottom-section .btn {
            background-color: #fff;         /* black background */
            color: #000;                    /* white text */
            border-radius: 8px;             /* smooth corners */
            padding: 6px 0;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .bottom-section .btn:hover {
            background-color: #555;      /* green hover */
            color: #fff;
            transform: translateY(-1px);    /* subtle lift effect */
        }

        .bottom-section .btn i {
            font-size: 1rem;
        }
    
    </style>
</head>
<body class="bg-light">

    
    <!--  Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">

            <!--- Left section: toggle + title -->
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-secondary me-3" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>

                <a class="navbar-brand fw-bold text-dark" href="#">Student Housing</a>
            </div>
            
            <!-- Right section: notification bell -->
            <div class="d-flex align-items-center">
                <button class="btn btn-light position-relative me-2">
                    <i class="bi bi-bell fs-5"></i>
                    <!-- Optional: red dot for unread notifications -->
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                </button>
            </div>

        </div>
    </nav>



    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        
        <ul class="nav flex-column mt-4">
            <li><a href="javascript:void(0);" class="active" onclick="loadSection('search_properties')"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Properties</a></li>
            <li><a href="javascript:void(0);"  onclick="loadSection('saved_properties')"><i class="fa-regular fa-heart me-2"></i>Saved</a></li>
        <li><a href="javascript:void(0);" onclick="loadSection('my_bookings')"><i class="fa-regular fa-calendar me-2"></i>My Bookings</a></li>

            <li><a href="javascript:void(0);" onclick="loadSection('payments')"><i class="fa-solid fa-money-bill me-2"></i>Payments</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('messages')"><i class="fa-regular fa-message me-2"></i>Messages</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('student_profile')"><i class="bi bi-person me-2"></i>Profile</a></li>

            <!-- Divider line-->
            <hr class="my-3 mx-3">
        </ul>

        <div class="bottom-section">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-person-circle fs-4 me-2"></i>
                <div>
                    <strong>Student</strong><br>
                    <small class="text-muted"><?= htmlspecialchars($_SESSION['username'] ?? 'student') ?></small>
                </div>
            </div>
            <a href="../logout.php" class="btn btn-dark w-100 d-flex  align-items-center justify-content-center">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>


    </div>

<!-- Main Content -->
<div class="main-content" id="content" style="margin-left: 260px; padding: 20px; transition: margin-left .3s;">
    <!--Loaded dynamically-->
</div>


</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  const toggle = document.getElementById("sidebarToggle");
  const content = document.getElementById("content");

  toggle.addEventListener("click", () => {
    sidebar.classList.toggle("collapsed");
    const isCollapsed = sidebar.classList.contains('collapsed');
    content.style.marginLeft = isCollapsed ? '0' : '260px';
  });

  window.loadSection = function(section, el = null) {
    fetch(`sections/${section}.php`)
      .then(res => res.text())
      .then(html => {
        content.innerHTML = html;
                // Remove active from all links and add to the matching link
                document.querySelectorAll(".sidebar a").forEach(a => a.classList.remove("active"));
                // Try to find the link by its onclick attribute
                const selector = `.sidebar a[onclick*="${section}"]`;
                const matching = document.querySelector(selector);
                if (matching) matching.classList.add('active');
      })
      .catch(err => console.error("Error loading section:", err));
  };

  // Default section load
    // Load search_properties by default when opening the student dashboard
    loadSection("search_properties");
});
</script>
</html>
