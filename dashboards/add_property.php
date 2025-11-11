<?php
session_start();
include("../config/db.php");

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    echo "error: Unauthorized access";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get landlord_id from session if not in POST
    $landlord_id = isset($_POST['landlord_id']) ? intval($_POST['landlord_id']) : $_SESSION['user_id'];
    
    // Validate that the landlord_id matches the session
    if ($landlord_id != $_SESSION['user_id']) {
        echo "error: Unauthorized access";
        exit();
    }
    
    // Sanitize and validate input
    $title = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $city = mysqli_real_escape_string($conn, trim($_POST['city'] ?? ''));
    $rent = floatval($_POST['rent'] ?? 0);
    $type = mysqli_real_escape_string($conn, trim($_POST['type'] ?? ''));
    $bedrooms = intval($_POST['bedrooms'] ?? 0);
    $area = intval($_POST['area'] ?? 0);
    $status = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Available'));
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));

    // Validate required fields
    if (empty($title) || empty($address) || empty($city) || $rent <= 0 || empty($type)) {
        echo "error: Please fill in all required fields";
        exit();
    }

    // Handle amenities
    $amenities = '';
    if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
        $amenities_array = array_map(function($item) use ($conn) {
            return mysqli_real_escape_string($conn, trim($item));
        }, $_POST['amenities']);
        $amenities = implode(', ', $amenities_array);
    }

    // Handle image uploads
    $images = [];
    if (!empty($_FILES['property_images']['name'][0])) {
		// Resolve upload directory robustly relative to project root
		$uploadDir = dirname(__DIR__) . "/uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

		$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/avif', 'image/webp'];
		$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'avif', 'webp'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
		$uploadErrors = [];

		// Use finfo to validate actual MIME type
		$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

        foreach ($_FILES['property_images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['property_images']['error'][$key] === UPLOAD_ERR_OK) {
				$fileType = $_FILES['property_images']['type'][$key];
                $fileSize = $_FILES['property_images']['size'][$key];
				$originalName = $_FILES['property_images']['name'][$key];
				$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                
				// Validate file extension
				if (!in_array($ext, $allowedExtensions)) {
					$uploadErrors[] = "Invalid extension for {$originalName}";
                    continue;
                }
                
				// Validate MIME type (best-effort)
				if ($finfo) {
					$detectedType = finfo_file($finfo, $tmpName);
					if ($detectedType && !in_array($detectedType, $allowedTypes)) {
						$uploadErrors[] = "Invalid MIME type for {$originalName}";
						continue;
					}
				}

                // Validate file size
                if ($fileSize > $maxFileSize) {
					$uploadErrors[] = "File too large: {$originalName}";
                    continue;
                }
                
				$filename = time() . "_" . uniqid() . "_" . basename($originalName);
				$targetFile = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR . $filename;
                
                if (move_uploaded_file($tmpName, $targetFile)) {
                    $images[] = $filename;
				} else {
					$uploadErrors[] = "Failed to move uploaded file: {$originalName}";
                }
            }
        }

		if ($finfo) {
			finfo_close($finfo);
		}

		// If files were selected but none passed validation/move, surface a helpful error
		if (empty($images) && !empty($uploadErrors)) {
			echo "error: " . implode('; ', $uploadErrors);
			exit();
		}
    }

    // Insert into database
    $query = "INSERT INTO properties (landlord_id, title, address, city, rent, type, bedrooms, area, status, description, amenities)
              VALUES ('$landlord_id', '$title', '$address', '$city', '$rent', '$type', '$bedrooms', '$area', '$status', '$description', '$amenities')";

   if ($conn->query($query)) {
    $property_id = $conn->insert_id; // Get the newly created property ID

    // Save uploaded images in property_images table
    $imgErrors = [];
    foreach ($images as $img) {
        $imgEsc = $conn->real_escape_string($img);
        $imgQuery = "INSERT INTO property_images (property_id, image_path) VALUES ('$property_id', '$imgEsc')";
        if (!$conn->query($imgQuery)) {
            $imgErrors[] = "Failed to insert image $img: " . $conn->error;
        }
    }

    if (!empty($imgErrors)) {
        // Return combined error information for debugging
        echo "error: Images uploaded but DB insert failed: " . implode('; ', $imgErrors);
        exit;
    }

    echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
}
// <-- This closing brace was missing
?>

