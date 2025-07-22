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

$theaterId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirectUrl = "theaters.php"; // Default redirect location

if ($theaterId > 0) {
    // IMPORTANT: Foreign key constraints should ideally handle cascading deletes
    // if configured with ON DELETE CASCADE for theater_halls, movie_schedules, and bookingtable.
    // Ensure cascades are set up correctly on all levels (theaters -> halls -> schedules -> bookings).

    // Delete the theater
    $deleteQuery = "DELETE FROM theaters WHERE theaterid = $1";
    $deleteResult = pg_query_params($conn, $deleteQuery, array($theaterId));
    
    if ($deleteResult) {
        // If ON DELETE CASCADE is set up correctly, associated halls, schedules, and bookings
        // should be deleted automatically.
        $redirectUrl .= "?success=Theater deleted successfully!";
    } else {
        // Provide a more specific error if the deletion fails, especially due to FKs
        $errorMessage = "Error deleting theater: " . pg_last_error($conn);
        // Check for specific PostgreSQL foreign key violation error message
        if (strpos(pg_last_error($conn), "foreign key constraint fails") !== false || strpos(pg_last_error($conn), "violates foreign key constraint") !== false) {
            $errorMessage = "Cannot delete theater. There are still associated halls, schedules, or bookings. Please delete them first or ensure cascading deletes are properly configured in your database schema.";
        }
        $redirectUrl .= "?error=" . urlencode($errorMessage);
    }
} else {
    $redirectUrl .= "?error=Invalid theater ID provided for deletion.";
}

pg_close($conn);

header("Location: " . $redirectUrl);
exit();
?>
