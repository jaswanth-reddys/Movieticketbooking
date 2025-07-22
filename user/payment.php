<?php
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?message=Please login to complete payment");
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

$bookingData = $_SESSION['booking_data'] ?? null;
$paymentStatus = 'failed'; // Default to failed
$errorMessage = '';
$bookingID = null; // To store the new booking ID

if ($bookingData) {
    // --- SIMULATED PAYMENT GATEWAY PROCESS ---
    // In a real application, you would integrate with a payment gateway here.
    // This involves sending transaction details to the gateway, handling callbacks,
    // and verifying the payment response.
    //
    // Since the actual Paytm library files (config_paytm.php, encdec_paytm.php)
    // and merchant credentials are not available, we are simulating a successful payment.
    //
    // The crucial fix: Insert into 'payment' table FIRST, as 'bookingtable'
    // has a foreign key constraint to it.
    // ------------------------------------------

    $paymentSuccess = true; // Simulate successful payment for demonstration

    if ($paymentSuccess) {
        $paymentStatus = 'success';

        // Extract required data for payment table from bookingData and generate defaults
        $orderID = $bookingData['ORDERID'];
        $txnAmount = $bookingData['amount'];
        $mid = "SIMULATED_MID"; // Dummy Merchant ID
        $txnID = "SIMULATED_TXN_" . uniqid(); // Dummy Transaction ID
        $paymentMode = "SIMULATED_NB"; // Dummy Payment Mode (Net Banking)
        $currency = "INR";
        $txnDate = date('Y-m-d H:i:s');
        $status = "TXN_SUCCESS";
        $respCode = "01";
        $respMsg = "Txn Success";
        $gatewayName = "SIMULATED_GATEWAY";
        $bankTxnID = "SIMULATED_BANKTXN_" . uniqid();
        $bankName = "SIMULATED_BANK";
        $checksumHash = "SIMULATED_CHECKSUM"; // Dummy Checksum

        // 1. Insert into payment table
        // Use pg_query_params for prepared statements
        $paymentInsertQuery = "
            INSERT INTO payment
            (ORDERID, MID, TXNID, TXNAMOUNT, PAYMENTMODE, CURRENCY, TXNDATE, STATUS, RESPCODE, RESPMSG, GATEWAYNAME, BANKTXNID, BANKNAME, CHECKSUMHASH)
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14)
        ";
        $paymentInsertResult = pg_query_params(
            $conn,
            $paymentInsertQuery,
            array($orderID, $mid, $txnID, $txnAmount, $paymentMode, $currency, $txnDate, $status, $respCode, $respMsg, $gatewayName, $bankTxnID, $bankName, $checksumHash)
        );

        if ($paymentInsertResult) {
            // Payment record inserted successfully, now insert the booking
            // Use RETURNING bookingID to get the newly generated bookingID
            $bookingInsertQuery = "
                INSERT INTO bookingtable
                (movieID, scheduleID, hallID, bookingTheatre, bookingType, bookingDate, bookingTime, bookingFName, bookingLName, bookingPNumber, bookingEmail, ORDERID, seats, amount)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14)
                RETURNING bookingID
            ";
            $bookingInsertResult = pg_query_params(
                $conn,
                $bookingInsertQuery,
                array(
                    $bookingData['movieID'],
                    $bookingData['scheduleID'],
                    $bookingData['hallID'],
                    $bookingData['bookingTheatre'],
                    $bookingData['bookingType'],
                    $bookingData['bookingDate'],
                    $bookingData['bookingTime'],
                    $bookingData['bookingFName'],
                    $bookingData['bookingLName'],
                    $bookingData['bookingPNumber'],
                    $bookingData['bookingEmail'],
                    $bookingData['ORDERID'], // Use the same ORDERID
                    $bookingData['seats'],
                    $bookingData['amount']
                )
            );

            if ($bookingInsertResult) {
                $bookingRow = pg_fetch_assoc($bookingInsertResult);
                $bookingID = $bookingRow['bookingid']; // Get the returned bookingID
                // Clear session data after successful booking
                unset($_SESSION['booking_data']);
                header("Location: booking_confirmation.php?booking_id=" . $bookingID);
                exit();
            } else {
                // If booking fails, you might want to consider rolling back the payment record
                // For simplicity here, we'll just report the error.
                $errorMessage = "Failed to record booking in the database: " . pg_last_error($conn);
                $paymentStatus = 'failed';
            }
        } else {
            $errorMessage = "Failed to record payment details in the database: " . pg_last_error($conn);
            $paymentStatus = 'failed';
        }

    } else {
        $errorMessage = "Payment gateway declined the transaction (simulated).";
    }
} else {
    $errorMessage = "No booking data found for payment. Please start a new booking from the movie details page.";
}

// Close PostgreSQL connection
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Showtime Select</title>
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
            flex-direction: column;
            min-height: 100vh;
        }
        header, footer {
            flex-shrink: 0;
        }
        main {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
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
    <main class="container mx-auto px-4 py-8 text-center">
        <div class="card p-8 max-w-lg mx-auto">
            <?php if ($paymentStatus == 'success' && empty($errorMessage)): ?>
                <i class="fas fa-check-circle text-green-500 text-6xl mb-6"></i>
                <h1 class="text-4xl font-bold text-white mb-4">Payment Simulated Successfully!</h1>
                <p class="text-gray-300 text-lg mb-6">Your booking is being processed. Redirecting to confirmation page...</p>
                <p class="text-sm text-gray-500 mt-4">
                    Note: This is a simulated payment. A real payment gateway integration (like Paytm) requires additional library files (`config_paytm.php`, `encdec_paytm.php`) and merchant credentials which were not provided.
                </p>
            <?php elseif ($paymentStatus == 'failed' || !empty($errorMessage)): ?>
                <i class="fas fa-times-circle text-red-500 text-6xl mb-6"></i>
                <h1 class="text-4xl font-bold text-white mb-4">Payment Failed!</h1>
                <p class="text-gray-300 text-lg mb-6"><?php echo htmlspecialchars($errorMessage); ?></p>
                <a href="booking.php?schedule_id=<?php echo htmlspecialchars($bookingData['scheduleID'] ?? ''); ?>" class="btn-primary inline-block">Try Again</a>
                <p class="text-sm text-gray-500 mt-4">
                    Note: This is a simulated payment. A real payment gateway integration (like Paytm) requires additional library files (`config_paytm.php`, `encdec_paytm.php`) and merchant credentials which were not provided.
                </p>
            <?php else: // Should not happen if logic is sequential ?>
                <i class="fas fa-exclamation-circle text-yellow-500 text-6xl mb-6"></i>
                <h1 class="text-4xl font-bold text-white mb-4">Payment Processing...</h1>
                <p class="text-gray-300 text-lg mb-6">Please do not close this window.</p>
                <?php if (!empty($errorMessage)): ?>
                    <p class="text-red-400 mt-4"><?php echo htmlspecialchars($errorMessage); ?></p>
                    <a href="index.php" class="btn-primary inline-block mt-6">Back to Home</a>
                <?php endif; ?>
            <?php endif; ?>
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
