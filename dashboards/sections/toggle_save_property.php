<?php
session_start();
header('Content-Type: application/json');
include("../../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['property_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$student_id = intval($_SESSION['user_id']);
$property_id = intval($_POST['property_id']);
$action = $_POST['action'];

// Check if saved_properties table exists, create if not
$checkTable = $conn->query("SHOW TABLES LIKE 'saved_properties'");
if (!$checkTable || $checkTable->num_rows === 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS saved_properties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        property_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_save (student_id, property_id)
    )";
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
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    // Don't close the connection here as it might be used elsewhere
}
?>