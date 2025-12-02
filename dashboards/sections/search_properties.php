<?php
session_start();
include("../../config/db.php");

// Allow public browsing of properties. If user is logged in capture role/student id.
$role = strtolower(trim($_SESSION["role"] ?? ''));
$student_id = intval($_SESSION['user_id'] ?? 0);

// read filters
$q = trim($_GET['q'] ?? '');
$city = trim($_GET['city'] ?? '');

// build query
$where = "WHERE p.status='Available'";
if ($q !== '') {
    $safeQ = "%".$conn->real_escape_string($q)."%" ;
    $where .= " AND (p.title LIKE '$safeQ' OR p.address LIKE '$safeQ' OR p.city LIKE '$safeQ')";
}
if ($city !== '') {
    $safeCity = $conn->real_escape_string($city);
    $where .= " AND p.city = '$safeCity'";
}

// ensure student_id is an integer (avoid accidental SQL issues)
$student_id = intval($_SESSION['user_id'] ?? 0);
$errorMsg = null;

// Check if property_images table exists
$checkImagesTable = $conn->query("SHOW TABLES LIKE 'property_images'");
$hasImagesTable = ($checkImagesTable && $checkImagesTable->num_rows > 0);

// Check if uploaded_at column exists in property_images
$hasUploadedAt = false;
if ($hasImagesTable) {
    $checkColumn = $conn->query("SHOW COLUMNS FROM property_images LIKE 'uploaded_at'");
    $hasUploadedAt = ($checkColumn && $checkColumn->num_rows > 0);
}

// Check if saved_properties table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'saved_properties'");
$hasSavedTable = ($checkTable && $checkTable->num_rows > 0);

// Build image subquery - only if table exists
if ($hasImagesTable) {
    $orderBy = $hasUploadedAt ? "uploaded_at DESC, id DESC" : "id DESC";
    $imageSubquery = "(SELECT GROUP_CONCAT(image_path ORDER BY $orderBy SEPARATOR ',')
           FROM property_images
           WHERE property_id = p.id
           ORDER BY $orderBy
           LIMIT 4) AS image_paths";
} else {
    $imageSubquery = "NULL AS image_paths";
}

// Build saved properties join - only if table exists
$savedJoin = $hasSavedTable ? "LEFT JOIN saved_properties sp ON p.id = sp.property_id AND sp.student_id = $student_id" : "";
$savedSelect = $hasSavedTable ? "CASE WHEN sp.id IS NOT NULL THEN 1 ELSE 0 END as is_saved" : "0 as is_saved";

$sql = "
    SELECT
        p.id, p.title, p.city, p.address, p.rent, p.type, p.bedrooms, p.area, p.description,
        $imageSubquery,
        $savedSelect
    FROM properties p
    $savedJoin
    $where
    ORDER BY p.id DESC
    LIMIT 60
";

// run query and detect errors for debugging
$res = $conn->query($sql);
if ($res === false) {
    // Log SQL and DB error for debugging (visible in php error log)
    error_log("search_properties SQL error: " . $conn->error);
    error_log("search_properties SQL: " . $sql);
    $properties = [];
    $errorMsg = "Database error: " . htmlspecialchars($conn->error);
} else {
    $properties = $res->fetch_all(MYSQLI_ASSOC);
    $errorMsg = null;
    error_log("search_properties: Found " . count($properties) . " properties");
    if (count($properties) > 0) {
        error_log("First property ID: " . $properties[0]['id']);
    }
}
$count = count($properties);

// Resolve filesystem and URL paths for uploads robustly
$uploadsFsDir = realpath(__DIR__ . "/../../uploads"); // filesystem path
// Adjust this base URL if your app root is different (assumes http://localhost/e_rentalHub/)
$uploadsBaseUrl = "/e_rentalHub/uploads/";

// Session-based saved properties (no DB)
if (!isset($_SESSION['saved_property_ids']) || !is_array($_SESSION['saved_property_ids'])) {
	$_SESSION['saved_property_ids'] = [];
}
$savedIds = array_fill_keys(array_map('intval', $_SESSION['saved_property_ids']), true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Properties</title>
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
        .search-hero {
            background: linear-gradient(135deg, #0a1f44, #0f4c81);
            border-radius: 15px;
            padding: 2rem 1rem;
            margin-bottom: 1.5rem;
        }
        .search-hero h2 {
            font-size: 1.75rem; /* Smaller heading */
            margin-bottom: 0.5rem;
        }

        .search-hero p {
            font-size: 0.95rem; /* Slightly smaller paragraph */
            margin-bottom: 1.5rem;
        }
        .search-bar {
            background: white;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: nowrap;
            align-items: center;
            border-radius: 30px;
            
        }

        .search-bar input::placeholder {
            color: #aaa;
            font-size: 0.9rem;
        }

        .property-card {
            transition: transform 0.2s;
            border-radius: 15px;
            overflow: hidden;
        }

        .property-card:hover {
            transform: translateY(-5px);
        }

        .property-card img {
            object-fit: cover;
            height: 220px;
            width: 100%;
        }

        .property-card .badge {
            font-size: 0.75rem;
            border-radius: 10px;
        }

        .property-card .btn-light:hover i {
            color: #e63946;
        }

         /* Ensure badge (type) and favorite icon sit on top of the image */
         .prop-badge{
             position: absolute;
             top: 12px;
             left: 12px;
             z-index: 2;
             padding: .35rem .6rem;
             border-radius: 12px;
             background: rgba(255,255,255,0.95);
             color: #111;
             border: 1px solid rgba(0,0,0,0.06);
             backdrop-filter: blur(2px);
         }
         .fav-btn{
             position: absolute;
             top: 12px;
             right: 12px;
             z-index: 2;
             width: 36px;
             height: 36px;
             display: flex;
             align-items: center;
             justify-content: center;
             background: rgba(255,255,255,0.95);
             border: 1px solid rgba(0,0,0,0.06);
             border-radius: 50%;
             box-shadow: 0 2px 6px rgba(0,0,0,0.08);
         }
    </style>
</head>
<body>

    <div class="page-pad">
    <div class="search-hero text-center text-white rounded-4">
        <h2 class="fw-semibold">Find Your Perfect Student Home</h2>
        <p>Explore hundreds of student-friendly properties near your university</p>

        <form class="container search-form" method="get">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group bg-white rounded-pill px-2">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control border-0" placeholder="Search by name or location">
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="text" name="city" value="<?= htmlspecialchars($city) ?>" class="form-control rounded-pill" placeholder="City (optional)">
                </div>
                <div class="col-md-3 d-grid d-md-block">
                    <button class="btn btn-light text-dark rounded-pill px-4"><i class="bi bi-sliders me-1"></i>Search</button>
                </div>
            </div>
        </form>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Available Properties</h4>
            <small class="text-muted"><?= $count ?> properties found</small>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?= $errorMsg ?>
                <br><small>Please check your database connection and table structure.</small>
            </div>
        <?php endif; ?>

        <div class="row" id="propertiesContainer">
            <?php if ($count === 0 && !$errorMsg): ?>
                <div class="col-12">
                    <div class="alert alert-info">No available properties match your search.</div>
                </div>
            <?php else: ?>
				<?php foreach ($properties as $p):
					// Resolve images: split comma-separated paths and validate each
					$imageUrls = [];
					if (!empty($p['image_paths'])) {
						$imagePaths = explode(',', $p['image_paths']);
						foreach ($imagePaths as $path) {
							$path = trim($path);
							if (!empty($path) && $uploadsFsDir) {
								$fsCandidate = $uploadsFsDir . DIRECTORY_SEPARATOR . $path;
								if (file_exists($fsCandidate)) {
									$imageUrls[] = $uploadsBaseUrl . $path;
								}
							}
						}
					}

					// If no valid images, use fallbacks
					if (empty($imageUrls) && $uploadsFsDir) {
						$fallback1Name = "pexels-vince-2227832.jpg";
						$fallback2Name = "img.avif";
						if (file_exists($uploadsFsDir . DIRECTORY_SEPARATOR . $fallback1Name)) {
							$imageUrls[] = $uploadsBaseUrl . $fallback1Name;
						} elseif (file_exists($uploadsFsDir . DIRECTORY_SEPARATOR . $fallback2Name)) {
							$imageUrls[] = $uploadsBaseUrl . $fallback2Name;
						}
					}

					// If still no images, use SVG placeholder
					if (empty($imageUrls)) {
						$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500"><rect width="100%" height="100%" fill="#e9ecef"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#6c757d" font-family="Arial, Helvetica, sans-serif" font-size="28">No image available</text></svg>';
						$imageUrls[] = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
					}

					$title = htmlspecialchars($p['title']);
					$loc = htmlspecialchars($p['city'].", ".$p['address']);
					$beds = (int)($p['bedrooms'] ?? 0);
					$baths = max(1, floor(max(1,$beds)/2));
					$area = htmlspecialchars($p['area'] ?? '...');
					$type = htmlspecialchars($p['type'] ?? 'Apartment');
					$price = number_format((float)$p['rent']);
					$isSaved = $p['is_saved'] == 1;
				?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card property-card">
                        <div class="position-relative">
                            <?php if (count($imageUrls) > 1): ?>
                                <div id="carousel-<?= (int)$p['id'] ?>" class="carousel slide">
                                    <div class="carousel-inner">
                                        <?php foreach ($imageUrls as $index => $imgUrl): ?>
                                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                                <img src="<?= htmlspecialchars($imgUrl) ?>" class="d-block w-100" alt="<?= $title ?>" style="height: 220px; object-fit: cover;" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22800%22 height=%22500%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23e9ecef%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%236c757d%22 font-family=%22Arial,Helvetica,sans-serif%22 font-size=%2228%22%3ENo image available%3C/text%3E%3C/svg%3E'">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="carousel-indicators">
                                        <?php for ($i = 0; $i < count($imageUrls); $i++): ?>
                                            <button type="button" data-bs-target="#carousel-<?= (int)$p['id'] ?>" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-current="true" aria-label="Slide <?= $i + 1 ?>"></button>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($imageUrls[0]) ?>" alt="<?= $title ?>" style="height: 220px; object-fit: cover;" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22800%22 height=%22500%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23e9ecef%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%236c757d%22 font-family=%22Arial,Helvetica,sans-serif%22 font-size=%2228%22%3ENo image available%3C/text%3E%3C/svg%3E'">
                            <?php endif; ?>
							<span class="badge bg-light text-dark prop-badge"><?= $type ?></span>
							<button type="button" class="btn btn-light fav-btn shadow-sm save-property"
                                    data-property-id="<?= (int)$p['id'] ?>"
                                    data-saved="<?= $isSaved ? '1' : '0' ?>"
                                    aria-label="Save property">
								<i class="bi <?= $isSaved ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
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
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle carousel hover for property cards
    document.querySelectorAll('.property-card').forEach(card => {
        const carousel = card.querySelector('.carousel');
        if (carousel) {
            const carouselInstance = new bootstrap.Carousel(carousel, {
                interval: false
            });
            card.addEventListener('mouseenter', () => {
                carouselInstance.next();
            });
            card.addEventListener('mouseleave', () => {
                carouselInstance.to(0);
            });
        }
    });
});
// We use a delegated handler attached to the dashboard root (student_dashboard.php) to handle fav buttons.
// This file no longer binds its own DOMContentLoaded handlers so AJAX-injected content uses the central handler.
</script>

<!-- Login prompt modal shown when a guest tries to save a property -->
<div class="modal fade" id="loginPromptModal" tabindex="-1" aria-labelledby="loginPromptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginPromptModalLabel">Please sign in</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                You need to be logged in as a student to save properties. Would you like to log in now?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="/e_rentalHub/login.html" class="btn btn-primary">Log in</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>