<?php
include("config/db.php");
$result = $conn->query('SELECT id, address, city FROM properties WHERE id = 12');
if ($result && $row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . PHP_EOL;
    echo 'Address: ' . $row['address'] . PHP_EOL;
    echo 'City: ' . $row['city'] . PHP_EOL;
} else {
    echo 'Property not found' . PHP_EOL;
}
?>
