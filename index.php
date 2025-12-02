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
			GROUP_CONCAT(DISTINCT pi.image_path ORDER BY pi.uploaded_at DESC, pi.id DESC LIMIT 4) AS image_paths
		FROM properties p
		LEFT JOIN property_images pi ON p.id = pi.property_id
		WHERE p.status = 'Available'
		GROUP BY p.id
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
		// Handle multiple images
		$imagePaths = !empty($p['image_paths']) ? explode(',', $p['image_paths']) : [];
		$images = [];

		foreach ($imagePaths as $imagePath) {
			$img = null;
			if (!empty($imagePath) && $uploadsFsDir) {
				$fsCandidate = $uploadsFsDir . DIRECTORY_SEPARATOR . $imagePath;
				if (file_exists($fsCandidate)) {
					$img = $uploadsBaseUrl . $imagePath;
				}
			}
			if ($img !== null) {
				$images[] = $img;
			}
		}

		// If no images found, use fallback
		if (empty($images)) {
			$fallback1Name = "pexels-vince-2227832.jpg";
			$fallback2Name = "img.avif";
			if ($uploadsFsDir && file_exists($uploadsFsDir . DIRECTORY_SEPARATOR . $fallback1Name)) {
				$images[] = $uploadsBaseUrl . $fallback1Name;
			} elseif ($uploadsFsDir && file_exists($uploadsFsDir . DIRECTORY_SEPARATOR . $fallback2Name)) {
				$images[] = $uploadsBaseUrl . $fallback2Name;
			} else {
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500"><rect width="100%" height="100%" fill="#e9ecef"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#6c757d" font-family="Arial, Helvetica, sans-serif" font-size="28">No image available</text></svg>';
				$images[] = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
			}
		}

		$id = (int)$p['id'];
		$title = htmlspecialchars($p['title'] ?? 'Property');
		$location = htmlspecialchars($p['location'] ?? '');
		$price = number_format((float)($p['rent'] ?? 0));

		// Generate image carousel HTML for up to 4 images, like in search_properties.php
		$imageHtml = '';
		$imageCount = count($images);

		if ($imageCount === 1) {
			$imageHtml = '<img src="' . $images[0] . '" class="card-img-top" style="height: 220px; object-fit: cover; border-radius: 0.75rem 0.75rem 0 0;" alt="' . $title . '">';
		} else {
			$carouselId = 'carousel-' . $id;
			$imageHtml = '<div id="' . $carouselId . '" class="carousel slide" data-bs-ride="carousel" style="height: 220px; border-radius: 0.75rem 0.75rem 0 0; overflow: hidden;">';
			$imageHtml .= '<div class="carousel-inner h-100">';

			foreach ($images as $index => $imgSrc) {
				$activeClass = $index === 0 ? ' active' : '';
				$imageHtml .= '<div class="carousel-item h-100' . $activeClass . '">';
				$imageHtml .= '<img src="' . $imgSrc . '" class="d-block w-100 h-100" style="object-fit: cover;" alt="' . $title . '" onerror="this.onerror=null;this.src=\'data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22800%22 height=%22500%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23e9ecef%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%236c757d%22 font-family=%22Arial,Helvetica,sans-serif%22 font-size=%2228%22%3ENo image available%3C/text%3E%3C/svg%3E\'">';
				$imageHtml .= '</div>';
			}

			$imageHtml .= '</div>';
			$imageHtml .= '<div class="carousel-indicators">';
			for ($i = 0; $i < $imageCount; $i++) {
				$activeClass = $i === 0 ? ' active' : '';
				$imageHtml .= '<button type="button" data-bs-target="#' . $carouselId . '" data-bs-slide-to="' . $i . '" class="' . $activeClass . '" aria-current="true" aria-label="Slide ' . ($i + 1) . '"></button>';
			}
			$imageHtml .= '</div>';
			$imageHtml .= '</div>';
		}

		echo '
		<div class="col-md-4">
			<div class="card shadow-sm border-0 rounded-4 h-100">
				' . $imageHtml . '
				<div class="card-body text-start d-flex flex-column">
					<h5 class="card-title fw-bold">' . $title . '</h5>
					<p class="text-muted mb-1">
						<i class="bi bi-geo-alt-fill text-primary"></i> ' . $location . '
					</p>
					<p class="fw-bold text-primary mb-3">
						KES ' . $price . ' / month
					</p>
					<a href="houses/view.php?id=' . $id . '" class="btn btn-dark w-100 rounded-3 mt-auto">View Property</a>
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