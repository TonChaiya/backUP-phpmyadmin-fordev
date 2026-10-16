-- Backup database: `drugmap_db`
-- Created at: 2026-07-21T08:58:57+00:00

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
START TRANSACTION;
SET FOREIGN_KEY_CHECKS=0;

CREATE DATABASE IF NOT EXISTS `drugmap_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `drugmap_db`;


-- --------------------------------------------------------
-- Table structure for `drug_lots`

DROP TABLE IF EXISTS `drug_lots`;
CREATE TABLE `drug_lots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pcucode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '05957' COMMENT 'รหัสหน่วยงาน',
  `takenno` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เลขที่ที่ออก',
  `drugcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'รหัสยา',
  `dateexpire` date NOT NULL COMMENT 'วันที่หมดอายุ',
  `lotno` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'เลขล็อต',
  `lotamount` int NOT NULL COMMENT 'จำนวนยา',
  `lotunit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'หน่วยของล็อต',
  `lotprice` decimal(10,2) NOT NULL COMMENT 'ราคา',
  `packamount` int NOT NULL COMMENT 'จำนวนในบรรจุ',
  `packunit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'หน่วยบรรจุ',
  `uploaded_file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ชื่อไฟล์ที่อัพโหลด',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_drugcode` (`drugcode`),
  KEY `idx_dateexpire` (`dateexpire`),
  CONSTRAINT `drug_lots_ibfk_1` FOREIGN KEY (`drugcode`) REFERENCES `drugs` (`drugcode`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางจัดการล็อตยา';


-- --------------------------------------------------------
-- Table structure for `drugs`

DROP TABLE IF EXISTS `drugs`;
CREATE TABLE `drugs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `working_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'รหัสการทำงาน',
  `drugcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'รหัสยา',
  `drug_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ชื่อยา',
  `unitsellcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'รหัสหน่วยขาย',
  `unitsellname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ชื่อหน่วยขาย',
  `packunit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'หน่วยบรรจุ',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `working_code` (`working_code`),
  UNIQUE KEY `drugcode` (`drugcode`),
  KEY `idx_working_code` (`working_code`),
  KEY `idx_drugcode` (`drugcode`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางข้อมูลยามูลฐาน';


-- --------------------------------------------------------
-- Table structure for `upload_history`

DROP TABLE IF EXISTS `upload_history`;
CREATE TABLE `upload_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ชื่อไฟล์',
  `file_size` int NOT NULL COMMENT 'ขนาดไฟล์ (bytes)',
  `total_rows` int NOT NULL COMMENT 'จำนวนแถวทั้งหมด',
  `success_rows` int NOT NULL COMMENT 'จำนวนแถวที่สำเร็จ',
  `failed_rows` int NOT NULL COMMENT 'จำนวนแถวที่ล้มเหลว',
  `error_log` longtext COLLATE utf8mb4_unicode_ci COMMENT 'บันทึกข้อผิดพลาด',
  `uploaded_by` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ผู้อัพโหลด',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางประวัติการอัพโหลด';

SET FOREIGN_KEY_CHECKS=1;
COMMIT;
