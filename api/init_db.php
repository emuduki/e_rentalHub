<?php
/**
 * Database Test and Initialize Script
 * This script tests database connection and creates test data if needed
 */

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "e_rentalhub";

// Create connection
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die(json_encode([
        'error' => 'Database connection failed',
        'details' => $conn->connect_error
    ]));
}

echo json_encode(['status' => 'Connected to MySQL']);
echo "\n";

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `e_rentalhub`";
if ($conn->query($sql) === TRUE) {
    echo json_encode(['status' => 'Database e_rentalhub created or exists']);
} else {
    echo json_encode(['error' => 'Error creating database: ' . $conn->error]);
}
echo "\n";

// Select the database
$conn->select_db($dbname);

// Create users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'landlord', 'admin') DEFAULT 'student',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['status' => 'Users table created or exists']);
} else {
    echo json_encode(['error' => 'Error creating users table: ' . $conn->error]);
}
echo "\n";

// Check if test user exists
$testEmail = "student@example.com";
$checkUser = $conn->prepare("SELECT user_id FROM users WHERE email = ?");

if (!$checkUser) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$checkUser->bind_param("s", $testEmail);
$checkUser->execute();
$result = $checkUser->get_result();

if ($result->num_rows === 0) {
    // Create test user
    $passwordHash = password_hash("password123", PASSWORD_BCRYPT);
    $insertUser = $conn->prepare(
        "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)"
    );
    
    if (!$insertUser) {
        echo json_encode(['error' => 'Insert prepare failed: ' . $conn->error]);
        exit;
    }
    
    $username = "john_student";
    $role = "student";
    $insertUser->bind_param("ssss", $username, $testEmail, $passwordHash, $role);
    
    if ($insertUser->execute()) {
        echo json_encode([
            'status' => 'Test user created',
            'email' => $testEmail,
            'password' => 'password123'
        ]);
    } else {
        echo json_encode(['error' => 'Error creating test user: ' . $insertUser->error]);
    }
    $insertUser->close();
} else {
    echo json_encode(['status' => 'Test user already exists']);
}
echo "\n";

// Test login query
$testEmail = "student@example.com";
$loginTest = $conn->prepare("SELECT user_id, username, email, password, role FROM users WHERE email = ?");

if (!$loginTest) {
    echo json_encode(['error' => 'Login test prepare failed: ' . $conn->error]);
    exit;
}

$loginTest->bind_param("s", $testEmail);
$loginTest->execute();
$loginResult = $loginTest->get_result();

if ($loginResult->num_rows > 0) {
    $user = $loginResult->fetch_assoc();
    if (password_verify("password123", $user['password'])) {
        echo json_encode([
            'status' => 'Login test successful',
            'user' => $user
        ]);
    } else {
        echo json_encode(['error' => 'Password verification failed']);
    }
} else {
    echo json_encode(['error' => 'User not found in login test']);
}
echo "\n";

$loginTest->close();
$conn->close();
?>
