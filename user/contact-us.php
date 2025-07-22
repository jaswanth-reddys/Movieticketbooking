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

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $fName = $_POST['fName'];
    $lName = $_POST['lName'];
    $eMail = $_POST['eMail'];
    $feedback = $_POST['feedback'];

    // Use pg_query_params for prepared statements in PostgreSQL
    $query = "INSERT INTO feedbacktable (senderfName, senderlName, sendereMail, senderfeedback) VALUES ($1, $2, $3, $4)";
    $result = pg_query_params($conn, $query, array($fName, $lName, $eMail, $feedback));

    if ($result) {
        $message = '<div class="bg-green-600 text-white p-3 rounded-lg mb-4">Your message has been sent successfully! We will get back to you soon.</div>';
    } else {
        $message = '<div class="bg-red-600 text-white p-3 rounded-lg mb-4">Error sending message: ' . pg_last_error($conn) . '</div>';
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
    <title>Contact Us - Showtime Select</title>
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
        <h1 class="text-4xl font-bold text-center mb-10 text-white">Contact Us</h1>

        <?php echo $message; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="card p-8">
                <h2 class="text-3xl font-bold text-white mb-6">Send Us a Message</h2>
                <form action="" method="POST" class="space-y-5">
                    <div>
                        <label for="fName" class="block text-gray-300 text-sm font-bold mb-2">First Name:</label>
                        <input type="text" id="fName" name="fName" class="form-input" required>
                    </div>
                    <div>
                        <label for="lName" class="block text-gray-300 text-sm font-bold mb-2">Last Name:</label>
                        <input type="text" id="lName" name="lName" class="form-input">
                    </div>
                    <div>
                        <label for="eMail" class="block text-gray-300 text-sm font-bold mb-2">Email Address:</label>
                        <input type="email" id="eMail" name="eMail" class="form-input" required>
                    </div>
                    <div>
                        <label for="feedback" class="block text-gray-300 text-sm font-bold mb-2">Your Message:</label>
                        <textarea id="feedback" name="feedback" rows="7" class="form-input resize-y" required></textarea>
                    </div>
                    <div class="text-center">
                        <button type="submit" name="submit_feedback" class="btn-primary text-xl font-bold">Send Message</button>
                    </div>
                </form>
            </div>

            <div class="card p-8">
                <h2 class="text-3xl font-bold text-white mb-6">Address & Info</h2>
                <div class="space-y-5 text-lg">
                    <div>
                        <h3 class="text-xl font-semibold text-e94560 mb-2"><i class="fas fa-phone-alt mr-3"></i> Phone Numbers</h3>
                        <p><a href="tel:+91xxxxxxxxx" class="text-gray-300 hover:underline">+91 xxxxxxxxxx</a></p>
                        <p><a href="tel:+91xxxxxxxxx" class="text-gray-300 hover:underline">+91 xxxxxxxxxx</a></p>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-e94560 mb-2"><i class="fas fa-map-marker-alt mr-3"></i> Address</h3>
                        <p class="text-gray-300">Acad Block, IIIT RAICHUR, Yermarus Camp</p>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-e94560 mb-2"><i class="fas fa-envelope mr-3"></i> E-mail</h3>
                        <p><a href="mailto:cs22b1058@iiitr.ac.in" class="text-gray-300 hover:underline">cs22b1058@iiitr.ac.in</a></p>
                    </div>
                </div>
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
</body>
</html>
