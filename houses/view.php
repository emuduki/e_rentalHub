<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../config/db.php");
include("../config/api_keys.php");

$property_id = intval($_GET['id'] ?? 0);
if ($property_id <= 0) {
    die("Invalid property ID. <a href='../index.html'>Go back</a>");
}

// Fetch property details
$userPk = null;
$candidates = ['id','user_id','userid','uid','email'];
foreach ($candidates as $c) {
    $colRes = $conn->query("SHOW COLUMNS FROM users LIKE '" . $conn->real_escape_string($c) . "'");
    if ($colRes && $colRes->num_rows > 0) {
        $userPk = $c;
        break;
    }
}

if ($userPk) {
    // safe column name
    $userPkEsc = $userPk;
    $sql = "SELECT p.*, u.username as landlord_name 
        FROM properties p 
        LEFT JOIN users u ON p.landlord_id = u.`$userPkEsc` 
        WHERE p.id = $property_id";
} else {
    // users table exists but no common PK found; do not join to avoid SQL errors
    $sql = "SELECT p.*, NULL as landlord_name FROM properties p WHERE p.id = $property_id";
}

$result = $conn->query($sql);
if (!$result) {
    error_log("view.php SQL error: " . $conn->error);
    error_log("view.php SQL: " . $sql);
    die("Database error: " . $conn->error);
}
if ($result->num_rows === 0) {
    error_log("view.php: Property ID $property_id not found in database");
    die("Property not found. <a href='../index.html'>Go back</a>");
}
$property = $result->fetch_assoc();

// Fetch all images for this property
$images_query = $conn->query("SELECT image_path FROM property_images WHERE property_id = $property_id ORDER BY id ASC");
$images = [];
if ($images_query) {
    while ($row = $images_query->fetch_assoc()) {
        $images[] = $row['image_path'];
    }
}

// Parse amenities
$amenities_list = [];
if (!empty($property['amenities'])) {
    $amenities_list = array_map('trim', explode(',', $property['amenities']));
}

$uploadsFsDir = realpath(__DIR__ . "/../uploads");
$uploadsBaseUrl = "/e_rentalHub/uploads/";

// Prepare image URLs
$imageUrls = [];
foreach ($images as $img) {
    if ($uploadsFsDir && file_exists($uploadsFsDir . DIRECTORY_SEPARATOR . $img)) {
        $imageUrls[] = $uploadsBaseUrl . $img;
    }
}

// If no images, use fallback
if (empty($imageUrls)) {
    $fallback = $uploadsFsDir . DIRECTORY_SEPARATOR . "pexels-vince-2227832.jpg";
    if (file_exists($fallback)) {
        $imageUrls[] = $uploadsBaseUrl . "pexels-vince-2227832.jpg";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($property['title']) ?> - Property Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Leaflet CSS (fallback when Google Maps is unavailable) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding-top: 20px;
        }
        .property-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .image-gallery {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
        }

        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .side-images {
            display: grid;
            grid-template-rows: repeat(3, 1fr);
            gap: 12px;
        }

        .side-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .property-info {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .feature-icon {
            font-size: 1.5rem;
            color: #2563eb;
            width: 30px;
            text-align: center;
        }
        .price-badge {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            display: inline-block;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .location-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        .modal-image {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
        }

        .location-info-box {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .footer-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: #6b7280;
        }

        .footer-contact-item i {
            font-size: 1.25rem;
            margin-top: 0.25rem;
            color: #2563eb;
        }
        @media (max-width: 768px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .image-gallery {
                grid-template-columns: 1fr;
            }
            .side-images {
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: auto;
            }
            .side-image {
                height: 120px;
            }
            
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Property Header -->
        <div class="property-header">
            <h1 class="mb-3"><?= htmlspecialchars($property['title']) ?></h1>
            <div class="location-info">
                <i class="bi bi-geo-alt-fill text-primary"></i>
                <span><?= htmlspecialchars($property['address'] . ', ' . $property['city']) ?></span>
            </div>
            <div class="price-badge">
                KES <?= number_format($property['rent']) ?> / month
            </div>
        </div>

        <!-- Image Gallery -->
        <?php if (!empty($imageUrls)): ?>
        <div class="image-gallery">
            <img src="<?= htmlspecialchars($imageUrls[0]) ?>" alt="Main property image" class="main-image" onclick="openImageModal(0)">
            <div class="side-images">
                <?php for ($i = 1; $i < min(4, count($imageUrls)); $i++): ?>
                    <img src="<?= htmlspecialchars($imageUrls[$i]) ?>" alt="Property image <?= $i ?>" class="side-image" onclick="openImageModal(<?= $i ?>)">
                <?php endfor; ?>
            </div>
                    <?php for ($i = count($imageUrls); $i < 4; $i++): ?>
                        <div class="side-image" style="background: #e5e7eb; display: flex; align-items: center; justify-content: center; color: #9ca3af; border: 1px solid #ddd;">
                            <i class="bi bi-image" style="font-size: 2rem;"></i>
                        </div>
                    <?php endfor; ?>
        </div>
        <?php endif; ?>

        <!-- Property Information -->
        <div class="property-info">
            <h2 class="section-title">About Property</h2>
            <p class="text-muted" style="line-height: 1.8;">
                <?= !empty($property['description']) ? nl2br(htmlspecialchars($property['description'])) : 'No description available for this property.' ?>
            </p>
        </div>

        <!-- Advance Features -->
        <div class="property-info">
            <h2 class="section-title">Advance Features</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <i class="bi bi-door-open feature-icon"></i>
                    <span><?= (int)$property['bedrooms'] ?> Bedrooms</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-droplet feature-icon"></i>
                    <span><?= max(1, floor((int)$property['bedrooms'] / 2)) ?> Bathrooms</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-rulers feature-icon"></i>
                    <span><?= (int)$property['area'] ?> sqft</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-house feature-icon"></i>
                    <span><?= htmlspecialchars($property['type']) ?></span>
                </div>
                <?php 
                $amenity_icons = [
                    'WiFi' => 'wifi',
                    'Parking' => 'car-front',
                    'Water 24/7' => 'droplet',
                    'Security' => 'shield-check',
                    'Backup Generator' => 'lightning',
                    'Laundry' => 'bucket',
                    'TV Cable' => 'tv',
                    'Free Medical' => 'hospital',
                    'Fireplace' => 'fire',
                    'Free Spa' => 'flower1'
                ];
                foreach ($amenities_list as $amenity): 
                    $icon = $amenity_icons[$amenity] ?? 'check-circle';
                ?>
                <div class="feature-item">
                    <i class="bi bi-<?= $icon ?> feature-icon"></i>
                    <span><?= htmlspecialchars($amenity) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Location Section -->
        <div class="property-info">
            <h2 class="section-title">Location</h2>
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div id="map" style="width: 100%; height: 400px; border-radius: 10px; background: #e5e7eb; display: flex; align-items: center; justify-content: center; color: #9ca3af;">
                        <div class="text-center">
                            <i class="bi bi-map" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>Map view coming soon</p>
                            <small>Address: <?= htmlspecialchars($property['address'] . ', ' . $property['city']) ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="location-info-box">
                        <h3 class="feature-title">Property Location</h3>
                        <p class="feature-description">Find this property easily. Contact the landlord for viewing arrangements.</p>
                        <div class="footer-contact-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span><?= htmlspecialchars($property['address'] . ', ' . $property['city']) ?></span>
                        </div>
                        <div class="footer-contact-item">
                            <i class="bi bi-telephone-fill"></i>
                            <span>+254 700 000 000</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="property-info text-center">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <button class="btn btn-success btn-lg me-2" data-bs-toggle="modal" data-bs-target="#bookingModal">
                    <i class="bi bi-calendar-check me-2"></i>Book Now
                </button>
            <?php endif; ?>
            <a href="tel:+254700000000" class="btn btn-primary btn-lg me-2">
                <i class="bi bi-telephone me-2"></i>Contact Landlord
            </a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <a href="../dashboards/sections/search_properties.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-arrow-left me-2"></i>Back to Search
                </a>
            <?php else: ?>
                <a href="../index.html" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-arrow-left me-2"></i>Back to Home
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="bookingModalLabel">
                        <i class="bi bi-calendar-check me-2"></i>Book This Property
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bookingForm" method="POST" action="process_booking.php">
                        <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="checkInDate" class="form-label">Check-in Date</label>
                            <input type="date" class="form-control" id="checkInDate" name="check_in_date" required min="<?= date('Y-m-d') ?>">
                            <small class="text-muted">Select when you want to move in</small>
                        </div>

                        <div class="mb-3">
                            <label for="leaseLength" class="form-label">Lease Length</label>
                            <select class="form-select" id="leaseLength" name="lease_length" required>
                                <option value="">Select lease period</option>
                                <option value="1">1 Month</option>
                                <option value="3">3 Months</option>
                                <option value="6">6 Months</option>
                                <option value="12">1 Year</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any questions or special requests..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <strong>Property:</strong> <?= htmlspecialchars($property['title']) ?><br>
                            <strong>Monthly Rent:</strong> KES <?= number_format($property['rent']) ?><br>
                            <strong>Landlord:</strong> <?= !empty($property['landlord_name']) ? htmlspecialchars($property['landlord_name']) : 'To be confirmed' ?>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-check-circle me-2"></i>Confirm Booking
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" alt="Property image" class="modal-image">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const images = <?= json_encode($imageUrls) ?>;
        
        function openImageModal(index) {
            if (index >= 0 && index < images.length) {
                document.getElementById('modalImage').src = images[index];
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            }
        }

        // Handle booking form submission
        document.getElementById('bookingForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('process_booking.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
                    modal.hide();

                    // Show success message
                    alert(data.message + '\n\nReservation ID: ' + data.reservation_id);
                    
                    // Optionally redirect to student dashboard
                    setTimeout(() => {
                        window.location.href = '../dashboards/student_dashboard.php';
                    }, 2000);
                } else {
                    alert('Booking failed: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while processing your booking.');
            }
        });

        function initMap() {
            try {
                const address = "<?= htmlspecialchars($property['address'] . ', ' . $property['city']) ?>";
                const geocoder = new google.maps.Geocoder();

                geocoder.geocode({ 'address': address }, function(results, status) {
                    if (status === 'OK' && results && results[0]) {
                        const map = new google.maps.Map(document.getElementById("map"), {
                            zoom: 15,
                            center: results[0].geometry.location
                        });
                        const marker = new google.maps.Marker({
                            position: results[0].geometry.location,
                            map: map,
                            title: "<?= addslashes(htmlspecialchars($property['title'])) ?>"
                        });
                    } else {
                        // Fallback to Kisii if geocoding fails
                        const kisii = { lat: -0.6818, lng: 34.7677 };
                        const map = new google.maps.Map(document.getElementById("map"), {
                            zoom: 14,
                            center: kisii,
                        });
                        const marker = new google.maps.Marker({
                            position: kisii,
                            map: map,
                            title: "Student Housing Office (Approximate Location)"
                        });
                    }
                });
            } catch (error) {
                console.error('Google Maps error:', error);
                // Show fallback message
                document.getElementById("map").innerHTML = `
                    <div class="text-center">
                        <i class="bi bi-map" style="font-size: 3rem; margin-bottom: 1rem; color: #6b7280;"></i>
                        <p style="color: #6b7280;">Map temporarily unavailable</p>
                        <small style="color: #9ca3af;">Address: <?= htmlspecialchars($property['address'] . ', ' . $property['city']) ?></small>
                        <br><small style="color: #ef4444;">Please check your Google Maps API key configuration</small>
                    </div>
                `;
            }
        }

        function gm_authFailure() {
            console.error('Google Maps authentication failed');
            document.getElementById("map").innerHTML = `
                <div class="text-center">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; color: #ef4444;"></i>
                    <p style="color: #6b7280;">Google Maps API Key Error</p>
                    <small style="color: #9ca3af;">Address: <?= htmlspecialchars($property['address'] . ', ' . $property['city']) ?></small>
                    <br><small style="color: #ef4444;">Please configure a valid Google Maps API key in config/api_keys.php</small>
                </div>
            `;
        }
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDd5_XAqPcgJh0FHhyQJRG4-uvSe_abclE&callback=initMap" async defer></script>

</body>
</html>

