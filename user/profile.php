<?php
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

$userBookings = [];
$errorMessage = '';
$message = ''; // For profile update/password change messages

$userEmail = $_SESSION['user_username'] ?? ''; // Assuming username is used as email for booking

// Get user information for profile display
$user = null;
if (isset($_SESSION['user_id'])) {
    $query = "SELECT id, username, name, phone FROM users WHERE id = $1";
    $result = pg_query_params($conn, $query, array($_SESSION['user_id']));
    if ($result) {
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            // Convert keys to lowercase for consistency with PostgreSQL's default behavior
            $user = array_change_key_case($user, CASE_LOWER);
        }
    } else {
        error_log("Error fetching user details: " . pg_last_error($conn));
    }
}


// Fetch bookings for the logged-in user
if (!empty($userEmail)) {
    $query = "
        SELECT b.*, m.movieTitle, m.movieImg, m.movieGenre,
               ms.showDate, ms.showTime, ms.price as ticketPrice,
               h.hallName, h.hallType, t.theaterName
        FROM bookingtable b
        LEFT JOIN movietable m ON b.movieID = m.movieID
        LEFT JOIN movie_schedules ms ON b.scheduleID = ms.scheduleID
        LEFT JOIN theater_halls h ON b.hallID = h.hallID
        LEFT JOIN theaters t ON h.theaterID = t.theaterID
        WHERE b.bookingEmail = $1
        ORDER BY ms.showDate DESC, ms.showTime DESC
    ";
    $result = pg_query_params($conn, $query, array($userEmail));
    if ($result) {
        if (pg_num_rows($result) > 0) {
            while ($row = pg_fetch_assoc($result)) {
                // Convert keys to lowercase for consistency with PostgreSQL's default behavior
                $userBookings[] = array_change_key_case($row, CASE_LOWER);
            }
        } else {
            $errorMessage = "You have no past bookings yet.";
        }
    } else {
        $errorMessage = "Error fetching bookings: " . pg_last_error($conn);
    }
} else {
    $errorMessage = "User email not found in session. Please log in again.";
}

// Process form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    
    $updateQuery = "UPDATE users SET name = $1, phone = $2 WHERE id = $3";
    $updateResult = pg_query_params($conn, $updateQuery, array($name, $phone, $_SESSION['user_id']));
    
    if ($updateResult) {
        $message = '<div class="bg-green-600 text-white p-3 rounded-lg mb-4">Profile updated successfully!</div>';
        // Update session variables immediately
        $_SESSION['user_name'] = $name;
        $_SESSION['user_phone'] = $phone;
        // Re-fetch user data to ensure UI is consistent
        $query = "SELECT id, username, name, phone FROM users WHERE id = $1";
        $result = pg_query_params($conn, $query, array($_SESSION['user_id']));
        if ($result) {
            $user = pg_fetch_assoc($result);
            $user = array_change_key_case($user, CASE_LOWER);
        }
    } else {
        $message = '<div class="bg-red-600 text-white p-3 rounded-lg mb-4">Error updating profile: ' . pg_last_error($conn) . '</div>';
    }
}

// Process form submission for password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Re-fetch user data to get the current password for verification
    $query = "SELECT password FROM users WHERE id = $1";
    $result = pg_query_params($conn, $query, array($_SESSION['user_id']));
    $userPasswordData = null;
    if ($result && pg_num_rows($result) > 0) {
        $userPasswordData = pg_fetch_assoc($result);
        $userPasswordData = array_change_key_case($userPasswordData, CASE_LOWER);
    }
    
    // IMPORTANT: Assuming passwords are NOT HASHED based on movie_db.sql's user dump ('123').
    // In a real system, you would use password_verify($current_password, $userPasswordData['password'])
    // and password_hash($new_password, PASSWORD_DEFAULT) for new passwords.
    
    if ($userPasswordData && $current_password === $userPasswordData['password']) { // Direct comparison for plain text passwords
        if ($new_password === $confirm_password) {
            $updateQuery = "UPDATE users SET password = $1 WHERE id = $2";
            $updateResult = pg_query_params($conn, $updateQuery, array($new_password, $_SESSION['user_id'])); // Storing plain text password
            if ($updateResult) {
                $message = '<div class="bg-green-600 text-white p-3 rounded-lg mb-4">Password changed successfully!</div>';
            } else {
                $message = '<div class="bg-red-600 text-white p-3 rounded-lg mb-4">Error changing password: ' . pg_last_error($conn) . '</div>';
            }
        } else {
            $message = '<div class="bg-red-600 text-white p-3 rounded-lg mb-4">New passwords do not match!</div>';
        }
    } else {
        $message = '<div class="bg-red-600 text-white p-3 rounded-lg mb-4">Current password is incorrect!</div>';
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
    <title><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>'s Profile - Showtime Select</title>
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
        .booking-item {
            background-color: #1f4068; /* Slightly lighter blue for booking items */
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease;
        }
        .booking-item:hover {
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
        .profile-header-card {
            background-color: #16213e;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            margin-bottom: 30px;
        }
        .tab-button {
            background-color: #0f3460;
            color: #e0e0e0;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
            font-weight: 500;
        }
        .tab-button.active {
            background-color: #e94560;
            color: white;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.3);
        }
        .tab-button:not(.active):hover {
            background-color: #1f4068;
        }
        .tab-content-panel {
            background-color: #0f3460;
            padding: 30px;
            border-radius: 0 8px 8px 8px; /* Rounded bottom corners and right top */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
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
        <div class="profile-header-card">
            <h1 class="text-4xl font-bold text-white mb-2">Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>!</h1>
            <p class="text-gray-300 text-lg">Your email: <?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></p>
            <p class="text-gray-300 text-lg">Your phone: <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></p>
        </div>

        <?php echo $message; // Display general messages ?>

        <div class="flex flex-wrap justify-center md:justify-start gap-2 mb-6" id="profileTabs">
            <button class="tab-button active" data-tab="bookings">My Bookings</button>
            <button class="tab-button" data-tab="edit-profile">Edit Profile</button>
            <button class="tab-button" data-tab="change-password">Change Password</button>
        </div>

        <div id="profileTabsContent">
            <div id="bookings" class="tab-content-panel active">
                <h3 class="text-3xl font-bold text-white mb-6">My Booking History</h3>
                
                <?php if (!empty($errorMessage) && empty($userBookings)): ?>
                    <div class="bg-blue-600 text-white p-4 rounded-lg text-center">
                        <?php echo htmlspecialchars($errorMessage); ?> <a href="index.php" class="underline font-semibold hover:text-blue-200">Browse movies</a> to book your first ticket!
                    </div>
                <?php elseif (!empty($userBookings)): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <?php foreach ($userBookings as $booking): ?>
                            <div class="booking-item p-6 flex flex-col sm:flex-row items-center sm:items-start gap-4">
                                <?php if ($booking['movieimg']): ?>
                                    <img src="../<?php echo htmlspecialchars($booking['movieimg']); ?>" alt="<?php echo htmlspecialchars($booking['movietitle']); ?>" class="w-24 h-36 object-cover rounded-md border-2 border-e94560 flex-shrink-0">
                                <?php else: ?>
                                    <div class="w-24 h-36 bg-gray-700 rounded-md flex items-center justify-center text-gray-400 text-center text-xs border-2 border-e94560 flex-shrink-0">No Image</div>
                                <?php endif; ?>
                                <div class="flex-grow text-center sm:text-left">
                                    <h4 class="text-2xl font-semibold text-white mb-2"><?php echo htmlspecialchars($booking['movietitle'] ?? 'N/A Movie'); ?></h4>
                                    <p class="text-gray-300"><strong>Booking ID:</strong> <span class="text-e94560"><?php echo htmlspecialchars($booking['bookingid']); ?></span></p>
                                    <p class="text-gray-300"><strong>Order ID:</strong> <?php echo htmlspecialchars($booking['orderid']); ?></p>
                                    <p class="text-gray-300"><strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($booking['showdate'] ?? '')); ?></p>
                                    <p class="text-gray-300"><strong>Time:</strong> <?php echo date('h:i A', strtotime($booking['showtime'] ?? '')); ?></p>
                                    <p class="text-gray-300"><strong>Theater:</strong> <?php echo htmlspecialchars($booking['theatername'] ?? 'N/A Theater'); ?></p>
                                    <p class="text-gray-300"><strong>Hall:</strong> <?php echo htmlspecialchars($booking['hallname'] ?? 'N/A Hall'); ?> (<?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($booking['halltype'] ?? ''))); ?>)</p>
                                    <p class="text-gray-300"><strong>Seats:</strong> <span class="font-bold text-yellow-300"><?php echo htmlspecialchars($booking['seats'] ?? 'N/A'); ?></span></p>
                                    <p class="text-white text-xl font-bold mt-2">Total Amount: ₹<?php echo number_format($booking['amount'] ?? 0, 2); ?></p>
                                    <a href="booking_confirmation.php?booking_id=<?php echo htmlspecialchars($booking['bookingid']); ?>" class="btn-primary mt-4 inline-block text-sm">View Full Ticket</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="edit-profile" class="tab-content-panel hidden">
                <h3 class="text-3xl font-bold text-white mb-6">Edit Profile</h3>
                <form action="" method="POST" class="space-y-6">
                    <div>
                        <label for="name" class="block text-gray-300 text-sm font-bold mb-2">Full Name:</label>
                        <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="email" class="block text-gray-300 text-sm font-bold mb-2">Email:</label>
                        <input type="email" id="email" class="form-input bg-gray-700 cursor-not-allowed" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                        <p class="text-gray-500 text-sm mt-1">Email cannot be changed as it's used for login.</p>
                    </div>
                    <div>
                        <label for="phone" class="block text-gray-300 text-sm font-bold mb-2">Phone Number:</label>
                        <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="text-center">
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>

            <div id="change-password" class="tab-content-panel hidden">
                <h3 class="text-3xl font-bold text-white mb-6">Change Password</h3>
                <form action="" method="POST" class="space-y-6">
                    <div>
                        <label for="current_password" class="block text-gray-300 text-sm font-bold mb-2">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                    </div>
                    <div>
                        <label for="new_password" class="block text-gray-300 text-sm font-bold mb-2">New Password:</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-gray-300 text-sm font-bold mb-2">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                    </div>
                    <div class="text-center">
                        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-bg text-gray-400 py-8 mt-12">
        <div class="container mx-auto text-center px-4">
            <p>&copy; <?php echo date('Y'); ?> Showtime Select. All rights reserved.</p>
            <p class="text-sm">Designed for educational purpose </p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('#profileTabs .tab-button');
            const tabContents = document.querySelectorAll('#profileTabsContent .tab-content-panel');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetTab = button.dataset.tab;

                    // Deactivate all buttons and hide all content
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.add('hidden'));

                    // Activate the clicked button and show its content
                    button.classList.add('active');
                    document.getElementById(targetTab).classList.remove('hidden');
                });
            });

            // Show initial tab (bookings)
            document.getElementById('bookings').classList.remove('hidden');
        });
    </script>
</body>
</html>
