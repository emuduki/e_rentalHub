<?php
// If asked for recent properties, output a small HTML fragment with latest 3 properties
if (isset($_GET['recent']) && $_GET['recent'] === '1') {
	include("./config/db.php");
	header('Content-Type: text/html; charset=utf-8');

	$sql = "
		SELECT
			p.id,
			p.title,
			CONCAT_WS(', ', p.city, p.address) AS location,
			p.rent,
			(SELECT pi.image_path
			   FROM property_images pi
			  WHERE pi.property_id = p.id
			  ORDER BY pi.uploaded_at DESC, pi.id DESC
			  LIMIT 1) AS image_path
		FROM properties p
		WHERE p.status = 'Available'
		ORDER BY p.id DESC
		LIMIT 6
	";
	$res = $conn->query($sql);
	$properties = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

	$uploadsFsDir = realpath(__DIR__ . "/uploads");
	$uploadsBaseUrl = "/e_rentalHub/uploads/";

	if (empty($properties)) {
		echo '<div class="col-12"><div class="alert alert-info mb-0">No recent properties to display.</div></div>';
		exit;
	}

	foreach ($properties as $p) {
		// Resolve image path
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

		$id = (int)$p['id'];
		$title = htmlspecialchars($p['title'] ?? 'Property');
		$location = htmlspecialchars($p['location'] ?? '');
		$price = number_format((float)($p['rent'] ?? 0));

		echo '
		<div class="col-md-4">
			<div class="card shadow-sm border-0 rounded-4 h-100">
				<img src="'.$img.'" class="card-img-top" style="height: 220px; object-fit: cover; border-radius: 0.75rem 0.75rem 0 0;" alt="'.$title.'">
				<div class="card-body text-start d-flex flex-column">
					<h5 class="card-title fw-bold">'.$title.'</h5>
					<p class="text-muted mb-1">
						<i class="bi bi-geo-alt-fill text-primary"></i> '.$location.'
					</p>
					<p class="fw-bold text-primary mb-3">
						KES '.$price.' / month
					</p>
					<a href="dashboards/houses/view.php?id='.$id.'" class="btn btn-dark w-100 rounded-3 mt-auto">View Property</a>
				</div>
			</div>
		</div>';
	}
	exit;
}

// Otherwise, redirect to static homepage
header("Location: index.html");
exit;
?>