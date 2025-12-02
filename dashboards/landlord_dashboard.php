<?php
session_start();
include("../config/db.php");

// Role normalization
$role = strtolower(trim($_SESSION["role"] ?? ''));
if ($role !== 'landlord') {
    header("Location: ../index.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the landlord record
$landlord_query = "SELECT id FROM landlords WHERE user_id = '$user_id'";
$landlord_result = $conn->query($landlord_query);
if ($landlord_result->num_rows === 0) {
    echo "Landlord profile not found.";
    exit();
}
$landlord = $landlord_result->fetch_assoc();
$landlord_id = $landlord['id'];

// Fetch counts
$totalProperties = $conn->query("SELECT COUNT(*) AS total FROM properties WHERE landlord_id = $landlord_id")->fetch_assoc()['total'];
$totalReservations = $conn->query("SELECT COUNT(*) AS total FROM reservations r JOIN properties p ON r.property_id = p.id WHERE p.landlord_id = $landlord_id")->fetch_assoc()['total'];
$totalPending = $conn->query("SELECT COUNT(*) AS total FROM reservations r JOIN properties p ON r.property_id = p.id WHERE p.landlord_id = $landlord_id AND r.status='pending'")->fetch_assoc()['total'];
$totalApproved = $conn->query("SELECT COUNT(*) AS total FROM reservations r JOIN properties p ON r.property_id = p.id WHERE p.landlord_id = $landlord_id AND r.status='approved'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Landlord Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body { min-height: 100vh; display: flex; flex-direction: column; }

    .navbar{
        z-index: 1100;
    }

    /* Sidebar */
    .sidebar {
        min-width: 260px;
        background-color: #ffffff; /* darker shade than navbar */
        padding-top: 20px 0;
        height: calc(100vh - 56px); /* full height minus navbar */
        position: fixed;
        z-index: 1000;
        top: 56px; /* same height as navbar */
        left: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
        border-right: 1px solid #dee2e6;
        box-shadow: 2px 0 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
        /* When collapsed */
    .sidebar.collapsed {
        transform: translateX(-260px);
    }
        .sidebar .brand {
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        color: #12be82;
        margin-bottom: 10px;
        }
        .sidebar ul {
        list-style: none;
        padding: 0;
        margin-top: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-evenly;
        }
        .sidebar ul li {
        margin: 2px 10px;
        }
        .sidebar ul li a {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        text-decoration: none;
        color: #212529;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.25s ease;
        position: relative;
        }

        .sidebar ul li a:hover {
        background-color: #f2f2f2;
        color: #12be82;
        }
    
        .sidebar ul li a.active {
        background-color: #000;
        color: #fff !important;
        font-weight: 600;
        }
        .sidebar .badge {
        font-size: 0.75rem;
        border-radius: 10px;
        padding: 3px 7px;
        position: absolute;
        right: 12px;
        }

        .add-btn-container {
        margin-top: auto;
        padding: 20px;
        }
        .add-btn {
        background-color: #000;
        color: #fff;
        padding: 12px 20px;
        border-radius: 10px;
        text-align: center;
        display: block;
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.3s;
        }
        .add-btn:hover {
        background-color: #12be82;
        color: #fff;
        }

        /*main content */
        .main-content {
            padding: 20px 40px; /* space on top/bottom and left/right */
            background-color: #f8f9fa; /* optional: light background for contrast */
            min-height: 100vh;
            box-sizing: border-box; /* ensures padding is included in width */
            transition: all 0.3s ease;
        }
        .drag-area {
            border: 2px dashed rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 32px;
            background: rgba(0,0,0,0.03);
            cursor: pointer;
            transition: .2s;
        }

        .drag-area:hover {
            border-color: rgba(0,0,0,0.4);
            background: rgba(0,0,0,0.05);
        }

        .drag-area.drag-over {
            border-color: #12be82;
            background: rgba(18, 190, 130, 0.1);
        }

        .drag-area input {
            cursor: pointer;
        }
        
        /* Ensure modal displays above the fixed navbar/sidebar which use high z-index values */
        .modal-backdrop {
            z-index: 1110 !important;
        }
        .modal {
            z-index: 1120 !important;
        }

        /* Modal dialog sizing for this specific modal */
        #addPropertyModal .modal-dialog {
            max-width: 50%; /* Adjust as needed (e.g. 50%, 700px, etc.) */
            margin: auto; /* Center the modal */
        }

        .upload-box {
            border: 2px dashed rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 32px;
            background: rgba(0,0,0,0.03);
            cursor: pointer;
            transition: .2s;
        }

        .upload-box:hover {
            border-color: rgba(0,0,0,0.4);
            background: rgba(0,0,0,0.05);
        }

        .drag-area input {
            cursor: pointer;
        }
        /* Ensure modal displays above the fixed navbar/sidebar which use high z-index values */
        .modal-backdrop {
            z-index: 1110 !important;
        }
        .modal {
            z-index: 1120 !important;
        }


        .upload-box:hover {
            border-color: rgba(0,0,0,0.4);
            background: rgba(0,0,0,0.05);
        }

        .drag-area input {
            cursor: pointer;
        }
       


</style>
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
            <div class="container-fluid px-4">
                <!--Sidebar Toogle Button-->
                <button class="btn btn-outline-secondary me-3" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <!-- Brand Logo -->
                <a class="navbar-brand fw-bold text-primary d-flex align-items-center" href="index.html">
                    <img src="#" alt="Logo" height="35" class="me-2" onerror="this.style.display='none'">
                    <span class="d-none d-sm-inline">HousingPortal</span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link active fw-semibold" aria-current="page" href="../index.html">Home</a>
                        </li>
                    </ul>

                </div>
            </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        
        <ul class="nav flex-column mt-4">
            <li><a href="javascript:void(0);" class="active" onclick="loadSection('overview')"><i class="bi bi-house-door me-2"></i>Overview</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('my_properties')"><i class="bi bi-building me-2"></i>My Properties</a></li>
        <li><a href="javascript:void(0);" onclick="loadSection('manage_reservations')"><i class="bi bi-calendar me-2"></i>Reservations</a></li>

            <li><a href="javascript:void(0);" onclick="loadSection('inquiries')"><i class="bi bi-envelope me-2"></i>Inquiries</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('analytics')"><i class="bi bi-bar-chart me-2"></i>Analytics</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('income_reports')"><i class="bi bi-cash-coin me-2"></i>Income Reports</a></li>
            <li><a href="javascript:void(0);" onclick="loadSection('availability')"><i class="bi bi-calendar2-week me-2"></i>Availability</a></li>

            <!-- Divider line-->
            <hr class="my-3 mx-3">

            <li><a href="javascript:void(0);" onclick="loadSection('landlord_profile')"><i class="bi bi-person me-2"></i>Profile</a></li>
        </ul>

        <div class="add-btn-container mt-auto">
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                <i class="bi bi-plus-lg me-1"></i>Add New House
            </a>
        </div>

    </div>

    <!-- Main Content -->
    <div class="main-content" id="content">
        <!--Section content will be loaded here-->
    </div>

    <!-- Add Property Modal -->
     <div class="modal fade" id="addPropertyModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content shadow-lg border-0" style="border-radius: 14px;">
                
                <!-- Modal Header -->
                <div class="modal-header border-0">
                    <h4 class=" fw-bold mb-0">Post New Housing Listing</h4 >
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">

                    <p class="text-muted">Add a new housing property.</p>

                    <!--Form fields-->
                    <form id="propertyForm" method="POST" enctype="multipart/form-data" action="add_property.php">
                        <!--Image Upload-->
                        <div class="upload-box mb-4">
                            <div class="drag-area text-center">
                                <i class="bi bi-cloud-arrow-up fs-1"></i>
                                <p>Drag & drop your images here, or click to select files</p>
                                <small class="text-muted">Up to 5 images (JPG, PNG, GIF)</small>
                                <input type="file" id="imageUploadInput" name="property_images[]" multiple accept="image/*" class="form-control mt-3">
                            </div>
                            <div id="selectedImages"></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="fw-semibold">Property Title*</label>
                                <input type="text" name="title" id="propertyTitle" class="form-control" placeholder="e.g., Student Room Near University" required>
                            </div>

                            <div class="col-md-12">
                                <label class="fw-semibold">Address*</label>
                                <input type="text" name="address" id="propertyAddress" class="form-control" placeholder="Street Address" required>
                            </div>

                            <div class="col-md-6">
                                <label class="fw-semibold">City*</label>
                                <input type="text" name="city" id="propertyCity" class="form-control" placeholder="City" required>
                            </div>

                            <div class="col-md-6">
                                <label class="fw-semibold">Monthly Rent (KES)*</label>
                                <input type="number" name="rent" id="propertyRent" class="form-control" placeholder="e.g., 5,000" required>
                            </div>

                            <div class="col-md-6">
                                <label class="fw-semibold">Property Type*</label>
                                <select name="type" id="propertyType" class="form-select" required>
                                    <option value="" disabled selected>All type</option>
                                    <option value="Sstudio">Studio</option>
                                    <option value="bedsitter">Bedsitter</option>
                                    <option value="apartment">Apartment</option>
                                    <option value="single_room">Single Room</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="fw-semibold">Bedrooms</label>
                                <input type="number" name="bedrooms" id="propertyBedrooms" class="form-control" placeholder="e.g., 2" required>
                            </div>

                            <div class="col-md-6">
                                <label class="fw-semibold">Area (sq ft)*</label>
                                <input type="number" name="area" id="propertyArea" class="form-control" placeholder="950">
                            </div>

                            <div class="col-md-6">
                                <label class="fw-semibold">Status *</label>
                                <select name="status" id="propertyStatus" class="form-select" required>
                                    <option value="Available">Available</option>
                                    <option value="Reserved">Reserved</option>
                                    <option value="Unavailable">Unavailable</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="fw-semibold">Description</label>
                                <textarea name="description" id="propertyDescription" class="form-control" rows="3" placeholder="Describe your property (ideal for students, distance to campus, etc.)"></textarea>
                            </div>

                            <div class="col-12 mt-3">
                                <label class="fw-semibold d-block">Amenities</label>
                                <div class="row">
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="WiFi"> WiFi</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Kitchen"> Kitchen</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Study Desk"> Study Desk</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Water Included"> Water Included</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Public Transport"> Public Transport</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Parking"> Parking</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Furnished"> Furnished</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Shared Kitchen"> Shared Kitchen</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Electricity Included"> Electricity Included</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Quiet Area"> Quiet Area</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="AC"> AC</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Washer/Dryer"> Washer/Dryer</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Security"> Security</div>
                                        <div class="col-6 col-md-4"><input type="checkbox" name="amenities[]" value="Close to Campus"> Close to Campus</div>
                                    </div>   
                                </div>
                        </div>

                        <!-- Hidden input for landlord_id -->
                        <input type="hidden" name="landlord_id" value="<?php echo $landlord_id; ?>">

                    </form>

                </div>
                <!-- Modal Footer -->
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="propertyForm" class="btn btn-dark px-4" id="submitProperty">Post Listing</button>
                </div>

            </div>

        </div>

    </div>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle').addEventListener('click', function () {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    sidebar.classList.toggle('collapsed');

    if (sidebar.classList.contains('collapsed')) {
        mainContent.style.marginLeft = "0";  // Adjust content width when collapsed
    } else {
        mainContent.style.marginLeft = "260px"; // Same width as sidebar
    }
});
</script>

<script>
// Load a section into the #content area via AJAX
function loadSection(section) {
    const content = document.getElementById('content');
    // Update active link state
    document.querySelectorAll('.sidebar ul li a').forEach(a => a.classList.remove('active'));
    const selector = `.sidebar ul li a[onclick="loadSection('${section}')"]`;
    const activeLink = document.querySelector(selector);
    if (activeLink) activeLink.classList.add('active');

    // Show a loading placeholder
    content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

    fetch('sections/' + section + '.php')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            content.innerHTML = html;
            
            // Execute scripts in the loaded content
            const scripts = content.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = '<div class="alert alert-danger">Failed to load section. Please try again.</div>';
        });
}

// Ensure main content has left margin to accommodate sidebar on initial load
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    if (!sidebar.classList.contains('collapsed')) {
        mainContent.style.marginLeft = "260px";
    } else {
        mainContent.style.marginLeft = "0";
    }

    // Load default section
    loadSection('overview');
});
</script>

<script>
let selectedFiles = [];

const dragArea = document.querySelector('.drag-area');
const fileInput = document.getElementById('imageUploadInput');

// Prevent default drag behaviors
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dragArea.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});

// Highlight drop area when item is dragged over it
['dragenter', 'dragover'].forEach(eventName => {
    dragArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dragArea.addEventListener(eventName, unhighlight, false);
});

// Handle dropped files
dragArea.addEventListener('drop', handleDrop, false);

// Handle click to open file dialog
dragArea.addEventListener('click', () => {
    fileInput.click();
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    dragArea.classList.add('drag-over');
}

function unhighlight(e) {
    dragArea.classList.remove('drag-over');
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles(files);
}

function handleFiles(files) {
    const fileArray = Array.from(files);
    // Add new files to the selectedFiles array
    selectedFiles = selectedFiles.concat(fileArray);
    updateSelectedImagesList();
}

fileInput.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    // Add new files to the selectedFiles array
    selectedFiles = selectedFiles.concat(files);
    updateSelectedImagesList();
    // Reset the input to allow selecting the same files again or fresh selection
    fileInput.value = '';
});

function updateSelectedImagesList() {
    const selectedImagesDiv = document.getElementById('selectedImages');
    selectedImagesDiv.innerHTML = '';

    if (selectedFiles.length > 0) {
        const list = document.createElement('ul');
        list.className = 'list-group list-group-flush';

        selectedFiles.forEach((file, i) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            const span = document.createElement('span');
            span.textContent = file.name;
            li.appendChild(span);
            const removeBtn = document.createElement('button');
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.textContent = 'Remove';
            removeBtn.onclick = () => {
                selectedFiles.splice(i, 1);
                updateSelectedImagesList();
            };
            li.appendChild(removeBtn);
            list.appendChild(li);
        });

        selectedImagesDiv.appendChild(list);
    }
}
</script>

<script>
document.getElementById("propertyForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const formData = new FormData();

    // Append all form fields manually, excluding file inputs
    const form = this;
    const inputs = form.querySelectorAll('input:not([type="file"]), select, textarea');
    inputs.forEach(input => {
        if (input.type === 'checkbox') {
            if (input.checked) {
                formData.append(input.name, input.value);
            }
        } else {
            formData.append(input.name, input.value);
        }
    });

    // Append selected files to formData
    selectedFiles.forEach((file, index) => {
        formData.append('property_images[]', file);
    });

    fetch("add_property.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if (data.trim() === "success") {
            alert("Property added successfully!");
            document.getElementById("propertyForm").reset();
            selectedFiles = [];
            updateSelectedImagesList();
            const modal = bootstrap.Modal.getInstance(document.getElementById("addPropertyModal"));
            modal.hide();

            // Reload the current section if it's my_properties, otherwise reload overview
            const activeLink = document.querySelector('.sidebar ul li a.active');
            if (activeLink && activeLink.getAttribute('onclick') && activeLink.getAttribute('onclick').includes('my_properties')) {
                loadSection('my_properties');
            } else if (activeLink && activeLink.getAttribute('onclick') && activeLink.getAttribute('onclick').includes('overview')) {
                loadSection('overview');
            }
        } else {
            alert("Error: " + data);
        }
    })
    .catch(err => {
        alert("Something went wrong: " + err);
    });
});
</script>

</body>
</html>


