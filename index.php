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

// Get featured movies for display (limit to 6 for homepage)
$featuredMoviesQuery = "
    SELECT m.movieID, m.movieImg, m.movieTitle, m.movieGenre, m.movieDuration, m.movieRelDate, l.locationName
    FROM movietable m
    LEFT JOIN locations l ON m.locationID = l.locationID
    ORDER BY m.movieRelDate DESC, m.movieTitle ASC
    LIMIT 6
";
$featuredMovies = pg_query($conn, $featuredMoviesQuery);

// Check if the movie query failed
if ($featuredMovies === false) {
    die("Featured Movies Query failed: " . pg_last_error($conn) . "<br>SQL: " . htmlspecialchars($featuredMoviesQuery));
}

// Get total movies count
$totalMoviesQuery = "SELECT COUNT(*) as total FROM movietable";
$totalMoviesResult = pg_query($conn, $totalMoviesQuery);
$totalMovies = 0; // Default to 0
if ($totalMoviesResult) {
    $totalMovies = pg_fetch_assoc($totalMoviesResult)['total'];
} else {
    // Log error or handle it appropriately, but don't die on a count query
    error_log("Total Movies Query failed: " . pg_last_error($conn));
}


// Query for "Our Theaters" section
// Fetch all active theaters, including their panorama image path
$theatersQuery = "
    SELECT
        theaterID,
        theaterName,
        theaterCity,
        theaterPanoramaImg
    FROM theaters
    WHERE theaterStatus = 'active'
    ORDER BY theaterName ASC
";
$theaters = pg_query($conn, $theatersQuery);

// Check if the theater query failed
if ($theaters === false) {
    die("Theater Query failed: " . pg_last_error($conn) . "<br>SQL: " . htmlspecialchars($theatersQuery));
}

// Close PostgreSQL connection
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Showtime Select - Movie Ticket Booking</title>
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
        .hero-bg {
            background: linear-gradient(135deg, #16213e 0%, #0f3460 100%);
        }
        .card {
            background-color: #0f3460; /* Dark blue for cards */
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
        }
        .card-image {
            width: 100%;
            height: 250px; /* Fixed height for consistency */
            object-fit: cover;
            border-bottom: 3px solid #e94560; /* Accent border */
        }
        .btn-primary {
            background-color: #e94560;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #b82e4a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.4);
        }
        .btn-primary.block {
            display: block;
            text-align: center;
        }
        .btn-secondary {
            background-color: transparent;
            color: #e94560;
            border: 2px solid #e94560;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-secondary:hover {
            background-color: #e94560;
            color: white;
            transform: translateY(-2px);
        }
        .footer-bg {
            background-color: #16213e;
        }
        .logo-text {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
        }
        .feature-icon {
            color: #e94560;
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #e94560 0%, #b82e4a 100%);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            color: white;
        }
        /* Responsive grid for movies */
        @media (min-width: 640px) {
            .movies-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1024px) {
            .movies-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        
        .theater-card {
            background-color: #0f3460;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .theater-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
        }
        .theater-card-image {
            width: 100%;
            height: 200px; /* Adjust height for theater preview */
            object-fit: cover;
            border-bottom: 3px solid #e94560;
        }
        .btn-secondary-custom { /* Custom class for secondary button style */
            background-color: #3f5f8a; /* A complementary blue */
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            text-align: center;
        }
        .btn-secondary-custom:hover {
            background-color: #304a6c;
        }
        .card-buttons {
            padding: 1.5rem; /* Padding matches card-body */
            display: flex;
            flex-direction: column;
            gap: 0.75rem; /* Space between buttons */
            margin-top: auto; /* Push buttons to the bottom */
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
                    <li><a href="user/index.php" class="nav-link text-white hover:text-red-500">Movies</a></li>
                    <li><a href="user/schedule.php" class="nav-link text-white hover:text-red-500">Schedule</a></li>
                    <li><a href="user/contact-us.php" class="nav-link text-white hover:text-red-500">Contact Us</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="user/profile.php" class="nav-link text-white hover:text-red-500">Profile</a></li>
                        <li><a href="user/logout.php" class="nav-link text-white hover:text-red-500">Logout</a></li>
                    <?php else: ?>
                        <li><a href="user/login.php" class="nav-link text-white hover:text-red-500">Login</a></li>
                        <li><a href="user/register.php" class="nav-link text-white hover:text-red-500">Register</a></li>
                    <?php endif; ?>
                    <li><a href="admin/index.php" class="nav-link text-white hover:text-red-500">Admin</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-bg py-20">
        <div class="container mx-auto text-center px-4">
            <h1 class="text-5xl font-bold text-white mb-6">Welcome to Showtime Select</h1>
            <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">
                Your premier destination for movie ticket booking. Discover the latest movies, 
                book your seats, and enjoy an unforgettable cinema experience.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="user/index.php" class="btn-primary">Browse Movies</a>
                <a href="user/register.php" class="btn-secondary">Join Now</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center text-white mb-12">Why Choose Showtime Select?</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <i class="fas fa-ticket-alt feature-icon"></i>
                    <h3 class="text-xl font-semibold text-white mb-4">Easy Booking</h3>
                    <p class="text-gray-400">Book your movie tickets online with just a few clicks. No more waiting in long queues.</p>
                </div>
                <div class="text-center">
                    <i class="fas fa-film feature-icon"></i>
                    <h3 class="text-xl font-semibold text-white mb-4">Latest Movies</h3>
                    <p class="text-gray-400">Stay updated with the latest movie releases and blockbusters in your area.</p>
                </div>
                <div class="text-center">
                    <i class="fas fa-couch feature-icon"></i>
                    <h3 class="text-xl font-semibold text-white mb-4">Seat Selection</h3>
                    <p class="text-gray-400">Choose your preferred seats from our interactive seating chart for the best experience.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-16">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-3 gap-8">
                <div class="stats-card">
                    <h3 class="text-3xl font-bold mb-2"><?php echo $totalMovies; ?>+</h3>
                    <p class="text-lg">Movies Available</p>
                </div>
                <div class="stats-card">
                    <h3 class="text-3xl font-bold mb-2">24/7</h3>
                    <p class="text-lg">Online Booking</p>
                </div>
                <div class="stats-card">
                    <h3 class="text-3xl font-bold mb-2">100%</h3>
                    <p class="text-lg">Secure Payment</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Movies Section -->
    <?php if ($featuredMovies && pg_num_rows($featuredMovies) > 0): ?>
    <section class="py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center text-white mb-12">Featured Movies</h2>
            <div class="movies-grid grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <?php while ($movie = pg_fetch_assoc($featuredMovies)): ?>
                    <div class="card">
                        <img src="<?php echo htmlspecialchars($movie['movieimg']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/300x450/cccccc/333333?text=No+Movie+Image';" alt="<?php echo htmlspecialchars($movie['movietitle']); ?>" class="card-image">
                        <div class="p-6">
                            <h3 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($movie['movietitle']); ?></h3>
                            <p class="text-sm text-gray-400 mb-1"><strong>Genre:</strong> <?php echo htmlspecialchars($movie['moviegenre']); ?></p>
                            <p class="text-sm text-gray-400 mb-4"><strong>Duration:</strong> <?php echo htmlspecialchars($movie['movieduration']); ?> min</p>
                            <a href="user/movie_details.php?id=<?php echo htmlspecialchars($movie['movieid']); ?>" class="btn-primary block text-center">View Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="text-center mt-12">
                <a href="user/index.php" class="btn-secondary">View All Movies</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Our Theaters Section -->
    <?php if ($theaters && pg_num_rows($theaters) > 0): ?>
    <section class="py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center text-white mb-12">Our Theaters</h2>
            <div class="movies-grid grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <?php while ($theater = pg_fetch_assoc($theaters)): ?>
                    <div class="theater-card">
                        <img src="<?php echo htmlspecialchars($theater['theaterpanoramaimg'] ?? 'img/placeholders/default_theater_panorama.jpg'); ?>" onerror="this.onerror=null;this.src='https://placehold.co/400x200/0f3460/e0e0e0?text=No+Panorama';" alt="<?php echo htmlspecialchars($theater['theatername']); ?>" class="theater-card-image">
                        <div class="p-6">
                            <h3 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($theater['theatername']); ?></h3>
                            <p class="text-sm text-gray-400 mb-4"><strong>City:</strong> <?php echo htmlspecialchars($theater['theatercity'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="card-buttons">
                            <?php if (!empty($theater['theaterpanoramaimg'])): ?>
                                <a href="user/view_theater.php?theater_id=<?php echo htmlspecialchars($theater['theaterid']); ?>" class="btn-primary">View Theater</a>
                            <?php else: ?>
                                <span class="btn-secondary-custom opacity-50 cursor-not-allowed">No Panorama</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Call to Action Section -->
    <section class="hero-bg py-16">
        <div class="container mx-auto text-center px-4">
            <h2 class="text-3xl font-bold text-white mb-6">Ready to Book Your Next Movie?</h2>
            <p class="text-lg text-gray-300 mb-8">Join thousands of movie lovers who trust Showtime Select for their entertainment needs.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="user/register.php" class="btn-primary">Create Account</a>
                <a href="user/login.php" class="btn-secondary">Login</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-bg text-gray-400 py-12">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold logo-text mb-4">Showtime Select</h3>
                    <p class="text-gray-400">Your premier destination for movie ticket booking and entertainment.</p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="user/index.php" class="text-gray-400 hover:text-red-500">Movies</a></li>
                        <li><a href="user/login.php" class="text-gray-400 hover:text-red-500">Login</a></li>
                        <li><a href="user/register.php" class="text-gray-400 hover:text-red-500">Register</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="user/contact-us.php" class="text-gray-400 hover:text-red-500">Contact Us</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500">Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500">Terms of Service</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Connect</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-red-500"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-gray-400 hover:text-red-500"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-red-500"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-400 hover:text-red-500"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <hr class="border-gray-600 my-8">
    
            <div class="container mx-auto text-center px-4">
                <p>&copy; <?php echo date('Y'); ?> Showtime Select. All rights reserved.</p>
                <p class="text-sm">Designed for educational purpose </p>
            </div>

        </div>
    </footer>

    <!-- Smooth scrolling script -->
    <script>
        // Add smooth scrolling to all links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
