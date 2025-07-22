<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
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

$hallId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$theaterId = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0; // Needed to redirect back correctly
$redirectUrl = "theater_halls.php?theater_id=" . $theaterId; // Default redirect

if ($hallId > 0 && $theaterId > 0) {
    // Check for dependencies (schedules) before deleting hall
    $checkSchedulesQuery = "SELECT COUNT(*) as count FROM movie_schedules WHERE hallid = $1";
    $checkSchedulesResult = pg_query_params($conn, $checkSchedulesQuery, array($hallId));
    
    if (!$checkSchedulesResult) {
        $errorMessage = "Database error checking schedules: " . pg_last_error($conn);
        $redirectUrl .= "&error=" . urlencode($errorMessage);
    } else {
        $schedulesCount = pg_fetch_assoc($checkSchedulesResult)['count'];

        if ($schedulesCount > 0) {
            $errorMessage = "Cannot delete hall. It has " . $schedulesCount . " schedule(s) associated. Please delete all associated schedules first.";
            $redirectUrl .= "&error=" . urlencode($errorMessage);
        } else {
            // Delete the hall
            $deleteQuery = "DELETE FROM theater_halls WHERE hallid = $1";
            $deleteResult = pg_query_params($conn, $deleteQuery, array($hallId));
            
            if ($deleteResult) {
                $redirectUrl .= "&success=Hall deleted successfully!";
            } else {
                $errorMessage = "Error deleting hall: " . pg_last_error($conn);
                $redirectUrl .= "&error=" . urlencode($errorMessage);
            }
        }
    }
} else {
    $redirectUrl = "theaters.php?error=" . urlencode("Invalid hall or theater ID provided for deletion.");
}

pg_close($conn);

header("Location: " . $redirectUrl);
exit();
?>
