<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Content Manager (roleID 3)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 3)) {
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

// Get all locations for dropdown
// Ensure column names are lowercase as PostgreSQL typically stores them that way
$locationsQuery = "SELECT locationid, locationname FROM locations WHERE locationstatus = 'active' ORDER BY locationname";
$locations = pg_query($conn, $locationsQuery);
if (!$locations) {
    die("Error fetching locations: " . pg_last_error($conn));
}

// Process form submission
$errorMessage = '';
$successMessage = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $movieTitle = $_POST['movieTitle'];
    $movieGenre = $_POST['movieGenre'];
    $movieDuration = $_POST['movieDuration'];
    $movieRelDate = $_POST['movieRelDate'];
    $movieDirector = $_POST['movieDirector'];
    $movieActors = $_POST['movieActors'];
    $locationID = $_POST['locationID'] ?: null; // Can be null
    $mainHall = $_POST['mainHall'] ?: 0; // Default to 0 if not set
    $vipHall = $_POST['vipHall'] ?: 0;   // Default to 0 if not set
    $privateHall = $_POST['privateHall'] ?: 0; // Default to 0 if not set
    
    // Handle file upload
    $targetDir = "../../img/"; // Path relative to content_manager folder (two levels up)
    $fileName = basename($_FILES["movieImage"]["name"]);
    $uniqueFileName = uniqid() . "_" . $fileName; // Generate a unique file name
    $targetFilePath = $targetDir . $uniqueFileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $uploadOk = 1;
    
    // Check if image file is a actual image or fake image
    if(isset($_FILES["movieImage"]["tmp_name"]) && $_FILES["movieImage"]["tmp_name"] != "") {
        $check = @getimagesize($_FILES["movieImage"]["tmp_name"]); // Use @to suppress warnings
        if($check !== false) {
            $uploadOk = 1;
        } else {
            $errorMessage = "File is not an image.";
            $uploadOk = 0;
        }
    } else {
        $errorMessage = "No image file uploaded.";
        $uploadOk = 0;
    }

    // Check file size (5MB max)
    if ($uploadOk == 1 && $_FILES["movieImage"]["size"] > 5000000) {
        $errorMessage = "Sorry, your file is too large (max 5MB).";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if($uploadOk == 1 && $fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" && $fileType != "gif" ) {
        $errorMessage = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }
    
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        if (empty($errorMessage)) { // If no specific error message yet
            $errorMessage = "Sorry, your file was not uploaded.";
        }
    } else {
        // if everything is ok, try to upload file
        if (move_uploaded_file($_FILES["movieImage"]["tmp_name"], $targetFilePath)) {
            // File uploaded successfully, now insert movie data
            $movieImg = "img/" . $uniqueFileName; // Path to store in database (relative to project root)

            // Ensure column names are lowercase in the INSERT query
            $insertQuery = "INSERT INTO movietable (movieimg, movietitle, moviegenre, movieduration, moviereldate, moviedirector, movieactors, locationid, mainhall, viphall, privatehall) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)";
            $insertResult = pg_query_params($conn, $insertQuery, array($movieImg, $movieTitle, $movieGenre, $movieDuration, $movieRelDate, $movieDirector, $movieActors, $locationID, $mainHall, $vipHall, $privateHall));
            
            if ($insertResult) {
                $successMessage = "Movie added successfully!";
                // Redirect to movies page after successful addition
                header("Location: movies.php?success=1");
                exit();
            } else {
                $errorMessage = "Error: " . pg_last_error($conn);
                // If DB insert fails, consider deleting the uploaded file to clean up
                if (file_exists($targetFilePath)) {
                    unlink($targetFilePath); 
                }
            }
        } else {
            $errorMessage = "Sorry, there was an error uploading your file.";
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
    <title>Add New Movie - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../../img/sslogo.jpg"> <!-- Adjusted path -->
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
        .preview-image {
            max-width: 200px;
            max-height: 300px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
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
                            <span>Content Management</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="movies.php">
                                <i class="fas fa-film"></i>
                                Movies
                            </a>
                        </li>
                        <?php if ($_SESSION['admin_role'] == 1): // Only Super Admin sees these links in Content Manager sidebar ?>
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
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Theater Management (Super Admin)</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/theaters.php">
                                <i class="fas fa-building"></i>
                                Theaters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/locations.php">
                                <i class="fas fa-map-marker-alt"></i>
                                Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/bookings.php">
                                <i class="fas fa-ticket-alt"></i>
                                Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Add New Movie</h1>
                    <a href="movies.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Movies
                    </a>
                </div>

                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="movieTitle">Movie Title</label>
                                    <input type="text" class="form-control" id="movieTitle" name="movieTitle" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="movieGenre">Genre</label>
                                    <input type="text" class="form-control" id="movieGenre" name="movieGenre" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="movieDuration">Duration (minutes)</label>
                                    <input type="number" class="form-control" id="movieDuration" name="movieDuration" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="movieRelDate">Release Date</label>
                                    <input type="date" class="form-control" id="movieRelDate" name="movieRelDate" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="movieDirector">Director</label>
                                    <input type="text" class="form-control" id="movieDirector" name="movieDirector" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="movieActors">Actors</label>
                                    <input type="text" class="form-control" id="movieActors" name="movieActors" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="locationID">Location</label>
                                    <select class="form-control" id="locationID" name="locationID">
                                        <option value="">Select Location (Optional)</option>
                                        <?php 
                                        // Reset location results pointer if already fetched
                                        if (pg_num_rows($locations) > 0) {
                                            pg_result_seek($locations, 0);
                                        }
                                        while ($location = pg_fetch_assoc($locations)): 
                                            // Ensure fetched keys are lowercase for consistency
                                            $location = array_change_key_case($location, CASE_LOWER);
                                        ?>
                                            <option value="<?php echo htmlspecialchars($location['locationid']); ?>">
                                                <?php echo htmlspecialchars($location['locationname']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="movieImage">Movie Poster</label>
                                    <input type="file" class="form-control-file" id="movieImage" name="movieImage" required onchange="previewImage(this)">
                                    <img id="preview" class="preview-image" style="display: none;" src="#" alt="Image Preview">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hall Price inputs -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="mainHall">Main Hall Price</label>
                                    <input type="number" step="0.01" class="form-control" id="mainHall" name="mainHall" value="0">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="vipHall">VIP Hall Price</label>
                                    <input type="number" step="0.01" class="form-control" id="vipHall" name="vipHall" value="0">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="privateHall">Private Hall Price</label>
                                    <input type="number" step="0.01" class="form-control" id="privateHall" name="privateHall" value="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Add Movie
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function previewImage(input) {
            var preview = document.getElementById('preview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
