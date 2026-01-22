<?php
session_start();
include("../../config/db.php");

//  Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    header("Location: ../index.html");
    exit();
}

$landlord_id = intval($_SESSION['user_id'] ?? 0);
$errorMsg = null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Analytics</title>
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

        /* Container */
        .analytics-container {
            margin-top: 30px;
        }

        /* Titles */
        .analytics-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
        }

        .analytics-subtitle {
            margin-top: -5px;
            color: #6b7280;
            font-size: 0.95rem;
        }

        /* Grid */
        .analytics-grid {
            margin-top: 25px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
        }

        /* Analytics Card */
        .analytics-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: 0.3s ease;
        }

        .analytics-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        /* Header */
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .analytics-header h4 {
            font-size: 1rem;
            color: #4b5563;
            font-weight: 600;
        }

        /* Icons */
        .analytics-icon {
            font-size: 1.6rem;
            color: #3b82f6;
        }

        .analytics-icon.star {
            color: #fbbf24;
        }

        /* Main Number */
        .analytics-value {
            margin-top: 10px;
            font-size: 1.8rem;
            font-weight: 700;
            color: #111827;
        }

        /* Status Text */
        .analytics-status {
            margin-top: 5px;
            font-weight: 600;
            color: #10b981; /* green */
        }

        /* Positive / Negative Change */
        .analytics-change {
            margin-top: 5px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .analytics-change.positive {
            color: #10b981;
        }

        .analytics-change.negative {
            color: #ef4444;
        }

    	</style>
	</head>
<body>

    <div class="analytics-container">

    <h2 class="analytics-title">Property Analytics</h2>
    <p class="analytics-subtitle">Track performance and insights for your properties</p>

    <div class="analytics-grid">

        <!-- Total Views -->
        <div class="analytics-card">
            <div class="analytics-header">
                <h4>Total Views</h4>
                <i class="bi bi-eye analytics-icon"></i>
            </div>

            <div class="analytics-value">2,018</div>
            <div class="analytics-change positive">+18% from last month</div>
        </div>

        <!-- Total Bookings -->
        <div class="analytics-card">
            <div class="analytics-header">
                <h4>Total Bookings</h4>
                <i class="bi bi-house-door analytics-icon"></i>
            </div>

            <div class="analytics-value">40</div>
            <div class="analytics-change positive">+23% from last month</div>
        </div>

        <!-- Avg Rating -->
        <div class="analytics-card">
            <div class="analytics-header">
                <h4>Avg. Rating</h4>
                <i class="bi bi-star-fill analytics-icon star"></i>
            </div>

            <div class="analytics-value">4.8</div>
            <div class="analytics-status">Excellent</div>
        </div>

        <!-- Conversion Rate -->
        <div class="analytics-card">
            <div class="analytics-header">
                <h4>Conversion Rate</h4>
                <i class="bi bi-graph-up-arrow analytics-icon"></i>
            </div>

            <div class="analytics-value">2.0%</div>
            <div class="analytics-change positive">+5% improvement</div>
        </div>

    </div>
</div>

</body>