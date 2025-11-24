<?php
session_start();
include("../config/db.php");

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'admin') {
    header("Location: ../login.html");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { min-height: 100vh; display: flex; flex-direction: column; }

    .navbar{
        z-index: 1100;
    }

    /* Sidebar */
    .sidebar {
        width: 260px;
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
        transition: transform 0.28s ease, width 0.28s ease;
        z-index: 1200; /* ensure sidebar sits above main content */
    }
        /* When collapsed */
    .sidebar.collapsed {
        transform: translateX(-260px);
    }

    /* Main content spacing to accommodate sidebar */
    .main-content { margin-left: 260px; transition: margin-left .28s ease; }
    .main-content.collapsed { margin-left: 0; }
    /* Ensure sidebar links are clickable above other elements */
    .sidebar .nav { position: relative; z-index: 1210; }
    .sidebar .nav a { position: relative; z-index: 1211; pointer-events: auto; }
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
    

    @media (max-width: 768px) {
        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
            top: 0;
        }
        .main-content {
            margin-left: 0;
            width: 100%;
            margin-top: 20px;
        }
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

            <a class="navbar-brand fw-bold text-dark" href="#">Admin Panel</a>
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

<!--  Sidebar -->
<div class="sidebar" id="sidebar">
    <ul class="nav flex-column mt-4">
        <li><a href="javascript:void(0);" class="active" onclick="loadSection('a_dashboard', this)"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
        <li><a href="javascript:void(0);"  onclick="loadSection('users', this)"><i class="bi bi-people me-2"></i>Users</a></li>
        <li><a href="javascript:void(0);"  onclick="loadSection('properties', this)"><i class="bi bi-building me-2"></i>Properties</a></li>
        <li><a href="javascript:void(0);"  onclick="loadSection('bookings', this)"><i class="bi bi-calendar-check me-2"></i>Bookings</a></li>
        <li><a href="javascript:void(0);"  onclick="loadSection('financials', this)"><i class="bi bi-cash-stack me-2"></i>Financial</a></li>
        <li><a href="javascript:void(0);" onclick="loadSection('support', this)"><i class="bi bi-chat-dots me-2"></i>Support</a></li>
        <li><a href="javascript:void(0);" onclick="loadSection('settings', this)"><i class="bi bi-gear me-2"></i>Settings</a></li>
    </ul>

    <div class="bottom-section">
        <div class="d-flex align-items-center mb-2">
            <i class="bi bi-person-circle fs-4 me-2"></i>
            <div>
                <strong>Admin</strong><br>
                <small class="text-muted"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></small>
            </div>
        </div>
        <a href="../logout.php" class="btn btn-dark w-100 d-flex  align-items-center justify-content-center">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
    
</div>

<!-- Main Content -->
<div class="main-content" id="content">
    <!--Loaded dynamically-->
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  const toggle = document.getElementById("sidebarToggle");

  toggle.addEventListener("click", () => {
    sidebar.classList.toggle("collapsed");
    document.querySelector(".main-content").classList.toggle("collapsed");
  });

    window.loadSection = function(section, el = null) {
        // use absolute path to avoid relative URL 404s
        fetch('/e_rentalHub/dashboards/sections/' + section + '.php')
            .then(res => {
                if (!res.ok) throw new Error('Failed to load section: ' + res.status + ' ' + res.statusText);
                return res.text();
            })
            .then(html => {
                document.getElementById("content").innerHTML = html;
                document.querySelectorAll(".sidebar a").forEach(a => a.classList.remove("active"));
                if (el) el.classList.add("active");
            })
            .catch(err => {
                console.error("Error loading section:", err);
                document.getElementById("content").innerHTML = '<div class="alert alert-danger">Could not load section: ' + err.message + '</div>';
            });
    };

    // Default section load — find the dashboard link by partial onclick attribute to be robust
    const dashboardLink = document.querySelector('.sidebar a[onclick*="a_dashboard"]');
    loadSection("a_dashboard", dashboardLink);

});
</script>

</body>
</html>