<?php
session_start();

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'admin') {
	header("Location: ../login.html");
	exit();
}

$admin_username = $_SESSION['username'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? 'admin@example.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Settings</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	<style>
		:root {
			--bg: #f4f6fb;
			--card: #ffffff;
			--border: #e6e9f0;
			--text: #1f2937;
			--muted: #6b7280;
			--accent: #0ea5e9;
			--accent-soft: #e0f2fe;
			--radius: 14px;
		}

		body {
			background: radial-gradient(1100px 420px at 10% 0%, #eef2ff 0%, rgba(238, 242, 255, 0) 60%),
						radial-gradient(900px 420px at 90% 10%, #e0f2fe 0%, rgba(224, 242, 254, 0) 60%),
						var(--bg);
			color: var(--text);
			padding-top: 56px;
		}

		.settings-shell {
			padding: 24px 10px 40px 10px;
		}

		.settings-title {
			font-weight: 700;
			font-size: 1.8rem;
		}

		.settings-subtitle {
			color: var(--muted);
			font-size: 0.95rem;
		}

		.settings-nav {
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
			padding: 10px;
		}

		.settings-nav .list-group-item {
			border: 0;
			border-radius: 10px;
			font-weight: 600;
			color: var(--muted);
		}

		.settings-nav .list-group-item.active {
			background: var(--accent-soft);
			color: var(--accent);
		}

		.settings-card {
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
			padding: 18px;
		}

		.settings-card h6 {
			font-weight: 700;
		}

		.form-switch .form-check-input {
			width: 2.6rem;
			height: 1.4rem;
		}

		.pill {
			background: #eef2ff;
			color: #4338ca;
			padding: 4px 10px;
			border-radius: 999px;
			font-size: 0.75rem;
			font-weight: 600;
		}

		.action-tile {
			border: 1px dashed var(--border);
			border-radius: 12px;
			padding: 14px;
			background: #f8fafc;
		}

		.sticky-save {
			position: sticky;
			bottom: 16px;
			z-index: 10;
			border-radius: 12px;
			padding: 12px;
			background: rgba(255, 255, 255, 0.9);
			border: 1px solid var(--border);
			backdrop-filter: blur(8px);
		}

		@media (max-width: 992px) {
			.settings-nav {
				margin-bottom: 16px;
			}
		}
	</style>
</head>
<body>
	<div class="container settings-shell">
		<div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
			<div>
				<div class="settings-title">Admin Settings</div>
				<div class="settings-subtitle">Tune platform behavior, security, and support workflows.</div>
			</div>
			<div class="d-flex align-items-center gap-2">
				<span class="pill">Last saved: Feb 10, 2026</span>
				<button class="btn btn-dark"><i class="bi bi-save2 me-2"></i>Save changes</button>
			</div>
		</div>

		<div class="row g-4">
			<div class="col-lg-3">
				<div class="settings-nav list-group" id="settings-tabs" role="tablist">
					<button class="list-group-item list-group-item-action active" data-bs-toggle="list" data-bs-target="#tab-profile" type="button" role="tab">
						<i class="bi bi-person-gear me-2"></i>Profile
					</button>
					<button class="list-group-item list-group-item-action" data-bs-toggle="list" data-bs-target="#tab-security" type="button" role="tab">
						<i class="bi bi-shield-lock me-2"></i>Security
					</button>
					<button class="list-group-item list-group-item-action" data-bs-toggle="list" data-bs-target="#tab-notifications" type="button" role="tab">
						<i class="bi bi-bell me-2"></i>Notifications
					</button>
					<button class="list-group-item list-group-item-action" data-bs-toggle="list" data-bs-target="#tab-platform" type="button" role="tab">
						<i class="bi bi-gear me-2"></i>Platform
					</button>
					<button class="list-group-item list-group-item-action" data-bs-toggle="list" data-bs-target="#tab-support" type="button" role="tab">
						<i class="bi bi-life-preserver me-2"></i>Support
					</button>
					<button class="list-group-item list-group-item-action" data-bs-toggle="list" data-bs-target="#tab-data" type="button" role="tab">
						<i class="bi bi-database me-2"></i>Data & Backup
					</button>
					<button class="list-group-item list-group-item-action" data-bs-toggle="list" data-bs-target="#tab-integrations" type="button" role="tab">
						<i class="bi bi-plug me-2"></i>Integrations
					</button>
				</div>
			</div>

			<div class="col-lg-9">
				<div class="tab-content" id="settings-tab-content">
					<div class="tab-pane fade show active" id="tab-profile" role="tabpanel">
						<div class="settings-card mb-4">
							<div class="d-flex align-items-center justify-content-between mb-3">
								<div>
									<h6 class="mb-1">Admin Profile</h6>
									<p class="text-muted mb-0">Manage your admin identity and access.</p>
								</div>
								<span class="pill">Owner</span>
							</div>
							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label">Username</label>
									<input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_username); ?>">
								</div>
								<div class="col-md-6">
									<label class="form-label">Email</label>
									<input type="email" class="form-control" value="<?php echo htmlspecialchars($admin_email); ?>">
								</div>
								<div class="col-md-6">
									<label class="form-label">Role</label>
									<input type="text" class="form-control" value="Super Admin" disabled>
								</div>
								<div class="col-md-6">
									<label class="form-label">Default Dashboard</label>
									<select class="form-select">
										<option selected>Overview</option>
										<option>Users</option>
										<option>Properties</option>
										<option>Financials</option>
									</select>
								</div>
							</div>
						</div>

						<div class="settings-card">
							<h6 class="mb-3">Password Update</h6>
							<div class="row g-3">
								<div class="col-md-4">
									<label class="form-label">Current Password</label>
									<input type="password" class="form-control" placeholder="********">
								</div>
								<div class="col-md-4">
									<label class="form-label">New Password</label>
									<input type="password" class="form-control" placeholder="At least 10 characters">
								</div>
								<div class="col-md-4">
									<label class="form-label">Confirm Password</label>
									<input type="password" class="form-control" placeholder="Repeat new password">
								</div>
							</div>
							<div class="mt-3">
								<button class="btn btn-outline-primary">Update password</button>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="tab-security" role="tabpanel">
						<div class="settings-card mb-4">
							<h6 class="mb-2">Security Controls</h6>
							<p class="text-muted">Control access policies and protective guardrails.</p>
							<div class="row g-3">
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="twofaToggle" checked>
										<label class="form-check-label" for="twofaToggle">Require 2FA for admins</label>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="ipAllowToggle">
										<label class="form-check-label" for="ipAllowToggle">Enable IP allowlist</label>
									</div>
								</div>
								<div class="col-md-6">
									<label class="form-label">Session Timeout</label>
									<select class="form-select">
										<option>15 minutes</option>
										<option selected>30 minutes</option>
										<option>60 minutes</option>
										<option>4 hours</option>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label">Suspicious Login Alerts</label>
									<select class="form-select">
										<option>Off</option>
										<option selected>Email only</option>
										<option>Email + SMS</option>
									</select>
								</div>
								<div class="col-12">
									<label class="form-label">IP Allowlist</label>
									<textarea class="form-control" rows="3" placeholder="192.168.1.10, 41.60.0.0/16"></textarea>
									<div class="form-text">Separate IPs with commas. Supports CIDR notation.</div>
								</div>
							</div>
						</div>

						<div class="settings-card">
							<h6 class="mb-3">Access Logs</h6>
							<div class="row g-3">
								<div class="col-md-4">
									<div class="action-tile">
										<div class="fw-semibold">Last login</div>
										<div class="text-muted">Feb 10, 2026 - 09:14</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="action-tile">
										<div class="fw-semibold">Active sessions</div>
										<div class="text-muted">2 devices</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="action-tile">
										<div class="fw-semibold">Export audit log</div>
										<button class="btn btn-outline-secondary btn-sm mt-2">Download CSV</button>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="tab-notifications" role="tabpanel">
						<div class="settings-card mb-4">
							<h6 class="mb-3">Admin Notifications</h6>
							<div class="row g-3">
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="notifEmail" checked>
										<label class="form-check-label" for="notifEmail">Email notifications</label>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="notifSms">
										<label class="form-check-label" for="notifSms">SMS notifications</label>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="notifInApp" checked>
										<label class="form-check-label" for="notifInApp">In-app alerts</label>
									</div>
								</div>
								<div class="col-md-6">
									<label class="form-label">Digest Schedule</label>
									<select class="form-select">
										<option>Real-time</option>
										<option selected>Daily</option>
										<option>Weekly</option>
									</select>
								</div>
							</div>
						</div>

						<div class="settings-card">
							<h6 class="mb-3">Escalations</h6>
							<div class="row g-3">
								<div class="col-md-4">
									<label class="form-label">High Priority Threshold</label>
									<input type="number" class="form-control" value="4">
								</div>
								<div class="col-md-4">
									<label class="form-label">Escalate after (hours)</label>
									<input type="number" class="form-control" value="6">
								</div>
								<div class="col-md-4">
									<label class="form-label">On-call channel</label>
									<select class="form-select">
										<option selected>Email + SMS</option>
										<option>Email only</option>
										<option>Slack webhook</option>
									</select>
								</div>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="tab-platform" role="tabpanel">
						<div class="settings-card mb-4">
							<h6 class="mb-3">Platform Rules</h6>
							<div class="row g-3">
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="maintenanceToggle">
										<label class="form-check-label" for="maintenanceToggle">Maintenance mode</label>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="approvalToggle" checked>
										<label class="form-check-label" for="approvalToggle">Require listing approval</label>
									</div>
								</div>
								<div class="col-md-4">
									<label class="form-label">Commission Rate (%)</label>
									<input type="number" class="form-control" value="10">
								</div>
								<div class="col-md-4">
									<label class="form-label">Default Currency</label>
									<select class="form-select">
										<option selected>KES</option>
										<option>USD</option>
										<option>EUR</option>
									</select>
								</div>
								<div class="col-md-4">
									<label class="form-label">Timezone</label>
									<select class="form-select">
										<option selected>Africa/Nairobi</option>
										<option>UTC</option>
										<option>Europe/London</option>
									</select>
								</div>
							</div>
						</div>

						<div class="settings-card">
							<h6 class="mb-3">Moderation & Trust</h6>
							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label">Auto-flag reviews below</label>
									<select class="form-select">
										<option>2 stars</option>
										<option selected>3 stars</option>
										<option>4 stars</option>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label">Max reports before lock</label>
									<input type="number" class="form-control" value="5">
								</div>
								<div class="col-12">
									<label class="form-label">Blocked keywords</label>
									<input type="text" class="form-control" placeholder="spam, scam, fake">
								</div>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="tab-support" role="tabpanel">
						<div class="settings-card mb-4">
							<h6 class="mb-3">Support Center</h6>
							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label">SLA response time</label>
									<select class="form-select">
										<option>2 hours</option>
										<option selected>4 hours</option>
										<option>8 hours</option>
										<option>24 hours</option>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label">Support hours</label>
									<select class="form-select">
										<option selected>08:00 - 20:00 (Mon-Sat)</option>
										<option>24/7</option>
										<option>08:00 - 17:00 (Weekdays)</option>
									</select>
								</div>
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="autoAssignToggle" checked>
										<label class="form-check-label" for="autoAssignToggle">Auto-assign new tickets</label>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-check form-switch">
										<input class="form-check-input" type="checkbox" id="aiReplyToggle">
										<label class="form-check-label" for="aiReplyToggle">Suggest AI replies</label>
									</div>
								</div>
								<div class="col-12">
									<label class="form-label">Escalation rules</label>
									<textarea class="form-control" rows="3" placeholder="If unresolved after 12 hours, escalate to Admin Lead."></textarea>
								</div>
							</div>
						</div>

						<div class="settings-card">
							<h6 class="mb-3">Canned Responses</h6>
							<div class="row g-3">
								<div class="col-md-6">
									<input type="text" class="form-control" placeholder="Subject: Booking refund delay">
								</div>
								<div class="col-md-6">
									<input type="text" class="form-control" placeholder="Tag: refunds, booking">
								</div>
								<div class="col-12">
									<textarea class="form-control" rows="3" placeholder="Write the reply template here..."></textarea>
								</div>
								<div class="col-12">
									<button class="btn btn-outline-primary">Add response</button>
								</div>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="tab-data" role="tabpanel">
						<div class="settings-card mb-4">
							<h6 class="mb-3">Backups</h6>
							<div class="row g-3">
								<div class="col-md-4">
									<label class="form-label">Backup frequency</label>
									<select class="form-select">
										<option>Hourly</option>
										<option selected>Daily</option>
										<option>Weekly</option>
									</select>
								</div>
								<div class="col-md-4">
									<label class="form-label">Retention period</label>
									<select class="form-select">
										<option>7 days</option>
										<option selected>30 days</option>
										<option>90 days</option>
									</select>
								</div>
								<div class="col-md-4">
									<label class="form-label">Storage region</label>
									<select class="form-select">
										<option selected>East Africa</option>
										<option>Europe</option>
										<option>US East</option>
									</select>
								</div>
								<div class="col-12">
									<button class="btn btn-outline-secondary">Run manual backup</button>
								</div>
							</div>
						</div>

						<div class="settings-card">
							<h6 class="mb-3">Exports</h6>
							<div class="row g-3">
								<div class="col-md-4">
									<button class="btn btn-outline-dark w-100"><i class="bi bi-download me-2"></i>Export users</button>
								</div>
								<div class="col-md-4">
									<button class="btn btn-outline-dark w-100"><i class="bi bi-download me-2"></i>Export bookings</button>
								</div>
								<div class="col-md-4">
									<button class="btn btn-outline-dark w-100"><i class="bi bi-download me-2"></i>Export properties</button>
								</div>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="tab-integrations" role="tabpanel">
						<div class="settings-card mb-4">
							<h6 class="mb-3">External Services</h6>
							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label">Email provider</label>
									<select class="form-select">
										<option selected>SMTP</option>
										<option>SendGrid</option>
										<option>Mailgun</option>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label">Payment gateway</label>
									<select class="form-select">
										<option selected>Paystack</option>
										<option>Flutterwave</option>
										<option>Stripe</option>
									</select>
								</div>
								<div class="col-12">
									<label class="form-label">Google Maps API Key</label>
									<input type="text" class="form-control" placeholder="AIza...">
								</div>
								<div class="col-12">
									<button class="btn btn-outline-primary">Test connections</button>
								</div>
							</div>
						</div>

						<div class="settings-card">
							<h6 class="mb-3">System Tools</h6>
							<div class="row g-3">
								<div class="col-md-4">
									<div class="action-tile">
										<div class="fw-semibold">Clear cache</div>
										<button class="btn btn-outline-secondary btn-sm mt-2">Run</button>
									</div>
								</div>
								<div class="col-md-4">
									<div class="action-tile">
										<div class="fw-semibold">Rebuild search index</div>
										<button class="btn btn-outline-secondary btn-sm mt-2">Run</button>
									</div>
								</div>
								<div class="col-md-4">
									<div class="action-tile">
										<div class="fw-semibold">Sync analytics</div>
										<button class="btn btn-outline-secondary btn-sm mt-2">Run</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="sticky-save mt-4 d-flex align-items-center justify-content-between">
					<div>
						<strong>Unsaved changes</strong>
						<div class="text-muted small">Review updates before saving.</div>
					</div>
					<div class="d-flex gap-2">
						<button class="btn btn-outline-secondary">Discard</button>
						<button class="btn btn-dark"><i class="bi bi-save2 me-2"></i>Save settings</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
