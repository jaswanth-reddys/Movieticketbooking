<?php
session_start();

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

$movie = null;
$schedules = null;
$errorMessage = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $movieId = $_GET['id'];

    // Fetch movie details using a prepared statement
    $movieQuery = "SELECT m.*, l.locationName FROM movietable m LEFT JOIN locations l ON m.locationID = l.locationID WHERE m.movieID = $1";
    $movieResult = pg_query_params($conn, $movieQuery, array($movieId));

    if ($movieResult) {
        if (pg_num_rows($movieResult) > 0) {
            $movie = pg_fetch_assoc($movieResult);
            // Convert keys to lowercase for consistency with PostgreSQL's default behavior
            $movie = array_change_key_case($movie, CASE_LOWER);
        } else {
            $errorMessage = "Movie not found.";
        }
    } else {
        $errorMessage = "Error fetching movie details: " . pg_last_error($conn);
    }

    // Fetch movie schedules
    if ($movie) {
        // Updated query to fetch hallPanoramaImg and theaterID
        // CURDATE() is replaced with CURRENT_DATE in PostgreSQL
        $schedulesQuery = "
            SELECT ms.scheduleID, ms.showDate, ms.showTime, ms.price,
                   h.hallName, h.hallType, h.hallPanoramaImg,
                   t.theaterName, t.theaterAddress, t.theaterCity, t.theaterID
            FROM movie_schedules ms
            JOIN theater_halls h ON ms.hallID = h.hallID
            JOIN theaters t ON h.theaterID = t.theaterID
            WHERE ms.movieID = $1 AND ms.scheduleStatus = 'active' AND ms.showDate >= CURRENT_DATE
            ORDER BY ms.showDate ASC, ms.showTime ASC
        ";
        $schedules = pg_query_params($conn, $schedulesQuery, array($movieId));

        if (!$schedules) {
            error_log("Error fetching schedules: " . pg_last_error($conn));
            $errorMessage = "Error retrieving movie schedules.";
        }
    }
} else {
    $errorMessage = "Invalid movie ID.";
}

// Close PostgreSQL connection
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $movie ? htmlspecialchars($movie['movietitle']) . ' - Details' : 'Movie Details'; ?> - Showtime Select</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS for 21stdev classic look -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #1a1a2e; /* Darker background */
            color: #e0e0e0; /* Light text */
            line-height: 1.6;
        }
        .header-bg {
            background-color: #16213e; /* Slightly lighter dark for header */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .nav-link {
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: #e94560; /* Accent color on hover */
        }
        .movie-detail-card {
            background-color: #0f3460; /* Dark blue for main card */
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        .movie-poster {
            width: 100%;
            height: auto;
            max-height: 400px;
            object-fit: contain; /* Keep aspect ratio and fit within bounds */
            border-radius: 8px;
            border: 3px solid #e94560; /* Accent border */
        }
        .schedule-card {
            background-color: #1f4068; /* Slightly lighter blue for schedule cards */
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* To push buttons to bottom */
        }
        .schedule-card:hover {
            transform: translateY(-3px);
        }
        .btn-primary {
            background-color: #e94560;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #b82e4a;
        }
        .btn-secondary-custom { /* Reusing existing style for consistency */
            background-color: #3f5f8a;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .btn-secondary-custom:hover {
            background-color: #304a6c;
        }
        .footer-bg {
            background-color: #16213e;
        }
        .logo-text {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
        }
        .schedule-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem; /* Space between buttons */
            margin-top: auto; /* Push buttons to the bottom of the card */
            padding-top: 1rem; /* Add some padding above buttons */
        }
    </style>
</head>
<body class="antialiased">
    <!-- Header -->
    <header class="header-bg shadow-lg py-4">
        <div class="container mx-auto flex justify-between items-center px-4">
            <a href="index.php" class="text-2xl font-bold logo-text">Showtime Select</a>
            <nav>
                <ul class="flex space-x-6">
                    <li><a href="index.php" class="nav-link text-white hover:text-red-500">Home</a></li>
                    <li><a href="schedule.php" class="nav-link text-white hover:text-red-500">Schedule</a></li>
                    <li><a href="contact-us.php" class="nav-link text-white hover:text-red-500">Contact Us</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php" class="nav-link text-white hover:text-red-500">Profile</a></li>
                        <li><a href="logout.php" class="nav-link text-white hover:text-red-500">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="nav-link text-white hover:text-red-500">Login</a></li>
                        <li><a href="register.php" class="nav-link text-white hover:text-red-500">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-600 text-white p-4 rounded-lg mb-6 text-center">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <div class="text-center">
                <a href="index.php" class="btn-primary inline-block">Back to Movies</a>
            </div>
        <?php elseif ($movie): ?>
            <div class="movie-detail-card p-8 flex flex-col md:flex-row items-center md:items-start gap-8">
                <div class="md:w-1/3 flex-shrink-0">
                    <img src="../<?php echo htmlspecialchars($movie['movieimg']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/300x450/cccccc/333333?text=No+Movie+Image';" alt="<?php echo htmlspecialchars($movie['movietitle']); ?>" class="movie-poster">
                </div>
                <div class="md:w-2/3">
                    <h1 class="text-5xl font-bold text-white mb-4"><?php echo htmlspecialchars($movie['movietitle']); ?></h1>
                    <p class="text-lg text-gray-300 mb-2"><strong>Genre:</strong> <?php echo htmlspecialchars($movie['moviegenre']); ?></p>
                    <p class="text-lg text-gray-300 mb-2"><strong>Duration:</strong> <?php echo htmlspecialchars($movie['movieduration']); ?> minutes</p>
                    <p class="text-lg text-gray-300 mb-2"><strong>Release Date:</strong> <?php echo date('F j, Y', strtotime($movie['moviereldate'])); ?></p>
                    <p class="text-lg text-gray-300 mb-2"><strong>Director:</strong> <?php echo htmlspecialchars($movie['moviedirector']); ?></p>
                    <p class="text-lg text-gray-300 mb-4"><strong>Actors:</strong> <?php echo htmlspecialchars($movie['movieactors']); ?></p>
                    <p class="text-lg text-gray-300 mb-4"><strong>Playing in:</strong> <?php echo htmlspecialchars($movie['locationname'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <h2 class="text-4xl font-bold text-white text-center mt-12 mb-8">Available Showtimes</h2>

            <?php if ($schedules && pg_num_rows($schedules) > 0): ?>
                <?php
                // Group schedules by date
                $groupedSchedules = [];
                while ($schedule = pg_fetch_assoc($schedules)) {
                    // Convert keys to lowercase for consistency with PostgreSQL's default behavior
                    $schedule = array_change_key_case($schedule, CASE_LOWER);
                    $date = $schedule['showdate'];
                    if (!isset($groupedSchedules[$date])) {
                        $groupedSchedules[$date] = [];
                    }
                    $groupedSchedules[$date][] = $schedule;
                }
                ?>
                <?php foreach ($groupedSchedules as $date => $dailySchedules): ?>
                    <div class="bg-gray-800 rounded-lg p-6 mb-6 shadow-md">
                        <h3 class="text-2xl font-semibold text-white mb-4"><?php echo date('l, F j, Y', strtotime($date)); ?></h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($dailySchedules as $schedule): ?>
                                <div class="schedule-card p-5">
                                    <p class="text-white text-xl font-medium mb-2"><i class="far fa-clock mr-2 text-e94560"></i> <?php echo date('h:i A', strtotime($schedule['showtime'])); ?></p>
                                    <p class="text-gray-300 text-md mb-1"><i class="fas fa-building mr-2 text-e94560"></i> <?php echo htmlspecialchars($schedule['theatername']); ?></p>
                                    <p class="text-gray-300 text-md mb-1"><i class="fas fa-couch mr-2 text-e94560"></i> Hall: <?php echo htmlspecialchars($schedule['hallname']); ?> (<?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($schedule['halltype']))); ?>)</p>
                                    <p class="text-gray-300 text-md mb-4"><i class="fas fa-map-marker-alt mr-2 text-e94560"></i> <?php echo htmlspecialchars($schedule['theateraddress']) . ', ' . htmlspecialchars($schedule['theatercity']); ?></p>
                                    <p class="text-white text-2xl font-bold mb-4">₹<?php echo number_format($schedule['price'], 2); ?></p>
                                    
                                    <div class="schedule-buttons">
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <a href="booking.php?schedule_id=<?php echo htmlspecialchars($schedule['scheduleid']); ?>" class="btn-primary block text-center">Book Now</a>
                                        <?php else: ?>
                                            <a href="login.php?message=Please login to book tickets" class="btn-primary block text-center">Login to Book</a>
                                        <?php endif; ?>

                                        <?php if (!empty($schedule['hallpanoramimg'])): ?>
                                            <a href="view_theater.php?theater_id=<?php echo htmlspecialchars($schedule['theaterid']); ?>&hall_panorama_img=<?php echo urlencode($schedule['hallpanoramimg']); ?>&hall_name=<?php echo urlencode($schedule['hallname']); ?>&theater_name=<?php echo urlencode($schedule['theatername']); ?>" class="btn-secondary-custom block text-center">View Hall Panorama</a>
                                        <?php else: ?>
                                            <span class="btn-secondary-custom opacity-50 cursor-not-allowed text-sm">No Hall Panorama</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-xl text-gray-400">No upcoming showtimes available for this movie.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer-bg text-gray-400 py-8 mt-12">
        <div class="container mx-auto text-center px-4">
            <p>&copy; <?php echo date('Y'); ?> Showtime Select. All rights reserved.</p>
            <p class="text-sm">Designed for educational purpose </p>
        </div>
    </footer>
</body>
</html>
