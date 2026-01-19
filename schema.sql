-- Database Schema for Bulk Order Assignment System

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Table structure for table `couriers`
--

CREATE TABLE `couriers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `serviceable_locations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`serviceable_locations`)),
  `daily_capacity` int(11) NOT NULL,
  `current_assigned_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_capacity` (`current_assigned_count`,`daily_capacity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `delivery_location` varchar(100) NOT NULL,
  `order_value` decimal(10,2) NOT NULL,
  `status` enum('NEW','ASSIGNED','UNASSIGNED') NOT NULL DEFAULT 'NEW',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_status_location` (`status`,`delivery_location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `order_assignments`
--

CREATE TABLE `order_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `assignment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESS',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uniq_order_assign` (`order_id`),
  KEY `idx_courier` (`courier_id`),
  CONSTRAINT `fk_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  CONSTRAINT `fk_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
