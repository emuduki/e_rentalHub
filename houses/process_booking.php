<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in as a student.']);
    exit();
}

// Get POST data
$property_id = intval($_POST['property_id'] ?? 0);
$student_id = $_SESSION['user_id'];
$check_in_date = trim($_POST['check_in_date'] ?? '');
$lease_length = intval($_POST['lease_length'] ?? 0);
// notes removed: some schemas don't have the 'notes' column, so we no longer store it here

// Validate inputs
if ($property_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid property ID.']);
    exit();
}

if (empty($check_in_date)) {
    echo json_encode(['success' => false, 'message' => 'Check-in date is required.']);
    exit();
}

// Validate date is in the future
$checkInDateTime = strtotime($check_in_date);
if ($checkInDateTime === false || $checkInDateTime < strtotime('today')) {
    echo json_encode(['success' => false, 'message' => 'Check-in date must be in the future.']);
    exit();
}

if ($lease_length <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid lease length.']);
    exit();
}

// Check if property exists
$propCheck = $conn->prepare("SELECT id, rent, landlord_id FROM properties WHERE id = ?");
$propCheck->bind_param("i", $property_id);
$propCheck->execute();
$propResult = $propCheck->get_result();

if ($propResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Property not found.']);
    exit();
}

$property = $propResult->fetch_assoc();
$landlord_id = isset($property['landlord_id']) ? (int)$property['landlord_id'] : 0;
$propCheck->close();
// Ensure the landlord referenced by the property exists. Do NOT use the session user id for landlord.
if ($landlord_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Property has no assigned landlord.']);
    exit();
}

$landCheck = $conn->prepare("SELECT id FROM landlords WHERE id = ? LIMIT 1");
if ($landCheck) {
    $landCheck->bind_param('i', $landlord_id);
    $landCheck->execute();
    $landResult = $landCheck->get_result();
    if (!$landResult || $landResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid landlord for this property.']);
        exit();
    }
    $landCheck->close();
} else {
    // If landlords table or query fails, abort with clear message
    error_log('process_booking: landlord lookup prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error validating landlord.']);
    exit();
}

// Create reservations table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    property_id INT NOT NULL,
    landlord_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    lease_length INT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (landlord_id) REFERENCES landlords(id)
)";

if (!$conn->query($createTableSQL)) {
    error_log("Failed to create reservations table: " . $conn->error);
}

// Check if student already has a pending booking for this property
$checkBooking = $conn->prepare("SELECT id FROM reservations WHERE student_id = ? AND property_id = ? AND status IN ('pending', 'approved')");
$checkBooking->bind_param("ii", $student_id, $property_id);
$checkBooking->execute();
$bookingResult = $checkBooking->get_result();

if ($bookingResult->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have a booking for this property.']);
    exit();
}
$checkBooking->close();

// Insert booking into database
// notes removed from insert to match existing DB schemas
$stmt = $conn->prepare("INSERT INTO reservations (student_id, property_id, landlord_id, check_in_date, lease_length) VALUES (?, ?, ?, ?, ?)");


if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("iiisi", $student_id, $property_id, $landlord_id, $check_in_date, $lease_length);

if ($stmt->execute()) {
    $reservation_id = $stmt->insert_id;
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking submitted successfully! The landlord will review your request.',
        'reservation_id' => $reservation_id
    ]);
} else {
    error_log("Booking insert error: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to create booking: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>