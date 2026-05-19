-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2026 at 04:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ccs_sitin_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author` varchar(100) DEFAULT 'CCS Admin',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `author`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Welcome to CCS Sit-in System', 'We are excited to announce the launch of our new sit-in monitoring system! 🎉', 'CCS Admin', 1, '2026-04-09 16:57:42', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`id`, `user_id`, `announcement_id`, `read_at`) VALUES
(1, 2, 1, '2026-05-14 12:38:58');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `rating` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `message`, `rating`, `created_at`) VALUES
(1, 1, 'It\'s Good!', 5, '2026-05-14 13:51:39');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 12, 'welcome', 'Welcome to CCS Sit-in System', 'Welcome! You can now reserve laboratories and track your sit-in sessions.', 'dashboard.php', 0, '2026-05-14 12:41:39'),
(2, 2, 'welcome', 'Welcome to CCS Sit-in System', 'Welcome! You can now reserve laboratories and track your sit-in sessions.', 'dashboard.php', 0, '2026-05-14 12:42:36'),
(3, 1, 'welcome', 'Welcome to CCS Sit-in System', 'Welcome! You can now reserve laboratories and track your sit-in sessions.', 'dashboard.php', 0, '2026-05-14 12:47:13'),
(4, 1, 'reservation', 'Reservation Approved', 'Your reservation for Lab 524 on 2026-05-14 (PC PC-01) has been APPROVED. You may now proceed to the lab.', NULL, 0, '2026-05-14 13:00:14'),
(6, 11, 'welcome', 'Welcome to CCS Sit-in System', 'Welcome! You can now reserve laboratories and track your sit-in sessions.', 'dashboard.php', 0, '2026-05-14 13:02:21'),
(7, 1, 'sitin', 'Sit-in Completed', 'Your sit-in session has been completed. You received 1 reward point(s)! You now have 1 reward point(s). Get 3 points to earn +1 session!', 'history.php', 0, '2026-05-14 13:49:52'),
(8, 1, 'feedback', 'Feedback Received', 'Thank you for your feedback! Our admin will review it shortly.', 'history.php', 0, '2026-05-14 13:51:39');

-- --------------------------------------------------------

--
-- Table structure for table `pcs`
--

CREATE TABLE `pcs` (
  `id` int(11) NOT NULL,
  `lab` varchar(20) NOT NULL,
  `pc_number` varchar(20) NOT NULL,
  `status` enum('available','broken','reserved') NOT NULL DEFAULT 'available',
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pcs`
--

INSERT INTO `pcs` (`id`, `lab`, `pc_number`, `status`, `notes`, `updated_at`) VALUES
(1, '524', 'PC-01', 'available', NULL, '2026-05-14 09:10:34'),
(2, '526', 'PC-01', 'available', NULL, '2026-05-14 09:10:34'),
(3, '528', 'PC-01', 'available', NULL, '2026-05-14 09:10:34'),
(4, '530', 'PC-01', 'available', NULL, '2026-05-14 09:10:34'),
(5, '517', 'PC-01', 'available', NULL, '2026-05-14 11:18:20'),
(6, '524', 'PC-02', 'available', NULL, '2026-05-14 09:10:34'),
(7, '526', 'PC-02', 'available', NULL, '2026-05-14 09:10:34'),
(8, '528', 'PC-02', 'available', NULL, '2026-05-14 09:10:34'),
(9, '530', 'PC-02', 'available', NULL, '2026-05-14 09:10:34'),
(10, '517', 'PC-02', 'available', NULL, '2026-05-14 11:18:20'),
(11, '524', 'PC-03', 'available', NULL, '2026-05-14 09:10:34'),
(12, '526', 'PC-03', 'available', NULL, '2026-05-14 09:10:34'),
(13, '528', 'PC-03', 'available', NULL, '2026-05-14 09:10:34'),
(14, '530', 'PC-03', 'available', NULL, '2026-05-14 09:10:34'),
(15, '517', 'PC-03', 'available', NULL, '2026-05-14 11:18:20'),
(16, '524', 'PC-04', 'available', NULL, '2026-05-14 09:10:34'),
(17, '526', 'PC-04', 'available', NULL, '2026-05-14 09:10:34'),
(18, '528', 'PC-04', 'available', NULL, '2026-05-14 09:10:34'),
(19, '530', 'PC-04', 'available', NULL, '2026-05-14 09:10:34'),
(20, '517', 'PC-04', 'available', NULL, '2026-05-14 11:18:20'),
(21, '524', 'PC-05', 'available', NULL, '2026-05-14 09:10:34'),
(22, '526', 'PC-05', 'available', NULL, '2026-05-14 09:10:34'),
(23, '528', 'PC-05', 'available', NULL, '2026-05-14 09:10:34'),
(24, '530', 'PC-05', 'available', NULL, '2026-05-14 09:10:34'),
(25, '517', 'PC-05', 'available', NULL, '2026-05-14 11:18:20'),
(26, '524', 'PC-06', 'available', NULL, '2026-05-14 09:10:34'),
(27, '526', 'PC-06', 'available', NULL, '2026-05-14 09:10:34'),
(28, '528', 'PC-06', 'available', NULL, '2026-05-14 09:10:34'),
(29, '530', 'PC-06', 'available', NULL, '2026-05-14 09:10:34'),
(30, '517', 'PC-06', 'available', NULL, '2026-05-14 11:18:20'),
(31, '524', 'PC-07', 'available', NULL, '2026-05-14 09:10:34'),
(32, '526', 'PC-07', 'available', NULL, '2026-05-14 09:10:34'),
(33, '528', 'PC-07', 'available', NULL, '2026-05-14 09:10:34'),
(34, '530', 'PC-07', 'available', NULL, '2026-05-14 09:10:34'),
(35, '517', 'PC-07', 'available', NULL, '2026-05-14 11:18:20'),
(36, '524', 'PC-08', 'available', NULL, '2026-05-14 09:10:34'),
(37, '526', 'PC-08', 'available', NULL, '2026-05-14 09:10:34'),
(38, '528', 'PC-08', 'available', NULL, '2026-05-14 09:10:34'),
(39, '530', 'PC-08', 'available', NULL, '2026-05-14 09:10:34'),
(40, '517', 'PC-08', 'available', NULL, '2026-05-14 11:18:20'),
(41, '524', 'PC-09', 'available', NULL, '2026-05-14 09:10:34'),
(42, '526', 'PC-09', 'available', NULL, '2026-05-14 09:10:34'),
(43, '528', 'PC-09', 'available', NULL, '2026-05-14 11:51:59'),
(44, '530', 'PC-09', 'available', NULL, '2026-05-14 09:10:34'),
(45, '517', 'PC-09', 'available', NULL, '2026-05-14 11:18:20'),
(46, '524', 'PC-10', 'available', NULL, '2026-05-14 09:10:34'),
(47, '526', 'PC-10', 'available', NULL, '2026-05-14 09:10:34'),
(48, '528', 'PC-10', 'available', NULL, '2026-05-14 09:10:34'),
(49, '530', 'PC-10', 'available', NULL, '2026-05-14 09:10:34'),
(50, '517', 'PC-10', 'available', NULL, '2026-05-14 11:18:20'),
(51, '524', 'PC-11', 'available', NULL, '2026-05-14 09:10:34'),
(52, '526', 'PC-11', 'available', NULL, '2026-05-14 09:10:34'),
(53, '528', 'PC-11', 'available', NULL, '2026-05-14 09:10:34'),
(54, '530', 'PC-11', 'available', NULL, '2026-05-14 09:10:34'),
(55, '517', 'PC-11', 'available', NULL, '2026-05-14 11:18:20'),
(56, '524', 'PC-12', 'available', NULL, '2026-05-14 09:10:34'),
(57, '526', 'PC-12', 'available', NULL, '2026-05-14 09:10:34'),
(58, '528', 'PC-12', 'available', NULL, '2026-05-14 09:10:34'),
(59, '530', 'PC-12', 'available', NULL, '2026-05-14 09:10:34'),
(60, '517', 'PC-12', 'available', NULL, '2026-05-14 11:18:20'),
(61, '524', 'PC-13', 'available', NULL, '2026-05-14 09:10:34'),
(62, '526', 'PC-13', 'available', NULL, '2026-05-14 09:10:34'),
(63, '528', 'PC-13', 'available', NULL, '2026-05-14 09:10:34'),
(64, '530', 'PC-13', 'available', NULL, '2026-05-14 09:10:34'),
(65, '517', 'PC-13', 'available', NULL, '2026-05-14 11:18:20'),
(66, '524', 'PC-14', 'available', NULL, '2026-05-14 09:10:34'),
(67, '526', 'PC-14', 'available', NULL, '2026-05-14 09:10:34'),
(68, '528', 'PC-14', 'available', NULL, '2026-05-14 09:10:34'),
(69, '530', 'PC-14', 'available', NULL, '2026-05-14 09:10:34'),
(70, '517', 'PC-14', 'available', NULL, '2026-05-14 11:18:20'),
(71, '524', 'PC-15', 'available', NULL, '2026-05-14 09:10:34'),
(72, '526', 'PC-15', 'available', NULL, '2026-05-14 09:10:34'),
(73, '528', 'PC-15', 'available', NULL, '2026-05-14 09:10:34'),
(74, '530', 'PC-15', 'available', NULL, '2026-05-14 09:10:34'),
(75, '517', 'PC-15', 'available', NULL, '2026-05-14 11:18:20'),
(76, '524', 'PC-16', 'available', NULL, '2026-05-14 09:10:34'),
(77, '526', 'PC-16', 'available', NULL, '2026-05-14 09:10:34'),
(78, '528', 'PC-16', 'available', NULL, '2026-05-14 09:10:34'),
(79, '530', 'PC-16', 'available', NULL, '2026-05-14 09:10:34'),
(80, '517', 'PC-16', 'available', NULL, '2026-05-14 11:18:20'),
(81, '524', 'PC-17', 'available', NULL, '2026-05-14 09:10:34'),
(82, '526', 'PC-17', 'available', NULL, '2026-05-14 09:10:34'),
(83, '528', 'PC-17', 'available', NULL, '2026-05-14 09:10:34'),
(84, '530', 'PC-17', 'available', NULL, '2026-05-14 09:10:34'),
(85, '517', 'PC-17', 'available', NULL, '2026-05-14 11:18:20'),
(86, '524', 'PC-18', 'available', NULL, '2026-05-14 09:10:34'),
(87, '526', 'PC-18', 'available', NULL, '2026-05-14 09:10:34'),
(88, '528', 'PC-18', 'available', NULL, '2026-05-14 09:10:34'),
(89, '530', 'PC-18', 'available', NULL, '2026-05-14 09:10:34'),
(90, '517', 'PC-18', 'available', NULL, '2026-05-14 11:18:20'),
(91, '524', 'PC-19', 'available', NULL, '2026-05-14 09:10:34'),
(92, '526', 'PC-19', 'available', NULL, '2026-05-14 09:10:34'),
(93, '528', 'PC-19', 'available', NULL, '2026-05-14 09:10:34'),
(94, '530', 'PC-19', 'available', NULL, '2026-05-14 09:10:34'),
(95, '517', 'PC-19', 'available', NULL, '2026-05-14 11:18:20'),
(96, '524', 'PC-20', 'available', NULL, '2026-05-14 09:10:34'),
(97, '526', 'PC-20', 'available', NULL, '2026-05-14 09:10:34'),
(98, '528', 'PC-20', 'available', NULL, '2026-05-14 09:10:34'),
(99, '530', 'PC-20', 'available', NULL, '2026-05-14 09:10:34'),
(100, '517', 'PC-20', 'available', NULL, '2026-05-14 11:18:20'),
(101, '524', 'PC-21', 'available', NULL, '2026-05-14 09:10:34'),
(102, '526', 'PC-21', 'available', NULL, '2026-05-14 09:10:34'),
(103, '528', 'PC-21', 'available', NULL, '2026-05-14 09:10:34'),
(104, '530', 'PC-21', 'available', NULL, '2026-05-14 09:10:34'),
(105, '517', 'PC-21', 'available', NULL, '2026-05-14 11:18:20'),
(106, '524', 'PC-22', 'available', NULL, '2026-05-14 09:10:34'),
(107, '526', 'PC-22', 'available', NULL, '2026-05-14 09:10:34'),
(108, '528', 'PC-22', 'available', NULL, '2026-05-14 09:10:34'),
(109, '530', 'PC-22', 'available', NULL, '2026-05-14 09:10:34'),
(110, '517', 'PC-22', 'available', NULL, '2026-05-14 11:18:20'),
(111, '524', 'PC-23', 'available', NULL, '2026-05-14 09:10:34'),
(112, '526', 'PC-23', 'available', NULL, '2026-05-14 09:10:34'),
(113, '528', 'PC-23', 'available', NULL, '2026-05-14 09:10:34'),
(114, '530', 'PC-23', 'available', NULL, '2026-05-14 09:10:34'),
(115, '517', 'PC-23', 'available', NULL, '2026-05-14 11:18:20'),
(116, '524', 'PC-24', 'available', NULL, '2026-05-14 09:10:34'),
(117, '526', 'PC-24', 'available', NULL, '2026-05-14 09:10:34'),
(118, '528', 'PC-24', 'available', NULL, '2026-05-14 09:10:34'),
(119, '530', 'PC-24', 'available', NULL, '2026-05-14 09:10:34'),
(120, '517', 'PC-24', 'available', NULL, '2026-05-14 11:18:20'),
(121, '524', 'PC-25', 'available', NULL, '2026-05-14 09:10:34'),
(122, '526', 'PC-25', 'available', NULL, '2026-05-14 09:10:34'),
(123, '528', 'PC-25', 'available', NULL, '2026-05-14 09:10:34'),
(124, '530', 'PC-25', 'available', NULL, '2026-05-14 09:10:34'),
(125, '517', 'PC-25', 'available', NULL, '2026-05-14 11:18:20'),
(126, '524', 'PC-26', 'available', NULL, '2026-05-14 09:10:34'),
(127, '526', 'PC-26', 'available', NULL, '2026-05-14 09:10:34'),
(128, '528', 'PC-26', 'available', NULL, '2026-05-14 09:10:34'),
(129, '530', 'PC-26', 'available', NULL, '2026-05-14 09:10:34'),
(130, '517', 'PC-26', 'available', NULL, '2026-05-14 11:18:20'),
(131, '524', 'PC-27', 'available', NULL, '2026-05-14 09:10:34'),
(132, '526', 'PC-27', 'available', NULL, '2026-05-14 09:10:34'),
(133, '528', 'PC-27', 'available', NULL, '2026-05-14 09:10:34'),
(134, '530', 'PC-27', 'available', NULL, '2026-05-14 09:10:34'),
(135, '517', 'PC-27', 'available', NULL, '2026-05-14 11:18:20'),
(136, '524', 'PC-28', 'available', NULL, '2026-05-14 09:10:34'),
(137, '526', 'PC-28', 'available', NULL, '2026-05-14 09:10:34'),
(138, '528', 'PC-28', 'available', NULL, '2026-05-14 09:10:34'),
(139, '530', 'PC-28', 'available', NULL, '2026-05-14 09:10:34'),
(140, '517', 'PC-28', 'available', NULL, '2026-05-14 11:18:20'),
(141, '524', 'PC-29', 'available', NULL, '2026-05-14 09:10:34'),
(142, '526', 'PC-29', 'available', NULL, '2026-05-14 09:10:34'),
(143, '528', 'PC-29', 'available', NULL, '2026-05-14 09:10:34'),
(144, '530', 'PC-29', 'available', NULL, '2026-05-14 09:10:34'),
(145, '517', 'PC-29', 'available', NULL, '2026-05-14 11:18:20'),
(146, '524', 'PC-30', 'available', NULL, '2026-05-14 09:10:34'),
(147, '526', 'PC-30', 'available', NULL, '2026-05-14 09:10:34'),
(148, '528', 'PC-30', 'available', NULL, '2026-05-14 09:10:34'),
(149, '530', 'PC-30', 'available', NULL, '2026-05-14 09:10:34'),
(150, '517', 'PC-30', 'available', NULL, '2026-05-14 11:18:20'),
(151, '524', 'PC-31', 'available', NULL, '2026-05-14 09:10:34'),
(152, '526', 'PC-31', 'available', NULL, '2026-05-14 09:10:34'),
(153, '528', 'PC-31', 'available', NULL, '2026-05-14 09:10:34'),
(154, '530', 'PC-31', 'available', NULL, '2026-05-14 09:10:34'),
(155, '517', 'PC-31', 'available', NULL, '2026-05-14 11:18:20'),
(156, '524', 'PC-32', 'available', NULL, '2026-05-14 09:10:34'),
(157, '526', 'PC-32', 'available', NULL, '2026-05-14 09:10:34'),
(158, '528', 'PC-32', 'available', NULL, '2026-05-14 09:10:34'),
(159, '530', 'PC-32', 'available', NULL, '2026-05-14 09:10:34'),
(160, '517', 'PC-32', 'available', NULL, '2026-05-14 11:18:20'),
(161, '524', 'PC-33', 'available', NULL, '2026-05-14 09:10:34'),
(162, '526', 'PC-33', 'available', NULL, '2026-05-14 09:10:34'),
(163, '528', 'PC-33', 'available', NULL, '2026-05-14 09:10:34'),
(164, '530', 'PC-33', 'available', NULL, '2026-05-14 09:10:34'),
(165, '517', 'PC-33', 'available', NULL, '2026-05-14 11:18:20'),
(166, '524', 'PC-34', 'available', NULL, '2026-05-14 09:10:34'),
(167, '526', 'PC-34', 'available', NULL, '2026-05-14 09:10:34'),
(168, '528', 'PC-34', 'available', NULL, '2026-05-14 09:10:34'),
(169, '530', 'PC-34', 'available', NULL, '2026-05-14 09:10:34'),
(170, '517', 'PC-34', 'available', NULL, '2026-05-14 11:18:20'),
(171, '524', 'PC-35', 'available', NULL, '2026-05-14 09:10:34'),
(172, '526', 'PC-35', 'available', NULL, '2026-05-14 09:10:34'),
(173, '528', 'PC-35', 'available', NULL, '2026-05-14 09:10:34'),
(174, '530', 'PC-35', 'available', NULL, '2026-05-14 09:10:34'),
(175, '517', 'PC-35', 'available', NULL, '2026-05-14 11:18:20'),
(176, '524', 'PC-36', 'available', NULL, '2026-05-14 09:10:34'),
(177, '526', 'PC-36', 'available', NULL, '2026-05-14 09:10:34'),
(178, '528', 'PC-36', 'available', NULL, '2026-05-14 09:10:34'),
(179, '530', 'PC-36', 'available', NULL, '2026-05-14 09:10:34'),
(180, '517', 'PC-36', 'available', NULL, '2026-05-14 11:18:20'),
(181, '524', 'PC-37', 'available', NULL, '2026-05-14 09:10:34'),
(182, '526', 'PC-37', 'available', NULL, '2026-05-14 09:10:34'),
(183, '528', 'PC-37', 'available', NULL, '2026-05-14 09:10:34'),
(184, '530', 'PC-37', 'available', NULL, '2026-05-14 09:10:34'),
(185, '517', 'PC-37', 'available', NULL, '2026-05-14 11:18:20'),
(186, '524', 'PC-38', 'available', NULL, '2026-05-14 09:10:34'),
(187, '526', 'PC-38', 'available', NULL, '2026-05-14 09:10:34'),
(188, '528', 'PC-38', 'available', NULL, '2026-05-14 09:10:34'),
(189, '530', 'PC-38', 'available', NULL, '2026-05-14 09:10:34'),
(190, '517', 'PC-38', 'available', NULL, '2026-05-14 11:18:20'),
(191, '524', 'PC-39', 'available', NULL, '2026-05-14 09:10:34'),
(192, '526', 'PC-39', 'available', NULL, '2026-05-14 09:10:34'),
(193, '528', 'PC-39', 'available', NULL, '2026-05-14 09:10:34'),
(194, '530', 'PC-39', 'available', NULL, '2026-05-14 09:10:34'),
(195, '517', 'PC-39', 'available', NULL, '2026-05-14 11:18:20'),
(196, '524', 'PC-40', 'available', NULL, '2026-05-14 09:10:34'),
(197, '526', 'PC-40', 'available', NULL, '2026-05-14 09:10:34'),
(198, '528', 'PC-40', 'available', NULL, '2026-05-14 09:10:34'),
(199, '530', 'PC-40', 'available', NULL, '2026-05-14 09:10:34'),
(200, '517', 'PC-40', 'available', NULL, '2026-05-14 11:18:20'),
(201, '524', 'PC-41', 'available', NULL, '2026-05-14 09:10:34'),
(202, '526', 'PC-41', 'available', NULL, '2026-05-14 09:10:34'),
(203, '528', 'PC-41', 'available', NULL, '2026-05-14 09:10:34'),
(204, '530', 'PC-41', 'available', NULL, '2026-05-14 09:10:34'),
(205, '517', 'PC-41', 'available', NULL, '2026-05-14 11:18:20'),
(206, '524', 'PC-42', 'available', NULL, '2026-05-14 09:10:34'),
(207, '526', 'PC-42', 'available', NULL, '2026-05-14 09:10:34'),
(208, '528', 'PC-42', 'available', NULL, '2026-05-14 09:10:34'),
(209, '530', 'PC-42', 'available', NULL, '2026-05-14 09:10:34'),
(210, '517', 'PC-42', 'available', NULL, '2026-05-14 11:18:20'),
(211, '524', 'PC-43', 'available', NULL, '2026-05-14 09:10:34'),
(212, '526', 'PC-43', 'available', NULL, '2026-05-14 09:10:34'),
(213, '528', 'PC-43', 'available', NULL, '2026-05-14 09:10:34'),
(214, '530', 'PC-43', 'available', NULL, '2026-05-14 09:10:34'),
(215, '517', 'PC-43', 'available', NULL, '2026-05-14 11:18:20'),
(216, '524', 'PC-44', 'available', NULL, '2026-05-14 09:10:34'),
(217, '526', 'PC-44', 'available', NULL, '2026-05-14 09:10:34'),
(218, '528', 'PC-44', 'available', NULL, '2026-05-14 09:10:34'),
(219, '530', 'PC-44', 'available', NULL, '2026-05-14 09:10:34'),
(220, '517', 'PC-44', 'available', NULL, '2026-05-14 11:18:20'),
(221, '524', 'PC-45', 'available', NULL, '2026-05-14 09:10:34'),
(222, '526', 'PC-45', 'available', NULL, '2026-05-14 09:10:34'),
(223, '528', 'PC-45', 'available', NULL, '2026-05-14 09:10:34'),
(224, '530', 'PC-45', 'available', NULL, '2026-05-14 09:10:34'),
(225, '517', 'PC-45', 'available', NULL, '2026-05-14 11:18:20'),
(226, '524', 'PC-46', 'available', NULL, '2026-05-14 09:10:34'),
(227, '526', 'PC-46', 'available', NULL, '2026-05-14 09:10:34'),
(228, '528', 'PC-46', 'available', NULL, '2026-05-14 09:10:34'),
(229, '530', 'PC-46', 'available', NULL, '2026-05-14 09:10:34'),
(230, '517', 'PC-46', 'available', NULL, '2026-05-14 11:18:20'),
(231, '524', 'PC-47', 'available', NULL, '2026-05-14 09:10:34'),
(232, '526', 'PC-47', 'available', NULL, '2026-05-14 09:10:34'),
(233, '528', 'PC-47', 'available', NULL, '2026-05-14 09:10:34'),
(234, '530', 'PC-47', 'available', NULL, '2026-05-14 09:10:34'),
(235, '517', 'PC-47', 'available', NULL, '2026-05-14 11:18:20'),
(236, '524', 'PC-48', 'available', NULL, '2026-05-14 09:10:34'),
(237, '526', 'PC-48', 'available', NULL, '2026-05-14 09:10:34'),
(238, '528', 'PC-48', 'available', NULL, '2026-05-14 09:10:34'),
(239, '530', 'PC-48', 'available', NULL, '2026-05-14 09:10:34'),
(240, '517', 'PC-48', 'available', NULL, '2026-05-14 11:18:20'),
(241, '524', 'PC-49', 'available', NULL, '2026-05-14 09:10:34'),
(242, '526', 'PC-49', 'available', NULL, '2026-05-14 09:10:34'),
(243, '528', 'PC-49', 'available', NULL, '2026-05-14 09:10:34'),
(244, '530', 'PC-49', 'available', NULL, '2026-05-14 09:10:34'),
(245, '517', 'PC-49', 'available', NULL, '2026-05-14 11:18:20'),
(246, '524', 'PC-50', 'available', NULL, '2026-05-14 09:10:34'),
(247, '526', 'PC-50', 'available', NULL, '2026-05-14 09:10:34'),
(248, '528', 'PC-50', 'available', NULL, '2026-05-14 09:10:34'),
(249, '530', 'PC-50', 'available', NULL, '2026-05-14 09:10:34'),
(250, '517', 'PC-50', 'available', NULL, '2026-05-14 11:18:20');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `purpose` varchar(100) NOT NULL,
  `laboratory` varchar(100) NOT NULL,
  `pc_number` varchar(20) DEFAULT NULL,
  `time_in` time NOT NULL,
  `reservation_date` date NOT NULL,
  `status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `alt_pc_suggestion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `id_number`, `name`, `purpose`, `laboratory`, `pc_number`, `time_in`, `reservation_date`, `status`, `rejection_reason`, `alt_pc_suggestion`, `created_at`) VALUES
(1, 1, '21526785', 'CJ Charles', 'Java', '524', 'PC-01', '08:00:00', '2026-05-14', 'approved', NULL, NULL, '2026-05-14 12:59:11');

-- --------------------------------------------------------

--
-- Table structure for table `sit_in`
--

CREATE TABLE `sit_in` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `laboratory` varchar(100) NOT NULL,
  `pc_number` varchar(20) DEFAULT NULL,
  `login_time` time NOT NULL,
  `logout_time` time DEFAULT NULL,
  `login_date` date NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `reservation_id` int(11) DEFAULT NULL,
  `reward_points_given` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sit_in`
--

INSERT INTO `sit_in` (`id`, `user_id`, `id_number`, `name`, `purpose`, `laboratory`, `pc_number`, `login_time`, `logout_time`, `login_date`, `status`, `reward_points_given`, `created_at`) VALUES
(1, 1, '21526785', 'CJ Charles', 'Python', '524', NULL, '21:02:05', '21:49:52', '2026-05-14', 'completed', 1, '2026-05-14 13:02:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `course` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `year_level` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `password` varchar(255) NOT NULL,
  `sessions` int(11) DEFAULT 30,
  `reward_points` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `course`, `last_name`, `first_name`, `middle_name`, `year_level`, `email`, `address`, `password`, `sessions`, `reward_points`) VALUES
(1, '21526785', 'BSIT', 'Charles', 'CJ', 'R', 3, 'charlescj1203@gmail.com', 'T.Padilla St.,Cebu City', '$2y$10$gioGR2IYNBvvAyY4bOYEWOtYBbgF94SD8AWGkZ3E8YpzyKu.NwHna', 29, 1),
(2, '21418470', 'BSIT', 'Pacs', 'Ejhie', '', 3, 'ejhiepacquiao108@gmail.com', 'mingla', '$2y$10$JLu9vBZSyuEo4Bbumju8UOHHikODYziXPz3/ScHVjZS.blfdyXuLK', 30, 0),
(11, '21515010', 'BSIT', 'Carpentero', 'Althea Kathleen', 'B', 3, 'altheakathleen@gmail.com', 'Kamputhaw, Cebu City', '$2y$10$5YlNNzzABoEgr8u5Pln9zef.GkWy.xBynGYFit352vXbPqu0cawcq', 30, 0),
(12, '34521678', 'BSCS', 'Tamayo', 'Triszha', 'R', 2, 'trish@gmail.com', 'Lahug, Cebu City', '$2y$10$x1.wYYhLg.kcmyj1Zj5jhOmCKBxVebNMizwJeCnpQvpx.iHPX7fKe', 30, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`user_id`,`announcement_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `pcs`
--
ALTER TABLE `pcs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lab_pc` (`lab`,`pc_number`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sit_in`
--
ALTER TABLE `sit_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pcs`
--
ALTER TABLE `pcs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sit_in`
--
ALTER TABLE `sit_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sit_in`
--
ALTER TABLE `sit_in`
  ADD CONSTRAINT `sit_in_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sit_in_ibfk_2` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
