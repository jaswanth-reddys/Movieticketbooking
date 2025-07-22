SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


--
-- Create Database if not exists
--
CREATE DATABASE IF NOT EXISTS `movie_db` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `movie_db`;

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `roleID` int(11) NOT NULL AUTO_INCREMENT,
  `roleName` varchar(50) NOT NULL,
  `roleDescription` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`roleID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `admin_roles`
--
INSERT INTO `admin_roles` (`roleName`, `roleDescription`) VALUES
('Super Admin', 'Has access to all features and functionalities'),
('Theater Manager', 'Can manage theaters, halls, and schedules'),
('Content Manager', 'Can manage movies and content');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--
CREATE TABLE IF NOT EXISTS `admin_users` (
  `adminID` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `fullName` varchar(100) NOT NULL,
  `roleID` int(11) NOT NULL,
  `lastLogin` datetime DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `dateAdded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adminID`),
  UNIQUE KEY `username` (`username`),
  KEY `roleID` (`roleID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `admin_users`
--
-- Default password 'admin123' hashed with PASSWORD_DEFAULT or similar for $2y$10$
INSERT INTO `admin_users` (`username`, `password`, `email`, `fullName`, `roleID`, `status`) VALUES
('admin', '$2y$10$Zyw3AvNSq5hEu.b.bH136uI64VHcKfXOq9YHd6EDIb8RxzicTQLH2', 'admin@example.com', 'Super Admin', 1, 'active'),
('tadmin', '$2y$10$Zyw3AvNSq5hEu.b.bH136uI64VHcKfXOq9YHd6EDIb8RxzicTQLH2', 'tadmin@example.com', 'Theater Manager', 2, 'active'),
('cadmin', '$2y$10$Zyw3AvNSq5hEu.b.bH136uI64VHcKfXOq9YHd6EDIb8RxzicTQLH2', 'cadmin@example.com', 'Content Manager', 3, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `bookingtable`
--
CREATE TABLE IF NOT EXISTS `bookingtable` (
  `bookingID` int(11) NOT NULL AUTO_INCREMENT,
  `movieID` int(11) DEFAULT NULL,
  `scheduleID` int(11) DEFAULT NULL, 
  `hallID` int(11) DEFAULT NULL,     
  `bookingTheatre` varchar(100) NOT NULL,
  `bookingType` varchar(100) DEFAULT NULL,
  `bookingDate` varchar(50) NOT NULL,
  `bookingTime` varchar(50) NOT NULL,
  `bookingFName` varchar(100) NOT NULL,
  `bookingLName` varchar(100) DEFAULT NULL,
  `bookingPNumber` varchar(12) NOT NULL,
  `bookingEmail` varchar(255) NOT NULL,
  `ORDERID` varchar(255) NOT NULL,
  `seats` VARCHAR(255) NULL,         -- Added from updates.sql
  `amount` DECIMAL(10,2) NULL,       -- Added from updates.sql
  PRIMARY KEY (`bookingID`),
  UNIQUE KEY `bookingID` (`bookingID`),
  KEY `foreign_key_movieID` (`movieID`),
  KEY `foreign_key_ORDERID` (`ORDERID`),
  KEY `fk_bookingtable_scheduleID` (`scheduleID`), -- Index for new FK
  KEY `fk_bookingtable_hallID` (`hallID`)          -- Index for new FK
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `bookingtable`
--
-- Note: Existing data might not have scheduleID, hallID, seats, amount. They will be NULL by default.
INSERT INTO `bookingtable` (`bookingID`, `movieID`, `bookingTheatre`, `bookingType`, `bookingDate`, `bookingTime`, `bookingFName`, `bookingLName`, `bookingPNumber`, `bookingEmail`, `ORDERID`, `seats`, `amount`, `scheduleID`, `hallID`) VALUES
(38, 1, 'private-hall', '7d', '13-3', '15-00', 'Roshan', 'Bonde', '7448042514', 'robinbond2k18@gmail.com', 'ORD74294887', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feedbacktable`
--
CREATE TABLE IF NOT EXISTS `feedbacktable` (
  `msgID` int(12) NOT NULL AUTO_INCREMENT,
  `senderfName` varchar(50) NOT NULL,
  `senderlName` varchar(50) DEFAULT NULL,
  `sendereMail` varchar(100) NOT NULL,
  `senderfeedback` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`msgID`),
  UNIQUE KEY `msgID` (`msgID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `feedbacktable`
--
INSERT INTO `feedbacktable` (`msgID`, `senderfName`, `senderlName`, `sendereMail`, `senderfeedback`) VALUES
(1, 'Ahmed', 'Ali', 'Ahmed@mail.com', 'Hello first'),
(2, 'Ahmed', 'Ali', 'asa@as.com', 'asdas'),
(3, 'Roshan', 'Bonde', 'robinbond2k18@gmail.com', 'Very bad arrangement.\r\n\r\nWorsts I have ever seen.\r\n\r\nBastards!!!!!!!');

-- --------------------------------------------------------

--
-- Table structure for table `locations` (cities/regions)
--
CREATE TABLE IF NOT EXISTS `locations` (
  `locationID` int(11) NOT NULL AUTO_INCREMENT,
  `locationName` varchar(100) NOT NULL,
  `locationState` varchar(100) DEFAULT NULL,
  `locationCountry` varchar(100) DEFAULT 'India',
  `locationStatus` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`locationID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `locations`
--
INSERT INTO `locations` (`locationName`, `locationState`, `locationCountry`) VALUES
('Mumbai', 'Maharashtra', 'India'),
('Delhi', 'Delhi', 'India'),
('Bangalore', 'Karnataka', 'India'),
('Hyderabad', 'Telangana', 'India'),
('Chennai', 'Tamil Nadu', 'India');

-- --------------------------------------------------------

--
-- Table structure for table `movietable`
--
CREATE TABLE IF NOT EXISTS `movietable` (
  `movieID` int(11) NOT NULL AUTO_INCREMENT,
  `movieImg` varchar(150) NOT NULL,
  `movieTitle` varchar(100) NOT NULL,
  `movieGenre` varchar(50) NOT NULL,
  `movieDuration` int(11) NOT NULL,
  `movieRelDate` date NOT NULL,
  `movieDirector` varchar(50) NOT NULL,
  `movieActors` varchar(150) NOT NULL,
  `locationID` int(11) DEFAULT NULL, -- Added from admin_updates.sql
  `mainhall` int(11) NOT NULL,
  `viphall` int(11) NOT NULL,
  `privatehall` int(11) NOT NULL,
  PRIMARY KEY (`movieID`),
  UNIQUE KEY `movieID` (`movieID`),
  KEY `fk_movietable_locationID` (`locationID`) -- Index for new FK
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `movietable`
--
INSERT INTO `movietable` (`movieID`, `movieImg`, `movieTitle`, `movieGenre`, `movieDuration`, `movieRelDate`, `movieDirector`, `movieActors`, `mainhall`, `viphall`, `privatehall`, `locationID`) VALUES

(1, 'img/tollywood_posters/rrr.jpg', 'RRR', 'Action, Drama, Historical', 187, '2022-03-25', 'S. S. Rajamouli', 'N. T. Rama Rao Jr., Ram Charan, Alia Bhatt, Ajay Devgn', 0, 0, 0, NULL),
(2, 'img/tollywood_posters/baahubali2.jpg', 'Baahubali 2: The Conclusion', 'Action, Fantasy, War', 167, '2017-04-28', 'S. S. Rajamouli', 'Prabhas, Rana Daggubati, Anushka Shetty, Tamannaah', 0, 0, 0, NULL),
(3, 'img/tollywood_posters/pushpa.jpg', 'Pushpa: The Rise', 'Action, Crime, Drama', 179, '2021-12-17', 'Sukumar', 'Allu Arjun, Fahadh Faasil, Rashmika Mandanna', 0, 0, 0, NULL),
(4, 'img/tollywood_posters/sarkaruvaari.jpg', 'Sarkaru Vaari Paata', 'Action, Comedy, Romance', 159, '2022-05-12', 'Parasuram', 'Mahesh Babu, Keerthy Suresh', 0, 0, 0, NULL),
(5, 'img/tollywood_posters/rangasthalam.jpg', 'Rangasthalam', 'Action, Drama', 170, '2018-03-30', 'Sukumar', 'Ram Charan, Samantha Ruth Prabhu, Aadhi Pinisetty', 0, 0, 0, NULL),
(6, 'img/tollywood_posters/arjunreddy.jpg', 'Arjun Reddy', 'Romance, Drama', 186, '2017-08-25', 'Sandeep Reddy Vanga', 'Vijay Deverakonda, Shalini Pandey', 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `movie_schedules`
--
CREATE TABLE IF NOT EXISTS `movie_schedules` (
  `scheduleID` int(11) NOT NULL AUTO_INCREMENT,
  `movieID` int(11) NOT NULL,
  `hallID` int(11) NOT NULL,
  `showDate` date NOT NULL,
  `showTime` time NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `scheduleStatus` enum('active','cancelled','completed') DEFAULT 'active',
  PRIMARY KEY (`scheduleID`),
  KEY `movieID` (`movieID`),
  KEY `hallID` (`hallID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--
CREATE TABLE IF NOT EXISTS `payment` (
  `ORDERID` varchar(255) CHARACTER SET latin1 NOT NULL,
  `MID` varchar(255) NOT NULL,
  `TXNID` varchar(255) NOT NULL,
  `TXNAMOUNT` varchar(255) NOT NULL,
  `PAYMENTMODE` varchar(255) NOT NULL,
  `CURRENCY` varchar(255) NOT NULL,
  `TXNDATE` varchar(255) NOT NULL,
  `STATUS` varchar(255) NOT NULL,
  `RESPCODE` varchar(255) NOT NULL,
  `RESPMSG` varchar(255) NOT NULL,
  `GATEWAYNAME` varchar(255) NOT NULL,
  `BANKTXNID` varchar(255) NOT NULL,
  `BANKNAME` varchar(255) NOT NULL,
  `CHECKSUMHASH` varchar(255) NOT NULL,
  PRIMARY KEY (`ORDERID`) -- IMPORTANT: ORDERID must be a PRIMARY KEY to be referenced
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payment`
--
INSERT INTO `payment` (`ORDERID`, `MID`, `TXNID`, `TXNAMOUNT`, `PAYMENTMODE`, `CURRENCY`, `TXNDATE`, `STATUS`, `RESPCODE`, `RESPMSG`, `GATEWAYNAME`, `BANKTXNID`, `BANKNAME`, `CHECKSUMHASH`) VALUES
('ORD74294887', 'BvuNYX16485310423471', '20201122111212800110168967602099353', '900.00', 'NB', 'INR', '2020-11-22 22:52:27.0', 'TXN_SUCCESS', '01', 'Txn Success', 'AXIS', '12097079902', 'AXIS', 'mzBIZdaiA+Gfw9Yluagan+n8wM4O8JI/WdKryykKMQYtCA/ZBa5J1recZP6o6XL5j735yb8e+VxPdBNZE/GUXf9RWJDawzsOZ76syjMkjM=');

-- --------------------------------------------------------

--
-- Table structure for table `seats`
--
CREATE TABLE IF NOT EXISTS `seats` (
  `seatID` int(11) NOT NULL AUTO_INCREMENT,
  `hallID` int(11) NOT NULL,
  `seatNumber` varchar(10) NOT NULL,
  `seatRow` varchar(5) NOT NULL,
  `seatStatus` enum('available','booked','maintenance') DEFAULT 'available',
  PRIMARY KEY (`seatID`),
  KEY `hallID` (`hallID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `theaters`
--
CREATE TABLE IF NOT EXISTS `theaters` (
  `theaterID` int(11) NOT NULL AUTO_INCREMENT,
  `theaterName` varchar(100) NOT NULL,
  `theaterAddress` varchar(255) NOT NULL,
  `theaterCity` varchar(100) NOT NULL,
  `theaterState` varchar(100) DEFAULT NULL,
  `theaterZipcode` varchar(20) DEFAULT NULL,
  `theaterPhone` varchar(20) DEFAULT NULL,
  `theaterEmail` varchar(100) DEFAULT NULL,
  `theaterStatus` enum('active','inactive') DEFAULT 'active',
  `dateAdded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`theaterID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `theaters`
--
INSERT INTO `theaters` (`theaterName`, `theaterAddress`, `theaterCity`, `theaterState`, `theaterZipcode`, `theaterPhone`, `theaterEmail`) VALUES
('PVR Cinemas', '123 Main Street', 'Mumbai', 'Maharashtra', '400001', '9876543210', 'pvr.mumbai@example.com'),
('INOX Movies', '456 Park Avenue', 'Delhi', 'Delhi', '110001', '9876543211', 'inox.delhi@example.com'),
('Cinepolis', '789 Theater Road', 'Bangalore', 'Karnataka', '560001', '9876543212', 'cinepolis.bangalore@example.com');

-- --------------------------------------------------------

--
-- Table structure for table `theater_halls`
--
CREATE TABLE IF NOT EXISTS `theater_halls` (
  `hallID` int(11) NOT NULL AUTO_INCREMENT,
  `theaterID` int(11) NOT NULL,
  `hallName` varchar(100) NOT NULL,
  `hallType` enum('main-hall','vip-hall','private-hall') NOT NULL,
  `totalSeats` int(11) NOT NULL DEFAULT 100,
  `hallStatus` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`hallID`),
  KEY `theaterID` (`theaterID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



--
-- Table structure for table `users`
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `name` varchar(80) NOT NULL,
  `password` varchar(80) NOT NULL,
  `phone` VARCHAR(20) NULL, -- Added from updates.sql
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--
INSERT INTO `users` (`id`, `username`, `name`, `password`, `phone`) VALUES
(1, '123', 'Aman', '123', NULL);


--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD CONSTRAINT `fk_admin_users_roleID` FOREIGN KEY (`roleID`) REFERENCES `admin_roles` (`roleID`);

--
-- Constraints for table `bookingtable`
--
ALTER TABLE `bookingtable`
  ADD CONSTRAINT `foreign_key_movieID` FOREIGN KEY (`movieID`) REFERENCES `movietable` (`movieID`),
  ADD CONSTRAINT `foreign_key_ORDERID` FOREIGN KEY (`ORDERID`) REFERENCES `payment` (`ORDERID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookingtable_hallID` FOREIGN KEY (`hallID`) REFERENCES `theater_halls` (`hallID`),
  ADD CONSTRAINT `fk_bookingtable_scheduleID` FOREIGN KEY (`scheduleID`) REFERENCES `movie_schedules` (`scheduleID`);

--
-- Constraints for table `movietable`
--
ALTER TABLE `movietable`
  ADD CONSTRAINT `fk_movietable_locationID` FOREIGN KEY (`locationID`) REFERENCES `locations` (`locationID`);

--
-- Constraints for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD CONSTRAINT `fk_schedules_hallID` FOREIGN KEY (`hallID`) REFERENCES `theater_halls` (`hallID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedules_movieID` FOREIGN KEY (`movieID`) REFERENCES `movietable` (`movieID`) ON DELETE CASCADE;

--
-- Constraints for table `seats`
--
ALTER TABLE `seats`
  ADD CONSTRAINT `fk_seats_hallID` FOREIGN KEY (`hallID`) REFERENCES `theater_halls` (`hallID`) ON DELETE CASCADE;

--
-- Constraints for table `theater_halls`
--
ALTER TABLE `theater_halls`
  ADD CONSTRAINT `fk_theater_halls_theaterID` FOREIGN KEY (`theaterID`) REFERENCES `theaters` (`theaterID`) ON DELETE CASCADE;

ALTER TABLE `theaters` ADD `theaterPanoramaImg` VARCHAR(255) NULL AFTER `theaterEmail`;

ALTER TABLE `theaters`
ADD COLUMN `locationID` INT(11) NULL AFTER `theaterStatus`,
ADD CONSTRAINT `fk_theaters_locationID` FOREIGN KEY (`locationID`) REFERENCES `locations` (`locationID`) ON DELETE SET NULL ON UPDATE CASCADE;


ALTER TABLE `theater_halls` ADD `hallPanoramaImg` VARCHAR(255) NULL AFTER `totalSeats`;

--
-- Dumping data for table `theater_halls`
--
INSERT INTO `theater_halls` (`hallID`, `theaterID`, `hallName`, `hallType`, `totalSeats`, `hallPanoramaImg`, `hallStatus`) VALUES
(1, 1, 'Hall 1', 'main-hall', 120, 'img/panoramas/pvr_lobby.jpg', 'active'),
(2, 1, 'Hall 2', 'vip-hall', 100, 'img/panoramas/pvr_lobby.jpg', 'active'),
(3, 1, 'Hall 3', 'private-hall', 80, 'img/panoramas/pvr_lobby.jpg', 'active'),
(4, 2, 'Hall 1', 'main-hall', 150, 'img/panoramas/inox_entrance.jpg', 'active'),
(5, 2, 'Hall 2', 'vip-hall', 100, 'img/panoramas/inox_entrance.jpg', 'active'),
(6, 3, 'Hall 1', 'main-hall', 200, 'img/panoramas/cinepolis_exterior.jpg', 'active');

-- --------------------------------------------------------

-- Update PVR Cinemas with a panorama image
UPDATE `theaters`
SET `theaterPanoramaImg` = 'img/panoramas/pvr_lobby.jpg' -- Replace with your actual image path
WHERE `theaterID` = 1; -- Assuming PVR Cinemas is theaterID 1

-- Update INOX Movies
UPDATE `theaters`
SET `theaterPanoramaImg` = 'img/panoramas/inox_entrance.jpg' -- Replace with your actual image path
WHERE `theaterID` = 2; -- Assuming INOX Movies is theaterID 2

-- Update Cinepolis
UPDATE `theaters`
SET `theaterPanoramaImg` = 'img/panoramas/cinepolis_exterior.jpg' -- Replace with your actual image path
WHERE `theaterID` = 3; -- Assuming Cinepolis is theaterID 3




COMMIT;
