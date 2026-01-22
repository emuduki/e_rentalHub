<?php
/**
 * Mobile Registration API Endpoint
 * POST /api/register.php
 * 
 * Request:
 * {
 *   "username": "John Doe",
 *   "email": "student@example.com",
 *   "password": "password123",
 *   "role": "student"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Registration successful",
 *   "data": {
 *     "user_id": 1,
 *     "username": "John Doe",
 *     "email": "student@example.com",
 *     "role": "student"
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
$required = ['username', 'email', 'password', 'role'];
if (!validateRequired($data, $required)) {
    $missing = getMissingFields($data, $required);
    sendError('Missing required fields: ' . implode(', ', $missing), 400);
}

$username = sanitizeInput($conn, $data['username']);
$email = sanitizeInput($conn, $data['email']);
$password = $data['password'];
$role = sanitizeInput($conn, $data['role']);

// Validate email format
if (!validateEmail($email)) {
    sendError('Invalid email format', 400);
}

// Validate password length
if (strlen($password) < 6) {
    sendError('Password must be at least 6 characters', 400);
}

// Validate role
$validRoles = ['student', 'landlord', 'admin'];
if (!in_array($role, $validRoles)) {
    sendError('Invalid role. Must be: ' . implode(', ', $validRoles), 400);
}

// Check if email already exists
$checkQuery = "SELECT id FROM users WHERE email = ?";
$checkStmt = $conn->prepare($checkQuery);

if (!$checkStmt) {
    logActivity('register', 'POST', false, 'Prepare check failed: ' . $conn->error);
    sendError('Database error', 500);
}

$checkStmt->bind_param('s', $email);
if (!$checkStmt->execute()) {
    logActivity('register', 'POST', false, 'Execute check failed: ' . $checkStmt->error);
    sendError('Database error', 500);
}

$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    logActivity('register', 'POST', false, 'Email already exists: ' . $email);
    sendError('Email already registered', 409);
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Insert new user
$insertQuery = "INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())";
$insertStmt = $conn->prepare($insertQuery);

if (!$insertStmt) {
    logActivity('register', 'POST', false, 'Prepare insert failed: ' . $conn->error);
    sendError('Database error', 500);
}

$insertStmt->bind_param('ssss', $username, $email, $passwordHash, $role);

if (!$insertStmt->execute()) {
    logActivity('register', 'POST', false, 'Execute insert failed: ' . $insertStmt->error);
    sendError('Failed to create user', 500);
}

$userId = $insertStmt->insert_id;

// Create role-specific profile if needed
if ($role === 'student') {
    $profileQuery = "INSERT INTO students (user_id, full_name, email, created_at) VALUES (?, ?, ?, NOW())";
    $profileStmt = $conn->prepare($profileQuery);
    if ($profileStmt) {
        $profileStmt->bind_param('iss', $userId, $username, $email);
        $profileStmt->execute();
        $profileStmt->close();
    }
} elseif ($role === 'landlord') {
    $profileQuery = "INSERT INTO landlords (user_id, full_name, email, created_at) VALUES (?, ?, ?, NOW())";
    $profileStmt = $conn->prepare($profileQuery);
    if ($profileStmt) {
        $profileStmt->bind_param('iss', $userId, $username, $email);
        $profileStmt->execute();
        $profileStmt->close();
    }
}

// Set session
$_SESSION['user_id'] = $userId;
$_SESSION['username'] = $username;
$_SESSION['email'] = $email;
$_SESSION['role'] = $role;

$responseData = [
    'user_id' => (int)$userId,
    'username' => $username,
    'email' => $email,
    'role' => $role
];

logActivity('register', 'POST', true, 'User registered: ' . $email);
sendResponse(true, 'Registration successful', $responseData, 201);

$insertStmt->close();
$checkStmt->close();
?>
