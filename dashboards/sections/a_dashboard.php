<?php
include("../../config/db.php");
session_start();


// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'admin') {
    header("Location: ../login.html");
    exit();
}

$admin_id = $_SESSION['user_id'] ?? null;

// Helper to safely run COUNT queries and return 0 on error
function safe_count($conn, $sql) {
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) {
        return (int)($row['total'] ?? 0);
    }
    return 0;
}

// Metrics
$totalUsers = safe_count($conn, "SELECT COUNT(*) AS total FROM users");
$activeProperties = safe_count($conn, "SELECT COUNT(*) AS total FROM properties WHERE status IS NULL OR status != 'inactive'");
$totalBookings = safe_count($conn, "SELECT COUNT(*) AS total FROM reservations");

// Revenue approximation (if payments or reservations table exists)
$revenue = 0;
$res = $conn->query("SELECT SUM(amount) AS s FROM payments");
if ($res && ($row = $res->fetch_assoc()) && isset($row['s'])) {
    $revenue = (float)$row['s'];
} else {
    // fallback: try summing reservation total_amount if payments table missing
    $res2 = $conn->query("SELECT SUM(total_amount) AS s FROM reservations");
    if ($res2 && ($r2 = $res2->fetch_assoc()) && isset($r2['s'])) {
        $revenue = (float)$r2['s'];
    }
}

// Commission earned placeholder (10% of revenue as example)
$commission = round($revenue * 0.10);

// Pending counts
$pendingProperties = safe_count($conn, "SELECT COUNT(*) AS total FROM properties WHERE status = 'pending'");
$pendingUsers = safe_count($conn, "SELECT COUNT(*) AS total FROM users WHERE verified = 0 OR verified IS NULL");

// Recent activity: fetch latest entries from properties, reservations and users
$recent = [];
$p = $conn->query("SELECT id, title, created_at FROM properties ORDER BY id DESC LIMIT 3");
if ($p) while ($row = $p->fetch_assoc()) $recent[] = ['type' => 'property', 'title' => $row['title'] ?? 'New property', 'time' => $row['created_at'] ?? 'just now'];
$b = $conn->query("SELECT r.id, r.property_id, r.created_at, p.title FROM reservations r LEFT JOIN properties p ON p.id = r.property_id ORDER BY r.id DESC LIMIT 3");
if ($b) while ($row = $b->fetch_assoc()) $recent[] = ['type' => 'booking', 'title' => 'New booking: ' . ($row['title'] ?? 'property'), 'time' => $row['created_at'] ?? 'just now'];
$u = $conn->query("SELECT id, username, created_at FROM users ORDER BY id DESC LIMIT 3");
if ($u) while ($row = $u->fetch_assoc()) $recent[] = ['type' => 'user', 'title' => 'New user: ' . ($row['username'] ?? 'user'), 'time' => $row['created_at'] ?? 'just now'];

// Trim to 6 items and keep order
$recent = array_slice($recent, 0, 6);
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

        .admin-stats .card { border-radius: 12px; box-shadow: 0 6px 18px rgba(15,23,42,0.06); }
        .stat-number { font-size: 2.25rem; font-weight: 700; }
        .stat-sub { color: #10b981; font-size: 0.9rem; }
        .recent-item { padding: 0.9rem 1rem; border-radius: 8px; background: #fff; margin-bottom: 0.8rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); }
        .pending-card { border-radius: 12px; padding: 1rem; background: #fff; box-shadow: 0 6px 18px rgba(15,23,42,0.04); }
    </style>
</head>
<body>

    <div class="container py-3">
        <h2 class="mb-1">Admin Dashboard</h2>
        <p class="text-muted">Welcome back! Here's what's happening with your platform.</p>

        <div class="row g-3 admin-stats mb-4">
            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted">Total Users</div>
                            <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
                            <div class="stat-sub mt-1">+12% from last month</div>
                        </div>
                        <div class="text-muted fs-3"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted">Active Properties</div>
                            <div class="stat-number"><?php echo number_format($activeProperties); ?></div>
                            <div class="stat-sub mt-1">+8% from last month</div>
                        </div>
                        <div class="text-muted fs-3"><i class="bi bi-building"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted">Total Bookings</div>
                            <div class="stat-number"><?php echo number_format($totalBookings); ?></div>
                            <div class="stat-sub mt-1">+23% from last month</div>
                        </div>
                        <div class="text-muted fs-3"><i class="bi bi-calendar-check"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted">Revenue (KES)</div>
                            <div class="stat-number">KES <?php echo number_format($revenue); ?></div>
                            <div class="stat-sub mt-1">+18% from last month</div>
                        </div>
                        <div class="text-muted fs-3"><i class="bi bi-currency-dollar"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted">Commission Earned</div>
                            <div class="stat-number">KES <?php echo number_format($commission); ?></div>
                            <div class="stat-sub mt-1">+15% from last month</div>
                        </div>
                        <div class="text-muted fs-3"><i class="bi bi-graph-up"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted">Pending Reviews</div>
                            <div class="stat-number"><?php echo number_format($pendingProperties + $pendingUsers); ?></div>
                            <div class="stat-sub mt-1"><?php echo $pendingProperties; ?> properties, <?php echo $pendingUsers; ?> users</div>
                        </div>
                        <div class="text-muted fs-3"><i class="bi bi-exclamation-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <h5>Recent Activity</h5>
                <div class="mt-3">
                    <?php if (empty($recent)): ?>
                        <div class="text-muted">No recent activity</div>
                    <?php else: ?>
                        <?php foreach ($recent as $act): ?>
                            <div class="recent-item d-flex align-items-start">
                                <div class="me-3">
                                    <?php if ($act['type'] === 'booking'): ?>
                                        <span class="badge bg-primary">B</span>
                                    <?php elseif ($act['type'] === 'property'): ?>
                                        <span class="badge bg-success">P</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">U</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div><strong><?php echo htmlspecialchars($act['title']); ?></strong></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($act['time']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-4">
                <h5>Pending Actions</h5>
                <div class="mt-3 pending-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong>Properties awaiting approval</strong>
                            <div class="text-muted small"><?php echo $pendingProperties; ?> new listings to review</div>
                        </div>
                        <div><button class="btn btn-outline-secondary">Review</button></div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong>Users pending verification</strong>
                            <div class="text-muted small"><?php echo $pendingUsers; ?> users to verify</div>
                        </div>
                        <div><button class="btn btn-outline-secondary">Review</button></div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Urgent support tickets</strong>
                            <div class="text-muted small">2 urgent issues to resolve</div>
                        </div>
                        <div><button class="btn btn-danger">View</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
