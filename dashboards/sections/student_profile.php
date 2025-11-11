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


// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$fullName = $conn->real_escape_string(trim($_POST['full_name'] ?? ''));
	$studentIdentifier = $conn->real_escape_string(trim($_POST['student_identifier'] ?? ''));
	$email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
	$phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
	$bio = $conn->real_escape_string(trim($_POST['bio'] ?? ''));
	$university = $conn->real_escape_string(trim($_POST['university'] ?? ''));
	$course = $conn->real_escape_string(trim($_POST['course'] ?? ''));
	$year = $conn->real_escape_string(trim($_POST['year_of_study'] ?? ''));
	$address = $conn->real_escape_string(trim($_POST['current_address'] ?? ''));
	$emName = $conn->real_escape_string(trim($_POST['emergency_name'] ?? ''));
	$emPhone = $conn->real_escape_string(trim($_POST['emergency_phone'] ?? ''));

	// Handle avatar upload (optional)
	$avatarFilename = null;
	if (!empty($_FILES['avatar']['name'])) {
		$uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
		if (!is_dir($uploadDir)) {
			mkdir($uploadDir, 0777, true);
		}
		$allowedExtensions = ['jpg','jpeg','png','gif'];
		$allowedMime = ['image/jpeg','image/png','image/gif'];
		$orig = $_FILES['avatar']['name'];
		$tmp = $_FILES['avatar']['tmp_name'];
		$size = (int)$_FILES['avatar']['size'];
		$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
		$ok = in_array($ext, $allowedExtensions, true);
		$detected = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) : $_FILES['avatar']['type'];
		if ($ok && in_array($detected, $allowedMime, true) && $size <= 5 * 1024 * 1024) {
			$avatarFilename = "avatar_" . $studentUserId . "_" . time() . "." . $ext;
			$target = $uploadDir . $avatarFilename;
			if (!move_uploaded_file($tmp, $target)) {
				$avatarFilename = null;
			}
		}
	}

	// Upsert
	$exists = $conn->query("SELECT id FROM student_profiles WHERE user_id={$studentUserId} LIMIT 1");
	if ($exists && $exists->num_rows > 0) {
		$setAvatar = $avatarFilename ? ", avatar='{$conn->real_escape_string($avatarFilename)}'" : "";
		$conn->query("
			UPDATE student_profiles SET
				full_name='{$fullName}',
				student_identifier='{$studentIdentifier}',
				email='{$email}',
				phone='{$phone}',
				bio='{$bio}',
				university='{$university}',
				course='{$course}',
				year_of_study='{$year}',
				current_address='{$address}',
				emergency_name='{$emName}',
				emergency_phone='{$emPhone}'
				{$setAvatar}
			WHERE user_id={$studentUserId}
			LIMIT 1
		");
	} else {
		$conn->query("
			INSERT INTO student_profiles
				(user_id, full_name, student_identifier, email, phone, bio, university, course, year_of_study, current_address, emergency_name, emergency_phone, avatar)
			VALUES
				({$studentUserId}, '{$fullName}', '{$studentIdentifier}', '{$email}', '{$phone}', '{$bio}', '{$university}', '{$course}', '{$year}', '{$address}', '{$emName}', '{$emPhone}', " . ($avatarFilename ? "'{$conn->real_escape_string($avatarFilename)}'" : "NULL") . ")
		");
	}
	header("Location: student_profile.php?updated=1");
	exit();
}

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
$res = $conn->query("SELECT * FROM student_profiles WHERE user_id={$studentUserId} LIMIT 1");
if ($res && $res->num_rows > 0) {
	$profile = $res->fetch_assoc();
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
		.kv{color:#6c757d}
		.card-section{border-radius:16px;border:1px solid rgba(0,0,0,0.06)}
		.section-title{font-weight:700}
	</style>
	</head>
<body>
	<div class="container py-4">
		<?php if (isset($_GET['updated'])): ?>
		<div class="alert alert-success">Profile updated successfully.</div>
		<?php endif; ?>

		<div class="profile-header mb-4">
			<div class="position-relative">
				<?php if ($avatarUrl): ?>
					<img src="<?= htmlspecialchars($avatarUrl) ?>" class="avatar" alt="Avatar">
				<?php else: ?>
					<div class="avatar"><?= strtoupper(substr($profile['full_name'] ?: 'KK', 0, 2)) ?></div>
				<?php endif; ?>
			</div>
			<div class="flex-grow-1">
				<h4 class="mb-1"><?= htmlspecialchars($profile['full_name'] ?: 'Kevin Kipchoge') ?></h4>
				<div class="text-muted">USIU · <?= htmlspecialchars($profile['course'] ?: 'Business Administration') ?></div>
				<small class="text-muted">Student ID: <?= htmlspecialchars($profile['student_identifier'] ?: 'STU2024001') ?></small>
			</div>
			<button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="bi bi-pencil me-1"></i>Edit</button>
		</div>

		<div class="row g-3">
			<div class="col-12">
				<div class="card card-section">
					<div class="card-body">
						<h6 class="section-title mb-3">Personal Information</h6>
						<div class="row g-3">
							<div class="col-md-6">
								<div class="kv">Full Name</div>
								<div><?= htmlspecialchars($profile['full_name'] ?: 'Kevin Kipchoge') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Student ID</div>
								<div><?= htmlspecialchars($profile['student_identifier'] ?: 'STU2024001') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Email Address</div>
								<div><?= htmlspecialchars($profile['email'] ?: 'kevin.k@student.ac.ke') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Phone Number</div>
								<div><?= htmlspecialchars($profile['phone'] ?: '+254 756 789 012') ?></div>
							</div>
							<div class="col-12">
								<div class="kv">Bio</div>
								<div><?= htmlspecialchars($profile['bio'] ?: 'Third year business student looking for affordable accommodation near campus.') ?></div>
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
								<div class="kv">University</div>
								<div><?= htmlspecialchars($profile['university'] ?: 'USIU') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Course/Program</div>
								<div><?= htmlspecialchars($profile['course'] ?: 'Business Administration') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Year of Study</div>
								<div><?= htmlspecialchars($profile['year_of_study'] ?: 'Third Year') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Current Address</div>
								<div><?= htmlspecialchars($profile['current_address'] ?: 'Main Campus Hostel, USIU') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Emergency Contact</div>
								<div><?= htmlspecialchars($profile['emergency_name'] ?: 'Jane Kipchoge') ?></div>
							</div>
							<div class="col-md-6">
								<div class="kv">Contact Phone</div>
								<div><?= htmlspecialchars($profile['emergency_phone'] ?: '+254 722 123 456') ?></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Edit Modal -->
	<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Update Profile</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form method="post" enctype="multipart/form-data">
					<div class="modal-body">
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
								<label class="form-label">Email</label>
								<input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Phone</label>
								<input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone']) ?>">
							</div>
							<div class="col-12">
								<label class="form-label">Bio</label>
								<textarea name="bio" class="form-control" rows="2"><?= htmlspecialchars($profile['bio']) ?></textarea>
							</div>
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
								<label class="form-label">Emergency Contact Name</label>
								<input type="text" name="emergency_name" class="form-control" value="<?= htmlspecialchars($profile['emergency_name']) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Emergency Contact Phone</label>
								<input type="text" name="emergency_phone" class="form-control" value="<?= htmlspecialchars($profile['emergency_phone']) ?>">
							</div>
							<div class="col-12">
								<label class="form-label">Profile Photo</label>
								<input type="file" name="avatar" accept="image/*" class="form-control">
								<small class="text-muted">JPG, PNG or GIF up to 5MB</small>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-dark">Save Changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

