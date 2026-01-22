<?php
/**
 * API Configuration and Helper Functions
 * Centralized configuration for all API endpoints
 */

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

/**
 * Send JSON response
 * 
 * @param bool $success - Operation status
 * @param string $message - Response message
 * @param mixed $data - Response data
 * @param int $statusCode - HTTP status code
 */
function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Send error response
 * 
 * @param string $message - Error message
 * @param int $statusCode - HTTP status code
 * @param mixed $data - Additional error data
 */
function sendError($message, $statusCode = 400, $data = null) {
    sendResponse(false, $message, $data, $statusCode);
}

/**
 * Get request method
 * 
 * @return string - HTTP method
 */
function getRequestMethod() {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

/**
 * Get request data (handles GET, POST, JSON)
 * 
 * @return array - Request parameters
 */
function getRequestData() {
    $method = getRequestMethod();
    
    if ($method === 'GET') {
        return $_GET;
    }
    
    if ($method === 'POST' || $method === 'PUT') {
        // Try to parse JSON first
        $json = file_get_contents('php://input');
        if ($json) {
            $decoded = json_decode($json, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        
        // Fall back to POST data
        return $_POST;
    }
    
    return [];
}

/**
 * Validate required fields
 * 
 * @param array $data - Data to validate
 * @param array $required - Required field names
 * @return bool - True if all required fields present
 */
function validateRequired($data, $required) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            return false;
        }
    }
    return true;
}

/**
 * Get missing fields
 * 
 * @param array $data - Data to validate
 * @param array $required - Required field names
 * @return array - Missing field names
 */
function getMissingFields($data, $required) {
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Start user session from token/header
 * Returns user data if authenticated, null otherwise
 * 
 * @param mysqli $conn - Database connection
 * @return array|null - User data or null
 */
function authenticateUser($conn) {
    // Check for Authorization header or session
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        // Bearer token authentication (implement JWT or token validation)
        // For now, check session
    }
    
    // Check session
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        return [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'],
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null
        ];
    }
    
    return null;
}

/**
 * Sanitize string input
 * 
 * @param mysqli $conn - Database connection
 * @param string $input - Input to sanitize
 * @return string - Sanitized input
 */
function sanitizeInput($conn, $input) {
    return $conn->real_escape_string(trim($input));
}

/**
 * Validate email format
 * 
 * @param string $email - Email to validate
 * @return bool - True if valid email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format property data for API response
 * 
 * @param array $property - Property data from database
 * @return array - Formatted property data
 */
function formatProperty($property) {
    return [
        'id' => (int)$property['id'],
        'title' => $property['title'] ?? '',
        'city' => $property['city'] ?? '',
        'address' => $property['address'] ?? '',
        'rent' => (float)$property['rent'] ?? 0,
        'type' => $property['type'] ?? '',
        'bedrooms' => (int)$property['bedrooms'] ?? 0,
        'area' => $property['area'] ?? '',
        'description' => $property['description'] ?? '',
        'status' => $property['status'] ?? 'Available',
        'image_paths' => !empty($property['image_paths']) ? explode(',', $property['image_paths']) : []
    ];
}

/**
 * Log API activity for debugging
 * 
 * @param string $endpoint - API endpoint
 * @param string $method - HTTP method
 * @param bool $success - Request status
 * @param string $message - Log message
 */
function logActivity($endpoint, $method, $success, $message = '') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $method $endpoint - " . ($success ? 'SUCCESS' : 'FAILURE') . " - $message\n";
    
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($logEntry, 3, $logDir . '/api.log');
}

?>
