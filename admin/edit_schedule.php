<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Ensure user has Theater Manager role (roleID = 2)
if ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2) {
    header("Location: ../dashboard.php"); // Redirect to main admin dashboard or access denied page
    exit();
}

// Check if schedule ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: schedules.php");
    exit();
}

$scheduleId = $_GET['id'];

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

$schedule = null;
$errorMessage = '';
$successMessage = '';

// Fetch schedule data
$stmtQuery = "
    SELECT ms.*, m.movietitle, h.hallname, t.theatername
    FROM movie_schedules ms
    JOIN movietable m ON ms.movieid = m.movieid
    JOIN theater_halls h ON ms.hallid = h.hallid
    JOIN theaters t ON h.theaterid = t.theaterid
    WHERE ms.scheduleid = $1
";
$stmtResult = pg_query_params($conn, $stmtQuery, array($scheduleId));
if ($stmtResult && pg_num_rows($stmtResult) > 0) {
    $schedule = pg_fetch_assoc($stmtResult);
    // Convert keys to lowercase for consistency with PostgreSQL's default behavior
    $schedule = array_change_key_case($schedule, CASE_LOWER);
} else {
    $errorMessage = "Schedule not found.";
}

// Get all movies for dropdown
$moviesQuery = "SELECT movieid, movietitle FROM movietable ORDER BY movietitle";
$movies = pg_query($conn, $moviesQuery);
if (!$movies) {
    die("Error fetching movies: " . pg_last_error($conn));
}

// Get all theater halls for dropdown
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

// Process form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_schedule'])) {
    $movieId = $_POST['movieId'];
    $hallId = $_POST['hallId'];
    $showDate = $_POST['showDate'];
    $showTime = $_POST['showTime'];
    $price = $_POST['price'];
    $status = $_POST['status'];

    $updateQuery = "UPDATE movie_schedules SET movieid = $1, hallid = $2, showdate = $3, showtime = $4, price = $5, schedulestatus = $6 WHERE scheduleid = $7";
    $updateResult = pg_query_params($conn, $updateQuery, array($movieId, $hallId, $showDate, $showTime, $price, $status, $scheduleId));
    
    if ($updateResult) {
        $successMessage = "Schedule updated successfully!";
        // Re-fetch schedule data to display updated info
        $stmtQuery = "
            SELECT ms.*, m.movietitle, h.hallname, t.theatername
            FROM movie_schedules ms
            JOIN movietable m ON ms.movieid = m.movieid
            JOIN theater_halls h ON ms.hallid = h.hallid
            JOIN theaters t ON h.theaterid = t.theaterid
            WHERE ms.scheduleid = $1
        ";
        $stmtResult = pg_query_params($conn, $stmtQuery, array($scheduleId));
        $schedule = pg_fetch_assoc($stmtResult);
        $schedule = array_change_key_case($schedule, CASE_LOWER);
    } else {
        $errorMessage = "Error updating schedule: " . pg_last_error($conn);
    }
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../../img/sslogo.jpg"> <!-- Path adjusted -->
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
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Theater Manager</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="nav-link" href="../logout.php">Sign out</a>
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
                            <a class="nav-link" href="theaters.php">
                                <i class="fas fa-building"></i>
                                Theaters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Edit Schedule ID: <?php echo htmlspecialchars($schedule['scheduleid'] ?? 'N/A'); ?></h1>
                    <a href="schedules.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Schedules
                    </a>
                </div>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($schedule): ?>
                    <div class="form-container">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="movieId">Movie</label>
                                <select class="form-control" id="movieId" name="movieId" required>
                                    <?php while ($movie = pg_fetch_assoc($movies)): ?>
                                        <option value="<?php echo $movie['movieid']; ?>" <?php echo ($schedule['movieid'] == $movie['movieid']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($movie['movietitle']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="hallId">Theater Hall</label>
                                <select class="form-control" id="hallId" name="hallId" required>
                                    <?php while ($hall = pg_fetch_assoc($halls)): ?>
                                        <option value="<?php echo $hall['hallid']; ?>" <?php echo ($schedule['hallid'] == $hall['hallid']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hall['theatername'] . ' - ' . $hall['hallname'] . ' (' . str_replace('-', ' ', $hall['halltype']) . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="showDate">Show Date</label>
                                <input type="date" class="form-control" id="showDate" name="showDate" value="<?php echo htmlspecialchars($schedule['showdate']); ?>" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="showTime">Show Time</label>
                                <input type="time" class="form-control" id="showTime" name="showTime" value="<?php echo htmlspecialchars($schedule['showtime']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="price">Ticket Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($schedule['price']); ?>" required min="0">
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="active" <?php echo ($schedule['schedulestatus'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="cancelled" <?php echo ($schedule['schedulestatus'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo ($schedule['schedulestatus'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="submit" name="update_schedule" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="text-center text-danger">Schedule details could not be loaded.</p>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
