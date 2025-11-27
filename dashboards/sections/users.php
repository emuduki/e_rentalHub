<?php 
session_start();
include(__DIR__ . "/../../config/db.php");

// Fetch students with their booking count
$students = $conn->query("
    SELECT 
        s.id AS student_id,
        u.username,
        u.email,
        s.phone,
        u.created_at,
        0 AS booking_count,
        1 AS status
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.id DESC
");

if (!$students) {
    error_log("users.php: students query failed: " . $conn->error);
    // ensure variable is null so template shows 'No students found' instead of throwing
    $students = null;
} else {
    error_log("users.php: students query succeeded, rows=" . $students->num_rows);
}

// Fetch landlords with their property count
// This query joins users -> landlords -> properties to correctly count properties.
$landlords = $conn->query("
    SELECT l.id AS landlord_id,
           l.user_id AS user_id,
           COALESCE(CONCAT(TRIM(l.first_name), ' ', TRIM(l.last_name)), u.username) AS name,
           COALESCE(l.email, u.email) AS email,
           l.phone AS phone,
           l.business_name,
           l.created_at,
           COUNT(p.id) AS property_count,
           1 AS status
    FROM landlords l
    JOIN users u ON u.user_id = l.user_id
    LEFT JOIN properties p ON l.id = p.landlord_id
    GROUP BY l.id
");

// Debug: log if the landlords query failed or returned zero rows
if (!$landlords) {
    error_log("users.php: landlords query failed: " . $conn->error);
} else {
    error_log("users.php: landlords query succeeded, rows=" . $landlords->num_rows);
}

// Fetch landlords with their properties for detailed view
$landlords_with_properties = [];
$result = $conn->query("
    SELECT l.id AS landlord_id, l.user_id AS user_id, COALESCE(CONCAT(TRIM(l.first_name), ' ', TRIM(l.last_name)), u.username) AS name,
        COALESCE(l.email, u.email) AS email, l.phone AS phone, l.created_at, 1 AS verified,
        p.id AS property_id, p.title AS property_title, p.rent, p.address
    FROM landlords l
    JOIN users u ON u.user_id = l.user_id
    LEFT JOIN properties p ON l.id = p.landlord_id
    ORDER BY l.id, p.id");

if (!$result) {
    error_log("users.php: detailed query failed: " . $conn->error);
} else {
    error_log("users.php: detailed query succeeded, rows=" . $result->num_rows);
}

// Organize data by landlord
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lid = $row['landlord_id'];
        if (!isset($landlords_with_properties[$lid])) {
            $landlords_with_properties[$lid] = [
                'landlord_id' => $row['landlord_id'],
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'created_at' => $row['created_at'],
                'verified' => $row['verified'],
                'properties' => []
            ];
        }
        if ($row['property_id']) {
            $landlords_with_properties[$lid]['properties'][] = [
                'id' => $row['property_id'],
                'title' => $row['property_title'],
                'rent' => $row['rent'],
                'address' => $row['address']
            ];
        }
    }
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    :root{
        --text-dark:#1a1a1a;
        --text-muted:#6c6f73;
        --border:#e5e7eb;
        --card-bg:#ffffff;
        --bg:#f5f6fa;
        --accent:#0d6efd;
        --radius:12px;
    }

    body{
        background:var(--bg);
        font-family:system-ui, -apple-system, "Segoe UI", Roboto;
        padding: 56px;
    }

    /* Page title */
    h4.fw-bold{
        margin-top:4px;
        font-size:26px;
    }

    /* --- TOGGLE (Landlords | Students) --- */
    .tab-btn{
        border:0;
        padding:8px 18px;
        border-radius:50px;
        background:#f0f1f3;
        color:#333;
        font-weight:600;
        font-size:14px;
        transition:0.2s;
        cursor: pointer;
        display: inline-block;
        user-select: none;
        pointer-events: auto;
    }
    .tab-btn.active{
        background:var(--card-bg);
        border:1px solid var(--border);
        box-shadow:0 1px 3px rgba(0,0,0,0.08);
    }

    /* Search bar */
    .search-group .form-control{
        border-radius:50px;
        height:42px;
        padding-left:40px;
        border:1px solid var(--border);
    }
    .search-group .bi{
        position:absolute;
        left:14px;
        top:10px;
        color:var(--text-muted);
    }

    /* --- MAIN WHITE CARD --- */
    .table-card{
        background:var(--card-bg);
        border:1px solid var(--border);
        border-radius:var(--radius);
        padding:0;
        overflow:hidden;
        box-shadow:0 1px 3px rgba(0,0,0,0.06);
    }

    /* Table Header */
    .table thead th{
        background:#fafafa;
        color:#555;
        font-size:13px;
        font-weight:600;
        text-transform:uppercase;
        letter-spacing:0.3px;
        padding:14px;
        border-bottom:1px solid var(--border);
    }

    /* Rows */
    .table td{
        padding:15px 14px;
        color:#222;
    }

    .table tbody tr:hover{
        background:#f9fafb;
    }

    /* Status badges */
    .status-badge{
        padding:5px 10px;
        border-radius:50px;
        color:#fff;
        font-size:12px;
        font-weight:600;
    }
    .status-badge.verified{ background:#27ae60 }
    .status-badge.pending{ background:#f39c12 }
    .status-badge.suspended{ background:#e74c3c }

    /* Action buttons */
    .btn-warning, .btn-danger{
        padding:5px 10px;
        font-size:12px;
        border-radius:6px;
    }

    /* Modal styling */
    .list-group-item{
        border:1px solid var(--border);
        border-radius:var(--radius);
        margin-bottom:10px;
    }
    .list-group-item h6{
        font-size:15px;
    }

    /* Export button */
    .export-btn{
        border-radius:8px;
    }

    /* Tables scroll on small screens */
    .table-card{
        overflow-x:auto;
    }

    /* Ensure radio buttons work with labels */
    .btn-check {
        display: none;
    }
    
    .btn-check:checked + .tab-btn {
        background:var(--card-bg);
        border:1px solid var(--border);
        box-shadow:0 1px 3px rgba(0,0,0,0.08);
    }

</style>
</head>
<body>

    <h4 class="fw-bold">Users Management</h4>
    <p class="text-muted">Manage landlords and students on the platform</p>

    <!--Toggle (use radios+labels for reliable clicks) -->
    <div class="d-flex gap-2 mb-3" id="usersToggleWrapper">
        <div role="tablist" aria-label="Users toggle">
                <input type="radio" class="btn-check" name="usersToggle" id="toggleLandlords" autocomplete="off" checked>
                <label class="tab-btn active" for="toggleLandlords" id="lblLandlords" role="button" tabindex="0">Landlords</label>

                <input type="radio" class="btn-check" name="usersToggle" id="toggleStudents" autocomplete="off">
                <label class="tab-btn" for="toggleStudents" id="lblStudents" role="button" tabindex="0">Students</label>
        </div>
    </div>

    <!-- Search + Status -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="position-relative search-group" style="width:48%">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" placeholder="Search users...">
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select class="form-select" style="width:200px">
                <option>All Status</option>
                <option>Active</option>
                <option>Pending</option>
                <option>Suspended</option>
            </select>
            <button class="btn btn-outline-secondary export-btn"><i class="bi bi-download"></i> Export</button>
        </div>
    </div>

    <!--LANDLORDS TABLE (DEFAULT) -->
    <div id="tableLandlords" class="table-card">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="min-width:120px">ID</th>
                    <th style="min-width:200px">Name</th>
                    <th style="min-width:220px">Email</th>
                    <th style="min-width:160px">Phone</th>
                    <th style="min-width:120px">Join Date</th>
                    <th style="min-width:120px">Properties</th>
                    <th style="min-width:120px">Status</th>
                    <th style="min-width:140px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($landlords && $landlords->num_rows > 0): ?>
                    <?php while($row = $landlords->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= 'L' . str_pad(htmlspecialchars($row['landlord_id']), 3, '0', STR_PAD_LEFT) ?></strong></td>
                            <td><?= htmlspecialchars($row['name'] ?? $row['username'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                            <td><?= ($row['created_at'] ? date('n/j/Y', strtotime($row['created_at'])) : 'N/A') ?></td>
                            <td>
                                <span class="badge bg-info"><?= $row['property_count'] ?></span>
                                <button class="btn btn-sm btn-link" onclick="showLandlordProperties(<?= $row['landlord_id'] ?>)">View</button>
                            </td>
                            <td>
                                <span class="status-badge <?= ($row['status'] == 1 ? 'verified' : 'pending') ?>">
                                    <?= ($row['status'] == 1 ? 'Verified' : 'Pending') ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning">Edit</button>
                                <button class="btn btn-sm btn-danger">Block</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted">No landlords found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Properties Modal for Landlord -->
    <div class="modal fade" id="propertiesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Landlord Properties</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="propertiesContent">
                    <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- STUDENTS TABLE (HIDDEN BY DEFAULT) -->
    <div id="tableStudents" class="table-card" style="display:none;">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th><th>Username</th><th>Email</th><th>Phone</th><th>Join Date</th><th>Bookings</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students && $students->num_rows > 0): ?>
                    <?php while($row = $students->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= 'S' . str_pad(htmlspecialchars($row['student_id']), 3, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= htmlspecialchars($row['username'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['created_at'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge bg-success"><?= $row['booking_count'] ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?= ($row['status'] == 1 ? 'verified' : 'pending') ?>">
                                <?= ($row['status'] == 1 ? 'Verified' : 'Pending') ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning">Edit</button>
                            <button class="btn btn-sm btn-danger">Block</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted">No students found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Inject PHP data
    const landlordsData = <?= json_encode($landlords_with_properties, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

    function showTab(tab) {
        console.log('showTab called with:', tab);
        
        var lblL = document.getElementById("lblLandlords");
        var lblS = document.getElementById("lblStudents");
        var tableLandlords = document.getElementById("tableLandlords");
        var tableStudents = document.getElementById("tableStudents");
        
        if (lblL) lblL.classList.remove("active");
        if (lblS) lblS.classList.remove("active");

        if (tab === "landlords") {
            if (lblL) lblL.classList.add("active");
            if (tableLandlords) tableLandlords.style.display = "block";
            if (tableStudents) tableStudents.style.display = "none";
            var r = document.getElementById('toggleLandlords');
            if (r) r.checked = true;
        } else if (tab === "students") {
            if (lblS) lblS.classList.add("active");
            if (tableStudents) tableStudents.style.display = "block";
            if (tableLandlords) tableLandlords.style.display = "none";
            var r = document.getElementById('toggleStudents');
            if (r) r.checked = true;
        }
    }

    function showLandlordProperties(landlordId) {
        const modalEl = document.getElementById('propertiesModal');
        const modal = new bootstrap.Modal(modalEl);
        const content = document.getElementById('propertiesContent');
        
        // Find landlord in the data object
        const landlord = landlordsData[landlordId];
        
        if (landlord && landlord.properties && landlord.properties.length > 0) {
            let html = '<div class="list-group">';
            landlord.properties.forEach(prop => {
                html += `
                    <div class="list-group-item">
                        <h6 class="mb-1">${prop.title}</h6>
                        <p class="mb-1"><small class="text-muted">${prop.address}</small></p>
                        <p class="mb-0"><strong>KES ${new Intl.NumberFormat().format(prop.rent)}</strong> / month</p>
                    </div>
                `;
            });
            html += '</div>';
            content.innerHTML = html;
        } else {
            content.innerHTML = '<p class="text-muted">No properties listed for this landlord.</p>';
        }
        
        modal.show();
    }

    // Initialize listeners immediately
    (function(){
        // Radio button change listeners
        var rLand = document.getElementById('toggleLandlords');
        var rStud = document.getElementById('toggleStudents');
        
        if (rLand) {
            rLand.addEventListener('change', function(){
                if (this.checked) showTab('landlords');
            });
        }
        
        if (rStud) {
            rStud.addEventListener('change', function(){
                if (this.checked) showTab('students');
            });
        }

        // Label click listeners (for better UX if radios are hidden/styled)
        var lblL = document.getElementById('lblLandlords');
        var lblS = document.getElementById('lblStudents');
        
        if (lblL) {
            lblL.addEventListener('click', function(e){
                e.preventDefault();
                showTab('landlords');
            });
        }
        
        if (lblS) {
            lblS.addEventListener('click', function(e){
                e.preventDefault();
                showTab('students');
            });
        }
        
        // Default state
        showTab('landlords');
    })();
    </script>
</body>
</html>