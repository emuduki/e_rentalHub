<?php
session_start();
include("../../config/db.php");

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    header("Location: ../index.html");
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$landlord_id = 0;
$errorMsg = null;

if ($user_id > 0) {
    $landlord_row = $conn->query("SELECT id FROM landlords WHERE user_id = $user_id LIMIT 1");
    if ($landlord_row && $landlord_row->num_rows > 0) {
        $landlord_id = (int)$landlord_row->fetch_assoc()['id'];
    }
}

if ($landlord_id === 0) {
    $errorMsg = "Landlord profile not found.";
}

function fmt_number($value, $decimals = 0) {
    return number_format((float)$value, $decimals);
}

$totalProperties = 0;
$propertyStatusCounts = [
    'Available' => 0,
    'Reserved' => 0,
    'Unavailable' => 0
];
$bookingStatusCounts = [
    'pending' => 0,
    'confirmed' => 0,
    'cancelled' => 0,
    'completed' => 0
];
$totalReservations = 0;
$totalSaves = 0;
$totalRevenue = 0;
$avgRent = 0;
$avgLeaseLength = 0;
$avgDaysToFirstBooking = null;
$monthlyBookings = [];
$monthlyLabels = [];
$last30Bookings = 0;
$leasedProperties = 0;
$topProperties = [];
$recentReservations = [];
$insights = [];

if (!$errorMsg) {
    $totalProperties = (int)($conn->query("SELECT COUNT(*) AS total FROM properties WHERE landlord_id = $landlord_id")
        ->fetch_assoc()['total'] ?? 0);

    $status_result = $conn->query("SELECT status, COUNT(*) AS total FROM properties WHERE landlord_id = $landlord_id GROUP BY status");
    if ($status_result) {
        while ($row = $status_result->fetch_assoc()) {
            if (isset($propertyStatusCounts[$row['status']])) {
                $propertyStatusCounts[$row['status']] = (int)$row['total'];
            }
        }
    }

    $booking_result = $conn->query("SELECT status, COUNT(*) AS total FROM reservations WHERE landlord_id = $landlord_id GROUP BY status");
    if ($booking_result) {
        while ($row = $booking_result->fetch_assoc()) {
            if (isset($bookingStatusCounts[$row['status']])) {
                $bookingStatusCounts[$row['status']] = (int)$row['total'];
            }
        }
    }
    $totalReservations = array_sum($bookingStatusCounts);

    $totalSaves = (int)($conn->query("SELECT COUNT(*) AS total FROM saved_properties sp JOIN properties p ON p.id = sp.property_id WHERE p.landlord_id = $landlord_id")
        ->fetch_assoc()['total'] ?? 0);

    $totalRevenue = (float)($conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM reservations WHERE landlord_id = $landlord_id AND status IN ('confirmed','completed')")
        ->fetch_assoc()['total'] ?? 0);

    $avgRent = (float)($conn->query("SELECT COALESCE(AVG(rent), 0) AS avg_rent FROM properties WHERE landlord_id = $landlord_id")
        ->fetch_assoc()['avg_rent'] ?? 0);

    $avgLeaseLength = (float)($conn->query("SELECT COALESCE(AVG(lease_length), 0) AS avg_lease FROM reservations WHERE landlord_id = $landlord_id")
        ->fetch_assoc()['avg_lease'] ?? 0);

    $leasedProperties = (int)($conn->query("SELECT COUNT(DISTINCT property_id) AS total FROM reservations WHERE landlord_id = $landlord_id AND status IN ('confirmed','completed')")
        ->fetch_assoc()['total'] ?? 0);

    $last30Bookings = (int)($conn->query("SELECT COUNT(*) AS total FROM reservations WHERE landlord_id = $landlord_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")
        ->fetch_assoc()['total'] ?? 0);

    $avg_days_result = $conn->query(
        "SELECT AVG(DATEDIFF(r.first_booking, p.created_at)) AS avg_days
         FROM properties p
         JOIN (SELECT property_id, MIN(created_at) AS first_booking FROM reservations WHERE landlord_id = $landlord_id GROUP BY property_id) r
           ON r.property_id = p.id
         WHERE p.landlord_id = $landlord_id"
    );
    if ($avg_days_result) {
        $avgDaysToFirstBooking = $avg_days_result->fetch_assoc()['avg_days'];
        if ($avgDaysToFirstBooking !== null) {
            $avgDaysToFirstBooking = (float)$avgDaysToFirstBooking;
        }
    }

    $month_buckets = [];
    $month_labels = [];
    $now = new DateTime('first day of this month');
    for ($i = 5; $i >= 0; $i--) {
        $month = (clone $now)->modify("-$i months");
        $key = $month->format('Y-m');
        $month_buckets[$key] = 0;
        $month_labels[$key] = $month->format('M Y');
    }
    $monthly_result = $conn->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
         FROM reservations
         WHERE landlord_id = $landlord_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY ym"
    );
    if ($monthly_result) {
        while ($row = $monthly_result->fetch_assoc()) {
            $key = $row['ym'];
            if (isset($month_buckets[$key])) {
                $month_buckets[$key] = (int)$row['total'];
            }
        }
    }
    $monthlyBookings = $month_buckets;
    $monthlyLabels = $month_labels;

    $top_result = $conn->query(
        "SELECT p.id, p.title, p.city, p.rent, p.status,
                COALESCE(sp.saves, 0) AS saves,
                COALESCE(rb.bookings, 0) AS bookings,
                COALESCE(rb.pending, 0) AS pending
         FROM properties p
         LEFT JOIN (SELECT property_id, COUNT(*) AS saves FROM saved_properties GROUP BY property_id) sp
           ON sp.property_id = p.id
         LEFT JOIN (
            SELECT property_id,
                   SUM(status IN ('confirmed','completed')) AS bookings,
                   SUM(status = 'pending') AS pending
            FROM reservations
            WHERE landlord_id = $landlord_id
            GROUP BY property_id
         ) rb ON rb.property_id = p.id
         WHERE p.landlord_id = $landlord_id
         ORDER BY bookings DESC, saves DESC, p.created_at DESC
         LIMIT 5"
    );
    if ($top_result) {
        while ($row = $top_result->fetch_assoc()) {
            $topProperties[] = $row;
        }
    }

    $recent_result = $conn->query(
        "SELECT r.id, r.status, r.created_at, r.check_in_date, r.amount, p.title, p.city
         FROM reservations r
         JOIN properties p ON p.id = r.property_id
         WHERE r.landlord_id = $landlord_id
         ORDER BY r.created_at DESC
         LIMIT 6"
    );
    if ($recent_result) {
        while ($row = $recent_result->fetch_assoc()) {
            $recentReservations[] = $row;
        }
    }

    if ($totalProperties === 0) {
        $insights[] = "Add your first listing to start tracking demand and bookings.";
    }
    $occupancyRate = $totalProperties > 0 ? ($leasedProperties / $totalProperties) * 100 : 0;
    if ($totalProperties > 0 && $occupancyRate < 50) {
        $insights[] = "Occupancy is below 50%. Consider refreshing photos or pricing to boost bookings.";
    }
    if ($totalSaves > 0 && ($bookingStatusCounts['confirmed'] + $bookingStatusCounts['completed']) === 0) {
        $insights[] = "You are getting saves but no confirmed bookings. Try faster follow-up on inquiries.";
    }
    if ($bookingStatusCounts['pending'] > 3) {
        $insights[] = "You have multiple pending requests. Quick responses can lift your conversion rate.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #111827;
            --muted: #64748b;
            --accent: #0ea5e9;
            --accent-2: #14b8a6;
            --accent-3: #f59e0b;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --stroke: rgba(15, 23, 42, 0.08);
            --shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: radial-gradient(circle at top, rgba(14, 165, 233, 0.12), transparent 50%),
                linear-gradient(180deg, #f1f5f9 0%, #ffffff 60%);
            padding-top: 56px;
            font-family: "Space Grotesk", "Segoe UI", sans-serif;
            color: var(--ink);
        }

        .analytics-shell {
            padding: 24px;
        }

        .analytics-hero {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .analytics-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .analytics-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
        }

        .hero-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .chip {
            background: var(--surface);
            border: 1px solid var(--stroke);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--stroke);
            border-radius: 18px;
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .kpi-card h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--muted);
            margin: 0;
        }

        .kpi-value {
            font-size: 1.7rem;
            font-weight: 700;
            margin-top: 10px;
        }

        .kpi-meta {
            margin-top: 6px;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .kpi-icon {
            font-size: 1.6rem;
            color: var(--accent);
        }

        .panel-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 16px;
        }

        .panel {
            background: var(--surface);
            border-radius: 18px;
            border: 1px solid var(--stroke);
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .panel h5 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .mini-bar {
            display: grid;
            grid-template-columns: 90px 1fr 40px;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .mini-bar__track {
            height: 10px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .mini-bar__fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
        }

        .trend-chart {
            background: var(--surface-2);
            border-radius: 16px;
            border: 1px solid var(--stroke);
            padding: 12px;
        }

        .trend-chart svg {
            width: 100%;
            height: auto;
            display: block;
        }

        .trend-grid {
            stroke: rgba(15, 23, 42, 0.08);
            stroke-width: 1;
        }

        .trend-line {
            fill: none;
            stroke: var(--accent);
            stroke-width: 3;
        }

        .trend-area {
            fill: url(#trendFill);
        }

        .trend-point {
            fill: #ffffff;
            stroke: var(--accent-2);
            stroke-width: 2;
        }

        .trend-labels {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 6px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: var(--muted);
            text-align: center;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pill.available { background: rgba(20, 184, 166, 0.15); color: #0f766e; }
        .status-pill.reserved { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .status-pill.unavailable { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }

        .status-pill.pending { background: rgba(245, 158, 11, 0.18); color: #b45309; }
        .status-pill.confirmed { background: rgba(14, 165, 233, 0.16); color: #0369a1; }
        .status-pill.completed { background: rgba(20, 184, 166, 0.15); color: #0f766e; }
        .status-pill.cancelled { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }

        .insight-list {
            display: grid;
            gap: 10px;
        }

        .insight-item {
            padding: 12px;
            border-radius: 14px;
            background: var(--surface-2);
            border: 1px solid var(--stroke);
            font-size: 0.9rem;
        }

        .table-modern thead th {
            color: var(--muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--stroke);
        }

        .table-modern td {
            vertical-align: middle;
        }

        .empty-state {
            padding: 16px;
            border-radius: 14px;
            background: var(--surface-2);
            color: var(--muted);
            border: 1px dashed var(--stroke);
            text-align: center;
        }

        @media (max-width: 992px) {
            .kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .panel-grid {
                grid-template-columns: 1fr;
            }
            .panel-grid .panel {
                grid-column: 1 / -1 !important;
            }
        }

        @media (max-width: 768px) {
            .panel-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
            .panel-grid .panel {
                grid-column: 1 / -1 !important;
            }
            .mini-bar {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            .trend-labels {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .analytics-shell {
                padding: 16px;
            }
            .analytics-hero {
                align-items: flex-start;
                margin-bottom: 16px;
            }
            .analytics-title {
                font-size: 1.6rem;
            }
            .chip {
                font-size: 0.78rem;
                padding: 5px 10px;
            }
            .kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
            .kpi-card {
                padding: 12px;
            }
            .kpi-card h4 {
                font-size: 0.85rem;
            }
            .kpi-value {
                font-size: 1.3rem;
            }
            .kpi-meta {
                font-size: 0.8rem;
            }
            .kpi-icon {
                font-size: 1.3rem;
            }
            .panel {
                padding: 12px;
                border-radius: 14px;
                box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
            }
            .panel h5 {
                font-size: 0.95rem;
                margin-bottom: 10px;
            }
            .panel-grid {
                grid-template-columns: 1fr;
            }
            .panel-grid .panel {
                grid-column: 1 / -1 !important;
            }
            .trend-labels {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="analytics-shell">
        <div class="analytics-hero">
            <div>
                <h2 class="analytics-title">Property Analytics</h2>
                <p class="analytics-subtitle">Real-time performance, demand, and revenue insights for your listings.</p>
            </div>
            <div class="hero-chips">
                <span class="chip">Period: last 6 months</span>
                <span class="chip">Updated: <?php echo date('M d, Y'); ?></span>
            </div>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php else: ?>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h4>Total Properties</h4>
                    <i class="bi bi-houses kpi-icon"></i>
                </div>
                <div class="kpi-value"><?php echo fmt_number($totalProperties); ?></div>
                <div class="kpi-meta"><?php echo fmt_number($propertyStatusCounts['Available']); ?> available</div>
            </div>

            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h4>Occupancy Rate</h4>
                    <i class="bi bi-bar-chart kpi-icon"></i>
                </div>
                <div class="kpi-value"><?php echo fmt_number($totalProperties > 0 ? ($leasedProperties / $totalProperties) * 100 : 0, 1); ?>%</div>
                <div class="kpi-meta"><?php echo fmt_number($leasedProperties); ?> leased properties</div>
            </div>

            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h4>Bookings (30 days)</h4>
                    <i class="bi bi-calendar-check kpi-icon"></i>
                </div>
                <div class="kpi-value"><?php echo fmt_number($last30Bookings); ?></div>
                <div class="kpi-meta"><?php echo fmt_number($totalReservations); ?> total reservations</div>
            </div>

            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h4>Total Revenue</h4>
                    <i class="bi bi-cash-coin kpi-icon"></i>
                </div>
                <div class="kpi-value">KES <?php echo fmt_number($totalRevenue, 0); ?></div>
                <div class="kpi-meta">Confirmed + completed</div>
            </div>

            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h4>Demand Signals</h4>
                    <i class="bi bi-heart kpi-icon"></i>
                </div>
                <div class="kpi-value"><?php echo fmt_number($totalSaves); ?></div>
                <div class="kpi-meta"><?php echo fmt_number($totalSaves > 0 ? (($bookingStatusCounts['confirmed'] + $bookingStatusCounts['completed']) / $totalSaves) * 100 : 0, 1); ?>% save-to-booking</div>
            </div>

            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h4>Average Rent</h4>
                    <i class="bi bi-graph-up kpi-icon"></i>
                </div>
                <div class="kpi-value">KES <?php echo fmt_number($avgRent, 0); ?></div>
                <div class="kpi-meta">Avg lease: <?php echo fmt_number($avgLeaseLength, 1); ?> months</div>
            </div>
        </div>

        <div class="panel-grid">
            <div class="panel" style="grid-column: span 7;">
                <h5>Booking Trends</h5>
                <?php
                $values = array_values($monthlyBookings);
                $labels = array_values($monthlyLabels);
                $maxMonthly = $values ? max($values) : 0;
                $pointCount = count($values);
                $chartWidth = 520;
                $chartHeight = 140;
                $paddingLeft = 40;
                $paddingTop = 20;
                $paddingBottom = 40;
                $paddingRight = 20;
                $viewWidth = $paddingLeft + $chartWidth + $paddingRight;
                $viewHeight = $paddingTop + $chartHeight + $paddingBottom;
                $xStep = $pointCount > 1 ? $chartWidth / ($pointCount - 1) : 0;
                $points = [];
                foreach ($values as $i => $value) {
                    $x = $paddingLeft + ($xStep * $i);
                    $y = $paddingTop + ($maxMonthly > 0 ? $chartHeight - (($value / $maxMonthly) * $chartHeight) : $chartHeight);
                    $points[] = [$x, $y, $value];
                }
                $linePath = '';
                foreach ($points as $i => $point) {
                    $prefix = $i === 0 ? 'M' : 'L';
                    $linePath .= $prefix . ' ' . round($point[0], 2) . ' ' . round($point[1], 2) . ' ';
                }
                $areaPath = trim($linePath);
                if ($areaPath !== '') {
                    $areaPath .= ' L ' . ($paddingLeft + $chartWidth) . ' ' . ($paddingTop + $chartHeight) . ' L ' . $paddingLeft . ' ' . ($paddingTop + $chartHeight) . ' Z';
                }
                ?>
                <div class="trend-chart">
                    <svg viewBox="0 0 <?php echo $viewWidth; ?> <?php echo $viewHeight; ?>" role="img" aria-label="Monthly booking trend">
                        <defs>
                            <linearGradient id="trendFill" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#0ea5e9" stop-opacity="0.35"></stop>
                                <stop offset="100%" stop-color="#0ea5e9" stop-opacity="0.05"></stop>
                            </linearGradient>
                        </defs>
                        <?php for ($g = 0; $g <= 4; $g++):
                            $y = $paddingTop + ($chartHeight * ($g / 4));
                        ?>
                            <line class="trend-grid" x1="<?php echo $paddingLeft; ?>" y1="<?php echo $y; ?>" x2="<?php echo $paddingLeft + $chartWidth; ?>" y2="<?php echo $y; ?>"></line>
                        <?php endfor; ?>
                        <?php if ($areaPath !== ''): ?>
                            <path class="trend-area" d="<?php echo trim($areaPath); ?>"></path>
                            <path class="trend-line" d="<?php echo trim($linePath); ?>"></path>
                            <?php foreach ($points as $point): ?>
                                <circle class="trend-point" cx="<?php echo round($point[0], 2); ?>" cy="<?php echo round($point[1], 2); ?>" r="4"></circle>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <text x="<?php echo $paddingLeft; ?>" y="<?php echo $paddingTop + 40; ?>" fill="#94a3b8">No booking data yet.</text>
                        <?php endif; ?>
                    </svg>
                    <div class="trend-labels">
                        <?php foreach ($labels as $index => $label): ?>
                            <div><?php echo htmlspecialchars($label); ?><br><strong><?php echo fmt_number($values[$index] ?? 0); ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="panel" style="grid-column: span 5;">
                <h5>Booking Pipeline</h5>
                <?php if ($totalReservations === 0): ?>
                    <div class="empty-state">No reservations yet.</div>
                <?php else: ?>
                    <?php foreach ($bookingStatusCounts as $status => $count):
                        $percent = $totalReservations > 0 ? round(($count / $totalReservations) * 100) : 0;
                    ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="status-pill <?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
                            <span class="fw-semibold"><?php echo fmt_number($count); ?> (<?php echo $percent; ?>%)</span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar" style="width: <?php echo $percent; ?>%; background: linear-gradient(90deg, var(--accent), var(--accent-2));"></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel" style="grid-column: span 7;">
                <h5>Top Performing Properties</h5>
                <?php if (empty($topProperties)): ?>
                    <div class="empty-state">No properties to rank yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-borderless table-modern">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>City</th>
                                <th>Rent</th>
                                <th>Saves</th>
                                <th>Bookings</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProperties as $property): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($property['title']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($property['city']); ?></td>
                                <td>KES <?php echo fmt_number($property['rent'], 0); ?></td>
                                <td><?php echo fmt_number($property['saves']); ?></td>
                                <td><?php echo fmt_number($property['bookings']); ?></td>
                                <td>
                                    <?php
                                        $status_class = strtolower($property['status']);
                                    ?>
                                    <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($property['status']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="panel" style="grid-column: span 5;">
                <h5>Recent Reservations</h5>
                <?php if (empty($recentReservations)): ?>
                    <div class="empty-state">No recent reservations.</div>
                <?php else: ?>
                    <?php foreach ($recentReservations as $reservation): ?>
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($reservation['title']); ?></div>
                                <div class="text-muted" style="font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($reservation['city']); ?> | Check-in: <?php echo htmlspecialchars($reservation['check_in_date']); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="status-pill <?php echo $reservation['status']; ?>"><?php echo ucfirst($reservation['status']); ?></span>
                                <div class="text-muted" style="font-size: 0.85rem;">KES <?php echo fmt_number($reservation['amount'], 0); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel" style="grid-column: span 12;">
                <h5>Actionable Insights</h5>
                <?php if (empty($insights)): ?>
                    <div class="insight-item">Your portfolio is healthy. Keep monitoring demand and keep listings up to date.</div>
                <?php else: ?>
                    <div class="insight-list">
                        <?php foreach ($insights as $insight): ?>
                            <div class="insight-item"><?php echo htmlspecialchars($insight); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="mt-3 text-muted" style="font-size: 0.85rem;">
                    Avg time to first booking: <?php echo $avgDaysToFirstBooking === null ? 'N/A' : fmt_number($avgDaysToFirstBooking, 1) . ' days'; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>