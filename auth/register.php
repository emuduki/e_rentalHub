<?php
// Fix include path (project has folder named 'condig' in this workspace)
include(__DIR__ . "/../condig/db.php");

// Only handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Use null-coalescing to avoid undefined index warnings
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // Accept optional role from the form; default to 'user' when missing or empty
    $role = trim($_POST['role'] ?? '');

    // Basic validation
    $errors = [];
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!empty($errors)) {
        // Return simple error messages (you can change to redirect with session flash)
        foreach ($errors as $err) {
            echo '<p style="color:red">' . htmlspecialchars($err) . '</p>';
        }
        exit;
    }

    // Ensure $conn exists
    if (!isset($conn) || $conn === null) {
        echo '<p style="color:red">Database connection not available.</p>';
        exit;
    }

    // Username is already from form (stored in $username)
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    // Normalize role
    $allowedRoles = ['student', 'landlord', 'admin', 'user'];
    if ($role === '' || !in_array($role, $allowedRoles, true)) {
        $role = 'user';
    }

    // Optional: check for existing email
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if ($check) {
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo '<p style="color:red">An account with that email already exists.</p>';
            $check->close();
            exit;
        }
        $check->close();
    }

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo '<p style="color:red">Prepare failed: ' . htmlspecialchars($conn->error) . '</p>';
        exit;
    }
    $stmt->bind_param('ssss', $username, $email, $hashed, $role);

    if ($stmt->execute()) {
        echo 'Registration successful! <a href="../login.html">Login</a>';
    } else {
        echo '<p style="color:red">Error: ' . htmlspecialchars($stmt->error) . '</p>';
    }

    $stmt->close();
    $conn->close();
}
?>
