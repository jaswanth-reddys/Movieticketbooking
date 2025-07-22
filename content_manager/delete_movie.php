<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Content Manager (roleID 3)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 3)) {
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

$movieId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirectUrl = "movies.php"; // Default redirect location

if ($movieId > 0) {
    // Before deleting movie, check for dependent records in movie_schedules
    // IMPORTANT: Foreign key constraints should ideally handle cascading deletes
    // if configured with ON DELETE CASCADE.
    
    // Optional: Get movie image path before deletion to remove the file
    // Using lowercase column names
    $stmtImgQuery = "SELECT movieimg FROM movietable WHERE movieid = $1";
    $stmtImgResult = pg_query_params($conn, $stmtImgQuery, array($movieId));
    $movieImgPath = null;
    if ($stmtImgResult && pg_num_rows($stmtImgResult) > 0) {
        $row = pg_fetch_assoc($stmtImgResult);
        $movieImgPath = $row['movieimg'];
    }

    // Check for dependencies (schedules) before deleting movie
    // Using lowercase column names
    $checkSchedulesQuery = "SELECT COUNT(*) as count FROM movie_schedules WHERE movieid = $1";
    $checkSchedulesResult = pg_query_params($conn, $checkSchedulesQuery, array($movieId));
    $schedulesCount = pg_fetch_assoc($checkSchedulesResult)['count'];

    if ($schedulesCount > 0) {
        $errorMessage = "Cannot delete movie. It is associated with " . $schedulesCount . " schedule(s). Please delete all associated schedules first.";
        $redirectUrl .= "?error=" . urlencode($errorMessage);
    } else {
        // Delete the movie
        // Using lowercase column names
        $deleteQuery = "DELETE FROM movietable WHERE movieid = $1";
        $deleteResult = pg_query_params($conn, $deleteQuery, array($movieId));
        
        if ($deleteResult) {
            // If ON DELETE CASCADE is set up for movie_schedules,
            // then associated schedules and their related bookings (if FK from bookings to schedules)
            // should also be deleted automatically.
            
            // Delete the associated image file if it exists
            // Path needs to be relative to the script's location or absolute
            if ($movieImgPath && file_exists("../../" . $movieImgPath)) { // Two levels up to project root, then img/
                // Ensure the path is within the expected img directory to prevent directory traversal
                if (strpos($movieImgPath, 'img/') === 0 && realpath("../../" . $movieImgPath)) {
                    unlink("../../" . $movieImgPath);
                }
            }
            
            $redirectUrl .= "?success=Movie deleted successfully!";
        } else {
            $redirectUrl .= "?error=Error deleting movie: " . urlencode(pg_last_error($conn));
        }
    }
} else {
    $redirectUrl .= "?error=Invalid movie ID provided for deletion.";
}

pg_close($conn);

header("Location: " . $redirectUrl);
exit();
?>
