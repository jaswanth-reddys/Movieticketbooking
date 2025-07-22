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

$panoramaSource = null;
$displayName = "Panorama View"; // Default display name
$errorMessage = '';
$sourceType = 'none'; // 'theater' or 'hall' or 'error'

// Check if a specific HALL panorama image is provided via GET (from movie_details.php)
if (isset($_GET['hall_panorama_img']) && !empty($_GET['hall_panorama_img'])) {
    $hallPanoramaImgPath = $_GET['hall_panorama_img'];
    $passedHallName = htmlspecialchars($_GET['hall_name'] ?? 'Hall');
    $passedTheaterName = htmlspecialchars($_GET['theater_name'] ?? 'Theater');

    // Basic validation of the path (ensure it starts with 'img/')
    if (strpos($hallPanoramaImgPath, 'img/') === 0) {
        $panoramaSource = '../' . $hallPanoramaImgPath; // Path from user/ to project root, then img/
        $displayName = $passedTheaterName . ' - ' . $passedHallName;
        $sourceType = 'hall';
    } else {
        $errorMessage = "Invalid hall panorama image path provided in URL.";
        $sourceType = 'error';
    }

} elseif (isset($_GET['theater_id']) && is_numeric($_GET['theater_id'])) {
    // If only theater_id is provided (from index.php)
    $theaterId = $_GET['theater_id'];

    // Fetch theater details including the main theater panorama image path
    $query = "SELECT theatername, theaterpanoramaimg FROM theaters WHERE theaterid = $1";
    $result = pg_query_params($conn, $query, array($theaterId));
    
    if ($result) {
        if (pg_num_rows($result) > 0) {
            $theaterData = pg_fetch_assoc($result);
            // PostgreSQL column names are case-sensitive if double-quoted, otherwise lowercase.
            // Using lowercase column names to match the database schema.
            $displayName = $theaterData['theatername'];
            if (!empty($theaterData['theaterpanoramaimg'])) {
                // Basic validation of the path (ensure it starts with 'img/')
                if (strpos($theaterData['theaterpanoramaimg'], 'img/') === 0) {
                    $panoramaSource = '../' . htmlspecialchars($theaterData['theaterpanoramaimg']);
                    $sourceType = 'theater';
                } else {
                    $errorMessage = "Invalid theater panorama image path stored in database.";
                    $sourceType = 'error';
                }
            } else {
                $errorMessage = "No panorama image explicitly set for " . htmlspecialchars($theaterData['theatername']) . ".";
                $sourceType = 'none'; // No panorama data
            }
        } else {
            $errorMessage = "Theater not found with ID: " . $theaterId . ".";
            $sourceType = 'error';
        }
    } else {
        $errorMessage = "Database query error for theater details: " . pg_last_error($conn);
        $sourceType = 'error';
    }
} else {
    $errorMessage = "No specific theater or hall ID provided to view.";
    $sourceType = 'error';
}

// Close PostgreSQL connection
pg_close($conn);

// Final check if a valid panorama source was determined.
// If sourceType is 'error' or 'none', ensure $hasValidPanoramaSource is false.
$hasValidPanoramaSource = ($sourceType !== 'error' && $sourceType !== 'none' && !empty($panoramaSource));

// If not valid, ensure a placeholder is used for the script even if not displayed
if (!$hasValidPanoramaSource) {
    $panoramaSource = "https://placehold.co/1920x1080/0f3460/e0e0e0?text=No+Panorama+Available";
    if (empty($errorMessage)) { // Generic message if no specific error yet
        $errorMessage = "Panorama image could not be loaded or is not available. Check database path and file existence.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View <?php echo htmlspecialchars($displayName); ?> - Showtime Select</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Panolens.js and Three.js -->
    <!-- It's crucial these are loaded correctly -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/105/three.min.js"></script>
    <script src="../js/panolens.min.js"></script> 
    
    <!-- Custom CSS -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            margin: 0;
            overflow: hidden; /* Hide scrollbars for 360 viewer */
            font-family: 'Inter', sans-serif;
            background-color: #1a1a2e; /* Darker background */
            color: #e0e0e0; /* Light text */
        }
        .header-bg {
            background-color: #16213e; /* Slightly lighter dark for header */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: absolute;
            width: 100%;
            z-index: 1000; /* Ensure header is on top */
            top: 0;
            left: 0;
            padding: 1rem 0;
        }
        .logo-text {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
        }
        .main-container {
            position: fixed; /* Fixed to viewport */
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #111;
        }
        .image-container {
            width: 100%;
            height: 100%;
            display: block; /* Ensure it takes full space */
        }
        /* Panolens canvas styling */
        .panolens-canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
        .overlay-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.7);
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            z-index: 999;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6);
        }
        .btn-back {
            background-color: #e94560;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            text-align: center;
            margin-top: 1rem;
            display: inline-block;
        }
        .btn-back:hover {
            background-color: #b82e4a;
        }
        /* Style for the current view title */
        .view-title-overlay {
            position: absolute;
            top: 50px; /* Below the header */
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.6);
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            z-index: 900; /* Below header, above canvas */
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header-bg">
        <div class="container mx-auto flex justify-between items-center px-4">
            <a href="index.php" class="text-2xl font-bold logo-text">Showtime Select</a>
            <nav>
                <a href="javascript:history.back()" class="btn-back">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </nav>
        </div>
    </header>

    <div class="main-container">
        <?php if (!$hasValidPanoramaSource): ?>
            <div class="overlay-content">
                <h1 class="text-3xl font-bold text-red-400 mb-4">Error or Panorama Not Found</h1>
                <p class="text-lg text-gray-300"><?php echo htmlspecialchars($errorMessage); ?></p>
                <p class="text-md text-gray-400 mt-2">
                    Please ensure:
                    <ul>
                        <li>The `theaterPanoramaImg` or `hallPanoramaImg` column is populated in your database.</li>
                        <li>The image file exists at the specified path (`../img/panoramas/your_image.jpg`).</li>
                        <li>Your `img/panoramas/` folder has correct read permissions for the web server.</li>
                    </ul>
                </p>
                <a href="javascript:history.back()" class="btn-back">Go Back</a>
            </div>
        <?php else: ?>
            <div class="image-container" id="image-container">
                <!-- Panolens.js viewer will be initialized here -->
            </div>
            <!-- Title overlay for the panorama -->
            <h1 class="view-title-overlay">
                Viewing: <?php echo htmlspecialchars($displayName); ?>
            </h1>
        <?php endif; ?>
    </div>

    <?php if ($hasValidPanoramaSource): ?>
    <script>
        // Use DOMContentLoaded to ensure HTML elements are ready
        document.addEventListener('DOMContentLoaded', function() {
            const panoramaImageSrc = "<?php echo $panoramaSource; ?>";
            const imageContainer = document.getElementById("image-container");

            // Check if imageContainer exists before initializing Panolens
            if (!imageContainer) {
                console.error("Error: Panolens image container (id='image-container') not found in the DOM.");
                // This shouldn't happen if $hasValidPanoramaSource is true, as the div is rendered.
                return;
            }

            try {
                const panoramaImage = new PANOLENS.ImagePanorama(panoramaImageSrc);
                
                const viewer = new PANOLENS.Viewer({
                    container: imageContainer,
                    autoRotate: true,
                    autoRotateSpeed: 0.1,
                    controlBar: true, // Show Panolens control bar
                    output: 'console' // Set to 'console' for debugging, 'none' for production
                });

                viewer.add(panoramaImage);

                // Ensure responsive behavior
                window.addEventListener('resize', function() {
                    if (viewer && viewer.renderer && viewer.camera) {
                        const width = imageContainer.clientWidth;
                        const height = imageContainer.clientHeight;
                        viewer.renderer.setSize(width, height);
                        viewer.camera.aspect = width / height;
                        viewer.camera.updateProjectionMatrix();
                    }
                });

                // Handle Panolens internal image load errors (different from network 404s)
                panoramaImage.addEventListener('error', function(event) {
                    console.error("Panolens Image Load Error (internal):", event);
                    imageContainer.innerHTML = '<div class="overlay-content"><h1 class="text-3xl font-bold text-red-400 mb-4">Image Load Error</h1><p class="text-lg text-gray-300">Could not load the panorama image. The file might be corrupted or not a valid 360 panorama.</p><a href="javascript:history.back()" class="btn-back">Go Back</a></div>';
                    if (viewer.widget) { viewer.widget.barElement.style.display = 'none'; }
                    if (viewer.OrbitControls) { viewer.OrbitControls.enabled = false; }
                    if (viewer.DeviceOrientationControls) { viewer.DeviceOrientationControls.enabled = false; }
                });

            } catch (e) {
                console.error("Panolens Initialization Error (JavaScript catch block):", e);
                // This catch block handles errors during the Panolens library initialization itself
                imageContainer.innerHTML = '<div class="overlay-content"><h1 class="text-3xl font-bold text-red-400 mb-4">Initialization Error</h1><p class="text-lg text-gray-300">Could not initialize the panorama viewer. Ensure Panolens.js and Three.js are correctly loaded and compatible.</p><a href="javascript:history.back()" class="btn-back">Go Back</a></div>';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
