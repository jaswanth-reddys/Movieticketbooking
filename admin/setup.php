<?php
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

// Read SQL file
// IMPORTANT: PostgreSQL does not support multi_query like MySQLi.
// You need to parse the SQL file into individual statements and execute them one by one.
// This is a simplified approach; for complex SQL files, a more robust parser would be needed.
$sql_file_content = file_get_contents('../database/admin_updates.sql');

// Split SQL statements by semicolon, but be careful with semicolons inside comments or strings
$sql_statements = array_filter(array_map('trim', explode(';', $sql_file_content)));

$errors = [];
foreach ($sql_statements as $stmt) {
    if (empty($stmt)) continue; // Skip empty statements

    // Attempt to execute each statement
    $result = pg_query($conn, $stmt);
    if (!$result) {
        $errors[] = "Error executing SQL: " . pg_last_error($conn) . "\nStatement: " . $stmt;
    }
}

if (empty($errors)) {
    echo "<h2>Database setup completed successfully!</h2>";
    echo "<p>The admin panel has been set up. You can now <a href='index.php'>login to the admin panel</a> using:</p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><strong>Important:</strong> Please change the default password after logging in for the first time.</p>";
} else {
    echo "<h2>Errors occurred during database setup:</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}

pg_close($conn);
?>
