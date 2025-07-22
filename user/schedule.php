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

// Fetch all active schedules, joining with movies, halls, and theaters
// CURDATE() is replaced with CURRENT_DATE in PostgreSQL
$schedulesQuery = "
    SELECT ms.scheduleID, ms.showDate, ms.showTime, ms.price,
           m.movieID, m.movieTitle, m.movieGenre, m.movieDuration, m.movieImg, m.movieDirector, m.movieActors,
           h.hallName, h.hallType, t.theaterName, t.theaterAddress, t.theaterCity
    FROM movie_schedules ms
    JOIN movietable m ON ms.movieID = m.movieID
    JOIN theater_halls h ON ms.hallID = h.hallID
    JOIN theaters t ON h.theaterID = t.theaterID
    WHERE ms.scheduleStatus = 'active' AND ms.showDate >= CURRENT_DATE
    ORDER BY ms.showDate ASC, m.movieTitle ASC, ms.showTime ASC
";
$result = pg_query($conn, $schedulesQuery);

$groupedSchedules = [];
if ($result) { // Check if query executed successfully
    if (pg_num_rows($result) > 0) {
        while ($row = pg_fetch_assoc($result)) {
            // Convert keys to lowercase for consistency with PostgreSQL's default behavior
            $row = array_change_key_case($row, CASE_LOWER);

            $date = $row['showdate'];
            $movieId = $row['movieid'];

            if (!isset($groupedSchedules[$date])) {
                $groupedSchedules[$date] = [];
            }
            if (!isset($groupedSchedules[$date][$movieId])) {
                $groupedSchedules[$date][$movieId] = [
                    'movieDetails' => [
                        'movieID' => $row['movieid'],
                        'movieTitle' => $row['movietitle'],
                        'movieGenre' => $row['moviegenre'],
                        'movieDuration' => $row['movieduration'],
                        'movieImg' => $row['movieimg'],
                        'movieDirector' => $row['moviedirector'],
                        'movieActors' => $row['movieactors']
                    ],
                    'showtimes' => []
                ];
            }
            $groupedSchedules[$date][$movieId]['showtimes'][] = [
                'scheduleID' => $row['scheduleid'],
                'showTime' => $row['showtime'],
                'price' => $row['price'],
                'hallName' => $row['hallname'],
                'hallType' => $row['halltype'],
                'theaterName' => $row['theatername'],
                'theaterAddress' => $row['theateraddress'],
                'theaterCity' => $row['theatercity']
            ];
        }
    }
} else {
    // Handle query error
    error_log("Schedule query failed: " . pg_last_error($conn));
    // You might want to set an error message to display on the page
}

// Close PostgreSQL connection
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Schedule - Showtime Select</title>
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
        .card {
            background-color: #0f3460; /* Dark blue for cards */
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        .movie-schedule-item {
            background-color: #1f4068; /* Slightly lighter blue for schedule items */
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease;
        }
        .movie-schedule-item:hover {
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
        .footer-bg {
            background-color: #16213e;
        }
        .logo-text {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
        }
        .movie-poster-mini {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid #e94560;
        }
        .showtime-pill {
            background-color: #e94560;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        .showtime-pill:hover {
            background-color: #b82e4a;
        }
        .date-header {
            background-color: #16213e;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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
        <h1 class="text-4xl font-bold text-center mb-10 text-white">Movie Schedules</h1>

        <?php if (empty($groupedSchedules)): ?>
            <p class="text-center text-xl text-gray-400">No upcoming schedules available.</p>
        <?php else: ?>
            <?php foreach ($groupedSchedules as $date => $moviesForDate): ?>
                <div class="mb-10">
                    <div class="date-header text-3xl font-bold text-white mb-6">
                        <?php echo date('l, F j, Y', strtotime($date)); ?>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($moviesForDate as $movieData): ?>
                            <div class="movie-schedule-item p-6 flex items-start gap-6">
                                <img src="../<?php echo htmlspecialchars($movieData['movieDetails']['movieImg']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/80x120/cccccc/333333?text=No+Movie+Image';" alt="<?php echo htmlspecialchars($movieData['movieDetails']['movieTitle']); ?>" class="movie-poster-mini">
                                
                                <div class="flex-grow">
                                    <h2 class="text-3xl font-semibold text-white mb-2"><?php echo htmlspecialchars($movieData['movieDetails']['movieTitle']); ?></h2>
                                    <p class="text-gray-300 text-sm mb-2">
                                        <?php echo htmlspecialchars($movieData['movieDetails']['movieGenre']); ?> &bull; 
                                        <?php echo htmlspecialchars($movieData['movieDetails']['movieDuration']); ?> min &bull; 
                                        Directed by <?php echo htmlspecialchars($movieData['movieDetails']['movieDirector']); ?>
                                    </p>
                                    
                                    <h3 class="text-xl font-medium text-white mt-4 mb-3">Showtimes:</h3>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                        <?php foreach ($movieData['showtimes'] as $showtime): ?>
                                            <?php if (isset($_SESSION['user_id'])): ?>
                                                <a href="booking.php?schedule_id=<?php echo htmlspecialchars($showtime['scheduleID']); ?>" class="showtime-pill">
                                                    <i class="far fa-clock mr-2"></i> <?php echo date('h:i A', strtotime($showtime['showTime'])); ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="login.php?message=Please login to book tickets" class="showtime-pill">
                                                    <i class="far fa-clock mr-2"></i> <?php echo date('h:i A', strtotime($showtime['showTime'])); ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-4 text-gray-400 text-sm">
                                        <p>Playing at various halls and theaters.</p>
                                        <a href="movie_details.php?id=<?php echo htmlspecialchars($movieData['movieDetails']['movieID']); ?>" class="text-e94560 hover:underline mt-2 inline-block">
                                            View all details & specific theaters
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
