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

$bookingDetails = null;
$errorMessage = '';

if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
    $bookingID = $_GET['booking_id'];

    // Fetch comprehensive booking details using a prepared statement for security
    // PostgreSQL uses $1, $2, etc. for parameters in prepared statements
    $query = "
        SELECT b.*, m.movieTitle, m.movieImg, m.movieGenre, m.movieDuration,
               ms.showDate, ms.showTime, ms.price as ticketPrice,
               h.hallName, h.hallType, t.theaterName, t.theaterAddress, t.theaterCity
        FROM bookingtable b
        LEFT JOIN movietable m ON b.movieID = m.movieID
        LEFT JOIN movie_schedules ms ON b.scheduleID = ms.scheduleID
        LEFT JOIN theater_halls h ON b.hallID = h.hallID
        LEFT JOIN theaters t ON h.theaterID = t.theaterID
        WHERE b.bookingID = $1
    ";
    
    $result = pg_query_params($conn, $query, array($bookingID));

    if ($result) {
        if (pg_num_rows($result) > 0) {
            $bookingDetails = pg_fetch_assoc($result);
        } else {
            $errorMessage = "Booking details not found.";
        }
    } else {
        $errorMessage = "Error fetching booking details: " . pg_last_error($conn);
    }

    // Optionally, check if the booking belongs to the logged-in user for security
    // Note: PostgreSQL column names are typically lowercase by default
    if (isset($_SESSION['user_username']) && $bookingDetails && $bookingDetails['bookingemail'] !== $_SESSION['user_username']) {
        $errorMessage = "Access Denied: This booking does not belong to your account.";
        $bookingDetails = null; // Clear details if unauthorized
    }

} else {
    $errorMessage = "Invalid booking ID. Please go back to your profile or home page.";
}

// Close PostgreSQL connection
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Showtime Select</title>
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
        .confirmation-icon {
            color: #28a745; /* Green for success */
        }
        /* Styles for print */
        @media print {
            body * {
                visibility: hidden;
            }
            .printable-ticket, .printable-ticket * {
                visibility: visible;
            }
            .printable-ticket {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                box-sizing: border-box;
                color: black; /* Ensure text is black for printing */
                background-color: white;
            }
            .printable-ticket .qr-code-img {
                width: 100px;
                height: 100px;
                object-fit: contain;
                margin-top: 10px;
            }
            .printable-ticket h1, .printable-ticket h2, .printable-ticket h3 {
                color: black !important;
            }
            .printable-ticket p, .printable-ticket strong, .printable-ticket span {
                color: black !important;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body class="antialiased">
    <!-- Header -->
    <header class="header-bg shadow-lg py-4 no-print">
        <div class="container mx-auto flex justify-between items-center px-4">
            <a href="index.php" class="text-2xl font-bold logo-text">Showtime Select</a>
            <nav>
                <ul class="flex space-x-6">
                    <li><a href="index.php" class="nav-link text-white hover:text-red-500">Home</a></li>
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
    <main class="container mx-auto px-4 py-8 text-center">
        <div class="card p-8 max-w-3xl mx-auto">
            <?php if (!empty($errorMessage)): ?>
                <div class="bg-red-600 text-white p-4 rounded-lg mb-6">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
                <a href="index.php" class="btn-primary inline-block">Back to Home</a>
            <?php elseif ($bookingDetails): ?>
                <i class="fas fa-check-circle confirmation-icon text-7xl mb-6 no-print"></i>
                <h1 class="text-5xl font-bold text-white mb-4 no-print">Booking Confirmed!</h1>
                <p class="text-gray-300 text-xl mb-8 no-print">Thank you for your booking with Showtime Select.</p>

                <!-- Printable Ticket Section -->
                <div class="printable-ticket bg-gray-800 p-6 rounded-lg text-left shadow-lg">
                    <div class="flex justify-between items-center border-b border-gray-700 pb-4 mb-4">
                        <h2 class="text-4xl font-bold text-white logo-text">Showtime Select</h2>
                        <img class="qr-code-img" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($bookingDetails['orderid']); ?>" alt="QR Code">
                    </div>

                    <h3 class="text-3xl font-semibold text-white mb-4"><?php echo htmlspecialchars($bookingDetails['movietitle'] ?? 'N/A Movie'); ?></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-lg">
                        <div>
                            <p><strong>Order ID:</strong> <span class="text-e94560"><?php echo htmlspecialchars($bookingDetails['orderid']); ?></span></p>
                            <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($bookingDetails['bookingid']); ?></p>
                            <p><strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($bookingDetails['showdate'] ?? '')); ?></p>
                            <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($bookingDetails['showtime'] ?? '')); ?></p>
                            <p><strong>Seats:</strong> <span class="font-bold text-yellow-300"><?php echo htmlspecialchars($bookingDetails['seats'] ?? 'N/A'); ?></span></p>
                        </div>
                        <div>
                            <p><strong>Theater:</strong> <?php echo htmlspecialchars($bookingDetails['theatername'] ?? 'N/A Theater'); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($bookingDetails['theateraddress'] . ', ' . $bookingDetails['theatercity']); ?></p>
                            <p><strong>Hall:</strong> <?php echo htmlspecialchars($bookingDetails['hallname'] ?? 'N/A Hall'); ?> (<?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($bookingDetails['halltype'] ?? ''))); ?>)</p>
                            <p><strong>Price per ticket:</strong> ₹<?php echo number_format($bookingDetails['ticketprice'] ?? 0, 2); ?></p>
                            <p><strong>Total Amount:</strong> <span class="text-green-400 font-bold">₹<?php echo number_format($bookingDetails['amount'] ?? 0, 2); ?></span></p>
                        </div>
                    </div>

                    <div class="mt-8 pt-4 border-t border-gray-700">
                        <h4 class="text-2xl font-semibold text-white mb-3">Customer Information</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($bookingDetails['bookingfname'] . ' ' . $bookingDetails['bookinglname']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($bookingDetails['bookingemail']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($bookingDetails['bookingpnumber']); ?></p>
                    </div>
                </div>

                <div class="mt-10 flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4 no-print">
                    <a href="index.php" class="btn-primary inline-flex items-center justify-center">
                        <i class="fas fa-home mr-2"></i> Go to Home
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="profile.php" class="btn-primary inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700">
                            <i class="fas fa-user-circle mr-2"></i> View My Bookings
                        </a>
                    <?php endif; ?>
                    <button class="btn-primary inline-flex items-center justify-center bg-gray-600 hover:bg-gray-700" onclick="window.print()">
                        <i class="fas fa-print mr-2"></i> Print Ticket
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-bg text-gray-400 py-8 mt-12 no-print">
        <div class="container mx-auto text-center px-4">
            <p>&copy; <?php echo date('Y'); ?> Showtime Select. All rights reserved.</p>
            <p class="text-sm">Designed for educational purpose </p>
        </div>
    </footer>
</body>
</html>
