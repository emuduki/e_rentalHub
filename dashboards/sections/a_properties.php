<?php
include("../../config/db.php");

$sql = "
SELECT p.*,
       CONCAT_WS(', ', p.city, p.address) AS location,
       l.first_name, l.last_name,
       COALESCE((SELECT pi.image_path FROM property_images pi
        WHERE pi.property_id = p.id ORDER BY pi.uploaded_at DESC LIMIT 1), 'placeholder-property.jpg') AS image,
       (SELECT COUNT(*) FROM reservations r WHERE r.property_id = p.id) AS bookings,
       0 AS views
FROM properties p
LEFT JOIN landlords l ON p.landlord_id = l.id
ORDER BY p.created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    error_log("a_properties.php: properties query failed: " . $conn->error);
    echo "<p style='color:red;'>Database query failed: " . $conn->error . "</p>";
    $result = null;
} else {
    error_log("a_properties.php: properties query succeeded, rows=" . $result->num_rows);
    echo "<!-- Debug: Query succeeded, rows: " . $result->num_rows . " -->";
}
?>

<style>
    table img { border-radius: 10px; }
    .table td { vertical-align: middle; }
    .badge { padding: 6px 10px; font-size: .85rem; }
    .table th:nth-child(2), .table td:nth-child(2) { min-width: 200px; } /* Property name */
    .table th:nth-child(3), .table td:nth-child(3) { min-width: 150px; } /* Landlord */
    .table th:nth-child(5), .table td:nth-child(5) { min-width: 120px; } /* Price */
</style>

<div class="container-fluid" style="padding-top: 56px;">
    <h3 class="fw-bold">Properties Management</h3>
    <p class="text-muted">Review and manage all property listings on the platform</p>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="search" class="form-control" placeholder="Search properties...">
        </div>
        <div class="col-md-3">
            <select class="form-select">
                <option value="">All Status</option>
                <option>Available</option>
                <option>Reserved</option>
                <option>Unavailable</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select">
                <option value="">All Types</option>
                <option>Apartment</option>
                <option>Studio</option>
                <option>Bedsitter</option>
                <option>Single Room</option>
                <option>House</option>
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
                    <th>Image</th>
                    <th>Property</th>
                    <th>Landlord</th>
                    <th>Location</th>
                    <th>Price</th>
                    <th>Type</th>
                    <th>Views</th>
                    <th>Bookings</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($p = $result->fetch_assoc()): ?>
            <tr>
                <td>
                    <img src="/e_rentalHub/uploads/<?= $p['image'] ?: 'placeholder-property.jpg' ?>"
                         width="60" height="45" style="object-fit:cover;border-radius:6px;">
                </td>

                <td>
                    <?= htmlspecialchars($p['title']) ?><br>
                    <small class="text-muted">ID: P00<?= $p['id'] ?></small>
                </td>

                <td><?= htmlspecialchars($p['first_name']." ".$p['last_name']) ?></td>

                <td><?= htmlspecialchars($p['city']) ?></td>

                <td>KES <?= number_format($p['rent']) ?></td>

                <td><?= htmlspecialchars($p['type']) ?></td>

                <td><?= intval($p['views']) ?></td>

                <td><?= intval($p['bookings']) ?></td>

                <td>
                    <?php
                        $status = $p['status'];
                        $badgeClass = [
                            'Available'   => 'success',
                            'Reserved'    => 'warning',
                            'Unavailable' => 'danger'
                        ][$status] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $badgeClass ?>"><?= $status ?></span>
                </td>

                <td>
                    <button class="btn btn-light btn-sm">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>
