<?php
session_start();
include("../../config/db.php");

$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'student') {
	header("Location: ../login.html");
	exit();
}

$student_id = intval($_SESSION['user_id'] ?? 0);
$errorMsg = null;

// Check if saved_properties table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'saved_properties'");
$hasSavedTable = ($checkTable && $checkTable->num_rows > 0);

// Check if property_images table exists
$checkImagesTable = $conn->query("SHOW TABLES LIKE 'property_images'");
$hasImagesTable = ($checkImagesTable && $checkImagesTable->num_rows > 0);

// Check if uploaded_at column exists in property_images
$hasUploadedAt = false;
if ($hasImagesTable) {
    $checkColumn = $conn->query("SHOW COLUMNS FROM property_images LIKE 'uploaded_at'");
    $hasUploadedAt = ($checkColumn && $checkColumn->num_rows > 0);
}

// Build image subquery - only if table exists
if ($hasImagesTable) {
    $imageOrderBy = $hasUploadedAt ? "pi.uploaded_at DESC, pi.id DESC" : "pi.id DESC";
    $imageSubquery = "(SELECT pi.image_path 
           FROM property_images pi 
          WHERE pi.property_id = p.id 
          ORDER BY $imageOrderBy
          LIMIT 1) AS image_path";
} else {
    $imageSubquery = "NULL AS image_path";
}

// Fetch saved properties from database
$properties = [];
if ($hasSavedTable && $student_id > 0) {
	$sql = "
		SELECT
			p.id, p.title, p.city, p.address, p.rent, p.type, p.bedrooms, p.area, p.description,
			$imageSubquery
		FROM properties p
		INNER JOIN saved_properties sp ON p.id = sp.property_id
		WHERE sp.student_id = $student_id
		ORDER BY sp.created_at DESC, p.id DESC
	";
	$res = $conn->query($sql);
	if ($res === false) {
		error_log("saved_properties SQL error: " . $conn->error);
		error_log("saved_properties SQL: " . $sql);
		$errorMsg = "Database error: " . htmlspecialchars($conn->error);
	} else {
		$properties = $res->fetch_all(MYSQLI_ASSOC);
	}
} elseif (!$hasSavedTable) {
	$errorMsg = "Saved properties feature is not available. Please contact administrator.";
}
$count = count($properties);

$uploadsFsDir = realpath(__DIR__ . "/../../uploads");
$uploadsBaseUrl = "/e_rentalHub/uploads/";
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Saved Properties</title>
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
		.property-card { border-radius: 15px; overflow: hidden; transition: transform .2s; }
		.property-card:hover { transform: translateY(-5px); }
		.property-card img { object-fit: cover; height: 220px; width: 100%; }
		.prop-badge{ position:absolute; top:12px; left:12px; z-index:2; padding:.35rem .6rem; border-radius:12px; background:rgba(255,255,255,.95); color:#111; border:1px solid rgba(0,0,0,.06); backdrop-filter: blur(2px); }
		.fav-btn{ position:absolute; top:12px; right:12px; z-index:2; width:36px; height:36px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.95); border:1px solid rgba(0,0,0,.06); border-radius:50%; box-shadow:0 2px 6px rgba(0,0,0,.08); }
	</style>
	</head>
<body>
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h4 class="mb-0">Saved Properties</h4>
			<small class="text-muted"><?= $count ?> saved</small>
		</div>

		<?php if ($errorMsg): ?>
			<div class="alert alert-danger">
				<strong>Error:</strong> <?= $errorMsg ?>
			</div>
		<?php endif; ?>

		<div class="row">
			<?php if ($count === 0 && !$errorMsg): ?>
				<div class="col-12">
					<div class="alert alert-info">You haven't saved any properties yet.</div>
				</div>
			<?php else: ?>
				<?php foreach ($properties as $p):
					// Resolve image
					$img = null;
					if (!empty($p['image_path']) && $uploadsFsDir) {
						$fsCandidate = $uploadsFsDir . DIRECTORY_SEPARATOR . $p['image_path'];
						if (file_exists($fsCandidate)) {
							$img = $uploadsBaseUrl . $p['image_path'];
						}
					}
					if ($img === null && $uploadsFsDir) {
						$fallback1Name = "pexels-vince-2227832.jpg";
						$fallback2Name = "img.avif";
						if (file_exists($uploadsFsDir . DIRECTORY_SEPARATOR . $fallback1Name)) {
							$img = $uploadsBaseUrl . $fallback1Name;
						} elseif (file_exists($uploadsFsDir . DIRECTORY_SEPARATOR . $fallback2Name)) {
							$img = $uploadsBaseUrl . $fallback2Name;
						}
					}
					if ($img === null) {
						$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500"><rect width="100%" height="100%" fill="#e9ecef"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#6c757d" font-family="Arial, Helvetica, sans-serif" font-size="28">No image available</text></svg>';
						$img = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
					}
					$title = htmlspecialchars($p['title']);
					$loc = htmlspecialchars($p['city'].", ".$p['address']);
					$beds = (int)($p['bedrooms'] ?? 0);
					$baths = max(1, floor(max(1,$beds)/2));
					$area = htmlspecialchars($p['area'] ?? '—');
					$type = htmlspecialchars($p['type'] ?? 'Apartment');
					$price = number_format((float)$p['rent']);
				?>
				<div class="col-md-6 col-lg-4 mb-4">
					<div class="card property-card">
						<div class="position-relative">
							<img src="<?= $img ?>" alt="<?= $title ?>">
							<span class="badge bg-light text-dark prop-badge"><?= $type ?></span> 
							<button type="button" class="btn btn-light fav-btn shadow-sm" data-prop-id="<?= (int)$p['id'] ?>" aria-label="Unsave property">
								<i class="bi bi-heart-fill text-danger"></i>
							</button>
						</div>
						<div class="card-body">
							<h6 class="fw-bold text-truncate"><?= $title ?></h6>
							<p class="mb-2"><i class="bi bi-geo-alt me-1"></i><?= $loc ?></p>
							<div class="d-flex justify-content-between meta mb-2">
								<small><i class="bi bi-door-open me-1"></i><?= $beds ?> Bed</small>
								<small><i class="bi bi-droplet me-1"></i><?= $baths ?> Bath</small>
								<small><i class="bi bi-rulers me-1"></i><?= $area ?> sqft</small>
							</div>
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<div class="text-primary fw-bold">KES <?= $price ?></div>
									<small class="text-muted">per month</small>
								</div>
								<a href="/e_rentalHub/houses/view.php?id=<?= (int)$p['id'] ?>" class="btn btn-dark btn-sm rounded-pill">View Details</a>
							</div>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<script>
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.fav-btn');
        if (!btn) return;
        const propId = btn.getAttribute('data-prop-id');
        if (!propId) return;

        try {
            const resp = await fetch('toggle_save_property.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=unsave&property_id=' + encodeURIComponent(propId)
            });
            const data = await resp.json();
            if (data.success) {
                // remove card from UI
                const card = btn.closest('.col-md-6, .col-lg-4');
                if (card) card.remove();
            } else {
                alert('Could not update saved state. Please try again.');
            }
        } catch (err) {
            console.error(err);
            alert('Network error. Please try again.');
        }
    });
	</script>
</body>
</html>
