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

$theaterId = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0;
$theaterName = "N/A";
$halls = null;
$errorMessage = '';
$successMessage = '';

if ($theaterId > 0) {
    // Fetch theater name - using lowercase quoted column name
    $stmtTheaterQuery = "SELECT \"theatername\" FROM theaters WHERE \"theaterid\" = $1";
    $stmtTheaterResult = pg_query_params($conn, $stmtTheaterQuery, array($theaterId));
    if ($stmtTheaterResult && pg_num_rows($stmtTheaterResult) > 0) {
        $rowTheater = pg_fetch_assoc($stmtTheaterResult);
        $theaterName = $rowTheater['theatername'];
    } else {
        $errorMessage = "Theater not found for ID: " . $theaterId;
        $theaterId = 0; // Invalidate theaterId if not found
    }

    // Handle hall deletion
    if (isset($_GET['delete_hall']) && is_numeric($_GET['delete_hall'])) {
        $hallId = $_GET['delete_hall'];

        // Check for dependencies (schedules) before deleting hall - using lowercase quoted column name
        $checkSchedulesQuery = "SELECT COUNT(*) as count FROM movie_schedules WHERE \"hallid\" = $1";
        $checkSchedulesResult = pg_query_params($conn, $checkSchedulesQuery, array($hallId));
        $schedulesCount = pg_fetch_assoc($checkSchedulesResult)['count'];

        if ($schedulesCount > 0) {
            $errorMessage = "Cannot delete hall. It is associated with " . $schedulesCount . " schedule(s). Please delete all associated schedules first.";
        } else {
            // Get hall panorama image path before deletion to remove the file - using corrected lowercase quoted column name
            $stmtImgQuery = "SELECT \"hallpanoramaimg\" FROM theater_halls WHERE \"hallid\" = $1";
            $stmtImgResult = pg_query_params($conn, $stmtImgQuery, array($hallId));
            $hallPanoramaImgPath = null;
            if ($row = pg_fetch_assoc($stmtImgResult)) {
                $hallPanoramaImgPath = $row['hallpanoramaimg'];
            }

            // Delete hall - using lowercase quoted column names
            $deleteHallQuery = "DELETE FROM theater_halls WHERE \"hallid\" = $1 AND \"theaterid\" = $2";
            $deleteHallResult = pg_query_params($conn, $deleteHallQuery, array($hallId, $theaterId));
            
            if ($deleteHallResult) {
                $successMessage = "Hall deleted successfully!";
                // Delete the associated image file if it exists
                if ($hallPanoramaImgPath && file_exists("../" . $hallPanoramaImgPath)) { // Path from user/ to project root
                    if (strpos($hallPanoramaImgPath, 'img/panoramas/') === 0 && realpath("../" . $hallPanoramaImgPath)) {
                        unlink("../" . $hallPanoramaImgPath);
                    }
                }
            } else {
                $errorMessage = "Error deleting hall: " . pg_last_error($conn);
            }
        }
    }

    // Handle hall addition
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_hall'])) {
        $hallName = $_POST['hallName'];
        $hallType = $_POST['hallType'];
        $totalSeats = $_POST['totalSeats'];
        $hallStatus = $_POST['hallStatus'];
        
        $hallPanoramaImg = null; // Initialize to null
        $uploadOk = 1; // Flag for panorama image upload status

        // Handle panorama image upload if provided
        if (isset($_FILES["hallPanoramaImage"]) && $_FILES["hallPanoramaImage"]["error"] == UPLOAD_ERR_OK) {
            $targetDir = "../img/panoramas/"; // Path relative to theater_manager/ folder
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $fileName = basename($_FILES["hallPanoramaImage"]["name"]);
            $uniqueFileName = uniqid() . "_" . $fileName;
            $targetFilePath = $targetDir . $uniqueFileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            
            $check = @getimagesize($_FILES["hallPanoramaImage"]["tmp_name"]);
            if($check === false) { $errorMessage = "Panorama file is not a valid image."; $uploadOk = 0; }
            if($_FILES["hallPanoramaImage"]["size"] > 15000000) { $errorMessage = "Sorry, panorama file is too large (max 15MB)."; $uploadOk = 0; }
            if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" ) { $errorMessage = "Sorry, only JPG, JPEG, and PNG files are allowed for panoramas."; $uploadOk = 0; }

            if ($uploadOk == 0) {
                // Error message already set
            } else {
                if (move_uploaded_file($_FILES["hallPanoramaImage"]["tmp_name"], $targetFilePath)) {
                    $hallPanoramaImg = "img/panoramas/" . $uniqueFileName; // Path to store in database (relative to project root)
                } else {
                    $errorMessage = "Sorry, there was an error uploading the panorama file to the server.";
                    $uploadOk = 0;
                }
            }
        } else if (isset($_FILES["hallPanoramaImage"]) && $_FILES["hallPanoramaImage"]["error"] != UPLOAD_ERR_NO_FILE) {
            $errorMessage = "File upload error for panorama: " . $_FILES["hallPanoramaImage"]["error"];
            $uploadOk = 0;
        }

        if ($uploadOk !== 0) {
            // Add hall - using corrected lowercase quoted column name
            $addHallQuery = "INSERT INTO theater_halls (\"theaterid\", \"hallname\", \"halltype\", \"totalseats\", \"hallstatus\", \"hallpanoramaimg\") VALUES ($1, $2, $3, $4, $5, $6)";
            $addHallResult = pg_query_params($conn, $addHallQuery, array($theaterId, $hallName, $hallType, $totalSeats, $hallStatus, $hallPanoramaImg));
            
            if ($addHallResult) {
                $successMessage = "Hall added successfully!";
            } else {
                $errorMessage = "Error adding hall to database: " . pg_last_error($conn);
                if ($hallPanoramaImg && file_exists("../" . $hallPanoramaImg)) {
                    unlink("../" . $hallPanoramaImg); // Clean up uploaded file on DB error
                }
            }
        }
    }

    // Handle hall update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_hall'])) {
        $hallIdToUpdate = $_POST['edit_hallId'];
        $hallName = $_POST['edit_hallName'];
        $hallType = $_POST['edit_hallType'];
        $totalSeats = $_POST['edit_totalSeats'];
        $hallStatus = $_POST['edit_hallStatus'];

        $currentHallPanoramaImg = $_POST['current_hall_panorama_img'] ?? null; // Get existing path
        $newHallPanoramaImg = $currentHallPanoramaImg; // Assume current image by default
        $uploadOk = 1;

        // Handle new panorama image upload for update
        if (isset($_FILES["editHallPanoramaImage"]) && $_FILES["editHallPanoramaImage"]["error"] == UPLOAD_ERR_OK) {
            $targetDir = "../img/panoramas/";
            if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
            $fileName = basename($_FILES["editHallPanoramaImage"]["name"]);
            $uniqueFileName = uniqid() . "_" . $fileName;
            $targetFilePath = $targetDir . $uniqueFileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            $check = @getimagesize($_FILES["editHallPanoramaImage"]["tmp_name"]);
            if($check === false) { $errorMessage = "New panorama file is not a valid image."; $uploadOk = 0; }
            if($_FILES["editHallPanoramaImage"]["size"] > 15000000) { $errorMessage = "Sorry, new panorama file is too large (max 15MB)."; $uploadOk = 0; }
            if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" ) { $errorMessage = "Sorry, only JPG, JPEG, and PNG files are allowed for new panoramas."; $uploadOk = 0; }

            if ($uploadOk == 0) {
                // Error already set
            } else {
                if (move_uploaded_file($_FILES["editHallPanoramaImage"]["tmp_name"], $targetFilePath)) {
                    $newHallPanoramaImg = "img/panoramas/" . $uniqueFileName;
                    // Delete old panorama file if different and exists
                    if (!empty($currentHallPanoramaImg) && $currentHallPanoramaImg != $newHallPanoramaImg && file_exists("../" . $currentHallPanoramaImg)) {
                        if (strpos($currentHallPanoramaImg, 'img/panoramas/') === 0 && realpath("../" . $currentHallPanoramaImg)) {
                            unlink("../" . $currentHallPanoramaImg);
                        }
                    }
                } else {
                    $errorMessage = "Error uploading new panorama file for update.";
                    $uploadOk = 0;
                }
            }
        } else if (isset($_FILES["editHallPanoramaImage"]) && $_FILES["editHallPanoramaImage"]["error"] != UPLOAD_ERR_NO_FILE) {
            $errorMessage = "File upload error for new panorama: " . $_FILES["editHallPanoramaImage"]["error"];
            $uploadOk = 0;
        }

        if ($uploadOk !== 0) {
            // Update hall - using corrected lowercase quoted column name
            $updateHallQuery = "UPDATE theater_halls SET \"hallname\" = $1, \"halltype\" = $2, \"totalseats\" = $3, \"hallstatus\" = $4, \"hallpanoramaimg\" = $5 WHERE \"hallid\" = $6 AND \"theaterid\" = $7";
            $updateHallResult = pg_query_params($conn, $updateHallQuery, array($hallName, $hallType, $totalSeats, $hallStatus, $newHallPanoramaImg, $hallIdToUpdate, $theaterId));
            
            if ($updateHallResult) {
                $successMessage = "Hall updated successfully!";
            } else {
                $errorMessage = "Error updating hall in database: " . pg_last_error($conn);
                // If DB update fails, consider deleting the newly uploaded panorama file to clean up
                if ($newHallPanoramaImg != $currentHallPanoramaImg && file_exists("../" . $newHallPanoramaImg)) {
                    unlink("../" . $newHallPanoramaImg);
                }
            }
        }
    }


    // Get all halls for this theater - using lowercase quoted column name for ORDER BY
    // Re-fetch after any add/delete/update operation
    if ($theaterId > 0) {
        $hallsQuery = "SELECT * FROM theater_halls WHERE \"theaterid\" = $1 ORDER BY \"hallname\"";
        $halls = pg_query_params($conn, $hallsQuery, array($theaterId));
        if (!$halls) {
            $errorMessage .= ($errorMessage ? "<br>" : "") . "Failed to fetch halls: " . pg_last_error($conn);
            $halls = null;
        }
    } else {
        $halls = null; // No halls if theaterId is invalid
    }

} else {
    $errorMessage = "No theater ID provided. Please select a theater from the Theaters list to manage its halls.";
    $halls = null;
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Halls for <?php echo htmlspecialchars($theaterName); ?> - Showtime Select Admin</title>
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
        .table-container, .form-container {
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .hall-type-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: capitalize;
            background-color: #007bff; /* default blue */
            color: white;
        }
        .hall-type-badge.main-hall { background-color: #28a745; } /* green */
        .hall-type-badge.vip-hall { background-color: #ffc107; color: #343a40; } /* yellow */
        .hall-type-badge.private-hall { background-color: #6f42c1; } /* purple */
        .preview-image {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            display: block;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="nav-link" href="../admin/logout.php">Sign out</a> <!-- Corrected path -->
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
                            <a class="nav-link active" href="theaters.php">
                                <i class="fas fa-building"></i>
                                Theaters
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
                    <h1>Manage Halls for <?php echo htmlspecialchars($theaterName); ?></h1>
                    <div class="d-flex align-items-center">
                        <a href="theaters.php" class="btn btn-secondary mr-2">
                            <i class="fas fa-arrow-left"></i> Back to Theaters
                        </a>
                        <?php if ($theaterId > 0): ?>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addHallModal">
                            <i class="fas fa-plus"></i> Add New Hall
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($successMessage) && !empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($theaterId == 0): ?>
                    <div class="alert alert-info text-center">
                        Please select a theater from the <a href="theaters.php">Theaters list</a> to manage its halls.
                    </div>
                <?php elseif ($halls && pg_num_rows($halls) > 0): ?>
                    <div class="table-container">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Hall ID</th>
                                        <th>Hall Name</th>
                                        <th>Hall Type</th>
                                        <th>Total Seats</th>
                                        <th>Panorama</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($hall = pg_fetch_assoc($halls)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($hall['hallid']); ?></td>
                                            <td><?php echo htmlspecialchars($hall['hallname']); ?></td>
                                            <td>
                                                <span class="hall-type-badge <?php echo htmlspecialchars($hall['halltype']); ?>">
                                                    <?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($hall['halltype']))); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($hall['totalseats']); ?></td>
                                            <td>
                                                <?php if (!empty($hall['hallpanoramaimg'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($hall['hallpanoramaimg']); ?>" alt="Panorama" class="preview-image" style="max-width: 50px; max-height: 50px;">
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $hall['hallstatus'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($hall['hallstatus'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning edit-hall" 
                                                        data-id="<?php echo htmlspecialchars($hall['hallid']); ?>"
                                                        data-name="<?php echo htmlspecialchars($hall['hallname']); ?>"
                                                        data-type="<?php echo htmlspecialchars($hall['halltype']); ?>"
                                                        data-seats="<?php echo htmlspecialchars($hall['totalseats']); ?>"
                                                        data-status="<?php echo htmlspecialchars($hall['hallstatus']); ?>"
                                                        data-panorama="<?php echo htmlspecialchars($hall['hallpanoramaimg'] ?? ''); ?>"
                                                        data-toggle="modal" data-target="#editHallModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="theater_halls.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>&delete_hall=<?php echo htmlspecialchars($hall['hallid']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this hall? This will also delete associated schedules and bookings!')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        No halls found for <?php echo htmlspecialchars($theaterName); ?>. Click "Add New Hall" to get started.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add Hall Modal -->
    <div class="modal fade" id="addHallModal" tabindex="-1" role="dialog" aria-labelledby="addHallModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addHallModalLabel">Add New Hall to <?php echo htmlspecialchars($theaterName); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="theater_halls.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="hallName">Hall Name</label>
                            <input type="text" class="form-control" id="hallName" name="hallName" required>
                        </div>
                        <div class="form-group">
                            <label for="hallType">Hall Type</label>
                            <select class="form-control" id="hallType" name="hallType" required>
                                <option value="main-hall">Main Hall</option>
                                <option value="vip-hall">VIP Hall</option>
                                <option value="private-hall">Private Hall</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="totalSeats">Total Seats</label>
                            <input type="number" class="form-control" id="totalSeats" name="totalSeats" required min="1">
                        </div>
                        <div class="form-group">
                            <label for="hallStatus">Status</label>
                            <select class="form-control" id="hallStatus" name="hallStatus" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hallPanoramaImage">Hall Panorama Image (Optional)</label>
                            <input type="file" class="form-control-file" id="hallPanoramaImage" name="hallPanoramaImage" onchange="previewHallPanorama(this, 'addHallPanoramaPreview')">
                            <img id="addHallPanoramaPreview" class="preview-image" style="display: none;" src="#" alt="Panorama Preview">
                            <small class="form-text text-muted">Upload a 360-degree panorama image for this hall (JPG, PNG, max 15MB).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="add_hall" class="btn btn-primary">Add Hall</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Hall Modal -->
    <div class="modal fade" id="editHallModal" tabindex="-1" role="dialog" aria-labelledby="editHallModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editHallModalLabel">Edit Hall</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="theater_halls.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="edit_hallId" name="edit_hallId">
                        <input type="hidden" id="current_hall_panorama_img" name="current_hall_panorama_img">
                        <div class="form-group">
                            <label for="edit_hallName">Hall Name</label>
                            <input type="text" class="form-control" id="edit_hallName" name="edit_hallName" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_hallType">Hall Type</label>
                            <select class="form-control" id="edit_hallType" name="edit_hallType" required>
                                <option value="main-hall">Main Hall</option>
                                <option value="vip-hall">VIP Hall</option>
                                <option value="private-hall">Private Hall</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_totalSeats">Total Seats</label>
                            <input type="number" class="form-control" id="edit_totalSeats" name="edit_totalSeats" required min="1">
                        </div>
                        <div class="form-group">
                            <label for="edit_hallStatus">Status</label>
                            <select class="form-control" id="edit_hallStatus" name="edit_hallStatus" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editHallPanoramaImage">Hall Panorama Image (Optional)</label>
                            <!-- Image preview for edit modal -->
                            <img id="editHallPanoramaPreview" class="preview-image" style="display: none;" src="#" alt="Panorama Preview">
                            <input type="file" class="form-control-file" id="editHallPanoramaImage" name="editHallPanoramaImage" onchange="previewHallPanorama(this, 'editHallPanoramaPreview')">
                            <small class="form-text text-muted">Upload a new 360-degree panorama image for this hall (JPG, PNG, max 15MB).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="update_hall" class="btn btn-primary">Update Hall</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Function to preview image for both add and edit modals
        function previewHallPanorama(input, previewId) {
            var preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
                preview.src = '#'; // Clear src
            }
        }

        // Fill edit modal with hall data
        $('.edit-hall').click(function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var type = $(this).data('type');
            var seats = $(this).data('seats');
            var status = $(this).data('status');
            var panorama = $(this).data('panorama'); // Get panorama path

            $('#edit_hallId').val(id);
            $('#edit_hallName').val(name);
            $('#edit_hallType').val(type);
            $('#edit_totalSeats').val(seats);
            $('#edit_hallStatus').val(status);
            $('#current_hall_panorama_img').val(panorama); // Set current panorama path in hidden field

            var editPreview = document.getElementById('editHallPanoramaPreview');
            if (panorama) {
                editPreview.src = '../' + panorama; // Adjust path for display
                editPreview.style.display = 'block';
            } else {
                editPreview.style.display = 'none';
                editPreview.src = '#';
            }
        });
    </script>
</body>
</html>
