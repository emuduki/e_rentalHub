<?php
session_start();
header('Content-Type: application/json');
include("../../config/db.php");

// Only students may save properties
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    error_log("toggle_save_property: Unauthorized access attempt. session_role=" . ($_SESSION['role'] ?? 'NULL'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized - please log in as a student to save properties.']);
    exit();
}

if (!isset($_POST['property_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$student_id = intval($_SESSION['user_id']);
$property_id = intval($_POST['property_id']);
$action = $_POST['action'];

// Ensure saved_properties table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'saved_properties'");
if (!$checkTable || $checkTable->num_rows === 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS saved_properties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        property_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_save (student_id, property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($createTable)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create saved_properties table: ' . $conn->error
        ]);
        exit();
    }
}

try {
    if ($action === 'save') {
        $stmt = $conn->prepare("INSERT IGNORE INTO saved_properties (student_id, property_id) VALUES (?, ?)");
    } else {
        $stmt = $conn->prepare("DELETE FROM saved_properties WHERE student_id = ? AND property_id = ?");
    }

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("ii", $student_id, $property_id);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => $action === 'save' ? 'Property saved' : 'Property unsaved'
    ]);
} catch (Exception $e) {
    error_log('toggle_save_property error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    $conn->close();
}