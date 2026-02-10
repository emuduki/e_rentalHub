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

//PENDING REVENUE
if ($is_admin) {
    $pending_q = $conn->prepare("SELECT SUM(amount) AS total_pending FROM reservations WHERE status='pending'");
    $pending_q->execute();
} else {
    $pending_q = $conn->prepare("SELECT SUM(amount) AS total_pending FROM reservations WHERE landlord_id=? AND status='pending'");
    $pending_q->bind_param("i", $landlord_id);
    $pending_q->execute();
}
$pending_result = $pending_q->get_result();
$pending_revenue = $pending_result ? ($pending_result->fetch_assoc()['total_pending'] ?? 0) : 0;
$pending_q->close();

//THIS MONTH REVENUE
if ($is_admin) {
    $this_q = $conn->prepare("SELECT SUM(amount) AS total_month FROM reservations WHERE status='confirmed' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
    $this_q->execute();
} else {
    $this_q = $conn->prepare("SELECT SUM(amount) AS total_month FROM reservations WHERE landlord_id=? AND status='confirmed' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
    $this_q->bind_param("i", $landlord_id);
    $this_q->execute();
}
$this_result = $this_q->get_result();
$this_month_revenue = $this_result ? ($this_result->fetch_assoc()['total_month'] ?? 0) : 0;
$this_q->close();

//LAST 30 DAYS REVENUE
if ($is_admin) {
    $last30_q = $conn->prepare("SELECT SUM(amount) AS total_30 FROM reservations WHERE status='confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $last30_q->execute();
} else {
    $last30_q = $conn->prepare("SELECT SUM(amount) AS total_30 FROM reservations WHERE landlord_id=? AND status='confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $last30_q->bind_param("i", $landlord_id);
    $last30_q->execute();
}
$last30_result = $last30_q->get_result();
$last30_revenue = $last30_result ? ($last30_result->fetch_assoc()['total_30'] ?? 0) : 0;
$last30_q->close();

//TOTAL BOOKINGS
if ($is_admin) {
    $bookings_q = $conn->prepare("SELECT COUNT(*) AS total_bookings FROM reservations");
    $bookings_q->execute();
} else {
    $bookings_q = $conn->prepare("SELECT COUNT(*) AS total_bookings FROM reservations WHERE landlord_id=?");
    $bookings_q->bind_param("i", $landlord_id);
    $bookings_q->execute();
}
$bookings_result = $bookings_q->get_result();
$total_bookings = $bookings_result ? ($bookings_result->fetch_assoc()['total_bookings'] ?? 0) : 0;
$bookings_q->close();

//AVG BOOKING VALUE
if ($is_admin) {
    $avg_q = $conn->prepare("SELECT AVG(amount) AS avg_amount FROM reservations WHERE status='confirmed'");
    $avg_q->execute();
} else {
    $avg_q = $conn->prepare("SELECT AVG(amount) AS avg_amount FROM reservations WHERE landlord_id=? AND status='confirmed'");
    $avg_q->bind_param("i", $landlord_id);
    $avg_q->execute();
}
$avg_result = $avg_q->get_result();
$avg_booking = $avg_result ? ($avg_result->fetch_assoc()['avg_amount'] ?? 0) : 0;
$avg_q->close();

//COLLECTION RATE (confirmed vs confirmed + pending)
$expected_revenue = (float)$total_revenue + (float)$pending_revenue;
$collection_rate = $expected_revenue > 0 ? ($total_revenue / $expected_revenue) * 100 : 0;

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
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
   :root {
        --ink: #111827;
        --muted: #64748b;
        --accent: #0ea5e9;
        --accent-2: #14b8a6;
        --accent-3: #f59e0b;
        --surface: #ffffff;
        --surface-2: #f8fafc;
        --stroke: rgba(15, 23, 42, 0.08);
        --shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
    }

    body {
        background: radial-gradient(circle at top, rgba(14, 165, 233, 0.12), transparent 50%),
            linear-gradient(180deg, #f1f5f9 0%, #ffffff 60%);
        font-family: "Space Grotesk", "Segoe UI", sans-serif;
        color: var(--ink);
    }

    .finance-shell {
        padding: 24px;
    }

    .finance-hero {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .finance-title {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
    }

    .finance-subtitle {
        margin: 4px 0 0;
        color: var(--muted);
    }

    .hero-chips {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .chip {
        background: var(--surface);
        border: 1px solid var(--stroke);
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.85rem;
        color: var(--muted);
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }

    .fin-box {
        background: var(--surface);
        padding: 18px;
        border-radius: 18px;
        border: 1px solid var(--stroke);
        box-shadow: var(--shadow);
    }

    .fin-value {
        font-size: 1.7rem;
        font-weight: 700;
        color: var(--ink);
    }

    .fin-label {
        font-size: 0.95rem;
        color: var(--muted);
    }

    .fin-meta {
        margin-top: 6px;
        font-size: 0.85rem;
        color: var(--muted);
    }

    .tab-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    .tab-btn {
        padding: 10px 18px;
        border-radius: 999px;
        background: var(--surface-2);
        border: 1px solid var(--stroke);
        cursor: pointer;
        font-size: 0.9rem;
        transition: .2s;
    }

    .tab-btn.active {
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        border-color: transparent;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .chart-card {
        background: var(--surface);
        border-radius: 20px;
        padding: 22px;
        border: 1px solid var(--stroke);
        box-shadow: var(--shadow);
    }

    .chart-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--muted);
        margin-bottom: 14px;
    }

    .chart-wrap {
        background: var(--surface-2);
        border: 1px dashed rgba(15, 23, 42, 0.12);
        border-radius: 16px;
        padding: 12px;
    }

    .chart-wrap canvas {
        width: 100% !important;
        height: 320px !important;
    }

    @media (max-width: 992px) {
        .kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 576px) {
        .finance-shell {
            padding: 16px;
        }
        .finance-hero {
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .finance-title {
            font-size: 1.6rem;
        }
        .chip {
            font-size: 0.78rem;
            padding: 5px 10px;
        }
        .kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .fin-box {
            padding: 12px;
        }
        .fin-value {
            font-size: 1.3rem;
        }
        .fin-label {
            font-size: 0.85rem;
        }
        .tab-buttons {
            gap: 6px;
        }
        .chart-wrap canvas {
            height: 260px !important;
        }
    }
</style>

<div class="finance-shell">
    <div class="finance-hero">
        <div>
            <h3 class="finance-title">Financial Overview</h3>
            <p class="finance-subtitle">Track revenue performance, bookings value, and portfolio health.</p>
        </div>
        <div class="hero-chips">
            <span class="chip"><?php echo $is_admin ? 'Scope: Platform' : 'Scope: My portfolio'; ?></span>
            <span class="chip">Updated: <?php echo date('M d, Y'); ?></span>
        </div>
    </div>

    <!--Summary cards-->
    <div class="kpi-grid">
        <div class="fin-box">
            <div class="fin-label">Total Revenue</div>
            <div class="fin-value">KES <?= number_format((float)$total_revenue, 0) ?></div>
            <div class="fin-meta">Confirmed payments</div>
        </div>

        <div class="fin-box">
            <div class="fin-label">This Month</div>
            <div class="fin-value">KES <?= number_format((float)$this_month_revenue, 0) ?></div>
            <div class="fin-meta">Current month</div>
        </div>

        <div class="fin-box">
            <div class="fin-label">Last 30 Days</div>
            <div class="fin-value">KES <?= number_format((float)$last30_revenue, 0) ?></div>
            <div class="fin-meta">Rolling window</div>
        </div>

        <div class="fin-box">
            <div class="fin-label">Pending Revenue</div>
            <div class="fin-value">KES <?= number_format((float)$pending_revenue, 0) ?></div>
            <div class="fin-meta">Awaiting confirmation</div>
        </div>

        <div class="fin-box">
            <div class="fin-label">Avg Booking Value</div>
            <div class="fin-value">KES <?= number_format((float)$avg_booking, 0) ?></div>
            <div class="fin-meta"><?= number_format((float)$total_bookings) ?> bookings</div>
        </div>

        <div class="fin-box">
            <div class="fin-label">Collection Rate</div>
            <div class="fin-value"><?= number_format((float)$collection_rate, 1) ?>%</div>
            <div class="fin-meta">Confirmed vs pending</div>
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
                <div class="chart-title">Monthly Revenue</div>
                <div class="chart-wrap">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="chart-card">
                <div class="chart-title">Revenue by Property Type</div>
                <div class="chart-wrap">
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
                <div class="chart-title">Bookings by Property Type</div>
                <div class="chart-wrap">
                    <canvas id="pieDistribution"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="chart-card">
                <div class="chart-title">Booking Breakdown</div>
                <div id="typeBreakdown"></div>
            </div>
        </div>

    </div>
</div>

<div id="performers" class="tab-content">
    <div class="chart-card">
        <div class="chart-title">Top Earning Properties</div>
        <div class="chart-wrap">
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
                backgroundColor: 'rgba(14, 165, 233, 0.6)',
                borderColor: '#0ea5e9',
                borderWidth: 2,
                borderRadius: 10,
                barThickness: 26,
                hoverBackgroundColor: 'rgba(20, 184, 166, 0.7)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(148, 163, 184, 0.2)' },
                    ticks: {
                        color: '#64748b',
                        callback: (value) => `KES ${value}`
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10,
                    callbacks: {
                        label: (ctx) => `KES ${ctx.parsed.y}`
                    }
                }
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
                backgroundColor: ['#0ea5e9', '#14b8a6', '#f59e0b', '#f97316', '#6366f1'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#64748b', boxWidth: 12 }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10
                }
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
                backgroundColor: ['#0ea5e9', '#14b8a6', '#f59e0b', '#f97316', '#6366f1'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#64748b', boxWidth: 12 }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10
                }
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
                backgroundColor: 'rgba(20, 184, 166, 0.6)',
                borderColor: '#14b8a6',
                borderWidth: 2,
                borderRadius: 10,
                barThickness: 26
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(148, 163, 184, 0.2)' },
                    ticks: {
                        color: '#64748b',
                        callback: (value) => `KES ${value}`
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10,
                    callbacks: {
                        label: (ctx) => `KES ${ctx.parsed.y}`
                    }
                }
            }
        }
    });
}

/* ---------- Helper to populate the right-hand type breakdown ---------- */
function renderTypeBreakdown() {
    const types = TYPE_LABELS || [];
    const values = TYPE_VALUES || [];
    const colors = ['#0ea5e9', '#14b8a6', '#f59e0b', '#f97316', '#6366f1'];
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

