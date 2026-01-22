<?php
/**
 * Unsave Property API Endpoint
 * POST /api/unsave_property.php
 * 
 * Request:
 * {
 *   "property_id": 123
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Property unsaved",
 *   "data": {
 *     "property_id": 123,
 *     "saved": false
 *   }
 * }
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/config.php';

// Only allow POST requests
if (getRequestMethod() !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

// Check if user is authenticated
$user = authenticateUser($conn);
if (!$user || $user['role'] !== 'student') {
    sendError('Authentication required. Must be logged in as student.', 401);
}

// Get request data
$data = getRequestData();

// Validate required fields
if (!isset($data['property_id']) || empty($data['property_id'])) {
    sendError('Property ID is required', 400);
}

$propertyId = intval($data['property_id']);
$studentId = intval($user['user_id']);

if ($propertyId <= 0) {
    sendError('Invalid property ID', 400);
}

// Check if saved_properties table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'saved_properties'");
if (!$checkTable || $checkTable->num_rows === 0) {
    sendError('Saved properties feature is not available', 503);
}

// Remove saved property
$deleteQuery = "DELETE FROM saved_properties WHERE property_id = ? AND student_id = ?";
$deleteStmt = $conn->prepare($deleteQuery);

if (!$deleteStmt) {
    logActivity('unsave_property', 'POST', false, 'Delete prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$deleteStmt->bind_param('ii', $propertyId, $studentId);

if (!$deleteStmt->execute()) {
    logActivity('unsave_property', 'POST', false, 'Delete execute failed: ' . $deleteStmt->error);
    sendError('Failed to unsave property', 500);
}

$affectedRows = $deleteStmt->affected_rows;

if ($affectedRows === 0) {
    sendResponse(true, 'Property was not saved', [
        'property_id' => $propertyId,
        'saved' => false
    ]);
}

logActivity('unsave_property', 'POST', true, "Student $studentId unsaved property $propertyId");
sendResponse(true, 'Property unsaved', [
    'property_id' => $propertyId,
    'saved' => false
]);

$deleteStmt->close();
?>
