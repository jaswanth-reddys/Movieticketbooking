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

// Handle schedule deletion
$successMessage = '';
$errorMessage = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $scheduleId = $_GET['delete'];
    
    // Check if the schedule exists
    $checkQuery = "SELECT \"scheduleID\" FROM movie_schedules WHERE \"scheduleID\" = $1";
    $checkResult = pg_query_params($conn, $checkQuery, array($scheduleId));
    
    if ($checkResult && pg_num_rows($checkResult) > 0) {
        // Check if schedule is used in bookings
        $checkBookingsQuery = "SELECT COUNT(*) as count FROM bookingtable WHERE \"scheduleID\" = $1";
        $checkBookingsResult = pg_query_params($conn, $checkBookingsQuery, array($scheduleId));
        $bookingsCount = pg_fetch_assoc($checkBookingsResult)['count'];
        
        if ($bookingsCount > 0) {
            $errorMessage = "Cannot delete schedule. It is associated with " . $bookingsCount . " booking(s).";
        } else {
            // Delete the schedule
            $deleteQuery = "DELETE FROM movie_schedules WHERE \"scheduleID\" = $1";
            $deleteResult = pg_query_params($conn, $deleteQuery, array($scheduleId));
            
            if ($deleteResult) {
                $successMessage = "Schedule deleted successfully!";
            } else {
                $errorMessage = "Error deleting schedule: " . pg_last_error($conn);
            }
        }
    } else {
        $errorMessage = "Schedule not found!";
    }
}

// Handle schedule addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_schedule'])) {
    $movieId = $_POST['movieId'];
    $hallId = $_POST['hallId'];
    $showDate = $_POST['showDate'];
    $showTime = $_POST['showTime'];
    $price = $_POST['price'];
    $status = $_POST['status'];
    
    // Note: movieID and hallID are foreign keys, ensure they are correctly referenced
    $insertQuery = "INSERT INTO movie_schedules (movieid, hallid, showdate, showtime, price, schedulestatus) VALUES ($1, $2, $3, $4, $5, $6)";
    $insertResult = pg_query_params($conn, $insertQuery, array($movieId, $hallId, $showDate, $showTime, $price, $status));
    
    if ($insertResult) {
        $successMessage = "Schedule added successfully!";
    } else {
        $errorMessage = "Error adding schedule: " . pg_last_error($conn);
    }
}

// Get all movies for dropdown
// Changed "movieID" to "movieid" and "movieTitle" to "movietitle" based on the error hint
$moviesQuery = "SELECT movieid, movietitle FROM movietable ORDER BY movietitle";
$movies = pg_query($conn, $moviesQuery);
if (!$movies) {
    die("Error fetching movies: " . pg_last_error($conn));
}

// Get all theater halls for dropdown
// Changed "theaterID" to "theaterid" based on the new error hint
$hallsQuery = "
    SELECT h.hallid, h.hallname, h.halltype, t.theatername 
    FROM theater_halls h
    JOIN theaters t ON h.theaterid = t.theaterid 
    WHERE h.hallstatus = 'active'
    ORDER BY t.theatername, h.hallname
";
$halls = pg_query($conn, $hallsQuery);
if (!$halls) {
    die("Error fetching halls: " . pg_last_error($conn));
}

// Get all schedules with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$params = [];
$param_index = 1;

if (!empty($search)) {
    $searchParam = "%" . $search . "%";
    // Changed "movieTitle" to "movietitle"
    $searchCondition = "WHERE m.movietitle ILIKE $" . ($param_index++) . " OR t.\"theaterName\" ILIKE $" . ($param_index++) . "";
    $params = [$searchParam, $searchParam];
}

// Count total records for pagination
// Changed "theaterID" to "theaterid"
$countQuery = "
    SELECT COUNT(*) as total 
    FROM movie_schedules ms
    JOIN movietable m ON ms.movieid = m.movieid 
    JOIN theater_halls h ON ms.hallid = h.hallid 
    JOIN theaters t ON h.theaterid = t.theaterid 
" . $searchCondition;

$stmtCountResult = pg_query_params($conn, $countQuery, $params);
if (!$stmtCountResult) {
    die("Error counting schedules: " . pg_last_error($conn));
}
$totalRecords = pg_fetch_assoc($stmtCountResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get schedules for current page
// Changed "theaterID" to "theaterid"
$query = "
SELECT ms.*, m.movietitle, h.hallname, h.halltype, t.theatername 
FROM movie_schedules ms
JOIN movietable m ON ms.movieid = m.movieid 
JOIN theater_halls h ON ms.hallid = h.hallid 
JOIN theaters t ON h.theaterid = t.theaterid 
" . $searchCondition . "
ORDER BY ms.showdate DESC, ms.showtime DESC
LIMIT $" . ($param_index++) . " OFFSET $" . ($param_index++) . "";

$query_params = array_merge($params, [$recordsPerPage, $offset]);
$schedules = pg_query_params($conn, $query, $query_params);

if (!$schedules) {
    die("Error fetching schedules: " . pg_last_error($conn));
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - Showtime Select Admin</title>
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
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .search-box {
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
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-completed {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .hall-type {
            text-transform: capitalize;
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
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt"></i>
                                Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="schedules.php">
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
                    <h1>Manage Schedules</h1>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addScheduleModal">
                        <i class="fas fa-plus"></i> Add New Schedule
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
                    <div class="search-box">
                        <form action="" method="GET" class="form-inline">
                            <div class="input-group w-100">
                                <input type="text" name="search" class="form-control" placeholder="Search by movie or theater" value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="schedules.php" class="btn btn-outline-danger">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Movie</th>
                                    <th>Theater</th>
                                    <th>Hall</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (pg_num_rows($schedules) > 0): ?>
                                    <?php while ($schedule = pg_fetch_assoc($schedules)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($schedule['scheduleid']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['movietitle']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['theatername']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($schedule['hallname']); ?> 
                                                <span class="hall-type">(<?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($schedule['halltype']))); ?>)</span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($schedule['showdate']))); ?></td>
                                            <td><?php echo htmlspecialchars(date('h:i A', strtotime($schedule['showtime']))); ?></td>
                                            <td>₹<?php echo number_format($schedule['price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($schedule['schedulestatus']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($schedule['schedulestatus'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="edit_schedule.php?id=<?php echo htmlspecialchars($schedule['scheduleid']); ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="schedules.php?delete=<?php echo htmlspecialchars($schedule['scheduleid']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this schedule? This will also delete associated bookings!')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No schedules found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1" role="dialog" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addScheduleModalLabel">Add New Schedule</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="movieId">Movie</label>
                            <select class="form-control" id="movieId" name="movieId" required>
                                <option value="">Select Movie</option>
                                <?php
                                // Reset movie results pointer if already fetched
                                if (pg_num_rows($movies) > 0) {
                                    pg_result_seek($movies, 0);
                                }
                                while ($movie = pg_fetch_assoc($movies)): ?>
                                    <option value="<?php echo htmlspecialchars($movie['movieid']); ?>">
                                        <?php echo htmlspecialchars($movie['movietitle']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hallId">Theater Hall</label>
                            <select class="form-control" id="hallId" name="hallId" required>
                                <option value="">Select Theater Hall</option>
                                <?php
                                // Reset hall results pointer if already fetched
                                if (pg_num_rows($halls) > 0) {
                                    pg_result_seek($halls, 0);
                                }
                                while ($hall = pg_fetch_assoc($halls)): ?>
                                    <option value="<?php echo htmlspecialchars($hall['hallid']); ?>">
                                        <?php echo htmlspecialchars($hall['theatername'] . ' - ' . $hall['hallname'] . ' (' . str_replace('-', ' ', $hall['halltype']) . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="showDate">Show Date</label>
                            <input type="date" class="form-control" id="showDate" name="showDate" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="showTime">Show Time</label>
                            <input type="time" class="form-control" id="showTime" name="showTime" required>
                        </div>
                        <div class="form-group">
                            <label for="price">Ticket Price (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="add_schedule" class="btn btn-primary">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
