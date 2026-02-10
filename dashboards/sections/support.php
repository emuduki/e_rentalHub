<?php
session_start();

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'admin') {
	header("Location: ../login.html");
	exit();
}

$admin_username = $_SESSION['username'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Support Center</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	<style>
		:root {
			--bg: #f6f8fb;
			--card: #ffffff;
			--border: #e6eaf1;
			--text: #111827;
			--muted: #6b7280;
			--accent: #10b981;
			--accent-soft: #ecfdf5;
			--warning-soft: #fff7ed;
			--danger-soft: #fef2f2;
			--radius: 14px;
		}

		body {
			background: radial-gradient(1100px 460px at 15% 0%, #e7f5ff 0%, rgba(231, 245, 255, 0) 60%),
						radial-gradient(900px 420px at 90% 10%, #f0fdf4 0%, rgba(240, 253, 244, 0) 60%),
						var(--bg);
			color: var(--text);
			padding-top: 56px;
		}

		.support-shell { padding: 24px 10px 40px 10px; }

		.support-title { font-weight: 700; font-size: 1.85rem; }
		.support-subtitle { color: var(--muted); font-size: 0.95rem; }

		.card-soft {
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
			padding: 18px;
		}

		.metric-tile {
			border-radius: 12px;
			border: 1px solid var(--border);
			padding: 14px;
			background: #fff;
		}

		.metric-tile .metric-value { font-size: 1.6rem; font-weight: 700; }

		.pill {
			padding: 4px 10px;
			border-radius: 999px;
			font-size: 0.75rem;
			font-weight: 600;
		}

		.pill.success { background: var(--accent-soft); color: #047857; }
		.pill.warning { background: var(--warning-soft); color: #c2410c; }
		.pill.danger { background: var(--danger-soft); color: #b91c1c; }

		.queue-item {
			border-bottom: 1px solid var(--border);
			padding: 12px 0;
		}
		.queue-item:last-child { border-bottom: 0; }

		.chat-status {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			font-weight: 600;
		}

		.dot {
			width: 10px;
			height: 10px;
			border-radius: 50%;
			background: #22c55e;
		}

		.action-card {
			background: #f8fafc;
			border: 1px dashed var(--border);
			border-radius: 12px;
			padding: 14px;
		}

		.table thead th {
			font-size: 0.8rem;
			text-transform: uppercase;
			letter-spacing: 0.03em;
			color: var(--muted);
		}

		.form-control, .form-select { border-radius: 10px; }

		@media (max-width: 992px) {
			.support-actions { margin-top: 16px; }
		}
	</style>
</head>
<body>
	<div class="container support-shell">
		<div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
			<div>
				<div class="support-title">Support Center</div>
				<div class="support-subtitle">Manage tickets, SLAs, and customer success operations.</div>
			</div>
			<div class="support-actions d-flex align-items-center gap-2">
				<span class="pill success">Online</span>
				<button class="btn btn-outline-secondary"><i class="bi bi-sliders me-2"></i>Filters</button>
				<button class="btn btn-dark"><i class="bi bi-plus-circle me-2"></i>New ticket</button>
			</div>
		</div>

		<div class="row g-3 mb-4">
			<div class="col-md-3">
				<div class="metric-tile">
					<div class="text-muted">Open tickets</div>
					<div class="metric-value">42</div>
					<span class="pill warning">+6 today</span>
				</div>
			</div>
			<div class="col-md-3">
				<div class="metric-tile">
					<div class="text-muted">Avg response time</div>
					<div class="metric-value">2h 18m</div>
					<span class="pill success">Within SLA</span>
				</div>
			</div>
			<div class="col-md-3">
				<div class="metric-tile">
					<div class="text-muted">Escalations</div>
					<div class="metric-value">5</div>
					<span class="pill danger">Needs review</span>
				</div>
			</div>
			<div class="col-md-3">
				<div class="metric-tile">
					<div class="text-muted">CSAT</div>
					<div class="metric-value">92%</div>
					<span class="pill success">+3% MoM</span>
				</div>
			</div>
		</div>

		<div class="row g-4">
			<div class="col-lg-8">
				<div class="card-soft mb-4">
					<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
						<div>
							<h6 class="mb-1">Priority Queue</h6>
							<p class="text-muted mb-0">Live triage list for highest impact tickets.</p>
						</div>
						<div class="d-flex gap-2">
							<input type="search" class="form-control" placeholder="Search tickets" style="min-width: 200px;">
							<select class="form-select" style="min-width: 140px;">
								<option selected>All</option>
								<option>Billing</option>
								<option>Bookings</option>
								<option>Listings</option>
								<option>Security</option>
							</select>
						</div>
					</div>

					<div class="queue-item d-flex align-items-start justify-content-between">
						<div>
							<div class="fw-semibold">Refund requested for booking #BK-2103</div>
							<div class="text-muted small">Student reported duplicate charge. Awaiting payment gateway sync.</div>
							<div class="d-flex gap-2 mt-2">
								<span class="pill warning">High</span>
								<span class="pill">Billing</span>
							</div>
						</div>
						<div class="text-end">
							<div class="text-muted small">2h ago</div>
							<button class="btn btn-outline-primary btn-sm mt-2">Assign</button>
						</div>
					</div>

					<div class="queue-item d-flex align-items-start justify-content-between">
						<div>
							<div class="fw-semibold">Property listing flagged for fraud</div>
							<div class="text-muted small">Multiple users reported suspicious landlord contact.</div>
							<div class="d-flex gap-2 mt-2">
								<span class="pill danger">Urgent</span>
								<span class="pill">Trust & Safety</span>
							</div>
						</div>
						<div class="text-end">
							<div class="text-muted small">45m ago</div>
							<button class="btn btn-outline-primary btn-sm mt-2">Review</button>
						</div>
					</div>

					<div class="queue-item d-flex align-items-start justify-content-between">
						<div>
							<div class="fw-semibold">Landlord payout delay inquiry</div>
							<div class="text-muted small">Bank transfer pending verification.</div>
							<div class="d-flex gap-2 mt-2">
								<span class="pill warning">Medium</span>
								<span class="pill">Payouts</span>
							</div>
						</div>
						<div class="text-end">
							<div class="text-muted small">4h ago</div>
							<button class="btn btn-outline-primary btn-sm mt-2">Assign</button>
						</div>
					</div>
				</div>

				<div class="card-soft">
					<div class="d-flex align-items-center justify-content-between mb-3">
						<div>
							<h6 class="mb-1">Recent Tickets</h6>
							<p class="text-muted mb-0">Latest requests by category and status.</p>
						</div>
						<button class="btn btn-outline-secondary btn-sm">View all</button>
					</div>
					<div class="table-responsive">
						<table class="table align-middle mb-0">
							<thead>
								<tr>
									<th>Ticket</th>
									<th>Category</th>
									<th>Status</th>
									<th>Owner</th>
									<th>Last update</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>#TS-4311 - Booking reschedule</td>
									<td>Bookings</td>
									<td><span class="pill warning">Pending</span></td>
									<td>J. Wambui</td>
									<td>15m ago</td>
								</tr>
								<tr>
									<td>#TS-4310 - Account verification</td>
									<td>Accounts</td>
									<td><span class="pill success">Resolved</span></td>
									<td>M. Odhiambo</td>
									<td>1h ago</td>
								</tr>
								<tr>
									<td>#TS-4308 - Listing suspension</td>
									<td>Trust & Safety</td>
									<td><span class="pill danger">Escalated</span></td>
									<td>A. Mwangi</td>
									<td>3h ago</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="col-lg-4">
				<div class="card-soft mb-4">
					<div class="d-flex align-items-center justify-content-between mb-3">
						<div>
							<h6 class="mb-1">Live Support</h6>
							<p class="text-muted mb-0">Channel readiness and routing.</p>
						</div>
						<span class="chat-status"><span class="dot"></span>Live</span>
					</div>
					<div class="d-flex flex-column gap-3">
						<div class="action-card">
							<div class="fw-semibold">Active chat sessions</div>
							<div class="text-muted">7 ongoing conversations</div>
						</div>
						<div class="action-card">
							<div class="fw-semibold">WhatsApp queue</div>
							<div class="text-muted">3 awaiting reply</div>
						</div>
						<div class="action-card">
							<div class="fw-semibold">Auto replies</div>
							<div class="text-muted">Enabled (Business hours)</div>
						</div>
					</div>
				</div>

				<div class="card-soft mb-4">
					<h6 class="mb-3">Team Workload</h6>
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div>
							<div class="fw-semibold">Support Lead</div>
							<div class="text-muted small">12 tickets</div>
						</div>
						<div class="progress" style="width: 45%; height: 6px;">
							<div class="progress-bar bg-success" style="width: 65%"></div>
						</div>
					</div>
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div>
							<div class="fw-semibold">Billing Agent</div>
							<div class="text-muted small">8 tickets</div>
						</div>
						<div class="progress" style="width: 45%; height: 6px;">
							<div class="progress-bar bg-warning" style="width: 48%"></div>
						</div>
					</div>
					<div class="d-flex justify-content-between align-items-center">
						<div>
							<div class="fw-semibold">Trust & Safety</div>
							<div class="text-muted small">5 tickets</div>
						</div>
						<div class="progress" style="width: 45%; height: 6px;">
							<div class="progress-bar bg-danger" style="width: 72%"></div>
						</div>
					</div>
				</div>

				<div class="card-soft">
					<h6 class="mb-3">Knowledge Base</h6>
					<div class="action-card mb-3">
						<div class="fw-semibold">Top article</div>
						<div class="text-muted">How to resolve payment disputes</div>
						<button class="btn btn-outline-secondary btn-sm mt-2">Open article</button>
					</div>
					<div class="action-card">
						<div class="fw-semibold">Canned responses</div>
						<div class="text-muted">24 templates active</div>
						<button class="btn btn-outline-secondary btn-sm mt-2">Manage templates</button>
					</div>
				</div>
			</div>
		</div>

		<div class="card-soft mt-4">
			<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
				<div>
					<h6 class="mb-1">Escalation Playbook</h6>
					<p class="text-muted mb-0">Set automation for repeatable incidents.</p>
				</div>
				<button class="btn btn-outline-dark btn-sm">Create rule</button>
			</div>
			<div class="row g-3">
				<div class="col-md-4">
					<div class="action-card">
						<div class="fw-semibold">Refund disputes</div>
						<div class="text-muted">Escalate to Finance after 6 hours</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="action-card">
						<div class="fw-semibold">Fraud alerts</div>
						<div class="text-muted">Auto-lock listing and notify Trust team</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="action-card">
						<div class="fw-semibold">Payout delays</div>
						<div class="text-muted">Trigger status update to landlord</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
