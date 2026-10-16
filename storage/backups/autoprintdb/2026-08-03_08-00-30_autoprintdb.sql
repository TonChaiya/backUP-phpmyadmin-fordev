-- Backup database: `autoprintdb`
-- Created at: 2026-08-03T08:00:30+00:00
-- Dump order: tables/data, triggers, views, routines, events

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `autoprintdb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `autoprintdb`;


-- --------------------------------------------------------
-- Table structure for `autoprint_queue`

DROP TABLE IF EXISTS `autoprint_queue`;
CREATE TABLE `autoprint_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visitno` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pid` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','processing','ready','printing','done','waiting','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `force` tinyint(1) NOT NULL DEFAULT '0',
  `pdf_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attempts` int DEFAULT '0',
  `last_error` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `printed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_visitno` (`visitno`),
  KEY `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for `herbal_compress_history`

DROP TABLE IF EXISTS `herbal_compress_history`;
CREATE TABLE `herbal_compress_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visitno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pid` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pcucode` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `patient_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_date` date NOT NULL,
  `service_time` time DEFAULT NULL,
  `drugcode` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'H227',
  `status` enum('received','declined','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `received_at` datetime DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `print_status` enum('not_required','pending','printed','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_required',
  `printed_at` datetime DEFAULT NULL,
  `pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_herbal_compress_visitno` (`visitno`),
  KEY `idx_herbal_compress_pid_date` (`pid`,`service_date`),
  KEY `idx_herbal_compress_status_date` (`status`,`service_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for `visit_flag_audit`

DROP TABLE IF EXISTS `visit_flag_audit`;
CREATE TABLE `visit_flag_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visitno` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `old_flag` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_flag` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- AUTO_INCREMENT values restored after data load

ALTER TABLE `autoprint_queue` AUTO_INCREMENT = 983323;
ALTER TABLE `visit_flag_audit` AUTO_INCREMENT = 7;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
-- Backup completed successfully
