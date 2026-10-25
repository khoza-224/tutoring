-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 08, 2026 at 07:04 AM
-- Server version: 8.0.46
-- PHP Version: 8.2.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tutoring_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

CREATE TABLE `administrators` (
  `admin_id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`admin_id`, `full_name`, `email`, `password_hash`, `created_at`) VALUES
(1, 'Nomsa Khumalo', 'admin@tutoring.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(2, 'Peter van Wyk', 'admin2@tutoring.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int UNSIGNED NOT NULL,
  `enrolment_id` int UNSIGNED NOT NULL,
  `tutor_id` int UNSIGNED NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('requested','confirmed','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `enrolment_id`, `tutor_id`, `session_date`, `start_time`, `end_time`, `status`, `created_at`) VALUES
(1, 1, 1, '2026-09-28', '10:00:00', '11:00:00', 'completed', '2026-10-07 09:11:07'),
(2, 2, 2, '2026-09-30', '14:00:00', '15:00:00', 'completed', '2026-10-07 09:11:07'),
(3, 1, 1, '2026-10-07', '10:00:00', '11:00:00', 'confirmed', '2026-10-07 09:11:07'),
(4, 3, 3, '2026-10-08', '15:00:00', '16:00:00', 'confirmed', '2026-10-07 09:11:07'),
(5, 4, 3, '2026-10-09', '09:00:00', '10:00:00', 'requested', '2026-10-07 09:11:07'),
(6, 2, 2, '2026-10-12', '14:00:00', '15:00:00', 'cancelled', '2026-10-07 09:11:07');

-- --------------------------------------------------------

--
-- Table structure for table `enrolments`
--

CREATE TABLE `enrolments` (
  `enrolment_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `programme_id` int UNSIGNED NOT NULL,
  `payment_id` int UNSIGNED NOT NULL,
  `enrolment_date` date NOT NULL,
  `status` enum('active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrolments`
--

INSERT INTO `enrolments` (`enrolment_id`, `student_id`, `programme_id`, `payment_id`, `enrolment_date`, `status`) VALUES
(1, 1, 1, 1, '2026-09-20', 'active'),
(2, 2, 2, 2, '2026-09-21', 'active'),
(3, 3, 1, 3, '2026-09-22', 'active'),
(4, 1, 2, 5, '2026-09-25', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `recipient_role` enum('student','tutor','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `booking_id`, `recipient_role`, `recipient_email`, `message`, `sent_at`) VALUES
(1, 3, 'student', 'ayanda.zulu@student.test', 'Your Mathematics session on 2026-10-07 at 10:00 is confirmed.', '2026-10-02 09:00:00'),
(2, 3, 'tutor', 'thabo.mokoena@tutoring.test', 'New session assigned: 2026-10-07 at 10:00 (Ayanda Zulu).', '2026-10-02 09:00:00'),
(3, 3, 'admin', 'admin@tutoring.test', 'Booking #3 confirmed: Mathematics, 2026-10-07 at 10:00.', '2026-10-02 09:00:00'),
(4, 4, 'student', 'naledi.sithole@student.test', 'Your Mathematics session on 2026-10-08 at 15:00 is confirmed.', '2026-10-03 12:30:00'),
(5, 4, 'tutor', 'sipho.nkosi@tutoring.test', 'New session assigned: 2026-10-08 at 15:00 (Naledi Sithole).', '2026-10-03 12:30:00'),
(6, 4, 'admin', 'admin@tutoring.test', 'Booking #4 confirmed: Mathematics, 2026-10-08 at 15:00.', '2026-10-03 12:30:00'),
(7, 6, 'student', 'kagiso.molefe@student.test', 'Your Data Science session on 2026-10-12 has been cancelled.', '2026-10-04 08:20:00'),
(8, 6, 'tutor', 'lerato.dlamini@tutoring.test', 'Session on 2026-10-12 at 14:00 (Kagiso Molefe) was cancelled.', '2026-10-04 08:20:00'),
(9, 6, 'admin', 'admin@tutoring.test', 'Booking #6 was cancelled by the student.', '2026-10-04 08:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `programme_id` int UNSIGNED NOT NULL,
  `verified_by` int UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` enum('pending','completed','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reference_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_id`, `programme_id`, `verified_by`, `amount`, `payment_method`, `payment_status`, `reference_no`, `paid_at`) VALUES
(1, 1, 1, 1, 1500.00, 'Card', 'completed', 'PAY-2026-0001', '2026-09-20 09:15:00'),
(2, 2, 2, 1, 2200.00, 'EFT', 'completed', 'PAY-2026-0002', '2026-09-21 11:40:00'),
(3, 3, 1, 2, 1500.00, 'Card', 'completed', 'PAY-2026-0003', '2026-09-22 14:05:00'),
(4, 4, 2, NULL, 2200.00, 'EFT', 'pending', 'PAY-2026-0004', '2026-10-02 16:30:00'),
(5, 1, 2, 1, 2200.00, 'Card', 'completed', 'PAY-2026-0005', '2026-09-25 10:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `programmes`
--

CREATE TABLE `programmes` (
  `programme_id` int UNSIGNED NOT NULL,
  `programme_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `fee` decimal(10,2) NOT NULL
) ;

--
-- Dumping data for table `programmes`
--

INSERT INTO `programmes` (`programme_id`, `programme_name`, `description`, `fee`) VALUES
(1, 'Mathematics', 'Algebra, calculus, statistics and problem-solving support.', 1500.00),
(2, 'Data Science', 'Python, data analysis, visualisation and machine learning basics.', 2200.00);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int UNSIGNED NOT NULL,
  `first_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `created_at`) VALUES
(1, 'Ayanda', 'Zulu', 'ayanda.zulu@student.test', '0711234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(2, 'Kagiso', 'Molefe', 'kagiso.molefe@student.test', '0722345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(3, 'Naledi', 'Sithole', 'naledi.sithole@student.test', '0733456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(4, 'Themba', 'Ndlovu', 'themba.ndlovu@student.test', '0744567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(5, 'Zanele', 'Mahlangu', 'zanele.mahlangu@student.test', '0755678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(6, 'sanele', 'khoza', 'samkelosanele738@gmail.com', '0789518537', '$2y$10$f6KmkYEV2Z8BIqzZFdnRn.LNRea4BjiS6JtL8xgQwBVKXC4gjk13C', '2026-10-07 09:50:03');

-- --------------------------------------------------------

--
-- Table structure for table `tutors`
--

CREATE TABLE `tutors` (
  `tutor_id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tutors`
--

INSERT INTO `tutors` (`tutor_id`, `full_name`, `email`, `phone`, `password_hash`, `created_at`) VALUES
(1, 'Thabo Mokoena', 'thabo.mokoena@tutoring.test', '0811112222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(2, 'Lerato Dlamini', 'lerato.dlamini@tutoring.test', '0822223333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07'),
(3, 'Sipho Nkosi', 'sipho.nkosi@tutoring.test', '0833334444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-10-07 09:11:07');

-- --------------------------------------------------------

--
-- Table structure for table `tutor_availability`
--

CREATE TABLE `tutor_availability` (
  `availability_id` int UNSIGNED NOT NULL,
  `tutor_id` int UNSIGNED NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ;

--
-- Dumping data for table `tutor_availability`
--

INSERT INTO `tutor_availability` (`availability_id`, `tutor_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(1, 1, 'Monday', '09:00:00', '13:00:00'),
(2, 1, 'Wednesday', '09:00:00', '13:00:00'),
(3, 2, 'Monday', '13:00:00', '17:00:00'),
(4, 2, 'Tuesday', '13:00:00', '17:00:00'),
(5, 2, 'Wednesday', '13:00:00', '17:00:00'),
(6, 3, 'Thursday', '09:00:00', '17:00:00'),
(7, 3, 'Friday', '09:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `tutor_programmes`
--

CREATE TABLE `tutor_programmes` (
  `tutor_id` int UNSIGNED NOT NULL,
  `programme_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tutor_programmes`
--

INSERT INTO `tutor_programmes` (`tutor_id`, `programme_id`) VALUES
(1, 1),
(3, 1),
(2, 2),
(3, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `uq_admin_email` (`email`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD UNIQUE KEY `uq_tutor_session` (`tutor_id`,`session_date`,`start_time`),
  ADD KEY `fk_book_enrolment` (`enrolment_id`);

--
-- Indexes for table `enrolments`
--
ALTER TABLE `enrolments`
  ADD PRIMARY KEY (`enrolment_id`),
  ADD UNIQUE KEY `uq_enrolment_payment` (`payment_id`),
  ADD UNIQUE KEY `uq_student_programme` (`student_id`,`programme_id`),
  ADD KEY `fk_enrol_programme` (`programme_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notif_booking` (`booking_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `uq_payment_reference` (`reference_no`),
  ADD KEY `fk_pay_student` (`student_id`),
  ADD KEY `fk_pay_programme` (`programme_id`),
  ADD KEY `fk_pay_admin` (`verified_by`);

--
-- Indexes for table `programmes`
--
ALTER TABLE `programmes`
  ADD PRIMARY KEY (`programme_id`),
  ADD UNIQUE KEY `uq_programme_name` (`programme_name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `uq_student_email` (`email`);

--
-- Indexes for table `tutors`
--
ALTER TABLE `tutors`
  ADD PRIMARY KEY (`tutor_id`),
  ADD UNIQUE KEY `uq_tutor_email` (`email`);

--
-- Indexes for table `tutor_availability`
--
ALTER TABLE `tutor_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD UNIQUE KEY `uq_tutor_slot` (`tutor_id`,`day_of_week`,`start_time`);

--
-- Indexes for table `tutor_programmes`
--
ALTER TABLE `tutor_programmes`
  ADD PRIMARY KEY (`tutor_id`,`programme_id`),
  ADD KEY `fk_tp_programme` (`programme_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `admin_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrolments`
--
ALTER TABLE `enrolments`
  MODIFY `enrolment_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programmes`
--
ALTER TABLE `programmes`
  MODIFY `programme_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tutors`
--
ALTER TABLE `tutors`
  MODIFY `tutor_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tutor_availability`
--
ALTER TABLE `tutor_availability`
  MODIFY `availability_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_book_enrolment` FOREIGN KEY (`enrolment_id`) REFERENCES `enrolments` (`enrolment_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`tutor_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `enrolments`
--
ALTER TABLE `enrolments`
  ADD CONSTRAINT `fk_enrol_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrol_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`programme_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrol_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_admin` FOREIGN KEY (`verified_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pay_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`programme_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pay_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `tutor_availability`
--
ALTER TABLE `tutor_availability`
  ADD CONSTRAINT `fk_avail_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`tutor_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tutor_programmes`
--
ALTER TABLE `tutor_programmes`
  ADD CONSTRAINT `fk_tp_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`programme_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tp_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`tutor_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
