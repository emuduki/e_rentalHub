<?php
session_start();
include(__DIR__ . "/../config/db.php");

// Only handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        echo 'Email and password are required.';
        exit;
    }

    // Ensure DB connection exists
    if (!isset($conn) || $conn === null) {
        echo 'Database connection not available.';
        exit;
    }

    // Select all fields so we don't assume a specific primary key column name
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        echo 'Database error: ' . htmlspecialchars($conn->error);
        exit;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Set session data; the users table may use a different PK name
            $user_id = null;
            if (isset($user['id'])) {
                $user_id = $user['id'];
            } elseif (isset($user['user_id'])) {
                $user_id = $user['user_id'];
            } elseif (isset($user['userid'])) {
                $user_id = $user['userid'];
            } elseif (isset($user['uid'])) {
                $user_id = $user['uid'];
            }
            // Fallback to email if no numeric id column exists
            if ($user_id === null) {
                $user_id = $user['email'];
            }

            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $user['username'] ?? $user['email'] ?? '';
            $_SESSION['role'] = strtolower(trim($user['role'] ?? 'user'));

            // Redirect based on role
            $role = $_SESSION['role'];
            if ($role === 'student') {
                header('Location: ../dashboards/student_dashboard.php');
                exit;
            } elseif ($role === 'landlord') {
                header('Location: ../dashboards/landlord_dashboard.php');
                exit;
            } elseif ($role === 'admin') {
                header('Location: ../dashboards/admin_dashboard.php');
                exit;
            } else {
                // Default redirect
                header('Location: ../dashboards/student_dashboard.php');
                exit;
            }
        } else {
            echo 'Invalid email or password.';
        }
    } else {
        echo 'Invalid email or password.';
    }

    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
