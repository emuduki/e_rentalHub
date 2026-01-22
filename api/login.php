<?php
/**
 * Mobile Login API Endpoint
 * POST /api/login.php
 * 
 * Request:
 * {
 *   "email": "student@example.com",
 *   "password": "password123"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Login successful",
 *   "data": {
 *     "user_id": 1,
 *     "username": "John Doe",
 *     "email": "student@example.com",
 *     "role": "student",
 *     "token": "jwt_token_here"
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

// Get request data
$data = getRequestData();

// Validate required fields
if (!validateRequired($data, ['email', 'password'])) {
    $missing = getMissingFields($data, ['email', 'password']);
    sendError('Missing required fields: ' . implode(', ', $missing), 400);
}

$email = sanitizeInput($conn, $data['email']);
$password = $data['password']; // Don't sanitize password yet, will check with hash

// Validate email format
if (!validateEmail($email)) {
    sendError('Invalid email format', 400);
}

// Find user by email in users table
$query = "SELECT user_id, username, email, password, role FROM users WHERE email = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    logActivity('login', 'POST', false, 'Prepare failed: ' . $conn->error);
    sendError('Database error', 500);
}

$stmt->bind_param('s', $email);
if (!$stmt->execute()) {
    logActivity('login', 'POST', false, 'Execute failed: ' . $stmt->error);
    sendError('Database error', 500);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity('login', 'POST', false, 'User not found: ' . $email);
    sendError('Invalid email or password', 401);
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    logActivity('login', 'POST', false, 'Invalid password for: ' . $email);
    sendError('Invalid email or password', 401);
}

// Set session
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];

// TODO: Generate JWT token for stateless API authentication
// For now, return session-based response
$responseData = [
    'user_id' => (int)$user['user_id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'],
    'token' => session_id() // Temporary token (use JWT in production)
];

logActivity('login', 'POST', true, 'User logged in: ' . $email);
sendResponse(true, 'Login successful', $responseData);

$stmt->close();
?>
