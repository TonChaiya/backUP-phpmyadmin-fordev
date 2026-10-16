-- Backup database: `autoprintdb`
-- Created at: 2026-08-07T12:59:36+00:00
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
  `visitno` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `pid` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','waiting','processing','ready','printing','done','error') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB AUTO_INCREMENT=49302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `autoprint_queue` (`id`, `visitno`, `pid`, `status`, `force`, `pdf_path`, `attempts`, `last_error`, `created_at`, `updated_at`, `printed_at`) VALUES ('1', '132395', '1577', 'waiting', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:34:58', NULL),
('2', '132396', '461', 'waiting', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:34:58', NULL),
('3', '132397', '1942', 'done', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:36:57', '2026-08-06 09:36:57'),
('4', '132398', '1138', 'done', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:37:02', '2026-08-06 09:37:02'),
('5', '132401', '3577', 'ready', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:37:03', NULL),
('6', '132402', '434', 'done', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:38:04', '2026-08-06 09:38:04'),
('7', '132399', '199', 'done', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:38:08', '2026-08-06 09:38:08'),
('8', '132403', '3496', 'done', '0', NULL, '0', NULL, '2026-08-06 09:34:56', '2026-08-06 09:38:11', '2026-08-06 09:38:11'),
('177', '132405', '2273', 'done', '0', NULL, '0', NULL, '2026-08-06 09:36:56', '2026-08-06 09:38:14', '2026-08-06 09:38:14'),
('223', '132406', '2020', 'done', '0', NULL, '0', NULL, '2026-08-06 09:38:15', '2026-08-06 09:38:54', '2026-08-06 09:38:54'),
('664', '132407', '2449', 'done', '0', NULL, '0', NULL, '2026-08-06 09:42:27', '2026-08-06 09:44:21', '2026-08-06 09:44:21'),
('1545', '132409', '3611', 'done', '0', NULL, '0', NULL, '2026-08-06 09:50:06', '2026-08-06 09:51:28', '2026-08-06 09:51:28'),
('1858', '132410', '1152', 'done', '0', NULL, '0', NULL, '2026-08-06 09:52:35', '2026-08-06 09:53:03', '2026-08-06 09:53:03'),
('2054', '132411', '3639', 'done', '0', NULL, '0', NULL, '2026-08-06 09:54:01', '2026-08-06 09:57:00', '2026-08-06 09:57:00'),
('2783', '132412', '1272', 'done', '0', NULL, '0', NULL, '2026-08-06 09:58:58', '2026-08-06 10:00:03', '2026-08-06 10:00:03'),
('3069', '132413', '1220', 'done', '0', NULL, '0', NULL, '2026-08-06 10:00:48', '2026-08-06 10:01:21', '2026-08-06 10:01:21'),
('3678', '132414', '7442', 'done', '0', NULL, '0', NULL, '2026-08-06 10:04:32', '2026-08-06 10:07:23', '2026-08-06 10:07:23'),
('4376', '132415', '201', 'done', '0', NULL, '0', NULL, '2026-08-06 10:08:27', '2026-08-06 10:09:18', '2026-08-06 10:09:18'),
('4737', '132416', '341', 'done', '0', NULL, '0', NULL, '2026-08-06 10:10:22', '2026-08-06 10:12:00', '2026-08-06 10:12:00'),
('9868', '132419', '4082', 'done', '0', NULL, '0', NULL, '2026-08-06 10:36:05', '2026-08-06 10:36:32', '2026-08-06 10:36:32'),
('10109', '132420', '2252', 'done', '0', NULL, '0', NULL, '2026-08-06 10:37:14', '2026-08-06 10:37:44', '2026-08-06 10:37:44'),
('13575', '132421', '2647', 'done', '0', NULL, '0', NULL, '2026-08-06 10:53:04', '2026-08-06 10:53:45', '2026-08-06 10:53:45'),
('19582', '132422', '11926', 'done', '0', NULL, '0', NULL, '2026-08-06 11:19:21', '2026-08-06 11:20:47', '2026-08-06 11:20:47'),
('21584', '132423', '3457', 'done', '0', NULL, '0', NULL, '2026-08-06 11:27:42', '2026-08-06 11:28:35', '2026-08-06 11:28:35'),
('44001', '132424', '8105', 'done', '0', NULL, '0', NULL, '2026-08-06 12:57:38', '2026-08-06 12:57:55', '2026-08-06 12:57:55');

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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `herbal_compress_history` (`id`, `visitno`, `pid`, `pcucode`, `patient_name`, `service_date`, `service_time`, `drugcode`, `status`, `received_at`, `declined_at`, `cancelled_at`, `print_status`, `printed_at`, `pdf_path`, `last_error`, `created_at`, `updated_at`) VALUES ('2', '132360', '10466', '05957', 'นายบุญเลิศ ทองปาน', '2026-08-03', '11:24:27', 'H227', 'received', '2026-08-03 16:06:32', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:06:32', '2026-08-03 16:06:32'),
('3', '132358', '1553', '05957', 'นายประเวช ไชยวรรณ์', '2026-08-03', '11:21:29', 'H227', 'received', '2026-08-03 16:06:44', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:06:44', '2026-08-03 16:06:44'),
('4', '132353', '65', '05957', 'นายกองคำ สุยะปัน', '2026-08-03', '10:38:22', 'H227', 'received', '2026-08-03 16:06:53', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:06:53', '2026-08-03 16:06:53'),
('5', '132350', '6466', '05957', 'นางเข่ง พรหมมาเตียม', '2026-08-03', '10:23:01', 'H227', 'received', '2026-08-03 16:07:07', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:07:07', '2026-08-03 16:07:07'),
('6', '132349', '2143', '05957', 'นางวนิดา ปาลี', '2026-08-03', '10:14:56', 'H227', 'received', '2026-08-03 16:07:13', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:07:13', '2026-08-03 16:07:13'),
('7', '132346', '1304', '05957', 'นางบัวเรียว ปินตาสม', '2026-08-03', '10:03:53', 'H227', 'received', '2026-08-03 16:07:21', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:07:21', '2026-08-03 16:07:21'),
('8', '132343', '1638', '05957', 'นายปิยะศักดิ์ โอชาวัฒน์', '2026-08-03', '09:40:04', 'H227', 'received', '2026-08-03 16:07:32', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:07:32', '2026-08-03 16:07:32'),
('9', '132342', '790', '05957', 'นางดาราณี หน่อแก้ว', '2026-08-03', '09:32:24', 'H227', 'received', '2026-08-03 16:07:40', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:07:40', '2026-08-03 16:07:40'),
('10', '132336', '3869', '05957', 'นายเหรียญ ทาเกิด', '2026-08-03', '09:09:23', 'H227', 'received', '2026-08-03 16:07:52', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:07:52', '2026-08-03 16:07:52'),
('11', '132334', '1269', '05957', 'นางบัวบาน จำปาทอง', '2026-08-03', '09:02:54', 'H227', 'received', '2026-08-03 16:08:00', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:08:00', '2026-08-03 16:08:00'),
('12', '132335', '3516', '05957', 'นางอูปแก้ว ขันคำ', '2026-08-03', '09:04:47', 'H227', 'received', '2026-08-03 16:08:06', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:08:06', '2026-08-03 16:08:06'),
('13', '132333', '2049', '05957', 'นางรัตนา สุภาศรี', '2026-08-03', '08:57:07', 'H227', 'received', '2026-08-03 16:08:13', NULL, NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:08:13', '2026-08-03 16:08:13'),
('14', '132366', '1575', '05957', 'นายประเสริฐ โปธาเกี๋ยง', '2026-08-03', '15:05:57', 'H227', 'declined', NULL, '2026-08-03 16:15:10', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:10', '2026-08-03 16:15:10'),
('15', '132364', '11970', '05957', 'ด.ช.อภิวันท์ สุธีพรวิโรจน์', '2026-08-03', '13:25:58', 'H227', 'declined', NULL, '2026-08-03 16:15:12', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:12', '2026-08-03 16:15:12'),
('16', '132363', '9818', '05957', 'นายสว่าง ปัญญาเจริญ', '2026-08-03', '13:09:27', 'H227', 'declined', NULL, '2026-08-03 16:15:14', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:14', '2026-08-03 16:15:14'),
('17', '132361', '2479', '05957', 'นายศรีลา มาระดา', '2026-08-03', '11:32:29', 'H227', 'declined', NULL, '2026-08-03 16:15:15', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:15', '2026-08-03 16:15:15'),
('18', '132359', '6805', '05957', 'นายจรัญ ใจมุข', '2026-08-03', '11:23:03', 'H227', 'declined', NULL, '2026-08-03 16:15:17', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:17', '2026-08-03 16:15:17'),
('19', '132357', '6809', '05957', 'น.ส.ไอยวรินทร์ มนต์แก้ว', '2026-08-03', '11:20:44', 'H227', 'declined', NULL, '2026-08-03 16:15:18', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:18', '2026-08-03 16:15:18'),
('20', '132356', '3280', '05957', 'นางอัทธยา ยานะ', '2026-08-03', '11:12:01', 'H227', 'declined', NULL, '2026-08-03 16:15:20', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:20', '2026-08-03 16:15:20'),
('21', '132355', '4977', '05957', 'น.ส.วัชราภรณ์ สุธีพรวิโรจน์', '2026-08-03', '11:07:14', 'H227', 'declined', NULL, '2026-08-03 16:15:21', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:21', '2026-08-03 16:15:21'),
('22', '132352', '2428', '05957', 'นายศราวุธ มาระดา', '2026-08-03', '10:34:32', 'H227', 'declined', NULL, '2026-08-03 16:15:23', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:23', '2026-08-03 16:15:23'),
('23', '132351', '152', '05957', 'นายก๋องคำ มาระดา', '2026-08-03', '10:27:47', 'H227', 'declined', NULL, '2026-08-03 16:15:24', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:24', '2026-08-03 16:15:24'),
('24', '132344', '2000', '05957', 'นายยุทธนา ไชยมงคล', '2026-08-03', '09:41:19', 'H227', 'declined', NULL, '2026-08-03 16:15:26', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:26', '2026-08-03 16:15:26'),
('25', '132339', '867', '05957', 'นายถาวร หน่อแก้ว', '2026-08-03', '09:20:41', 'H227', 'declined', NULL, '2026-08-03 16:15:27', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:27', '2026-08-03 16:15:27'),
('26', '132338', '1548', '05957', 'นายประสิทธิ์ ใจสาม', '2026-08-03', '09:17:00', 'H227', 'declined', NULL, '2026-08-03 16:15:29', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:29', '2026-08-03 16:15:29'),
('27', '132332', '1206', '05957', 'นางนิ่มนภา คำโมง', '2026-08-03', '08:50:46', 'H227', 'declined', NULL, '2026-08-03 16:15:31', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:15:31', '2026-08-03 16:15:31'),
('28', '132368', '7063', '05957', 'นายกิตติพัทธ์ ปินชัยธนวัชร์', '2026-08-03', '16:05:32', 'H227', 'declined', NULL, '2026-08-03 16:58:03', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:03', '2026-08-03 16:58:03'),
('29', '132367', '846', '05957', 'นายถนอม ประสงค์ทรัพย์', '2026-08-03', NULL, 'H227', 'declined', NULL, '2026-08-03 16:58:04', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:04', '2026-08-03 16:58:04'),
('30', '132365', '2821', '05957', 'นายสำราญ จันต๊ะตึง', '2026-08-03', '15:01:11', 'H227', 'declined', NULL, '2026-08-03 16:58:04', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:04', '2026-08-03 16:58:04'),
('31', '132362', '2995', '05957', 'น.ส.สุพรรษา ตุ้ยคำ', '2026-08-03', '11:41:41', 'H227', 'declined', NULL, '2026-08-03 16:58:05', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:05', '2026-08-03 16:58:05'),
('32', '132354', '2271', '05957', 'นายวัลลภ โปธิคำ', '2026-08-03', '10:47:36', 'H227', 'declined', NULL, '2026-08-03 16:58:05', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:05', '2026-08-03 16:58:05'),
('33', '132348', '3586', '05957', 'นายเกียรติพงษ์ สมสร้อย', '2026-08-03', '10:11:10', 'H227', 'declined', NULL, '2026-08-03 16:58:05', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:05', '2026-08-03 16:58:05'),
('34', '132347', '3941', '05957', 'นางแก้วภา มูลงาม', '2026-08-03', '10:05:36', 'H227', 'declined', NULL, '2026-08-03 16:58:07', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:07', '2026-08-03 16:58:07'),
('35', '132345', '1837', '05957', 'นางฟองคำ สมสร้อย', '2026-08-03', '09:43:27', 'H227', 'declined', NULL, '2026-08-03 16:58:08', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:08', '2026-08-03 16:58:08'),
('36', '132341', '9799', '05957', 'น.ส.ดารณี จันทร์แหง', '2026-08-03', '09:28:56', 'H227', 'declined', NULL, '2026-08-03 16:58:09', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:09', '2026-08-03 16:58:09'),
('37', '132340', '2782', '05957', 'นางสายทอง มหายศดี', '2026-08-03', '09:23:40', 'H227', 'declined', NULL, '2026-08-03 16:58:09', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:09', '2026-08-03 16:58:09'),
('38', '132337', '2715', '05957', 'นางสวย ทาเกิด', '2026-08-03', '09:14:23', 'H227', 'declined', NULL, '2026-08-03 16:58:10', NULL, 'not_required', NULL, NULL, NULL, '2026-08-03 16:58:10', '2026-08-03 16:58:10');

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

ALTER TABLE `autoprint_queue` AUTO_INCREMENT = 49301;
ALTER TABLE `herbal_compress_history` AUTO_INCREMENT = 38;
ALTER TABLE `visit_flag_audit` AUTO_INCREMENT = 6;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
-- Backup completed successfully
