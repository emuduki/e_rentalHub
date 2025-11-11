<?php
session_start();
include("../../config/db.php");

// ensure logged in landlord
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    header("Location: ../login.html");
    exit();
}
$landlord_id = $_SESSION['user_id'] ?? null;
if (!$landlord_id) {
    echo "Unauthorized";
    exit();
}

//Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['reservation_action'], $_POST['reservation_id'])){
    $action = $_POST['reservation_action'];
    $resId = intval($_POST['reservation_id']);

    if ($action === 'approve') {
        $newStatus = 'confirmed';
    } elseif ($action === 'reject') {
        $newStatus = 'cancelled';
    } else {
        $newStatus = null;
    }

    if ($newStatus) {
        $uStmt = $conn->prepare("
            UPDATE reservations r
            JOIN properties p ON r.property_id = p.id
            SET r.status = ?
            WHERE r.id = ? AND p.landlord_id = ?
        ");

        if (!$uStmt) {
            die(" SQL ERROR: " . $conn->error);
        }

        $uStmt->bind_param("sii", $newStatus, $resId, $landlord_id);

        $uSuccess = $uStmt->execute();
        $uStmt->close();
        if ($uSuccess) {
            $_SESSION['flash'] = "Reservation updated successfully.";
        } else {
            $_SESSION['flash'] = "Error updating reservation.";
        }

        // Redirect to avoid resubmission
        $statusQuery = $_GET['status'] ?? 'pending';
        header("Location: manage_reservations.php?status={$statusQuery}");
        exit();
    }
}

// Status filter from querystring (default pending)
$allowedStatuses = ['pending','confirmed','completed','cancelled'];
$status = strtolower(trim($_GET['status'] ?? 'pending'));
if (!in_array($status, $allowedStatuses)) $status = 'pending';

// fetch counts for tabs
$countStmt = $conn->prepare("
    SELECT r.status, COUNT(*) AS cnt
    FROM reservations r
    JOIN properties p ON r.property_id = p.id
    WHERE p.landlord_id = ?
    GROUP BY r.status
");

$countStmt->bind_param("i", $landlord_id);
$countStmt->execute();
$resCounts = $countStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$countStmt->close();

$counts = array_fill_keys(['pending','confirmed','completed','cancelled'], 0);
foreach ($resCounts as $row) {
    $s = strtolower($row['status']);
    if (isset($counts[$s])) $counts[$s] = (int)$row['cnt'];
}

// Banner only shows when pending > 0
$pendingCount = $counts['pending'];

// Fetch reservations for the selected tab (ordered newest first)
$listStmt = $conn->prepare("
    SELECT r.id, r.student_id,
           r.start_date, r.end_date,
           r.amount, r.status, r.created_at,
           p.title AS property_title
    FROM reservations r
    JOIN properties p ON r.property_id = p.id
    WHERE p.landlord_id = ? AND r.status = ?
    ORDER BY r.created_at DESC
");
$listStmt->bind_param("is", $landlord_id, $status);
$listStmt->execute();
$reservations = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

// helper: human friendly "x days ago" from created_at
function time_ago($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    $units = [
        31536000 => 'y',
        2592000  => 'mo',
        604800   => 'w',
        86400    => 'd',
        3600     => 'h',
        60       => 'm',
        1        => 's'
    ];
    foreach ($units as $secs => $label) {
        if ($diff >= $secs) {
            $value = floor($diff / $secs);
            return "{$value}{$label} ago";
        }
    }
    return 'just now';
}

// Check if this is a partial content request
$isPartial = isset($_GET['partial']) && $_GET['partial'] == '1';

// If partial request, only return the table section
if ($isPartial) {
    // ensure the partial view knows it's being included
    if (!defined('DIRECT_ACCESS')) define('DIRECT_ACCESS', true);
    // Start output buffer to capture just the table HTML
    ob_start();
    include 'reservation_table.php'; // partial view
    echo ob_get_clean();
    exit();
}

?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Manage Reservations</title>
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
    .container-top { max-width: 1100px; margin: 32px auto; }
    .banner {
        background: #fff4e6;
        border-radius: 10px;
        padding: 18px 20px;
        border: 1px solid rgba(0,0,0,0.03);
        display:flex; align-items:center; gap:12px;
    }
    .banner .icon { background:#fff; border-radius:50%; padding:8px; color:#d35400; box-shadow: 0 2px 6px rgba(0,0,0,0.03); }
    .tab-pill {
        background: rgba(0,0,0,0.03);
        border-radius: 999px;
        display:flex; gap:12px; padding:6px;
    }
    .tab-pill .nav-link { border-radius:999px; padding:10px 22px; color:#333; }
    .tab-pill .nav-link.active { background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.06); color:#000; font-weight:600; }

    /* table card */
    .card-table { border-radius:12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
    .status-pill { padding:6px 10px; border-radius:999px; font-weight:600; text-transform:lowercase; }

    /* approve / reject buttons in actions */
    .btn-approve { background: #198754; color: #fff; border: none; border-radius:8px; padding:8px 14px; }
    .btn-reject  { background: #dc3545; color: #fff; border: none; border-radius:8px; padding:8px 14px; }

    .small-muted { color:#6c757d; font-size:0.9rem; }
</style>

</head>

<body class="bg-light">
    <h2 class="fw-bold">Student Reservations</h2>
    <p class="text-muted mb-4">Approve or reject student booking requests and manage confirmed bookings</p>

    <?php if ($pendingCount > 0): ?>
    <div class="banner mb-4">
        <div class="icon"><i class="bi bi-clock-historyfs-4"></i></div>
        <div>
            <div class="fw-semibold">You have <?= $pendingCount ?> pending booking <?= $pendingCount>1 ? 'requests' : 'request' ?> that need your attention</div>
            <div class="small-muted">Review and approve/reject student resavations below</div>
        </div>  
    </div>  
    <?php endif; ?>

    <!---Tabs----->
    <div class="tab-pill mb-3">
        <?php 
            $tabStatuses = [
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled'
            ];
            foreach ($tabStatuses as $key => $label):
                $active = ($status === $key) ? 'active' : '';
                // Always show count in parentheses (including 0)
                $count = $counts[$key] ?? 0;
                $badge = "<span class='badge bg-" . ($count > 0 ? 'danger' : 'secondary') . " ms-2'>($count)</span>";
        ?>
               <a class="nav-link <?= $active ?>" href="javascript:void(0)" data-status="<?= $key ?>"><?= $label ?> <?= $badge ?></a>
         <?php endforeach; ?>
    </div>

    <!--Table-->
    <?php define('DIRECT_ACCESS', true); include 'reservation_table.php'; ?>

<script>
function initializeActionForms() {
    // Handle approve/reject form submissions
    document.querySelectorAll('.action-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            // Show loading state
            const btn = form.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            
            // Submit form via AJAX
            fetch('sections/manage_reservations.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(() => {
                // Get current active tab's status
                const activeTab = document.querySelector('.tab-pill .nav-link.active');
                const status = activeTab ? activeTab.dataset.status : 'pending';
                
                // Refresh the table content
                return fetch(`sections/manage_reservations.php?status=${status}&partial=1`);
            })
            .then(res => res.text())
            .then(html => {
                const tableSection = document.querySelector('.card.card-table');
                if (tableSection && html) {
                    tableSection.outerHTML = html;
                    // Re-initialize forms in the new content
                    initializeActionForms();
                }
            })
            .catch(err => {
                console.error('Error:', err);
                // Restore button state
                btn.disabled = false;
                btn.innerHTML = originalText;
                alert('Error processing request. Please try again.');
            });
        });
    });
}

function initializeTabNavigation() {
    const tabs = document.querySelectorAll('.tab-pill .nav-link');
    tabs.forEach(tab => {
        // Remove existing listeners to prevent duplicates
        const newTab = tab.cloneNode(true);
        tab.parentNode.replaceChild(newTab, tab);
        newTab.addEventListener('click', handleTabClick);
    });
}

function handleTabClick(e) {
    e.preventDefault();
    const status = this.dataset.status;

    // Update active state on tabs
    document.querySelectorAll('.tab-pill .nav-link').forEach(t => t.classList.remove('active'));
    this.classList.add('active');

    const tableSection = document.querySelector('.card.card-table');
    if (tableSection) {
        tableSection.style.opacity = '0.5';
    }

    // Fetch new table content
    fetch(`sections/manage_reservations.php?status=${status}&partial=1`)
        .then(res => res.text())
        .then(html => {
            if (tableSection && html) {
                tableSection.outerHTML = html;
                // The table is new, so re-initialize the action forms within it
                initializeActionForms();
                // Re-initialize tab navigation to ensure it still works
                initializeTabNavigation();
            }
            if (tableSection) {
                tableSection.style.opacity = '1';
            }
        })
        .catch(err => {
            console.error('Error fetching reservations:', err);
            if (tableSection) {
                tableSection.style.opacity = '1';
            }
        });
}

// Initialize immediately (for AJAX-loaded content)
initializeActionForms();
initializeTabNavigation();

</script>

</body>
</html>