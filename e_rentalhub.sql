-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2025 at 10:37 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `e_rentalhub`
--

-- --------------------------------------------------------

--
-- Table structure for table `landlords`
--

CREATE TABLE `landlords` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `alt_phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `about_me` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `landlords`
--

INSERT INTO `landlords` (`id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `alt_phone`, `address`, `city`, `county`, `postal_code`, `about_me`, `profile_picture`, `business_name`, `tax_id`, `registration_number`, `created_at`) VALUES
(3, 2, 'ABEL', 'AJUKU', 'testlandlord@gmail.com', '0794362939', '0794362939', 'H76C+8HR, Amakura', 'Amukura', 'Kisii', '50403', 'I am A Certified landlord, and trusted', NULL, '', '', '', '2025-11-18 15:05:23'),
(4, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-18 15:50:41');

-- --------------------------------------------------------

--
-- Table structure for table `landlord_notifications`
--

CREATE TABLE `landlord_notifications` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `email_new_bookings` tinyint(1) DEFAULT 0,
  `email_new_messages` tinyint(1) DEFAULT 0,
  `sms_booking_confirmations` tinyint(1) DEFAULT 0,
  `sms_payment_updates` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `landlord_notifications`
--

INSERT INTO `landlord_notifications` (`id`, `landlord_id`, `email_new_bookings`, `email_new_messages`, `sms_booking_confirmations`, `sms_payment_updates`) VALUES
(25, 3, 0, 0, 0, 0),
(26, 4, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `landlord_payments`
--

CREATE TABLE `landlord_payments` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `payment_method` enum('Bank Transfer','M-Pesa','PayPal','Cash') DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `account_number` varchar(150) DEFAULT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `mpesa_number` varchar(20) DEFAULT NULL,
  `paypal_email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `rent` decimal(10,2) NOT NULL,
  `type` varchar(50) NOT NULL,
  `bedrooms` int(11) NOT NULL,
  `area` int(11) DEFAULT NULL,
  `status` enum('Available','Reserved','Unavailable') DEFAULT 'Available',
  `description` text DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `landlord_id`, `title`, `address`, `city`, `rent`, `type`, `bedrooms`, `area`, `status`, `description`, `amenities`, `created_at`) VALUES
(1, 3, 'Student room nears campus', 'H76C+8HR, Kisii', 'Kisii', 5600.00, 'Apartment', 2, 1300, 'Available', 'ideal for short distance', 'WiFi, Kitchen, Study Desk, Water Included, Public Transport, Furnished, Electricity Included, Security, Close to Campus', '2025-11-09 20:58:00'),
(2, 3, 'porujssnna', 'H76C+8HR, Kisii', 'Kisii', 6700.00, 'Sstudio', 5, 1300, 'Available', 'kdshdfkfjjrjasnnshjh', 'WiFi, Kitchen, Water Included, Public Transport, Furnished, Shared Kitchen, Quiet Area, AC', '2025-11-11 19:00:52'),
(3, 3, 'A secure apartment', 'H76C+8HR, Kisii', 'Kisii Town', 6700.00, 'apartment', 3, 1300, 'Available', 'Ideal for two or more students who are staying together', 'WiFi, Kitchen, Water Included, Public Transport, Parking, Furnished, Electricity Included, Security', '2025-11-11 21:44:57'),
(4, 3, 'A Spacious Room Near Campus', 'H76C+8HR, Kisii', 'Kisii Town', 7500.00, 'apartment', 2, 1300, 'Available', 'A spacious room ideal for students staying in groups or individuals staying more than three', 'WiFi, Kitchen, Water Included, Parking, Furnished, Electricity Included, Security, Close to Campus', '2025-11-17 06:39:27'),
(5, 3, 'Student Room next to Kisumu Ndogo', 'H76C+8HR, Kisii', 'Kisii Campus', 4500.00, 'single_room', 1, 500, 'Available', 'A single room with enough space', 'WiFi, Study Desk, Water Included, Electricity Included, Close to Campus', '2025-11-17 08:25:47'),
(6, 3, 'student room near campus', 'H76C+8HR, Kisii', 'Kisii', 3500.00, 'single_room', 0, 480, 'Available', 'spacious house suitable for students', 'WiFi, Study Desk, Water Included, Electricity Included', '2025-11-17 08:31:53');

-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

CREATE TABLE `property_images` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`id`, `property_id`, `image_path`, `uploaded_at`) VALUES
(1, 2, '1762887652_691387e49b073_pexels-michaelgaultphotos-10450052.jpg', '2025-11-11 19:00:52'),
(2, 3, '1762897497_6913ae59825e0_pexels-dropshado-2251247.jpg', '2025-11-11 21:44:57'),
(3, 4, '1763361567_691ac31f8852e_images.7.jpg', '2025-11-17 06:39:27'),
(4, 5, '1763367947_691adc0b033c1_pexels-allen-boguslavsky-1344061-34365793.jpg', '2025-11-17 08:25:47'),
(5, 6, '1763368313_691add7925ce3_pexels-huy-phan-316220-2826787.jpg', '2025-11-17 08:31:53');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `lease_length` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `student_id`, `property_id`, `landlord_id`, `check_in_date`, `lease_length`, `amount`, `status`, `created_at`, `updated_at`) VALUES
(5, 1, 6, 3, '2025-11-24', 1, 0.00, 'pending', '2025-11-24 16:39:22', '2025-11-24 16:39:22'),
(6, 1, 4, 3, '2025-11-27', 1, 0.00, 'confirmed', '2025-11-25 11:03:34', '2025-11-28 07:03:28');

-- --------------------------------------------------------

--
-- Table structure for table `saved_properties`
--

CREATE TABLE `saved_properties` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_properties`
--

INSERT INTO `saved_properties` (`id`, `student_id`, `property_id`, `created_at`) VALUES
(26, 1, 5, '2025-11-26 15:37:05'),
(27, 1, 6, '2025-11-27 10:22:40'),
(28, 1, 2, '2025-11-28 07:01:24'),
(29, 1, 3, '2025-11-28 07:01:27');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `student_identifier` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `university` varchar(150) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `year_of_study` varchar(50) DEFAULT NULL,
  `current_address` varchar(255) DEFAULT NULL,
  `emergency_name` varchar(150) DEFAULT NULL,
  `emergency_phone` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `full_name`, `student_identifier`, `email`, `phone`, `bio`, `university`, `course`, `year_of_study`, `current_address`, `emergency_name`, `emergency_phone`, `avatar`, `created_at`, `updated_at`) VALUES
(1, 1, 'abel emuduki', '786907', 'teststudent@gmail.com', '0794362939', 'i am a student', 'kisii university', 'soen', '2025', 'H76C+8HR, Amakura', '0794262939', '0794262939', NULL, '2025-11-27 09:26:54', '2025-11-27 10:09:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','landlord','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'student', 'teststudent@gmail.com', '$2y$10$oszioSqLofdTsyFqIVtvEe9IUd14vZC4vGpzfx9MUkDohIaUd/.Xe', 'student', '2025-11-09 17:28:56'),
(2, 'landlord', 'testlandlord@gmail.com', '$2y$10$aQEXxptdqjSADIC2tKbLauQ0CKMyRBsgRRjQCrVyw2Il72K6s..bC', 'landlord', '2025-11-09 18:47:02'),
(3, 'admin', 'testadmin@gmail.com', '$2y$10$vkjZ0bmCwP7kI07uuX7h0.bmgxg8TrSOL7zDK2QP20UdTOnAC1Fha', 'admin', '2025-11-17 08:40:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `landlords`
--
ALTER TABLE `landlords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `landlord_notifications`
--
ALTER TABLE `landlord_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `landlord_id` (`landlord_id`);

--
-- Indexes for table `landlord_payments`
--
ALTER TABLE `landlord_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `landlord_id` (`landlord_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_property_landlord` (`landlord_id`);

--
-- Indexes for table `property_images`
--
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reservation_tenant` (`student_id`),
  ADD KEY `fk_reservation_landlord` (`landlord_id`),
  ADD KEY `fk_reservation_property` (`property_id`);

--
-- Indexes for table `saved_properties`
--
ALTER TABLE `saved_properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_save` (`student_id`,`property_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_students_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `landlords`
--
ALTER TABLE `landlords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `landlord_notifications`
--
ALTER TABLE `landlord_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `landlord_payments`
--
ALTER TABLE `landlord_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `saved_properties`
--
ALTER TABLE `saved_properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `landlords`
--
ALTER TABLE `landlords`
  ADD CONSTRAINT `fk_landlord_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `landlord_notifications`
--
ALTER TABLE `landlord_notifications`
  ADD CONSTRAINT `landlord_notifications_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `landlords` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `landlord_payments`
--
ALTER TABLE `landlord_payments`
  ADD CONSTRAINT `landlord_payments_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `landlords` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_property_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `landlords` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `property_images`
--
ALTER TABLE `property_images`
  ADD CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservation_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `landlords` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reservation_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reservation_tenant` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
