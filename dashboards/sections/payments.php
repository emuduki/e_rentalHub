<?php
session_start();
include("../../config/db.php");

$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'student') {
	header("Location: ../index.html");
	exit();
}

$student_id = intval($_SESSION['user_id'] ?? 0);
$errorMsg = null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Payments</title>
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

    	</style>
	</head>
<body>
	<div class="container py-4">
		<!-- white card wrapper so this section looks like a contained panel -->
		<div class="bg-white rounded-4 shadow-sm p-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h4 class="mb-0">Payment History</h4>
		</div>
		<!-- short description — placed under the heading so it doesn't sit on the same line -->
		<div class="w-100 mb-3">
			<p class="text-muted mb-0">View all your payment transactions.</p>
		</div>

    </div>
</body>