<?php
/**
 * Search Properties API Endpoint
 * GET /api/search_properties.php
 * 
 * Query Parameters:
 * - q (optional): Search query (title, address, city)
 * - page (optional): Page number (default: 1)
 * - limit (optional): Results per page (default: 20)
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Search results",
 *   "data": {
 *     "query": "apartment",
 *     "total": 45,
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
$query = isset($data['q']) ? trim($data['q']) : '';
$page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
$limit = isset($data['limit']) ? min(100, max(1, intval($data['limit']))) : 20;
$offset = ($page - 1) * $limit;

// If no search query provided, return error
if (empty($query)) {
    sendError('Search query is required', 400);
}

// Sanitize query
$searchQuery = '%' . $conn->real_escape_string($query) . '%';

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
$countQuery = "
    SELECT COUNT(*) as total FROM properties p
    WHERE p.status = 'Available' 
    AND (p.title LIKE ? OR p.address LIKE ? OR p.city LIKE ?)
";

$countStmt = $conn->prepare($countQuery);
if (!$countStmt) {
    logActivity('search_properties', 'GET', false, 'Prepare count failed: ' . $conn->error);
    sendError('Database error', 500);
}

$countStmt->bind_param('sss', $searchQuery, $searchQuery, $searchQuery);
if (!$countStmt->execute()) {
    logActivity('search_properties', 'GET', false, 'Execute count failed: ' . $countStmt->error);
    sendError('Database error', 500);
}

$countResult = $countStmt->get_result();
$totalRow = $countResult->fetch_assoc();
$total = intval($totalRow['total']);

// Get properties
$searchQuery_db = '%' . $conn->real_escape_string($query) . '%';
$searchQuerySQL = "
    SELECT
        p.id, p.title, p.city, p.address, p.rent, p.type, p.bedrooms, p.area, p.description,
        $imageSubquery
    FROM properties p
    WHERE p.status = 'Available'
    AND (p.title LIKE ? OR p.address LIKE ? OR p.city LIKE ?)
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($searchQuerySQL);
if (!$stmt) {
    logActivity('search_properties', 'GET', false, 'Prepare search failed: ' . $conn->error);
    sendError('Database error', 500);
}

$stmt->bind_param('sssii', $searchQuery_db, $searchQuery_db, $searchQuery_db, $limit, $offset);

if (!$stmt->execute()) {
    logActivity('search_properties', 'GET', false, 'Execute search failed: ' . $stmt->error);
    sendError('Database error', 500);
}

$result = $stmt->get_result();
$properties = [];

while ($row = $result->fetch_assoc()) {
    $properties[] = formatProperty($row);
}

$responseData = [
    'query' => $query,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($total / $limit),
    'properties' => $properties
];

logActivity('search_properties', 'GET', true, "Searched for: '$query' (found $total results)");
sendResponse(true, 'Search results', $responseData);

$stmt->close();
$countStmt->close();
?>
