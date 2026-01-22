<?php
/**
 * Get My Bookings API Endpoint
 * GET /api/get_my_bookings.php
 * 
 * Query Parameters:
 * - status (optional): Filter by status (pending, confirmed, completed, cancelled)
 * - page (optional): Page number (default: 1)
 * - limit (optional): Results per page (default: 20)
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Bookings retrieved",
 *   "data": {
 *     "total": 5,
 *     "bookings": [...]
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

// Check if user is authenticated
$user = authenticateUser($conn);
if (!$user || $user['role'] !== 'student') {
    sendError('Authentication required. Must be logged in as student.', 401);
}

// Get request parameters
$data = getRequestData();
$page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
$limit = isset($data['limit']) ? min(100, max(1, intval($data['limit']))) : 20;
$offset = ($page - 1) * $limit;
$studentId = intval($user['user_id']);

// Build where clause
$whereConditions = ["r.student_id = ?"];
$params = [$studentId];
$types = 'i';

// Filter by status if provided
if (isset($data['status']) && !empty($data['status'])) {
    $status = sanitizeInput($conn, $data['status']);
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        sendError('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400);
    }
    $whereConditions[] = "r.status = ?";
    $params[] = $status;
    $types .= 's';
}

$where = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM reservations r $where";
$countStmt = $conn->prepare($countQuery);

if (!$countStmt) {
    logActivity('get_my_bookings', 'GET', false, 'Count prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$countStmt->bind_param($types, ...$params);
if (!$countStmt->execute()) {
    logActivity('get_my_bookings', 'GET', false, 'Count execute failed: ' . $countStmt->error);
    sendError('Database error', 500);
}

$countResult = $countStmt->get_result();
$totalRow = $countResult->fetch_assoc();
$total = intval($totalRow['total']);

// Get bookings with property details
$bookingsQuery = "
    SELECT
        r.id, r.property_id, r.student_id, r.landlord_id, r.check_in_date, r.check_out_date,
        r.amount, r.status, r.notes, r.created_at,
        p.title, p.city, p.address, p.rent, p.type
    FROM reservations r
    JOIN properties p ON r.property_id = p.id
    $where
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($bookingsQuery);

if (!$stmt) {
    logActivity('get_my_bookings', 'GET', false, 'Bookings prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

// Add pagination parameters
$newTypes = $types . 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt->bind_param($newTypes, ...$params);

if (!$stmt->execute()) {
    logActivity('get_my_bookings', 'GET', false, 'Bookings execute failed: ' . $stmt->error);
    sendError('Database error', 500);
}

$result = $stmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = [
        'id' => (int)$row['id'],
        'property' => [
            'id' => (int)$row['property_id'],
            'title' => $row['title'],
            'city' => $row['city'],
            'address' => $row['address'],
            'type' => $row['type']
        ],
        'check_in_date' => $row['check_in_date'],
        'check_out_date' => $row['check_out_date'],
        'amount' => (float)$row['amount'],
        'status' => $row['status'],
        'notes' => $row['notes'],
        'created_at' => $row['created_at']
    ];
}

$responseData = [
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($total / $limit),
    'bookings' => $bookings
];

logActivity('get_my_bookings', 'GET', true, "Retrieved $total bookings for student $studentId");
sendResponse(true, 'Bookings retrieved', $responseData);

$stmt->close();
$countStmt->close();
?>
