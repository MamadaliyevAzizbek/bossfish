-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: 127.0.0.1    Database: baliqchilik
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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

--
-- Current Database: `baliqchilik`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `baliqchilik` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `baliqchilik`;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `performed_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  KEY `activity_logs_pond_id_performed_at_index` (`pond_id`,`performed_at`),
  CONSTRAINT `activity_logs_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,2,'pond_created','Havza qo‘shildi','Ishonch 7 havzasi yaratildi','{\"region\":\"Sirdaryo\",\"area_m2\":\"6.00\"}','2026-07-30 00:07:59','2026-07-30 07:07:59','2026-07-30 07:07:59'),(2,1,2,'fish_add','Baliq qo‘shildi','200000 dona sazan, o‘rtacha vazni 3.000 kg','{\"fish_type\":\"sazan\",\"quantity\":200000,\"average_weight_kg\":\"3.000\",\"amount\":null,\"notes\":null}','2026-07-25 00:00:00','2026-07-30 07:09:30','2026-07-30 07:09:30'),(3,1,2,'main_report','Asosiy hisobot topshirildi','2026-07-30 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":\"12:30\",\"do_mg_l\":8,\"ph_level\":7,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":30,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_kg\":7}]}','2026-07-30 00:00:00','2026-07-31 02:50:49','2026-07-31 02:50:49'),(4,1,2,'daily_report','Kunlik hisobot topshirildi','2026-07-30 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Ekstruder 35\",\"feeding_kg\":230,\"wheat_bran_kg\":null,\"fertilizer_kg\":null}','2026-07-30 00:00:00','2026-07-31 02:53:59','2026-07-31 02:53:59'),(5,1,1,'main_report_updated','Asosiy hisobot o‘zgartirildi','2026-07-29 sanasi uchun asosiy hisobot yangilandi','{\"test_time\":\"12:30\",\"do_mg_l\":\"8.000\",\"ph_level\":\"7.00\",\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":\"30.00\",\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":7}]}','2026-07-29 00:00:00','2026-07-31 18:14:55','2026-07-31 18:14:55'),(6,1,1,'fish_add_updated','Baliq operatsiyasi o‘zgartirildi','200000 dona yangilandi','{\"fish_type\":\"sazan\",\"quantity\":200000,\"average_weight_g\":3,\"amount\":null,\"notes\":null}','2026-07-24 00:00:00','2026-07-31 18:15:17','2026-07-31 18:15:17'),(7,1,1,'fish_add','Baliq qo‘shildi','214000 dona tolstolob, o‘rtacha vazni 1 g','{\"fish_type\":\"tolstolob\",\"quantity\":214000,\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-07-31 00:00:00','2026-07-31 18:15:53','2026-07-31 18:15:53'),(8,1,1,'main_report','Asosiy hisobot topshirildi','2026-07-31 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":null,\"do_mg_l\":null,\"ph_level\":null,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":null,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":\"7\"},{\"fish_type\":\"tolstolob\",\"average_weight_g\":2}]}','2026-07-31 00:00:00','2026-07-31 18:16:48','2026-07-31 18:16:48'),(9,1,1,'fish_sale','Baliq sotildi','429 dona sazan, 3.000 kg, o‘rtacha vazni 7 g','{\"fish_type\":\"sazan\",\"quantity\":429,\"total_weight_kg\":\"3.000\",\"average_weight_g\":7,\"amount\":\"4000.00\",\"notes\":null}','2026-08-01 00:00:00','2026-08-01 21:52:48','2026-08-01 21:52:48'),(10,1,1,'daily_report','Kunlik hisobot topshirildi','2026-08-01 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Granula 32\",\"feeding_kg\":1,\"wheat_bran_kg\":null,\"fertilizer_kg\":null}','2026-08-01 00:00:00','2026-08-01 21:59:40','2026-08-01 21:59:40'),(11,1,1,'main_report','Asosiy hisobot topshirildi','2026-08-01 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":\"12:39\",\"do_mg_l\":5,\"ph_level\":8,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":null,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":8},{\"fish_type\":\"tolstolob\",\"average_weight_g\":3}]}','2026-08-01 00:00:00','2026-08-01 22:00:20','2026-08-01 22:00:20'),(12,1,1,'daily_report','Kunlik hisobot topshirildi','2026-08-01 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Granula 35\",\"feeding_kg\":1,\"wheat_bran_kg\":null,\"fertilizer_kg\":4}','2026-08-01 00:00:00','2026-08-01 22:00:57','2026-08-01 22:00:57'),(13,1,2,'daily_report','Kunlik hisobot topshirildi','2026-08-03 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Granula 32\",\"feeding_kg\":1,\"wheat_bran_kg\":1,\"fertilizer_kg\":null}','2026-08-03 00:00:00','2026-08-03 18:11:15','2026-08-03 18:11:15'),(14,2,2,'pond_created','Havza qo‘shildi','a havzasi yaratildi','{\"region\":\"Sirdaryo\",\"area_m2\":\"5.00\"}','2026-08-04 08:55:08','2026-08-04 15:55:08','2026-08-04 15:55:08'),(15,1,2,'fish_sale','Baliq sotildi','7 dona sazan, 0.700 kg, o‘rtacha vazni 100 g','{\"fish_type\":\"sazan\",\"quantity\":7,\"total_weight_kg\":\"0.700\",\"average_weight_g\":100,\"amount\":null,\"notes\":null}','2026-08-04 00:00:00','2026-08-04 16:06:48','2026-08-04 16:06:48'),(16,3,1,'pond_created','Havza qo‘shildi','&&& havzasi Egasi tomonidan yaratildi','{\"region\":\"Sirdaryo\",\"area_m2\":\"6.00\"}','2026-08-05 21:53:23','2026-08-06 04:53:23','2026-08-06 04:53:23'),(17,3,1,'fish_add','Baliq qo‘shildi','210000 dona tolstolob, 210.000 kg, o‘rtacha vazni 1 g','{\"fish_type\":\"tolstolob\",\"quantity\":210000,\"total_weight_kg\":\"210.000\",\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-08-05 00:00:00','2026-08-06 04:55:00','2026-08-06 04:55:00'),(18,3,1,'daily_report','Kunlik hisobot topshirildi','2026-06-08 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Ekstruder 37-32\",\"feeding_kg\":6,\"wheat_bran_kg\":null,\"fertilizer_kg\":null}','2026-06-08 00:00:00','2026-08-06 04:56:13','2026-08-06 04:56:13'),(19,3,1,'fish_operation_deleted','Baliq operatsiyasi o‘chirildi','Operatsiya o‘chirildi va zaxira qayta hisoblandi','[]','2026-08-05 21:56:48','2026-08-06 04:56:48','2026-08-06 04:56:48'),(20,3,1,'fish_add','Baliq qo‘shildi','210000 dona tolstolob, 210.000 kg, o‘rtacha vazni 1 g','{\"fish_type\":\"tolstolob\",\"quantity\":210000,\"total_weight_kg\":\"210.000\",\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-06-07 00:00:00','2026-08-06 04:57:03','2026-08-06 04:57:03'),(21,3,1,'fish_operation_deleted','Baliq operatsiyasi o‘chirildi','Operatsiya o‘chirildi va zaxira qayta hisoblandi','[]','2026-08-05 21:57:48','2026-08-06 04:57:48','2026-08-06 04:57:48'),(22,3,1,'fish_add','Baliq qo‘shildi','210000 dona tolstolob, 210.000 kg, o‘rtacha vazni 1 g','{\"fish_type\":\"tolstolob\",\"quantity\":210000,\"total_weight_kg\":\"210.000\",\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-07-07 00:00:00','2026-08-06 04:58:07','2026-08-06 04:58:07'),(23,3,1,'fish_add','Baliq qo‘shildi','160000 dona oq_amur, 160.000 kg, o‘rtacha vazni 1 g','{\"fish_type\":\"oq_amur\",\"quantity\":160000,\"total_weight_kg\":\"160.000\",\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-07-10 00:00:00','2026-08-06 05:27:32','2026-08-06 05:27:32'),(24,3,1,'fish_add','Baliq qo‘shildi','7500 dona sazan, 30.000 kg, o‘rtacha vazni 4 g','{\"fish_type\":\"sazan\",\"quantity\":7500,\"total_weight_kg\":\"30.000\",\"average_weight_g\":4,\"amount\":null,\"notes\":null}','2026-07-11 00:00:00','2026-08-06 05:28:09','2026-08-06 05:28:09'),(25,3,1,'fish_add','Baliq qo‘shildi','45000 dona targil_tolstolob, 90.000 kg, o‘rtacha vazni 2 g','{\"fish_type\":\"targil_tolstolob\",\"quantity\":45000,\"total_weight_kg\":\"90.000\",\"average_weight_g\":2,\"amount\":null,\"notes\":null}','2026-07-13 00:00:00','2026-08-06 05:28:50','2026-08-06 05:28:50'),(26,3,1,'fish_add','Baliq qo‘shildi','70000 dona sazan, 420.000 kg, o‘rtacha vazni 6 g','{\"fish_type\":\"sazan\",\"quantity\":70000,\"total_weight_kg\":\"420.000\",\"average_weight_g\":6,\"amount\":null,\"notes\":null}','2026-07-22 00:00:00','2026-08-06 05:35:15','2026-08-06 05:35:15'),(27,3,1,'fish_add','Baliq qo‘shildi','200000 dona sazan, 600.000 kg, o‘rtacha vazni 3 g','{\"fish_type\":\"sazan\",\"quantity\":200000,\"total_weight_kg\":\"600.000\",\"average_weight_g\":3,\"amount\":null,\"notes\":null}','2026-07-24 00:00:00','2026-08-06 05:35:33','2026-08-06 05:35:33'),(28,3,1,'fish_add','Baliq qo‘shildi','12000 dona sazan, 120.000 kg, o‘rtacha vazni 10 g','{\"fish_type\":\"sazan\",\"quantity\":12000,\"total_weight_kg\":\"120.000\",\"average_weight_g\":10,\"amount\":null,\"notes\":null}','2026-08-25 00:00:00','2026-08-06 05:37:06','2026-08-06 05:37:06'),(29,3,1,'fish_add','Baliq qo‘shildi','24000 dona oq_amur, 24.000 kg, o‘rtacha vazni 1 g','{\"fish_type\":\"oq_amur\",\"quantity\":24000,\"total_weight_kg\":\"24.000\",\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-07-28 00:00:00','2026-08-06 08:08:44','2026-08-06 08:08:44'),(30,3,1,'main_report','Asosiy hisobot topshirildi','2026-08-05 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":\"18:00\",\"do_mg_l\":null,\"ph_level\":8.199999999999999289457264239899814128875732421875,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":35,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":7},{\"fish_type\":\"tolstolob\",\"average_weight_g\":\"1\"},{\"fish_type\":\"oq_amur\",\"average_weight_g\":8},{\"fish_type\":\"targil_tolstolob\",\"average_weight_g\":\"2\"}]}','2026-08-05 00:00:00','2026-08-06 08:18:35','2026-08-06 08:18:35'),(31,3,1,'daily_report_deleted','Kunlik hisobot o‘chirildi','2026-06-08 sanasidagi kunlik hisobot o‘chirildi','{\"report_date\":\"2026-06-08\"}','2026-06-08 00:00:00','2026-08-06 08:20:05','2026-08-06 08:20:05'),(32,3,1,'daily_report','Kunlik hisobot topshirildi','2026-08-05 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Ekstruder 37-32\",\"feeding_kg\":300,\"wheat_bran_kg\":null,\"fertilizer_kg\":null}','2026-08-05 00:00:00','2026-08-06 08:20:57','2026-08-06 08:20:57'),(33,3,1,'warehouse_in','Omborga qo‘shildi','yem — 10 kg','{\"category\":\"feed\",\"name\":\"yem\",\"quantity\":\"10\",\"unit\":\"kg\"}','2026-08-09 20:37:08','2026-08-10 03:37:08','2026-08-10 03:37:08'),(34,3,1,'daily_report','Kunlik hisobot topshirildi','2026-08-09 sanasi uchun kunlik hisobot saqlandi','{\"items\":[{\"name\":\"yem\",\"quantity\":\"10.00\",\"unit\":\"kg\"}]}','2026-08-09 00:00:00','2026-08-10 04:55:48','2026-08-10 04:55:48'),(35,4,3,'pond_created','Havza qo‘shildi','havza havzasi yaratildi','{\"branch_id\":3,\"region\":\"Sirdaryo\",\"area_m2\":\"2.00\"}','2026-08-11 11:25:28','2026-08-11 18:25:28','2026-08-11 18:25:28'),(36,1,2,'warehouse_in','Omborga kirim qilindi','Brobiotik — 4 kg','{\"category\":\"medicine\",\"name\":\"Brobiotik\",\"quantity\":4,\"unit\":\"kg\"}','2026-08-15 22:52:48','2026-08-16 05:52:48','2026-08-16 05:52:48'),(37,1,2,'fish_sale','Baliq sotildi','4 dona sazan, 0.800 kg, o‘rtacha vazni 200 g','{\"fish_type\":\"sazan\",\"quantity\":4,\"total_weight_kg\":\"0.800\",\"average_weight_g\":200,\"amount\":\"400000.00\",\"notes\":null}','2026-08-15 00:00:00','2026-08-16 07:17:54','2026-08-16 07:17:54'),(38,1,2,'fish_operation_deleted','Baliq operatsiyasi o‘chirildi','Operatsiya o‘chirildi va zaxira qayta hisoblandi','[]','2026-08-16 00:19:50','2026-08-16 07:19:50','2026-08-16 07:19:50'),(39,1,2,'fish_operation_deleted','Baliq operatsiyasi o‘chirildi','Operatsiya o‘chirildi va zaxira qayta hisoblandi','[]','2026-08-16 00:19:52','2026-08-16 07:19:52','2026-08-16 07:19:52'),(40,1,2,'fish_operation_deleted','Baliq operatsiyasi o‘chirildi','Operatsiya o‘chirildi va zaxira qayta hisoblandi','[]','2026-08-16 00:19:54','2026-08-16 07:19:54','2026-08-16 07:19:54'),(41,1,2,'warehouse_in','Omborga kirim qilindi','Ekstruder 35 — 200 kg','{\"category\":\"feed\",\"name\":\"Ekstruder 35\",\"quantity\":200,\"unit\":\"kg\"}','2026-08-16 00:49:25','2026-08-16 07:49:25','2026-08-16 07:49:25'),(42,3,1,'warehouse_in','Omborga kirim qilindi','Ekstruder 37-32 — 1280 kg','{\"category\":\"feed\",\"name\":\"Ekstruder 37-32\",\"quantity\":1280,\"unit\":\"kg\"}','2026-08-16 11:33:55','2026-08-16 18:33:55','2026-08-16 18:33:55'),(43,3,1,'daily_report','Kunlik hisobot topshirildi','2026-08-14 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Ekstruder 37-32\",\"feeding_kg\":1280,\"wheat_bran_kg\":null,\"fertilizer_kg\":null}','2026-08-14 00:00:00','2026-08-16 18:34:19','2026-08-16 18:34:19'),(44,3,1,'main_report','Asosiy hisobot topshirildi','2026-08-14 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":\"18:40\",\"do_mg_l\":6,\"ph_level\":null,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":35,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":11},{\"fish_type\":\"tolstolob\",\"average_weight_g\":15},{\"fish_type\":\"oq_amur\",\"average_weight_g\":10},{\"fish_type\":\"targil_tolstolob\",\"average_weight_g\":15}]}','2026-08-14 00:00:00','2026-08-16 18:35:01','2026-08-16 18:35:01'),(45,3,1,'warehouse_in','Omborga kirim qilindi','batommaster — 10 dona','{\"category\":\"medicine\",\"name\":\"batommaster\",\"quantity\":10,\"unit\":\"piece\"}','2026-08-16 19:09:49','2026-08-17 02:09:49','2026-08-17 02:09:49'),(46,1,2,'daily_report','Kunlik hisobot topshirildi','2026-08-19 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"Ekstruder 35\",\"feeding_kg\":200}','2026-08-19 00:00:00','2026-08-20 00:14:32','2026-08-20 00:14:32'),(47,1,2,'main_report','Asosiy hisobot topshirildi','2026-08-19 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":null,\"do_mg_l\":null,\"ph_level\":null,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":null,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":16},{\"fish_type\":\"tolstolob\",\"average_weight_g\":7}]}','2026-08-19 00:00:00','2026-08-20 00:14:56','2026-08-20 00:14:56'),(48,1,2,'fish_sale','Baliq sotildi','150000 dona sazan, 2400.000 kg, o‘rtacha vazni 16 g','{\"fish_type\":\"sazan\",\"quantity\":150000,\"total_weight_kg\":\"2400.000\",\"average_weight_g\":16,\"amount\":\"56000.00\",\"notes\":null}','2026-08-19 00:00:00','2026-08-20 00:15:19','2026-08-20 00:15:19'),(49,1,2,'fish_loss','Baliq poteryasi qayd etildi','215000 dona tolstolob, 1505.000 kg, o‘rtacha vazni 7 g','{\"fish_type\":\"tolstolob\",\"quantity\":215000,\"total_weight_kg\":\"1505.000\",\"average_weight_g\":7,\"amount\":null,\"notes\":\"oldi\"}','2026-08-19 00:00:00','2026-08-20 00:31:22','2026-08-20 00:31:22'),(50,1,2,'pond_closure_requested','Havza yopish arizasi','Abduhamid Ishonch 7 havzasini yopmoqchi','{\"request_id\":1,\"closed_on\":\"2026-08-19\"}','2026-08-19 00:00:00','2026-08-20 00:31:54','2026-08-20 00:31:54'),(51,1,1,'fish_loss','Baliq poteryasi qayd etildi','49000 dona sazan (havza yopish)','{\"fish_type\":\"sazan\",\"quantity\":49000,\"total_weight_kg\":\"784.000\",\"closure\":true}','2026-08-19 00:00:00','2026-08-20 03:33:21','2026-08-20 03:33:21'),(52,1,1,'pond_closed','Havza yopildi','Ishonch 7 havzasi BOSS tasdiqlashi bilan yopildi','{\"mode\":\"approved\",\"closed_on\":\"2026-08-19\",\"request_id\":1}','2026-08-19 00:00:00','2026-08-20 03:33:21','2026-08-20 03:33:21'),(53,1,1,'pond_closure_approved','Havza yopish tasdiqlandi','Ishonch 7 havzasi BOSS tomonidan yopildi','{\"request_id\":1,\"closure_id\":1}','2026-08-19 20:33:21','2026-08-20 03:33:21','2026-08-20 03:33:21'),(54,3,3,'main_report','Asosiy hisobot topshirildi','2026-08-25 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":null,\"do_mg_l\":null,\"ph_level\":null,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":null,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":12},{\"fish_type\":\"tolstolob\",\"average_weight_g\":20},{\"fish_type\":\"oq_amur\",\"average_weight_g\":14.0999999999999996447286321199499070644378662109375},{\"fish_type\":\"targil_tolstolob\",\"average_weight_g\":21.5}]}','2026-08-25 00:00:00','2026-08-26 05:00:17','2026-08-26 05:00:17'),(55,3,3,'daily_report','Kunlik hisobot topshirildi','2026-08-22 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"yem 37\",\"feeding_kg\":1900}','2026-08-22 00:00:00','2026-08-26 05:02:16','2026-08-26 05:02:16'),(56,3,3,'main_report','Asosiy hisobot topshirildi','2026-09-04 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":null,\"do_mg_l\":null,\"ph_level\":null,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":null,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":\"35\"},{\"fish_type\":\"tolstolob\",\"average_weight_g\":\"28\"},{\"fish_type\":\"oq_amur\",\"average_weight_g\":\"34\"},{\"fish_type\":\"targil_tolstolob\",\"average_weight_g\":\"35\"}]}','2026-09-04 00:00:00','2026-09-05 04:07:04','2026-09-05 04:07:04'),(57,3,3,'main_report_updated','Asosiy hisobot o‘zgartirildi','2026-09-03 sanasi uchun asosiy hisobot yangilandi','{\"test_time\":null,\"do_mg_l\":null,\"ph_level\":null,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":null,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":\"30\"},{\"fish_type\":\"tolstolob\",\"average_weight_g\":\"35\"},{\"fish_type\":\"oq_amur\",\"average_weight_g\":\"34\"},{\"fish_type\":\"targil_tolstolob\",\"average_weight_g\":\"35\"}]}','2026-09-03 00:00:00','2026-09-05 04:07:54','2026-09-05 04:07:54'),(58,2,2,'fish_add','Baliq qo‘shildi','280000 dona sazan, 1120.000 kg, o‘rtacha vazni 4 g','{\"fish_type\":\"sazan\",\"quantity\":280000,\"total_weight_kg\":\"1120.000\",\"average_weight_g\":4,\"amount\":null,\"notes\":null}','2026-07-09 00:00:00','2026-09-10 00:11:09','2026-09-10 00:11:09'),(59,2,2,'fish_add','Baliq qo‘shildi','214000 dona tolstolob, 214.000 kg, o‘rtacha vazni 1 g','{\"fish_type\":\"tolstolob\",\"quantity\":214000,\"total_weight_kg\":\"214.000\",\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-07-09 00:00:00','2026-09-10 00:11:25','2026-09-10 00:11:25'),(60,2,2,'fish_add','Baliq qo‘shildi','180000 dona oq_amur, 180.000 kg, o‘rtacha vazni 1 g','{\"fish_type\":\"oq_amur\",\"quantity\":180000,\"total_weight_kg\":\"180.000\",\"average_weight_g\":1,\"amount\":null,\"notes\":null}','2026-07-09 00:00:00','2026-09-10 00:11:40','2026-09-10 00:11:40'),(61,2,2,'fish_add','Baliq qo‘shildi','45000 dona targil_tolstolob, 90.000 kg, o‘rtacha vazni 2 g','{\"fish_type\":\"targil_tolstolob\",\"quantity\":45000,\"total_weight_kg\":\"90.000\",\"average_weight_g\":2,\"amount\":null,\"notes\":null}','2026-07-17 00:00:00','2026-09-10 00:11:58','2026-09-10 00:11:58'),(62,2,2,'daily_report','Kunlik hisobot topshirildi','2026-08-21 sanasi uchun kunlik hisobot saqlandi','{\"feed_name\":\"granula\",\"feeding_kg\":8000,\"medicine_usage\":\"Brobiotik: 3 kg\"}','2026-08-21 00:00:00','2026-09-10 00:13:04','2026-09-10 00:13:04'),(63,2,2,'main_report','Asosiy hisobot topshirildi','2026-09-09 sanasi uchun asosiy hisobot saqlandi','{\"test_time\":null,\"do_mg_l\":null,\"ph_level\":null,\"ammonia_mg_l\":null,\"nitrite_mg_l\":null,\"water_transparency_cm\":null,\"fish_weights\":[{\"fish_type\":\"sazan\",\"average_weight_g\":\"40\"},{\"fish_type\":\"tolstolob\",\"average_weight_g\":\"40\"},{\"fish_type\":\"oq_amur\",\"average_weight_g\":\"45\"},{\"fish_type\":\"targil_tolstolob\",\"average_weight_g\":\"60\"}]}','2026-09-09 00:00:00','2026-09-10 00:13:27','2026-09-10 00:13:27'),(64,2,2,'fish_sale','Baliq sotildi','5000.000 kg sazan','{\"fish_type\":\"sazan\",\"quantity\":null,\"total_weight_kg\":\"5000.000\",\"average_weight_g\":null,\"unit_price\":45000,\"amount\":\"225000000.00\",\"notes\":null}','2026-09-09 00:00:00','2026-09-10 00:22:50','2026-09-10 00:22:50'),(65,2,2,'fish_loss','Baliq poteryasi qayd etildi','30 dona sazan, 1.221 kg, o‘rtacha vazni 41 g','{\"fish_type\":\"sazan\",\"quantity\":30,\"total_weight_kg\":\"1.221\",\"average_weight_g\":41,\"unit_price\":null,\"amount\":null,\"notes\":null}','2026-09-10 00:00:00','2026-09-11 04:48:42','2026-09-11 04:48:42');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_report_items`
--

DROP TABLE IF EXISTS `daily_report_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_report_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `daily_report_id` bigint(20) unsigned NOT NULL,
  `warehouse_item_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_report_items_daily_report_id_foreign` (`daily_report_id`),
  KEY `daily_report_items_warehouse_item_id_foreign` (`warehouse_item_id`),
  CONSTRAINT `daily_report_items_daily_report_id_foreign` FOREIGN KEY (`daily_report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_report_items_warehouse_item_id_foreign` FOREIGN KEY (`warehouse_item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_report_items`
--

LOCK TABLES `daily_report_items` WRITE;
/*!40000 ALTER TABLE `daily_report_items` DISABLE KEYS */;
INSERT INTO `daily_report_items` VALUES (1,6,1,10.00,'2026-08-10 04:55:48','2026-08-10 04:55:48'),(2,7,4,1280.00,'2026-08-16 18:34:19','2026-08-16 18:34:19'),(3,8,3,200.00,'2026-08-20 00:14:32','2026-08-20 00:14:32'),(4,9,6,1900.00,'2026-08-26 05:02:16','2026-08-26 05:02:16'),(5,10,7,8000.00,'2026-09-10 00:13:03','2026-09-10 00:13:03'),(6,10,2,3.00,'2026-09-10 00:13:03','2026-09-10 00:13:03');
/*!40000 ALTER TABLE `daily_report_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_reports`
--

DROP TABLE IF EXISTS `daily_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `report_date` date NOT NULL,
  `feed_name` varchar(255) DEFAULT NULL,
  `water_temp` decimal(5,2) DEFAULT NULL,
  `ph_level` decimal(4,2) DEFAULT NULL,
  `feeding_kg` decimal(10,2) DEFAULT NULL,
  `wheat_bran_kg` decimal(10,2) DEFAULT NULL,
  `fertilizer_kg` decimal(10,2) DEFAULT NULL,
  `mortality_count` int(10) unsigned NOT NULL DEFAULT 0,
  `fish_condition` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_reports_pond_id_report_date_unique` (`pond_id`,`report_date`),
  KEY `daily_reports_user_id_foreign` (`user_id`),
  CONSTRAINT `daily_reports_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_reports`
--

LOCK TABLES `daily_reports` WRITE;
/*!40000 ALTER TABLE `daily_reports` DISABLE KEYS */;
INSERT INTO `daily_reports` VALUES (1,1,2,'2026-07-30','Ekstruder 35',NULL,NULL,230.00,NULL,NULL,0,NULL,NULL,'2026-07-31 02:53:59','2026-07-31 02:53:59'),(2,1,1,'2026-08-01','Granula 35',NULL,NULL,1.00,NULL,4.00,0,NULL,NULL,'2026-08-01 21:59:40','2026-08-01 22:00:57'),(3,1,2,'2026-08-03','Granula 32',NULL,NULL,1.00,1.00,NULL,0,NULL,NULL,'2026-08-03 18:11:15','2026-08-03 18:11:15'),(5,3,1,'2026-08-05','Ekstruder 37-32',NULL,NULL,300.00,NULL,NULL,0,NULL,NULL,'2026-08-06 08:20:57','2026-08-06 08:20:57'),(6,3,1,'2026-08-09','yem',NULL,NULL,10.00,NULL,NULL,0,NULL,NULL,'2026-08-10 04:55:48','2026-08-10 04:55:48'),(7,3,1,'2026-08-14','Ekstruder 37-32',NULL,NULL,1280.00,NULL,NULL,0,NULL,NULL,'2026-08-16 18:34:19','2026-08-16 18:34:19'),(8,1,2,'2026-08-19','Ekstruder 35',NULL,NULL,200.00,NULL,NULL,0,NULL,NULL,'2026-08-20 00:14:32','2026-08-20 00:14:32'),(9,3,3,'2026-08-22','yem 37',NULL,NULL,1900.00,NULL,NULL,0,NULL,NULL,'2026-08-26 05:02:16','2026-08-26 05:02:16'),(10,2,2,'2026-08-21','granula',NULL,NULL,8000.00,NULL,NULL,0,NULL,NULL,'2026-09-10 00:13:03','2026-09-10 00:13:03');
/*!40000 ALTER TABLE `daily_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `category` varchar(64) NOT NULL DEFAULT 'other',
  `amount` decimal(14,2) NOT NULL,
  `quantity` decimal(14,3) DEFAULT NULL,
  `unit_price` decimal(14,2) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `warehouse_item_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_user_id_foreign` (`user_id`),
  KEY `expenses_farm_id_foreign` (`farm_id`),
  KEY `expenses_warehouse_item_id_foreign` (`warehouse_item_id`),
  CONSTRAINT `expenses_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_warehouse_item_id_foreign` FOREIGN KEY (`warehouse_item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,2,3,'feed',22000000.00,2000.000,11000.00,'2026-08-25','yem 37',NULL,6,'2026-08-26 05:01:54','2026-08-26 05:01:55'),(2,1,2,'feed',104000000.00,8000.000,13000.00,'2026-07-18','granula',NULL,7,'2026-09-10 00:12:35','2026-09-10 00:12:35');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farm_investments`
--

DROP TABLE IF EXISTS `farm_investments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_investments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `investor` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `invested_at` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_investments_farm_id_foreign` (`farm_id`),
  KEY `farm_investments_user_id_foreign` (`user_id`),
  CONSTRAINT `farm_investments_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `farm_investments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farm_investments`
--

LOCK TABLES `farm_investments` WRITE;
/*!40000 ALTER TABLE `farm_investments` DISABLE KEYS */;
/*!40000 ALTER TABLE `farm_investments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farm_worker`
--

DROP TABLE IF EXISTS `farm_worker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_worker` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `hired_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `farm_worker_farm_id_user_id_unique` (`farm_id`,`user_id`),
  KEY `farm_worker_user_id_foreign` (`user_id`),
  CONSTRAINT `farm_worker_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `farm_worker_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farm_worker`
--

LOCK TABLES `farm_worker` WRITE;
/*!40000 ALTER TABLE `farm_worker` DISABLE KEYS */;
INSERT INTO `farm_worker` VALUES (1,1,4,NULL,NULL,'2026-08-09 23:27:33','2026-08-09 23:27:33');
/*!40000 ALTER TABLE `farm_worker` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farms`
--

DROP TABLE IF EXISTS `farms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `region` varchar(255) NOT NULL,
  `director_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farms_director_id_foreign` (`director_id`),
  KEY `farms_created_by_foreign` (`created_by`),
  CONSTRAINT `farms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `farms_director_id_foreign` FOREIGN KEY (`director_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farms`
--

LOCK TABLES `farms` WRITE;
/*!40000 ALTER TABLE `farms` DISABLE KEYS */;
INSERT INTO `farms` VALUES (1,'Hojalik','Sirdaryo',2,2,'active',NULL,'2026-08-11 21:12:04','2026-08-11 21:12:04'),(2,'Hojalik','Sirdaryo',3,1,'active',NULL,'2026-08-11 21:12:04','2026-08-11 21:12:04');
/*!40000 ALTER TABLE `farms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feed_products`
--

DROP TABLE IF EXISTS `feed_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `price_per_kg` decimal(14,2) NOT NULL,
  `category` varchar(20) NOT NULL DEFAULT 'feed',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feed_products_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feed_products`
--

LOCK TABLES `feed_products` WRITE;
/*!40000 ALTER TABLE `feed_products` DISABLE KEYS */;
INSERT INTO `feed_products` VALUES (1,'Бугдой месний',3800.00,'feed',1,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(2,'Макка',4000.00,'feed',2,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(3,'Соя',8620.00,'feed',3,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(4,'Семичка киммати',3940.00,'feed',4,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(5,'Семичка Арзон',2762.00,'feed',5,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(6,'МЯС',2700.00,'feed',6,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(7,'Рыба',7100.00,'feed',7,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(8,'Мой',18000.00,'feed',8,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(9,'Кепак',3520.00,'feed',9,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(10,'Арпа',3700.00,'feed',10,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(11,'Мош',4700.00,'feed',11,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(12,'Рапс',5000.00,'feed',12,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(13,'Мучка',3000.00,'feed',13,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(14,'Кунжара',6600.00,'feed',14,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(15,'Сечка',3500.00,'feed',15,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(16,'Ун',4300.00,'feed',16,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(17,'Хлапковий пратейн',14760.00,'feed',17,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(18,'Премикс Рыба',53780.00,'vitamin',18,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(19,'Shakar',12000.00,'feed',19,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(20,'Монокалций',14200.00,'vitamin',20,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(21,'Трикалций',5090.00,'vitamin',21,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(22,'Охак',400.00,'vitamin',22,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(23,'Соль',950.00,'vitamin',23,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(24,'Премикх Старт',39200.00,'vitamin',24,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(25,'Премикх Рост',34496.00,'vitamin',25,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(26,'Премикх Финиш',33600.00,'vitamin',26,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(27,'Sammix Ctart',35500.00,'vitamin',27,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(28,'Sammix Rost',30000.00,'vitamin',28,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(29,'Sammix Finish',31000.00,'vitamin',29,'2026-08-20 17:12:02','2026-08-20 17:12:02'),(30,'Подкислитель Sal-tech',40000.00,'vitamin',30,'2026-08-20 17:12:02','2026-08-20 17:12:02');
/*!40000 ALTER TABLE `feed_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feed_ration_items`
--

DROP TABLE IF EXISTS `feed_ration_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_ration_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `feed_ration_id` bigint(20) unsigned NOT NULL,
  `feed_product_id` bigint(20) unsigned NOT NULL,
  `qty_per_100kg` decimal(10,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feed_ration_items_feed_ration_id_feed_product_id_unique` (`feed_ration_id`,`feed_product_id`),
  KEY `feed_ration_items_feed_product_id_foreign` (`feed_product_id`),
  CONSTRAINT `feed_ration_items_feed_product_id_foreign` FOREIGN KEY (`feed_product_id`) REFERENCES `feed_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feed_ration_items_feed_ration_id_foreign` FOREIGN KEY (`feed_ration_id`) REFERENCES `feed_rations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feed_ration_items`
--

LOCK TABLES `feed_ration_items` WRITE;
/*!40000 ALTER TABLE `feed_ration_items` DISABLE KEYS */;
INSERT INTO `feed_ration_items` VALUES (4,2,4,56.000,'2026-08-20 22:01:48','2026-08-20 22:01:48'),(5,2,9,38.000,'2026-08-20 22:01:48','2026-08-20 22:01:48'),(6,2,19,4.000,'2026-08-20 22:01:48','2026-08-20 22:01:48'),(7,2,20,1.000,'2026-08-20 22:01:48','2026-08-20 22:01:48'),(8,2,18,1.000,'2026-08-20 22:01:48','2026-08-20 22:01:48');
/*!40000 ALTER TABLE `feed_ration_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feed_rations`
--

DROP TABLE IF EXISTS `feed_rations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_rations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `kop_narx` decimal(14,2) DEFAULT NULL,
  `sale_price_per_kg` decimal(14,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feed_rations`
--

LOCK TABLES `feed_rations` WRITE;
/*!40000 ALTER TABLE `feed_rations` DISABLE KEYS */;
INSERT INTO `feed_rations` VALUES (2,'511',70.00,6000.00,NULL,0,'2026-08-20 22:01:48','2026-08-20 22:01:48');
/*!40000 ALTER TABLE `feed_rations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feed_types`
--

DROP TABLE IF EXISTS `feed_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feed_types_farm_id_name_unique` (`farm_id`,`name`),
  KEY `feed_types_created_by_foreign` (`created_by`),
  CONSTRAINT `feed_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `feed_types_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feed_types`
--

LOCK TABLES `feed_types` WRITE;
/*!40000 ALTER TABLE `feed_types` DISABLE KEYS */;
INSERT INTO `feed_types` VALUES (1,1,2,'Ekstruder 35','2026-07-31 02:53:59','2026-07-31 02:53:59'),(2,1,1,'Granula 32','2026-08-01 21:59:40','2026-08-01 21:59:40'),(3,1,1,'Granula 35','2026-08-01 22:00:57','2026-08-01 22:00:57'),(4,2,1,'Ekstruder 37-32','2026-08-06 04:56:13','2026-08-06 04:56:13'),(5,2,3,'yem 37','2026-08-26 05:01:55','2026-08-26 05:01:55'),(6,1,2,'granula','2026-09-10 00:12:35','2026-09-10 00:12:35');
/*!40000 ALTER TABLE `feed_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `finance_sections`
--

DROP TABLE IF EXISTS `finance_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finance_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finance_sections_farm_id_name_unique` (`farm_id`,`name`),
  KEY `finance_sections_created_by_foreign` (`created_by`),
  CONSTRAINT `finance_sections_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_sections_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_sections`
--

LOCK TABLES `finance_sections` WRITE;
/*!40000 ALTER TABLE `finance_sections` DISABLE KEYS */;
INSERT INTO `finance_sections` VALUES (1,2,'Elektr',3,'2026-09-04 22:04:22','2026-09-04 22:04:22');
/*!40000 ALTER TABLE `finance_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fish_operations`
--

DROP TABLE IF EXISTS `fish_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fish_operations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('add','sale','loss') NOT NULL,
  `fish_type` varchar(255) DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `average_weight_kg` decimal(10,3) DEFAULT NULL,
  `total_weight_kg` decimal(12,3) DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `operated_at` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fish_operations_pond_id_foreign` (`pond_id`),
  KEY `fish_operations_user_id_foreign` (`user_id`),
  CONSTRAINT `fish_operations_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fish_operations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fish_operations`
--

LOCK TABLES `fish_operations` WRITE;
/*!40000 ALTER TABLE `fish_operations` DISABLE KEYS */;
INSERT INTO `fish_operations` VALUES (1,1,1,'add','sazan',200000,0.003,NULL,NULL,'Baliq qo‘shish',NULL,'2026-07-24','2026-07-30 07:09:30','2026-07-31 18:15:17'),(2,1,1,'add','tolstolob',214000,0.001,NULL,NULL,'Baliq qo‘shish',NULL,'2026-07-31','2026-07-31 18:15:53','2026-07-31 18:15:53'),(7,3,1,'add','tolstolob',210000,0.001,210.000,NULL,'Baliq qo‘shish',NULL,'2026-07-07','2026-08-06 04:58:07','2026-08-06 04:58:07'),(8,3,1,'add','oq_amur',160000,0.001,160.000,NULL,'Baliq qo‘shish',NULL,'2026-07-10','2026-08-06 05:27:32','2026-08-06 05:27:32'),(9,3,1,'add','sazan',7500,0.004,30.000,NULL,'Baliq qo‘shish',NULL,'2026-07-11','2026-08-06 05:28:09','2026-08-06 05:28:09'),(10,3,1,'add','targil_tolstolob',45000,0.002,90.000,NULL,'Baliq qo‘shish',NULL,'2026-07-13','2026-08-06 05:28:50','2026-08-06 05:28:50'),(11,3,1,'add','sazan',70000,0.006,420.000,NULL,'Baliq qo‘shish',NULL,'2026-07-22','2026-08-06 05:35:15','2026-08-06 05:35:15'),(12,3,1,'add','sazan',200000,0.003,600.000,NULL,'Baliq qo‘shish',NULL,'2026-07-24','2026-08-06 05:35:33','2026-08-06 05:35:33'),(13,3,1,'add','sazan',12000,0.010,120.000,NULL,'Baliq qo‘shish',NULL,'2026-08-25','2026-08-06 05:37:06','2026-08-06 05:37:06'),(14,3,1,'add','oq_amur',24000,0.001,24.000,NULL,'Baliq qo‘shish',NULL,'2026-07-28','2026-08-06 08:08:44','2026-08-06 08:08:44'),(16,1,2,'sale','sazan',150000,0.016,2400.000,56000.00,'Baliq sotish',NULL,'2026-08-19','2026-08-20 00:15:19','2026-08-20 00:15:19'),(17,1,2,'loss','tolstolob',215000,0.007,1505.000,NULL,'Poterya','oldi','2026-08-19','2026-08-20 00:31:22','2026-08-20 00:31:22'),(18,1,1,'loss','sazan',49000,0.016,784.000,NULL,'Havza yopish','Havza yopish — qoldiq baliq yo‘qotish deb qayd etildi','2026-08-19','2026-08-20 03:33:21','2026-08-20 03:33:21'),(19,2,2,'add','sazan',280000,0.004,1120.000,NULL,'Baliq qo‘shish',NULL,'2026-07-09','2026-09-10 00:11:09','2026-09-10 00:11:09'),(20,2,2,'add','tolstolob',214000,0.001,214.000,NULL,'Baliq qo‘shish',NULL,'2026-07-09','2026-09-10 00:11:25','2026-09-10 00:11:25'),(21,2,2,'add','oq_amur',180000,0.001,180.000,NULL,'Baliq qo‘shish',NULL,'2026-07-09','2026-09-10 00:11:40','2026-09-10 00:11:40'),(22,2,2,'add','targil_tolstolob',45000,0.002,90.000,NULL,'Baliq qo‘shish',NULL,'2026-07-17','2026-09-10 00:11:58','2026-09-10 00:11:58'),(23,2,2,'sale','sazan',125000,0.040,5000.000,225000000.00,'Baliq sotish',NULL,'2026-09-09','2026-09-10 00:22:50','2026-09-10 00:22:50'),(24,2,2,'loss','sazan',30,0.041,1.221,NULL,'Yo‘qotish',NULL,'2026-09-10','2026-09-11 04:48:42','2026-09-11 04:48:42');
/*!40000 ALTER TABLE `fish_operations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `main_report_fish_weights`
--

DROP TABLE IF EXISTS `main_report_fish_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `main_report_fish_weights` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `main_report_id` bigint(20) unsigned NOT NULL,
  `fish_type` varchar(255) NOT NULL,
  `average_weight_kg` decimal(10,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `main_report_fish_weights_main_report_id_fish_type_unique` (`main_report_id`,`fish_type`),
  CONSTRAINT `main_report_fish_weights_main_report_id_foreign` FOREIGN KEY (`main_report_id`) REFERENCES `main_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `main_report_fish_weights`
--

LOCK TABLES `main_report_fish_weights` WRITE;
/*!40000 ALTER TABLE `main_report_fish_weights` DISABLE KEYS */;
INSERT INTO `main_report_fish_weights` VALUES (2,1,'sazan',0.007,'2026-07-31 18:14:55','2026-07-31 18:14:55'),(3,2,'sazan',0.007,'2026-07-31 18:16:48','2026-07-31 18:16:48'),(4,2,'tolstolob',0.002,'2026-07-31 18:16:48','2026-07-31 18:16:48'),(5,3,'sazan',0.008,'2026-08-01 22:00:20','2026-08-01 22:00:20'),(6,3,'tolstolob',0.003,'2026-08-01 22:00:20','2026-08-01 22:00:20'),(7,4,'sazan',0.007,'2026-08-06 08:18:35','2026-08-06 08:18:35'),(8,4,'tolstolob',0.001,'2026-08-06 08:18:35','2026-08-06 08:18:35'),(9,4,'oq_amur',0.008,'2026-08-06 08:18:35','2026-08-06 08:18:35'),(10,4,'targil_tolstolob',0.002,'2026-08-06 08:18:35','2026-08-06 08:18:35'),(11,5,'sazan',0.011,'2026-08-16 18:35:01','2026-08-16 18:35:01'),(12,5,'tolstolob',0.015,'2026-08-16 18:35:01','2026-08-16 18:35:01'),(13,5,'oq_amur',0.010,'2026-08-16 18:35:01','2026-08-16 18:35:01'),(14,5,'targil_tolstolob',0.015,'2026-08-16 18:35:01','2026-08-16 18:35:01'),(15,6,'sazan',0.016,'2026-08-20 00:14:56','2026-08-20 00:14:56'),(16,6,'tolstolob',0.007,'2026-08-20 00:14:56','2026-08-20 00:14:56'),(17,7,'sazan',0.012,'2026-08-26 05:00:17','2026-08-26 05:00:17'),(18,7,'tolstolob',0.020,'2026-08-26 05:00:17','2026-08-26 05:00:17'),(19,7,'oq_amur',0.014,'2026-08-26 05:00:17','2026-08-26 05:00:17'),(20,7,'targil_tolstolob',0.022,'2026-08-26 05:00:17','2026-08-26 05:00:17'),(25,8,'sazan',0.030,'2026-09-05 04:07:54','2026-09-05 04:07:54'),(26,8,'tolstolob',0.035,'2026-09-05 04:07:54','2026-09-05 04:07:54'),(27,8,'oq_amur',0.034,'2026-09-05 04:07:54','2026-09-05 04:07:54'),(28,8,'targil_tolstolob',0.035,'2026-09-05 04:07:54','2026-09-05 04:07:54'),(29,9,'sazan',0.040,'2026-09-10 00:13:27','2026-09-10 00:13:27'),(30,9,'tolstolob',0.040,'2026-09-10 00:13:27','2026-09-10 00:13:27'),(31,9,'oq_amur',0.045,'2026-09-10 00:13:27','2026-09-10 00:13:27'),(32,9,'targil_tolstolob',0.060,'2026-09-10 00:13:27','2026-09-10 00:13:27');
/*!40000 ALTER TABLE `main_report_fish_weights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `main_reports`
--

DROP TABLE IF EXISTS `main_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `main_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `report_date` date NOT NULL,
  `test_time` time DEFAULT NULL,
  `do_mg_l` decimal(8,3) DEFAULT NULL,
  `ph_level` decimal(4,2) DEFAULT NULL,
  `ammonia_mg_l` decimal(8,3) DEFAULT NULL,
  `nitrite_mg_l` decimal(8,3) DEFAULT NULL,
  `water_transparency_cm` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `main_reports_pond_id_report_date_unique` (`pond_id`,`report_date`),
  KEY `main_reports_user_id_foreign` (`user_id`),
  CONSTRAINT `main_reports_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `main_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `main_reports`
--

LOCK TABLES `main_reports` WRITE;
/*!40000 ALTER TABLE `main_reports` DISABLE KEYS */;
INSERT INTO `main_reports` VALUES (1,1,1,'2026-07-29','12:30:00',8.000,7.00,NULL,NULL,30.00,'2026-07-31 02:50:49','2026-07-31 18:14:55'),(2,1,1,'2026-07-31',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-31 18:16:48','2026-07-31 18:16:48'),(3,1,1,'2026-08-01','12:39:00',5.000,8.00,NULL,NULL,NULL,'2026-08-01 22:00:20','2026-08-01 22:00:20'),(4,3,1,'2026-08-05','18:00:00',NULL,8.20,NULL,NULL,35.00,'2026-08-06 08:18:35','2026-08-06 08:18:35'),(5,3,1,'2026-08-14','18:40:00',6.000,NULL,NULL,NULL,35.00,'2026-08-16 18:35:01','2026-08-16 18:35:01'),(6,1,2,'2026-08-19',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-20 00:14:56','2026-08-20 00:14:56'),(7,3,3,'2026-08-25',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-26 05:00:17','2026-08-26 05:00:17'),(8,3,3,'2026-09-03',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-05 04:07:04','2026-09-05 04:07:54'),(9,2,2,'2026-09-09',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-10 00:13:27','2026-09-10 00:13:27');
/*!40000 ALTER TABLE `main_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_28_171334_create_personal_access_tokens_table',1),(5,'2026_07_28_180000_add_role_to_users_table',1),(6,'2026_07_28_180001_create_ponds_table',1),(7,'2026_07_28_180002_create_pond_worker_table',1),(8,'2026_07_28_180003_create_tasks_table',1),(9,'2026_07_28_180004_create_daily_reports_table',1),(10,'2026_07_28_180005_create_worker_reports_table',1),(11,'2026_07_28_180006_create_expenses_table',1),(12,'2026_07_29_100000_add_password_plain_to_users_table',1),(13,'2026_07_29_110000_add_fish_stock_and_operations',1),(14,'2026_07_29_120000_add_fish_details_and_activity_logs',1),(15,'2026_07_29_121000_normalize_fish_type_names',1),(16,'2026_07_29_130000_add_feed_inputs_to_daily_reports',1),(17,'2026_07_29_140000_merge_fertilizer_into_single_amount',1),(18,'2026_07_29_150000_create_main_reports_tables',2),(19,'2026_07_31_220000_add_access_expires_at_to_users_table',3),(20,'2026_07_31_230000_add_total_weight_kg_to_fish_operations_table',4),(21,'2026_08_06_100000_add_coordinates_to_ponds_table',5),(22,'2026_08_09_100000_create_warehouse_tables',6),(23,'2026_08_09_110000_add_photo_to_warehouse_items',7),(24,'2026_08_10_100000_create_branches_and_migrate',8),(25,'2026_08_11_000000_revert_branches_to_pond_scope',9),(26,'2026_08_11_120000_create_farms_and_scope',10),(27,'2026_08_15_100000_add_pond_closures',11),(28,'2026_08_19_100000_expand_finance_tables',12),(29,'2026_08_19_120000_create_pond_closure_requests_table',13),(30,'2026_08_20_100000_create_feed_rations_tables',14),(31,'2026_08_20_110000_add_sale_price_to_feed_rations',15),(32,'2026_08_24_100000_add_warehouse_item_id_to_expenses_table',16),(33,'2026_08_26_100000_add_director_id_to_users_table',17),(34,'2026_08_28_100000_fix_expenses_category_enum',18),(35,'2026_08_31_100000_simplify_finance_and_custom_sections',19),(36,'2026_09_04_100000_create_owner_weekly_planners_table',20),(37,'2026_09_05_100000_create_owner_planner_extensions_tables',21);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owner_daily_extras`
--

DROP TABLE IF EXISTS `owner_daily_extras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owner_daily_extras` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_daily_extras_user_id_unique` (`user_id`),
  CONSTRAINT `owner_daily_extras_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owner_daily_extras`
--

LOCK TABLES `owner_daily_extras` WRITE;
/*!40000 ALTER TABLE `owner_daily_extras` DISABLE KEYS */;
INSERT INTO `owner_daily_extras` VALUES (1,1,'{\"dates\":[]}','2026-09-06 17:34:44','2026-09-06 17:34:44');
/*!40000 ALTER TABLE `owner_daily_extras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owner_inbox_tasks`
--

DROP TABLE IF EXISTS `owner_inbox_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owner_inbox_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `text` text NOT NULL,
  `color` varchar(16) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `owner_inbox_tasks_user_id_completed_at_index` (`user_id`,`completed_at`),
  KEY `owner_inbox_tasks_completed_at_index` (`completed_at`),
  CONSTRAINT `owner_inbox_tasks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owner_inbox_tasks`
--

LOCK TABLES `owner_inbox_tasks` WRITE;
/*!40000 ALTER TABLE `owner_inbox_tasks` DISABLE KEYS */;
INSERT INTO `owner_inbox_tasks` VALUES (1,1,'вппуупкпук','sky',1,'2026-09-07 17:34:11','2026-09-07 17:34:06','2026-09-07 17:34:11');
/*!40000 ALTER TABLE `owner_inbox_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owner_weekly_planners`
--

DROP TABLE IF EXISTS `owner_weekly_planners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owner_weekly_planners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_weekly_planners_user_id_unique` (`user_id`),
  CONSTRAINT `owner_weekly_planners_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owner_weekly_planners`
--

LOCK TABLES `owner_weekly_planners` WRITE;
/*!40000 ALTER TABLE `owner_weekly_planners` DISABLE KEYS */;
INSERT INTO `owner_weekly_planners` VALUES (1,1,'{\"days\":{\"mon\":{\"before_bomdod\":[{\"text\":\"\",\"color\":null}],\"after_bomdod\":[{\"text\":\"hhhh\",\"color\":\"red\"}],\"before_peshin\":[{\"text\":\"ga\",\"color\":\"lilac\"}],\"after_peshin\":[{\"text\":\"ddd\",\"color\":\"ocean\"}],\"before_asr\":[{\"text\":\"ss\",\"color\":\"sky\"}],\"after_asr\":[{\"text\":\"qee\",\"color\":\"mint\"}],\"before_shom\":[{\"text\":\"qee\",\"color\":\"sand\"}],\"after_shom\":[{\"text\":\"eeqe\",\"color\":\"lilac\"}],\"before_xufton\":[{\"text\":\"ffefw\",\"color\":\"ocean\"}],\"after_xufton\":[{\"text\":\"fwe\",\"color\":\"sky\"},{\"text\":\"\",\"color\":null}]},\"tue\":{\"before_bomdod\":[{\"text\":\"\",\"color\":null},{\"text\":\"\",\"color\":null}],\"after_bomdod\":[{\"text\":\"\",\"color\":null}],\"before_peshin\":[{\"text\":\"\",\"color\":null}],\"after_peshin\":[{\"text\":\"\",\"color\":null}],\"before_asr\":[{\"text\":\"\",\"color\":null}],\"after_asr\":[{\"text\":\"\",\"color\":null}],\"before_shom\":[{\"text\":\"\",\"color\":null}],\"after_shom\":[{\"text\":\"\",\"color\":null}],\"before_xufton\":[{\"text\":\"\",\"color\":null}],\"after_xufton\":[{\"text\":\"\",\"color\":null},{\"text\":\"\",\"color\":null}]},\"wed\":{\"before_bomdod\":[{\"text\":\"\",\"color\":null}],\"after_bomdod\":[{\"text\":\"\",\"color\":null}],\"before_peshin\":[{\"text\":\"\",\"color\":null},{\"text\":\"\",\"color\":null}],\"after_peshin\":[{\"text\":\"\",\"color\":null}],\"before_asr\":[{\"text\":\"\",\"color\":null}],\"after_asr\":[{\"text\":\"\",\"color\":null}],\"before_shom\":[{\"text\":\"\",\"color\":null}],\"after_shom\":[{\"text\":\"\",\"color\":null}],\"before_xufton\":[{\"text\":\"\",\"color\":null}],\"after_xufton\":[{\"text\":\"\",\"color\":null},{\"text\":\"\",\"color\":null}]},\"thu\":{\"before_bomdod\":[{\"text\":\"\",\"color\":null}],\"after_bomdod\":[{\"text\":\"\",\"color\":null}],\"before_peshin\":[{\"text\":\"\",\"color\":null}],\"after_peshin\":[{\"text\":\"\",\"color\":null}],\"before_asr\":[{\"text\":\"\",\"color\":null}],\"after_asr\":[{\"text\":\"\",\"color\":null}],\"before_shom\":[{\"text\":\"\",\"color\":null}],\"after_shom\":[{\"text\":\"\",\"color\":null}],\"before_xufton\":[{\"text\":\"\",\"color\":null}],\"after_xufton\":[{\"text\":\"\",\"color\":null},{\"text\":\"\",\"color\":null}]},\"fri\":{\"before_bomdod\":[{\"text\":\"\",\"color\":null}],\"after_bomdod\":[{\"text\":\"\",\"color\":null}],\"before_peshin\":[{\"text\":\"\",\"color\":null}],\"after_peshin\":[{\"text\":\"\",\"color\":null}],\"before_asr\":[{\"text\":\"\",\"color\":null}],\"after_asr\":[{\"text\":\"\",\"color\":null}],\"before_shom\":[{\"text\":\"\",\"color\":null}],\"after_shom\":[{\"text\":\"\",\"color\":null}],\"before_xufton\":[{\"text\":\"\",\"color\":null}],\"after_xufton\":[{\"text\":\"\",\"color\":null},{\"text\":\"\",\"color\":null}]},\"sat\":{\"before_bomdod\":[{\"text\":\"\",\"color\":null}],\"after_bomdod\":[{\"text\":\"\",\"color\":null}],\"before_peshin\":[{\"text\":\"\",\"color\":null}],\"after_peshin\":[{\"text\":\"\",\"color\":null}],\"before_asr\":[{\"text\":\"\",\"color\":null}],\"after_asr\":[{\"text\":\"\",\"color\":null}],\"before_shom\":[{\"text\":\"\",\"color\":null}],\"after_shom\":[{\"text\":\"\",\"color\":null}],\"before_xufton\":[{\"text\":\"\",\"color\":null}],\"after_xufton\":[{\"text\":\"\",\"color\":null},{\"text\":\"\",\"color\":null}]},\"sun\":{\"before_bomdod\":[{\"text\":\"\",\"color\":null}],\"after_bomdod\":[{\"text\":\"\",\"color\":null}],\"before_peshin\":[{\"text\":\"\",\"color\":null}],\"after_peshin\":[{\"text\":\"\",\"color\":null}],\"before_asr\":[{\"text\":\"\",\"color\":null}],\"after_asr\":[{\"text\":\"\",\"color\":null}],\"before_shom\":[{\"text\":\"\",\"color\":null}],\"after_shom\":[{\"text\":\"\",\"color\":null}],\"before_xufton\":[{\"text\":\"\",\"color\":null}],\"after_xufton\":[{\"text\":\"\",\"color\":null}]}}}','2026-09-05 05:05:48','2026-09-05 05:51:13');
/*!40000 ALTER TABLE `owner_weekly_planners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owner_yearly_planners`
--

DROP TABLE IF EXISTS `owner_yearly_planners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owner_yearly_planners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_yearly_planners_user_id_unique` (`user_id`),
  CONSTRAINT `owner_yearly_planners_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owner_yearly_planners`
--

LOCK TABLES `owner_yearly_planners` WRITE;
/*!40000 ALTER TABLE `owner_yearly_planners` DISABLE KEYS */;
INSERT INTO `owner_yearly_planners` VALUES (1,1,'{\"dates\":{\"2026-08-01\":[{\"text\":\"hh\",\"color\":\"sky\"}],\"2026-09-05\":[{\"text\":\"dd\",\"color\":\"mint\"},{\"text\":\"\",\"color\":null}],\"2026-09-07\":[{\"text\":\"jhbhbjh\",\"color\":\"mint\"}],\"2026-09-08\":[{\"text\":\"Raxim ak 9252 ga qong\'iroq qilish\",\"color\":\"sky\"}]}}','2026-09-06 17:34:44','2026-09-06 18:48:45');
/*!40000 ALTER TABLE `owner_yearly_planners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (21,'App\\Models\\User',5,'owner-spa','a8a7d74eca488eacfb1d4dce70d2a07badf4cd40a8344ad497c8f25509977888','[\"*\"]',NULL,NULL,'2026-08-13 19:45:19','2026-08-13 19:45:19'),(22,'App\\Models\\User',5,'owner-spa','d3d3fe21584b6549622c537010908df41b05da67a38c4adda533b2af6345363b','[\"*\"]',NULL,NULL,'2026-08-13 19:45:55','2026-08-13 19:45:55'),(23,'App\\Models\\User',1,'owner-spa','7fb1bcc4a5bf4cc6e948dcc8a2ea5c2ee0daee67aedb8acc424345506fa2bcd0','[\"*\"]','2026-08-15 23:02:57',NULL,'2026-08-15 00:01:35','2026-08-15 23:02:57'),(24,'App\\Models\\User',2,'spa','f23cb7619cd98fd6419f6d716973e0cb0cb18d7aba8fe3e986a33e1cd58996c7','[\"*\"]',NULL,NULL,'2026-08-16 05:36:10','2026-08-16 05:36:10'),(25,'App\\Models\\User',2,'spa','a4d9069234070e0585f1360de3aa7b94d702740ca5ff7ee7924fd2e834477d37','[\"*\"]','2026-08-16 05:36:34',NULL,'2026-08-16 05:36:34','2026-08-16 05:36:34'),(26,'App\\Models\\User',2,'spa','e3c54528fd5d5e5d9f4269dddde04a32d1cc67fb3fd8a40d6dbd0306362d59f5','[\"*\"]','2026-08-16 05:36:34',NULL,'2026-08-16 05:36:34','2026-08-16 05:36:34'),(30,'App\\Models\\User',2,'spa','8859e3989041b4884e07e891619ecaf55c9d101669677769cc05ec35083166de','[\"*\"]','2026-08-19 22:03:34',NULL,'2026-08-17 03:56:51','2026-08-19 22:03:34'),(34,'App\\Models\\User',1,'owner-spa','090e72be015153080fa7b9d72f8859eb64342cffceca5bf6895db211bba65ee2','[\"*\"]','2026-08-20 03:32:15',NULL,'2026-08-20 00:33:48','2026-08-20 03:32:15'),(35,'App\\Models\\User',2,'spa','51c816d6c36d4d7d4ab7456af752e3d8353a05e6668547fc10d629396006ade9','[\"*\"]','2026-08-20 03:32:34',NULL,'2026-08-20 03:31:53','2026-08-20 03:32:34'),(39,'App\\Models\\User',1,'owner-spa','9bd7ef7a39954ea314d09a8a7e72179ede57d6c32e36c25e42fca873f7f5611b','[\"*\"]','2026-08-29 16:54:20',NULL,'2026-08-26 22:14:31','2026-08-29 16:54:20'),(46,'App\\Models\\User',3,'spa','ecbdf2deb33e964842128706e4dc1032085dce7cd987c1ec1498d5f43741984f','[\"*\"]','2026-09-08 00:41:38','2026-10-07 17:39:46','2026-09-07 17:39:46','2026-09-08 00:41:38'),(48,'App\\Models\\User',2,'spa','a51cf15fa2ba677d9e5d79cb864b81d6306b8d7c1767c29efce703afa5e32437','[\"*\"]','2026-09-11 04:48:42','2026-10-09 21:06:38','2026-09-09 21:06:38','2026-09-11 04:48:42');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pond_closure_requests`
--

DROP TABLE IF EXISTS `pond_closure_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pond_closure_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `closed_on` date NOT NULL,
  `confirmations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`confirmations`)),
  `notes` text DEFAULT NULL,
  `analysis_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`analysis_snapshot`)),
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `pond_closure_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pond_closure_requests_pond_id_foreign` (`pond_id`),
  KEY `pond_closure_requests_requested_by_foreign` (`requested_by`),
  KEY `pond_closure_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `pond_closure_requests_pond_closure_id_foreign` (`pond_closure_id`),
  CONSTRAINT `pond_closure_requests_pond_closure_id_foreign` FOREIGN KEY (`pond_closure_id`) REFERENCES `pond_closures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pond_closure_requests_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pond_closure_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pond_closure_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pond_closure_requests`
--

LOCK TABLES `pond_closure_requests` WRITE;
/*!40000 ALTER TABLE `pond_closure_requests` DISABLE KEYS */;
INSERT INTO `pond_closure_requests` VALUES (1,1,2,'approved','2026-08-19','{\"all_finished\":true,\"no_fish_left\":true,\"sales_recorded\":true}','yaxshimas','{\"fish_types\":[{\"fish_type\":\"sazan\",\"label\":\"Sazan\",\"added\":200000,\"sold\":150000,\"loss\":0,\"remaining\":50000,\"sale_ratio_percent\":75,\"loss_free\":false,\"average_weight_kg\":0.01600000000000000033306690738754696212708950042724609375,\"remaining_mass_kg\":800},{\"fish_type\":\"tolstolob\",\"label\":\"Tolstolob\",\"added\":214000,\"sold\":0,\"loss\":215000,\"remaining\":0,\"sale_ratio_percent\":0,\"loss_free\":false,\"average_weight_kg\":0.007000000000000000145716771982051795930601656436920166015625,\"remaining_mass_kg\":0}],\"overall_loss_free\":false,\"total_remaining\":50000}',1,'2026-08-20 03:33:21',NULL,1,'2026-08-20 00:31:54','2026-08-20 03:33:21');
/*!40000 ALTER TABLE `pond_closure_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pond_closures`
--

DROP TABLE IF EXISTS `pond_closures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pond_closures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `mode` varchar(20) NOT NULL,
  `closed_on` date NOT NULL,
  `estimated_stock` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`estimated_stock`)),
  `actual_stock` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`actual_stock`)),
  `adjustments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`adjustments`)),
  `summary_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`summary_snapshot`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pond_closures_pond_id_foreign` (`pond_id`),
  KEY `pond_closures_user_id_foreign` (`user_id`),
  CONSTRAINT `pond_closures_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pond_closures_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pond_closures`
--

LOCK TABLES `pond_closures` WRITE;
/*!40000 ALTER TABLE `pond_closures` DISABLE KEYS */;
INSERT INTO `pond_closures` VALUES (1,1,1,'approved','2026-08-19','[{\"fish_type\":\"sazan\",\"label\":\"Sazan\",\"count\":50000,\"average_weight_kg\":0.01600000000000000033306690738754696212708950042724609375,\"mass_kg\":800}]',NULL,'{\"losses\":[{\"fish_type\":\"sazan\",\"quantity\":50000,\"operation_id\":18,\"reason\":\"remaining_at_closure\",\"added\":200000,\"sold\":150000,\"loss\":0}],\"surplus\":[],\"loss_free_types\":[]}','{\"pond\":{\"id\":1,\"name\":\"Ishonch 7\",\"region\":\"Sirdaryo\",\"area_m2\":\"6.00\",\"current_stock\":49000},\"totals\":{\"fish_count\":49000,\"total_mass_kg\":800,\"feed_kg\":432,\"bran_kg\":1,\"feed_total_kg\":433,\"fertilizer_kg\":4},\"sazan\":{\"count\":50000,\"initial_weight_g\":3,\"average_weight_g\":16,\"mass_kg\":800,\"gain_per_fish_g\":13,\"biomass_gain_kg\":650,\"growth_days\":26,\"daily_growth_per_fish_g\":0.5,\"feed_total_kg\":433,\"fcr\":0.67000000000000003996802888650563545525074005126953125},\"other_fish\":[],\"fish_stock\":[{\"fish_type\":\"sazan\",\"label\":\"Sazan\",\"added\":200000,\"removed\":150000,\"count\":50000,\"initial_weight_g\":3,\"average_weight_g\":16,\"mass_kg\":800}],\"fish_list\":[{\"fish_type\":\"sazan\",\"label\":\"Sazan\",\"added\":200000,\"removed\":150000,\"count\":50000,\"initial_weight_g\":3,\"average_weight_g\":16,\"mass_kg\":800},{\"fish_type\":\"tolstolob\",\"label\":\"Tolstolob\",\"added\":214000,\"removed\":215000,\"count\":0,\"initial_weight_g\":1,\"average_weight_g\":7,\"mass_kg\":0}],\"closure_analysis\":{\"fish_types\":[{\"fish_type\":\"sazan\",\"label\":\"Sazan\",\"added\":200000,\"sold\":150000,\"loss\":0,\"remaining\":50000,\"sale_ratio_percent\":75,\"loss_free\":false,\"average_weight_kg\":0.01600000000000000033306690738754696212708950042724609375,\"remaining_mass_kg\":800},{\"fish_type\":\"tolstolob\",\"label\":\"Tolstolob\",\"added\":214000,\"sold\":0,\"loss\":215000,\"remaining\":0,\"sale_ratio_percent\":0,\"loss_free\":false,\"average_weight_kg\":0.007000000000000000145716771982051795930601656436920166015625,\"remaining_mass_kg\":0}],\"overall_loss_free\":false,\"total_remaining\":50000}}','yaxshimas','2026-08-20 03:33:21','2026-08-20 03:33:21');
/*!40000 ALTER TABLE `pond_closures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pond_worker`
--

DROP TABLE IF EXISTS `pond_worker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pond_worker` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `hired_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pond_worker_pond_id_user_id_unique` (`pond_id`,`user_id`),
  KEY `pond_worker_user_id_foreign` (`user_id`),
  CONSTRAINT `pond_worker_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pond_worker_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pond_worker`
--

LOCK TABLES `pond_worker` WRITE;
/*!40000 ALTER TABLE `pond_worker` DISABLE KEYS */;
INSERT INTO `pond_worker` VALUES (1,1,4,NULL,NULL,'2026-08-09 23:27:33','2026-08-09 23:27:33');
/*!40000 ALTER TABLE `pond_worker` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ponds`
--

DROP TABLE IF EXISTS `ponds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ponds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `region` varchar(255) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `area_m2` decimal(12,2) DEFAULT NULL,
  `current_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `director_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `closed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ponds_director_id_foreign` (`director_id`),
  KEY `ponds_created_by_foreign` (`created_by`),
  KEY `ponds_farm_id_foreign` (`farm_id`),
  CONSTRAINT `ponds_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ponds_director_id_foreign` FOREIGN KEY (`director_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ponds_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ponds`
--

LOCK TABLES `ponds` WRITE;
/*!40000 ALTER TABLE `ponds` DISABLE KEYS */;
INSERT INTO `ponds` VALUES (1,1,'Ishonch 7','Sirdaryo',NULL,NULL,6.00,0,2,2,'inactive','2026-08-20 06:59:59',NULL,'2026-07-30 07:07:59','2026-08-20 03:33:21'),(2,1,'a','Sirdaryo',NULL,NULL,5.00,593970,2,2,'active',NULL,NULL,'2026-08-04 15:55:08','2026-09-11 04:48:42'),(3,2,'&&&','Sirdaryo',NULL,NULL,6.00,728500,3,1,'active',NULL,NULL,'2026-08-06 04:53:23','2026-08-06 08:08:44'),(4,2,'havza','Sirdaryo',40.3085400,67.5315900,2.00,0,3,3,'active',NULL,NULL,'2026-08-11 18:25:28','2026-08-11 18:25:28');
/*!40000 ALTER TABLE `ponds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('zOXjUC099Z5LBaNlRAxHEAC84e0EHaiHQc4JUdTp',2,'127.0.0.1','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoib1VxRUZGaHByY1FUaWV5SjhXTUdhZE5COGJiaE5SbVJMYWlXWGIwZCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kaXJla3Rvci9ob3Z1ei8yL3NvdGlzaC1wb3RlcnlhIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789058922);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','done','cancelled') NOT NULL DEFAULT 'pending',
  `completion_notes` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_pond_id_foreign` (`pond_id`),
  KEY `tasks_assigned_to_foreign` (`assigned_to`),
  KEY `tasks_created_by_foreign` (`created_by`),
  CONSTRAINT `tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'worker',
  `phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `access_expires_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `director_id` bigint(20) unsigned DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `password_plain` text DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  KEY `users_created_by_foreign` (`created_by`),
  KEY `users_director_id_foreign` (`director_id`),
  CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_director_id_foreign` FOREIGN KEY (`director_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Egasi','boss@gmail.com','owner',NULL,1,NULL,NULL,NULL,NULL,'$2y$12$mZ8OsjUnPggjEdUFLjPU1.TGmCW8PC0UfWnRMvlXrKVIgR5TB6gNC','eyJpdiI6InZhcytYWCtZSGFQSmpCWVpDSXhIaGc9PSIsInZhbHVlIjoic1o3Rk1zQVVaZ2xmelhkYm9ucHRRdz09IiwibWFjIjoiNzlkNGQyZDg5Y2FjMWRiNWRlZTdmNWZiMjNjYzQzMTg1MmY5OWNhZmMyYzllNmQyNGI3MmFiYTExY2ExYjM3MSIsInRhZyI6IiJ9',NULL,'2026-07-30 06:28:47','2026-08-06 04:50:34'),(2,'Abduhamid',NULL,'director','+998950702016',1,'2027-08-29 16:53:11',1,NULL,NULL,'$2y$12$JcnDcMM/d9zPxKZGuzJxF.ZTpZk/xPMmAGa.7xCWW0DvAq6vdsBeS','eyJpdiI6IkJjZ3k5d2VUdnIza3BhL2NsczNYZlE9PSIsInZhbHVlIjoiSFpRYU05OXdKR2hoZFFUVCtvMVdyQT09IiwibWFjIjoiOWZlN2EwMGZmMTIzOTg4MzBjYzE1ZTYwMzlmNWM2ODRkZDdiNjlhOWUyMTgyZGUxN2IyZTk4NzFlNmJjZDAzMyIsInRhZyI6IiJ9',NULL,'2026-07-30 07:06:39','2026-08-29 16:53:11'),(3,'Boss',NULL,'director','+998774442525',1,'2026-10-03 23:20:03',1,NULL,NULL,'$2y$12$c9T0avGa4aSBv8vkYDixEOswT3FIZy3g8ZrbfarKyW9ua8a3Lg2cO','eyJpdiI6InZoZmtjUEJIWDU4RzlGSWt1RGZOY1E9PSIsInZhbHVlIjoidjlRZUhEVGJoQnRwZHd2QjVPanMwZz09IiwibWFjIjoiYjZjY2I3OTdmZTc5MDI0ZjZjNDRiZWI5ZmJkNDEyNWM4NTQwMzQ4MzU2MjUxMzc3ZmE3YWM0ODllODk4MWIxMiIsInRhZyI6IiJ9',NULL,'2026-08-06 04:52:41','2026-09-03 23:20:03'),(4,'ggg',NULL,'worker','+998999999999',1,NULL,1,2,NULL,'$2y$12$38qSkZ8R31BZbRqNzbRRD.EQOH5Tp6yI2LXqpKhlwLnxJZzYGL57q','eyJpdiI6IjAwZWg3M2dCc1l2M3JDV2RsS2QvU2c9PSIsInZhbHVlIjoiZkNlbEpoNFVCVU1iVUJLS1k5T3JFQT09IiwibWFjIjoiZTBhNjJmYWQ1NmVlNjYxNzY2OTcyMThhMDRlNmQ1M2RhZjhmN2E1NWQwOTNjYTVlZDJjNDc3YjgxNzdkZjkwZiIsInRhZyI6IiJ9',NULL,'2026-08-09 23:27:33','2026-08-26 22:21:03'),(5,'BOSS','ega@baliq.uz','owner',NULL,1,NULL,NULL,NULL,NULL,'$2y$12$0IPWtyVz9rPTrD5Do.WFl.gpYO5qPCxGJw0Cee71fhi8aEerwDpMO','eyJpdiI6Ik1aR0pXTTFSbTVhZ1UvWHZRZ0pxdXc9PSIsInZhbHVlIjoiOWFack40dmJGYmZpaVl3V3BRSHo1QT09IiwibWFjIjoiMzg0NzcwYzkwYzE3NDBhMDBlNzM0ZTZjNzk5ZjAyYmQ0NGI2NDQ2MGUxMGMyMjYzMzcyNzAwN2EyM2Y3ZmY1NiIsInRhZyI6IiJ9',NULL,'2026-08-13 19:45:13','2026-08-13 19:45:13');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouse_items`
--

DROP TABLE IF EXISTS `warehouse_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouse_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `unit` varchar(10) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouse_items_farm_id_category_name_unique` (`farm_id`,`category`,`name`),
  KEY `warehouse_items_created_by_foreign` (`created_by`),
  CONSTRAINT `warehouse_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_items_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouse_items`
--

LOCK TABLES `warehouse_items` WRITE;
/*!40000 ALTER TABLE `warehouse_items` DISABLE KEYS */;
INSERT INTO `warehouse_items` VALUES (1,2,'feed','yem','kg',0.00,'warehouse/3/6pyShPTMhFNEB9c4OQLGdMnjswP2VWKPPh2sBmef.png',1,'2026-08-10 03:37:08','2026-08-10 04:55:48'),(2,1,'medicine','Brobiotik','kg',1.00,NULL,2,'2026-08-16 05:52:48','2026-09-10 00:13:03'),(3,1,'feed','Ekstruder 35','kg',0.00,NULL,2,'2026-08-16 07:49:25','2026-08-20 00:14:32'),(4,2,'feed','Ekstruder 37-32','kg',0.00,NULL,1,'2026-08-16 18:33:55','2026-08-16 18:34:19'),(5,2,'medicine','batommaster','piece',10.00,NULL,1,'2026-08-17 02:09:49','2026-08-17 02:09:49'),(6,2,'feed','yem 37','kg',100.00,NULL,3,'2026-08-26 05:01:55','2026-08-26 05:02:16'),(7,1,'feed','granula','kg',0.00,NULL,2,'2026-09-10 00:12:35','2026-09-10 00:13:03');
/*!40000 ALTER TABLE `warehouse_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worker_reports`
--

DROP TABLE IF EXISTS `worker_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pond_id` bigint(20) unsigned NOT NULL,
  `worker_id` bigint(20) unsigned NOT NULL,
  `report_date` date NOT NULL,
  `work_done` text NOT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `worker_reports_pond_id_foreign` (`pond_id`),
  KEY `worker_reports_worker_id_foreign` (`worker_id`),
  CONSTRAINT `worker_reports_pond_id_foreign` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `worker_reports_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_reports`
--

LOCK TABLES `worker_reports` WRITE;
/*!40000 ALTER TABLE `worker_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `worker_reports` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  3:21:51
