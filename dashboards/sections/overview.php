<?php
session_start();
include("../../config/db.php");

//  Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    header("Location: ../auth/login.html");
    exit();
}

// Resolve the landlord record id from the logged-in user_id. Many tables store landlord id separately
$user_id = $_SESSION['user_id'] ?? null;
$landlord_id = 0;
if ($user_id) {
    $stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $landlord_id = (int)$res->fetch_assoc()['id'];
        }
        $stmt->close();
    }
}

// If landlord_id could not be resolved, set counts to zero and avoid running queries that expect a landlord id
if (!$landlord_id) {
    $available_properties = 0;
    $total_properties_alias = 0;
    $reserved = 0;
    $pending_reservations = 0;
    $total_inquiries = 0;
    $revenue = 0;
    // continue to render a page that indicates no landlord profile found
} else {
    // Available properties and totals
    $available_query = $conn->query("\
        SELECT COUNT(*) AS total FROM properties p\
        WHERE p.landlord_id = $landlord_id\
        AND p.id NOT IN (SELECT property_id FROM reservations WHERE status = 'approved')\
    ");
    $available_properties = $available_query ? (int)$available_query->fetch_assoc()['total'] : 0;

    // Total properties (properties table)
    $properties_query = $conn->query("SELECT COUNT(*) AS total FROM properties WHERE landlord_id = $landlord_id");
    $total_properties = $properties_query ? (int)$properties_query->fetch_assoc()['total'] : 0;
    $total_properties_alias = $total_properties; // Alias for display

    // Total reservations (join through properties table)
    $reservations_query = $conn->query("\
        SELECT COUNT(*) AS total \n\
        FROM reservations r \n\
        JOIN properties p ON r.property_id = p.id \n\
        WHERE p.landlord_id = $landlord_id\
    ");
    $total_reservations = $reservations_query ? (int)$reservations_query->fetch_assoc()['total'] : 0;
    $reserved = $total_reservations; // Alias for display

    // Pending reservations (use properties matching)
    $pending_query = $conn->query("\
        SELECT COUNT(*) AS total \n\
        FROM reservations r \n\
        JOIN properties p ON r.property_id = p.id \n\
        WHERE p.landlord_id = $landlord_id AND r.status = 'pending'\
    ");
    $pending_reservations = $pending_query ? (int)$pending_query->fetch_assoc()['total'] : 0;
}


// Total inquiries (if table exists)
$inquiries_query = @$conn->query("SELECT COUNT(*) AS total FROM inquiries WHERE landlord_id = $landlord_id");
$total_inquiries = ($inquiries_query && $inquiries_query !== false) ? $inquiries_query->fetch_assoc()['total'] : 0;


// Estimated monthly income (if payments table exists)
$income_query = @$conn->query("
    SELECT SUM(amount) AS total FROM payments 
    WHERE landlord_id = $landlord_id 
    AND MONTH(payment_date) = MONTH(CURRENT_DATE())
");
$total_income = ($income_query && $income_query !== false) ? ($income_query->fetch_assoc()['total'] ?? 0) : 0;
$revenue = $total_income; // Alias for display


// Placeholder values for features not yet implemented
$total_views = 0;
$view_growth = 0;
$revenue_growth = 0;
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f5f5f5;
            padding-top: 56px;
        }
        .stat-card,
        .start-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 100px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .stat-card:hover,
        .start-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .stat-card > div:first-child,
        .start-card > div:first-child {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .stat-card > div:first-child > span,
        .start-card > div:first-child > span {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        .stat-card h4,
        .start-card h4 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #333;
            margin: 0 0 6px 0;
        }

        .icon-box {
            font-size: 1.8rem;
            color: #0d6efd;
            opacity: 0.9;
        }
        .trend-up { 
            color: #28a745; 
            font-size: 0.75rem; 
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .trend-up::before {
            content: "↑";
            font-size: 0.9rem;
        }
        .trend-down { 
            color: #dc3545; 
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .trend-down::before {
            content: "~";
            font-size: 0.9rem;
        }
        .row.g-3.mb-4 {
            margin-bottom: 2rem !important;  /* ⬅ gap before Quick Actions */
        }
        .quick-actions-wrapper {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 24px 30px;
        }
        .quick-actions {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .action-card {
            flex: 1 1 22%;
            min-width: 150px;
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        
        .action-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #fff;
            font-size: 1.8rem;
        }
        /* only the icons get the colors */
        .text-blue { background: #1e3a8a; }     
        .text-purple { background: #6f42c1; }   
        .text-green { background: #28a745; }   
        .text-orange { background: #fd7e14; } 

        .action-card p {
            color: #333;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .action-card small {
            color: #777;
            font-size: 0.8rem;
        }


        /* Prevent cards from hovering above sidebar */
        .stat-card,
        .start-card,
        .quick-actions-wrapper,
        .action-card {
            position: relative;
            z-index: 1;
        }

        .stat-card:hover,
        .start-card:hover,
        .action-card:hover {
            z-index: 1; /* stays below sidebar */
        }
        .recent-reservations {
            background: #fff;
            border-radius: 12px;
            padding: 24px 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-top: 2rem;
        }
        .reservation-item {
            transition: background-color 0.2s ease;
        }
        .reservation-item:hover {
            background-color: #f9fafb;
        }
        .badge {
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .view-all-link {
            display: block;
            width: 100%;
            text-align: center;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            padding: 8px 16px;
            border-radius: 8px;
            color:  #000;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .view-all-link:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: #000;
            text-decoration: none;
        }
        .top-properties {
            background: #fff;
            border-radius: 12px;
            padding: 24px 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .property-item:hover {
            background-color: #f9fafb;
        }

    </style>
    </head>

    <body class="p-4">
        <h4 class="fw-bold mb-2">Landlord Dashboard </h4>
        <p class="text-muted mb-4">Manage your properties and bookings</p>

        <!--Top Stat Cards-->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="stat-card mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Total Houses</span>
                        <i class="bi bi-building icon-box"></i>
                    </div>
                    <h4><?php echo $total_properties_alias; ?></h4>
                    <small class="trend-up"><?= $available_properties; ?> available</small>
                </div>
                <div class="stat-card">
                    <div class="d-flex justify-content-between">
                        <span>Reserved</span>
                        <i class="bi bi-people icon-box"></i>
                    </div>
                    <h4><?=$reserved ?></h4>
                    <small class="trend-down"><?= $pending_reservations; ?> pending</small>
                </div>
            </div>

            <div class="col-md-6">
                <div class="stat-card mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Total Views</span>
                        <i class="bi bi-bar-chart icon-box"></i>
                    </div>
                    <h4><?= $total_views ?></h4>
                    <small class="trend-up">+<?= $view_growth ?>% this month</small>
                </div>
                <div class="stat-card">
                    <div class="d-flex justify-content-between">
                        <span>Revenue</span>
                        <i class="bi bi-cash-coin icon-box"></i>
                    </div>
                    <h4>$<?= number_format($revenue) ?></h4>
                    <small class="trend-up">+<?= $revenue_growth ?>%</small>
                </div>
            </div>
        </div>

        <!--Quick Actions-->
        <div class="quick-actions-wrapper">
            <h6 class="fw-bold mb-3">Quick Actions</h6>
            <div class="quick-actions">
                <div class="action-card" onclick="window.location='../houses/add_house.php'">
                <div class="action-icon text-blue">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <p class="fw-semibold mb-0">Add New House</p>
                <small>List a new property</small>
            </div>

            <div class="action-card" onclick="window.location='../houses/analytics.php'">
                <div class="action-icon text-purple">
                    <i class="bi bi-bar-chart-line"></i>
                </div>
                <p class="fw-semibold mb-0">View Reports</p>
                <small>Generate analytics</small>
            </div>

            <div class="action-card" onclick="window.location='../houses/inquiries.php'">
                <div class="action-icon text-green">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <p class="fw-semibold mb-0">Messages</p>
                <small>Check inquiries</small>
            </div>

            <div class="action-card" onclick="window.location='../houses/manage_reservations.php'">
                <div class="action-icon text-orange">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <p class="fw-semibold mb-0">Schedule</p>
                <small>Manage bookings</small>
            </div>
            </div>
        </div>

        <!--Recent Reservations-->
        <div class="recent-reservations mt-4">
            <h6 class="fw-bold mb-3">Recent Reservations</h6>

            <?php
            // Fetch the 3 most recent reservations for this landlord
            $recent_reservations_query = $conn->query("
                  SELECT r.id, r.status, p.title AS property_title, COALESCE(u.name, u.username, u.email) AS tenant_name 
                  FROM reservations r 
                  JOIN properties p ON r.property_id = p.id 
                  LEFT JOIN users u ON (u.user_id = r.student_id OR u.id = r.student_id) 
                  WHERE p.landlord_id = $landlord_id 
                  ORDER BY r.created_at DESC 
                  LIMIT 3
            ");
            if ($recent_reservations_query && $recent_reservations_query->num_rows > 0):
                while ($row = $recent_reservations_query->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $status_class = ($status === 'confirmed') ? 'badge bg-success-subtle text-success' :
                                    (($status === 'pending') ? 'badge bg-warning-subtle text-warning' :
                                    (($status === 'cancelled') ? 'badge bg-danger-subtle text-danger' : 'badge bg-secondary-subtle text-secondary'));
            ?>
                <div class="reservation-item d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong><?= htmlspecialchars($row['tenant_name']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($row['property_title']) ?></small>
                    </div>
                    <span class="<?= $status_class ?> text-capitalize"><?= htmlspecialchars($status); ?></span>
                    
                </div>
            <?php 
                endwhile; 
            else:
                echo "<p class='text-muted fst-italic'>No recent reservations found.</p>";
            endif;
             ?>

             <div class="text-center mt-3">
                <a href="../houses/manage_reservations.php" class="view-all-link">
                    View All Reservations
                </a>
             </div>
        </div>

        <!--Top Performing Properties - Placeholder for future-->
        <div class="top-properties mt-4">
            <h6 class="fw-bold mb-3">Top Performing Properties</h6>
            <p class="text-muted fst-italic">Feature coming soon...</p>

            <?php
            // Fetch top 3 performing properties based on reservations
            $top_properties_query = $conn->query("
                 SELECT p.title, COUNT(r.id) AS reservation_count, p.rent
                 FROM properties p 
                 LEFT JOIN reservations r ON p.id = r.property_id 
                 WHERE p.landlord_id = $landlord_id 
                 GROUP BY p.id 
                 ORDER BY reservation_count DESC 
                 LIMIT 3
            "); // This query was missing h.rent

            if ($top_properties_query && $top_properties_query->num_rows > 0):
                while ($prop = $top_properties_query->fetch_assoc()):
            ?>
                <div class="property-item d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong><?= htmlspecialchars($prop['title']) ?></strong><br>
                        <small class="text-muted"><?= (int)$prop['reservation_count'] ?> Reservations</small>
                    </div>
                <span class="fw-semibold">$<?= number_format($prop['rent']) ?>/mo</span>
            </div>
        <?php 
                endwhile; 
            else:
                echo "<p class='text-muted fst-italic'>No property data available.</p>";
            endif;
             ?>

             <div class="text-center mt-3">
                <a href="../houses/analytics.php" class="view-all-link">
                    View Analytics
                </a>
             </div>
        </div>

    </body>
</html>