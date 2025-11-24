<?php
session_start();
include("../../config/db.php");

/**
 * landlord_profile.php
 * - Keeps your existing tabbed UI and logic.
 * - Uses $_SESSION['user_id'] as users.id and finds/creates the landlords row via landlords.user_id.
 * - Saves full profile to landlords table.
 * - Handles profile picture upload with basic validation and safe filename handling.
 * - Keeps payment + notification handling as in your previous file.
 *
 * NOTE: you said: user_id = users.id so we look up landlords by landlords.user_id = $_SESSION['user_id']
 */

// Ensure user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<p class='text-danger'>Unauthorized access. Please log in.</p>";
    exit();
}

// Helper: sanitize filename
function sanitize_filename($name) {
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return $name;
}

// Fetch landlord row by user_id
$stmt = $conn->prepare("SELECT * FROM landlords WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$landlord = $result->fetch_assoc();
$stmt->close();

// If no landlord record exists, create one with default values (user_id)
if (!$landlord) {
    $insertStmt = $conn->prepare("INSERT INTO landlords (user_id, created_at) VALUES (?, NOW())");
    $insertStmt->bind_param("i", $user_id);
    $insertStmt->execute();
    $insertStmt->close();

    // Re-fetch
    $stmt = $conn->prepare("SELECT * FROM landlords WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $landlord = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch user row as a fallback for missing landlord fields (so we display sensible defaults)
$uStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
if ($uStmt) {
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $userRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
} else {
        $userRow = [];
        // Log prepare failure
        error_log("landlord_profile.php: failed to prepare users SELECT: " . $conn->error);
}

// Merge user fields first, then landlord fields so landlord overrides user where present
$landlord = array_merge((array)$userRow, (array)$landlord);

    // Debug logging: indicate whether we found landlord/user info
    if (empty($landlord) || (!isset($landlord['id']) && empty($landlord['email']))) {
        error_log("landlord_profile.php: no landlord or user data found for user_id={$user_id}");
    } else {
        $lid = $landlord['id'] ?? 'n/a';
        $lem = $landlord['email'] ?? 'n/a';
        error_log("landlord_profile.php: loaded landlord id={$lid}, email={$lem} for user_id={$user_id}");
    }

// Fetch Payment Info
$payStmt = $conn->prepare("SELECT * FROM landlord_payments WHERE landlord_id = ?");
$landlord_id_for_pay = $landlord['id'] ?? null;
$payStmt->bind_param("i", $landlord_id_for_pay);
$payStmt->execute();
$payment = $payStmt->get_result()->fetch_assoc();
$payStmt->close();

// Fetch Notifications
$notiStmt = $conn->prepare("SELECT * FROM landlord_notifications WHERE landlord_id = ?");
$notiStmt->bind_param("i", $landlord_id_for_pay);
$notiStmt->execute();
$notifications = $notiStmt->get_result()->fetch_assoc();
$notiStmt->close();

// If no notifications row insert defaults
if (!$notifications && $landlord_id_for_pay) {
    $ins = $conn->prepare("INSERT INTO landlord_notifications (landlord_id) VALUES (?)");
    $ins->bind_param("i", $landlord_id_for_pay);
    $ins->execute();
    $ins->close();

    $notiStmt = $conn->prepare("SELECT * FROM landlord_notifications WHERE landlord_id = ?");
    $notiStmt->bind_param("i", $landlord_id_for_pay);
    $notiStmt->execute();
    $notifications = $notiStmt->get_result()->fetch_assoc();
    $notiStmt->close();
}

/*
 * Handle payment form (keeps your previous logic)
 * Payment form posts payment_method and associated fields
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {

    $method = trim($_POST['payment_method']);
    $bank = trim($_POST['bank_name'] ?? '');
    $accNum = trim($_POST['account_number'] ?? $_POST['mpesa_number'] ?? $_POST['paypal_email'] ?? '');
    $accName = trim($_POST['account_name'] ?? $_POST['account_name_mpesa'] ?? '');

    // If landlord row has no id (shouldn't happen) attempt to get it again
    $landlord_id_for_pay = $landlord['id'] ?? null;

    if (!$landlord_id_for_pay) {
        // Try to refetch
        $stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $landlordRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $landlord_id_for_pay = $landlordRow['id'] ?? null;
    }

    if ($landlord_id_for_pay) {
        // Check existing
        $check = $conn->prepare("SELECT id FROM landlord_payments WHERE landlord_id = ?");
        $check->bind_param("i", $landlord_id_for_pay);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE landlord_payments SET payment_method = ?, bank_name = ?, account_number = ?, account_name = ? WHERE landlord_id = ?");
            $stmt->bind_param("ssssi", $method, $bank, $accNum, $accName, $landlord_id_for_pay);
        } else {
            $stmt = $conn->prepare("INSERT INTO landlord_payments (landlord_id, payment_method, bank_name, account_number, account_name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $landlord_id_for_pay, $method, $bank, $accNum, $accName);
        }

        if ($stmt->execute()) {
            echo "<script>alert('Payment info updated successfully');</script>";
            // refresh payment
            $ps = $conn->prepare("SELECT * FROM landlord_payments WHERE landlord_id = ?");
            $ps->bind_param("i", $landlord_id_for_pay);
            $ps->execute();
            $payment = $ps->get_result()->fetch_assoc();
            $ps->close();
        } else {
            echo "<script>alert('Error saving payment info');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Unable to locate landlord record for payments.');</script>";
    }
}

/*
 * Handle profile picture upload & quick name update via file input or update_names marker
 * This preserves your previous behavior but ensures landlords are found by user_id
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['profile_picture']) || isset($_POST['update_names']))) {
    $success = true;
    $message = '';

    // ensure landlord id
    $landlord_id_for_pay = $landlord['id'] ?? null;

    // Profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $origName = basename($file['name']);
        $safeName = time() . '_' . sanitize_filename($origName);
        $uploadDir = "../../uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $uploadFile = $uploadDir . $safeName;

        // Validate image
        $imageInfo = @getimagesize($file['tmp_name']);
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if ($imageInfo === false || !in_array($imageInfo[2], $allowedTypes, true)) {
            $success = false;
            $message = "Uploaded file is not an accepted image type (jpg, png, gif, webp).";
        } elseif ($file['size'] > 4 * 1024 * 1024) { // 4MB limit
            $success = false;
            $message = "Image exceeds 4MB size limit.";
        } else {
            if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                // remove old file if exists and is not empty
                if (!empty($landlord['profile_picture'])) {
                    $oldFile = $uploadDir . $landlord['profile_picture'];
                    if (file_exists($oldFile) && is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                // Update DB
                $stmt = $conn->prepare("UPDATE landlords SET profile_picture = ? WHERE user_id = ?");
                $stmt->bind_param("si", $safeName, $user_id);
                if (!$stmt->execute()) {
                    $success = false;
                    $message = "Failed to update profile picture in database: " . $stmt->error;
                }
                $stmt->close();

                // refresh landlord row
                $stmt = $conn->prepare("SELECT * FROM landlords WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $landlord = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            } else {
                $success = false;
                $message = "Failed to move uploaded file.";
            }
        }
    }

    // Quick name update
    if (isset($_POST['first_name']) || isset($_POST['last_name'])) {
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name'] ?? '');
        $stmt = $conn->prepare("UPDATE landlords SET first_name = ?, last_name = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $fn, $ln, $user_id);
        if (!$stmt->execute()) {
            $success = false;
            $message .= " Failed to update names.";
        } else {
            // refresh landlord
            $stmt2 = $conn->prepare("SELECT * FROM landlords WHERE user_id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $landlord = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }
        $stmt->close();
    }

    if ($success) {
        echo "<script>alert('Profile updated successfully.');</script>";
    } else {
        echo "<script>alert('Error: " . addslashes($message) . "');</script>";
    }

}

/*
 * Handle notification toggles (keeps previous logic)
 * We check presence of the notification flag in POST and update accordingly
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['email_new_bookings']) || isset($_POST['email_new_messages']) || isset($_POST['sms_booking_confirmations']) || isset($_POST['sms_payment_updates']))) {
    $email_new_bookings = isset($_POST['email_new_bookings']) ? 1 : 0;
    $email_new_messages = isset($_POST['email_new_messages']) ? 1 : 0;
    $sms_booking_confirmations = isset($_POST['sms_booking_confirmations']) ? 1 : 0;
    $sms_payment_updates = isset($_POST['sms_payment_updates']) ? 1 : 0;

    $landlord_id_for_pay = $landlord['id'] ?? null;
    if ($landlord_id_for_pay) {
        $stmt = $conn->prepare("UPDATE landlord_notifications SET email_new_bookings=?, email_new_messages=?, sms_booking_confirmations=?, sms_payment_updates=? WHERE landlord_id=?");
        $stmt->bind_param("iiiii", $email_new_bookings, $email_new_messages, $sms_booking_confirmations, $sms_payment_updates, $landlord_id_for_pay);
        if ($stmt->execute()) {
            echo "<script>alert('Notification settings updated successfully');</script>";
            // refresh notifications
            $ns = $conn->prepare("SELECT * FROM landlord_notifications WHERE landlord_id = ?");
            $ns->bind_param("i", $landlord_id_for_pay);
            $ns->execute();
            $notifications = $ns->get_result()->fetch_assoc();
            $ns->close();
        } else {
            echo "<script>alert('Error updating notification settings');</script>";
        }
        $stmt->close();
    }
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
    body { min-height: 100vh; display: flex; flex-direction: column; background-color:#f5f5f5; padding-top:56px; font-family: 'Inter', 'Segoe UI', sans-serif;}
    .profile-card{background:#fff;border-radius:12px;padding:24px;margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
    .profile-avatar{width:100px;height:100px;background:#f8f9fa;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;color:#555;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.12);position:relative;overflow:hidden;}
    .profile-avatar img{width:100%;height:100%;object-fit:cover;}
    .tab-content{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border:1px solid #eee;}
    .nav-tabs{background:rgba(0,0,0,0.1);border:none!important;border-radius:12px;padding:4px 8px;margin-bottom:24px;display:flex;gap:4px;box-shadow:0 2px 6px rgba(0,0,0,0.05);}
    .nav-tabs .nav-link{flex:1;border:none;margin:0 4px;padding:10px 20px;font-weight:600;color:#555;border-radius:50px;text-align:center;}
    .nav-tabs .nav-link.active{background:#fff;color:#000;box-shadow:0 2px 6px rgba(0,0,0,0.08);}
    .form-control{background-color:#fff!important;border:1px solid #dee2e6;border-radius:8px;padding:12px 16px;font-size:15px;color:#333;}
    .form-control:focus{border-color:#0d6efd!important;box-shadow:0 0 0 3px rgba(13,110,253,0.15)!important;}
    .input-group-text{background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px 0 0 8px;color:#555;}
    label.form-label{font-size:14px;font-weight:500;color:#444;margin-bottom:6px;}
    .notify-box{background:#f8f9fa;border-radius:8px;padding:16px;margin-bottom:16px;border:1px solid #eee;}
    .btn-save{background:#000;color:#fff;padding:10px 24px;border-radius:8px;font-weight:600;}
    .btn-save:hover{background:#333;transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,0.15);}
    textarea.form-control{resize:none;min-height:120px;}
</style>
</head>
<body>

<div class="container">
    <div class="profile-wrapper mt-4">
        <h4 class="fw-bold mb-2">My Profile</h4>
        <p class="text-muted mb-4">Manage your personal information and account settings</p>
    </div>

    <!-- Main Profile Form (wraps all tabs) -->
    <form id="profileForm" method="POST" action="/e_rentalHub/dashboards/save_profile.php" enctype="multipart/form-data" class="profile-form">

        <div class="profile-card">
            <div class="d-flex align-items-center">
                <!-- Profile Picture -->
                <div class="position-relative">
                    <div class="profile-avatar">
                        <?php
                        if (!empty($landlord['profile_picture']) && file_exists("../../uploads/" . $landlord['profile_picture'])) {
                            echo "<img src='../../uploads/" . htmlspecialchars($landlord['profile_picture']) . "' alt='profile'>";
                        } else {
                            $firstName = $landlord['first_name'] ?? '';
                            $lastName = $landlord['last_name'] ?? '';
                            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                            echo $initials ?: 'LN';
                        }
                        ?>
                    </div>

                    <!-- CAMERA ICON TRIGGERS THE FILE INPUT -->
                    <label class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-2 camera-btn cursor-pointer" onclick="document.getElementById('profile_picture_input').click()">
                        <i class="bi bi-camera text-white"></i>
                    </label>
                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" class="d-none">
                </div>

                <!-- LANDLORD INFO -->
                <div class="ms-4">
                    <h5 class="mb-1"><?= htmlspecialchars($landlord['first_name'] ?? 'Landlord') ?> <?= htmlspecialchars($landlord['last_name'] ?? 'Name') ?></h5>
                    <p class="text-muted mb-1"><?= htmlspecialchars($landlord['email'] ?? 'email@example.com') ?></p>
                    <p class="text-muted mb-0"><?= htmlspecialchars($landlord['business_name'] ?? 'Business Name') ?></p>
                </div>

                <div class="ms-auto">
                    <button type="button" class="btn btn-save" onclick="submitProfileForm()">Save Changes</button>
                </div>
            </div>
        </div>

        <!--Tabs-->
        <ul class="nav nav-tabs border-0 mb-4" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">Personal</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="business-tab" data-bs-toggle="tab" data-bs-target="#business" type="button" role="tab">Business</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">Payment</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">Security</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">Notifications</button>
            </li>
        </ul>

        <!--Tab Content-->
        <div class="tab-content mb-5">
            <!-- PERSONAL -->
            <div class="tab-pane fade show active" id="personal">
                <h5 class="fw-bold mb-3">Personal Information</h5>
                <p class="text-muted mb-4">Update your personal details and contact information</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($landlord['first_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($landlord['last_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($landlord['email'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Primary Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($landlord['phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Alternate Phone</label>
                        <input type="text" class="form-control" name="alt_phone" value="<?= htmlspecialchars($landlord['alt_phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($landlord['address'] ?? '') ?>">
                    </div>

                    <div class="col-4">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($landlord['city'] ?? '') ?>">
                    </div>

                    <div class="col-4">
                        <label class="form-label">County</label>
                        <input type="text" class="form-control" name="county" value="<?= htmlspecialchars($landlord['county'] ?? '') ?>">
                    </div>

                    <div class="col-4">
                        <label class="form-label">Postal Code</label>
                        <input type="text" class="form-control" name="postal_code" value="<?= htmlspecialchars($landlord['postal_code'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">About Me</label>
                        <textarea class="form-control" name="about_me" rows="3"><?= htmlspecialchars($landlord['about_me'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- BUSINESS -->
            <div class="tab-pane fade" id="business">
                <div class="card border-0 shadow-sm p-4 rounded-4" style="background:#fff;">
                    <h5 class="fw-bold mb-2">Business Information</h5>
                    <p>Manage your business details and registration information</p>

                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Business Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-building"></i></span>
                                <input type="text" class="form-control" name="business_name" value="<?= htmlspecialchars($landlord['business_name'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tax ID / PIN</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-file-earmark-text"></i></span>
                                <input type="text" class="form-control" name="tax_id" value="<?= htmlspecialchars($landlord['tax_id'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Business Registration</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-123"></i></span>
                                <input type="text" class="form-control" name="registration_number" value="<?= htmlspecialchars($landlord['registration_number'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4 rounded-3">
                        <strong>Note:</strong> Business registration details help students trust your listings and are often required for compliance.
                    </div>
                </div>
            </div>

            <!-- PAYMENT -->
            <div class="tab-pane fade" id="payment">
                <h5 class="fw-bold mb-2">Payment Information</h5>
                <p class="text-muted mb-4">Configure how you receive payments</p>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Preferred Payment Method</label>
                    <select class="form-select" name="payment_method" id="paymentMethodSelect">
                        <option value="Bank Transfer" <?= ($payment['payment_method'] ?? '') == 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="M-Pesa" <?= ($payment['payment_method'] ?? '') == 'M-Pesa' ? 'selected' : '' ?>>M-Pesa</option>
                        <option value="PayPal" <?= ($payment['payment_method'] ?? '') == 'PayPal' ? 'selected' : '' ?>>PayPal</option>
                        <option value="Cash" <?= ($payment['payment_method'] ?? '') == 'Cash' ? 'selected' : '' ?>>Cash</option>
                    </select>
                </div>

                <div id="bankFields" class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Bank Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-bank"></i></span>
                            <input type="text" class="form-control" name="bank_name" value="<?= htmlspecialchars($payment['bank_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Account Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-credit-card-2-back"></i></span>
                            <input type="text" class="form-control" name="account_number" value="<?= htmlspecialchars($payment['account_number'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Account Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-person-badge"></i></span>
                            <input type="text" class="form-control" name="account_name" value="<?= htmlspecialchars($payment['account_name'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div id="mpesaFields" class="row g-3 d-none">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">M-Pesa Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-phone"></i></span>
                            <input type="text" class="form-control" name="mpesa_number" value="<?= htmlspecialchars($payment['mpesa_number'] ?? $payment['account_number'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Account Name (optional)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="account_name_mpesa" value="<?= htmlspecialchars($payment['account_name'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div id="paypalFields" class="row g-3 d-none">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">PayPal Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" name="paypal_email" value="<?= htmlspecialchars($payment['paypal_email'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div id="cashFields" class="row g-3 d-none">
                    <div class="col-12">
                        <div class="alert alert-info">Cash selected — no additional details required.</div>
                    </div>
                </div>

                <div class="alert alert-warning mt-4 rounded-3">
                    <strong>Security:</strong> Your payment information is stored securely. Only share sensitive details when necessary.
                </div>
            </div>

            <!-- SECURITY -->
            <div class="tab-pane fade" id="security">
                <h5 class="fw-bold mb-2">Security Settings</h5>
                <p class="text-muted mb-4">Manage your account and password security.</p>

                <div class="card border-0 shadow-sm p-4 rounded-4 mb-3" style="background:#fff;">
                    <!-- The form tag was removed from here. The inputs are now part of the main #profileForm -->
                    <div class="row g-4" id="changePasswordSection">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="current_password" placeholder="Enter current password">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" class="form-control" name="new_password" placeholder="Enter new password">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-check2-circle"></i></span>
                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password">
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-dark fw-semibold px-4 py-2" onclick="submitPasswordForm()"><i class="bi bi-arrow-repeat me-2"></i>Change Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="tab-pane fade" id="notifications">
                <h5 class="fw-bold mb-2">Notification Settings</h5>
                <p class="text-muted mb-4">Choose how you want to receive updates and alerts.</p>

                <div class="notify-box d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-envelope me-2"></i>Email - New Bookings</h6>
                        <p class="text-muted small mb-0">Get notified about new bookings via email.</p>
                    </div>
                    <div class="form-check form-switch fs-4">
                        <input class="form-check-input" type="checkbox" name="email_new_bookings" <?= ($notifications['email_new_bookings'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="notify-box d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-envelope me-2"></i>Email - New Messages</h6>
                        <p class="text-muted small mb-0">Get notified when students send inquiries</p>
                    </div>
                    <div class="form-check form-switch fs-4">
                        <input class="form-check-input" type="checkbox" name="email_new_messages" <?= ($notifications['email_new_messages'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="notify-box d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-telephone me-2"></i>SMS - Booking Confirmations</h6>
                        <p class="text-muted small mb-0">Receive SMS alerts for confirmed bookings.</p>
                    </div>
                    <div class="form-check form-switch fs-4">
                        <input class="form-check-input" type="checkbox" name="sms_booking_confirmations" <?= ($notifications['sms_booking_confirmations'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="notify-box d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-telephone me-2"></i>SMS - Payment Updates</h6>
                        <p class="text-muted small mb-0">Get SMS when payments are received.</p>
                    </div>
                    <div class="form-check form-switch fs-4">
                        <input class="form-check-input" type="checkbox" name="sms_payment_updates" <?= ($notifications['sms_payment_updates'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="alert alert-info mt-4 rounded-3 small">
                    <i class="bi bi-bell-fill me-2 fs-5"></i>
                    <span>You can update your notification preferences at any time. We respect your privacy and will not share your contact details.</span>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-dark fw-semibold px-4 py-2"> Save Changes</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Submit profile form via AJAX
function submitPasswordForm() {
    const form = document.getElementById('profileForm');
    const formData = new FormData();

    // Only gather password fields for this action
    formData.append('current_password', form.querySelector('[name="current_password"]').value);
    formData.append('new_password', form.querySelector('[name="new_password"]').value);
    formData.append('confirm_password', form.querySelector('[name="confirm_password"]').value);
    formData.append('update_password_action', '1'); // Action flag

    fetch('../actions/update_password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Password updated successfully!');
            // Clear password fields
            form.querySelector('[name="current_password"]').value = '';
            form.querySelector('[name="new_password"]').value = '';
            form.querySelector('[name="confirm_password"]').value = '';
        } else {
            alert('Error: ' + (data.message || 'Failed to update password.'));
        }
    })
    .catch(error => {
        console.error('Password Update Error:', error);
        alert('An error occurred while updating the password.');
    });
}


function submitProfileForm() {
    const form = document.getElementById('profileForm');
    const formData = new FormData(form);
    
    // Debug: log what we're sending
    console.log('Form data keys:', Array.from(formData.keys()));
    
    fetch('/e_rentalHub/dashboards/save_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status, 'OK:', response.ok);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            const data = JSON.parse(text);
            console.log('Parsed JSON:', data);
            if (data.success) {
                alert(data.message || 'Profile saved successfully!');
                // Optionally refresh the page to show updated values
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to save profile'));
            }
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            console.log('Raw response:', text);
            alert('An error occurred while saving the profile (JSON parse error). Check console for details.');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        console.error('Error stack:', error.stack);
        alert('An error occurred while saving the profile: ' + error.message);
    });
}

// Auto-submit when profile image is picked
const profilePictureInput = document.getElementById('profile_picture_input');
if (profilePictureInput) {
    profilePictureInput.addEventListener('change', function(){
        submitProfileForm();
    });
}

// Payment fields toggle
function initPaymentToggle() {
    const select = document.getElementById('paymentMethodSelect');
    if (!select) {
        console.warn('paymentMethodSelect not found');
        return;
    }

    const bankFields = document.getElementById('bankFields');
    const mpesaFields = document.getElementById('mpesaFields');
    const paypalFields = document.getElementById('paypalFields');
    const cashFields = document.getElementById('cashFields');

    if (!bankFields || !mpesaFields || !paypalFields || !cashFields) {
        console.warn('One or more payment field containers not found');
        return;
    }

    function updatePaymentFields() {
        const v = select.value;
        console.log('Payment method selected:', v);
        // Hide all first (use class d-none so Bootstrap styles don't override)
        bankFields.classList.add('d-none');
        mpesaFields.classList.add('d-none');
        paypalFields.classList.add('d-none');
        cashFields.classList.add('d-none');

        // Remove required from all first
        document.querySelectorAll('#bankFields input').forEach(i => i.required = false);
        document.querySelectorAll('#mpesaFields input').forEach(i => i.required = false);
        document.querySelectorAll('#paypalFields input').forEach(i => i.required = false);

        // Show the selected one and mark its inputs required where appropriate
        if (v === 'Bank Transfer') {
            bankFields.classList.remove('d-none');
            document.querySelectorAll('#bankFields input').forEach(i => i.required = true);
        } else if (v === 'M-Pesa') {
            mpesaFields.classList.remove('d-none');
            document.querySelectorAll('#mpesaFields input').forEach(i => i.required = true);
        } else if (v === 'PayPal') {
            paypalFields.classList.remove('d-none');
            document.querySelectorAll('#paypalFields input').forEach(i => i.required = true);
        } else if (v === 'Cash') {
            cashFields.classList.remove('d-none');
        }
    }

    select.addEventListener('change', updatePaymentFields);
    updatePaymentFields();
    console.log('Payment toggle initialized');
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPaymentToggle);
} else {
    initPaymentToggle();
}
</script>

</body>
</html>
