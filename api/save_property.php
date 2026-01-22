<?php
/**
 * Save Property API Endpoint
 * POST /api/save_property.php
 * 
 * Request:
 * {
 *   "property_id": 123
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Property saved",
 *   "data": {
 *     "property_id": 123,
 *     "saved": true
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

// Check if property exists
$propertyQuery = "SELECT id FROM properties WHERE id = ? LIMIT 1";
$propertyStmt = $conn->prepare($propertyQuery);

if (!$propertyStmt) {
    logActivity('save_property', 'POST', false, 'Property check prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$propertyStmt->bind_param('i', $propertyId);
if (!$propertyStmt->execute()) {
    logActivity('save_property', 'POST', false, 'Property check execute failed: ' . $propertyStmt->error);
    sendError('Database error', 500);
}

$propertyResult = $propertyStmt->get_result();
if ($propertyResult->num_rows === 0) {
    sendError('Property not found', 404);
}

// Check if saved_properties table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'saved_properties'");
if (!$checkTable || $checkTable->num_rows === 0) {
    sendError('Saved properties feature is not available', 503);
}

// Check if already saved
$checkQuery = "SELECT id FROM saved_properties WHERE property_id = ? AND student_id = ? LIMIT 1";
$checkStmt = $conn->prepare($checkQuery);

if (!$checkStmt) {
    logActivity('save_property', 'POST', false, 'Check prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$checkStmt->bind_param('ii', $propertyId, $studentId);
if (!$checkStmt->execute()) {
    logActivity('save_property', 'POST', false, 'Check execute failed: ' . $checkStmt->error);
    sendError('Database error', 500);
}

$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    sendResponse(true, 'Property already saved', [
        'property_id' => $propertyId,
        'saved' => true
    ]);
}

// Save property
$saveQuery = "INSERT INTO saved_properties (property_id, student_id, created_at) VALUES (?, ?, NOW())";
$saveStmt = $conn->prepare($saveQuery);

if (!$saveStmt) {
    logActivity('save_property', 'POST', false, 'Save prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$saveStmt->bind_param('ii', $propertyId, $studentId);

if (!$saveStmt->execute()) {
    logActivity('save_property', 'POST', false, 'Save execute failed: ' . $saveStmt->error);
    sendError('Failed to save property', 500);
}

logActivity('save_property', 'POST', true, "Student $studentId saved property $propertyId");
sendResponse(true, 'Property saved', [
    'property_id' => $propertyId,
    'saved' => true
]);

$saveStmt->close();
$checkStmt->close();
$propertyStmt->close();
?>
