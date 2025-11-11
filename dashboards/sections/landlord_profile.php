<?php
session_start();
include("../../config/db.php");

//Get Landlord details
$landlord_id = $_SESSION['user_id'] ?? null;
if (!$landlord_id) {
    echo "<p class='text-danger'>Unauthorized access. Please log in.</p>";
    exit();
}

// Prepare and execute the query safely to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM landlords WHERE id = ?");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$landlord = $result->fetch_assoc(); // Will be null if no landlord record is found
$stmt->close();

// Fetch Payment Info
$payStmt = $conn->prepare("SELECT * FROM landlord_payments WHERE landlord_id = ?");
$payStmt->bind_param("i", $landlord_id);
$payStmt->execute();
$payment = $payStmt->get_result()->fetch_assoc();
$payStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {

    // Normalize inputs and support multiple payment types.
    $method = $_POST['payment_method'];
    $bank = $_POST['bank_name'] ?? '';
    // account_number is used for Bank Transfer account number, but for M-Pesa or PayPal
    // we also accept mpesa_number or paypal_email and map them into account_number.
    $accNum = $_POST['account_number'] ?? $_POST['mpesa_number'] ?? $_POST['paypal_email'] ?? '';
    $accName = $_POST['account_name'] ?? $_POST['account_name_mpesa'] ?? '';

    // Check if payment row exists
    $check = $conn->prepare("SELECT id FROM landlord_payments WHERE landlord_id = ?");
    $check->bind_param("i", $landlord_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        // Update
        $stmt = $conn->prepare("UPDATE landlord_payments 
            SET payment_method=?, bank_name=?, account_number=?, account_name=? 
            WHERE landlord_id=?");
        $stmt->bind_param("ssssi", $method, $bank, $accNum, $accName, $landlord_id);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO landlord_payments 
            (landlord_id, payment_method, bank_name, account_number, account_name) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $landlord_id, $method, $bank, $accNum, $accName);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Payment info updated successfully');</script>";
    } else {
        echo "<script>alert('Error saving payment info');</script>";
    }
    $stmt->close();
}

// Fetch Notifications
$notiStmt = $conn->prepare("SELECT * FROM landlord_notifications WHERE landlord_id = ?");
$notiStmt->bind_param("i", $landlord_id);
$notiStmt->execute();
$notifications = $notiStmt->get_result()->fetch_assoc();
$notiStmt->close();

// Handle profile picture upload and name update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['profile_picture']) || isset($_POST['update_names']))) {
    $success = true;
    $message = '';

    // Handle profile picture upload if a file was sent
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $fileName = $landlord_id . '_' . time() . '_' . basename($file['name']);
        $uploadDir = "../../uploads/";
        $uploadFile = $uploadDir . $fileName;

        // Create uploads directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Check if it's an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $success = false;
            $message = "Uploaded file is not an image.";
        } else {
            // Move the file and update DB
            if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                // Delete old profile picture if exists
                if (!empty($landlord['profile_picture'])) {
                    $oldFile = $uploadDir . $landlord['profile_picture'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                $stmt = $conn->prepare("UPDATE landlords SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $fileName, $landlord_id);
                if (!$stmt->execute()) {
                    $success = false;
                    $message = "Failed to update profile picture in database.";
                }
                $stmt->close();
            } else {
                $success = false;
                $message = "Failed to upload image.";
            }
        }
    }

    // Handle name updates if submitted
    if (isset($_POST['first_name']) || isset($_POST['last_name'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        
        $stmt = $conn->prepare("UPDATE landlords SET first_name = ?, last_name = ? WHERE id = ?");
        $stmt->bind_param("ssi", $firstName, $lastName, $landlord_id);
        if (!$stmt->execute()) {
            $success = false;
            $message .= " Failed to update names.";
        }
        $stmt->close();
    }

    // Show result message
    if ($success) {
        echo "<script>alert('Profile updated successfully.');</script>";
    } else {
        echo "<script>alert('Error: " . addslashes($message) . "');</script>";
    }

    // Refresh landlord data
    $stmt = $conn->prepare("SELECT * FROM landlords WHERE id = ?");
    $stmt->bind_param("i", $landlord_id);
    $stmt->execute();
    $landlord = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

//if no notifications, insert defaults
if(!$notifications) {
    $conn->query("INSERT INTO landlord_notifications (landlord_id) VALUES ($landlord_id)");
    $notifications = $conn->query("SELECT * FROM landlord_notifications WHERE landlord_id = $landlord_id")->fetch_assoc();
}


//Handle notification updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_new_bookings'])) {
    $email_new_bookings = isset($_POST['email_new_bookings']) ? 1 : 0;
    $email_new_messages = isset($_POST['email_new_messages']) ? 1 : 0;
    $sms_booking_confirmations = isset($_POST['sms_booking_confirmations']) ? 1 : 0;
    $sms_payment_updates = isset($_POST['sms_payment_updates']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE landlord_notifications 
        SET email_new_bookings=?, email_new_messages=?, sms_booking_confirmations=?, sms_payment_updates=? 
        WHERE landlord_id=?");
    $stmt->bind_param("iiiii", $email_new_bookings, $email_new_messages, $sms_booking_confirmations, $sms_payment_updates, $landlord_id);

    if ($stmt->execute()) {
        echo "<script>alert('Notification settings updated successfully');</script>";
    } else {
        echo "<script>alert('Error updating notification settings');</script>";
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f5f5f5;
            padding-top: 56px;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }


        /* Profile header area */
        .profile-card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /* Avatar styling */
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #555;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            position: relative;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .tab-content {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #eee;
        }
        /* Tab navigation */
        .nav-tabs {
            background: rgba(0, 0, 0, 0.1);
            border: none !important;
            border-radius: 12px;
            padding: 4px 8px;
            margin-bottom: 24px;
            display: flex;
            gap: 4px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .nav-tabs .nav-link {
            flex: 1;
            border: none;
            margin: 0 4px;
            padding: 10px 20px;
            font-weight: 500;
            color: #555;
            border-radius: 50px;
            transition: all 0.2s;
            text-align: center;
            font-weight: 600;
        }

        .nav-tabs .nav-link:hover {
            background: rgba(0,0,0,0.04);
            color: #333;
        }

        .nav-tabs .nav-link.active {
            background: #fff;
            color: #000;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        /* Form controls */
        .form-control {
            background-color: #fff !important;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 15px;
            color: #333;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.15) !important;
        }

        .input-group-text {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px 0 0 8px;
            color: #555;
        }

        label.form-label {
            font-size: 14px;
            font-weight: 500;
            color: #444;
            margin-bottom: 6px;
        }

        /* Notification boxes */
        .notify-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid #eee;
        }

        /* Save button */
        .btn-save {
            background: #000;
            color: #fff;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-save:hover {
            background: #333;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        /* Form check styling */
        .form-check-input:checked {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }

        textarea.form-control {
            resize: none;
            min-height: 120px;
        }

    </style>
    </head>
    <body>

        <div class="profile-wrapper">
            <h4 class="fw-bold mb-2">My Profile</h4>
                <p class="text-muted mb-4">Manage your personal information and account settings</p>

        </div>

    
        <div class="profile-card">
            <div class="d-flex align-items-center">

                <!--  HIDDEN FORM JUST FOR PROFILE IMAGE UPLOAD -->
                <form id="profilePicForm" method="POST" enctype="multipart/form-data" action="update_profile_picture.php" class="d-none">
                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" onchange="document.getElementById('profilePicForm').submit()">
                </form>

                <!-- Profile Picture -->
                <div class="position-relative">
                    <div class="profile-avatar">
                        <?php 
                        if (!empty($landlord['profile_picture']) && file_exists("../../uploads/" . $landlord['profile_picture'])) {
                            echo "<img src='../../uploads/" . htmlspecialchars($landlord['profile_picture']) . "'>";
                        } else {
                            $firstName = $landlord['first_name'] ?? '';
                            $lastName = $landlord['last_name'] ?? '';
                            echo strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                        }
                        ?>
                    </div>

                    <!-- CAMERA ICON TRIGGERS THE HIDDEN FILE INPUT -->
                    <label class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-2 camera-btn cursor-pointer"
                        onclick="document.getElementById('profile_picture_input').click()">
                        <i class="bi bi-camera text-white"></i>
                    </label>
                </div>

                <!-- LANDLORD NAME FORM -->
                <div class="ms-4">
                    <form id="nameForm" method="POST" action="update_landlord_name.php" class="mb-2">
                        <div class="d-flex gap-2 align-items-center">
                            <input type="text" class="form-control form-control-sm" name="first_name"
                                placeholder="First Name"
                                value="<?= htmlspecialchars($landlord['first_name'] ?? '') ?>" required>

                            <input type="text" class="form-control form-control-sm" name="last_name"
                                placeholder="Last Name"
                                value="<?= htmlspecialchars($landlord['last_name'] ?? '') ?>" required>

                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </div>
                    </form>

                    <p class="text-muted mb-1"><?= htmlspecialchars($landlord['email'] ?? '') ?></p>
                    <p class="text-muted mb-0"><?= htmlspecialchars($landlord['business_name'] ?? 'Add Business Name') ?></p>
                </div>

                <!--  SIDE BUTTON -->
                <div class="ms-auto">
                    <button class="btn btn-save">Save Changes</button>
                </div>

            </div>
        </div>


            <!--Tabs-->
         <ul class="nav nav-tabs border-0 mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">Personal</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="business-tab" data-bs-toggle="tab" data-bs-target="#business" type="button" role="tab" aria-controls="business" aria-selected="false">Business</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">Payment</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">Security</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="false">Notifications</button>
                </li>
        </ul>


            <!--Tab Content-->
        <div class="tab-content">
                <div class="tab-pane fade show active" id="personal">
                    <h5 class="fw-bold mb-3">Personal Information</h5>
                    <p class="text-muted mb-4">Update your personal details and contact information</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name"
                                value="<?= htmlspecialchars($landlord['first_name'] ?? '') ?>" >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name"
                            value="<?= htmlspecialchars($landlord['last_name'] ?? '') ?>" >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email"
                            value="<?= htmlspecialchars($landlord['email'] ?? '') ?>" >

                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Primary Phone</label>
                        <input type="text" class="form-control" name="phone"
                            value="<?= htmlspecialchars($landlord['phone'] ?? '') ?>" >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Alternate Phone</label>
                        <input type="text" class="form-control" name="alt_phone"
                            value="<?= htmlspecialchars($landlord['alt_phone'] ?? '') ?>" >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" name="address"
                            value="<?= htmlspecialchars($landlord['address'] ?? '') ?>" >
                    </div>

                    <div class="col-4">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city"
                            value="<?= htmlspecialchars($landlord['city'] ?? '') ?>" >
                    </div>

                    <div class="col-4">
                        <label class="form-label">County</label>
                        <input type="text" class="form-control" name="county"
                            value="<?= htmlspecialchars($landlord['county'] ?? '') ?>" >
                    </div>

                    <div class="col-4">
                        <label class="form-label">Postal Code</label>
                        <input type="text" class="form-control" name="postal_code"
                            value="<?= htmlspecialchars($landlord['postal_code'] ?? '') ?>" >
                    </div>

                    <div class="col-12">
                        <label class="form-label">About Me</label>
                        <textarea class="form-control" name="about_me" rows="3">
                            <?= htmlspecialchars($landlord['about_me'] ?? '') ?></textarea>
                    </div>

                    </div>
                    


                </div>
                
                <div class="tab-pane fade" id="business">
                    <div class="card border-0 shadow-sm p-4 rounded-4" style="background: #fff;">
                        <h5 class="fw-bold mb-2">Business Information</h5>
                        <p>Manage your business details and reegistration information</p>

                        <div class="row g-4">
                            <!--Business Name (full width)--->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Business Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <input type="text" class="form-control" name="business_name"
                                    value="<?= htmlspecialchars($landlord['business_name'] ?? '') ?>" >
                                </div>       
                            </div>

                            <!---Tx ID / PIN (half width)--->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tax ID / PIN</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-file-earmark-text"></i></span>
                                    <input type="text" class="form-control" name="tax_id"
                                    value="<?= htmlspecialchars($landlord['tax_id'] ?? '') ?>" >
                                </div>
                            </div>

                            <!---Business Registration Number (half width)--->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Registration</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-123"></i></span>
                                    <input type="text" class="form-control" name="registration_number"
                                    value="<?= htmlspecialchars($landlord['registration_number'] ?? '') ?>" >
                                </div>
                            </div>
                        </div>
                            <div class="alert alert-warning mt-4 rounded-3">
                                <strong>Note:</strong> Business registration details help students trust your listings
                                and are required  for legal compliance in property rental.
                            </div>

                    </div>

                </div>

                <div class="tab-pane fade" id="payment">
                    <h5 class="fw-bold mb-2">Payment Information</h5>
                    <p class="text-muted mb-4">Configure how you receive payments from students</p>

                    <form method="POST" id="paymentForm">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Preferred Payment Method</label>
                            <select class="form-select" name="payment_method" id="paymentMethodSelect">
                                <option value="Bank Transfer" <?= ($payment['payment_method'] ?? '') == 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                <option value="M-Pesa" <?= ($payment['payment_method'] ?? '') == 'M-Pesa' ? 'selected' : '' ?>>M-Pesa</option>
                                <option value="PayPal" <?= ($payment['payment_method'] ?? '') == 'PayPal' ? 'selected' : '' ?>>PayPal</option>
                                <option value="Cash" <?= ($payment['payment_method'] ?? '') == 'Cash' ? 'selected' : '' ?>>Cash</option>
                            </select>
                        </div>

                        <!-- Bank Transfer fields -->
                        <div id="bankFields" class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Bank Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-bank"></i></span>
                                    <input type="text" class="form-control" name="bank_name"
                                        value="<?= htmlspecialchars($payment['bank_name'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Account Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-credit-card-2-back"></i></span>
                                    <input type="text" class="form-control" name="account_number"
                                        value="<?= htmlspecialchars($payment['account_number'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Account Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" class="form-control" name="account_name"
                                        value="<?= htmlspecialchars($payment['account_name'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- M-Pesa fields -->
                        <div id="mpesaFields" class="row g-3 d-none">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">M-Pesa Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-phone"></i></span>
                                    <input type="text" class="form-control" name="mpesa_number"
                                        value="<?= htmlspecialchars($payment['mpesa_number'] ?? $payment['account_number'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Account Name (optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" name="account_name_mpesa"
                                        value="<?= htmlspecialchars($payment['account_name'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- PayPal fields (placeholder, editable) -->
                        <div id="paypalFields" class="row g-3 d-none">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">PayPal Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" name="paypal_email"
                                        value="<?= htmlspecialchars($payment['paypal_email'] ?? $payment['account_number'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Cash selected -->
                        <div id="cashFields" class="row g-3 d-none">
                            <div class="col-12">
                                <div class="alert alert-info">Cash selected — no additional details required.</div>
                            </div>
                        </div>

                        <div class="alert alert-warning mt-4 rounded-3">
                            <strong>Security:</strong> Your payment information is encrypted and stored securely.
                            It will only be shared with students who have confirmed bookings.
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="security">
                    <h5 class="fw-bold mb-2">Security Settings</h5>
                    <p class="text-muted mb-4">Manage your account and password security.</p>

                    <div class="card border-0 shadow-sm p-4 rounded-4" style="background: #fff;">
                        <form method="POST" action="../../actions/update_password.php" id="changePasswordForm">
                            <div class="row g-4">
                                <!-- Current Password -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" name="current_password" placeholder="Enter current password" required>
                                    </div>
                                </div>

                                <!-- New Password -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">
                                            <i class="bi bi-shield-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" name="new_password" placeholder="Enter new password" required>
                                    </div>
                                </div>

                                <!-- Confirm New Password -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">
                                            <i class="bi bi-check2-circle"></i>
                                        </span>
                                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required>
                                    </div>
                                </div>

                                <!-- Change Password Button -->
                                <div class="col-12 d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-dark fw-semibold px-4 py-2">
                                        <i class="bi bi-arrow-repeat me-2"></i>Change Password
                                    </button>
                                </div>
                            </div>
                        </form>

                    </div>
                    
                </div>

                <div class="tab-pane fade" id="notifications">
                    <h5 class="fw-bold mb-2">Notification Settings</h5>
                    <p class="text-muted mb-4">Choose how you want to receive updates and alerts.</p>
                    
                    <form method="POST" id="notificationForm">

                        <div class="notify-box d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="bi bi-envelope me-2"></i>Email - New Bookings</h6>
                                <p class="text-muted small mb-0">Get notified about new bookings via email.</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" name="email_new_bookings"
                                    <?= ($notifications['email_new_bookings'] ?? 0) ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="notify-box d-flex justify-content-between align-items-center ">
                            <div>
                                <h6 class="mb-1"><i class="bi bi-envelope me-2"></i>Email - New Messages</h6>
                                <p class="text-muted small mb-0">Get notified when students send inquiries</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" name="email_new_messages"
                                    <?= ($notifications['email_new_messages'] ?? 0) ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="notify-box d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="bi bi-telephone me-2"></i>SMS - Booking Confirmations</h6>
                                <p class="text-muted small mb-0">Receive SMS alerts for confirmed bookings.</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" name="sms_booking_confirmations"
                                    <?= ($notifications['sms_booking_confirmations'] ?? 0) ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="notify-box d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="bi bi-telephone me-2"></i>SMS - Payment Updates</h6>
                                <p class="text-muted small mb-0">Get SMS when payments are received.</p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" name="sms_payment_updates"
                                    <?= ($notifications['sms_payment_updates'] ?? 0) ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4 rounded-3 small">
                            <i class="bi bi-bell-fill me-2 fs-5"></i>
                            <span>
                                You can update your notification preferences at any time.
                                We respect your privacy and will not share your contact details.
                            </span>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-dark fw-semibold px-4 py-2"> Save Changes</button>
                        </div>

                    </form>
                </div>

        </div>

        



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle payment fields based on selected payment method
        document.addEventListener('DOMContentLoaded', function () {
            const select = document.getElementById('paymentMethodSelect');
            if (!select) return; // form not on page

            const bankFields = document.getElementById('bankFields');
            const mpesaFields = document.getElementById('mpesaFields');
            const paypalFields = document.getElementById('paypalFields');
            const cashFields = document.getElementById('cashFields');

            function updatePaymentFields() {
                const v = select.value;
                bankFields.classList.toggle('d-none', v !== 'Bank Transfer');
                mpesaFields.classList.toggle('d-none', v !== 'M-Pesa');
                paypalFields.classList.toggle('d-none', v !== 'PayPal');
                cashFields.classList.toggle('d-none', v !== 'Cash');

                // required toggles
                document.querySelectorAll('#bankFields input').forEach(i => i.required = (v === 'Bank Transfer'));
                document.querySelectorAll('#mpesaFields input').forEach(i => i.required = (v === 'M-Pesa'));
                document.querySelectorAll('#paypalFields input').forEach(i => i.required = (v === 'PayPal'));
            }

            select.addEventListener('change', updatePaymentFields);
            updatePaymentFields();
        });
    </script>
    </body>
    </html>