-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: pos_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `audits`
--

DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audits`
--

LOCK TABLES `audits` WRITE;
/*!40000 ALTER TABLE `audits` DISABLE KEYS */;
INSERT INTO `audits` VALUES (1,2,1,'order.create','{\"order_id\":\"1\"}','2025-08-18 16:20:16'),(2,2,1,'order.create','{\"order_id\":\"2\"}','2025-08-18 16:20:18'),(3,2,1,'order.create','{\"order_id\":\"3\"}','2025-08-18 16:20:31'),(4,2,1,'order.create','{\"order_id\":\"4\"}','2025-08-18 16:20:31'),(5,2,1,'order.create','{\"order_id\":\"5\"}','2025-08-18 16:20:33'),(6,2,1,'order.create','{\"order_id\":\"6\"}','2025-08-18 16:20:34'),(7,2,1,'order.create','{\"order_id\":\"7\"}','2025-08-18 16:23:45'),(8,2,1,'order.create','{\"order_id\":\"8\"}','2025-08-18 16:26:44'),(9,2,1,'order.delete','{\"order_id\":8}','2025-08-18 16:39:28'),(10,2,1,'order.delete','{\"order_id\":7}','2025-08-18 16:39:30'),(11,3,2,'order.create','{\"order_id\":\"9\"}','2025-08-18 16:45:47'),(12,3,2,'order.create','{\"order_id\":\"10\"}','2025-08-18 17:14:17'),(13,3,2,'order.create','{\"order_id\":\"11\"}','2025-08-18 17:14:37'),(14,4,6,'order.create','{\"order_id\":\"12\"}','2025-08-18 17:53:50'),(15,2,1,'order.create','{\"order_id\":\"13\"}','2025-08-19 02:06:57'),(16,2,1,'order.create','{\"order_id\":\"14\"}','2025-08-19 06:56:56'),(17,6,6,'order.create','{\"order_id\":\"15\"}','2025-08-19 07:54:21'),(18,2,1,'order.create','{\"order_id\":\"16\"}','2025-08-19 08:52:40'),(19,4,6,'order.create','{\"order_id\":\"17\"}','2025-08-20 08:05:19'),(20,11,9,'order.create','{\"order_id\":\"18\"}','2025-08-21 04:34:04'),(21,12,7,'order.create','{\"order_id\":\"19\"}','2025-08-21 04:56:11'),(22,6,6,'order.create','{\"order_id\":\"20\"}','2025-08-21 08:57:35'),(23,4,6,'order.create','{\"order_id\":\"21\"}','2025-08-22 04:25:02'),(24,4,6,'order.create','{\"order_id\":\"22\"}','2025-08-22 04:25:32'),(25,4,6,'order.create','{\"order_id\":\"23\"}','2025-08-22 04:29:32'),(26,4,6,'order.create','{\"order_id\":\"24\"}','2025-08-22 06:07:10'),(27,4,6,'order.create','{\"order_id\":\"25\"}','2025-08-22 06:20:35'),(28,4,6,'order.create','{\"order_id\":\"26\"}','2025-08-22 06:26:54'),(29,4,6,'order.create','{\"order_id\":\"27\"}','2025-08-22 06:29:18'),(30,4,6,'order.create','{\"order_id\":\"28\"}','2025-08-22 06:29:39'),(31,4,6,'order.create','{\"order_id\":\"29\"}','2025-08-22 06:32:37'),(32,4,6,'order.create','{\"order_id\":\"30\"}','2025-08-22 06:32:51'),(33,4,6,'order.create','{\"order_id\":\"31\"}','2025-08-22 06:53:10'),(34,4,6,'order.create','{\"order_id\":\"32\"}','2025-08-22 07:05:09'),(35,4,6,'order.create','{\"order_id\":\"33\"}','2025-08-22 07:09:37'),(36,4,6,'order.create','{\"order_id\":\"34\"}','2025-08-22 07:10:06'),(37,4,6,'order.create','{\"order_id\":\"35\"}','2025-08-22 07:13:21'),(38,4,6,'order.create','{\"order_id\":\"36\"}','2025-08-22 07:14:08'),(39,4,6,'order.create','{\"order_id\":\"37\"}','2025-08-22 07:15:55'),(40,4,6,'order.create','{\"order_id\":\"38\"}','2025-08-22 07:18:47'),(41,4,6,'order.create','{\"order_id\":\"39\"}','2025-08-22 07:46:23'),(42,4,6,'order.create','{\"order_id\":\"40\"}','2025-08-22 13:57:48'),(43,4,6,'order.create','{\"order_id\":\"41\"}','2025-08-22 13:58:10'),(44,4,6,'order.create','{\"order_id\":\"42\"}','2025-08-23 10:25:49'),(45,8,7,'order.create','{\"order_id\":\"43\"}','2025-08-23 10:29:05'),(46,8,7,'order.create','{\"order_id\":\"44\"}','2025-08-23 10:46:26'),(47,8,7,'order.create','{\"order_id\":\"45\"}','2025-08-23 10:47:13'),(48,4,6,'order.create','{\"order_id\":\"46\"}','2025-08-25 01:29:31'),(49,4,6,'order.create','{\"order_id\":\"47\"}','2025-08-25 06:35:44'),(50,4,6,'order.create','{\"order_id\":\"51\"}','2025-08-27 03:58:36'),(51,4,6,'order.create','{\"order_id\":\"52\"}','2025-08-27 04:13:15'),(52,4,6,'order.create','{\"order_id\":\"53\"}','2025-08-27 04:19:16'),(53,4,6,'order.create','{\"order_id\":\"54\"}','2025-08-27 04:19:34'),(54,4,6,'order.create','{\"order_id\":\"55\"}','2025-08-27 04:26:53'),(55,4,6,'order.create','{\"order_id\":\"56\"}','2025-08-27 04:31:09'),(56,4,6,'order.create','{\"order_id\":\"70\"}','2025-08-27 06:43:13'),(57,4,6,'order.create','{\"order_id\":\"71\"}','2025-08-27 07:24:22'),(58,4,6,'order.create','{\"order_id\":\"72\",\"total\":210600,\"items_count\":1,\"payment_method\":\"cash\"}','2025-08-27 07:30:37'),(59,4,6,'order.create','{\"order_id\":\"77\",\"total\":84240,\"items_count\":1,\"payment_method\":\"qris\"}','2025-08-27 10:03:16'),(60,4,6,'order.create','{\"order_id\":\"80\",\"total\":84240,\"items_count\":1,\"payment_method\":\"credit\",\"credit_id\":\"2\",\"employee_id\":114,\"employee_name\":\"AGAM RIDWAN PUTRA (SPV CAFE BATU)\"}','2025-08-28 02:01:30'),(61,4,6,'order.create','{\"order_id\":\"81\",\"total\":42120,\"items_count\":1,\"payment_method\":\"credit\",\"credit_id\":\"3\",\"employee_id\":15,\"employee_name\":\"TESSA NUR ALIFAH (M. KREDIT)\"}','2025-08-28 03:29:02'),(62,4,6,'order.create','{\"order_id\":\"82\",\"total\":42120,\"items_count\":1,\"payment_method\":\"cash\"}','2025-08-28 04:35:31'),(63,7,8,'order.create','{\"order_id\":\"83\",\"total\":20000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 06:36:40'),(64,7,8,'order.create','{\"order_id\":\"84\",\"total\":20000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 06:40:27'),(65,7,8,'order.create','{\"order_id\":\"85\",\"total\":20000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 06:41:03'),(66,7,8,'order.create','{\"order_id\":\"86\",\"total\":18000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 06:41:24'),(67,7,8,'order.create','{\"order_id\":\"87\",\"total\":22000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 06:41:47'),(68,7,8,'order.create','{\"order_id\":\"88\",\"total\":45000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 07:01:17'),(69,7,8,'order.create','{\"order_id\":\"89\",\"total\":20000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 07:02:35'),(70,7,8,'order.create','{\"order_id\":\"90\",\"total\":44000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 07:04:50'),(71,7,8,'order.create','{\"order_id\":\"91\",\"total\":42000,\"items_count\":2,\"payment_method\":\"cash\"}','2025-09-06 09:04:31'),(72,7,8,'order.create','{\"order_id\":\"92\",\"total\":22000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 09:56:44'),(73,7,8,'order.create','{\"order_id\":\"93\",\"total\":62000,\"items_count\":2,\"payment_method\":\"cash\"}','2025-09-06 10:31:13'),(74,7,8,'order.create','{\"order_id\":\"94\",\"total\":22000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 10:39:32'),(75,7,8,'order.create','{\"order_id\":\"95\",\"total\":62000,\"items_count\":2,\"payment_method\":\"cash\"}','2025-09-06 10:40:14'),(76,7,8,'order.create','{\"order_id\":\"96\",\"total\":132000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 10:59:47'),(77,4,6,'order.create','{\"order_id\":\"97\",\"total\":72000,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-06 11:20:02'),(78,7,8,'order.create','{\"order_id\":\"98\",\"total\":24400,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-11 10:33:04'),(79,7,8,'order.create','{\"order_id\":\"99\",\"total\":42700,\"items_count\":2,\"payment_method\":\"cash\"}','2025-09-15 07:24:41'),(80,7,8,'order.create','{\"order_id\":\"100\",\"total\":18300,\"items_count\":1,\"payment_method\":\"credit\",\"credit_id\":\"4\",\"employee_id\":98,\"employee_name\":\"ADIS SOPANDI (KOKI RM SUNDA)\"}','2025-09-15 07:25:48'),(81,7,8,'order.create','{\"order_id\":\"101\",\"total\":21960,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 07:26:25'),(82,7,8,'order.create','{\"order_id\":\"102\",\"total\":21960,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 07:26:32'),(83,7,8,'order.create','{\"order_id\":\"103\",\"total\":21960,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 07:30:22'),(84,7,8,'order.create','{\"order_id\":\"104\",\"total\":18300,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 07:35:46'),(85,7,8,'order.create','{\"order_id\":\"105\",\"total\":18300,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 07:36:10'),(86,7,8,'order.create','{\"order_id\":\"106\",\"total\":48800,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 07:36:31'),(87,7,8,'order.create','{\"order_id\":\"107\",\"total\":18300,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 07:41:34'),(88,7,8,'order.create','{\"order_id\":\"108\",\"total\":18300,\"items_count\":1,\"payment_method\":\"qris\"}','2025-09-15 07:43:23'),(89,9,8,'order.create','{\"order_id\":\"109\",\"total\":21960,\"items_count\":1,\"payment_method\":\"cash\"}','2025-09-15 09:06:31'),(90,7,8,'order.create','{\"order_id\":\"110\",\"total\":24400,\"payment_method\":\"cash\",\"items_count\":1}','2025-09-15 09:12:06'),(91,7,8,'order.create','{\"order_id\":\"111\",\"total\":21960,\"payment_method\":\"cash\",\"items_count\":1}','2025-09-15 09:22:19'),(92,7,8,'order.create','{\"order_id\":\"112\",\"total\":48800,\"payment_method\":\"cash\",\"items_count\":1,\"cash_given\":50000,\"change\":1200}','2025-09-15 09:34:43'),(93,7,8,'order.create','{\"order_id\":\"113\",\"total\":48800,\"payment_method\":\"cash\",\"items_count\":1,\"cash_given\":50000,\"change\":1200}','2025-09-15 09:38:02'),(94,7,8,'order.create','{\"order_id\":\"114\",\"total\":48800,\"payment_method\":\"credit\",\"items_count\":1,\"cash_given\":0,\"change\":0}','2025-09-15 09:56:18'),(95,7,8,'order.create','{\"order_id\":\"115\",\"total\":24400,\"payment_method\":\"cash\",\"items_count\":1,\"cash_given\":25000,\"change\":600}','2025-09-15 09:56:44'),(96,4,6,'order.create','{\"order_id\":\"116\",\"total\":43920,\"payment_method\":\"credit\",\"items_count\":1,\"cash_given\":0,\"change\":0}','2025-09-15 10:05:25'),(97,4,6,'order.create','{\"order_id\":\"117\",\"total\":43920,\"payment_method\":\"cash\",\"items_count\":1,\"cash_given\":45000,\"change\":1080}','2025-09-15 10:07:35'),(98,4,6,'order.create','{\"order_id\":\"118\",\"total\":43920,\"payment_method\":\"qris\",\"items_count\":1,\"cash_given\":0,\"change\":0}','2025-09-15 10:07:43'),(99,7,8,'order.create','{\"order_id\":\"119\",\"total\":48800,\"payment_method\":\"credit\",\"items_count\":1,\"cash_given\":0,\"change\":0}','2025-09-17 03:05:09'),(100,7,8,'order.create','{\"order_id\":\"120\",\"total\":213500,\"payment_method\":\"credit\",\"items_count\":9,\"cash_given\":0,\"change\":0}','2025-09-17 03:20:24'),(101,7,8,'order.create','{\"order_id\":\"121\",\"total\":286700,\"payment_method\":\"cash\",\"items_count\":7,\"cash_given\":300000,\"change\":13300}','2025-09-17 03:57:31'),(102,7,8,'order.create','{\"order_id\":\"122\",\"total\":46360,\"payment_method\":\"credit\",\"items_count\":2,\"cash_given\":0,\"change\":0}','2025-09-17 09:00:26'),(103,7,8,'order.create','{\"order_id\":\"123\",\"total\":46360,\"payment_method\":\"credit\",\"items_count\":2,\"cash_given\":0,\"change\":0}','2025-09-18 06:28:05'),(104,7,8,'order.create','{\"order_id\":\"124\",\"total\":21960,\"payment_method\":\"qris\",\"items_count\":1,\"cash_given\":0,\"change\":0}','2025-09-23 02:31:53');
/*!40000 ALTER TABLE `audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Pusat'),(6,'Cafe Batu'),(7,'Restoran Khas Sunda Mang Dadang'),(8,'De\'Onél'),(9,'Oasis Tropis');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cancel_requests`
--

DROP TABLE IF EXISTS `cancel_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cancel_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approver_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cancel_requests`
--

LOCK TABLES `cancel_requests` WRITE;
/*!40000 ALTER TABLE `cancel_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `cancel_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_transactions`
--

DROP TABLE IF EXISTS `cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `cash_given` decimal(15,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_transactions`
--

LOCK TABLES `cash_transactions` WRITE;
/*!40000 ALTER TABLE `cash_transactions` DISABLE KEYS */;
INSERT INTO `cash_transactions` VALUES (1,112,50000.00,1200.00,'2025-09-15 09:34:43'),(2,113,50000.00,1200.00,'2025-09-15 09:38:02'),(3,115,25000.00,600.00,'2025-09-15 09:56:44'),(4,117,45000.00,1080.00,'2025-09-15 10:07:35'),(5,121,300000.00,13300.00,'2025-09-17 03:57:31');
/*!40000 ALTER TABLE `cash_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credit_payments`
--

DROP TABLE IF EXISTS `credit_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `credit_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','transfer','qris','other') DEFAULT 'cash',
  `note` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_credit` (`credit_id`),
  KEY `idx_created` (`created_at`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `credit_payments_ibfk_1` FOREIGN KEY (`credit_id`) REFERENCES `credits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credit_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credit_payments`
--

LOCK TABLES `credit_payments` WRITE;
/*!40000 ALTER TABLE `credit_payments` DISABLE KEYS */;
INSERT INTO `credit_payments` VALUES (1,2,84240.00,'qris','',4,'2025-08-28 02:02:32'),(2,3,42120.00,'other','',4,'2025-08-28 03:29:36'),(3,4,18300.00,'cash','',7,'2025-09-15 08:32:27'),(4,5,48800.00,'cash','lunas',7,'2025-09-15 09:58:11'),(5,6,43920.00,'cash','lunas 15 sept 2025',4,'2025-09-15 10:08:42'),(6,7,48800.00,'cash','keuangan',7,'2025-09-17 03:07:08'),(7,8,213500.00,'cash','',7,'2025-09-17 04:04:11'),(8,9,46360.00,'cash','keuangan',7,'2025-09-17 09:46:14');
/*!40000 ALTER TABLE `credit_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credits`
--

DROP TABLE IF EXISTS `credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `employee_name` varchar(120) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('unpaid','partial','paid','cancelled') DEFAULT 'unpaid',
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_name`),
  KEY `idx_status` (`status`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_created` (`created_at`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `credits_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `credits_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `credits_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credits`
--

LOCK TABLES `credits` WRITE;
/*!40000 ALTER TABLE `credits` DISABLE KEYS */;
INSERT INTO `credits` VALUES (2,80,114,'AGAM RIDWAN PUTRA (SPV CAFE BATU)','AGAM RIDWAN PUTRA (SPV CAFE BATU)',NULL,84240.00,84240.00,'paid',6,4,'2025-09-27',NULL,'2025-08-28 02:01:30','2025-08-28 02:02:32'),(3,81,15,'TESSA NUR ALIFAH (M. KREDIT)','TESSA NUR ALIFAH (M. KREDIT)',NULL,42120.00,42120.00,'paid',6,4,'2025-09-27',NULL,'2025-08-28 03:29:02','2025-08-28 03:29:36'),(4,100,98,'ADIS SOPANDI (KOKI RM SUNDA)','ADIS SOPANDI (KOKI RM SUNDA)',NULL,18300.00,18300.00,'paid',8,7,'2025-10-15',NULL,'2025-09-15 07:25:48','2025-09-15 08:32:27'),(5,114,148,'ADELYA S (BARISTA)','ADELYA S (BARISTA)',NULL,48800.00,48800.00,'paid',8,7,'2025-10-15',NULL,'2025-09-15 09:56:18','2025-09-15 09:58:11'),(6,116,148,'ADELYA S (BARISTA)','ADELYA S (BARISTA)',NULL,43920.00,43920.00,'paid',6,4,'2025-10-15',NULL,'2025-09-15 10:05:25','2025-09-15 10:08:42'),(7,119,125,'ALI MANSUR HIDAYAT (BARISTA)','ALI MANSUR HIDAYAT (BARISTA)',NULL,48800.00,48800.00,'paid',8,7,'2025-10-17',NULL,'2025-09-17 03:05:09','2025-09-17 03:07:08'),(8,120,15,'TESSA NUR ALIFAH (M. KREDIT)','TESSA NUR ALIFAH (M. KREDIT)',NULL,213500.00,213500.00,'paid',8,7,'2025-10-17',NULL,'2025-09-17 03:20:24','2025-09-17 04:04:11'),(9,122,114,'AGAM RIDWAN PUTRA (SPV CAFE BATU)','AGAM RIDWAN PUTRA (SPV CAFE BATU)',NULL,46360.00,46360.00,'paid',8,7,'2025-10-17',NULL,'2025-09-17 09:00:26','2025-09-17 09:46:14'),(10,123,114,'AGAM RIDWAN PUTRA (SPV CAFE BATU)','AGAM RIDWAN PUTRA (SPV CAFE BATU)',NULL,46360.00,0.00,'unpaid',8,7,'2025-10-18',NULL,'2025-09-18 06:28:05','2025-09-18 06:28:05');
/*!40000 ALTER TABLE `credits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (5,'NAMA'),(6,'GILAR SABILAROSYAD (DIR. UTAMA)'),(7,'RONAL KASTARI (DIR. OPERASIONAL)'),(8,'DONI ROMDONI (GM. OPERASIONAL)'),(9,'RAFLY IRWIN PANGESTU (ASST. DESIGN)'),(10,'ELISA NURHAYATI (M. HR)'),(11,'LENI SITI HANDAYANI (M. PERSONALIA)'),(12,'NADIYA PUTRI HIDAYAH (M. GENERAL AFFAIR)'),(13,'RIZKY AMELIA (M. KEUANGAN)'),(14,'SALSABILA RIZKI (KASIR PUSAT)'),(15,'TESSA NUR ALIFAH (M. KREDIT)'),(16,'ENI SUMARNI (KREDIT)'),(17,'DEDE INTAN NUR ALIYAH (KREDIT)'),(18,'HERI RUSMANTO (KEPALA GUDANG & LOGISTIK)'),(19,'AYU PATMAWATI (SPV SC RUMAH 72)'),(20,'TIA SANTIKA (GM PARIWISATA)'),(21,'AZHARI ALI IDRIS (M. AUDIT PT & CV)'),(22,'REZA DWI ARTA (AUDIT PT & CV)'),(23,'WINDA WIDIANTY (M. GUDANG)'),(24,'NOERAHMAN ARIF (AUDIT GUDANG)'),(25,'ANDRI FAIZAL HIDAYAT (STAFF GUDANG)'),(26,'ARI TAUFIK (STAFF GUDANG)'),(27,'MUHAMAD ANDI FAHRIZAL (STAFF GUDANG)'),(28,'TEGUH BUDIANSYAH (TAMAN)'),(29,'TRI KUSUMA AJI (SPV OB & CS)'),(30,'DEDE KURNIASIH (CLEANING SERVICE)'),(31,'EEN SUNAYAH (CLEANING SERVICE)'),(32,'SANDI KURNIA (CLEANING SERVICE)'),(33,'HADI NUGRAHA (CLEANING SERVICE)'),(34,'RUHIAT (CLEANING SERVICE)'),(35,'GENDI PIANDRI HIDAYAT (CLEANING SERVICE)'),(36,'ASEP TAUDIN (SPV UNGGAS)'),(37,'MUHAMAD CUCUN (DIV. UNGGAS)'),(38,'IYAN SOFIYAN (DIV. UNGGAS)'),(39,'TAUFIK KURAHMAN (DIV. UNGGAS)'),(40,'M FAJAR (DIV. UNGGAS)'),(41,'AYAT SETIAWAN (SPV DOMBA & KAMBING)'),(42,'FAUZAN SUJANA JULIANSYAH (DIV. PTR DOMBA)'),(43,'ACEP SANDI (DIV. PTR DOMBA)'),(44,'ASEP CAHYANA (DIV. PTR DOMBA)'),(45,'SONI NUGRAHA (DIV. PTR DOMBA)'),(46,'ANDRI INDRAYANA (DIV. PTR DOMBA)'),(47,'SUJANA KOSIM (M. BUDAYA DAN EDUKASI)'),(48,'RUKMANA (DIV. PTR KAMBING)'),(49,'DIK DIK (DIV. PTR KAMBING)'),(50,'MURDIANSYAH (DIV. PTR KAMBING)'),(51,'GUGUN GUNAWAN (DIV. PTR KAMBING)'),(52,'NUR MUHAMMAD (SPV BUDIDAYA IKAN DAN BELUT)'),(53,'TATANG (DIV. BELUT)'),(54,'DADAN TRIANA (GM. CV KENCANA 58)'),(55,'ELAN (DIV. IKAN)'),(56,'MUHADI (SPV TAMAN)'),(57,'RAI AHMAD FUADI (DIV. TAMAN)'),(58,'EGI RESNANSYAH (DIV. TAMAN)'),(59,'TEGUH ESA NUGERAHA (DIV. TAMAN)'),(60,'RIYAN FIRMANSYAH (DIV. TAMAN)'),(61,'ANANG (DIV. TAMAN)'),(62,'DEDE SUPRIADI (DIV. TAMAN)'),(63,'SURYADINATA (DIV. TAMAN)'),(64,'SURYANA (SPV KEBUN)'),(65,'IYUS YUSTIANA (DIV. KEBUN)'),(66,'DADANG RISMA (DIV. KEBUN)'),(67,'SUTARJA (DIV. KEBUN)'),(68,'OGI NAYOGI (DIV. KEBUN)'),(69,'ODAM EMEN (DIV. KEBUN)'),(70,'WILLY DARWIS D (HIDROPONIK)'),(71,'DIKI FAJAR S (HIDROPONIK)'),(72,'DIVA JULIANSYAH (KEPALA KEAMANAN)'),(73,'RIAN RAMADHANI (SECURITY)'),(74,'RUDI MULYADI (SECURITY)'),(75,'EKO SEPTIADI (SECURITY)'),(76,'RESHA SATRIA (SECURITY)'),(77,'AHMAD KARYADI (SECURITY)'),(78,'DENI KURNIAWAN (SECURITY)'),(79,'RAKA JAKA D (SECURITY)'),(80,'AHMAD JAENUDIN (SECURITY)'),(81,'RAMADHAN (SECURITY)'),(82,'RIZQY LESMANA (SECURITY)'),(83,'DEDE ROHIMAT (SECURITY)'),(84,'DEDE HERMAN (SECURITY)'),(85,'ANZAR SOLIHIN (SECURITY)'),(86,'FAJAR HERYANA (SECURITY)'),(87,'M FIRDAUS (SECURITY)'),(88,'SAMU NUGRAHA (SECURITY)'),(89,'ANDRI PRATAMA YOGA (SECURITY)'),(90,'JARWO (KENDARAAN DAN UMUM)'),(91,'RUDI NURCAHYADI (IT)'),(92,'MUHAMAD RUDI MARDIANA (SPV KAROKE)'),(93,'NUGGRAHA RIEZKI AMALLIA (IT)'),(94,'RUHIYATNA (M. F&B)'),(95,'ALMA JUNITA (SPV SERVICE AND COMPLANT)'),(96,'RIZKI FITRIA (SPV RM. SUNDA)'),(97,'I KADE ARYA AGUS SUBAGIA (M. PARIWISATA)'),(98,'ADIS SOPANDI (KOKI RM SUNDA)'),(99,'PRADITA BANYU PUTRA (KOKI RM SUNDA)'),(100,'YARIS RISYADI (ASST KOKI RM SUNDA)'),(101,'UTARI SRI YUWITA (WAITER-GREETER-KASIR)'),(102,'KARLINA ANDINI (WAITER-GREETER-KASIR)'),(103,'YULIA NURFADILAH (WAITER-GREETER-KASIR)'),(104,'RIFKI HAMBALI (WAITER-GREETER-KASIR)'),(105,'CHICI PIDATUNISA (WAITER-GREETER-KASIR)'),(106,'NANDRA RIZKI BETARI (WAITER-GREETER-KASIR)'),(107,'NONON TAMIA RAHMA (CLEANING SERVICE)'),(108,'RINI KURNIASARI (ASST M. KEUANGAN)'),(109,'DIKKI DESTIANA (MARKETING)'),(110,'DIKKY SEPTIAN (SPV COTTAGE'),(111,'RHASSYA INTAN AFNI AENI (M. PELAYANAN)'),(112,'HASYIM SURYANA (GM. CV RUMAH 72)'),(113,'KUSNADIN (SPV GUDANG CV)'),(114,'AGAM RIDWAN PUTRA (SPV CAFE BATU)'),(115,'SAM FAJAR (SPV D\'ONEL)'),(116,'ELVA AVRIYANTI (ADM. CAFE BATU)'),(117,'ALVIANDO (ADM. CAFE BATU)'),(118,'RIYAN RAMADHAN (PURCHASING)'),(119,'BELLA APRIYANTI (SPV TROPIS)'),(120,'AGUS SETIAWAN (KASIR CAFE BATU)'),(121,'IRFAN MUHAROM (KOKI 1 CAFE BATU)'),(122,'ASTRID RIZKI MAULIDA (KOKI 2 CAFE BATU)'),(123,'IWAN SUBAGJA (BARISTA)'),(124,'IMELDA AMALIA (BARISTA)'),(125,'ALI MANSUR HIDAYAT (BARISTA)'),(126,'MUHAEMIN (BARISTA)'),(127,'HERLIN FITRIA SEPTIANI (SPV FO)'),(128,'ANISA NURUL ZAHWA (WAITERS)'),(129,'RIZWAR NUR RAHMAN (WAITERS)'),(130,'SALWA (WAITERS)'),(131,'PUTRI APRILIANTI (GREATING)'),(132,'M. RIDWAN (WAITERS)'),(133,'M. AGUNG RAMDANI (PURCHASING)'),(134,'HENI NURHAENI (CLEANING SERVICE)'),(135,'KIKI KURNIAWAN (CLEANING SERVICE)'),(136,'AGUN FAUZI LUTFIANA (CLEANING SERVICE)'),(137,'KRISNA FAUZI (CLEANING SERVICE)'),(138,'FAKHIRA (WAITERS)'),(139,'SUGIH (WAITERS)'),(140,'KARTINA (WAITERS)'),(141,'NENG DIAN (WAITERS)'),(142,'YULIA FEBRIANTI (WAITERS)'),(143,'HANNI AFWA (WAITERS)'),(144,'SHIFFA DWIPANGGA (WAITERS)'),(145,'DEA AGUSTINA (WAITERS)'),(146,'RISKA AMALIA (WAITERS)'),(147,'ANANDA CIKAL (BARISTA)'),(148,'ADELYA S (BARISTA)'),(149,'ASEP FAUZI (KASIR)'),(150,'IRFAN HERDIAN (KOKI)'),(151,'ERIK PERMANA SUTARJA (KOKI)'),(152,'NENG DESI (CLEANING SERVICE)'),(153,'DEWI RATNA WULAN (CLEANING SERVICE)'),(154,'YAYANG TOMIKA (CLEANING SERVICE)'),(155,'MUNAWAR KHOLIL (SPV OPERASIONAL)'),(156,'NISA FEBRIANA (WAITERS)'),(157,'JOHAN (SPV OUTBOND)');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`,`branch_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `discount` int(11) NOT NULL DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (10,10,1,3,15000,0,NULL),(60,43,5,3,25000,0,NULL),(61,44,5,3,25000,0,NULL),(62,45,5,1,25000,0,NULL),(65,47,9,1,5000,0,NULL),(66,47,6,1,20000,0,NULL),(70,51,10,1,36000,0,NULL),(71,52,10,1,36000,0,NULL),(72,53,6,1,20000,0,NULL),(73,54,6,5,20000,0,NULL),(74,55,6,1,20000,0,NULL),(75,56,12,1,5000,0,NULL),(76,56,10,1,36000,0,NULL),(77,56,6,1,20000,0,NULL),(78,70,10,1,36000,0,'other'),(79,70,12,1,5000,0,'drink'),(80,71,10,3,36000,0,'other'),(81,72,10,5,36000,0,NULL),(86,77,10,2,36000,0,NULL),(89,80,10,2,36000,0,NULL),(90,81,10,1,36000,0,NULL),(91,82,10,1,36000,0,NULL),(92,83,15,1,20000,0,NULL),(93,84,13,1,20000,0,NULL),(94,85,13,1,20000,0,NULL),(95,86,14,1,18000,0,NULL),(96,87,19,1,22000,0,NULL),(97,88,22,3,15000,0,NULL),(98,89,20,1,20000,0,NULL),(99,90,16,2,22000,0,NULL),(100,91,13,1,20000,0,NULL),(101,91,16,1,22000,0,NULL),(102,92,16,1,22000,0,NULL),(103,93,13,2,20000,0,NULL),(104,93,16,1,22000,0,NULL),(105,94,16,1,22000,0,NULL),(106,95,13,2,20000,0,NULL),(107,95,16,1,22000,0,NULL),(108,96,16,6,22000,0,NULL),(109,97,10,2,36000,0,NULL),(111,99,22,1,15000,0,NULL),(112,99,13,1,20000,0,NULL),(113,100,22,1,15000,0,NULL),(114,101,14,1,18000,0,NULL),(115,102,14,1,18000,0,NULL),(116,103,14,1,18000,0,NULL),(117,104,22,1,15000,0,NULL),(118,105,22,1,15000,0,NULL),(119,106,13,2,20000,0,NULL),(120,107,22,1,15000,0,NULL),(121,108,22,1,15000,0,NULL),(122,109,18,1,18000,0,NULL),(123,110,13,1,20000,0,NULL),(124,111,14,1,18000,0,NULL),(125,112,13,2,20000,0,NULL),(126,113,13,2,20000,0,NULL),(127,114,13,2,20000,0,NULL),(128,115,13,1,20000,0,NULL),(129,116,10,1,36000,0,NULL),(130,117,10,1,36000,0,NULL),(131,118,10,1,36000,0,NULL),(132,119,13,2,20000,0,NULL),(133,120,14,1,18000,0,NULL),(134,120,13,1,20000,0,NULL),(135,120,22,1,15000,0,NULL),(136,120,18,1,18000,0,NULL),(137,120,17,1,20000,0,NULL),(138,120,16,1,22000,0,NULL),(139,120,20,1,20000,0,NULL),(140,120,19,1,22000,0,NULL),(141,120,15,1,20000,0,NULL),(142,121,22,1,15000,0,NULL),(143,121,13,1,20000,0,NULL),(144,121,18,2,18000,0,NULL),(145,121,15,5,20000,0,NULL),(146,121,19,1,22000,0,NULL),(147,121,20,1,20000,0,NULL),(148,121,16,1,22000,0,NULL),(149,122,18,1,18000,0,NULL),(150,122,17,1,20000,0,NULL),(151,123,14,1,18000,0,NULL),(152,123,13,1,20000,0,NULL),(153,124,18,1,18000,0,NULL);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `payment_method` varchar(40) NOT NULL,
  `status` enum('pending','paid','cancelled','refunded','credit') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `customer_name` varchar(120) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `employee_name` varchar(120) DEFAULT NULL,
  `order_type` varchar(16) DEFAULT NULL,
  `table_no` varchar(32) DEFAULT NULL,
  `tax_percent` float DEFAULT NULL,
  `tax_value` int(11) DEFAULT 0,
  `service_percent` float DEFAULT NULL,
  `service_value` int(11) DEFAULT 0,
  `cash_given` int(11) DEFAULT NULL,
  `change_returned` decimal(15,2) DEFAULT NULL,
  `change_amount` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_method` (`payment_method`)
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (10,3,2,45000,'cash','paid','2025-08-18 17:14:17',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,NULL,NULL),(43,8,7,87750,'credit','paid','2025-08-23 10:29:05','',NULL,'RIZKI FITRIA (SPV RM. SUNDA)',NULL,NULL,12,9000,5,3750,NULL,NULL,NULL),(44,8,7,87750,'credit','paid','2025-08-23 10:46:26','',NULL,'ADELYA S (BARISTA)','takeaway','',12,9000,5,3750,NULL,NULL,NULL),(45,8,7,29250,'qris','paid','2025-08-23 10:47:13','agus sumi',NULL,'','takeaway','',12,3000,5,1250,NULL,NULL,NULL),(47,4,6,29250,'credit','paid','2025-08-25 06:35:44','',NULL,'I KADE ARYA AGUS SUBAGIA (M. PARIWISATA)','takeaway','',12,3000,5,1250,NULL,NULL,NULL),(51,4,6,42120,'cash','paid','2025-08-27 03:58:36','',NULL,'','dinein','',12,4320,5,1800,NULL,NULL,NULL),(52,4,6,42120,'credit','paid','2025-08-27 04:13:15','',NULL,'ACEP SANDI (DIV. PTR DOMBA)','takeaway','',12,4320,5,1800,NULL,NULL,NULL),(53,4,6,23400,'cash','paid','2025-08-27 04:19:16','',NULL,'','dinein','',12,2400,5,1000,NULL,NULL,NULL),(54,4,6,117000,'qris','paid','2025-08-27 04:19:34','',NULL,'','dinein','',12,12000,5,5000,NULL,NULL,NULL),(55,4,6,23400,'qris','paid','2025-08-27 04:26:53','',NULL,'','dinein','',12,2400,5,1000,NULL,NULL,NULL),(56,4,6,71370,'credit','paid','2025-08-27 04:31:09','',NULL,'ALI MANSUR HIDAYAT (BARISTA)','dinein','',12,7320,5,3050,NULL,NULL,NULL),(70,4,6,47970,'qris','paid','2025-08-27 06:43:13','',NULL,'','dinein','',12,4920,5,2050,NULL,NULL,NULL),(71,4,6,126360,'qris','paid','2025-08-27 07:24:22','ali agrem',NULL,'','dinein','',12,12960,5,5400,NULL,NULL,NULL),(72,4,6,210600,'cash','paid','2025-08-27 07:30:37','',NULL,'','dinein','',12,21600,5,9000,NULL,NULL,NULL),(77,4,6,84240,'qris','paid','2025-08-27 10:03:16','',NULL,NULL,'dinein','',12,8640,5,3600,NULL,NULL,NULL),(80,4,6,84240,'credit','credit','2025-08-28 02:01:30','',114,'AGAM RIDWAN PUTRA (SPV CAFE BATU)','takeaway','',12,8640,5,3600,NULL,NULL,NULL),(81,4,6,42120,'credit','paid','2025-08-28 03:29:02','',15,'TESSA NUR ALIFAH (M. KREDIT)','takeaway','',12,4320,5,1800,NULL,NULL,NULL),(82,4,6,42120,'cash','paid','2025-08-28 04:35:31','',NULL,NULL,'dinein','',12,4320,5,1800,NULL,NULL,NULL),(83,7,8,20000,'cash','paid','2025-09-06 06:36:40','Kade',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(84,7,8,20000,'cash','paid','2025-09-06 06:40:27','bu popppy',NULL,NULL,'dinein','1',0,0,0,0,NULL,NULL,NULL),(85,7,8,20000,'cash','paid','2025-09-06 06:41:03','ridwan',NULL,NULL,'dinein','1',0,0,0,0,NULL,NULL,NULL),(86,7,8,18000,'cash','paid','2025-09-06 06:41:24','PT karawang',NULL,NULL,'dinein','1',0,0,0,0,NULL,NULL,NULL),(87,7,8,22000,'cash','paid','2025-09-06 06:41:47','sam fajar',NULL,NULL,'dinein','1',0,0,0,0,NULL,NULL,NULL),(88,7,8,45000,'cash','paid','2025-09-06 07:01:17','Ami',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(89,7,8,20000,'cash','paid','2025-09-06 07:02:35','Sam',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(90,7,8,44000,'cash','paid','2025-09-06 07:04:50','sam',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(91,7,8,42000,'cash','paid','2025-09-06 09:04:31','Nanda',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(92,7,8,22000,'cash','paid','2025-09-06 09:56:44','',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(93,7,8,62000,'cash','paid','2025-09-06 10:31:13','Pak Ronal',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(94,7,8,22000,'cash','paid','2025-09-06 10:39:32','',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(95,7,8,62000,'cash','paid','2025-09-06 10:40:14','',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(96,7,8,132000,'cash','paid','2025-09-06 10:59:47','',NULL,NULL,'dinein','',0,0,0,0,NULL,NULL,NULL),(97,4,6,72000,'cash','paid','2025-09-06 11:20:02','syumi',NULL,NULL,'takeaway','',0,0,0,0,NULL,NULL,NULL),(99,7,8,42700,'cash','paid','2025-09-15 07:24:41','andri',NULL,NULL,'dinein','',12,4200,10,3500,NULL,NULL,NULL),(100,7,8,18300,'credit','paid','2025-09-15 07:25:48','',98,'ADIS SOPANDI (KOKI RM SUNDA)','dinein','',12,1800,10,1500,NULL,NULL,NULL),(101,7,8,21960,'cash','paid','2025-09-15 07:26:25','',NULL,NULL,'dinein','',12,2160,10,1800,NULL,NULL,NULL),(102,7,8,21960,'cash','paid','2025-09-15 07:26:32','',NULL,NULL,'dinein','',12,2160,10,1800,NULL,NULL,NULL),(103,7,8,21960,'cash','paid','2025-09-15 07:30:22','',NULL,NULL,'dinein','',12,2160,10,1800,NULL,NULL,NULL),(104,7,8,18300,'cash','paid','2025-09-15 07:35:46','',NULL,NULL,'dinein','',12,1800,10,1500,NULL,NULL,NULL),(105,7,8,18300,'cash','paid','2025-09-15 07:36:10','',NULL,NULL,'dinein','',12,1800,10,1500,NULL,NULL,NULL),(106,7,8,48800,'cash','paid','2025-09-15 07:36:31','',NULL,NULL,'dinein','',12,4800,10,4000,NULL,NULL,NULL),(107,7,8,18300,'cash','paid','2025-09-15 07:41:34','',NULL,NULL,'dinein','',12,1800,10,1500,NULL,NULL,NULL),(108,7,8,18300,'qris','paid','2025-09-15 07:43:23','',NULL,NULL,'dinein','',12,1800,10,1500,NULL,NULL,NULL),(109,9,8,21960,'cash','paid','2025-09-15 09:06:31','',NULL,NULL,'dinein','',12,2160,10,1800,NULL,NULL,NULL),(110,7,8,24400,'cash','paid','2025-09-15 09:12:06','',NULL,NULL,'dinein','',12,2400,10,2000,NULL,NULL,NULL),(111,7,8,21960,'cash','paid','2025-09-15 09:22:19','',NULL,NULL,'dinein','',12,2160,10,1800,NULL,NULL,NULL),(112,7,8,48800,'cash','paid','2025-09-15 09:34:43','',NULL,NULL,'dinein','',12,4800,10,4000,NULL,NULL,NULL),(113,7,8,48800,'cash','paid','2025-09-15 09:38:02','',NULL,NULL,'dinein','',12,4800,10,4000,NULL,NULL,NULL),(114,7,8,48800,'credit','paid','2025-09-15 09:56:18','',148,'ADELYA S (BARISTA)','dinein','',12,4800,10,4000,NULL,NULL,NULL),(115,7,8,24400,'cash','paid','2025-09-15 09:56:44','agus',NULL,NULL,'dinein','',12,2400,10,2000,NULL,NULL,NULL),(116,4,6,43920,'credit','paid','2025-09-15 10:05:25','',148,'ADELYA S (BARISTA)','takeaway','',12,4320,10,3600,NULL,NULL,NULL),(117,4,6,43920,'cash','paid','2025-09-15 10:07:35','',NULL,NULL,'dinein','',12,4320,10,3600,NULL,NULL,NULL),(118,4,6,43920,'qris','paid','2025-09-15 10:07:43','',NULL,NULL,'dinein','',12,4320,10,3600,NULL,NULL,NULL),(119,7,8,48800,'credit','paid','2025-09-17 03:05:09','',125,'ALI MANSUR HIDAYAT (BARISTA)','dinein','',12,4800,10,4000,NULL,NULL,NULL),(120,7,8,213500,'credit','paid','2025-09-17 03:20:24','',15,'TESSA NUR ALIFAH (M. KREDIT)','dinein','',12,21000,10,17500,NULL,NULL,NULL),(121,7,8,286700,'cash','paid','2025-09-17 03:57:31','juno',NULL,NULL,'takeaway','',12,28200,10,23500,NULL,NULL,NULL),(122,7,8,46360,'credit','paid','2025-09-17 09:00:26','',114,'AGAM RIDWAN PUTRA (SPV CAFE BATU)','dinein','',12,4560,10,3800,NULL,NULL,NULL),(123,7,8,46360,'credit','credit','2025-09-18 06:28:05','',114,'AGAM RIDWAN PUTRA (SPV CAFE BATU)','dinein','',12,4560,10,3800,NULL,NULL,NULL),(124,7,8,21960,'qris','paid','2025-09-23 02:31:53','agus',NULL,NULL,'dinein','',12,2160,10,1800,NULL,NULL,NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `price` int(11) NOT NULL,
  `hpp` int(11) NOT NULL DEFAULT 0,
  `image` varchar(200) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `category` varchar(50) NOT NULL DEFAULT 'other',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `fk_products_branch` (`branch_id`),
  CONSTRAINT `fk_products_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,6,'MAKANAN-NASGOR','Nasi Goreng',15000,0,'68a344950368a.jpg',0,'food'),(5,7,'MAKANAN-AYAM-BAKAR','Ayam Bakar Khas',25000,0,'68a36e2eb3513.jpg',1,'other'),(6,6,'MAKANAN-NASGOR-KAMPUNG','Nasi Goreng Kampung',20000,5000,'68a375de06fee.jpg',0,'food'),(8,9,'MOCK-PASSION-MARKISA','Mocktail Passion Fruit Markisa',30000,0,'68a6a0e1a345b.jpg',1,'other'),(9,6,'MINUMAN-ES-TEH','Es Teh Manis',5000,3000,'68a816768834e.png',0,'drink'),(10,6,'ROKOK-DJARUM','LA PURPLE',36000,25000,'68ae75282f4cb.jpg',0,'other'),(12,6,'MINUMAN-ES-TEH-MANIS','Es Teh Manis',5000,2000,'68ae89f933373.jpg',0,'drink'),(13,8,'KOPI_AMER_ICE','Americano Ice',20000,10000,'68bbd40f8ea40.png',1,'drink'),(14,8,'KOPI_AMER_HOT','Americano Hot',18000,10000,'68d21fa9a4103.jpg',1,'drink'),(15,8,'KOPI_V60','V60',20000,10000,'68d21fcbf0c12.jpg',1,'drink'),(16,8,'KOPI_MILK','Ice Coffe Milk De Oneal',22000,10000,'68d2202ee22ba.jpg',1,'drink'),(17,8,'COKLAT_ICE','Ice Chocolate',20000,10000,'68bbd445baf89.png',1,'drink'),(18,8,'COKLAT_HOT','Hot Chocolate',18000,10000,'68d21ffa630ce.jpg',1,'drink'),(19,8,'MILKSHAKE_OREO','Milkshake Oreo',22000,10000,'68bbd4799cf59.png',1,'drink'),(20,8,'KOPI_JAPANESE','Japanese',20000,10000,'68d2200b31fda.jpg',1,'drink'),(22,8,'ESPRESSO','espresso',15000,10000,'68bbdbf489216.png',1,'drink'),(23,6,'MIE-ORG-001','Mie Goreng Original',8000,3939,'68c92c3eec47e.jpg',1,'food'),(24,6,'MIE-AYG-002','Mie Goreng Ayam Geprek',8000,3939,'68c92c7626ff6.jpg',1,'food'),(25,6,'MIE-RDG-003','Mie Goreng Rendang',8000,3914,'68c92cb13ee8c.jpg',1,'food'),(26,6,'MIE-RIC-004','Mie Goreng Rica-Rica',3939,8000,'68c92cd86403a.jpg',1,'food'),(27,6,'MIE-IGA-005','Mie Goreng Iga Penyet',8000,3939,'68c92d09c11cc.jpg',1,'food'),(28,6,'MIE-AYB-006','Mie Rebus Ayam Bawang',8000,3789,'68c92d4038812.jpeg',1,'food'),(29,6,'MIE-SOT-007','Mie Rebus Soto',8000,3789,'68c92d637c102.jpeg',1,'food'),(30,6,'MIE-EMP-008','Mie Rebus Empal Gentong',8000,3939,'68c92d8f6df3f.jpeg',1,'food'),(31,6,'MIE-KOC-009','Mie Rebus Kocok Bandung',8000,3939,'68c92df054062.jpeg',1,'food'),(32,6,'MIE-PAB-010','Pop Mie Ayam Bawang',10000,5306,'68c92e605863b.jpg',1,'food'),(33,6,'MIE-PBS-011','Pop Mie Baso',10000,5264,'68c92e8fc7fa2.jpg',1,'food'),(34,6,'MIE-PDW-012','Pop Mie Pedas Dower',10000,5847,'68c92eb84f40e.jpg',1,'food'),(35,6,'MIE-PGR-013','Pop Mie Goreng',10000,5597,'68c92ed5ddd50.jpg',1,'food'),(36,6,'MIE-PGL-014','Pop Mie Pedas Gledek',10000,5847,'68c92ef01c8da.jpg',1,'food'),(37,6,'ROK-JAZ-001','Jazy Kretek',11000,8500,'68c93307e9e71.jpg',1,'other'),(38,6,'ROK-NES-002','Neslite Max 12',19000,15600,'68c93348d3f7b.webp',1,'other'),(39,6,'ROK-LUC-003','Lucky Strike',32000,28300,'68c933bbe28d2.jpg',1,'other'),(40,6,'ROK-MLD-004','MLD Fresh Cola',33000,29600,'68c934053fc37.jpg',1,'other'),(41,6,'ROK-DJI-005','DJI SAM SOE REFILL 12',26000,21200,'68c934552025a.jpg',1,'other'),(42,6,'ROK-MAG-006','Magnum Filter',28000,25200,'68c934a7924b2.jpg',1,'other'),(43,6,'ROK-DUN-007','Dunhil Putih 16',33000,29250,'68c934e42da86.jpg',1,'other'),(44,6,'ROK-GGS-008','Gudang Garam Surya 12',28000,24600,'68c9352c04c5f.jpg',1,'other'),(45,6,'ROK-GGF-009','Gudang Garam Filter',27000,24600,'68c9356e8baab.jpg',1,'other'),(46,6,'ROK-GG1-010','Gudang Garam Surya 16',37000,33600,'68c935b33f4c5.jpg',1,'other'),(47,6,'ROK-ESD-011','Esse Double Change',48000,41500,'68c936052fc4c.jpg',1,'other'),(48,6,'ROK-DJC-012','Djarum Coklat',19000,15450,'68c9363bc0a3a.jpg',1,'other'),(49,6,'ROK-DJS-013','Djarum Super',26000,22800,'68c936891c3a0.jpg',1,'other'),(50,6,'ROK-LAP-014','LA Purple',37000,32950,'68c936c50b685.jpg',1,'other'),(51,6,'ROK-SAM-015','Sampoerna Mild',39000,34100,'68c936f4c6de1.jpg',1,'other'),(52,6,'ROK-DJM-016','Djarum Coklat Extra Mocca',19000,16100,'68c9373b800d3.jpg',1,'other'),(53,6,'MK-UMS-001','Ultra Milk Strawberry',9000,4583,'68ca15bca9cf6.jpg',1,'drink'),(54,6,'MK-UMC-002','Ultra Milk Coklat',9000,4583,'68ca15da3ad79.jpg',1,'drink'),(55,6,'MK-UMF-003','Ultra Milk Full Cream',9000,4583,'68ca162c1d397.jpg',1,'drink'),(56,6,'MK-MLS-004','Milku Strawberry',6000,2917,'68ca1672bf82c.jpg',1,'drink'),(57,6,'MK-MLC-005','Milku Coklat',6000,2917,'68ca1696153cf.jpg',1,'drink'),(58,6,'MK-MLF-006','Milku Full Cream',6000,2917,'68ca16b74e4ba.jpg',1,'drink'),(59,6,'MK-NIP-007','Nipis Madu',7000,3250,'68ca178ae42da.jpg',1,'drink'),(60,6,'MK-TB3-008','Teh Botol 350 ML',7000,3458,'68ca18c82865e.jpg',1,'drink'),(61,6,'MK-TB2-009','Teh Botol 250 ML',5000,2396,'68ca18f3d4a01.jpg',1,'drink'),(62,6,'MK-SPR-010','Sprite 250 ML',6000,3000,'68ca1940cfad6.jpg',1,'drink'),(63,6,'MK-FAN-011','Fanta 250 ML',6000,3000,'68ca19891e277.jpg',1,'drink'),(64,6,'MK-COC-012','Coca cola 250 ML',6000,3000,'68ca19cd63756.jpg',1,'drink'),(65,6,'MK-FTL-013','Fruit Tea Lemon',7000,3458,'68ca1a1646634.jpg',1,'drink'),(66,6,'MK-FTM-014','Fruit Tea Markisa',7000,3458,'68ca1a36197cb.jpg',1,'drink'),(67,6,'MK-FTS-015','Fruit Tea Strawberry',7000,3458,'68ca1a5aabcfc.jpg',1,'drink'),(68,6,'MK-KRA-016','Kratindaeng',8000,4900,'68ca1b0a9d2dd.jpg',1,'drink'),(69,6,'MK-MIC-018','Mizone Canberry',7000,3958,'68ca1b8cf1dae.jpg',1,'drink'),(70,6,'MK-MIL-017','Mizone Lychee',7000,3958,'68ca1c2aae4bb.jpg',1,'drink'),(71,6,'MK-POC-019','Pocari Sweat',8500,5417,'68ca1cc08c8f4.jpg',1,'drink'),(72,6,'MK-GDM-020','Good Day Funtastic Mocacinno',8500,5417,'68ca1d4342dac.jpg',1,'drink'),(73,6,'MK-GDC-021','Good Day Funtastic Cappucino',8500,5417,'68ca1d6c73aca.jpg',1,'drink'),(74,6,'MK-NCM-022','Nescafe Caramel Macchiato',9000,5979,'68ca1dfe1028c.jpg',1,'drink'),(75,6,'MK-NIB-023','Nescafe Ice Black',9000,5979,'68ca1e2149f2f.jpg',1,'drink'),(76,6,'MK-NCP-024','Nescafe Cappucino',9000,5979,'68ca1ec07c5bb.jpg',1,'drink'),(77,6,'MK-NLT-025','Nescafe Latte',9000,5979,'68ca1f0b85ffa.jpg',1,'drink'),(78,6,'MK-HYC-026','Hydro Coco',9000,5896,'68ca1f8bb311b.jpg',1,'drink'),(79,6,'MK-YC5-027','You C1000 500 ML',11000,6500,'68ca206eece09.jpg',1,'drink'),(80,6,'MK-YCK-028','You C1000 140 ML',10000,5417,'68ca20d072ceb.jpg',1,'drink'),(81,6,'MK-AQ6-029','Aqua Botol 600 ML',5000,1917,'68ca21315584e.jpg',1,'drink'),(82,6,'MK-AQ3-030','Aqua Botol 330 ML',3500,1542,'68ca216b6e372.jpg',1,'drink'),(83,6,'MK-ADC-031','Adem Sari Chinku',9000,5938,'68ca228140fe9.jpg',1,'drink'),(84,6,'CM-QTE-001','Qtela',5000,1666,'68d1fb5ee7726.jpg',1,'food'),(85,6,'CM-CHI-002','Chitato Lite',5000,1683,'68d1fbb61630b.jpg',1,'food'),(86,6,'CM-KAG-003','Kacang Garuda',5000,1650,'68d1fbdb16040.jpg',1,'food'),(87,6,'CM-TAR-004','Taro',5000,1687,'68d1fc92b3c84.jpg',1,'food'),(88,6,'CM-BEB-005','Beng-Beng',3000,1088,'68d1fcb731773.jpg',1,'food'),(89,6,'CM-FRF-006','French Fries 2000',5000,1872,'68d1fd02c8f15.jpg',1,'food'),(90,6,'CM-TTC-007','Tic-Tac',5000,1725,'68d1fd3182be8.jpg',1,'food'),(91,6,'CM-ROS-008','Garuda Rosta',3000,850,'68d1fdb04b1a0.jpg',1,'food'),(92,6,'CM-POC-009','Pocky Coklat',1750,5000,'68d1fdd37fa08.jpg',1,'food'),(93,6,'CM-POS-010','Pocky Strawberry',5000,1750,'68d1fdee19b1a.jpg',1,'food'),(94,6,'CM-CCO-011','Chocolatos Coklat',3000,833,'68d1fe334ed7a.jpg',1,'food'),(95,6,'CM-CKE-012','Chocolatos Keju',3000,833,'68d1fe504ec08.jpg',1,'food'),(96,6,'CM-WFN-013','Wafer Nabati',3000,1687,'68d1fe8678d3a.jpg',1,'food'),(97,6,'CM-ORC-014','Oreo Cake',6000,2791,'68d1fea32e820.jpg',1,'food'),(98,6,'CM-KAT-015','Kacang Atom',5000,1700,'68d1feb9464e9.jpg',1,'food'),(99,6,'FD-NGB-001','Nasi Goreng Biasa',15000,6517,'68d203c8c3405.jpg',1,'food'),(100,6,'FD-NGS-002','Nasi Goreng Spesial',20000,8560,'68d203e6dfffb.jpg',1,'food'),(101,6,'FD-MTT-003','Mie Tek Tek',15000,6767,'68d2041680ab4.jpg',1,'food'),(102,6,'FD-MTS-004','Mie Tek Tek Spesial',20000,9162,'68d2043ac5f05.jpg',1,'food'),(103,6,'FD-MIG-005','Mie Goreng',15000,6767,'68d2045d077b9.jpg',1,'food'),(104,6,'FD-MGS-006','Mie Goreng Spesial',20000,9162,'68d20480246b2.jpg',1,'food'),(105,6,'FD-KWB-007','Kwetiaw Goreng',15000,9545,'68d204ae92b1a.jpg',1,'food'),(106,6,'FD-KWS-008','Kwetiaw Goreng Spesial',20000,9545,'68d204d4d8f71.jpg',1,'food'),(107,6,'FD-TLD-009','Telur Dadar',5000,2350,'68d204ef64bb0.jpg',1,'food'),(108,6,'FD-NAS-010','Nasi',5000,2300,'68d2050ed1783.jpg',1,'food');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `permission` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,'kasir','pos.use'),(2,'kasir','order.create'),(3,'kasir','order.cancel.request'),(4,'spv','order.cancel.approve'),(5,'spv','inventory.view'),(6,'spv_warehouse','inventory.view.all'),(7,'admin','*'),(8,'superadmin','*');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_branch`
--

DROP TABLE IF EXISTS `stock_branch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_branch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_branch` (`product_id`,`branch_id`),
  UNIQUE KEY `product_branch_unique` (`product_id`,`branch_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `stock_branch_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_branch_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_branch`
--

LOCK TABLES `stock_branch` WRITE;
/*!40000 ALTER TABLE `stock_branch` DISABLE KEYS */;
INSERT INTO `stock_branch` VALUES (1,10,6,7,'2025-09-15 10:07:43'),(3,1,1,10,'2025-08-27 03:53:08'),(8,6,6,15,'2025-09-15 09:00:37'),(11,12,6,15,'2025-09-15 09:00:25'),(28,14,8,8,'2025-09-18 06:28:05'),(29,13,8,11,'2025-09-18 06:28:05'),(30,18,8,9,'2025-09-23 02:31:53'),(31,17,8,13,'2025-09-17 09:00:26'),(32,16,8,15,'2025-09-17 03:57:31'),(33,19,8,12,'2025-09-17 03:57:31'),(35,15,8,8,'2025-09-17 03:57:31'),(41,20,8,12,'2025-09-17 03:57:31'),(43,22,8,4,'2025-09-17 03:57:31');
/*!40000 ALTER TABLE `stock_branch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_branch_history`
--

DROP TABLE IF EXISTS `stock_branch_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_branch_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `type` enum('in','out') NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sbh_product` (`product_id`),
  KEY `fk_sbh_branch` (`branch_id`),
  KEY `fk_sbh_user` (`user_id`),
  CONSTRAINT `fk_sbh_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sbh_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sbh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_branch_history`
--

LOCK TABLES `stock_branch_history` WRITE;
/*!40000 ALTER TABLE `stock_branch_history` DISABLE KEYS */;
INSERT INTO `stock_branch_history` VALUES (1,10,6,1,'out','Penjualan Order #51',4,'2025-08-27 03:58:36'),(2,10,6,1,'out','Penjualan Order #52',4,'2025-08-27 04:13:15'),(3,6,6,1,'out','Penjualan Order #53',4,'2025-08-27 04:19:16'),(4,6,6,5,'out','Penjualan Order #54',4,'2025-08-27 04:19:34'),(5,6,6,1,'out','Penjualan Order #55',4,'2025-08-27 04:26:53'),(6,12,6,1,'out','Penjualan Order #56',4,'2025-08-27 04:31:09'),(7,10,6,1,'out','Penjualan Order #56',4,'2025-08-27 04:31:09'),(8,6,6,1,'out','Penjualan Order #56',4,'2025-08-27 04:31:09'),(9,10,6,1,'out','Penjualan Order #70',4,'2025-08-27 06:43:13'),(10,12,6,1,'out','Penjualan Order #70',4,'2025-08-27 06:43:13'),(11,10,6,3,'out','Penjualan Order #71',4,'2025-08-27 07:24:22'),(12,10,6,5,'out','Penjualan Order #72',4,'2025-08-27 07:30:37');
/*!40000 ALTER TABLE `stock_branch_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `branch_id` (`branch_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,10,6,'in',20,'','2025-08-27 03:48:42',4),(2,10,6,'out',5,'Penjualan POS - Order #72','2025-08-27 07:30:37',4),(7,10,6,'out',2,'Penjualan POS - Order #77','2025-08-27 10:03:16',4),(10,10,6,'out',2,'Penjualan Kredit - Order #80','2025-08-28 02:01:30',4),(11,10,6,'out',1,'Penjualan Kredit - Order #81','2025-08-28 03:29:02',4),(12,10,6,'out',1,'Penjualan POS - Order #82','2025-08-28 04:35:31',4),(13,14,8,'in',15,'','2025-09-06 06:33:06',2),(14,13,8,'in',15,'','2025-09-06 06:33:27',2),(15,18,8,'in',15,'','2025-09-06 06:33:38',2),(16,17,8,'in',15,'','2025-09-06 06:33:44',2),(17,16,8,'in',15,'','2025-09-06 06:33:54',2),(18,19,8,'in',15,'','2025-09-06 06:34:07',2),(20,15,8,'in',15,'','2025-09-06 06:36:08',2),(21,15,8,'out',1,'Penjualan POS - Order #83 (Customer: Kade)','2025-09-06 06:36:40',7),(22,13,8,'out',1,'Penjualan POS - Order #84 (Customer: bu popppy) (Meja: 1)','2025-09-06 06:40:27',7),(23,13,8,'out',1,'Penjualan POS - Order #85 (Customer: ridwan) (Meja: 1)','2025-09-06 06:41:03',7),(24,14,8,'out',1,'Penjualan POS - Order #86 (Customer: PT karawang) (Meja: 1)','2025-09-06 06:41:24',7),(25,19,8,'out',1,'Penjualan POS - Order #87 (Customer: sam fajar) (Meja: 1)','2025-09-06 06:41:47',7),(26,20,8,'in',15,'','2025-09-06 06:43:23',2),(28,22,8,'in',15,'','2025-09-06 07:00:28',2),(29,22,8,'out',3,'Penjualan POS - Order #88 (Customer: Ami)','2025-09-06 07:01:17',7),(30,20,8,'out',1,'Penjualan POS - Order #89 (Customer: Sam)','2025-09-06 07:02:35',7),(31,16,8,'out',2,'Penjualan POS - Order #90 (Customer: sam)','2025-09-06 07:04:50',7),(32,13,8,'out',1,'Penjualan POS - Order #91 (Customer: Nanda)','2025-09-06 09:04:31',7),(33,16,8,'out',1,'Penjualan POS - Order #91 (Customer: Nanda)','2025-09-06 09:04:31',7),(34,16,8,'out',1,'Penjualan POS - Order #92','2025-09-06 09:56:44',7),(35,13,8,'out',2,'Penjualan POS - Order #93 (Customer: Pak Ronal)','2025-09-06 10:31:13',7),(36,16,8,'out',1,'Penjualan POS - Order #93 (Customer: Pak Ronal)','2025-09-06 10:31:13',7),(37,16,8,'out',1,'Penjualan POS - Order #94','2025-09-06 10:39:32',7),(38,13,8,'out',2,'Penjualan POS - Order #95','2025-09-06 10:40:14',7),(39,16,8,'out',1,'Penjualan POS - Order #95','2025-09-06 10:40:14',7),(40,16,8,'out',6,'Penjualan POS - Order #96','2025-09-06 10:59:47',7),(41,10,6,'out',2,'Penjualan POS - Order #97 (Customer: syumi)','2025-09-06 11:20:02',4),(42,13,8,'out',1,'Penjualan POS - Order #98','2025-09-11 10:33:04',7),(43,22,8,'out',1,'Penjualan POS - Order #99 (Customer: andri)','2025-09-15 07:24:41',7),(44,13,8,'out',1,'Penjualan POS - Order #99 (Customer: andri)','2025-09-15 07:24:41',7),(45,22,8,'out',1,'Penjualan Kredit - Order #100','2025-09-15 07:25:48',7),(46,14,8,'out',1,'Penjualan POS - Order #101','2025-09-15 07:26:25',7),(47,14,8,'out',1,'Penjualan POS - Order #102','2025-09-15 07:26:32',7),(48,14,8,'out',1,'Penjualan POS - Order #103','2025-09-15 07:30:22',7),(49,22,8,'out',1,'Penjualan POS - Order #104','2025-09-15 07:35:46',7),(50,22,8,'out',1,'Penjualan POS - Order #105','2025-09-15 07:36:10',7),(51,13,8,'out',2,'Penjualan POS - Order #106','2025-09-15 07:36:31',7),(52,22,8,'out',1,'Penjualan POS - Order #107','2025-09-15 07:41:34',7),(53,22,8,'out',1,'Penjualan POS - Order #108','2025-09-15 07:43:23',7),(54,13,8,'in',15,'','2025-09-15 08:23:52',7),(55,13,8,'in',15,'','2025-09-15 08:24:11',7),(57,16,8,'in',15,'tambah stock 15 september 2025','2025-09-15 08:27:09',7),(58,10,6,'in',10,'Update stock 15 september 2025','2025-09-15 09:00:07',4),(59,12,6,'in',15,'Update stock 15 september 2025','2025-09-15 09:00:25',4),(60,6,6,'in',15,'Update stock 15 september 2025','2025-09-15 09:00:37',4),(61,18,8,'out',1,'Penjualan POS - Order #109','2025-09-15 09:06:31',9),(62,13,8,'out',1,'Penjualan POS - Order #110','2025-09-15 09:12:06',7),(63,14,8,'out',1,'Penjualan POS - Order #111','2025-09-15 09:22:19',7),(64,13,8,'out',2,'Penjualan POS - Order #112','2025-09-15 09:34:43',7),(65,13,8,'out',2,'Penjualan POS - Order #113','2025-09-15 09:38:02',7),(66,13,8,'out',2,'Penjualan Kredit - Order #114','2025-09-15 09:56:18',7),(67,13,8,'out',1,'Penjualan POS - Order #115','2025-09-15 09:56:44',7),(68,10,6,'out',1,'Penjualan Kredit - Order #116','2025-09-15 10:05:25',4),(69,10,6,'out',1,'Penjualan POS - Order #117','2025-09-15 10:07:35',4),(70,10,6,'out',1,'Penjualan POS - Order #118','2025-09-15 10:07:43',4),(71,13,8,'out',10,'kelebihan','2025-09-17 03:02:39',7),(72,13,8,'out',2,'Penjualan Kredit - Order #119','2025-09-17 03:05:09',7),(73,14,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(74,13,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(75,22,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(76,18,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(77,17,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(78,16,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(79,20,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(80,19,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(81,15,8,'out',1,'Penjualan Kredit - Order #120','2025-09-17 03:20:24',7),(82,22,8,'out',1,'Penjualan POS - Order #121','2025-09-17 03:57:31',7),(83,13,8,'out',1,'Penjualan POS - Order #121','2025-09-17 03:57:31',7),(84,18,8,'out',2,'Penjualan POS - Order #121','2025-09-17 03:57:31',7),(85,15,8,'out',5,'Penjualan POS - Order #121','2025-09-17 03:57:31',7),(86,19,8,'out',1,'Penjualan POS - Order #121','2025-09-17 03:57:31',7),(87,20,8,'out',1,'Penjualan POS - Order #121','2025-09-17 03:57:31',7),(88,16,8,'out',1,'Penjualan POS - Order #121','2025-09-17 03:57:31',7),(89,18,8,'out',1,'Penjualan Kredit - Order #122','2025-09-17 09:00:26',7),(90,17,8,'out',1,'Penjualan Kredit - Order #122','2025-09-17 09:00:26',7),(91,14,8,'out',1,'Penjualan Kredit - Order #123','2025-09-18 06:28:05',7),(92,13,8,'out',1,'Penjualan Kredit - Order #123','2025-09-18 06:28:05',7),(93,18,8,'out',1,'Penjualan POS - Order #124','2025-09-23 02:31:53',7);
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `targets`
--

DROP TABLE IF EXISTS `targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `target_month` date DEFAULT NULL,
  `target_amount` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `targets`
--

LOCK TABLES `targets` WRITE;
/*!40000 ALTER TABLE `targets` DISABLE KEYS */;
/*!40000 ALTER TABLE `targets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('kasir','spv','admin','superadmin','spv_warehouse') NOT NULL DEFAULT 'kasir',
  `branch_id` int(11) DEFAULT 1,
  `totp_secret` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Super Admin','admin@example.com','$2y$10$A1cQ6k2J3s6g8I2o8Xk1pO9sC2mH8mK2Y6k9u4e3fG5d7s8u9vWcG','superadmin',1,NULL,'2025-08-18 14:11:57'),(2,'ADMIN1','admin@pos.com','$2y$10$gAqiLCaDPcXobgQqffluauh/nEcle4/sn5QUGTVaVg/9CVunWCEHm','superadmin',1,NULL,'2025-08-18 14:38:57'),(4,'Agam Suragam','agambatu@tep.com','$2y$10$esQ3.txEBB/KQVSrA1nIteXrZACPDo3ylyPds9AJhlGrR7d2ecFfe','spv',6,NULL,'2025-08-18 17:34:24'),(6,'oppa','oppa@tep.com','$2y$10$ayFt5MWnwxyX8YfoGtqa8u0uhAMkLZ0aG.cD.//qe9wq44e1crSBG','kasir',6,NULL,'2025-08-18 18:15:44'),(7,'Sam Fajar','sam@tep.com','$2y$10$9P99FRM8lyjljTP3OL9mwu0TkDX.3jzGkrmXNJhn/FWCGvSWijxE6','spv',8,NULL,'2025-08-19 02:33:52'),(8,'Cici','cici_sunda@pos.com','$2y$10$/TxykUpEmKUb7L3XR/0ZtusCos8ceyOqJf3aA47udoSvSRt1YOt.2','kasir',7,NULL,'2025-08-21 02:40:08'),(9,'Asep','asep_onel@pos.com','$2y$10$QxxtFnn.lPNZJP2IrBwJBunBsv5Wt/uLzccuTubHohbbLynWKB.Qu','kasir',8,NULL,'2025-08-21 02:44:41'),(10,'Bella','bella_oasis@tep.com','$2y$10$0GDLT.edFOcAlMzNhnE0ke5pAME6ryyvyqalUqlBKVn4ZGgl9lxeC','spv',9,NULL,'2025-08-21 04:31:29'),(11,'Ojuy','ojuy_oasis@tep.com','$2y$10$1Tn4g4t5y.5m/nFm9oK0kuGJNcObhEqfnzuYte5rLR2QvdI5e08.u','kasir',9,NULL,'2025-08-21 04:32:26'),(12,'Kiki_sunda','kiki_sunda@tep.com','$2y$10$/HFXMkjarWSO3ZfxD07mVeSU/oq5aRLs.ykpFAVXoj2a6XS65QvHG','spv',7,NULL,'2025-08-21 04:46:07');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouse_stock`
--

DROP TABLE IF EXISTS `warehouse_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouse_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `sku` varchar(60) NOT NULL,
  `name` varchar(160) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT 'pcs',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_warehouse_branch_sku` (`branch_id`,`sku`),
  CONSTRAINT `warehouse_stock_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouse_stock`
--

LOCK TABLES `warehouse_stock` WRITE;
/*!40000 ALTER TABLE `warehouse_stock` DISABLE KEYS */;
INSERT INTO `warehouse_stock` VALUES (1,1,'BAHAN-GULA','Gula Pasir',20,'Kg','2025-08-19 07:01:16');
/*!40000 ALTER TABLE `warehouse_stock` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-23 13:01:57
