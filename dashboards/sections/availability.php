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
$statusCounts = [
	'Available' => 0,
	'Reserved' => 0,
	'Unavailable' => 0
];
$upcomingCheckins = 0;
$activeBookings = 0;
$avgLeadDays = null;
$outlookCounts = [];
$outlookLabels = [];
$propertyRows = [];
$upcomingReservations = [];
$insights = [];

if (!$errorMsg) {
	$totalProperties = (int)($conn->query("SELECT COUNT(*) AS total FROM properties WHERE landlord_id = $landlord_id")
		->fetch_assoc()['total'] ?? 0);

	$status_result = $conn->query("SELECT status, COUNT(*) AS total FROM properties WHERE landlord_id = $landlord_id GROUP BY status");
	if ($status_result) {
		while ($row = $status_result->fetch_assoc()) {
			if (isset($statusCounts[$row['status']])) {
				$statusCounts[$row['status']] = (int)$row['total'];
			}
		}
	}

	$upcomingCheckins = (int)($conn->query(
		"SELECT COUNT(*) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id
		   AND status IN ('pending','confirmed')
		   AND check_in_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
	)->fetch_assoc()['total'] ?? 0);

	$activeBookings = (int)($conn->query(
		"SELECT COUNT(*) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id
		   AND status = 'confirmed'
		   AND check_in_date <= CURDATE()
		   AND DATE_ADD(check_in_date, INTERVAL lease_length MONTH) >= CURDATE()"
	)->fetch_assoc()['total'] ?? 0);

	$lead_result = $conn->query(
		"SELECT AVG(DATEDIFF(r.first_booking, p.created_at)) AS avg_days
		 FROM properties p
		 JOIN (SELECT property_id, MIN(created_at) AS first_booking
			   FROM reservations
			   WHERE landlord_id = $landlord_id
			   GROUP BY property_id) r
		   ON r.property_id = p.id
		 WHERE p.landlord_id = $landlord_id"
	);
	if ($lead_result) {
		$avgLeadDays = $lead_result->fetch_assoc()['avg_days'];
		if ($avgLeadDays !== null) {
			$avgLeadDays = (float)$avgLeadDays;
		}
	}

	$month_buckets = [];
	$month_labels = [];
	$now = new DateTime('first day of this month');
	for ($i = 0; $i <= 5; $i++) {
		$month = (clone $now)->modify("+$i months");
		$key = $month->format('Y-m');
		$month_buckets[$key] = 0;
		$month_labels[$key] = $month->format('M Y');
	}
	$outlook_result = $conn->query(
		"SELECT DATE_FORMAT(check_in_date, '%Y-%m') AS ym, COUNT(*) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id
		   AND status IN ('pending','confirmed')
		   AND check_in_date >= CURDATE()
		   AND check_in_date < DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
		 GROUP BY ym"
	);
	if ($outlook_result) {
		while ($row = $outlook_result->fetch_assoc()) {
			if (isset($month_buckets[$row['ym']])) {
				$month_buckets[$row['ym']] = (int)$row['total'];
			}
		}
	}
	$outlookCounts = $month_buckets;
	$outlookLabels = $month_labels;

	$property_result = $conn->query(
		"SELECT p.id, p.title, p.city, p.status, p.rent,
				MIN(CASE WHEN r.status IN ('pending','confirmed') AND r.check_in_date >= CURDATE()
					THEN r.check_in_date END) AS next_check_in,
				SUM(r.status = 'pending') AS pending_requests,
				SUM(r.status = 'confirmed') AS confirmed_requests,
				MAX(CASE WHEN r.status IN ('confirmed','completed')
					THEN DATE_ADD(r.check_in_date, INTERVAL r.lease_length MONTH) END) AS last_checkout
		 FROM properties p
		 LEFT JOIN reservations r ON r.property_id = p.id
		 WHERE p.landlord_id = $landlord_id
		 GROUP BY p.id
		 ORDER BY FIELD(p.status, 'Available', 'Reserved', 'Unavailable'), next_check_in ASC"
	);
	if ($property_result) {
		while ($row = $property_result->fetch_assoc()) {
			$propertyRows[] = $row;
		}
	}

	$upcoming_result = $conn->query(
		"SELECT r.id, r.status, r.check_in_date, r.lease_length, p.title, p.city
		 FROM reservations r
		 JOIN properties p ON p.id = r.property_id
		 WHERE r.landlord_id = $landlord_id
		   AND r.status IN ('pending','confirmed')
		   AND r.check_in_date >= CURDATE()
		 ORDER BY r.check_in_date ASC
		 LIMIT 6"
	);
	if ($upcoming_result) {
		while ($row = $upcoming_result->fetch_assoc()) {
			$upcomingReservations[] = $row;
		}
	}

	if ($statusCounts['Available'] > 0 && $upcomingCheckins === 0) {
		$insights[] = "Listings are available but no upcoming check-ins. Consider promoting or updating your listings.";
	}
	if ($statusCounts['Reserved'] > $statusCounts['Available']) {
		$insights[] = "More reserved listings than available. Prepare for upcoming move-ins.";
	}
	if ($avgLeadDays !== null && $avgLeadDays > 21) {
		$insights[] = "Average time to first booking is above 3 weeks. Highlight pricing or amenities to boost demand.";
	}
	if ($totalProperties === 0) {
		$insights[] = "Add a property to start managing availability.";
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Availability</title>
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

		.availability-shell {
			padding: 24px;
		}

		.availability-hero {
			display: flex;
			align-items: flex-end;
			justify-content: space-between;
			gap: 16px;
			flex-wrap: wrap;
			margin-bottom: 20px;
		}

		.availability-title {
			font-size: 2rem;
			font-weight: 700;
			margin: 0;
		}

		.availability-subtitle {
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
				grid-template-columns: 1fr;
			}
			.panel-grid .panel {
				grid-column: 1 / -1 !important;
			}
			.mini-bar {
				grid-template-columns: 1fr;
				gap: 6px;
			}
		}

		@media (max-width: 576px) {
			.availability-shell {
				padding: 16px;
			}
			.availability-hero {
				align-items: flex-start;
				margin-bottom: 16px;
			}
			.availability-title {
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
		}
	</style>
</head>
<body>
	<div class="availability-shell">
		<div class="availability-hero">
			<div>
				<h2 class="availability-title">Availability</h2>
				<p class="availability-subtitle">Track vacancies, upcoming move-ins, and property readiness.</p>
			</div>
			<div class="hero-chips">
				<span class="chip">Period: next 6 months</span>
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
				<div class="kpi-meta"><?php echo fmt_number($statusCounts['Available']); ?> available</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Active Bookings</h4>
					<i class="bi bi-door-open kpi-icon"></i>
				</div>
				<div class="kpi-value"><?php echo fmt_number($activeBookings); ?></div>
				<div class="kpi-meta">Currently occupied</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Upcoming Check-ins</h4>
					<i class="bi bi-calendar-event kpi-icon"></i>
				</div>
				<div class="kpi-value"><?php echo fmt_number($upcomingCheckins); ?></div>
				<div class="kpi-meta">Next 30 days</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Available Now</h4>
					<i class="bi bi-check-circle kpi-icon"></i>
				</div>
				<div class="kpi-value"><?php echo fmt_number($statusCounts['Available']); ?></div>
				<div class="kpi-meta">Ready to list</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Reserved</h4>
					<i class="bi bi-hourglass-split kpi-icon"></i>
				</div>
				<div class="kpi-value"><?php echo fmt_number($statusCounts['Reserved']); ?></div>
				<div class="kpi-meta">Pending move-in</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Avg Lead Time</h4>
					<i class="bi bi-stopwatch kpi-icon"></i>
				</div>
				<div class="kpi-value"><?php echo $avgLeadDays === null ? 'N/A' : fmt_number($avgLeadDays, 1) . 'd'; ?></div>
				<div class="kpi-meta">To first booking</div>
			</div>
		</div>

		<div class="panel-grid">
			<div class="panel" style="grid-column: span 7;">
				<h5>Availability Outlook</h5>
				<?php
				$maxOutlook = $outlookCounts ? max($outlookCounts) : 0;
				foreach ($outlookCounts as $key => $count):
					$width = $maxOutlook > 0 ? round(($count / $maxOutlook) * 100) : 0;
					if ($count > 0 && $width < 8) {
						$width = 8;
					}
				?>
				<div class="mini-bar">
					<div class="text-muted"><?php echo htmlspecialchars($outlookLabels[$key]); ?></div>
					<div class="mini-bar__track">
						<div class="mini-bar__fill" style="width: <?php echo $width; ?>%;"></div>
					</div>
					<div class="fw-semibold text-end"><?php echo fmt_number($count); ?></div>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="panel" style="grid-column: span 5;">
				<h5>Upcoming Move-ins</h5>
				<?php if (empty($upcomingReservations)): ?>
					<div class="empty-state">No upcoming check-ins.</div>
				<?php else: ?>
					<?php foreach ($upcomingReservations as $reservation): ?>
						<div class="d-flex justify-content-between align-items-start mb-3">
							<div>
								<div class="fw-semibold"><?php echo htmlspecialchars($reservation['title']); ?></div>
								<div class="text-muted" style="font-size: 0.85rem;">
									<?php echo htmlspecialchars($reservation['city']); ?> | Check-in: <?php echo htmlspecialchars($reservation['check_in_date']); ?>
								</div>
							</div>
							<div class="text-end">
								<span class="status-pill <?php echo $reservation['status']; ?>"><?php echo ucfirst($reservation['status']); ?></span>
								<div class="text-muted" style="font-size: 0.85rem;">Lease: <?php echo fmt_number($reservation['lease_length']); ?> mo</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="panel" style="grid-column: span 12;">
				<h5>Property Availability</h5>
				<?php if (empty($propertyRows)): ?>
					<div class="empty-state">No properties to display.</div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table table-borderless table-modern">
						<thead>
							<tr>
								<th>Property</th>
								<th>City</th>
								<th>Status</th>
								<th>Next Check-in</th>
								<th>Requests</th>
								<th>Vacancy Days</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($propertyRows as $property): ?>
								<?php
									$status_class = strtolower($property['status']);
									$vacancyDays = null;
									if (!empty($property['last_checkout'])) {
										$vacancyDays = max(0, (int)floor((time() - strtotime($property['last_checkout'])) / 86400));
									}
								?>
								<tr>
									<td class="fw-semibold"><?php echo htmlspecialchars($property['title']); ?></td>
									<td class="text-muted"><?php echo htmlspecialchars($property['city']); ?></td>
									<td><span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($property['status']); ?></span></td>
									<td><?php echo $property['next_check_in'] ? htmlspecialchars($property['next_check_in']) : 'None'; ?></td>
									<td><?php echo fmt_number($property['pending_requests']); ?> pending / <?php echo fmt_number($property['confirmed_requests']); ?> confirmed</td>
									<td><?php echo $vacancyDays === null ? 'N/A' : fmt_number($vacancyDays) . ' days'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>

			<div class="panel" style="grid-column: span 12;">
				<h5>Actionable Insights</h5>
				<?php if (empty($insights)): ?>
					<div class="insight-item">Availability looks balanced. Keep monitoring demand and updates.</div>
				<?php else: ?>
					<div class="insight-list">
						<?php foreach ($insights as $insight): ?>
							<div class="insight-item"><?php echo htmlspecialchars($insight); ?></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
</body>
</html>
