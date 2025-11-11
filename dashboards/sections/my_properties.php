<?php
session_start();
include("../../config/db.php");

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    header("Location: ../../auth/login.php");
    exit();
}

$landlord_id = $_SESSION['user_id'];

// Fetch all properties for this landlord (include first image and image count)
$properties_query = $conn->query("
    SELECT 
        p.id,
        p.title,
        p.address,
        p.city,
        p.rent,
        p.type,
        p.bedrooms,
        p.area,
        p.status,
        p.description,
        COUNT(DISTINCT r.id) AS booking_count,
        (SELECT COUNT(*) FROM property_images pi WHERE pi.property_id = p.id) AS image_count,
        (SELECT pi.image_path 
           FROM property_images pi 
          WHERE pi.property_id = p.id 
          ORDER BY pi.uploaded_at DESC, pi.id DESC 
          LIMIT 1) AS first_image_path
    FROM properties p
    LEFT JOIN reservations r ON p.id = r.property_id
    WHERE p.landlord_id = $landlord_id
    GROUP BY p.id
    ORDER BY p.id DESC
");

$properties = [];
if ($properties_query) {
    while ($row = $properties_query->fetch_assoc()) {
        $properties[] = $row;
    }
}

// Get total count
$total_count = count($properties);

// Get views count (placeholder - you can implement actual views tracking later)
// For now, we'll use a simple calculation based on property age or random
function getViews($property_id) {
    // Placeholder: return a random number between 2-15
    // In production, you'd query a views table
    return rand(2, 15);
}

// Get bookings count from reservations
function getBookings($property_id, $conn) {
    $result = $conn->query("SELECT COUNT(*) AS count FROM reservations WHERE property_id = $property_id");
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }
    return 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 56px; /* match your navbar height */
        }

        .properties-container {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .section-heading {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
        }

        .properties-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .properties-table thead {
            background-color: #f9fafb;
        }

        .properties-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }

        .properties-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .properties-table tbody tr {
            transition: background-color 0.2s;
        }

        .properties-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .property-info {
            display: flex;
            flex-direction: column;
        }

        .property-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .property-id {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .location-info {
            color: #1f2937;
            font-size: 0.95rem;
        }

        .details-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-item i {
            color: #2563eb;
        }

        .price-info {
            font-weight: 600;
            color: #1f2937;
            font-size: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-available {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-reserved {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-unavailable {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .period-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .period-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .period-item i {
            color: #2563eb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="page-header">
            <h1 class="page-title">My Properties</h1>
            <p class="page-subtitle">View and manage all your student housing listings.</p>
        </div>
        <div class="properties-container">
            <h2 class="section-heading">All Properties (<?php echo $total_count; ?>)</h2>

            <?php if (count($properties) > 0): ?>
                <div class="table-responsive">
                    <table class="properties-table">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Location</th>
                                <th>Details</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Period</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $property): 
                                $views = getViews($property['id']);
                                $bookings = getBookings($property['id'], $conn);
                                
                                // Determine status badge class
                                $status_class = 'status-available';
                                if (strtolower($property['status']) === 'reserved') {
                                    $status_class = 'status-reserved';
                                } elseif (strtolower($property['status']) === 'unavailable') {
                                    $status_class = 'status-unavailable';
                                }
                                
                                // Format location
                                $location = htmlspecialchars($property['city'] . ', ' . $property['address']);
                                
                                // Format price
                                $price = 'KES ' . number_format($property['rent']) . '/mo';
                                
                                // Get bathrooms (if available in database, otherwise default to 1)
                                $bathrooms = isset($property['bathrooms']) ? $property['bathrooms'] : (isset($property['bedrooms']) ? max(1, floor($property['bedrooms'] / 2)) : 1);
                            ?>
                                <tr>
                                    <td>
                                        <div class="property-info">
                                            <span class="property-name"><?php echo htmlspecialchars($property['title']); ?></span>
                                            <span class="property-id">ID: #<?php echo $property['id']; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="location-info"><?php echo $location; ?></div>
                                    </td>
                                    <td>
                                        <div class="details-info">
                                            <div class="detail-item">
                                                <i class="bi bi-door-open"></i>
                                                <span><?php echo $property['bedrooms'] ?? 0; ?> beds</span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-droplet"></i>
                                                <span><?php echo $bathrooms; ?> baths</span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-rulers"></i>
                                                <span><?php echo $property['area'] ?? 'N/A'; ?> sq ft</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price-info"><?php echo $price; ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($property['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="period-info">
                                            <div class="period-item">
                                                <i class="bi bi-eye"></i>
                                                <span><?php echo $views; ?> views</span>
                                            </div>
                                            <div class="period-item">
                                                <i class="bi bi-calendar-check"></i>
                                                <span><?php echo $bookings; ?> bookings</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No Properties Found</h3>
                    <p>You haven't listed any properties yet. Click "Add New House" to get started!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</body>
</html>

