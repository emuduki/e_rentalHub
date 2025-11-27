<?php
session_start();
// helpful debug flags
ini_set('display_errors', 1);
error_reporting(E_ALL);
include("../../config/db.php");

$uploadsBaseUrl = "/e_rentalHub/uploads/";

// Debug endpoint to inspect session / DB quickly
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "DEBUG my_bookings.php\n";
    echo "SESSION: " . json_encode($_SESSION) . "\n";
    echo "ROLE: " . ($_SESSION['role'] ?? 'NULL') . "\n";

    // site sanity
    echo "PHP Version: " . phpversion() . "\n";

    // quick DB test
    $test = $conn->query("SELECT COUNT(*) AS c FROM properties LIMIT 1");
    if ($test) {
        $row = $test->fetch_assoc();
        echo "Properties count (sample): " . ($row['c'] ?? 'N/A') . "\n";
    } else {
        echo "DB error: " . $conn->error . "\n";
    }

    exit();
}

// ensure logged in student
$role = strtolower(trim($_SESSION['role'] ?? ''));
if ($role !== 'student') {
    echo "<p class='text-danger'>Please log in as a student to view your bookings.</p>";
    exit();
}

$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id) {
    echo "<p class='text-danger'>Unauthorized</p>";
    exit();
}

$tab = strtolower(trim($_GET['tab'] ?? 'active'));
$activeStatuses = ['pending','confirmed'];
$pastStatuses = ['completed','cancelled'];

if ($tab === 'past') {
    $statuses = $pastStatuses;
} else {
    $tab = 'active';
    $statuses = $activeStatuses;
}

// Build safe IN list from known statuses
$inList = "('" . implode("','", $statuses) . "')";

$sql = "SELECT r.id AS reservation_id, r.check_in_date, r.lease_length, r.amount AS reservation_amount, r.status, r.created_at, r.property_id,
               p.title AS property_title, p.rent AS property_rent, CONCAT_WS(', ', p.address, p.city) AS property_location,
               (SELECT pi.image_path FROM property_images pi WHERE pi.property_id = p.id ORDER BY pi.id DESC LIMIT 1) AS image_path,
               l.id AS landlord_id, l.first_name AS landlord_first, l.last_name AS landlord_last, l.phone AS landlord_phone
        FROM reservations r
        JOIN properties p ON r.property_id = p.id
        LEFT JOIN landlords l ON p.landlord_id = l.id
        WHERE r.student_id = ? AND r.status IN $inList
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<p class='text-danger'>Database error: " . htmlspecialchars($conn->error) . "</p>";
    exit();
}
$stmt->bind_param("i", $student_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<style>
    .booking-card { border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.05); background:#fff; padding:18px; margin-bottom:20px; }
    .booking-img { width:160px; height:120px; object-fit:cover; border-radius:8px; }
    .booking-title { font-size:1.125rem; font-weight:600; }
    .booking-badge { font-weight:600; text-transform:none; }
    .booking-row { display:flex; gap:18px; align-items:flex-start; }
    .booking-actions .btn { margin-right:10px; }
    .small-muted { color:#6c757d; }
</style>

<div class="container mt-5 pt-4">


    <h3 class="fw-bold">My Bookings</h3>
    <p class="text-muted">View and manage your property bookings</p>

    <div class="mb-3">
        <a href="?tab=active" data-tab="active" class="tab-btn btn btn-sm <?= $tab==='active' ? 'btn-outline-primary' : 'btn-light' ?>">Active Bookings</a>
        <a href="?tab=past" data-tab="past" class="tab-btn btn btn-sm <?= $tab==='past' ? 'btn-outline-primary' : 'btn-light' ?>">Past Bookings</a>
    </div>
</div>

<?php if (empty($bookings)): ?>
    <?php if ($tab === 'past'): ?>
        <div class="text-center text-muted">No past bookings found.</div>
    <?php else: ?>
        <div class="text-center text-muted">No active bookings found.</div>
    <?php endif; ?>
<?php else: ?>
    <?php foreach ($bookings as $b):
        $title = htmlspecialchars($b['property_title'] ?? 'Property');
        $location = htmlspecialchars($b['property_location'] ?? '');
        $imgPath = $b['image_path'] ? $uploadsBaseUrl . $b['image_path'] : ($uploadsBaseUrl . 'placeholder-property.jpg');
        $status = htmlspecialchars(ucfirst($b['status']));
        $checkIn = htmlspecialchars($b['check_in_date']);
        $lease = intval($b['lease_length']);
        $checkOut = ($checkIn && $lease>0) ? date('Y-m-d', strtotime("+$lease months", strtotime($checkIn))) : '—';
        $landlordName = trim(($b['landlord_first'] ?? '') . ' ' . ($b['landlord_last'] ?? '')) ?: 'Landlord';
        $landlordPhone = htmlspecialchars($b['landlord_phone'] ?? '');

        // amount handling
        $amount = (isset($b['reservation_amount']) && floatval($b['reservation_amount'])>0) ? $b['reservation_amount'] : ($b['property_rent'] ?? 0);
        $months = $lease>0 ? $lease : 1;
        $total = floatval($amount) * $months;
    ?>

    <div class="booking-card">
        <div class="booking-row">
            <div class="flex-shrink-0">
                <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= $title ?>" class="booking-img" onerror="this.onerror=null;this.src='<?= $uploadsBaseUrl ?>placeholder-property.jpg'">
            </div>

            <div style="flex:1;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="booking-title"><?= $title ?></div>
                        <div class="small-muted"><?= $location ?></div>
                    </div>

                    <div class="text-end">
                        <span class="badge bg-success booking-badge"><?= $status ?></span>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="small-muted">Check-in</div>
                        <div><?= $checkIn ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-muted">Check-out</div>
                        <div><?= $checkOut ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-muted">Payment Details</div>
                        <div>KES <?= number_format((float)$amount,0) ?>/month</div>
                        <div class="small-muted">Total: KES <?= number_format((float)$total,0) ?> (<?= $months ?> <?= $months>1? 'months':'month' ?>)</div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="small-muted">Landlord</div>
                        <div class="fw-semibold"><?= htmlspecialchars($landlordName) ?></div>
                        <?php if ($landlordPhone): ?>
                            <div class="small-muted">+<?= htmlspecialchars($landlordPhone) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6 booking-actions text-md-end mt-2">
                        <a href="/e_rentalHub/houses/view.php?id=<?= intval($b['property_id']) ?>" class="btn btn-outline-secondary btn-sm">Contact Landlord</a>
                        <a href="#" class="btn btn-outline-secondary btn-sm">Download Contract</a>
                        <a href="#" class="btn btn-link text-danger btn-sm">Request Cancellation</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endforeach; ?>
<?php endif; ?>

<script>
// Tab buttons should reload this same section using AJAX so the parent dashboard container stays intact.
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        const tab = this.dataset.tab;

        // Visual feedback while loading
        this.classList.remove('btn-light');
        this.classList.add('btn-outline-primary');

        const other = document.querySelector('.tab-btn[data-tab!="' + tab + '"]');
        if (other) {
            other.classList.remove('btn-outline-primary');
            other.classList.add('btn-light');
        }

        // Fetch the new content for the selected tab and replace the dashboard content container
        fetch('/e_rentalHub/dashboards/sections/my_bookings.php?tab=' + encodeURIComponent(tab), { credentials: 'same-origin' })
            .then(r => {
                if (!r.ok) throw new Error('Status ' + r.status);
                return r.text();
            })
            .then(html => {
                // Replace the dashboard's main content area
                const container = document.getElementById('content');
                if (container) {
                    container.innerHTML = html;
                    // run any inline scripts inside the injected HTML
                    Array.from(container.querySelectorAll('script')).forEach(old => {
                        const newScript = document.createElement('script');
                        if (old.src) newScript.src = old.src; else newScript.text = old.textContent;
                        document.body.appendChild(newScript);
                        document.body.removeChild(newScript);
                    });
                }
            })
            .catch(err => {
                console.error('Failed to load bookings tab:', err);
                alert('Failed to load bookings. Please try again.');
            });
    });
});
</script>

<?php
// end file
?>
