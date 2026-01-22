<?php
/**
 * Create Booking API Endpoint
 * POST /api/create_booking.php
 * 
 * Request:
 * {
 *   "property_id": 123,
 *   "check_in_date": "2024-01-15",
 *   "check_out_date": "2024-06-30",
 *   "notes": "Special requests..."
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Booking created",
 *   "data": {
 *     "booking_id": 456,
 *     "property_id": 123,
 *     "status": "pending",
 *     "amount": 15000
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
$required = ['property_id', 'check_in_date', 'check_out_date'];
if (!validateRequired($data, $required)) {
    $missing = getMissingFields($data, $required);
    sendError('Missing required fields: ' . implode(', ', $missing), 400);
}

$propertyId = intval($data['property_id']);
$checkInDate = sanitizeInput($conn, $data['check_in_date']);
$checkOutDate = sanitizeInput($conn, $data['check_out_date']);
$notes = isset($data['notes']) ? sanitizeInput($conn, $data['notes']) : '';
$studentId = intval($user['user_id']);

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOutDate)) {
    sendError('Invalid date format. Use YYYY-MM-DD', 400);
}

$checkInTime = strtotime($checkInDate);
$checkOutTime = strtotime($checkOutDate);

if ($checkInTime === false || $checkOutTime === false) {
    sendError('Invalid date values', 400);
}

if ($checkInTime >= $checkOutTime) {
    sendError('Check-out date must be after check-in date', 400);
}

// Get property details
$propertyQuery = "SELECT id, rent, landlord_id FROM properties WHERE id = ? LIMIT 1";
$propertyStmt = $conn->prepare($propertyQuery);

if (!$propertyStmt) {
    logActivity('create_booking', 'POST', false, 'Property check prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$propertyStmt->bind_param('i', $propertyId);
if (!$propertyStmt->execute()) {
    logActivity('create_booking', 'POST', false, 'Property check execute failed: ' . $propertyStmt->error);
    sendError('Database error', 500);
}

$propertyResult = $propertyStmt->get_result();
if ($propertyResult->num_rows === 0) {
    sendError('Property not found', 404);
}

$property = $propertyResult->fetch_assoc();
$monthlyRent = floatval($property['rent']);
$landlordId = intval($property['landlord_id']);

// Calculate number of days
$daysCount = (int)(($checkOutTime - $checkInTime) / (24 * 60 * 60));
if ($daysCount <= 0) {
    sendError('Booking duration must be at least 1 day', 400);
}

// Assume monthly rate, calculate total amount
// Convert to months (approximately 30 days per month)
$monthsCount = $daysCount / 30;
$totalAmount = $monthlyRent * $monthsCount;

// Check if reservations table exists (some apps use "bookings" instead)
$checkReservationsTable = $conn->query("SHOW TABLES LIKE 'reservations'");
$hasReservationsTable = ($checkReservationsTable && $checkReservationsTable->num_rows > 0);

if (!$hasReservationsTable) {
    sendError('Booking system is not available', 503);
}

// Create booking
$bookingQuery = "
    INSERT INTO reservations 
    (property_id, student_id, landlord_id, check_in_date, check_out_date, amount, status, notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
";

$bookingStmt = $conn->prepare($bookingQuery);

if (!$bookingStmt) {
    logActivity('create_booking', 'POST', false, 'Booking insert prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$bookingStmt->bind_param('iiiisds', $propertyId, $studentId, $landlordId, $checkInDate, $checkOutDate, $totalAmount, $notes);

if (!$bookingStmt->execute()) {
    logActivity('create_booking', 'POST', false, 'Booking insert execute failed: ' . $bookingStmt->error);
    sendError('Failed to create booking', 500);
}

$bookingId = $bookingStmt->insert_id;

logActivity('create_booking', 'POST', true, "Student $studentId created booking $bookingId for property $propertyId");
sendResponse(true, 'Booking created', [
    'booking_id' => (int)$bookingId,
    'property_id' => $propertyId,
    'status' => 'pending',
    'amount' => round($totalAmount, 2),
    'check_in_date' => $checkInDate,
    'check_out_date' => $checkOutDate
], 201);

$bookingStmt->close();
$propertyStmt->close();
?>
