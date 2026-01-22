<?php
session_start();
include("../config/db.php");

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'student') {
    header("Location: ../index.html");
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch student profile data
$student_profile = [
    'full_name' => $_SESSION['username'] ?? 'Student',
    'email' => $_SESSION['email'] ?? '',
    'avatar' => null
];

$profile_res = $conn->query("
    SELECT full_name, email, avatar 
    FROM students 
    WHERE user_id = {$student_id} 
    LIMIT 1
");

if ($profile_res && $profile_res->num_rows > 0) {
    $student_profile = array_merge($student_profile, $profile_res->fetch_assoc());
}

$uploadsBaseUrl = "/e_rentalHub/uploads/";
$avatarUrl = '';
if (!empty($student_profile['avatar'])) {
    $avatarUrl = $uploadsBaseUrl . $student_profile['avatar'];
}

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
                z-index: 1200; /* ensure sidebar sits above main content */
        }
            /* When collapsed */
         .sidebar.collapsed {
            transform: translateX(-260px);
        }
        .main-content { margin-left: 260px; transition: margin-left .28s ease; }
        /* Ensure main content sits below the fixed top navbar so headings aren't obscured */
        .main-content { padding-top: 72px; }
        .main-content.collapsed { margin-left: 0; }
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

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                min-width: 200px;
            }
            .sidebar.collapsed {
                transform: translateX(-200px);
            }
            .main-content {
                margin-left: 200px;
                padding-top: 72px;
                padding: 12px;
                transition: margin-left 0.28s ease;
            }
            .main-content.collapsed {
                margin-left: 0;
            }
            .sidebar .brand {
                font-size: 0.95rem;
            }
            .sidebar ul li a {
                padding: 6px 8px;
                font-size: 0.9rem;
            }
            .sidebar .badge {
                font-size: 0.65rem;
                padding: 2px 5px;
            }
            .bottom-section {
                padding: 8px 12px;
                margin-bottom: 12px;
            }
            .bottom-section .d-flex {
                font-size: 0.9rem;
            }
            .bottom-section .btn {
                padding: 5px 0;
                font-size: 0.85rem;
            }
            
            /* Dashboard content styling for tablet */
            .card {
                margin-bottom: 1rem;
                border-radius: 8px;
            }
            .card-body {
                padding: 1rem;
            }
            h1, h2 {
                font-size: 1.5rem;
            }
            h3 {
                font-size: 1.1rem;
            }
            .table {
                font-size: 0.9rem;
            }
            .table thead th {
                padding: 0.5rem;
            }
            .table tbody td {
                padding: 0.5rem;
            }
            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
            .form-control,
            .form-select {
                padding: 0.4rem 0.6rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                min-width: 160px;
                position: fixed;
                top: 56px;
                left: 0;
                height: calc(100vh - 56px);
                z-index: 1050;
                box-shadow: 2px 0 6px rgba(0, 0, 0, 0.2);
            }
            .sidebar.collapsed {
                transform: translateX(-160px);
            }
            .main-content {
                margin-left: 0 !important;
                padding: 10px;
                transition: none;
                width: 100%;
            }
            .main-content.collapsed {
                margin-left: 0;
            }
            .sidebar .brand {
                font-size: 0.8rem;
                margin-bottom: 8px;
            }
            .sidebar ul {
                gap: 6px;
                padding-top: 12px;
            }
            .sidebar ul li {
                margin: 0 4px;
            }
            .sidebar ul li a {
                padding: 5px 6px;
                font-size: 0.75rem;
            }
            .sidebar ul li a i {
                font-size: 0.9rem;
            }
            .sidebar .badge {
                font-size: 0.55rem;
                padding: 1px 4px;
                right: 6px;
            }
            .bottom-section {
                padding: 6px 10px;
                margin-bottom: 10px;
            }
            .bottom-section .d-flex {
                font-size: 0.75rem;
                margin-bottom: 10px;
                align-items: center;
                gap: 4px;
                background: #f8f9fa;
                padding: 6px 8px;
                border-radius: 6px;
            }
            .bottom-section .d-flex img {
                width: 18px !important;
                height: 18px !important;
                object-fit: cover;
                flex: 0 0 18px;
                border-radius: 50%;
                display: block;
            }
            .bottom-section .d-flex .rounded-circle {
                width: 18px !important;
                height: 18px !important;
                flex: 0 0 18px;
                border-radius: 50%;
                display: block;
            }
            .bottom-section .d-flex > div {
                flex: 1;
                min-width: 0;
                display: block !important;
                line-height: 1.2;
            }
            .bottom-section .d-flex > div strong {
                display: block;
                font-size: 0.73rem;
                font-weight: 600;
                color: #212529;
                margin-bottom: 1px;
                word-break: break-word;
                white-space: normal;
            }
            .bottom-section .d-flex > div small {
                display: block;
                font-size: 0.63rem;
                color: #6c757d;
                word-break: break-word;
                white-space: normal;
            }
            .bottom-section a,
            .bottom-section .btn {
                padding: 4px 0;
                font-size: 0.75rem;
            }
            .bottom-section .btn i {
                font-size: 0.9rem;
            }
            
            /* Dashboard content styling for mobile */
            h1 {
                font-size: 1.25rem;
                margin-bottom: 0.75rem;
            }
            h2 {
                font-size: 1.1rem;
                margin-bottom: 0.5rem;
            }
            h3 {
                font-size: 0.95rem;
            }
            
            .card {
                margin-bottom: 0.75rem;
                border-radius: 6px;
                border: 1px solid #e9ecef;
            }
            
            .card-body {
                padding: 0.75rem;
            }
            
            .card-header {
                padding: 0.75rem;
            }
            
            .table {
                font-size: 0.8rem;
                margin-bottom: 0.75rem;
            }
            
            .table thead th {
                padding: 0.4rem 0.25rem;
                font-size: 0.75rem;
            }
            
            .table tbody td {
                padding: 0.4rem 0.25rem;
                font-size: 0.75rem;
            }
            
            .btn {
                padding: 0.35rem 0.6rem;
                font-size: 0.8rem;
                border-radius: 4px;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .form-control,
            .form-select {
                padding: 0.35rem 0.5rem;
                font-size: 0.85rem;
                border-radius: 4px;
            }
            
            .form-label {
                font-size: 0.85rem;
                margin-bottom: 0.3rem;
            }
            
            .alert {
                padding: 0.75rem;
                font-size: 0.85rem;
                margin-bottom: 0.75rem;
            }
            
            .badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
            }
            
            .row {
                margin-right: -5px;
                margin-left: -5px;
            }
            
            .col, [class*='col-'] {
                padding-right: 5px;
                padding-left: 5px;
            }
            
            .mb-4 {
                margin-bottom: 0.75rem !important;
            }
            
            .mb-3 {
                margin-bottom: 0.5rem !important;
            }
            
            .p-3 {
                padding: 0.75rem !important;
            }
            
            .p-4 {
                padding: 1rem !important;
            }
            
            /* Booking card mobile styling */
            .booking-card {
                padding: 8px !important;
                margin-bottom: 10px !important;
                border-radius: 6px !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
            }
            
            .booking-img {
                width: 70px !important;
                height: 60px !important;
                border-radius: 4px !important;
                object-fit: cover;
            }
            
            .booking-row {
                gap: 8px !important;
                align-items: flex-start;
            }
            
            .booking-row > .flex-shrink-0 {
                flex-shrink: 0;
            }
            
            .booking-row > div:not(.flex-shrink-0) {
                flex: 1;
                min-width: 0;
            }
            
            .booking-row .d-flex {
                flex-direction: column;
                gap: 2px;
            }
            
            .booking-title {
                font-size: 0.85rem !important;
                font-weight: 600;
                margin-bottom: 2px;
                line-height: 1.2;
                word-break: break-word;
            }
            
            .booking-badge {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.4rem !important;
                width: fit-content;
            }
            
            .small-muted {
                font-size: 0.7rem;
                color: #6c757d;
                margin-bottom: 4px;
            }
            
            .booking-row .row {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 6px 4px !important;
                margin-top: 6px !important;
                width: 100%;
            }
            
            .booking-row .row > div {
                padding: 0 !important;
                font-size: 0.7rem;
                display: flex;
                flex-direction: column;
                gap: 1px;
            }
            
            .booking-row .row > div .small-muted {
                font-size: 0.65rem;
                margin-bottom: 0;
            }
            
            .booking-row .row > div > div:not(.small-muted) {
                font-size: 0.75rem;
                font-weight: 500;
            }
            
            .booking-actions {
                margin-top: 8px !important;
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
            }
            
            .booking-actions .btn {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.7rem !important;
                margin: 0 !important;
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
            <li><a href="javascript:void(0);" class="active" onclick="loadSection('search_properties', this)"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Properties</a></li>
            <li><a href="javascript:void(0);"  onclick="loadSection('saved_properties', this)"><i class="fa-regular fa-heart me-2"></i>Saved</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('my_bookings', this)"><i class="fa-regular fa-calendar me-2"></i>My Bookings</a></li>

            <li><a href="javascript:void(0);" onclick="loadSection('payments', this)"><i class="bi bi-currency-dollar me-2"></i>Payments</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('messages', this)"><i class="fa-regular fa-message me-2"></i>Messages</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('student_profile', this)"><i class="bi bi-person me-2"></i>Profile</a></li>

            <!-- Divider line-->
            <hr class="my-3 mx-3">
        </ul>

        <div class="bottom-section">
            <div class="d-flex align-items-center mb-2">
                <?php if ($avatarUrl): ?>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" class="rounded-circle me-2" alt="Avatar" style="width: 40px; height: 40px; object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: #e9ecef; font-weight: 700; color: #6c757d; font-size: 14px;">
                        <?= strtoupper(substr($student_profile['full_name'] ?: 'S', 0, 2)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <strong><?= htmlspecialchars($student_profile['full_name'] ?: 'Student') ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($student_profile['email'] ?: $_SESSION['username'] ?? 'student') ?></small>
                </div>
            </div>
            <a href="../auth/logout.php" class="btn btn-dark w-100 d-flex  align-items-center justify-content-center">
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

    function executeScripts(container) {
        Array.from(container.querySelectorAll('script')).forEach(old => {
            try {
                const newScript = document.createElement('script');
                if (old.src) {
                    newScript.src = old.src;
                    newScript.async = false;
                } else {
                    newScript.text = old.textContent;
                }
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);
            } catch (err) {
                console.warn('Failed to execute injected script', err);
            }
        });
    }

    function loadUrl(url, contentContainer, el = null) {
        console.debug('Loading from', url);
        fetch(url, { credentials: 'same-origin' })
            .then(res => {
                if (!res.ok) throw new Error('Failed to load: ' + res.statusText);
                return res.text();
            })
            .then(html => {
                if (!html || !html.trim()) {
                    contentContainer.innerHTML = '<div class="alert alert-warning">Section returned empty content.</div>';
                } else {
                    contentContainer.innerHTML = html;
                    executeScripts(contentContainer);
                }

                if (el) {
                    document.querySelectorAll(".sidebar a").forEach(a => a.classList.remove("active"));
                    el.classList.add('active');
                }
            })
            .catch(err => {
                console.error("Error loading content:", err);
                contentContainer.innerHTML = '<div class="alert alert-danger">Could not load content: ' + err.message + '</div>';
            });
    }

    window.loadSection = function(section, el = null) {
        const url = '/e_rentalHub/dashboards/sections/' + section + '.php';
        loadUrl(url, content, el);
    };

    content.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.matches('.search-form')) {
            e.preventDefault();
            const formData = new URLSearchParams(new FormData(form));
            const url = '/e_rentalHub/dashboards/sections/search_properties.php?' + formData.toString();
            loadUrl(url, content);
        }
    });

    // Default section load
    const searchLink = document.querySelector('.sidebar a[onclick*="search_properties"]');
    loadSection("search_properties", searchLink);

    // Global delegated listener for save/unsave heart buttons
    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.fav-btn');
        if (!btn) return;

        const propId = btn.getAttribute('data-property-id') || btn.getAttribute('data-prop-id');
        if (!propId) return;

        const icon = btn.querySelector('i');
        const isSaved = btn.dataset.saved === '1' || icon?.classList.contains('bi-heart-fill');
        const action = isSaved ? 'unsave' : 'save';

        // Optimistic UI update
        if (icon) {
            icon.classList.toggle('bi-heart', action === 'unsave');
            icon.classList.toggle('bi-heart-fill', action === 'save');
            icon.classList.toggle('text-danger', action === 'save');
        }

        try {
            const resp = await fetch('/e_rentalHub/dashboards/sections/toggle_save_property.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'property_id=' + encodeURIComponent(propId) + '&action=' + encodeURIComponent(action)
            });
            const data = await resp.json();
            if (!data.success) {
                throw new Error(data.message || 'Could not update saved state.');
            }
            btn.dataset.saved = action === 'save' ? '1' : '0';
            if (action === 'unsave') {
                const pageTitle = document.querySelector('#content h4')?.textContent?.toLowerCase() || '';
                if (pageTitle.includes('saved properties')) {
                    btn.closest('.col-md-6, .col-lg-4, .booking-card')?.remove();
                }
            }
        } catch (err) {
            console.error('Save property error:', err);
            alert(err.message);
            // Revert UI on failure
            if (icon) {
                icon.classList.toggle('bi-heart', action === 'save');
                icon.classList.toggle('bi-heart-fill', action === 'unsave');
                icon.classList.toggle('text-danger', action === 'unsave');
            }
        }
    });
});
</script>
</html>
