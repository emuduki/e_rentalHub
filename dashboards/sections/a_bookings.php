<?php
session_start();
include("../../config/db.php");

// Ensure logged in admin
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'admin') {
    echo "<div class='alert alert-danger'>Unauthorized access. Admin only.</div>";
    exit();
}

$sql = "
SELECT r.id, r.property_id, r.student_id, r.landlord_id, r.check_in_date, r.lease_length, r.status, r.created_at,
       p.title AS property,
       p.rent AS amount,
       COALESCE(s.full_name, u_student.username) AS student_name,
       COALESCE(CONCAT(TRIM(l.first_name), ' ', TRIM(l.last_name)), u_landlord.username) AS landlord_name
FROM reservations r
LEFT JOIN properties p ON p.id = r.property_id
LEFT JOIN users u_student ON u_student.user_id = r.student_id
LEFT JOIN students s ON s.user_id = r.student_id
LEFT JOIN landlords l ON l.id = r.landlord_id
LEFT JOIN users u_landlord ON u_landlord.user_id = l.user_id
WHERE r.id IS NOT NULL
ORDER BY r.created_at DESC
";

$result = $conn->query($sql);
if (!$result) {
    error_log("a_bookings.php: bookings query failed: " . $conn->error);
    echo "<div class='alert alert-danger'>Database query failed: " . htmlspecialchars($conn->error) . "</div>";
    $result = false;
} else {
    error_log("a_bookings.php: bookings query succeeded, rows=" . $result->num_rows);
    echo "<!-- Debug: Query succeeded, rows: " . $result->num_rows . " -->";
    if ($result->num_rows == 0) {
        echo "<p style='color:orange;'>No bookings found in the database.</p>";
        // Check if reservations table has any data
        $check_sql = "SELECT COUNT(*) as total FROM reservations";
        $check_result = $conn->query($check_sql);
        if ($check_result) {
            $total = $check_result->fetch_assoc()['total'];
            echo "<p style='color:blue;'>Total reservations in table: " . $total . "</p>";
        }
    }
}
?>

<style>
    .table th:nth-child(2), .table td:nth-child(2) { min-width: 200px; } /* Property name */
</style>

<div class="container-fluid" style="padding-top: 56px;">
    <h3 class="fw-bold">Bookings Management</h3>
    <p class="text-muted">View and manage all bookings across the platform</p>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="search" class="form-control" placeholder="Search bookings...">
        </div>
        <div class="col-md-3">
            <select class="form-select">
                <option value="">All Status</option>
                <option>pending</option>
                <option>confirmed</option>
                <option>cancelled</option>
                <option>completed</option>
            </select>
        </div>
        <div class="col-md-2 text-end">
            <button class="btn btn-outline-secondary">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table align-middle">
            <thead class="table-light">
                <tr>
                    <th style="white-space: nowrap;">Booking ID</th>
                    <th>Property</th>
                    <th style="min-width: 150px; white-space: nowrap;">Student</th>
                    <th style="min-width: 100px; white-space: nowrap;">Landlord</th>
                    <th style="white-space: nowrap;">Check-In</th>
                    <th style="min-width: 120px; text-align: center;">Lease Length</th>
                    <th style="min-width: 150px; white-space: nowrap;">Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>

            <?php if ($result): while($b = $result->fetch_assoc()): ?>
            <tr>
                <td style="white-space: nowrap;">B00<?= $b['id'] ?></td>

                <td><?= htmlspecialchars($b['property'] ?? 'N/A') ?></td>

                <td style="white-space: nowrap;"><?= htmlspecialchars($b['student_name'] ?? 'N/A') ?></td>

                <td style="white-space: nowrap;"><?= htmlspecialchars($b['landlord_name'] ?? 'N/A') ?></td>

                <td style="white-space: nowrap;"><?= htmlspecialchars($b['check_in_date'] ?? 'N/A') ?></td>

                <td style="text-align: center;"><?= $b['lease_length'] ?? 0 ?> days</td>

                <td>KES <?= number_format($b['amount'] ?? 0) ?></td>

                <td>
                    <?php
                        $badge = [
                            'pending'=>'warning',
                            'confirmed'=>'primary',
                            'cancelled'=>'danger',
                            'completed'=>'success'
                        ][$b['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $badge ?>"><?= $b['status'] ?></span>
                </td>

                <td>
                    <button class="btn btn-light btn-sm">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; endif; ?>

            </tbody>
        </table>
    </div>
</div>
