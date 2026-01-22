<?php
session_start();
header('Content-Type: application/json');
include("../config/db.php");

// Debug logging
error_log("Cancel booking API called");
error_log("Session: " . json_encode($_SESSION));
error_log("POST data: " . json_encode($_POST));

// Ensure logged in student
$role = strtolower(trim($_SESSION['role'] ?? ''));
if ($role !== 'student') {
    error_log("Unauthorized: role is '$role'");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id) {
    error_log("Unauthorized: no user_id in session");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get request parameters
$data = getRequestData();
$reservation_id = intval($data['reservation_id'] ?? 0);
if (!$reservation_id) {
    sendError('Invalid reservation ID', 400);
}

// Check if the reservation belongs to the student and is cancellable
$sql = "SELECT status FROM reservations WHERE id = ? AND student_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    logActivity('cancel_booking', 'POST', false, 'Select prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$stmt->bind_param("ii", $reservation_id, $student_id);
if (!$stmt->execute()) {
    logActivity('cancel_booking', 'POST', false, 'Select execute failed: ' . $stmt->error);
    sendError('Database error', 500);
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    logActivity('cancel_booking', 'POST', false, "Reservation $reservation_id not found for student $student_id");
    sendError('Reservation not found', 404);
}

$row = $result->fetch_assoc();
$status = $row['status'];
if (!in_array($status, ['pending', 'confirmed'])) {
    logActivity('cancel_booking', 'POST', false, "Cannot cancel reservation $reservation_id with status '$status'");
    sendError('Cannot cancel this booking. Only pending or confirmed bookings can be cancelled.', 400);
}

$stmt->close();

// Update status to cancelled
$update_sql = "UPDATE reservations SET status = 'cancelled' WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
if (!$update_stmt) {
    logActivity('cancel_booking', 'POST', false, 'Update prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$update_stmt->bind_param("i", $reservation_id);
if (!$update_stmt->execute()) {
    logActivity('cancel_booking', 'POST', false, 'Update execute failed: ' . $update_stmt->error);
    sendError('Failed to cancel booking', 500);
}

$update_stmt->close();

logActivity('cancel_booking', 'POST', true, "Cancelled reservation $reservation_id for student $student_id");
sendResponse(true, 'Booking cancelled successfully');
