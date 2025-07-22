<?php
session_start();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
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

$error = "";
$message = "";

// Check for redirect message
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = $_POST["username"];
    $input_password = $_POST["password"];

    // Use pg_query_params for prepared statements to prevent SQL injection
    // Note: Assuming plain text passwords based on provided movie_db.sql.
    // In a real application, always hash passwords using password_hash() and verify with password_verify().
    $query = "SELECT id, username, name, password, phone FROM users WHERE username = $1 LIMIT 1";
    $result = pg_query_params($conn, $query, array($input_username));

    if ($result) {
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            // Direct comparison for plain text passwords as seen in movie_db.sql
            // Note: PostgreSQL column names are typically lowercase by default
            if ($input_password === $user['password']) { // DIRECT COMPARISON FOR PLAIN TEXT PASSWORDS
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_phone'] = $user['phone']; // Assuming 'phone' column exists and is fetched
                $_SESSION['user_email'] = $user['username']; // Assuming username is email or used as email for booking

                // Redirect to the original page if available, otherwise to profile
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']); // Clear the redirect URL
                    header("Location: " . $redirect_url);
                } else {
                    header("Location: profile.php");
                }
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Database query failed: " . pg_last_error($conn);
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
    <title>User Login - Showtime Select</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Full viewport height */
            padding: 20px;
        }
        .login-container {
            max-width: 450px;
            width: 100%;
            background-color: #0f3460; /* Dark blue for card */
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            padding: 40px;
            text-align: center;
        }
        .login-logo {
            margin-bottom: 30px;
        }
        .login-logo h1 {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        .login-logo p {
            color: #e0e0e0;
            font-size: 1.1rem;
        }
        .form-input {
            background-color: #16213e;
            border: 1px solid #0f3460;
            color: #e0e0e0;
            border-radius: 8px;
            padding: 12px 18px;
            width: 100%;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .form-input:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 0 2px rgba(233, 69, 96, 0.5);
        }
        .btn-primary {
            background-color: #e94560;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            font-weight: bold;
            width: 100%;
            font-size: 1.1rem;
        }
        .btn-primary:hover {
            background-color: #b82e4a;
        }
        .error-message {
            color: #ef4444; /* Tailwind red-500 */
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .link-text {
            color: #8892b0; /* Light gray-blue for links */
            transition: color 0.3s ease;
        }
        .link-text:hover {
            color: #e94560;
        }
    </style>
</head>
<body class="antialiased">
    <div class="login-container">
        <div class="login-logo">
            <h1>Showtime Select</h1>
            <p>User Login</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="bg-blue-600 text-white p-3 rounded-lg mb-4 text-center">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-4">
                <input type="text" name="username" placeholder="Username (or Email)" class="form-input" required>
            </div>
            <div class="mb-6">
                <input type="password" name="password" placeholder="Password" class="form-input" required>
            </div>
            <button type="submit" class="btn-primary">Login</button>
        </form>

        <div class="mt-6 text-center text-gray-400">
            Don't have an account? <a href="register.php" class="link-text font-semibold">Register Here</a>
        </div>
        <div class="mt-4 text-center">
            <a href="index.php" class="link-text inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>
