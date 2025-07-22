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

// Check if already logged in, redirect to appropriate dashboard
if (isset($_SESSION['admin_id'])) {
    if ($_SESSION['admin_role'] == 1) { // Super Admin
        header("Location: dashboard.php");
    } elseif ($_SESSION['admin_role'] == 2) { // Theater Manager
        header("Location: ../theater_manager/dashboard.php");
    } elseif ($_SESSION['admin_role'] == 3) { // Content Manager
        header("Location: ../content_manager/dashboard.php");
    } else {
        // Fallback for unknown role, or show a generic error/unauthorized page
        header("Location: unauthorized.php"); // You might create this page
    }
    exit();
}

$error = "";

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = $_POST["username"];
    $input_password = $_POST["password"];

    // Use prepared statements to prevent SQL injection
    // Changed column names to lowercase as per PostgreSQL's default behavior for unquoted identifiers
    $query = "SELECT adminid, username, password, fullname, roleid FROM admin_users WHERE username = $1 AND status = 'active' LIMIT 1";
    $result = pg_query_params($conn, $query, array($input_username));

    if ($result && pg_num_rows($result) > 0) {
        $admin = pg_fetch_assoc($result);
        // Verify the password (assuming it's hashed in the database as per combined SQL)
        if (password_verify($input_password, $admin['password'])) {
            // Set session variables
            // Accessing fetched data using lowercase keys
            $_SESSION['admin_id'] = $admin['adminid'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['fullname'];
            $_SESSION['admin_role'] = $admin['roleid']; // IMPORTANT: Store the roleID

            // Update last login time
            // Changed column name to lowercase
            $updateQuery = "UPDATE admin_users SET lastlogin = NOW() WHERE adminid = $1";
            pg_query_params($conn, $updateQuery, array($admin['adminid']));

            // Redirect based on role
            $_SESSION['just_logged_in'] = true; // Use this for welcome messages if needed
            if ($_SESSION['admin_role'] == 1) { // Super Admin
                header("Location: dashboard.php");
            } elseif ($_SESSION['admin_role'] == 2) { // Theater Manager
                header("Location: ../theater_manager/dashboard.php");
            } elseif ($_SESSION['admin_role'] == 3) { // Content Manager
                header("Location: ../content_manager/dashboard.php");
            } else {
                // Fallback for unknown role
                $error = "Access denied for this role.";
                session_destroy(); // Destroy session for unknown roles
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Showtime Select</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo h1 {
            color: #e83e8c;
            font-weight: bold;
        }
        .login-logo p {
            color: #6c757d;
        }
        .login-form .form-control {
            height: 45px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .login-form .btn-primary {
            background-color: #e83e8c;
            border-color: #e83e8c;
            height: 45px;
            font-weight: bold;
            border-radius: 4px;
        }
        .login-form .btn-primary:hover {
            background-color: #d33076;
            border-color: #d33076;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #6c757d;
            text-decoration: none;
        }
        .back-link a:hover {
            color: #e83e8c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <h1>Showtime Select</h1>
                <p>Admin Panel</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form class="login-form" method="post" action="">
                <div class="form-group">
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </div>
            </form>
            
            <div class="back-link">
                <a href="../user/index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
