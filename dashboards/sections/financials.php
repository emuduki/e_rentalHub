<?php  
session_start();
include(__DIR__ . "/../../config/db.php");

//Ensure user is logged in
$session_user_id = $_SESSION['user_id'] ?? 0;
if (!$session_user_id) die("User not logged in");

// Determine if this is for a landlord or admin viewing platform-wide stats
$is_admin = strtolower(trim($_SESSION['role'] ?? '')) === 'admin';
$landlord_id = null;

if (!$is_admin) {
    // Fetch landlord ID for regular landlords
    $stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $landlord_id = $res['id'] ?? 0;
    $stmt->close();
}

//TOTAL REVENUE
if ($is_admin) {
    // Show platform-wide revenue for admin
    $q = $conn->prepare("
        SELECT SUM(amount) AS total_revenue
        FROM reservations
        WHERE status='confirmed'
    ");
    $q->execute();
} else {
    // Show landlord-specific revenue
    $q = $conn->prepare("
        SELECT SUM(amount) AS total_revenue
        FROM reservations
        WHERE landlord_id=? AND status='confirmed'
    ");
    $q->bind_param("i", $landlord_id);
    $q->execute();
}
$result = $q->get_result();
$total_revenue = $result ? ($result->fetch_assoc()['total_revenue'] ?? 0) : 0;
$q->close();

//MONTHLY REVENUE
if ($is_admin) {
    $month_q = $conn->prepare("
        SELECT DATE_FORMAT(created_at,'%b') AS month, SUM(amount) AS mtotal
        FROM reservations
        WHERE status='confirmed'
        GROUP BY DATE_FORMAT(created_at,'%Y-%m')
        ORDER BY created_at ASC
    ");
    $month_q->execute();
} else {
    $month_q = $conn->prepare("
        SELECT DATE_FORMAT(created_at,'%b') AS month, SUM(amount) AS mtotal
        FROM reservations
        WHERE landlord_id=? AND status='confirmed'
        GROUP BY DATE_FORMAT(created_at,'%Y-%m')
        ORDER BY created_at ASC
    ");
    $month_q->bind_param("i", $landlord_id);
    $month_q->execute();
}

$month_labels = [];
$month_values = [];

$month_result = $month_q->get_result();
if ($month_result) {
    while ($row = $month_result->fetch_assoc()) {
        $month_labels[] = $row['month'];
        $month_values[] = $row['mtotal'];
    }
}
$month_q->close();

//BOOKINGS BY PROPERTY TYPE
if ($is_admin) {
    $type_q = $conn->prepare("
        SELECT p.type, COUNT(r.id) AS total_bookings
        FROM reservations r
        JOIN properties p ON r.property_id = p.id
        WHERE r.status IN ('pending', 'confirmed', 'completed')
        GROUP BY p.type
    ");
    if (!$type_q) {
        die("Prepare failed: " . $conn->error);
    }
    $type_q->execute();
} else {
    $type_q = $conn->prepare("
        SELECT p.type, COUNT(r.id) AS total_bookings
        FROM reservations r
        JOIN properties p ON r.property_id = p.id
        WHERE r.landlord_id=? AND r.status IN ('pending', 'confirmed', 'completed')
        GROUP BY p.type
    ");
    if (!$type_q) {
        die("Prepare failed: " . $conn->error);
    }
    $type_q->bind_param("i", $landlord_id);
    $type_q->execute();
}

$type_labels = [];
$type_values = [];

$type_result = $type_q->get_result();
if ($type_result) {
    while ($row = $type_result->fetch_assoc()) {
        $type_labels[] = $row['type'];
        $type_values[] = $row['total_bookings'];
    }
}
$type_q->close();

//TOP PROPERTIES
if ($is_admin) {
    $top_q = $conn->prepare("
        SELECT p.title, SUM(r.amount) AS total
        FROM reservations r
        JOIN properties p ON r.property_id = p.id
        WHERE r.status='confirmed'
        GROUP BY r.property_id
        ORDER BY total DESC
        LIMIT 5
    ");
    $top_q->execute();
} else {
    $top_q = $conn->prepare("
        SELECT p.title, SUM(r.amount) AS total
        FROM reservations r
        JOIN properties p ON r.property_id = p.id
        WHERE r.landlord_id=? AND r.status='confirmed'
        GROUP BY r.property_id
        ORDER BY total DESC
        LIMIT 5
    ");
    $top_q->bind_param("i", $landlord_id);
    $top_q->execute();
}

$top_property_names = [];
$top_property_values = [];

$top_result = $top_q->get_result();
if ($top_result) {
    while ($row = $top_result->fetch_assoc()) {
        $top_property_names[] = $row['title'];
        $top_property_values[] = $row['total'];
    }
}
$top_q->close();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<style>
   body {
    background: #f5f7fb;
    font-family: 'Inter', sans-serif;
    }

    .fin-box {
        background: #ffffff;
        padding: 22px;
        border-radius: 20px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }

    .fin-value {
        font-size: 30px;
        font-weight: 700;
        color: #2d2e2f;
    }

    .fin-label {
        font-size: 14px;
        color: #6c757d;
    }
    .tab-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 18px;
    }

    .tab-btn {
        padding: 10px 18px;
        border-radius: 25px;
        background: #f1f1f1;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: .2s;
    }

    .tab-btn.active {
        background: #2d83f8;
        color: white;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Chart boxes */
    .chart-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }

    /* Header title like screenshot */
    .finance-title {
        font-size: 28px;
        font-weight: 700;
        background: linear-gradient(135deg, #2d83f8, #3aa9ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Summary section spacing */
    .summary-row {
        margin-bottom: 18px;
    }
</style>

<div class="container py-4">

    <h3 class="finance-title mb-4">Financial Overview</h3>

    <!--Summary cards-->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="fin-box">
                <div class="fin-label">Total Revenue</div>
                <div class="fin-value">KES <?= number_format((float)$total_revenue, 0) ?></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="fin-box">
                <div class="fin-label">Monthly Revenue</div>
                <div class="fin-value">KES <?= number_format((float)array_sum($month_values), 0) ?></div>
            </div>

        </div>

    </div>

</div>

<div class="tab-buttons">
    <button class="tab-btn active" onclick="showTab('trends')">Revenue Trends</button>
    <button class="tab-btn" onclick="showTab('distribution')">Property Distribution</button>
    <button class="tab-btn" onclick="showTab('performers')">Top Performers</button>
</div>


<div id="trends" class="tab-content active">
    <div class="row g-4">
        <div class="col-md-7">
            <div class="chart-card">
                <h6 class="text-muted mb-3">Monthly Revenue</h6>
                <div style="position: relative; height: 380px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="chart-card">
                <h6 class="text-muted mb-3">Revenue by Property Type</h6>
                <div style="position: relative; height: 380px;">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="distribution" class="tab-content">
    <div class="row g-4">

        <div class="col-md-7">
            <div class="chart-card">
                <h6 class="text-muted mb-3">Bookings by Property Type</h6>
                <div style="position: relative; height: 400px;">
                    <canvas id="pieDistribution"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="chart-card">
                <h6 class="text-muted mb-3">Booking Breakdown</h6>
                <div id="typeBreakdown"></div>
            </div>
        </div>

    </div>
</div>

<div id="performers" class="tab-content">
    <div class="chart-card">
        <h6 class="text-muted mb-3">Top Earning Properties</h6>
        <div style="position: relative; height: 350px;">
            <canvas id="topChart"></canvas>
        </div>
    </div>
</div>



<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/*
  Full chart initialization + tab handling script.
  - Lazy-inits charts only when their tab becomes visible.
  - Updates/resizes charts on tab show so hidden-canvas issues are avoided.
*/

// Hold Chart instances
const CHARTS = {
    monthly: null,
    typeSmall: null,
    distributionPie: null,
    top: null
};

// Helper: build a chart safely
function createChart(canvas, cfg) {
    if (!canvas) return null;
    try {
        return new Chart(canvas, cfg);
    } catch (err) {
        console.error("Chart creation failed:", err);
        return null;
    }
}

// Activate a tab and initialize charts if necessary
function showTab(tab) {
    // hide all tab contents
    document.querySelectorAll('.tab-content').forEach(div => div.classList.remove('active'));
    // show requested
    const target = document.getElementById(tab);
    if (target) target.classList.add('active');

    // Manage tab button active state (find tab-btn with onclick matching)
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const matchingBtn = document.querySelector(`.tab-btn[onclick="showTab('${tab}')"]`);
    if (matchingBtn) matchingBtn.classList.add('active');

    // Initialize or resize charts based on which tab was selected
    if (tab === 'trends') {
        initMonthlyChart();
        initTypeSmallChart();
        // ensure they redraw
        setTimeout(() => {
            if (CHARTS.monthly) CHARTS.monthly.resize();
            if (CHARTS.typeSmall) CHARTS.typeSmall.resize();
        }, 100);
    }

    if (tab === 'distribution') {
        initDistributionPie();
        setTimeout(() => { if (CHARTS.distributionPie) CHARTS.distributionPie.resize(); }, 100);
    }

    if (tab === 'performers') {
        initTopChart();
        setTimeout(() => { if (CHARTS.top) CHARTS.top.resize(); }, 100);
    }
}

/* ---------- Data injected from server (keep these exactly as in your PHP page) ---------- */
const MONTH_LABELS = <?= json_encode($month_labels) ?>;
const MONTH_VALUES = <?= json_encode($month_values) ?>;

const TYPE_LABELS = <?= json_encode($type_labels) ?>;
const TYPE_VALUES = <?= json_encode($type_values) ?>;

const TOP_NAMES = <?= json_encode($top_property_names ?? []) ?>;
const TOP_VALUES = <?= json_encode($top_property_values ?? []) ?>;
/* --------------------------------------------------------------------------------------- */


/* ---------- Chart initializers (lazy) ---------- */

function initMonthlyChart() {
    if (CHARTS.monthly) return; // already created
    const canvas = document.getElementById('monthlyChart');
    if (!canvas || typeof Chart === 'undefined') return;

    CHARTS.monthly = createChart(canvas, {
        type: 'bar',
        data: {
            labels: MONTH_LABELS,
            datasets: [{
                label: "KES",
                data: MONTH_VALUES,
                backgroundColor: '#2d83f8',
                borderColor: '#2d83f8',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: { beginAtZero: true }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function initTypeSmallChart() {
    if (CHARTS.typeSmall) return;
    const canvas = document.getElementById('typeChart');
    if (!canvas || typeof Chart === 'undefined') return;

    CHARTS.typeSmall = createChart(canvas, {
        type: 'pie',
        data: {
            labels: TYPE_LABELS,
            datasets: [{
                data: TYPE_VALUES,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

function initDistributionPie() {
    if (CHARTS.distributionPie) return;
    const canvas = document.getElementById('pieDistribution');
    if (!canvas || typeof Chart === 'undefined') return;

    CHARTS.distributionPie = createChart(canvas, {
        type: 'pie',
        data: {
            labels: TYPE_LABELS,
            datasets: [{
                data: TYPE_VALUES,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            layout: {
                padding: 60
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // also update breakdown area
    renderTypeBreakdown();
}

function initTopChart() {
    if (CHARTS.top) return;
    const canvas = document.getElementById('topChart');
    if (!canvas || typeof Chart === 'undefined') return;

    CHARTS.top = createChart(canvas, {
        type: 'bar',
        data: {
            labels: TOP_NAMES,
            datasets: [{
                label: "KES",
                data: TOP_VALUES,
                backgroundColor: '#3aa9ff',
                borderColor: '#3aa9ff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: { beginAtZero: true }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

/* ---------- Helper to populate the right-hand type breakdown ---------- */
function renderTypeBreakdown() {
    const types = TYPE_LABELS || [];
    const values = TYPE_VALUES || [];
    const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
    const total = values.reduce((a, b) => a + (Number(b) || 0), 0);

    let breakdownHtml = '';
    if (types.length === 0) {
        breakdownHtml = '<p style="color: #999; text-align: center; padding: 20px;">No data available</p>';
    } else {
        types.forEach((type, index) => {
            const v = Number(values[index] || 0);
            const percentage = total > 0 ? ((v / total) * 100).toFixed(0) : 0;
            const color = colors[index % colors.length];
            breakdownHtml += `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eee;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="width:14px;height:14px;background:${color};border-radius:3px;display:inline-block"></span>
                        <span style="font-size:14px;color:#333">${type}</span>
                    </div>
                    <div style="text-align:right">
                        <div style="font-weight:600;color:#2d2e2f">${v} Bookings</div>
                        <div style="font-size:12px;color:#999">${percentage}%</div>
                    </div>
                </div>
            `;
        });
    }

    const breakdownDiv = document.getElementById('typeBreakdown');
    if (breakdownDiv) breakdownDiv.innerHTML = breakdownHtml;
}

/* ---------- DOM ready: wire default tab and initial content ---------- */
document.addEventListener('DOMContentLoaded', function() {
    // Default show trends tab (same as your original)
    showTab('trends');

    // If first tab is active, initialize its charts immediately
    // (monthly and type small live inside 'trends')
    initMonthlyChart();
    initTypeSmallChart();
    renderTypeBreakdown();

    // Ensure chart canvases have a reasonable height so they render nicely
    // (only if maintainAspectRatio=false; set a min-height)
    const canvasSizing = [
        'monthlyChart', 'typeChart', 'pieDistribution', 'topChart'
    ];
    canvasSizing.forEach(id => {
        const c = document.getElementById(id);
        if (c) c.style.minHeight = (id === 'monthlyChart' || id === 'topChart') ? '260px' : '220px';
    });

    // Resize charts on window resize
    window.addEventListener('resize', function() {
        Object.values(CHARTS).forEach(chart => { if (chart && typeof chart.resize === 'function') chart.resize(); });
    });
});
</script>

