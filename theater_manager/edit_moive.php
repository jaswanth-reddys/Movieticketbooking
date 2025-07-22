<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Content Manager (roleID 3)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 3)) {
    header("Location: index.php");
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

$movieId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$movie = null;
$errorMessage = '';
$successMessage = '';

if ($movieId > 0) {
    // Fetch current movie details
    $stmtQuery = "SELECT m.*, l.locationname FROM movietable m LEFT JOIN locations l ON m.locationid = l.locationid WHERE m.movieid = $1";
    $stmtResult = pg_query_params($conn, $stmtQuery, array($movieId));
    if ($stmtResult && pg_num_rows($stmtResult) > 0) {
        $movie = pg_fetch_assoc($stmtResult);
        // Convert keys to lowercase for consistency with PostgreSQL's default behavior
        $movie = array_change_key_case($movie, CASE_LOWER);
    } else {
        $errorMessage = "Movie not found.";
    }
} else {
    $errorMessage = "Invalid movie ID provided.";
}

// Get all locations for dropdown
$locationsQuery = "SELECT locationid, locationname FROM locations WHERE locationstatus = 'active' ORDER BY locationname";
$locations = pg_query($conn, $locationsQuery);
if (!$locations) {
    die("Error fetching locations: " . pg_last_error($conn));
}

// Process form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_movie'])) {
    if (!$movie) { // If movie wasn't found initially, don't proceed
        $errorMessage = "Cannot update: Movie not found.";
    } else {
        $movieTitle = $_POST['movieTitle'];
        $movieGenre = $_POST['movieGenre'];
        $movieDuration = $_POST['movieDuration'];
        $movieRelDate = $_POST['movieRelDate'];
        $movieDirector = $_POST['movieDirector'];
        $movieActors = $_POST['movieActors'];
        $locationID = $_POST['locationID'] ?: null; // Can be null
        $mainHall = $_POST['mainHall'] ?: 0;
        $vipHall = $_POST['vipHall'] ?: 0;
        $privateHall = $_POST['privateHall'] ?: 0;

        $movieImg = $movie['movieimg']; // Keep existing image by default

        // Handle file upload if a new image is provided
        if (isset($_FILES["movieImage"]) && $_FILES["movieImage"]["error"] == UPLOAD_ERR_OK) {
            $targetDir = "../img/"; // Path relative to admin folder
            $fileName = basename($_FILES["movieImage"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            $uploadOk = 1;

            // Basic image validation
            $check = getimagesize($_FILES["movieImage"]["tmp_name"]);
            if($check === false) { $errorMessage = "File is not an image."; $uploadOk = 0; }
            if($_FILES["movieImage"]["size"] > 5000000) { $errorMessage = "Sorry, your file is too large (max 5MB)."; $uploadOk = 0; }
            if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" && $fileType != "gif" ) { $errorMessage = "Sorry, only JPG, JPEG, PNG & GIF files are allowed."; $uploadOk = 0; }

            if ($uploadOk == 0) {
                // Do not proceed with update if new image upload fails
                $errorMessage = "Image upload failed: " . $errorMessage;
            } else {
                if (move_uploaded_file($_FILES["movieImage"]["tmp_name"], $targetFilePath)) {
                    $movieImg = "img/" . $fileName; // Update image path
                    // Optional: Delete old image file if it exists and is different
                    if (!empty($movie['movieimg']) && $movie['movieimg'] != $movieImg && file_exists("../" . $movie['movieimg'])) {
                        unlink("../" . $movie['movieimg']);
                    }
                } else {
                    $errorMessage = "Sorry, there was an error uploading the new image.";
                }
            }
        }
        
        // Only proceed with database update if no image upload error occurred
        if (empty($errorMessage) || strpos($errorMessage, "Image upload failed") === false) {
            $updateQuery = "UPDATE movietable SET movieimg = $1, movietitle = $2, moviegenre = $3, movieduration = $4, moviereldate = $5, moviedirector = $6, movieactors = $7, locationid = $8, mainhall = $9, viphall = $10, privatehall = $11 WHERE movieid = $12";
            $updateResult = pg_query_params($conn, $updateQuery, array($movieImg, $movieTitle, $movieGenre, $movieDuration, $movieRelDate, $movieDirector, $movieActors, $locationID, $mainHall, $vipHall, $privateHall, $movieId));

            if ($updateResult) {
                $successMessage = "Movie updated successfully!";
                // Refresh movie data after update
                $stmtQuery = "SELECT m.*, l.locationname FROM movietable m LEFT JOIN locations l ON m.locationid = l.locationid WHERE m.movieid = $1";
                $stmtResult = pg_query_params($conn, $stmtQuery, array($movieId));
                $movie = pg_fetch_assoc($stmtResult); // Update $movie variable with new data
                $movie = array_change_key_case($movie, CASE_LOWER);
            } else {
                $errorMessage = "Error updating movie: " . pg_last_error($conn);
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
    <title>Edit Movie - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg">
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
        .movie-image-preview {
            max-width: 200px;
            max-height: 300px;
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
                <a class="nav-link" href="logout.php">Sign out</a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
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
                        <li class="nav-item">
                            <a class="nav-link" href="theaters.php">
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
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Edit Movie: <?php echo htmlspecialchars($movie['movietitle'] ?? 'N/A'); ?></h1>
                    <a href="movies.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Movies
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

                <?php if ($movie): ?>
                    <div class="form-container">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="movieID" value="<?php echo htmlspecialchars($movie['movieid']); ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="movieTitle">Movie Title</label>
                                        <input type="text" class="form-control" id="movieTitle" name="movieTitle" value="<?php echo htmlspecialchars($movie['movietitle']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="movieGenre">Genre</label>
                                        <input type="text" class="form-control" id="movieGenre" name="movieGenre" value="<?php echo htmlspecialchars($movie['moviegenre']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="movieDuration">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="movieDuration" name="movieDuration" value="<?php echo htmlspecialchars($movie['movieduration']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="movieRelDate">Release Date</label>
                                        <input type="date" class="form-control" id="movieRelDate" name="movieRelDate" value="<?php echo htmlspecialchars($movie['moviereldate']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="movieDirector">Director</label>
                                        <input type="text" class="form-control" id="movieDirector" name="movieDirector" value="<?php echo htmlspecialchars($movie['moviedirector']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="movieActors">Actors</label>
                                        <input type="text" class="form-control" id="movieActors" name="movieActors" value="<?php echo htmlspecialchars($movie['movieactors']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="locationID">Location</label>
                                        <select class="form-control" id="locationID" name="locationID">
                                            <option value="">Select Location (Optional)</option>
                                            <?php
                                            // Reset pointer for locations query
                                            if (pg_num_rows($locations) > 0) {
                                                pg_result_seek($locations, 0);
                                            }
                                            while ($location = pg_fetch_assoc($locations)): ?>
                                                <option value="<?php echo htmlspecialchars($location['locationid']); ?>" <?php echo ($movie['locationid'] == $location['locationid']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($location['locationname']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="movieImage">Movie Poster</label>
                                        <?php if (!empty($movie['movieimg'])): ?>
                                            <img id="current_preview" src="../<?php echo htmlspecialchars($movie['movieimg']); ?>" alt="Current Poster" class="movie-image-preview">
                                        <?php else: ?>
                                            <img id="current_preview" src="https://placehold.co/200x300/cccccc/333333?text=No+Img" alt="No Current Poster" class="movie-image-preview">
                                        <?php endif; ?>
                                        <input type="file" class="form-control-file mt-2" id="movieImage" name="movieImage" onchange="previewNewImage(this)">
                                        <small class="form-text text-muted">Upload a new image to replace the current one.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hall Price inputs -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mainHall">Main Hall Price</label>
                                        <input type="number" step="0.01" class="form-control" id="mainHall" name="mainHall" value="<?php echo htmlspecialchars($movie['mainhall']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="vipHall">VIP Hall Price</label>
                                        <input type="number" step="0.01" class="form-control" id="vipHall" name="vipHall" value="<?php echo htmlspecialchars($movie['viphall']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="privateHall">Private Hall Price</label>
                                        <input type="number" step="0.01" class="form-control" id="privateHall" name="privateHall" value="<?php echo htmlspecialchars($movie['privatehall']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="submit" name="update_movie" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Movie
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function previewNewImage(input) {
            var preview = document.getElementById('current_preview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                // If no new file selected, revert to current image or placeholder
                preview.src = "<?php echo !empty($movie['movieimg']) ? '../' . htmlspecialchars($movie['movieimg']) : 'https://placehold.co/200x300/cccccc/333333?text=No+Img'; ?>";
            }
        }
    </script>
</body>
</html>
