<?php
session_start();
include("../../config/db.php");

// ensure logged in landlord
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    header("Location: ../login.html");
    exit();
}
$landlord_id = $_SESSION['user_id'] ?? null;
if (!$landlord_id) {
    echo "Unauthorized";
    exit();
}