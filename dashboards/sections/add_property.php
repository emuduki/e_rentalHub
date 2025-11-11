<?php
session_start();
include("../config/db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $landlord_id = intval($_POST['landlord_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $address = $conn->real_escape_string($_POST['address']);
    $city = $conn->real_escape_string($_POST['city']);
    $rent = floatval($_POST['rent']);
    $type = $conn->real_escape_string($_POST['type']);
    $bedrooms = intval($_POST['bedrooms']);
    $area = intval($_POST['area']);
    $status = $conn->real_escape_string($_POST['status']);
    $description = $conn->real_escape_string($_POST['description']);
    $amenities = isset($_POST['amenities']) ? implode(", ", $_POST['amenities']) : "";

    // Insert property (NO images here)
    $query = "INSERT INTO properties (landlord_id, title, address, city, rent, type, bedrooms, area, status, description, amenities)
              VALUES ('$landlord_id', '$title', '$address', '$city', '$rent', '$type', '$bedrooms', '$area', '$status', '$description', '$amenities')";

    if (!$conn->query($query)) {
        echo "error: " . $conn->error;
        exit;
    }

    $property_id = $conn->insert_id; // used for images table

    // Handle image uploads
    if (!empty($_FILES['property_images']['name'][0])) {
        $uploadDir = "../uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        foreach ($_FILES['property_images']['tmp_name'] as $key => $tmpName) {
            $filename = time() . "_" . basename($_FILES['property_images']['name'][$key]);
            $targetFile = $uploadDir . $filename;

            if (move_uploaded_file($tmpName, $targetFile)) {
                // Save image path into property_images table
                $conn->query("INSERT INTO property_images (property_id, image_path)
                              VALUES ('$property_id', '$filename')");
            }
        }
    }

    echo "success";
}
?>
