-- --------------------------------------------------------
-- Host:                         192.168.100.2
-- Server version:               8.0.45 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.15.0.7171
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for app
CREATE DATABASE IF NOT EXISTS `app` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `app`;

-- Dumping structure for table app.doctrine_migration_versions
CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Dumping data for table app.doctrine_migration_versions: ~1 rows (approximately)
DELETE FROM `doctrine_migration_versions`;
INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
	('DoctrineMigrations\\Version20260217100651', '2026-02-17 10:08:21', 163);

-- Dumping structure for table app.messenger_messages
CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table app.messenger_messages: ~0 rows (approximately)
DELETE FROM `messenger_messages`;

-- Dumping structure for table app.user
CREATE TABLE IF NOT EXISTS `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table app.user: ~11 rows (approximately)
DELETE FROM `user`;
INSERT INTO `user` (`id`, `email`, `roles`, `password`, `first_name`) VALUES
	(23, 'abraca_admin@example.com', '[]', '$2y$13$N3I2aIgvatro1/19dX0yeOmb/e.ib9BvsOkXqy2x5r5MBzQmyQ7na', 'William'),
	(24, 'mstehr@hotmail.com', '[]', '$2y$13$OZaJ.sCUC7JeysiZL7jlDuQ457e.YM9YHTjs9TbLv1bEFJsHKSK7S', 'Willis'),
	(25, 'bell76@veum.org', '[]', '$2y$13$PKJ29WfEDlJ2D9qS84TDS.xSdonP6qIOnnNS1Ks.0g.WMibqCtc/2', 'Ansel'),
	(26, 'emily.shanahan@gaylord.com', '[]', '$2y$13$rZ4Hnu9fwzD2.rIpmDXcbOWjYau63VTVM1sTYV5hNYY.m9JsmoyA6', 'Geraldine'),
	(27, 'aubree82@hotmail.com', '[]', '$2y$13$4zgwTVDSbsclaDGdMAcRG.o2SQu6f6/9OkI5vvlprGcTX85uCF9IW', 'Chelsey'),
	(28, 'heaven.mann@little.com', '[]', '$2y$13$Zc9KgwkIhQSg8zG4hyNhsuJXAp7Uq0s8WFeSVlxYHp8TZ5HjCprgK', 'Royce'),
	(29, 'bertha.jacobi@reichert.com', '[]', '$2y$13$vTlwZA918sxipYyehMoJaOosFyMwFklmaRca5HK3JE5Q2UW9b2b4m', 'Sofia'),
	(30, 'anastasia.morar@hotmail.com', '[]', '$2y$13$2JCrcCJ4mZroApDWB6/OEO4oVnt0uLqSBf9X2Ig6CTrXL1SzffAjS', 'Bernadette'),
	(31, 'chackett@haag.com', '[]', '$2y$13$bUdsyoGfrgxwNNMzPJdra.A3BuN8mGkzkJU9zPxizhzuiH0S7rCvS', 'Lane'),
	(32, 'fdurgan@luettgen.biz', '[]', '$2y$13$amOJ.OoXVdzN.iksBJ/HAeCY1QXu//kyCy6fSwJwz3xqqNqIjbHNa', 'Alessandro'),
	(33, 'judson.johns@yahoo.com', '[]', '$2y$13$B0JCdb4B21nxhFpKpWNxi.wMT/dUdduOYam26hZe31kB6r6rstn4W', 'Mack');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
