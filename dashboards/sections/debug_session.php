<?php
session_start();
header('Content-Type: application/json');
echo json_encode([
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'cookies' => $_COOKIE
]);
?>