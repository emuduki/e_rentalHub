<?php
/**
 * Get Properties API Endpoint
 * GET /api/get_properties.php
 * 
 * Query Parameters:
 * - page (optional): Page number for pagination (default: 1)
 * - limit (optional): Results per page (default: 20)
 * - type (optional): Filter by property type (Apartment, House, etc.)
 * - city (optional): Filter by city
 * - min_rent (optional): Minimum rent price
 * - max_rent (optional): Maximum rent price
 * - sort (optional): Sort by (newest, oldest, price_asc, price_desc)
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Properties retrieved",
 *   "data": {
 *     "total": 150,
 *     "page": 1,
 *     "limit": 20,
 *     "properties": [...]
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

// Get request parameters
$data = getRequestData();
$page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
$limit = isset($data['limit']) ? min(100, max(1, intval($data['limit']))) : 20;
$offset = ($page - 1) * $limit;

// Build where clause
$whereConditions = ["p.status = 'Available'"];
$params = [];
$types = '';

// Filter by type
if (isset($data['type']) && !empty($data['type'])) {
    $type = sanitizeInput($conn, $data['type']);
    $whereConditions[] = "p.type = ?";
    $params[] = $type;
    $types .= 's';
}

// Filter by city
if (isset($data['city']) && !empty($data['city'])) {
    $city = sanitizeInput($conn, $data['city']);
    $whereConditions[] = "p.city = ?";
    $params[] = $city;
    $types .= 's';
}

// Filter by min rent
if (isset($data['min_rent'])) {
    $minRent = floatval($data['min_rent']);
    $whereConditions[] = "p.rent >= ?";
    $params[] = $minRent;
    $types .= 'd';
}

// Filter by max rent
if (isset($data['max_rent'])) {
    $maxRent = floatval($data['max_rent']);
    $whereConditions[] = "p.rent <= ?";
    $params[] = $maxRent;
    $types .= 'd';
}

$where = "WHERE " . implode(" AND ", $whereConditions);

// Determine sort order
$orderBy = "p.id DESC"; // default: newest first
if (isset($data['sort'])) {
    $sort = sanitizeInput($conn, $data['sort']);
    switch ($sort) {
        case 'oldest':
            $orderBy = "p.id ASC";
            break;
        case 'price_asc':
            $orderBy = "p.rent ASC";
            break;
        case 'price_desc':
            $orderBy = "p.rent DESC";
            break;
    }
}

// Check if property_images table exists
$checkImagesTable = $conn->query("SHOW TABLES LIKE 'property_images'");
$hasImagesTable = ($checkImagesTable && $checkImagesTable->num_rows > 0);

// Build image subquery
if ($hasImagesTable) {
    $imageSubquery = "(SELECT GROUP_CONCAT(image_path ORDER BY id DESC SEPARATOR ',') FROM property_images WHERE property_id = p.id LIMIT 4) AS image_paths";
} else {
    $imageSubquery = "NULL AS image_paths";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM properties p $where";
$countStmt = $conn->prepare($countQuery);

if ($types && count($params) > 0) {
    $countStmt->bind_param($types, ...$params);
}

if (!$countStmt->execute()) {
    logActivity('get_properties', 'GET', false, 'Count query failed: ' . $countStmt->error);
    sendError('Database error', 500);
}

$countResult = $countStmt->get_result();
$totalRow = $countResult->fetch_assoc();
$total = intval($totalRow['total']);

// Get properties
$query = "
    SELECT
        p.id, p.title, p.city, p.address, p.rent, p.type, p.bedrooms, p.area, p.description,
        $imageSubquery
    FROM properties p
    $where
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    logActivity('get_properties', 'GET', false, 'Prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

// Bind pagination parameters
$newTypes = $types . 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt->bind_param($newTypes, ...$params);

if (!$stmt->execute()) {
    logActivity('get_properties', 'GET', false, 'Execute failed: ' . $stmt->error);
    sendError('Database error', 500);
}

$result = $stmt->get_result();
$properties = [];

while ($row = $result->fetch_assoc()) {
    $properties[] = formatProperty($row);
}

$responseData = [
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($total / $limit),
    'properties' => $properties
];

logActivity('get_properties', 'GET', true, "Retrieved $limit properties (page $page)");
sendResponse(true, 'Properties retrieved', $responseData);

$stmt->close();
$countStmt->close();
?>
