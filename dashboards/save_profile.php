<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

// Lightweight debug log to a file to help trace requests in development.
// File: dashboards/save_profile_debug.log
try {
    $dbg = [
        'time' => date('c'),
        'script' => __FILE__,
        'session' => array_intersect_key($_SESSION ?? [], array_flip(['user_id','role','username'])),
        'post_keys' => array_keys($_POST ?? []),
        'files' => array_keys($_FILES ?? [])
    ];
    @file_put_contents(__DIR__ . '/save_profile_debug.log', json_encode($dbg) . PHP_EOL, FILE_APPEND | LOCK_EX);
} catch (
Exception $e) {
    // ignore file logging errors in production
}
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

error_log('save_profile.php invoked. session user_id=' . json_encode($_SESSION['user_id'] ?? null) . ' role=' . json_encode($_SESSION['role'] ?? null));

// Detect role and route to student handler if needed
$role = strtolower(trim($_SESSION['role'] ?? ''));
if ($role === 'student') {
    // Handle student profile save (centralized)
    try {
        error_log('save_profile.php: student branch entered. POST keys: ' . json_encode(array_keys($_POST)) . ' FILES: ' . json_encode(array_keys($_FILES)));
        // Collect student fields
        $fields = [
            'full_name', 'student_identifier', 'email', 'phone', 'bio',
            'university', 'course', 'year_of_study', 'current_address', 'emergency_name', 'emergency_phone'
        ];

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
        }

        // Handle avatar upload (optional)
        $avatar = null;
        if (!empty($_FILES['avatar']['name'])) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $filename = time() . "_avatar_" . basename($_FILES['avatar']['name']);
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                $avatar = $filename;
            }
        }

        // Also update the core users table if email is being changed
        if (isset($data['email']) && !empty($data['email'])) {
            $userUpdateStmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            if ($userUpdateStmt) {
                $userUpdateStmt->bind_param("si", $data['email'], $user_id);
                $userUpdateStmt->execute();
                $userUpdateStmt->close();
            }
        }

    // Check if students record exists (use a column-independent check to avoid PK name mismatches)
    $checkStmt = $conn->prepare("SELECT 1 FROM students WHERE user_id = ? LIMIT 1");
        if (!$checkStmt) throw new Exception("Prepare failed: " . $conn->error);
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();
        $exists = $checkRes->num_rows > 0;
        $checkStmt->close();

        if ($exists) {
            // Build UPDATE for provided fields
            $updates = [];
            $bind_values = [];
            $types = "";
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $bind_values[] = $data[$field];
                    $types .= "s";
                }
            }
            if ($avatar !== null) {
                $updates[] = "avatar = ?";
                $bind_values[] = $avatar;
                $types .= "s";
            }

            if (!empty($updates)) {
                $sql = "UPDATE students SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE user_id = ?";
                error_log('save_profile.php: student UPDATE SQL: ' . $sql . ' | bind_values: ' . json_encode($bind_values));
                $types .= "i";
                $bind_values[] = $user_id;

                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

                $refs = [&$types];
                foreach ($bind_values as &$val) $refs[] = &$val;
                call_user_func_array([$stmt, 'bind_param'], $refs);
                if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
                $stmt->close();
                // Fetch updated row to return to client
                $ps = $conn->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
                if (!$ps) throw new Exception("SELECT prepare failed: " . $conn->error);
                $ps->bind_param("i", $user_id);
                if (!$ps->execute()) throw new Exception("SELECT execute failed: " . $ps->error);
                $studentRow = $ps->get_result()->fetch_assoc();
                $ps->close();

                error_log('save_profile.php: student UPDATE successful, returning data for user_id=' . $user_id . ' | fetched row: ' . json_encode($studentRow));
                echo json_encode(['success' => true, 'message' => 'Student profile updated successfully', 'data' => $studentRow]);
                exit();
            } else {
                echo json_encode(['success' => true, 'message' => 'No changes to update']);
                exit();
            }
        } else {
            // INSERT new student record
            $columns = ['user_id'];
            $placeholders = ['?'];
            $types = 'i';
            $bind_values = [$user_id];
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $columns[] = $field;
                    $placeholders[] = '?';
                    $types .= 's';
                    $bind_values[] = $data[$field];
                }
            }
            if ($avatar !== null) {
                $columns[] = 'avatar';
                $placeholders[] = '?';
                $types .= 's';
                $bind_values[] = $avatar;
            }
            $columns[] = 'created_at'; $placeholders[] = 'NOW()';
            $columns[] = 'updated_at'; $placeholders[] = 'NOW()';

            // Note: The original version of this had a bug where placeholders didn't account for NOW()
            $sql = "INSERT INTO students (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            error_log('save_profile.php: student INSERT SQL: ' . $sql . ' | bind_values: ' . json_encode($bind_values));

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

            // bind only if there are param placeholders
            if (count($bind_values) > 0) {
                $refs = [&$types];
                foreach ($bind_values as &$val) $refs[] = &$val;
                call_user_func_array([$stmt, 'bind_param'], $refs);
            }

            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
            $stmt->close();
            // Fetch created row
            $ps = $conn->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
            $ps->bind_param("i", $user_id);
            $ps->execute();
            $studentRow = $ps->get_result()->fetch_assoc();
            $ps->close();

            echo json_encode(['success' => true, 'message' => 'Student profile created successfully', 'data' => $studentRow]);
            exit();
        }
    } catch (Exception $e) {
        error_log('save_profile.php student branch error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

try {
    // Collect and trim all POST fields
    $fields = [
        'first_name', 'last_name', 'email', 'phone', 'alt_phone',
        'address', 'city', 'county', 'postal_code', 'about_me',
        'business_name', 'tax_id', 'registration_number'
    ];

    $data = [];
    foreach ($fields as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    // Handle file upload
    $profile_picture = null;
    if (!empty($_FILES["profile_picture"]["name"])) {
        $target_dir = "../uploads/profile/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $filename = time() . "_" . basename($_FILES["profile_picture"]["name"]);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            $profile_picture = $filename;
        }
    }

    // Check if landlord record exists
    $checkStmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ?");
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $exists = $checkResult->num_rows > 0;
    $checkStmt->close();

    if ($exists) {
        // UPDATE: Build dynamic UPDATE statement with only submitted fields
        $updates = [];
        $bind_values = [];
        $types = "";

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $updates[] = "$field = ?";
                $bind_values[] = $data[$field];
                $types .= "s";
            }
        }

        if ($profile_picture !== null) {
            $updates[] = "profile_picture = ?";
            $bind_values[] = $profile_picture;
            $types .= "s";
        }

        if (!empty($updates)) {
            $sql = "UPDATE landlords SET " . implode(", ", $updates) . " WHERE user_id = ?";
            $types .= "i";
            $bind_values[] = $user_id;

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Dynamic binding
            $refs = [&$types];
            foreach ($bind_values as &$val) {
                $refs[] = &$val;
            }

            call_user_func_array([$stmt, 'bind_param'], $refs);

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'No changes to update']);
        }

    } else {
        // INSERT: Create new landlord profile with submitted fields
        $columns = ["user_id"];
        $placeholders = ["?"];
        $types = "i";
        $bind_values = [$user_id];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $columns[] = $field;
                $placeholders[] = "?";
                $types .= "s";
                $bind_values[] = $data[$field];
            }
        }

        if ($profile_picture !== null) {
            $columns[] = "profile_picture";
            $placeholders[] = "?";
            $types .= "s";
            $bind_values[] = $profile_picture;
        }

        $sql = "INSERT INTO landlords (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $refs = [&$types];
        foreach ($bind_values as &$val) {
            $refs[] = &$val;
        }

        call_user_func_array([$stmt, 'bind_param'], $refs);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Profile created successfully']);
    }

} catch (Exception $e) {
    error_log("save_profile.php error: " . $e->getMessage() . " | Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
