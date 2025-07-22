<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
    exit();
}

// Check if booking ID is provided
if (!isset($_GET['id'])) {
    header("Location: bookings.php");
    exit();
}

$bookingID = $_GET['id'];

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

$booking = null;
$payment = null;

// Get comprehensive booking details by joining all relevant tables
$bookingQuery = "
    SELECT b.*, m.movietitle, m.movieimg, m.moviegenre, m.movieduration,
           ms.showdate, ms.showtime, ms.price as ticketpriceperunit,
           h.hallname, h.halltype, t.theatername, t.theateraddress, t.theatercity
    FROM bookingtable b
    LEFT JOIN movietable m ON b.movieid = m.movieid
    LEFT JOIN movie_schedules ms ON b.scheduleid = ms.scheduleid
    LEFT JOIN theater_halls h ON b.hallid = h.hallid
    LEFT JOIN theaters t ON h.theaterid = t.theaterid
    WHERE b.bookingid = $1
";
$bookingResult = pg_query_params($conn, $bookingQuery, array($bookingID));

if (!$bookingResult || pg_num_rows($bookingResult) === 0) {
    header("Location: bookings.php");
    exit();
}

$booking = pg_fetch_assoc($bookingResult);
// Convert keys to lowercase for consistency with PostgreSQL's default behavior
$booking = array_change_key_case($booking, CASE_LOWER);

// Get payment details if available
$paymentQuery = "SELECT * FROM payment WHERE orderid = $1";
$paymentResult = pg_query_params($conn, $paymentQuery, array($booking['orderid']));
$payment = pg_num_rows($paymentResult) > 0 ? pg_fetch_assoc($paymentResult) : null;
if ($payment) {
    $payment = array_change_key_case($payment, CASE_LOWER);
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Booking - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            color: #fff;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #ced4da;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        .navbar .form-control {
            padding: .75rem 1rem;
            border-width: 0;
            border-radius: 0;
        }
        .form-control-dark {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .1);
        }
        .form-control-dark:focus {
            border-color: transparent;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, .25);
        }
        .main-content {
            margin-left: 240px;
            padding: 20px;
        }
        .booking-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .admin-user-info {
            display: flex;
            align-items: center;
        }
        .admin-user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .admin-user-info span {
            font-weight: bold;
        }
        .movie-details-section { /* Changed from .movie-details to avoid conflict */
            display: flex;
            align-items: center; /* Vertically align items */
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .movie-poster {
            width: 120px;
            height: auto;
            border-radius: 5px;
            margin-right: 20px;
        }
        .booking-info-grid { /* Changed from .booking-details to avoid conflict */
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .booking-detail-item { /* Changed from .booking-detail to avoid conflict */
            margin-bottom: 0; /* Reset margin from previous .booking-detail */
        }
        .booking-detail-item strong {
            display: block;
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .ticket {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px dashed #dee2e6;
        }
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #dee2e6;
        }
        .ticket-logo {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .ticket-qr {
            width: 80px;
            height: 80px;
            background-color: #fff;
            padding: 5px;
            border: 1px solid #dee2e6;
        }
        .action-buttons {
            margin-top: 30px;
            display: flex;
            justify-content: flex-start; /* Align buttons to start */
            gap: 10px; /* Space between buttons */
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
        }
        .btn-signout {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-signout:hover {
            background-color: #c82333;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                height: auto;
                padding: 0;
            }
            .sidebar-sticky {
                height: auto;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .admin-user-info {
                margin-top: 15px;
            }
            .booking-info-grid {
                grid-template-columns: 1fr; /* Single column for mobile */
            }
            .movie-details-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .movie-poster {
                margin-right: 0;
                margin-bottom: 15px;
            }
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            .action-buttons .btn {
                width: 100%; /* Full width buttons on mobile */
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="btn btn-signout" href="../admin/logout.php">Sign out</a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Theater Management</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="theaters.php">
                                <i class="fas fa-building"></i>
                                Theaters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt"></i>
                                Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-ticket-alt"></i>
                                Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <?php if ($_SESSION['admin_role'] == 1): ?>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Admin Functions (Super Admin)</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/dashboard.php">
                                <i class="fas fa-home"></i>
                                Super Admin Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/users.php">
                                <i class="fas fa-users"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/settings.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/reports.php">
                                <i class="fas fa-chart-bar"></i>
                                All Reports
                            </a>
                        </li>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Content Management (Super Admin)</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../content_manager/movies.php">
                                <i class="fas fa-film"></i>
                                Movies
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Booking Details</h1>
                    <div class="admin-user-info">
                        <img src="https://placehold.co/40x40/cccccc/333333?text=Admin" alt="Admin">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>

                <div class="booking-container">
                    <?php if ($booking): ?>
                    <div class="movie-details-section">
                        <?php if (!empty($booking['movieimg'])): ?>
                            <img src="../../<?php echo htmlspecialchars($booking['movieimg']); ?>" alt="<?php echo htmlspecialchars($booking['movietitle'] ?? 'Movie Poster'); ?>" class="movie-poster">
                        <?php else: ?>
                            <img src="https://placehold.co/120x180/cccccc/333333?text=No+Image" alt="No Movie Poster" class="movie-poster">
                        <?php endif; ?>
                        <div>
                            <h3><?php echo htmlspecialchars($booking['movietitle'] ?? 'N/A Movie'); ?></h3>
                            <p><strong>Order ID:</strong> <?php echo htmlspecialchars($booking['orderid']); ?></p>
                            <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['bookingid']); ?></p>
                        </div>
                    </div>
                    
                    <h4>Booking Information</h4>
                    <div class="booking-info-grid">
                        <div class="booking-detail-item">
                            <strong>Customer Name</strong>
                            <?php echo htmlspecialchars($booking['bookingfname'] . ' ' . $booking['bookinglname']); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Email</strong>
                            <?php echo htmlspecialchars($booking['bookingemail']); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Phone</strong>
                            <?php echo htmlspecialchars($booking['bookingpnumber']); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Movie Genre</strong>
                            <?php echo htmlspecialchars($booking['moviegenre'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Movie Duration</strong>
                            <?php echo htmlspecialchars($booking['movieduration'] ?? 'N/A'); ?> min
                        </div>
                        <div class="booking-detail-item">
                            <strong>Show Date</strong>
                            <?php echo htmlspecialchars($booking['showdate'] ? date('F j, Y', strtotime($booking['showdate'])) : 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Show Time</strong>
                            <?php echo htmlspecialchars($booking['showtime'] ? date('h:i A', strtotime($booking['showtime'])) : 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Theater</strong>
                            <?php echo htmlspecialchars($booking['theatername'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Hall</strong>
                            <?php echo htmlspecialchars($booking['hallname'] ?? 'N/A'); ?> (<?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($booking['halltype'] ?? ''))); ?>)
                        </div>
                        <div class="booking-detail-item">
                            <strong>Booked Seats</strong>
                            <?php echo htmlspecialchars($booking['seats'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Total Amount Paid</strong>
                            ₹<?php echo number_format($booking['amount'] ?? 0, 2); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Ticket Price per Unit</strong>
                            ₹<?php echo number_format($booking['ticketpriceperunit'] ?? 0, 2); ?>
                        </div>
                    </div>
                    
                    <?php if ($payment): ?>
                    <h4 class="mt-4">Payment Details</h4>
                    <div class="booking-info-grid">
                        <div class="booking-detail-item">
                            <strong>Transaction ID</strong>
                            <?php echo htmlspecialchars($payment['txnid'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Payment Mode</strong>
                            <?php echo htmlspecialchars($payment['paymentmode'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Amount Paid (PG)</strong>
                            <?php echo htmlspecialchars($payment['txnamount'] ?? 'N/A') . ' ' . htmlspecialchars($payment['currency'] ?? ''); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Transaction Date (PG)</strong>
                            <?php echo htmlspecialchars($payment['txndate'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Status (PG)</strong>
                            <span class="badge badge-<?php echo ($payment['status'] ?? '') === 'TXN_SUCCESS' ? 'success' : 'danger'; ?>">
                                <?php echo htmlspecialchars($payment['status'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Response Message</strong>
                            <?php echo htmlspecialchars($payment['respmsg'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Gateway Name</strong>
                            <?php echo htmlspecialchars($payment['gatewayname'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Bank Transaction ID</strong>
                            <?php echo htmlspecialchars($payment['banktxnid'] ?? 'N/A'); ?>
                        </div>
                        <div class="booking-detail-item">
                            <strong>Bank Name</strong>
                            <?php echo htmlspecialchars($payment['bankname'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="ticket mt-4">
                        <div class="ticket-header">
                            <div class="ticket-logo">Showtime Select</div>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo urlencode($booking['orderid']); ?>" alt="QR Code" class="ticket-qr">
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <h5><?php echo htmlspecialchars($booking['movietitle'] ?? 'N/A Movie'); ?></h5>
                                <p>
                                    <strong>Date & Time:</strong> <?php echo htmlspecialchars($booking['showdate'] ? date('F j, Y', strtotime($booking['showdate'])) : 'N/A'); ?> at <?php echo htmlspecialchars($booking['showtime'] ? date('h:i A', strtotime($booking['showtime'])) : 'N/A'); ?><br>
                                    <strong>Theatre:</strong> <?php echo htmlspecialchars($booking['theatername'] ?? 'N/A Theater'); ?> - <?php echo htmlspecialchars($booking['hallname'] ?? 'N/A Hall'); ?><br>
                                    <strong>Seats:</strong> <?php echo htmlspecialchars($booking['seats'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <p>
                                    <strong>Order ID:</strong><br>
                                    <?php echo htmlspecialchars($booking['orderid']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Bookings
                        </a>
                        <!-- Edit/Delete links might need separate files if they are complex -->
                        <!-- <a href="edit_booking.php?id=<?php echo htmlspecialchars($booking['bookingid']); ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Booking
                        </a>
                        <a href="delete_booking.php?id=<?php echo htmlspecialchars($booking['bookingid']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this booking?')">
                            <i class="fas fa-trash"></i> Delete Booking
                        </a> -->
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Ticket
                        </button>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        Booking details not found or invalid ID.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
