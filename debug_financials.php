<?php
session_start();
include("config/db.php");

// Ensure user is logged in
$session_user_id = $_SESSION['user_id'] ?? 0;
if (!$session_user_id) die("User not logged in");

// Determine if this is for a landlord or admin viewing platform-wide stats
$is_admin = strtolower(trim($_SESSION['role'] ?? '')) === 'admin';
$landlord_id = null;

if (!$is_admin) {
    // Fetch landlord ID for regular landlords
    $stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $landlord_id = $res['id'] ?? 0;
    $stmt->close();
}

echo "<h2>Debug Financials Data</h2>";
echo "<p>User ID: $session_user_id</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'unknown') . "</p>";
echo "<p>Is Admin: " . ($is_admin ? 'Yes' : 'No') . "</p>";
echo "<p>Landlord ID: " . ($landlord_id ?? 'N/A') . "</p>";

// Check total reservations
$query = $is_admin ?
    "SELECT COUNT(*) as total FROM reservations" :
    "SELECT COUNT(*) as total FROM reservations WHERE landlord_id=?";
$stmt = $conn->prepare($query);
if (!$is_admin) $stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$total_reservations = $result->fetch_assoc()['total'];
echo "<p>Total Reservations: $total_reservations</p>";

// Check confirmed reservations
$query = $is_admin ?
    "SELECT COUNT(*) as total FROM reservations WHERE status='confirmed'" :
    "SELECT COUNT(*) as total FROM reservations WHERE landlord_id=? AND status='confirmed'";
$stmt = $conn->prepare($query);
if (!$is_admin) $stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$confirmed_reservations = $result->fetch_assoc()['total'];
echo "<p>Confirmed Reservations: $confirmed_reservations</p>";

// Show sample confirmed reservations
$query = $is_admin ?
    "SELECT id, amount, status, created_at FROM reservations WHERE status='confirmed' LIMIT 5" :
    "SELECT id, amount, status, created_at FROM reservations WHERE landlord_id=? AND status='confirmed' LIMIT 5";
$stmt = $conn->prepare($query);
if (!$is_admin) $stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Sample Confirmed Reservations:</h3>";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p>ID: {$row['id']}, Amount: {$row['amount']}, Status: {$row['status']}, Created: {$row['created_at']}</p>";
    }
} else {
    echo "<p>No confirmed reservations found.</p>";
}

// Check properties
$query = $is_admin ?
    "SELECT COUNT(*) as total FROM properties" :
    "SELECT COUNT(*) as total FROM properties WHERE landlord_id=?";
$stmt = $conn->prepare($query);
if (!$is_admin) $stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$total_properties = $result->fetch_assoc()['total'];
echo "<p>Total Properties: $total_properties</p>";

$stmt->close();
$conn->close();
?>
