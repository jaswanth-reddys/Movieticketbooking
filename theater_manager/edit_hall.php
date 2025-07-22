<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
    exit();
}

// Database connection details for PostgreSQL
$host = "dpg-d1gk4s7gi27c73brav8g-a.oregon-postgres.render.com";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select";
$port = "5432";

// Construct the connection string
$conn_string = "host={$host} port={$port} dbname={$database} user={$username} password={$password} sslmode=require";
// Establish PostgreSQL connection
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

$hallId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$theaterId = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0; // Needed for redirection and context
$hall = null;
$errorMessage = '';
$successMessage = '';

if ($hallId > 0 && $theaterId > 0) {
    // Fetch current hall details
    $stmtQuery = "SELECT * FROM theater_halls WHERE hallid = $1 AND theaterid = $2";
    $stmtResult = pg_query_params($conn, $stmtQuery, array($hallId, $theaterId));
    if ($stmtResult && pg_num_rows($stmtResult) > 0) {
        $hall = pg_fetch_assoc($stmtResult);
        // Convert keys to lowercase for consistency with PostgreSQL's default behavior
        $hall = array_change_key_case($hall, CASE_LOWER);
    } else {
        $errorMessage = "Hall not found for the given IDs.";
    }
} else {
    $errorMessage = "Invalid hall or theater ID provided.";
}

// Process form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_hall'])) {
    if (!$hall) { // If hall wasn't found initially, don't proceed
        $errorMessage = "Cannot update: Hall not found or invalid.";
    } else {
        $hallName = $_POST['hallName'];
        $hallType = $_POST['hallType'];
        $totalSeats = $_POST['totalSeats'];
        $hallStatus = $_POST['hallStatus'];

        $currentHallPanoramaImg = $hall['hallpanoraimg']; // Keep existing image by default
        $newHallPanoramaImg = $currentHallPanoramaImg; // Assume current image by default
        $uploadOk = 1;

        // Handle new panorama image upload for update
        if (isset($_FILES["hallPanoramaImage"]) && $_FILES["hallPanoramaImage"]["error"] == UPLOAD_ERR_OK) {
            $targetDir = "../img/panoramas/"; // Path relative to theater_manager folder
            if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
            $fileName = basename($_FILES["hallPanoramaImage"]["name"]);
            $uniqueFileName = uniqid() . "_" . $fileName;
            $targetFilePath = $targetDir . $uniqueFileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            $check = @getimagesize($_FILES["hallPanoramaImage"]["tmp_name"]);
            if($check === false) { $errorMessage = "New panorama file is not a valid image."; $uploadOk = 0; }
            if($_FILES["hallPanoramaImage"]["size"] > 15000000) { $errorMessage = "Sorry, new panorama file is too large (max 15MB)."; $uploadOk = 0; }
            if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" ) { $errorMessage = "Sorry, only JPG, JPEG, and PNG files are allowed for new panoramas."; $uploadOk = 0; }

            if ($uploadOk == 0) {
                // Error already set
            } else {
                if (move_uploaded_file($_FILES["hallPanoramaImage"]["tmp_name"], $targetFilePath)) {
                    $newHallPanoramaImg = "img/panoramas/" . $uniqueFileName; // Path to store in database (relative to project root)
                    // Delete old panorama file if different and exists
                    if (!empty($currentHallPanoramaImg) && $currentHallPanoramaImg != $newHallPanoramaImg && file_exists("../" . $currentHallPanoramaImg)) {
                        // Ensure the path is within the expected img directory to prevent directory traversal
                        if (strpos($currentHallPanoramaImg, 'img/panoramas/') === 0 && realpath("../" . $currentHallPanoramaImg)) {
                            unlink("../" . $currentHallPanoramaImg);
                        }
                    }
                } else {
                    $errorMessage = "Error uploading new panorama file for update.";
                    $uploadOk = 0;
                }
            }
        } else if (isset($_FILES["hallPanoramaImage"]) && $_FILES["hallPanoramaImage"]["error"] != UPLOAD_ERR_NO_FILE) {
            $errorMessage = "File upload error for new panorama: " . $_FILES["hallPanoramaImage"]["error"];
            $uploadOk = 0;
        }

        // Only proceed with database update if no image upload error occurred
        if (empty($errorMessage) || strpos($errorMessage, "File upload error") === false) {
            $updateQuery = "UPDATE theater_halls SET hallname = $1, halltype = $2, totalseats = $3, hallstatus = $4, hallpanoraimg = $5 WHERE hallid = $6 AND theaterid = $7";
            $updateResult = pg_query_params($conn, $updateQuery, array($hallName, $hallType, $totalSeats, $hallStatus, $newHallPanoramaImg, $hallId, $theaterId));

            if ($updateResult) {
                $successMessage = "Hall updated successfully!";
                // Refresh hall data after update
                $stmtQuery = "SELECT * FROM theater_halls WHERE hallid = $1 AND theaterid = $2";
                $stmtResult = pg_query_params($conn, $stmtQuery, array($hallId, $theaterId));
                $hall = pg_fetch_assoc($stmtResult); // Update $hall variable with new data
                $hall = array_change_key_case($hall, CASE_LOWER);
            } else {
                $errorMessage = "Error updating hall: " . pg_last_error($conn);
                // If DB update fails, consider deleting the newly uploaded image file to clean up
                if ($newHallPanoramaImg != $currentHallPanoramaImg && file_exists("../" . $newHallPanoramaImg)) {
                    unlink("../" . $newHallPanoramaImg);
                }
            }
        }
    }
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hall - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg"> <!-- Adjusted path -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            color: #fff;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #ced4da;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        .navbar .form-control {
            padding: .75rem 1rem;
            border-width: 0;
            border-radius: 0;
        }
        .form-control-dark {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .1);
        }
        .form-control-dark:focus {
            border-color: transparent;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, .25);
        }
        .main-content {
            margin-left: 240px;
            padding: 20px;
        }
        .form-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .panorama-image-preview {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            display: block; /* Ensure it takes up space even if empty src */
            object-fit: contain;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="nav-link" href="../admin/logout.php">Sign out</a> <!-- Adjusted path -->
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Theater Management</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="theaters.php">
                                <i class="fas fa-building"></i>
                                Theaters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="theater_halls.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>">
                                <i class="fas fa-door-open"></i>
                                Halls (Theater ID: <?php echo htmlspecialchars($theaterId); ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt"></i>
                                Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-ticket-alt"></i>
                                Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <?php if ($_SESSION['admin_role'] == 1): // Only Super Admin sees these links in Theater Manager sidebar ?>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Admin Functions (Super Admin)</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/dashboard.php">
                                <i class="fas fa-home"></i>
                                Super Admin Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/users.php">
                                <i class="fas fa-users"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/settings.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/reports.php">
                                <i class="fas fa-chart-bar"></i>
                                All Reports
                            </a>
                        </li>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Content Management (Super Admin)</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../content_manager/movies.php">
                                <i class="fas fa-film"></i>
                                Movies
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Edit Hall: <?php echo htmlspecialchars($hall['hallname'] ?? 'N/A'); ?></h1>
                    <a href="theater_halls.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Halls
                    </a>
                </div>

                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($hall): ?>
                    <div class="form-container">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="hallId" value="<?php echo htmlspecialchars($hall['hallid']); ?>">
                            <input type="hidden" name="theaterId" value="<?php echo htmlspecialchars($hall['theaterid']); ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="hallName">Hall Name</label>
                                        <input type="text" class="form-control" id="hallName" name="hallName" value="<?php echo htmlspecialchars($hall['hallname']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="hallType">Hall Type</label>
                                        <select class="form-control" id="hallType" name="hallType" required>
                                            <option value="main-hall" <?php echo ($hall['halltype'] == 'main-hall') ? 'selected' : ''; ?>>Main Hall</option>
                                            <option value="vip-hall" <?php echo ($hall['halltype'] == 'vip-hall') ? 'selected' : ''; ?>>VIP Hall</option>
                                            <option value="private-hall" <?php echo ($hall['halltype'] == 'private-hall') ? 'selected' : ''; ?>>Private Hall</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="totalSeats">Total Seats</label>
                                        <input type="number" class="form-control" id="totalSeats" name="totalSeats" value="<?php echo htmlspecialchars($hall['totalseats']); ?>" required min="1">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="hallStatus">Status</label>
                                        <select class="form-control" id="hallStatus" name="hallStatus" required>
                                            <option value="active" <?php echo ($hall['hallstatus'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($hall['hallstatus'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="hallPanoramaImage">Hall Panorama Image (Optional)</label>
                                        <?php if (!empty($hall['hallpanoraimg'])): ?>
                                            <img id="current_panorama_preview" src="../<?php echo htmlspecialchars($hall['hallpanoraimg']); ?>" alt="Current Panorama" class="panorama-image-preview">
                                        <?php else: ?>
                                            <img id="current_panorama_preview" src="https://placehold.co/400x200/cccccc/333333?text=No+Panorama" alt="No Current Panorama" class="panorama-image-preview">
                                        <?php endif; ?>
                                        <input type="file" class="form-control-file mt-2" id="hallPanoramaImage" name="hallPanoramaImage" onchange="previewNewPanorama(this)">
                                        <small class="form-text text-muted">Upload a new 360-degree panorama image for this hall (JPG, PNG, max 15MB).</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="submit" name="update_hall" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Hall
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="text-center text-danger">Hall details could not be loaded. Please ensure a valid Hall ID and Theater ID are provided.</p>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function previewNewPanorama(input) {
            var preview = document.getElementById('current_panorama_preview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                // If no new file selected, revert to current image or placeholder
                preview.src = "<?php echo !empty($hall['hallpanoraimg']) ? '../' . htmlspecialchars($hall['hallpanoraimg']) : 'https://placehold.co/400x200/cccccc/333333?text=No+Panorama'; ?>";
            }
        }
    </script>
</body>
</html>
