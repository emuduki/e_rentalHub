<?php
session_start();
include("../../config/db.php");

// Ensure landlord is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.html");
    exit();
}

// Get the landlord record using the user_id from session
$user_id = $_SESSION['user_id'];
$landlord_query = "SELECT id FROM landlords WHERE user_id = '$user_id'";
$landlord_result = $conn->query($landlord_query);
if ($landlord_result->num_rows === 0) {
    echo "Landlord profile not found.";
    exit();
}
$landlord = $landlord_result->fetch_assoc();
$landlord_id = $landlord['id'];

// ✅ Fetch inquiry statistics safely

// --- New inquiries ---
$newCountQuery = $conn->query("
    SELECT COUNT(*) AS count 
    FROM inquiries i 
    JOIN houses h ON i.house_id = h.id 
    WHERE h.landlord_id = $landlord_id 
    AND (i.status = 'new' OR i.status IS NULL)
");
$newCount = $newCountQuery ? ($newCountQuery->fetch_assoc()['count'] ?? 0) : 0;

// --- Replied inquiries ---
$repliedCountQuery = $conn->query("
    SELECT COUNT(*) AS count 
    FROM inquiries i 
    JOIN houses h ON i.house_id = h.id 
    WHERE h.landlord_id = $landlord_id 
    AND i.status = 'replied'
");
$repliedCount = $repliedCountQuery ? ($repliedCountQuery->fetch_assoc()['count'] ?? 0) : 0;

// --- Average response time ---
$avgResponseQuery = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, i.created_at, i.responded_at)) AS avg_hours 
    FROM inquiries i
    JOIN houses h ON i.house_id = h.id
    WHERE h.landlord_id = $landlord_id AND i.status = 'replied'
");
$avgResponse = $avgResponseQuery ? $avgResponseQuery->fetch_assoc() : null;
$avgHours = ($avgResponse && $avgResponse['avg_hours']) ? round($avgResponse['avg_hours'], 1) : 0;
$responseRating = ($avgHours <= 3 && $avgHours > 0) ? "Excellent" : (($avgHours <= 8) ? "Good" : "Needs Improvement");

// ✅ Get 3 most recent inquiries
$recentInquiries = $conn->query("
    SELECT i.id, i.message, i.created_at, u.username AS tenant_name, u.email, h.title AS house_title
    FROM inquiries i
    JOIN users u ON i.user_id = u.id
    JOIN houses h ON i.house_id = h.id
    WHERE h.landlord_id = $landlord_id
    ORDER BY i.created_at DESC
    LIMIT 3
");
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f5f5f5;
            padding-top: 56px;
        }
        .inquiry-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 24px 30px;
            margin-bottom: 2rem;
        }
        .inquiry-item {
            border-bottom: 1px solid #eee;
            padding: 16px 0;
            transition: background-color 0.2s ease;
        }
        .inquiry-item:hover {
            background-color: #fafafa;
        }
        .inquiry-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .inquiry-name {
            font-weight: 600;
            font-size: 1rem;
            color: #333;
        }
        .inquiry-title {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 4px;
        }
        .inquiry-message {
            color: #444;
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        .inquiry-meta {
            color: #777;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .inquiry-meta i {
            font-size: 1rem;
            color: #6c757d;
        }
        .badge-new {
            background-color: #e7f1ff;
            color: #0d6efd;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
        }
        .summary-cards {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            margin-top: 1.5rem;
        }
        .summary-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 20px;
            width: 100%;
            display: flex;
            justify-content: space-between; /* text left, icon right */
            align-items: center;
        }
        .summary-card h6 {
            color: #333;
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
        }
        .summary-card .count {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
            color: #222;
        }
        .summary-card .status {
            font-size: 0.9rem;
            font-weight: 500;
        }
        .status.orange { color: #e67e22; }
        .status.green { color: #2ecc71; }
        .status.blue { color: #3498db; }
        .summary-icon {
            font-size: 2rem;
            color: #6c63ff;
            opacity: 0.9;
        }

    </style>
    <body class="p-4">

        <h4 class="fw-bold mb-2">Student Inquiries </h4>
        <p class="text-muted mb-4">Respond to messages and questions from potential student tenants</p>

        <div class="inquiry-container mb-4">
        <h6 class="fw-bold mb-3">New Inquiries (Recent 3)</h6>

        <?php if ($recentInquiries && $recentInquiries->num_rows > 0): ?>
            <?php while ($inquiry = $recentInquiries->fetch_assoc()): ?>
                <div class="inquiry-item">
                    <div class="inquiry-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="text-primary fs-5">
                                <i class="bi bi-chat-dots-fill"></i>
                            </div>
                            <div>
                                <div class="inquiry-name"><?= htmlspecialchars($inquiry['tenant_name']) ?></div>
                                <div class="inquiry-title"><?= htmlspecialchars($inquiry['house_title']) ?></div>
                            </div>
                        </div>
                        <span class="badge-new">New</span>
                    </div>
                    <p class="inquiry-message mb-1"><?= htmlspecialchars($inquiry['message']) ?></p>
                    <div class="inquiry-meta">
                        <span><i class="bi bi-clock"></i> <?= date('M j, Y g:i A', strtotime($inquiry['created_at'])) ?></span>
                        <span><i class="bi bi-envelope"></i> <?= htmlspecialchars($inquiry['email']) ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted fst-italic">
                <?= $conn->error ? "Error: " . htmlspecialchars($conn->error) : "No recent inquiries found." ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- ✅ Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div>
                <h6>New Inquiries</h6>
                <div class="count"><?= $newCount ?></div>
                <div class="status orange">Needs response</div>
            </div>
            <i class="bi bi-chat-dots-fill summary-icon"></i>
        </div>
        <div class="summary-card">
            <div>
                <h6>Replied</h6>
                <div class="count"><?= $repliedCount ?></div>
                <div class="status green">Responded</div>
            </div>
            <i class="bi bi-check-circle-fill summary-icon"></i>
        </div>
        <div class="summary-card">
            <div>
                <h6>Avg. Response Time</h6>
                <div class="count"><?= $avgHours ?> hours</div>
                <div class="status blue"><?= $responseRating ?></div>
            </div>
            <i class="bi bi-speedometer2 summary-icon"></i>
        </div>
    </div>
    </body>
</html>
