<?php
session_start();
include("../../config/db.php");

// Only students can view/edit this
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'student') {
	header("Location: ../login.html");
	exit();
}
$studentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($studentUserId <= 0) {
	header("Location: ../login.html");
	exit();
}


// Student profile saving is handled centrally by `dashboards/save_profile.php`.
// The form on this page now submits to that endpoint via JavaScript.

// Load profile
$profile = [
	'full_name' => '',
	'student_identifier' => '',
	'email' => '',
	'phone' => '',
	'bio' => '',
	'university' => '',
	'course' => '',
	'year_of_study' => '',
	'current_address' => '',
	'emergency_name' => '',
	'emergency_phone' => '',
	'avatar' => ''
];

// Join users and students table to get the most accurate data, preferring students table data.
$res = $conn->query("
    SELECT u.email AS user_email, s.*
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    WHERE u.user_id = {$studentUserId} LIMIT 1
");
if ($res && $res->num_rows > 0) {
	$profile = array_merge(['email' => ''], $res->fetch_assoc()); // ensure email key exists
}

$uploadsBaseUrl = "/e_rentalHub/uploads/";
$avatarUrl = '';
if (!empty($profile['avatar'])) {
	$avatarUrl = $uploadsBaseUrl . $profile['avatar'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Student Profile</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	<style>
		.profile-header{background:#f8fafc;border-radius:16px;padding:24px 20px;display:flex;gap:16px;align-items:center;}
		.avatar{width:88px;height:88px;border-radius:50%;object-fit:cover;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-weight:700;color:#6c757d;font-size:28px;position:relative}
		.avatar .cam{position:absolute;right:-2px;bottom:-2px;background:#212529;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;cursor:pointer}
		.btn-save{background:#000;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600}
		.btn-save:hover{background:#222;transform:translateY(-1px)}
		.kv{color:#6c757d}
		.card-section{border-radius:16px;border:1px solid rgba(0,0,0,0.06)}
		.section-title{font-weight:700}
	</style>
	</head>
<body>
	<div class="container py-4">
		<div id="studentAlertPlaceholder"></div>
		<!-- Debug area for quick client-side visibility while troubleshooting -->
		<div id="studentDebug" style="font-family:monospace;white-space:pre-wrap;margin-top:8px;color:#333"></div>
		<?php if (isset($_GET['updated'])): ?>
		<div class="alert alert-success">Profile updated successfully.</div>
		<?php endif; ?>

	<form id="studentProfileForm" method="POST" enctype="multipart/form-data">
		<div class="profile-header mb-4">
			<div class="position-relative">
				<?php if ($avatarUrl): ?>
					<img src="<?= htmlspecialchars($avatarUrl) ?>" class="avatar" alt="Avatar">
				<?php else: ?>
					<div class="avatar"><?= strtoupper(substr($profile['full_name'] ?: 'KK', 0, 2)) ?></div>
				<?php endif; ?>
				<label class="cam" onclick="document.getElementById('avatar_input').click()"><i class="bi bi-camera"></i></label>
				<input type="file" id="avatar_input" name="avatar" accept="image/*" class="d-none">
			</div>
			<div class="flex-grow-1">
				<h4 class="mb-1"><input type="text" name="full_name" class="form-control form-control-sm" value="<?= htmlspecialchars($profile['full_name'] ?: '') ?>"></h4>
				<div class="text-muted"><input type="text" name="course" class="form-control form-control-sm" value="<?= htmlspecialchars($profile['course'] ?: '') ?>" placeholder="Course / Program" style="max-width:320px"></div>
				<small class="text-muted">Student ID: <input type="text" name="student_identifier" class="form-control form-control-sm d-inline-block" value="<?= htmlspecialchars($profile['student_identifier'] ?: '') ?>" style="width:160px"></small>
			</div>
				<div class="ms-auto">
					<!-- Save button: use triggerSave to ensure non-blocking handling -->
					<button type="button" id="saveStudentBtn" class="btn btn-save" onclick="triggerSave(event)">Save Changes</button>
				</div>
		</div>

		<div class="row g-3">
			<div class="col-12">
				<div class="card card-section">
					<div class="card-body">
								<h6 class="section-title mb-3">Personal Information</h6>
								<div class="row g-3">
									<div class="col-md-6">
										<label class="form-label">Full Name</label>
										<input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($profile['full_name']) ?>">
									</div>
									<div class="col-md-6">
										<label class="form-label">Student ID</label>
										<input type="text" name="student_identifier" class="form-control" value="<?= htmlspecialchars($profile['student_identifier']) ?>">
									</div>
									<div class="col-md-6">
										<label class="form-label">Email Address</label>
										<input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>">
									</div>
									<div class="col-md-6">
										<label class="form-label">Phone Number</label>
										<input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone']) ?>">
									</div>
									<div class="col-12">
										<label class="form-label">Bio</label>
										<textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($profile['bio']) ?></textarea>
									</div>
								</div>
					</div>
				</div>
			</div>

			<div class="col-12">
				<div class="card card-section">
					<div class="card-body">
						<h6 class="section-title mb-3">Academic Information</h6>
						<div class="row g-3">
							<div class="col-md-6">
								<label class="form-label">University</label>
								<input type="text" name="university" class="form-control" value="<?= htmlspecialchars($profile['university']) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Course/Program</label>
								<input type="text" name="course" class="form-control" value="<?= htmlspecialchars($profile['course']) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Year of Study</label>
								<input type="text" name="year_of_study" class="form-control" value="<?= htmlspecialchars($profile['year_of_study']) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Current Address</label>
								<input type="text" name="current_address" class="form-control" value="<?= htmlspecialchars($profile['current_address']) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Emergency Contact</label>
								<input type="text" name="emergency_name" class="form-control" value="<?= htmlspecialchars($profile['emergency_name']) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Contact Phone</label>
								<input type="text" name="emergency_phone" class="form-control" value="<?= htmlspecialchars($profile['emergency_phone']) ?>">
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

		</form>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
	function showStudentAlert(type, message) {
	    const ph = document.getElementById('studentAlertPlaceholder');
	    ph.innerHTML = `<div class="alert alert-${type} alert-dismissible" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
	}

	function showStudentDebug(msg) {
		try {
			const d = document.getElementById('studentDebug');
			if (d) {
				d.textContent = new Date().toLocaleTimeString() + ' - ' + msg + "\n" + d.textContent;
			}
		} catch (e) {
			console.log('debug:', msg);
		}
	}

	// non-blocking wrapper called by the button; keeps logs and calls submit
	function submitStudentProfileForm(e) {
		// Prevent native form submission
		if (e && typeof e.preventDefault === 'function') e.preventDefault();
		console.log('submitStudentProfileForm entered');
		showStudentDebug('submitStudentProfileForm entered');

		const form = document.getElementById('studentProfileForm');
		if (!form) {
			console.error('studentProfileForm not found');
			showStudentDebug('studentProfileForm not found');
			return;
		}

		const formData = new FormData(form);
		// Add a flag so save_profile.php knows this is coming from the student form
		formData.append('source', 'student_profile');

		const saveBtn = document.getElementById('saveStudentBtn');
		const originalBtnText = saveBtn ? saveBtn.innerHTML : null;
		if (saveBtn) {
			saveBtn.disabled = true;
			saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
		}

		console.log('submitStudentProfileForm: sending fetch to /e_rentalHub/dashboards/save_profile.php', { user_id: <?= json_encode($studentUserId) ?> });
		showStudentDebug('submitStudentProfileForm: sending fetch');

		fetch('/e_rentalHub/dashboards/save_profile.php', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(response => {
			console.log('Response status:', response.status, 'ok:', response.ok);
			if (!response.ok) throw new Error('HTTP error ' + response.status);
			return response.text();
		})
		.then(text => {
			console.log('Raw response text:', text);
			let data = null;
			try {
				data = JSON.parse(text);
			} catch (err) {
				console.error('Failed to parse JSON from save_profile:', err, text.substring(0,300));
				showStudentAlert('danger', 'Unexpected server response. Check console for details.');
				return;
			}

			if (data.success) {
				showStudentAlert('success', data.message || 'Profile saved successfully');
				// keep UX consistent with landlord: reload to reflect updated values
				setTimeout(() => location.reload(), 600);
			} else {
				showStudentAlert('danger', data.message || 'Failed to save profile');
			}
		})
		.catch(err => {
			console.error('Save profile error', err);
			showStudentAlert('danger', 'An error occurred while saving the profile. Check console/logs.');
		})
		.finally(() => {
			if (saveBtn) {
				saveBtn.disabled = false;
				if (originalBtnText !== null) saveBtn.innerHTML = originalBtnText;
			}
		});
	}
			})
			.finally(() => {
				if (saveBtn) {
					saveBtn.disabled = false;
					if (originalBtnText !== null) saveBtn.innerHTML = originalBtnText;
				}
			});
			// call with a dummy event that has preventDefault to avoid native submit
			submitStudentProfileForm({ preventDefault: function(){} });
		});
	}

	// Ensure the form never performs a native submit (pressing Enter etc.)
	const studentForm = document.getElementById('studentProfileForm');
	if (studentForm) {
		studentForm.addEventListener('submit', function(e){
			e.preventDefault();
			submitStudentProfileForm(e);
		});
		// prevent Enter in input fields from submitting (but allow textarea)
		studentForm.addEventListener('keydown', function(e){
			if (e.key === 'Enter' && e.target && e.target.tagName && e.target.tagName.toLowerCase() !== 'textarea') {
				e.preventDefault();
				// optionally submit on Enter: submitStudentProfileForm(e);
			}
		});
	}

	// Attach click listener to Save button (safer than inline onclick)
	try {
		const saveBtn = document.getElementById('saveStudentBtn');
		if (saveBtn) {
			saveBtn.addEventListener('click', function(e){
				console.log('Save button clicked');
				showStudentDebug('Save button clicked');
				submitStudentProfileForm(e);
			});
		}
	} catch (err) {
		console.error('Error attaching save button listener', err);
		showStudentDebug('Error attaching save button listener: ' + err.message);
	}

	// Expose submit function to window for inline onclick fallback
	try { window.submitStudentProfileForm = submitStudentProfileForm; } catch (e) {}
	</script>
</body>
</html>
