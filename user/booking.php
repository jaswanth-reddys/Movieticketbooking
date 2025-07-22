<?php
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    // Store the current URL to redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php?message=Please login to book tickets");
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

$schedule = null;
$movie = null;
$hall = null;
$theater = null;
$bookedSeats = [];
$errorMessage = '';
$successMessage = ''; // For potential messages after form submission

if (isset($_GET['schedule_id']) && is_numeric($_GET['schedule_id'])) {
    $scheduleId = $_GET['schedule_id'];

    // Fetch schedule, movie, hall, and theater details using prepared statement
    $query = "
        SELECT ms.scheduleID, ms.showDate, ms.showTime, ms.price,
               m.movieID, m.movieTitle, m.movieImg, m.movieGenre, m.movieDuration,
               h.hallID, h.hallName, h.hallType, h.totalSeats,
               t.theaterName, t.theaterAddress, t.theaterCity
        FROM movie_schedules ms
        JOIN movietable m ON ms.movieID = m.movieID
        JOIN theater_halls h ON ms.hallID = h.hallID
        JOIN theaters t ON h.theaterID = t.theaterID
        WHERE ms.scheduleID = $1
    ";
    
    $result = pg_query_params($conn, $query, array($scheduleId));

    if ($result) {
        if (pg_num_rows($result) > 0) {
            $data = pg_fetch_assoc($result);
            // Note: PostgreSQL column names are typically lowercase by default
            $schedule = [
                'scheduleID' => $data['scheduleid'],
                'showDate' => $data['showdate'],
                'showTime' => $data['showtime'],
                'price' => $data['price']
            ];
            $movie = [
                'movieID' => $data['movieid'],
                'movieTitle' => $data['movietitle'],
                'movieImg' => $data['movieimg'],
                'movieGenre' => $data['moviegenre'],
                'movieDuration' => $data['movieduration']
            ];
            $hall = [
                'hallID' => $data['hallid'],
                'hallName' => $data['hallname'],
                'hallType' => $data['halltype'],
                'totalSeats' => $data['totalseats']
            ];
            $theater = [
                'theaterName' => $data['theatername'],
                'theaterAddress' => $data['theateraddress'],
                'theaterCity' => $data['theatercity']
            ];
        } else {
            $errorMessage = "Schedule not found.";
        }
    } else {
        $errorMessage = "Error fetching schedule details: " . pg_last_error($conn);
    }

    // Fetch already booked seats for this schedule
    if ($schedule) {
        $queryBookedSeats = "SELECT seats FROM bookingtable WHERE scheduleID = $1";
        $resultBookedSeats = pg_query_params($conn, $queryBookedSeats, array($scheduleId));
        
        if ($resultBookedSeats) {
            while ($row = pg_fetch_assoc($resultBookedSeats)) {
                if (!empty($row['seats'])) {
                    // Merge individual seat numbers from comma-separated strings
                    $bookedSeats = array_merge($bookedSeats, explode(',', $row['seats']));
                }
            }
        } else {
            error_log("Error fetching booked seats: " . pg_last_error($conn));
        }
    }

} else {
    $errorMessage = "Invalid schedule ID. Please go back and select a movie schedule.";
}

// Handle booking form submission (this will now pass to payment.php)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_tickets'])) {
    if (empty($_POST['selected_seats']) || $_POST['selected_seats'] === '[]') {
        $errorMessage = "Please select at least one seat to proceed.";
    } else {
        $selectedSeats = json_decode($_POST['selected_seats'], true);
        $bookingFName = $_POST['bookingFName'];
        $bookingLName = $_POST['bookingLName'];
        $bookingPNumber = $_POST['bookingPNumber'];
        $bookingEmail = $_POST['bookingEmail'];
        $totalAmount = $_POST['total_amount'];

        // Store all necessary booking data in session for payment.php
        $_SESSION['booking_data'] = [
            'movieID' => $movie['movieID'],
            'scheduleID' => $schedule['scheduleID'],
            'hallID' => $hall['hallID'],
            'bookingTheatre' => $theater['theaterName'], // Use theater name directly
            'bookingHallName' => $hall['hallName'], // Add hall name for clarity
            'bookingType' => $hall['hallType'], // Using hallType as bookingType
            'bookingDate' => $schedule['showDate'],
            'bookingTime' => $schedule['showTime'],
            'bookingFName' => $bookingFName,
            'bookingLName' => $bookingLName,
            'bookingPNumber' => $bookingPNumber,
            'bookingEmail' => $bookingEmail,
            'seats' => implode(',', $selectedSeats), // Store as comma-separated string
            'amount' => $totalAmount,
            'ORDERID' => 'ORD' . uniqid() // Generate a unique ORDERID
        ];

        header("Location: payment.php");
        exit();
    }
}

// Close PostgreSQL connection
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Tickets - Showtime Select</title>
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
        .seat-grid-container {
            width: 100%;
            overflow-x: auto; /* Allow horizontal scrolling on small screens */
            padding-bottom: 15px; /* Space for scrollbar */
        }
        .seat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(40px, 1fr)); /* Responsive seat grid */
            gap: 10px;
            padding: 20px;
            background-color: #1f4068;
            border-radius: 8px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
            min-width: 320px; /* Minimum width to prevent squishing */
        }
        .seat {
            width: 40px;
            height: 40px;
            background-color: #4CAF50; /* Available */
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.8rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
            color: white;
            user-select: none; /* Prevent text selection */
            flex-shrink: 0; /* Prevent seats from shrinking too much */
        }
        .seat.selected {
            background-color: #e94560; /* Selected */
            transform: scale(1.05);
            outline: 2px solid #fff; /* White outline for selected */
        }
        .seat.booked {
            background-color: #888; /* Booked */
            cursor: not-allowed;
            opacity: 0.7;
            outline: none;
        }
        .seat.booked:hover {
            transform: none;
        }
        .screen-indicator {
            background-color: #333;
            color: #eee;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-transform: uppercase;
            letter-spacing: 2px;
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
        .form-input {
            background-color: #16213e;
            border: 1px solid #0f3460;
            color: #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            width: 100%;
        }
        .form-input:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 0 2px rgba(233, 69, 96, 0.5);
        }
        .footer-bg {
            background-color: #16213e;
        }
        .logo-text {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
        }
        .legend-item {
            display: flex;
            align-items: center;
        }
        .legend-color-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 8px;
        }
        /* Mobile adjustments */
        @media (max-width: 768px) {
            .seat-grid {
                grid-template-columns: repeat(auto-fill, minmax(35px, 1fr)); /* Auto-fill with min width for small screens */
                gap: 8px;
            }
            .seat {
                width: 35px;
                height: 35px;
                font-size: 0.7rem;
            }
        }
        @media (max-width: 500px) {
            .seat-grid {
                grid-template-columns: repeat(auto-fill, minmax(30px, 1fr));
                gap: 5px;
            }
            .seat {
                width: 30px;
                height: 30px;
                font-size: 0.65rem;
            }
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
        <?php elseif ($schedule && $movie && $hall && $theater): ?>
            <h1 class="text-4xl font-bold text-center mb-8 text-white">Book Tickets for <?php echo htmlspecialchars($movie['movieTitle']); ?></h1>

            <div class="card p-8 mb-8">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                    <img src="../<?php echo htmlspecialchars($movie['movieImg']); ?>" alt="<?php echo htmlspecialchars($movie['movieTitle']); ?>" class="w-32 h-48 object-cover rounded-md border-2 border-e94560">
                    <div>
                        <h2 class="text-3xl font-semibold text-white mb-2"><?php echo htmlspecialchars($movie['movieTitle']); ?></h2>
                        <p class="text-gray-300"><strong>Genre:</strong> <?php echo htmlspecialchars($movie['movieGenre']); ?></p>
                        <p class="text-gray-300"><strong>Duration:</strong> <?php echo htmlspecialchars($movie['movieDuration']); ?> min</p>
                        <p class="text-gray-300"><strong>Theater:</strong> <?php echo htmlspecialchars($theater['theaterName']); ?> (Hall: <?php echo htmlspecialchars($hall['hallName']); ?> - <?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($hall['hallType']))); ?>)</p>
                        <p class="text-gray-300"><strong>Location:</strong> <?php echo htmlspecialchars($theater['theaterAddress'] . ', ' . $theater['theaterCity']); ?></p>
                        <p class="text-gray-300"><strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($schedule['showDate'])); ?></p>
                        <p class="text-gray-300"><strong>Time:</strong> <?php echo date('h:i A', strtotime($schedule['showTime'])); ?></p>
                        <p class="text-white text-2xl font-bold mt-4">Price per ticket: ₹<?php echo number_format($schedule['price'], 2); ?></p>
                    </div>
                </div>
            </div>

            <div class="card p-8 mb-8">
                <h2 class="text-3xl font-bold text-white text-center mb-6">Select Your Seats</h2>
                <div class="screen-indicator mb-8">Screen This Way</div>
                <div class="seat-grid-container">
                    <div id="seat-grid" class="seat-grid mx-auto">
                        <!-- Seats will be generated here by JavaScript -->
                    </div>
                </div>
                <div class="flex justify-center mt-6 gap-6 text-gray-300">
                    <div class="legend-item"><span class="legend-color-box bg-green-500"></span> Available</div>
                    <div class="legend-item"><span class="legend-color-box bg-e94560"></span> Selected</div>
                    <div class="legend-item"><span class="legend-color-box bg-gray-500"></span> Booked</div>
                </div>
            </div>

            <div class="card p-8">
                <h2 class="text-3xl font-bold text-white text-center mb-6">Your Details</h2>
                <form id="booking-form" action="" method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="bookingFName" class="block text-gray-300 text-sm font-bold mb-2">First Name:</label>
                            <input type="text" id="bookingFName" name="bookingFName" class="form-input" required value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bookingLName" class="block text-gray-300 text-sm font-bold mb-2">Last Name:</label>
                            <input type="text" id="bookingLName" name="bookingLName" class="form-input" value="<?php echo htmlspecialchars($_SESSION['user_lname'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bookingPNumber" class="block text-gray-300 text-sm font-bold mb-2">Phone Number:</label>
                            <input type="tel" id="bookingPNumber" name="bookingPNumber" class="form-input" required value="<?php echo htmlspecialchars($_SESSION['user_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bookingEmail" class="block text-gray-300 text-sm font-bold mb-2">Email:</label>
                            <input type="email" id="bookingEmail" name="bookingEmail" class="form-input" required value="<?php echo htmlspecialchars($_SESSION['user_username'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <p class="text-2xl text-white font-bold">Selected Tickets: <span id="selected-count">0</span></p>
                        <p class="text-3xl text-white font-bold">Total Price: ₹<span id="total-price">0.00</span></p>
                    </div>

                    <input type="hidden" name="selected_seats" id="selected-seats-input">
                    <input type="hidden" name="total_amount" id="total-amount-input">
                    <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($schedule['scheduleID']); ?>">

                    <div class="text-center">
                        <button type="submit" name="book_tickets" class="btn-primary text-xl font-bold">Proceed to Payment</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer-bg text-gray-400 py-8 mt-12">
        <div class="container mx-auto text-center px-4">
            <p>&copy; <?php echo date('Y'); ?> Showtime Select. All rights reserved.</p>
            <p class="text-sm">Designed for educational purpose </p>
        </div>
    </footer>

    <script>
        const totalSeats = <?php echo json_encode($hall['totalSeats'] ?? 0); ?>;
        const pricePerTicket = <?php echo json_encode($schedule['price'] ?? 0); ?>;
        // Convert bookedSeatsArray elements to strings to ensure consistent comparison
        const bookedSeatsArray = <?php echo json_encode(array_map('strval', $bookedSeats)); ?>;
        let selectedSeats = [];

        const seatGrid = document.getElementById('seat-grid');
        const selectedCountSpan = document.getElementById('selected-count');
        const totalPriceSpan = document.getElementById('total-price');
        const selectedSeatsInput = document.getElementById('selected-seats-input');
        const totalAmountInput = document.getElementById('total-amount-input');

        function generateSeats() {
            if (totalSeats === 0) {
                seatGrid.innerHTML = '<p class="text-center text-gray-400 col-span-full">No seats configured for this hall.</p>';
                return;
            }

            seatGrid.innerHTML = ''; // Clear existing seats
            // Determine number of columns for seat layout
            const numColumns = Math.ceil(Math.sqrt(totalSeats * 0.8)); // Heuristic for a somewhat square layout
            seatGrid.style.gridTemplateColumns = `repeat(auto-fit, minmax(40px, 1fr))`;

            for (let i = 1; i <= totalSeats; i++) {
                const seatElement = document.createElement('div');
                seatElement.classList.add('seat');
                seatElement.textContent = i; // Display seat number

                const seatNumberStr = String(i); // Ensure seat number is a string for comparison

                if (bookedSeatsArray.includes(seatNumberStr)) {
                    seatElement.classList.add('booked');
                } else {
                    seatElement.addEventListener('click', toggleSeatSelection);
                    seatElement.dataset.seatNumber = seatNumberStr; // Store seat number
                }
                seatGrid.appendChild(seatElement);
            }
        }

        function toggleSeatSelection(event) {
            const seat = event.target;
            const seatNumber = seat.dataset.seatNumber;

            if (seat.classList.contains('booked')) {
                return; // Should not happen if event listener is only on available seats
            }

            if (seat.classList.contains('selected')) {
                // Deselect seat
                seat.classList.remove('selected');
                selectedSeats = selectedSeats.filter(s => s !== seatNumber);
            } else {
                // Select seat
                seat.classList.add('selected');
                selectedSeats.push(seatNumber);
            }
            updateBookingSummary();
        }

        function updateBookingSummary() {
            const total = selectedSeats.length * pricePerTicket;
            selectedCountSpan.textContent = selectedSeats.length;
            totalPriceSpan.textContent = total.toFixed(2);
            selectedSeatsInput.value = JSON.stringify(selectedSeats); // Store as JSON string
            totalAmountInput.value = total.toFixed(2);
        }

        // Initialize seats when the page loads
        document.addEventListener('DOMContentLoaded', generateSeats);

        // Pre-fill user details if logged in (PHP variables are already echoed into input values in the PHP part)
    </script>
</body>
</html>
