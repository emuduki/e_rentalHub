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

function fmt_money($value) {
	return number_format((float)$value, 0);
}

function fmt_number($value, $decimals = 1) {
	return number_format((float)$value, $decimals);
}

$totalIncome = 0;
$incomeThisMonth = 0;
$incomeLastMonth = 0;
$incomeLast30Days = 0;
$pendingIncome = 0;
$avgBookingValue = 0;
$collectionRate = 0;
$monthlyIncome = [];
$monthlyLabels = [];
$incomeStatusTotals = [
	'confirmed' => 0,
	'completed' => 0,
	'pending' => 0
];
$topProperties = [];
$recentIncome = [];
$insights = [];

if (!$errorMsg) {
	$totalIncome = (float)($conn->query(
		"SELECT COALESCE(SUM(amount), 0) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id AND status IN ('confirmed','completed')"
	)->fetch_assoc()['total'] ?? 0);

	$pendingIncome = (float)($conn->query(
		"SELECT COALESCE(SUM(amount), 0) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id AND status = 'pending'"
	)->fetch_assoc()['total'] ?? 0);

	$avgBookingValue = (float)($conn->query(
		"SELECT COALESCE(AVG(amount), 0) AS avg_amount
		 FROM reservations
		 WHERE landlord_id = $landlord_id AND status IN ('confirmed','completed')"
	)->fetch_assoc()['avg_amount'] ?? 0);

	$incomeLast30Days = (float)($conn->query(
		"SELECT COALESCE(SUM(amount), 0) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id
		   AND status IN ('confirmed','completed')
		   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
	)->fetch_assoc()['total'] ?? 0);

	$incomeThisMonth = (float)($conn->query(
		"SELECT COALESCE(SUM(amount), 0) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id
		   AND status IN ('confirmed','completed')
		   AND YEAR(created_at) = YEAR(CURDATE())
		   AND MONTH(created_at) = MONTH(CURDATE())"
	)->fetch_assoc()['total'] ?? 0);

	$incomeLastMonth = (float)($conn->query(
		"SELECT COALESCE(SUM(amount), 0) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id
		   AND status IN ('confirmed','completed')
		   AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
		   AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
	)->fetch_assoc()['total'] ?? 0);

	$status_result = $conn->query(
		"SELECT status, COALESCE(SUM(amount), 0) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id AND status IN ('confirmed','completed','pending')
		 GROUP BY status"
	);
	if ($status_result) {
		while ($row = $status_result->fetch_assoc()) {
			if (isset($incomeStatusTotals[$row['status']])) {
				$incomeStatusTotals[$row['status']] = (float)$row['total'];
			}
		}
	}

	$expectedIncome = array_sum($incomeStatusTotals);
	$collectionRate = $expectedIncome > 0 ? ($totalIncome / $expectedIncome) * 100 : 0;

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
		"SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
		 FROM reservations
		 WHERE landlord_id = $landlord_id
		   AND status IN ('confirmed','completed')
		   AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
		 GROUP BY ym"
	);
	if ($monthly_result) {
		while ($row = $monthly_result->fetch_assoc()) {
			if (isset($month_buckets[$row['ym']])) {
				$month_buckets[$row['ym']] = (float)$row['total'];
			}
		}
	}
	$monthlyIncome = $month_buckets;
	$monthlyLabels = $month_labels;

	$top_result = $conn->query(
		"SELECT p.id, p.title, p.city, p.rent,
				COALESCE(SUM(r.amount), 0) AS income,
				COUNT(r.id) AS bookings
		 FROM properties p
		 LEFT JOIN reservations r
		   ON r.property_id = p.id
		  AND r.status IN ('confirmed','completed')
		 WHERE p.landlord_id = $landlord_id
		 GROUP BY p.id
		 ORDER BY income DESC, bookings DESC
		 LIMIT 5"
	);
	if ($top_result) {
		while ($row = $top_result->fetch_assoc()) {
			$topProperties[] = $row;
		}
	}

	$recent_result = $conn->query(
		"SELECT r.id, r.amount, r.status, r.created_at, r.check_in_date, p.title, p.city
		 FROM reservations r
		 JOIN properties p ON p.id = r.property_id
		 WHERE r.landlord_id = $landlord_id AND r.status IN ('confirmed','completed')
		 ORDER BY r.created_at DESC
		 LIMIT 6"
	);
	if ($recent_result) {
		while ($row = $recent_result->fetch_assoc()) {
			$recentIncome[] = $row;
		}
	}

	if ($pendingIncome > 0) {
		$insights[] = "KES " . fmt_money($pendingIncome) . " in pending payments. Follow up to improve cash flow.";
	}
	if ($incomeLastMonth > 0 && $incomeThisMonth < $incomeLastMonth) {
		$drop = (($incomeLastMonth - $incomeThisMonth) / $incomeLastMonth) * 100;
		$insights[] = "Income is down " . fmt_number($drop, 1) . "% compared to last month.";
	}
	if ($collectionRate < 70 && $expectedIncome > 0) {
		$insights[] = "Collection rate is below 70%. Consider reminders for pending bookings.";
	}
	if ($totalIncome === 0) {
		$insights[] = "No income yet. Confirm reservations to start generating revenue.";
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Income Reports</title>
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

		.income-shell {
			padding: 24px;
		}

		.income-hero {
			display: flex;
			align-items: flex-end;
			justify-content: space-between;
			gap: 16px;
			flex-wrap: wrap;
			margin-bottom: 20px;
		}

		.income-title {
			font-size: 2rem;
			font-weight: 700;
			margin: 0;
		}

		.income-subtitle {
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
			fill: url(#incomeFill);
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

		.status-pill.confirmed { background: rgba(14, 165, 233, 0.16); color: #0369a1; }
		.status-pill.completed { background: rgba(20, 184, 166, 0.15); color: #0f766e; }
		.status-pill.pending { background: rgba(245, 158, 11, 0.18); color: #b45309; }

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
			.trend-labels {
				grid-template-columns: repeat(3, minmax(0, 1fr));
			}
		}

		@media (max-width: 576px) {
			.income-shell {
				padding: 16px;
			}
			.income-hero {
				align-items: flex-start;
				margin-bottom: 16px;
			}
			.income-title {
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
			.trend-labels {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}
	</style>
</head>
<body>
	<div class="income-shell">
		<div class="income-hero">
			<div>
				<h2 class="income-title">Income Reports</h2>
				<p class="income-subtitle">Monitor cash flow, earnings, and property-level performance.</p>
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
					<h4>Total Collected</h4>
					<i class="bi bi-cash-stack kpi-icon"></i>
				</div>
				<div class="kpi-value">KES <?php echo fmt_money($totalIncome); ?></div>
				<div class="kpi-meta">Confirmed + completed</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>This Month</h4>
					<i class="bi bi-calendar2-week kpi-icon"></i>
				</div>
				<div class="kpi-value">KES <?php echo fmt_money($incomeThisMonth); ?></div>
				<div class="kpi-meta">Last month: KES <?php echo fmt_money($incomeLastMonth); ?></div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Last 30 Days</h4>
					<i class="bi bi-clock-history kpi-icon"></i>
				</div>
				<div class="kpi-value">KES <?php echo fmt_money($incomeLast30Days); ?></div>
				<div class="kpi-meta">Rolling window</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Pending Income</h4>
					<i class="bi bi-hourglass-split kpi-icon"></i>
				</div>
				<div class="kpi-value">KES <?php echo fmt_money($pendingIncome); ?></div>
				<div class="kpi-meta">Awaiting confirmation</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Avg Booking Value</h4>
					<i class="bi bi-graph-up kpi-icon"></i>
				</div>
				<div class="kpi-value">KES <?php echo fmt_money($avgBookingValue); ?></div>
				<div class="kpi-meta">Confirmed + completed</div>
			</div>

			<div class="kpi-card">
				<div class="d-flex justify-content-between align-items-start">
					<h4>Collection Rate</h4>
					<i class="bi bi-percent kpi-icon"></i>
				</div>
				<div class="kpi-value"><?php echo fmt_number($collectionRate, 1); ?>%</div>
				<div class="kpi-meta">Collected vs expected</div>
			</div>
		</div>

		<div class="panel-grid">
			<div class="panel" style="grid-column: span 7;">
				<h5>Income Trends</h5>
				<?php
				$values = array_values($monthlyIncome);
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
					<svg viewBox="0 0 <?php echo $viewWidth; ?> <?php echo $viewHeight; ?>" role="img" aria-label="Monthly income trend">
						<defs>
							<linearGradient id="incomeFill" x1="0" x2="0" y1="0" y2="1">
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
							<text x="<?php echo $paddingLeft; ?>" y="<?php echo $paddingTop + 40; ?>" fill="#94a3b8">No income data yet.</text>
						<?php endif; ?>
					</svg>
					<div class="trend-labels">
						<?php foreach ($labels as $index => $label): ?>
							<div><?php echo htmlspecialchars($label); ?><br><strong>KES <?php echo fmt_money($values[$index] ?? 0); ?></strong></div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="panel" style="grid-column: span 5;">
				<h5>Income Breakdown</h5>
				<?php
				$expectedIncome = array_sum($incomeStatusTotals);
				if ($expectedIncome === 0):
				?>
					<div class="empty-state">No income activity yet.</div>
				<?php else: ?>
					<?php foreach ($incomeStatusTotals as $status => $amount):
						$percent = $expectedIncome > 0 ? round(($amount / $expectedIncome) * 100) : 0;
					?>
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="status-pill <?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
							<span class="fw-semibold">KES <?php echo fmt_money($amount); ?> (<?php echo $percent; ?>%)</span>
						</div>
						<div class="progress mb-3" style="height: 8px;">
							<div class="progress-bar" style="width: <?php echo $percent; ?>%; background: linear-gradient(90deg, var(--accent), var(--accent-2));"></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="panel" style="grid-column: span 7;">
				<h5>Top Earning Properties</h5>
				<?php if (empty($topProperties)): ?>
					<div class="empty-state">No earnings to rank yet.</div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table table-borderless table-modern">
						<thead>
							<tr>
								<th>Property</th>
								<th>City</th>
								<th>Rent</th>
								<th>Bookings</th>
								<th>Income</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($topProperties as $property): ?>
							<tr>
								<td class="fw-semibold"><?php echo htmlspecialchars($property['title']); ?></td>
								<td class="text-muted"><?php echo htmlspecialchars($property['city']); ?></td>
								<td>KES <?php echo fmt_money($property['rent']); ?></td>
								<td><?php echo fmt_number($property['bookings'], 0); ?></td>
								<td class="fw-semibold">KES <?php echo fmt_money($property['income']); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>

			<div class="panel" style="grid-column: span 5;">
				<h5>Recent Income</h5>
				<?php if (empty($recentIncome)): ?>
					<div class="empty-state">No recent income records.</div>
				<?php else: ?>
					<?php foreach ($recentIncome as $income): ?>
						<div class="d-flex justify-content-between align-items-start mb-3">
							<div>
								<div class="fw-semibold"><?php echo htmlspecialchars($income['title']); ?></div>
								<div class="text-muted" style="font-size: 0.85rem;">
									<?php echo htmlspecialchars($income['city']); ?> | Check-in: <?php echo htmlspecialchars($income['check_in_date']); ?>
								</div>
							</div>
							<div class="text-end">
								<span class="status-pill <?php echo $income['status']; ?>"><?php echo ucfirst($income['status']); ?></span>
								<div class="text-muted" style="font-size: 0.85rem;">KES <?php echo fmt_money($income['amount']); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="panel" style="grid-column: span 12;">
				<h5>Actionable Insights</h5>
				<?php if (empty($insights)): ?>
					<div class="insight-item">Income performance looks stable. Keep your listings and pricing optimized.</div>
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