<?php
/**
 * Get Property Details API Endpoint
 * GET /api/get_property_details.php?id=123
 * 
 * Query Parameters:
 * - id (required): Property ID
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Property details retrieved",
 *   "data": {
 *     "id": 1,
 *     "title": "Modern Apartment",
 *     ...full property details...
 *   }
 * }
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/config.php';

// Only allow GET requests
if (getRequestMethod() !== 'GET') {
    sendError('Method not allowed. Use GET.', 405);
}

// Get request data
$data = getRequestData();

// Validate required fields
if (!isset($data['id']) || empty($data['id'])) {
    sendError('Property ID is required', 400);
}

$propertyId = intval($data['id']);

if ($propertyId <= 0) {
    sendError('Invalid property ID', 400);
}

// Check if property_images table exists
$checkImagesTable = $conn->query("SHOW TABLES LIKE 'property_images'");
$hasImagesTable = ($checkImagesTable && $checkImagesTable->num_rows > 0);

// Build image subquery
if ($hasImagesTable) {
    $imageSubquery = "(SELECT GROUP_CONCAT(image_path ORDER BY id DESC SEPARATOR ',') FROM property_images WHERE property_id = p.id LIMIT 10) AS image_paths";
} else {
    $imageSubquery = "NULL AS image_paths";
}

// Get property details
$query = "
    SELECT
        p.id, p.title, p.city, p.address, p.rent, p.type, p.bedrooms, 
        p.area, p.description, p.status, p.landlord_id, p.created_at,
        $imageSubquery
    FROM properties p
    WHERE p.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    logActivity('get_property_details', 'GET', false, 'Prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$stmt->bind_param('i', $propertyId);

if (!$stmt->execute()) {
    logActivity('get_property_details', 'GET', false, 'Execute failed: ' . $stmt->error);
    sendError('Database error', 500);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity('get_property_details', 'GET', false, 'Property not found: ' . $propertyId);
    sendError('Property not found', 404);
}

$property = $result->fetch_assoc();
$formattedProperty = formatProperty($property);

// Add additional details
$formattedProperty['created_at'] = $property['created_at'] ?? null;
$formattedProperty['landlord_id'] = intval($property['landlord_id'] ?? 0);

// Get landlord details if landlord_id exists
if (!empty($formattedProperty['landlord_id'])) {
    $landlordQuery = "SELECT full_name, email, phone FROM landlords WHERE id = ? LIMIT 1";
    $landlordStmt = $conn->prepare($landlordQuery);
    if ($landlordStmt) {
        $landlordStmt->bind_param('i', $formattedProperty['landlord_id']);
        if ($landlordStmt->execute()) {
            $landlordResult = $landlordStmt->get_result();
            if ($landlordResult->num_rows > 0) {
                $formattedProperty['landlord'] = $landlordResult->fetch_assoc();
            }
        }
        $landlordStmt->close();
    }
}

// Check if property is saved by current user
if (isset($_SESSION['user_id'])) {
    $checkSavedTable = $conn->query("SHOW TABLES LIKE 'saved_properties'");
    if ($checkSavedTable && $checkSavedTable->num_rows > 0) {
        $saveQuery = "SELECT id FROM saved_properties WHERE property_id = ? AND student_id = ? LIMIT 1";
        $saveStmt = $conn->prepare($saveQuery);
        if ($saveStmt) {
            $studentId = intval($_SESSION['user_id']);
            $saveStmt->bind_param('ii', $propertyId, $studentId);
            if ($saveStmt->execute()) {
                $saveResult = $saveStmt->get_result();
                $formattedProperty['is_saved'] = $saveResult->num_rows > 0;
            }
            $saveStmt->close();
        }
    }
}

logActivity('get_property_details', 'GET', true, 'Retrieved property: ' . $propertyId);
sendResponse(true, 'Property details retrieved', $formattedProperty);

$stmt->close();
?>
