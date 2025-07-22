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

// Handle location deletion
$successMessage = '';
$errorMessage = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $locationId = $_GET['delete'];
    
    // Check if the location exists - using lowercase quoted column name
    $checkQuery = "SELECT \"locationid\" FROM locations WHERE \"locationid\" = $1";
    $checkResult = pg_query_params($conn, $checkQuery, array($locationId));
    
    if ($checkResult && pg_num_rows($checkResult) > 0) {
        // Check if location is used in movies - using lowercase quoted column name
        $checkMoviesQuery = "SELECT COUNT(*) as count FROM movietable WHERE \"locationid\" = $1";
        $checkMoviesResult = pg_query_params($conn, $checkMoviesQuery, array($locationId));
        $moviesCount = pg_fetch_assoc($checkMoviesResult)['count'];
        
        if ($moviesCount > 0) {
            $errorMessage = "Cannot delete location. It is associated with " . $moviesCount . " movie(s).";
        } else {
            // Delete the location - using lowercase quoted column name
            $deleteQuery = "DELETE FROM locations WHERE \"locationid\" = $1";
            $deleteResult = pg_query_params($conn, $deleteQuery, array($locationId));
            
            if ($deleteResult) {
                $successMessage = "Location deleted successfully!";
            } else {
                $errorMessage = "Error deleting location: " . pg_last_error($conn);
            }
        }
    } else {
        $errorMessage = "Location not found!";
    }
}

// Handle location addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_location'])) {
    $locationName = $_POST['locationName'];
    $locationState = $_POST['locationState'];
    $locationCountry = $_POST['locationCountry'];
    $locationStatus = $_POST['locationStatus'];
    
    // Insert into locations - using lowercase quoted column names
    $insertQuery = "INSERT INTO locations (\"locationname\", \"locationstate\", \"locationcountry\", \"locationstatus\") VALUES ($1, $2, $3, $4)";
    $insertResult = pg_query_params($conn, $insertQuery, array($locationName, $locationState, $locationCountry, $locationStatus));
    
    if ($insertResult) {
        $successMessage = "Location added successfully!";
    } else {
        $errorMessage = "Error adding location: " . pg_last_error($conn);
    }
}

// Handle location update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_location'])) {
    $locationId = $_POST['locationId'];
    $locationName = $_POST['locationName'];
    $locationState = $_POST['locationState'];
    $locationCountry = $_POST['locationCountry'];
    $locationStatus = $_POST['locationStatus'];
    
    // Update locations - using lowercase quoted column names
    $updateQuery = "UPDATE locations SET \"locationname\" = $1, \"locationstate\" = $2, \"locationcountry\" = $3, \"locationstatus\" = $4 WHERE \"locationid\" = $5";
    $updateResult = pg_query_params($conn, $updateQuery, array($locationName, $locationState, $locationCountry, $locationStatus, $locationId));
    
    if ($updateResult) {
        $successMessage = "Location updated successfully!";
    } else {
        $errorMessage = "Error updating location: " . pg_last_error($conn);
    }
}

// Get all locations - using lowercase quoted column name for ORDER BY
$locationsQuery = "SELECT * FROM locations ORDER BY \"locationname\"";
$locations = pg_query($conn, $locationsQuery);
if (!$locations) {
    die("Error fetching locations: " . pg_last_error($conn));
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Locations - Showtime Select Admin</title>
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
        .table-container {
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
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="nav-link" href="../admin/logout.php">Sign out</a>
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
                            <a class="nav-link" href="../content_manager/movies.php">
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
                            <a class="nav-link active" href="locations.php">
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
                        <?php if ($_SESSION['admin_role'] == 1): // Only Super Admin sees Users, Reports, Settings in Theater Manager sidebar ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/users.php">
                                <i class="fas fa-users"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/reports.php">
                                <i class="fas fa-chart-bar"></i>
                                All Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/settings.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Theater Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Manage Locations</h1>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLocationModal">
                        <i class="fas fa-plus"></i> Add New Location
                    </button>
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

                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>State</th>
                                    <th>Country</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (pg_num_rows($locations) > 0): ?>
                                    <?php while ($location = pg_fetch_assoc($locations)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($location['locationid']); ?></td>
                                            <td><?php echo htmlspecialchars($location['locationname']); ?></td>
                                            <td><?php echo htmlspecialchars($location['locationstate']); ?></td>
                                            <td><?php echo htmlspecialchars($location['locationcountry']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $location['locationstatus'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($location['locationstatus'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning edit-location"
                                                        data-id="<?php echo htmlspecialchars($location['locationid']); ?>"
                                                        data-name="<?php echo htmlspecialchars($location['locationname']); ?>"
                                                        data-state="<?php echo htmlspecialchars($location['locationstate']); ?>"
                                                        data-country="<?php echo htmlspecialchars($location['locationcountry']); ?>"
                                                        data-status="<?php echo htmlspecialchars($location['locationstatus']); ?>"
                                                        data-toggle="modal" data-target="#editLocationModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="locations.php?delete=<?php echo htmlspecialchars($location['locationid']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this location? This may affect movies linked to this location.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No locations found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Location Modal -->
    <div class="modal fade" id="addLocationModal" tabindex="-1" role="dialog" aria-labelledby="addLocationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLocationModalLabel">Add New Location</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="locationName">Location Name</label>
                            <input type="text" class="form-control" id="locationName" name="locationName" required>
                        </div>
                        <div class="form-group">
                            <label for="locationState">State</label>
                            <input type="text" class="form-control" id="locationState" name="locationState">
                        </div>
                        <div class="form-group">
                            <label for="locationCountry">Country</label>
                            <input type="text" class="form-control" id="locationCountry" name="locationCountry" value="India">
                        </div>
                        <div class="form-group">
                            <label for="locationStatus">Status</label>
                            <select class="form-control" id="locationStatus" name="locationStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="add_location" class="btn btn-primary">Add Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Location Modal -->
    <div class="modal fade" id="editLocationModal" tabindex="-1" role="dialog" aria-labelledby="editLocationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLocationModalLabel">Edit Location</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_locationId" name="locationId">
                        <div class="form-group">
                            <label for="edit_locationName">Location Name</label>
                            <input type="text" class="form-control" id="edit_locationName" name="locationName" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_locationState">State</label>
                            <input type="text" class="form-control" id="edit_locationState" name="locationState">
                        </div>
                        <div class="form-group">
                            <label for="edit_locationCountry">Country</label>
                            <input type="text" class="form-control" id="edit_locationCountry" name="locationCountry">
                        </div>
                        <div class="form-group">
                            <label for="edit_locationStatus">Status</label>
                            <select class="form-control" id="edit_locationStatus" name="locationStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="update_location" class="btn btn-primary">Update Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Fill edit modal with location data
        $('.edit-location').click(function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var state = $(this).data('state');
            var country = $(this).data('country');
            var status = $(this).data('status');
            
            $('#edit_locationId').val(id);
            $('#edit_locationName').val(name);
            $('#edit_locationState').val(state);
            $('#edit_locationCountry').val(country);
            $('#edit_locationStatus').val(status);
        });
    </script>
</body>
</html>
