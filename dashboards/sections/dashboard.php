<?php
include("../../config/db.php");

//  Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'admin') {
    header("Location: ../auth/login.html");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Fetch sample metrics (replace with real queries)
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'] ?? 0;
$totalProperties = $conn->query("SELECT COUNT(*) AS total FROM properties")->fetch_assoc()['total'] ?? 0;
$totalBookings = $conn->query("SELECT COUNT(*) AS total FROM reservations")->fetch_assoc()['total'] ?? 0;
$totalRevenue = $conn->query("SELECT SUM(amount) AS total FROM payments")->fetch_assoc()['total'] ?? 0;
?>

