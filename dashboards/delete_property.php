<?php
session_start();
include("../config/db.php");

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a landlord
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the property ID from POST data
$property_id = intval($_POST['property_id'] ?? 0);

if ($property_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid property ID']);
    exit();
}

// Get the REAL landlord_id from landlords table
$logged_user_id = $_SESSION['user_id'];
$getLandlord = $conn->query("SELECT id FROM landlords WHERE user_id = $logged_user_id LIMIT 1");
$landlordData = $getLandlord->fetch_assoc();

if (!$landlordData) {
    echo json_encode(['success' => false, 'message' => 'Landlord profile not found']);
    exit();
}

$landlord_id = $landlordData['id'];

// Verify that the property belongs to this landlord
$checkProperty = $conn->query("SELECT id, title FROM properties WHERE id = $property_id AND landlord_id = $landlord_id LIMIT 1");
$propertyData = $checkProperty->fetch_assoc();

if (!$propertyData) {
    echo json_encode(['success' => false, 'message' => 'Property not found or access denied']);
    exit();
}

$property_title = $propertyData['title'];

// Start transaction for data integrity
$conn->begin_transaction();

try {
    // Delete property images first (to maintain foreign key constraints)
    $deleteImages = $conn->query("DELETE FROM property_images WHERE property_id = $property_id");

    // Delete saved properties references
    $deleteSaved = $conn->query("DELETE FROM saved_properties WHERE property_id = $property_id");

    // Delete reservations/bookings
    $deleteReservations = $conn->query("DELETE FROM reservations WHERE property_id = $property_id");

    // Delete inquiries
    $deleteInquiries = $conn->query("DELETE FROM inquiries WHERE property_id = $property_id");

    // Finally, delete the property itself
    $deleteProperty = $conn->query("DELETE FROM properties WHERE id = $property_id AND landlord_id = $landlord_id");

    if ($deleteProperty) {
        // Commit the transaction
        $conn->commit();

        // Delete physical image files from uploads directory
        $uploadsDir = realpath(__DIR__ . "/../uploads");
        if ($uploadsDir) {
            // Get all image paths for this property (in case some weren't deleted from DB)
            $imageQuery = $conn->query("SELECT image_path FROM property_images WHERE property_id = $property_id");
            while ($imageRow = $imageQuery->fetch_assoc()) {
                $imagePath = $uploadsDir . DIRECTORY_SEPARATOR . $imageRow['image_path'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Property "' . htmlspecialchars($property_title) . '" has been successfully deleted'
        ]);
    } else {
        throw new Exception('Failed to delete property from database');
    }

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting property: ' . $e->getMessage()]);
}

$conn->close();
?>
