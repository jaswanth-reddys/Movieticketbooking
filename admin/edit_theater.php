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

$theaterId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$theater = null;
$errorMessage = '';
$successMessage = '';

if ($theaterId > 0) {
    // Fetch current theater details
    $stmtQuery = "SELECT * FROM theaters WHERE theaterid = $1";
    $stmtResult = pg_query_params($conn, $stmtQuery, array($theaterId));
    if ($stmtResult && pg_num_rows($stmtResult) > 0) {
        $theater = pg_fetch_assoc($stmtResult);
        // Convert keys to lowercase for consistency with PostgreSQL's default behavior
        $theater = array_change_key_case($theater, CASE_LOWER);
    } else {
        $errorMessage = "Theater not found.";
    }
} else {
    $errorMessage = "Invalid theater ID provided.";
}

// Process form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_theater'])) {
    if (!$theater) {
        $errorMessage = "Cannot update: Theater not found.";
    } else {
        $theaterName = $_POST['theaterName'];
        $theaterAddress = $_POST['theaterAddress'];
        $theaterCity = $_POST['theaterCity'];
        $theaterState = $_POST['theaterState'];
        $theaterZipcode = $_POST['theaterZipcode'];
        $theaterPhone = $_POST['theaterPhone'];
        $theaterEmail = $_POST['theaterEmail'];
        $theaterStatus = $_POST['theaterStatus'];

        $updateQuery = "UPDATE theaters SET theatername = $1, theateraddress = $2, theatercity = $3, theaterstate = $4, theaterzipcode = $5, theaterphone = $6, theateremail = $7, theaterstatus = $8 WHERE theaterid = $9";
        $updateResult = pg_query_params($conn, $updateQuery, array($theaterName, $theaterAddress, $theaterCity, $theaterState, $theaterZipcode, $theaterPhone, $theaterEmail, $theaterStatus, $theaterId));

        if ($updateResult) {
            $successMessage = "Theater updated successfully!";
            // Refresh theater data after update
            $stmtQuery = "SELECT * FROM theaters WHERE theaterid = $1";
            $stmtResult = pg_query_params($conn, $stmtQuery, array($theaterId));
            $theater = pg_fetch_assoc($stmtResult); // Update $theater variable with new data
            $theater = array_change_key_case($theater, CASE_LOWER);
        } else {
            $errorMessage = "Error updating theater: " . pg_last_error($conn);
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
    <title>Edit Theater - Showtime Select Admin</title>
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
                    <h1>Edit Theater: <?php echo htmlspecialchars($theater['theatername'] ?? 'N/A'); ?></h1>
                    <a href="theaters.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Theaters
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

                <?php if ($theater): ?>
                    <div class="form-container">
                        <form action="" method="POST">
                            <input type="hidden" name="theaterID" value="<?php echo htmlspecialchars($theater['theaterid']); ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="theaterName">Theater Name</label>
                                        <input type="text" class="form-control" id="theaterName" name="theaterName" value="<?php echo htmlspecialchars($theater['theatername']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="theaterAddress">Address</label>
                                        <input type="text" class="form-control" id="theaterAddress" name="theaterAddress" value="<?php echo htmlspecialchars($theater['theateraddress']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="theaterCity">City</label>
                                        <input type="text" class="form-control" id="theaterCity" name="theaterCity" value="<?php echo htmlspecialchars($theater['theatercity']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="theaterState">State</label>
                                        <input type="text" class="form-control" id="theaterState" name="theaterState" value="<?php echo htmlspecialchars($theater['theaterstate']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="theaterZipcode">Zipcode</label>
                                        <input type="text" class="form-control" id="theaterZipcode" name="theaterZipcode" value="<?php echo htmlspecialchars($theater['theaterzipcode']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="theaterPhone">Phone</label>
                                        <input type="text" class="form-control" id="theaterPhone" name="theaterPhone" value="<?php echo htmlspecialchars($theater['theaterphone']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="theaterEmail">Email</label>
                                        <input type="email" class="form-control" id="theaterEmail" name="theaterEmail" value="<?php echo htmlspecialchars($theater['theateremail']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="theaterStatus">Status</label>
                                        <select class="form-control" id="theaterStatus" name="theaterStatus" required>
                                            <option value="active" <?php echo ($theater['theaterstatus'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($theater['theaterstatus'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="submit" name="update_theater" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Theater
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
</body>
</html>
