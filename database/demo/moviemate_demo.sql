
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `actor_role_snapshot` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safe_ip_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent_summary` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `before_data` json DEFAULT NULL,
  `after_data` json DEFAULT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_subject_time_index` (`subject_type`,`subject_id`,`created_at`),
  KEY `activity_logs_actor_user_id_index` (`actor_user_id`),
  KEY `activity_logs_action_index` (`action`),
  KEY `activity_logs_subject_type_index` (`subject_type`),
  KEY `activity_logs_subject_id_index` (`subject_id`),
  KEY `activity_logs_request_id_index` (`request_id`),
  KEY `activity_logs_route_name_index` (`route_name`),
  KEY `activity_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `activity_logs_prevent_update` BEFORE UPDATE ON `activity_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs are append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `activity_logs_prevent_delete` BEFORE DELETE ON `activity_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs are append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `ai_chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_chats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `response` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_chats_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_chats_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_chats` WRITE;
/*!40000 ALTER TABLE `ai_chats` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_chats` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `ai_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `input_data` json DEFAULT NULL,
  `result_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_recommendations_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_recommendations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_recommendations` WRITE;
/*!40000 ALTER TABLE `ai_recommendations` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_recommendations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `booking_discount_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_discount_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `discount_code_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `code_snapshot` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type_snapshot` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value_snapshot` bigint unsigned NOT NULL,
  `discount_amount` bigint unsigned NOT NULL,
  `subtotal_before` bigint unsigned NOT NULL,
  `subtotal_after` bigint unsigned NOT NULL,
  `status` enum('reserved','redeemed','released') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reserved',
  `reserved_at` timestamp NOT NULL,
  `redeemed_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_discount_codes_booking_id_discount_code_id_unique` (`booking_id`,`discount_code_id`),
  KEY `booking_discount_codes_user_id_foreign` (`user_id`),
  KEY `booking_discount_codes_discount_code_id_status_index` (`discount_code_id`,`status`),
  KEY `discount_user_quota_index` (`discount_code_id`,`user_id`,`status`),
  KEY `booking_discount_codes_status_index` (`status`),
  CONSTRAINT `booking_discount_codes_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_discount_codes_discount_code_id_foreign` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `booking_discount_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `booking_discount_codes` WRITE;
/*!40000 ALTER TABLE `booking_discount_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_discount_codes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `booking_point_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_point_redemptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `loyalty_account_id` bigint unsigned NOT NULL,
  `points` bigint unsigned NOT NULL,
  `point_value_vnd_snapshot` int unsigned NOT NULL DEFAULT '0',
  `discount_amount` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reserved',
  `reserved_at` timestamp NOT NULL,
  `redeemed_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `release_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_point_redemptions_booking_id_unique` (`booking_id`),
  KEY `booking_point_redemptions_loyalty_account_id_foreign` (`loyalty_account_id`),
  KEY `booking_point_redemptions_status_index` (`status`),
  CONSTRAINT `booking_point_redemptions_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_point_redemptions_loyalty_account_id_foreign` FOREIGN KEY (`loyalty_account_id`) REFERENCES `loyalty_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `booking_point_redemptions` WRITE;
/*!40000 ALTER TABLE `booking_point_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_point_redemptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `booking_seats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_seats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `showtime_id` bigint unsigned NOT NULL,
  `seat_id` bigint unsigned NOT NULL,
  `active_lock_key` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVE',
  `price` decimal(10,2) NOT NULL,
  `pricing_unit_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pricing_unit_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seat_type_snapshot` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_amount` bigint unsigned DEFAULT NULL,
  `surcharge_total` bigint DEFAULT NULL,
  `final_unit_amount` bigint unsigned DEFAULT NULL,
  `pricing_breakdown` json DEFAULT NULL,
  `pricing_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_seats_active_inventory_unique` (`showtime_id`,`seat_id`,`active_lock_key`),
  KEY `booking_seats_seat_id_foreign` (`seat_id`),
  KEY `booking_seats_showtime_seat_index` (`showtime_id`,`seat_id`),
  KEY `booking_seats_booking_showtime_foreign` (`booking_id`,`showtime_id`),
  CONSTRAINT `booking_seats_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_seats_booking_showtime_foreign` FOREIGN KEY (`booking_id`, `showtime_id`) REFERENCES `bookings` (`id`, `showtime_id`) ON DELETE CASCADE,
  CONSTRAINT `booking_seats_seat_id_foreign` FOREIGN KEY (`seat_id`) REFERENCES `seats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_seats_showtime_id_foreign` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_seats_active_lock_key_check` CHECK (((`active_lock_key` is null) or (`active_lock_key` = _utf8mb4'ACTIVE')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `booking_seats` WRITE;
/*!40000 ALTER TABLE `booking_seats` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_seats` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `booking_ticket_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_ticket_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `available_at` timestamp NULL DEFAULT NULL,
  `processing_started_at` timestamp NULL DEFAULT NULL,
  `lease_expires_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `last_error_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_ticket_deliveries_booking_id_unique` (`booking_id`),
  KEY `ticket_deliveries_claim_index` (`status`,`available_at`,`lease_expires_at`),
  CONSTRAINT `booking_ticket_deliveries_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `booking_ticket_deliveries` WRITE;
/*!40000 ALTER TABLE `booking_ticket_deliveries` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_ticket_deliveries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `booking_ticket_print_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_ticket_print_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_ticket_print_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `actor_role_snapshot` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_number` int unsigned NOT NULL,
  `operation_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safe_note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_print_events_operation_type_unique` (`operation_id`,`event_type`),
  KEY `booking_ticket_print_events_booking_ticket_print_id_foreign` (`booking_ticket_print_id`),
  KEY `booking_ticket_print_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `ticket_print_events_booking_id_index` (`booking_id`,`id`),
  KEY `booking_ticket_print_events_event_type_index` (`event_type`),
  KEY `booking_ticket_print_events_request_id_index` (`request_id`),
  CONSTRAINT `booking_ticket_print_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_ticket_print_events_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `booking_ticket_print_events_booking_ticket_print_id_foreign` FOREIGN KEY (`booking_ticket_print_id`) REFERENCES `booking_ticket_prints` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `booking_ticket_print_events` WRITE;
/*!40000 ALTER TABLE `booking_ticket_print_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_ticket_print_events` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `booking_ticket_print_events_prevent_update` BEFORE UPDATE ON `booking_ticket_print_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'booking_ticket_print_events are append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `booking_ticket_print_events_prevent_delete` BEFORE DELETE ON `booking_ticket_print_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'booking_ticket_print_events are append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `booking_ticket_prints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_ticket_prints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts_count` int unsigned NOT NULL DEFAULT '0',
  `printed_by_user_id` bigint unsigned DEFAULT NULL,
  `printed_at` timestamp NULL DEFAULT NULL,
  `last_failed_by_user_id` bigint unsigned DEFAULT NULL,
  `last_failed_at` timestamp NULL DEFAULT NULL,
  `last_failure_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_authorized_by_user_id` bigint unsigned DEFAULT NULL,
  `retry_authorized_at` timestamp NULL DEFAULT NULL,
  `active_operation_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active_operation_token_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active_operator_user_id` bigint unsigned DEFAULT NULL,
  `active_operation_expires_at` timestamp NULL DEFAULT NULL,
  `last_completed_operation_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_ticket_prints_booking_id_unique` (`booking_id`),
  UNIQUE KEY `booking_ticket_prints_active_operation_id_unique` (`active_operation_id`),
  KEY `booking_ticket_prints_printed_by_user_id_foreign` (`printed_by_user_id`),
  KEY `booking_ticket_prints_last_failed_by_user_id_foreign` (`last_failed_by_user_id`),
  KEY `booking_ticket_prints_retry_authorized_by_user_id_foreign` (`retry_authorized_by_user_id`),
  KEY `booking_ticket_prints_active_operator_user_id_foreign` (`active_operator_user_id`),
  KEY `booking_ticket_prints_status_index` (`status`),
  KEY `booking_ticket_prints_printed_at_index` (`printed_at`),
  KEY `booking_ticket_prints_active_operation_expires_at_index` (`active_operation_expires_at`),
  KEY `booking_ticket_prints_last_completed_operation_id_index` (`last_completed_operation_id`),
  CONSTRAINT `booking_ticket_prints_active_operator_user_id_foreign` FOREIGN KEY (`active_operator_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_ticket_prints_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `booking_ticket_prints_last_failed_by_user_id_foreign` FOREIGN KEY (`last_failed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_ticket_prints_printed_by_user_id_foreign` FOREIGN KEY (`printed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_ticket_prints_retry_authorized_by_user_id_foreign` FOREIGN KEY (`retry_authorized_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `booking_ticket_prints` WRITE;
/*!40000 ALTER TABLE `booking_ticket_prints` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_ticket_prints` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `sales_channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `created_by_staff_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_access_token_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_access_expires_at` timestamp NULL DEFAULT NULL,
  `ticket_email_token_nonce` char(43) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_email_token_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_email_token_expires_at` timestamp NULL DEFAULT NULL,
  `checkout_idempotency_key_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_request_fingerprint_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `showtime_id` bigint unsigned NOT NULL,
  `cinema_id` bigint unsigned DEFAULT NULL,
  `booking_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `seat_subtotal` bigint unsigned NOT NULL DEFAULT '0',
  `food_subtotal` bigint unsigned NOT NULL DEFAULT '0',
  `gross_amount` bigint unsigned NOT NULL DEFAULT '0',
  `promotion_discount_amount` bigint unsigned NOT NULL DEFAULT '0',
  `points_discount_amount` bigint unsigned NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `booking_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `used_at` datetime DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `ticket_emailed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_booking_code_unique` (`booking_code`),
  UNIQUE KEY `bookings_id_showtime_unique` (`id`,`showtime_id`),
  UNIQUE KEY `bookings_guest_access_token_hash_unique` (`guest_access_token_hash`),
  UNIQUE KEY `bookings_checkout_idempotency_key_hash_unique` (`checkout_idempotency_key_hash`),
  UNIQUE KEY `bookings_ticket_email_token_hash_unique` (`ticket_email_token_hash`),
  KEY `bookings_user_id_foreign` (`user_id`),
  KEY `bookings_showtime_id_foreign` (`showtime_id`),
  KEY `bookings_expiration_lookup_index` (`booking_status`,`expires_at`),
  KEY `bookings_cinema_created_index` (`cinema_id`,`created_at`),
  KEY `bookings_sales_channel_created_index` (`sales_channel`,`created_at`),
  KEY `bookings_creator_created_index` (`created_by_staff_id`,`created_at`),
  CONSTRAINT `bookings_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bookings_created_by_staff_id_foreign` FOREIGN KEY (`created_by_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_showtime_id_foreign` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cinema_consolidation_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cinema_consolidation_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `original_cinema_id` bigint unsigned DEFAULT NULL,
  `canonical_cinema_id` bigint unsigned NOT NULL,
  `original_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_payload` json DEFAULT NULL,
  `migrated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cinema_mapping_entity_unique` (`entity_type`,`entity_id`),
  KEY `cinema_consolidation_mappings_canonical_cinema_id_index` (`canonical_cinema_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cinema_consolidation_mappings` WRITE;
/*!40000 ALTER TABLE `cinema_consolidation_mappings` DISABLE KEYS */;
INSERT INTO `cinema_consolidation_mappings` VALUES (1,'canonical',1,1,1,'created','MovieMate Cinema – FPT Polytechnic','active',NULL,'2026-08-08 08:37:08');
/*!40000 ALTER TABLE `cinema_consolidation_mappings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cinema_operating_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cinema_operating_hours` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cinema_id` bigint unsigned NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL,
  `opens_at` time DEFAULT NULL,
  `latest_show_start_at` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cinema_operating_hours_cinema_id_day_of_week_unique` (`cinema_id`,`day_of_week`),
  CONSTRAINT `cinema_operating_hours_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cinema_operating_hours` WRITE;
/*!40000 ALTER TABLE `cinema_operating_hours` DISABLE KEYS */;
INSERT INTO `cinema_operating_hours` VALUES (1,1,1,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(2,1,2,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(3,1,3,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(4,1,4,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(5,1,5,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(6,1,6,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(7,1,7,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(8,2,1,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(9,2,2,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(10,2,3,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(11,2,4,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(12,2,5,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(13,2,6,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(14,2,7,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(15,3,1,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(16,3,2,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(17,3,3,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(18,3,4,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(19,3,5,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(20,3,6,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(21,3,7,'08:00:00','23:00:00',0,'2026-08-08 08:37:33','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `cinema_operating_hours` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cinema_pricing_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cinema_pricing_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cinema_id` bigint unsigned DEFAULT NULL,
  `room_id` bigint unsigned DEFAULT NULL,
  `seat_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `days_of_week` json DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `amount_vnd` bigint NOT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `stacks_with_weekend` tinyint(1) NOT NULL DEFAULT '0',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cinema_pricing_rules_cinema_id_foreign` (`cinema_id`),
  KEY `cinema_pricing_rules_room_id_foreign` (`room_id`),
  KEY `cinema_pricing_rules_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `pricing_rule_match_index` (`status`,`rule_type`,`cinema_id`,`room_id`),
  KEY `pricing_rule_effective_index` (`starts_at`,`ends_at`),
  KEY `pricing_rule_date_index` (`date_start`,`date_end`),
  CONSTRAINT `cinema_pricing_rules_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cinema_pricing_rules_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cinema_pricing_rules_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cinema_pricing_rules` WRITE;
/*!40000 ALTER TABLE `cinema_pricing_rules` DISABLE KEYS */;
INSERT INTO `cinema_pricing_rules` VALUES (1,'Giá cơ bản CG','base',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,80000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(2,'Phụ thu VIP CG','seat_type',1,NULL,'vip',NULL,NULL,NULL,NULL,NULL,NULL,30000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(3,'Giá ghế đôi CG','seat_type',1,NULL,'couple',NULL,NULL,NULL,NULL,NULL,NULL,80000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(4,'Phụ thu 3D CG','room_type',1,NULL,NULL,'3D',NULL,NULL,NULL,NULL,NULL,25000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(5,'Phụ thu suất tối CG','time_window',1,NULL,NULL,NULL,NULL,NULL,NULL,'18:00:00','22:00:00',15000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(6,'Phụ thu cuối tuần CG','weekend',1,NULL,NULL,NULL,'[6, 7]',NULL,NULL,NULL,NULL,10000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(7,'Ngày hội MovieMate CG','holiday',1,NULL,NULL,NULL,NULL,'2026-09-01','2026-09-01',NULL,NULL,20000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(8,'Giá cơ bản HD','base',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,80000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(9,'Phụ thu VIP HD','seat_type',2,NULL,'vip',NULL,NULL,NULL,NULL,NULL,NULL,30000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(10,'Giá ghế đôi HD','seat_type',2,NULL,'couple',NULL,NULL,NULL,NULL,NULL,NULL,80000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(11,'Phụ thu 3D HD','room_type',2,NULL,NULL,'3D',NULL,NULL,NULL,NULL,NULL,25000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(12,'Phụ thu suất tối HD','time_window',2,NULL,NULL,NULL,NULL,NULL,NULL,'18:00:00','22:00:00',15000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(13,'Phụ thu cuối tuần HD','weekend',2,NULL,NULL,NULL,'[6, 7]',NULL,NULL,NULL,NULL,10000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(14,'Ngày hội MovieMate HD','holiday',2,NULL,NULL,NULL,NULL,'2026-09-01','2026-09-01',NULL,NULL,20000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(15,'Giá cơ bản NTL','base',3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,80000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(16,'Phụ thu VIP NTL','seat_type',3,NULL,'vip',NULL,NULL,NULL,NULL,NULL,NULL,30000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(17,'Giá ghế đôi NTL','seat_type',3,NULL,'couple',NULL,NULL,NULL,NULL,NULL,NULL,80000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(18,'Phụ thu 3D NTL','room_type',3,NULL,NULL,'3D',NULL,NULL,NULL,NULL,NULL,25000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(19,'Phụ thu suất tối NTL','time_window',3,NULL,NULL,NULL,NULL,NULL,NULL,'18:00:00','22:00:00',15000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(20,'Phụ thu cuối tuần NTL','weekend',3,NULL,NULL,NULL,'[6, 7]',NULL,NULL,NULL,NULL,10000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(21,'Ngày hội MovieMate NTL','holiday',3,NULL,NULL,NULL,NULL,'2026-09-01','2026-09-01',NULL,NULL,20000,100,0,NULL,NULL,'active',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `cinema_pricing_rules` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cinemas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cinemas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  `default_cleaning_buffer_minutes` smallint unsigned DEFAULT NULL,
  `latitude` decimal(17,14) DEFAULT NULL,
  `longitude` decimal(17,14) DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cinemas_canonical_key_unique` (`canonical_key`),
  UNIQUE KEY `cinemas_code_unique` (`code`),
  KEY `cinemas_is_primary_index` (`is_primary`),
  KEY `cinemas_district_index` (`district`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cinemas` WRITE;
/*!40000 ALTER TABLE `cinemas` DISABLE KEYS */;
INSERT INTO `cinemas` VALUES (1,'CG','moviemate-fpt-polytechnic','MovieMate Cầu Giấy',NULL,'Số 8 Tôn Thất Thuyết, Cầu Giấy, Hà Nội','Hà Nội','Cầu Giấy','Việt Nam','Asia/Ho_Chi_Minh',15,21.02921400000000,105.78288300000000,NULL,NULL,NULL,'active',1,NULL,'2026-08-08 08:37:08','2026-08-08 08:37:33'),(2,'HD','moviemate-ha-dong','MovieMate Hà Đông',NULL,'Số 10 Trần Phú, Hà Đông, Hà Nội','Hà Nội','Hà Đông','Việt Nam','Asia/Ho_Chi_Minh',15,20.98022600000000,105.78777500000000,NULL,NULL,NULL,'active',0,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:33'),(3,'NTL','moviemate-nam-tu-liem','MovieMate Nam Từ Liêm',NULL,'Số 1 Trịnh Văn Bô, Nam Từ Liêm, Hà Nội','Hà Nội','Nam Từ Liêm','Việt Nam','Asia/Ho_Chi_Minh',15,21.03812980000000,105.74239120000000,NULL,NULL,NULL,'active',0,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `cinemas` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `discount_code_cinema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_code_cinema` (
  `discount_code_id` bigint unsigned NOT NULL,
  `cinema_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`discount_code_id`,`cinema_id`),
  KEY `discount_code_cinema_cinema_id_foreign` (`cinema_id`),
  CONSTRAINT `discount_code_cinema_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discount_code_cinema_discount_code_id_foreign` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `discount_code_cinema` WRITE;
/*!40000 ALTER TABLE `discount_code_cinema` DISABLE KEYS */;
/*!40000 ALTER TABLE `discount_code_cinema` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `discount_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `discount_type` enum('fixed','percent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` bigint unsigned NOT NULL,
  `maximum_discount_amount` bigint unsigned DEFAULT NULL,
  `minimum_order_amount` bigint unsigned NOT NULL DEFAULT '0',
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `total_quota` int unsigned DEFAULT NULL,
  `per_user_quota` int unsigned DEFAULT NULL,
  `registered_users_only` tinyint(1) NOT NULL DEFAULT '0',
  `first_order_only` tinyint(1) NOT NULL DEFAULT '0',
  `can_combine` tinyint(1) NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '0',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discount_codes_code_unique` (`code`),
  KEY `discount_codes_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `discount_codes_updated_by_user_id_foreign` (`updated_by_user_id`),
  KEY `discount_codes_is_active_index` (`is_active`),
  KEY `discount_codes_archived_at_index` (`archived_at`),
  CONSTRAINT `discount_codes_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discount_codes_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `discount_codes` WRITE;
/*!40000 ALTER TABLE `discount_codes` DISABLE KEYS */;
INSERT INTO `discount_codes` VALUES (1,'MOVIEMATE10','Ưu đãi MovieMate 10%','Mã demo dùng để kiểm thử checkout.','percent',10,50000,100000,NULL,NULL,1,NULL,NULL,0,0,0,10,NULL,NULL,NULL,'2026-08-08 08:37:41','2026-08-08 08:37:41'),(2,'WELCOME20K','Chào mừng khách hàng mới','Mã demo cho đơn đầu tiên của tài khoản.','fixed',20000,NULL,80000,NULL,NULL,1,NULL,NULL,1,1,0,20,NULL,NULL,NULL,'2026-08-08 08:37:41','2026-08-08 08:37:41');
/*!40000 ALTER TABLE `discount_codes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `food_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `food_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cinema_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(8,2) NOT NULL DEFAULT '0.00',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `food_items_cinema_active_index` (`cinema_id`,`active`),
  CONSTRAINT `food_items_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `food_items` WRITE;
/*!40000 ALTER TABLE `food_items` DISABLE KEYS */;
INSERT INTO `food_items` VALUES (1,NULL,'Bắp rang bơ','Bắp rang giòn rụm, vị muối nhẹ nhàng.',45000.00,'https://images.unsplash.com/photo-1498654896293-37aacf113fd9?auto=format&fit=crop&w=600&q=80',1,'2026-08-08 08:37:40','2026-08-08 08:37:40'),(2,NULL,'Kem ốc quế','Kem vani mềm mịn với ốc quế giòn tan.',39000.00,'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=600&q=80',1,'2026-08-08 08:37:40','2026-08-08 08:37:40'),(3,NULL,'Nước ngọt','Có thể chọn Coca-Cola, Pepsi hoặc nước khoáng.',32000.00,'https://images.unsplash.com/photo-1571091718767-18d9d78801d8?auto=format&fit=crop&w=600&q=80',1,'2026-08-08 08:37:40','2026-08-08 08:37:40'),(4,NULL,'Hot dog','Bánh mì nóng với xúc xích và sốt đặc trưng.',55000.00,'https://images.unsplash.com/photo-1550317138-10000687a72b?auto=format&fit=crop&w=600&q=80',1,'2026-08-08 08:37:41','2026-08-08 08:37:41');
/*!40000 ALTER TABLE `food_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `genres` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `genres_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `genres` WRITE;
/*!40000 ALTER TABLE `genres` DISABLE KEYS */;
INSERT INTO `genres` VALUES (1,'Action','action',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,'Adventure','adventure',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,'Animation','animation',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(4,'Comedy','comedy',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(5,'Crime','crime',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(6,'Drama','drama',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(7,'Family','family',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(8,'Fantasy','fantasy',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(9,'Horror','horror',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(10,'Mystery','mystery',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(11,'Romance','romance',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(12,'Science Fiction','science-fiction',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(13,'Thriller','thriller',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(14,'War','war',NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30');
/*!40000 ALTER TABLE `genres` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `loyalty_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `points_balance` bigint NOT NULL DEFAULT '0',
  `lifetime_earned` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loyalty_accounts_user_id_unique` (`user_id`),
  CONSTRAINT `loyalty_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `loyalty_accounts` WRITE;
/*!40000 ALTER TABLE `loyalty_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_accounts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `loyalty_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_reward_points` int unsigned NOT NULL DEFAULT '100',
  `point_value_vnd` int unsigned NOT NULL DEFAULT '100',
  `max_points_discount_percent` tinyint unsigned NOT NULL DEFAULT '50',
  `minimum_points_redemption` int unsigned NOT NULL DEFAULT '1',
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `max_discount_codes_per_booking` tinyint unsigned NOT NULL DEFAULT '3',
  PRIMARY KEY (`id`),
  KEY `loyalty_settings_updated_by_user_id_foreign` (`updated_by_user_id`),
  CONSTRAINT `loyalty_settings_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `loyalty_settings` WRITE;
/*!40000 ALTER TABLE `loyalty_settings` DISABLE KEYS */;
INSERT INTO `loyalty_settings` VALUES (1,100,100,50,1,NULL,'2026-08-08 08:37:28','2026-08-08 08:37:28',3);
/*!40000 ALTER TABLE `loyalty_settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `loyalty_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loyalty_account_id` bigint unsigned NOT NULL,
  `source_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `points_delta` bigint NOT NULL,
  `balance_after` bigint NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loyalty_transactions_source_key_unique` (`source_key`),
  KEY `loyalty_transactions_loyalty_account_id_foreign` (`loyalty_account_id`),
  KEY `loyalty_transactions_type_index` (`type`),
  CONSTRAINT `loyalty_transactions_loyalty_account_id_foreign` FOREIGN KEY (`loyalty_account_id`) REFERENCES `loyalty_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `loyalty_transactions` WRITE;
/*!40000 ALTER TABLE `loyalty_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_transactions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2024_01_01_000001_create_roles_table',1),(5,'2024_01_01_000002_add_fields_to_users_table',1),(6,'2024_01_01_000003_create_movies_table',1),(7,'2024_01_01_000004_create_genres_table',1),(8,'2024_01_01_000005_create_movie_genre_table',1),(9,'2024_01_01_000006_create_cinemas_table',1),(10,'2024_01_01_000007_create_rooms_table',1),(11,'2024_01_01_000008_create_seats_table',1),(12,'2024_01_01_000009_create_showtimes_table',1),(13,'2024_01_01_000010_create_bookings_table',1),(14,'2024_01_01_000011_create_booking_seats_table',1),(15,'2024_01_01_000012_create_payments_table',1),(16,'2024_01_01_000013_create_reviews_table',1),(17,'2024_01_01_000014_create_ai_chats_table',1),(18,'2024_01_01_000015_create_ai_recommendations_table',1),(19,'2026_05_20_000001_add_coordinates_to_cinemas_table',1),(20,'2026_05_20_000001_add_showtime_id_to_booking_seats_table',1),(21,'2026_05_23_000002_create_room_types_and_add_room_type_id_to_rooms_table',1),(22,'2026_05_24_000001_create_seat_types_and_extend_seats_table',1),(23,'2026_06_09_000000_create_food_and_order_tables',1),(24,'2026_06_09_000001_update_showtimes_status_to_operational_status',1),(25,'2026_06_10_000001_add_pair_position_to_seats_table',1),(26,'2026_06_10_000002_extend_payments_for_vnpay',1),(27,'2026_07_16_235959_ensure_booking_seat_showtime_fk_support',1),(28,'2026_07_17_000001_replace_booking_seat_unique_with_active_lock',1),(29,'2026_07_17_000002_add_code_and_unique_to_rooms_table',1),(30,'2026_07_17_000003_mark_duplicate_room_12_inactive',1),(31,'2026_08_02_000001_add_customer_email_to_bookings_table',1),(32,'2026_08_03_000001_add_rbac_fields_to_roles_table',1),(33,'2026_08_03_000002_create_permissions_table',1),(34,'2026_08_03_000003_create_permission_role_table',1),(35,'2026_08_03_100000_add_single_cinema_fields_and_mapping_table',1),(36,'2026_08_03_100001_consolidate_cinemas_to_fpt_polytechnic',1),(37,'2026_08_03_200000_create_versioned_room_layouts',1),(38,'2026_08_04_000000_add_showtime_schedule_lookup_index',1),(39,'2026_08_04_100000_harden_booking_foundations',1),(40,'2026_08_04_105000_harden_booking_seat_integrity',1),(41,'2026_08_04_110000_extend_payments_for_zalopay',1),(42,'2026_08_04_115000_add_payment_reconciliation_and_ticket_outbox',1),(43,'2026_08_04_120000_add_checkout_pricing_and_food_snapshots',1),(44,'2026_08_04_121000_harden_active_payment_attempt_states',1),(45,'2026_08_04_122000_create_payment_review_events_table',1),(46,'2026_08_04_123000_remove_booking_seat_fk_compatibility_index',1),(47,'2026_08_04_124000_add_ticket_email_access_credentials_to_bookings',1),(48,'2026_08_04_125000_guard_phase4_rollback_data',1),(49,'2026_08_05_000000_add_vnpay_provider_audit_fields_to_payments',1),(50,'2026_08_06_000001_add_operations_permissions',1),(51,'2026_08_06_000002_create_activity_logs_table',1),(52,'2026_08_06_000003_create_ticket_checkin_events_table',1),(53,'2026_08_06_100000_add_multi_cinema_foundation',1),(54,'2026_08_07_000001_create_booking_ticket_print_workflow',1),(55,'2026_08_07_100000_add_centralized_pricing_and_operating_rules',1),(56,'2026_08_07_200000_add_district_to_cinemas',1),(57,'2026_08_07_300000_enforce_single_active_payment_attempt_per_booking',1),(58,'2026_08_07_400000_create_room_layout_templates_and_movie_lifecycle',1),(59,'2026_08_07_410000_add_layout_template_and_movie_lifecycle_permissions',1),(60,'2026_08_07_420000_add_counter_sales_attribution',1),(61,'2026_08_07_421000_add_counter_sales_permissions',1),(62,'2026_08_08_000001_upgrade_room_types_master_data',1),(63,'2026_08_08_100000_create_discount_code_checkout_tables',1),(64,'2026_08_08_200000_add_verified_reviews_and_loyalty_ledger',1),(65,'2026_08_08_210000_add_checkout_limit_to_loyalty_settings',1),(66,'2026_08_08_220000_add_snapshot_details_to_booking_point_redemptions',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `movie_genre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_genre` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `movie_id` bigint unsigned NOT NULL,
  `genre_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movie_genre_movie_id_foreign` (`movie_id`),
  KEY `movie_genre_genre_id_foreign` (`genre_id`),
  CONSTRAINT `movie_genre_genre_id_foreign` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_genre_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `movie_genre` WRITE;
/*!40000 ALTER TABLE `movie_genre` DISABLE KEYS */;
INSERT INTO `movie_genre` VALUES (1,1,1,NULL,NULL),(2,2,6,NULL,NULL),(3,2,11,NULL,NULL),(4,3,4,NULL,NULL),(5,4,1,NULL,NULL),(6,4,12,NULL,NULL),(7,5,9,NULL,NULL),(8,6,6,NULL,NULL),(9,7,12,NULL,NULL),(10,8,6,NULL,NULL),(11,8,9,NULL,NULL),(12,9,1,NULL,NULL),(13,9,2,NULL,NULL),(14,9,12,NULL,NULL),(15,10,1,NULL,NULL),(16,10,2,NULL,NULL),(17,10,8,NULL,NULL),(18,11,2,NULL,NULL),(19,11,4,NULL,NULL),(20,11,7,NULL,NULL),(21,11,8,NULL,NULL),(22,12,2,NULL,NULL),(23,12,3,NULL,NULL),(24,12,4,NULL,NULL),(25,12,7,NULL,NULL),(26,12,8,NULL,NULL),(27,13,9,NULL,NULL),(28,14,9,NULL,NULL),(29,14,11,NULL,NULL),(30,15,1,NULL,NULL),(31,15,13,NULL,NULL),(32,16,1,NULL,NULL),(33,16,3,NULL,NULL),(34,16,5,NULL,NULL),(35,16,10,NULL,NULL),(36,17,4,NULL,NULL),(37,17,6,NULL,NULL),(38,17,7,NULL,NULL),(39,18,9,NULL,NULL),(40,18,11,NULL,NULL),(41,18,13,NULL,NULL),(42,19,6,NULL,NULL),(43,19,12,NULL,NULL),(44,20,9,NULL,NULL),(45,20,10,NULL,NULL),(46,21,9,NULL,NULL),(47,21,13,NULL,NULL),(48,22,9,NULL,NULL),(49,23,2,NULL,NULL),(50,23,3,NULL,NULL),(51,23,7,NULL,NULL),(52,23,8,NULL,NULL),(53,24,6,NULL,NULL),(54,24,11,NULL,NULL),(55,25,10,NULL,NULL),(56,25,12,NULL,NULL),(57,25,13,NULL,NULL),(58,26,2,NULL,NULL),(59,26,3,NULL,NULL),(60,26,4,NULL,NULL),(61,26,7,NULL,NULL),(62,27,9,NULL,NULL),(63,27,13,NULL,NULL),(64,28,9,NULL,NULL),(65,28,13,NULL,NULL),(66,29,1,NULL,NULL),(67,29,2,NULL,NULL),(68,29,12,NULL,NULL),(69,30,9,NULL,NULL),(70,30,13,NULL,NULL),(71,31,1,NULL,NULL),(72,31,2,NULL,NULL),(73,31,6,NULL,NULL),(74,31,8,NULL,NULL),(75,31,10,NULL,NULL),(76,31,13,NULL,NULL),(77,31,14,NULL,NULL);
/*!40000 ALTER TABLE `movie_genre` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `movies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `poster` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int NOT NULL DEFAULT '90',
  `age_rating` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'P',
  `release_date` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `movies_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `movies` WRITE;
/*!40000 ALTER TABLE `movies` DISABLE KEYS */;
INSERT INTO `movies` VALUES (1,'The Great Adventure','the-great-adventure','An epic journey.',NULL,NULL,NULL,NULL,120,'PG-13','2025-01-15','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(2,'Love in Spring','love-in-spring','A romantic story.',NULL,NULL,NULL,NULL,100,'PG','2025-02-20','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(3,'Laugh Out Loud','laugh-out-loud','Comedy for everyone.',NULL,NULL,NULL,NULL,95,'PG','2024-12-01','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(4,'Space Odyssey','space-odyssey','Sci‑fi exploration.',NULL,NULL,NULL,NULL,130,'PG-13','2025-03-10','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(5,'Haunted Night','haunted-night','Horror thriller.',NULL,NULL,NULL,NULL,105,'R','2024-10-31','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(6,'Family Tales','family-tales','Heartwarming drama.',NULL,NULL,NULL,NULL,110,'PG','2025-04-05','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(7,'Future Tech','future-tech','Tech‑driven sci‑fi.',NULL,NULL,NULL,NULL,115,'PG-13','2025-05-01','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(8,'Mystery Manor','mystery-manor','Mystery and suspense.',NULL,NULL,NULL,NULL,100,'PG','2025-06-12','inactive','2026-08-08 08:37:32','2026-08-08 08:37:32'),(9,'Spider-Man: Brand New Day','spider-man-brand-new-day','Peter Parker balances university life with fighting crime full-time as Spider-Man while a mysterious force changes the rules around him.','https://media.themoviedb.org/t/p/w500/iPOn6DinuVyLY17YM9mKuPofV08.jpg','https://media.themoviedb.org/t/p/w1280/qeQJx07rK2xm8SD2sJxFKhE7gs0.jpg',NULL,NULL,145,'T13','2026-07-31','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(10,'The Odyssey','the-odyssey','Odysseus undertakes a dangerous journey home after the Trojan War and faces mythic creatures, gods, and trials along the way.','https://media.themoviedb.org/t/p/w500/5rhTDKUhPYvpdQIijFIs5VoWsON.jpg','https://media.themoviedb.org/t/p/w1280/RMXG8myu1aGlNUsRjtxzmpdMK0.jpg',NULL,NULL,173,'T16','2026-07-17','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(11,'Moana','moana','A young wayfinder answers the ocean\'s call and journeys beyond the reef to help restore balance to her island home.','https://media.themoviedb.org/t/p/w500/zKVgiv5qHCvCLT4A2ymJi5QeXDH.jpg','https://media.themoviedb.org/t/p/w1280/c6BPbkO5Npt1OdwttAxCFo06wtH.jpg',NULL,NULL,115,'P','2026-07-10','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(12,'Minions & Monsters','minions-monsters','The Minions stumble into a new comic adventure filled with monsters, mayhem, and an unexpected family mission.','https://media.themoviedb.org/t/p/w500/nz7i42yhLIJ4ve9JKgM6NthoLHO.jpg','https://media.themoviedb.org/t/p/w1280/kkcwhgSFd81QDlXo8ytrpHPQjhy.jpg',NULL,NULL,90,'P','2026-07-01','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(13,'Evil Dead Burn','evil-dead-burn','A new nightmare from the Evil Dead universe unleashes demonic terror and a desperate fight for survival.','https://media.themoviedb.org/t/p/w500/uRxrNXQWkHoENm3nwVOZDYSCx2F.jpg','https://media.themoviedb.org/t/p/w1280/biwEwIkjZhMUfXzz59bpeDzwYB6.jpg',NULL,NULL,110,'T18','2026-07-10','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(14,'Leviticus','leviticus','Two young men confront a supernatural force as forbidden love and buried fears draw them into escalating horror.','https://media.themoviedb.org/t/p/w500/gnAsZvBygplNpp8PtjoTEYv3VPB.jpg','https://media.themoviedb.org/t/p/w1280/7y8zWGEjs7tresw4Hzkkf4TdkcL.jpg',NULL,NULL,88,'T18','2026-07-03','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(15,'Protector','protector','A determined protector is pulled into a violent conspiracy and must risk everything to keep a vulnerable target alive.','https://media.themoviedb.org/t/p/w500/icOZpnGuH9YrEaW3wrw5GJaXGih.jpg','https://media.themoviedb.org/t/p/w1280/vpuPY4UziUCxv7gYYoaZQ3LX7to.jpg',NULL,NULL,90,'T18','2026-07-17','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(16,'Detective Conan: Fallen Angel of the Highway','detective-conan-fallen-angel-of-the-highway','Conan investigates a highway incident that develops into a complex case involving crime, danger, and a hidden adversary.','https://media.themoviedb.org/t/p/w500/tqlOfb1ekyVYpDumiL9MsK6uirw.jpg','https://media.themoviedb.org/t/p/w1280/zT5S4GNs8Eu2gIkGORoN6yC1uE2.jpg',NULL,NULL,109,'P','2026-07-24','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(17,'Dear You','dear-you','A family story unfolds through affection, misunderstandings, and the choices that reconnect people who have grown apart.','https://media.themoviedb.org/t/p/w500/rjmhzdVS3Ia535pFawju857e2Na.jpg','https://media.themoviedb.org/t/p/w1280/AwmlL79nKTcX5tzAhyoV298xXlz.jpg',NULL,NULL,118,'P','2026-08-07','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(18,'The Stain','the-stain','A disturbing presence stains a relationship with obsession, fear, and consequences that become impossible to escape.','https://media.themoviedb.org/t/p/w500/vPxVvwMduxySggqEyHpwQNtjbx6.jpg','https://media.themoviedb.org/t/p/w1280/s9kbcVd0PQCw2JPKo9C84ChP2x.jpg',NULL,NULL,105,'T18','2026-07-10','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(19,'Sheep in the Box','sheep-in-the-box','A family encounters a mysterious human-like robot whose arrival forces them to reconsider memory, grief, and what makes a person real.','https://media.themoviedb.org/t/p/w500/9rOCIYYT8Q76FHgl3Nm5ofuc6TQ.jpg','https://media.themoviedb.org/t/p/w1280/iZCdWpGqKQaAdhysW8gYPTdCP6A.jpg',NULL,NULL,126,'P','2026-07-03','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(20,'The Shrine','the-shrine','A search connected to a remote shrine leads into an unsettling mystery where old beliefs and supernatural danger collide.','https://media.themoviedb.org/t/p/w500/grq3eAFw6D0iFB1xfBA9GPbNjeD.jpg','https://media.themoviedb.org/t/p/w1280/8KzGsh6LTZCbYg4UujDhYqlmVzg.jpg',NULL,NULL,95,'T16','2026-07-03','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(21,'Ghost Board','ghost-board','A group uses a spirit board and awakens a deadly presence that turns their curiosity into a fight to survive.','https://media.themoviedb.org/t/p/w500/xVjDFOKoZuPOv1m4Z7NJpQ1gbfF.jpg','https://media.themoviedb.org/t/p/w1280/nGbiKpQ4O9TQV9hpathwAEh18V4.jpg',NULL,NULL,125,'T16','2026-07-03','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(22,'Kijsada Paradise','kijsada-paradise','Visitors to an isolated paradise encounter a haunting past and a supernatural threat hidden behind its beauty.','https://media.themoviedb.org/t/p/w500/flWf8cNQrlw1PXXW7uzPZPGRDHx.jpg','https://media.themoviedb.org/t/p/w1280/x54Apuuj38C3aPv82BH34TQt8Um.jpg',NULL,NULL,113,'T16','2026-07-24','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(23,'The Land of Sometimes','the-land-of-sometimes','Twins travel to a magical island where every day brings a new character, song, and imaginative adventure.','https://media.themoviedb.org/t/p/w500/uEZAx4Rk42hv8bfrXC9pyQPWErw.jpg','https://media.themoviedb.org/t/p/w1280/nHP6HaHRZ8GHveLH5VVJSFFQ3S.jpg',NULL,NULL,93,'P','2026-07-31','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(24,'Beyond The Sky','beyond-the-sky','A brief encounter opens an intimate story about longing, connection, and looking beyond the limits people place around themselves.','https://media.themoviedb.org/t/p/w500/n1HrReIzov2K6mUm2iP8oIIoO3Q.jpg','https://media.themoviedb.org/t/p/w1280/hBQM83bU6CxOdVHSSqlIZ7eGMGD.jpg',NULL,NULL,10,'P','2026-07-25','now_showing','2026-08-08 08:37:32','2026-08-08 08:37:32'),(25,'The End of Oak Street','the-end-of-oak-street','A mystery on Oak Street draws ordinary people into a science-fiction conspiracy where nothing about their neighborhood is what it seems.','https://media.themoviedb.org/t/p/w500/3SifFCwwFzXdU1Ew0nA4Z92Bs15.jpg','https://media.themoviedb.org/t/p/w1280/b9q9VmbXDvJmTziRqkwdEmFdwhr.jpg',NULL,NULL,100,'T13','2026-08-14','coming_soon','2026-08-08 08:37:32','2026-08-08 08:37:32'),(26,'PAW Patrol: The Dino Movie','paw-patrol-the-dino-movie','The PAW Patrol enters a dinosaur-sized rescue adventure and works together to protect Adventure Bay.','https://media.themoviedb.org/t/p/w500/cxXSF4aY5N2cOGwaga5OnpPFHzk.jpg','https://media.themoviedb.org/t/p/w1280/upNeU9FNGAvdhnoThvv64Z0MWKn.jpg',NULL,NULL,88,'P','2026-08-14','coming_soon','2026-08-08 08:37:32','2026-08-08 08:37:32'),(27,'Insidious: Out of the Further','insidious-out-of-the-further','A new chapter opens in The Further as a family faces the lingering supernatural evil connected to the Lambert legacy.','https://media.themoviedb.org/t/p/w500/4tTrW9dXCByS5wt2pXVWb58zNjz.jpg','https://media.themoviedb.org/t/p/w1280/hD8y787ciNWQ2bn396YrSsOIzdN.jpg',NULL,NULL,106,'T13','2026-08-21','coming_soon','2026-08-08 08:37:32','2026-08-08 08:37:32'),(28,'The Eyes','the-eyes','A tense encounter turns into a dangerous ordeal in which watching and being watched have deadly consequences.','https://media.themoviedb.org/t/p/w500/yH2sGLdQejqf3Zk8KDuoDa5gr6E.jpg','https://media.themoviedb.org/t/p/w1280/75S750SAsppSiQc0S3EuSH0K77O.jpg',NULL,NULL,106,'T16','2026-08-14','coming_soon','2026-08-08 08:37:32','2026-08-08 08:37:32'),(29,'Agito: Superpower War','agito-superpower-war','Young heroes with extraordinary abilities are swept into a conflict that will decide how their powers shape the world.','https://media.themoviedb.org/t/p/w500/jPni7Oz12gZcCMnXsyqgcbBu2Pj.jpg','https://media.themoviedb.org/t/p/w1280/seiGSDA3rcgKIAcom0nZzXTqogH.jpg',NULL,NULL,97,'T13','2026-08-14','coming_soon','2026-08-08 08:37:32','2026-08-08 08:37:32'),(30,'The Djinn\'s Curse 2','the-djinns-curse-2','The curse returns with a more dangerous supernatural force and new victims struggling to break its hold.','https://media.themoviedb.org/t/p/w500/76BkBITF6zdHWdtEsWePhekA5nw.jpg','https://media.themoviedb.org/t/p/w1280/beEB5DlK30LneSXuaSzEJM48O40.jpg',NULL,NULL,97,'T16','2026-08-21','coming_soon','2026-08-08 08:37:32','2026-08-08 08:37:32'),(31,'Spirit Guardians: The Last Secret of the First Emperor','spirit-guardians-the-last-secret-of-the-first-emperor','Guardians race to uncover the First Emperor\'s final secret as war, ancient mystery, and supernatural forces converge.','https://media.themoviedb.org/t/p/w500/uaXDal48MG0sTGs2XU6L1wuVaUO.jpg','https://media.themoviedb.org/t/p/w1280/2H8hOTgw5Rrbl70CXi3hPKfzhhn.jpg',NULL,NULL,151,'P','2026-08-28','coming_soon','2026-08-08 08:37:33','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `movies` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `food_item_id` bigint unsigned NOT NULL,
  `snapshot_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` bigint unsigned DEFAULT NULL,
  `line_total` bigint unsigned DEFAULT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_food_item_id_foreign` (`food_item_id`),
  CONSTRAINT `order_items_food_item_id_foreign` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_cinema_id` bigint unsigned DEFAULT NULL,
  `subtotal` bigint unsigned NOT NULL DEFAULT '0',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_booking_id_unique` (`booking_id`),
  KEY `orders_user_id_foreign` (`user_id`),
  CONSTRAINT `orders_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `payment_review_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_review_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned NOT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resulting_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_result_category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_result_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_review_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `payment_review_events_payment_time_index` (`payment_id`,`created_at`),
  CONSTRAINT `payment_review_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_review_events_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `payment_review_events` WRITE;
/*!40000 ALTER TABLE `payment_review_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_review_events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vnpay',
  `app_id` bigint unsigned DEFAULT NULL,
  `app_trans_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_time_ms` bigint unsigned DEFAULT NULL,
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `reconcile_until` timestamp NULL DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `active_attempt_key` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'pending',_utf8mb4'processing',_utf8mb4'unresolved',_utf8mb4'review')) then _utf8mb4'ACTIVE' else NULL end)) VIRTUAL,
  `transaction_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_url` text COLLATE utf8mb4_unicode_ci,
  `response_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_transaction_created_at` timestamp NULL DEFAULT NULL,
  `provider_paid_at` timestamp NULL DEFAULT NULL,
  `zp_trans_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zp_trans_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_url` text COLLATE utf8mb4_unicode_ci,
  `qr_code` text COLLATE utf8mb4_unicode_ci,
  `provider_return_code` int DEFAULT NULL,
  `provider_sub_return_code` int DEFAULT NULL,
  `provider_return_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_sub_return_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `server_time_ms` bigint unsigned DEFAULT NULL,
  `callback_received_at` timestamp NULL DEFAULT NULL,
  `last_queried_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `settled_by_user_id` bigint unsigned DEFAULT NULL,
  `settled_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_response_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `callback_payload_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `query_response_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `raw_request` json DEFAULT NULL,
  `raw_response` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `booking_attempt_guard` varchar(16) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'pending',_utf8mb4'processing',_utf8mb4'unresolved',_utf8mb4'review')) then _utf8mb4'ACTIVE' else NULL end)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_order_code_unique` (`order_code`),
  UNIQUE KEY `payments_provider_app_trans_unique` (`provider`,`app_id`,`app_trans_id`),
  UNIQUE KEY `payments_provider_zp_trans_unique` (`provider`,`zp_trans_id`),
  UNIQUE KEY `payments_provider_transaction_id_unique` (`provider`,`transaction_id`),
  UNIQUE KEY `payments_one_active_attempt_unique` (`booking_id`,`provider`,`active_attempt_key`),
  UNIQUE KEY `payments_booking_active_attempt_unique` (`booking_id`,`booking_attempt_guard`),
  KEY `payments_booking_status_index` (`booking_id`,`status`),
  KEY `payments_provider_status_expiry_index` (`provider`,`status`,`expires_at`),
  KEY `payments_reconciliation_due_index` (`provider`,`status`,`reconcile_until`,`last_queried_at`),
  KEY `payments_settler_settled_index` (`settled_by_user_id`,`settled_at`),
  CONSTRAINT `payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_settled_by_user_id_foreign` FOREIGN KEY (`settled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_role_permission_id_foreign` (`permission_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` VALUES (1,1,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,2,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,3,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,4,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,5,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,6,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,7,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,8,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,9,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,10,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,11,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,12,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,13,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,14,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,15,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,16,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,17,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,18,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,19,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,21,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,22,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,23,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,24,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,25,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,26,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,27,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,28,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,29,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,30,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,31,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,32,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,33,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,34,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,35,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,36,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,37,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,38,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,39,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,40,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,41,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,42,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,43,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,44,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,45,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,46,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,47,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,48,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,49,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,50,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,51,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,52,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,53,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,54,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,55,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,56,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,57,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,58,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,59,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,60,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,61,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,62,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,63,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,64,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,65,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,66,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,67,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,68,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,69,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(1,70,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,1,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,2,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,3,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,4,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,5,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,6,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,7,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,8,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,9,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,10,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,11,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,12,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,13,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,15,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,17,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,18,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,19,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,21,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,22,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,24,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,26,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,27,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,28,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,29,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,30,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,32,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,33,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,34,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,35,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,37,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,39,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,40,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,41,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,42,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,43,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,44,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,45,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,46,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,47,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,48,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,49,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,50,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,51,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,52,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,53,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,54,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,55,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,56,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,57,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,58,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,59,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,60,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,61,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,62,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,63,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,64,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,65,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,66,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,1,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,6,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,19,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,26,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,27,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,28,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,29,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,34,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,39,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,42,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,51,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,61,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,62,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,63,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,64,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,65,'2026-08-08 08:37:30','2026-08-08 08:37:30');
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`),
  KEY `permissions_group_index` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'Xem đơn đặt vé','bookings.view',NULL,'bookings','2026-08-08 08:37:14','2026-08-08 08:37:14'),(2,'Xem thanh toán','payments.view',NULL,'payments','2026-08-08 08:37:14','2026-08-08 08:37:14'),(3,'Đối soát giao dịch','payments.reconcile',NULL,'payments','2026-08-08 08:37:14','2026-08-08 08:37:14'),(4,'Xem lịch sử gửi vé điện tử','ticket_deliveries.view',NULL,'ticket_deliveries','2026-08-08 08:37:14','2026-08-08 08:37:14'),(5,'Gửi lại vé điện tử','ticket_deliveries.retry',NULL,'ticket_deliveries','2026-08-08 08:37:14','2026-08-08 08:37:14'),(6,'Xem lịch sử soát vé','ticket_checkins.view',NULL,'ticket_checkins','2026-08-08 08:37:14','2026-08-08 08:37:14'),(7,'Xem trạng thái bảo trì ghế','seats.maintenance.view',NULL,'seats','2026-08-08 08:37:14','2026-08-08 08:37:14'),(8,'Cập nhật trạng thái bảo trì ghế','seats.maintenance.update',NULL,'seats','2026-08-08 08:37:14','2026-08-08 08:37:14'),(9,'Xem mã giảm giá','discounts.view',NULL,'discounts','2026-08-08 08:37:14','2026-08-08 08:37:14'),(10,'Quản lý mã giảm giá','discounts.manage',NULL,'discounts','2026-08-08 08:37:14','2026-08-08 08:37:14'),(11,'Xem đánh giá phim','reviews.view',NULL,'reviews','2026-08-08 08:37:14','2026-08-08 08:37:14'),(12,'Kiểm duyệt đánh giá phim','reviews.moderate',NULL,'reviews','2026-08-08 08:37:14','2026-08-08 08:37:14'),(13,'Xem báo cáo','reports.view',NULL,'reports','2026-08-08 08:37:14','2026-08-08 08:37:14'),(14,'Xem nhật ký hoạt động','activity_logs.view',NULL,'activity_logs','2026-08-08 08:37:14','2026-08-08 08:37:14'),(15,'Xem danh sách chi nhánh','cinemas.view',NULL,'cinemas','2026-08-08 08:37:17','2026-08-08 08:37:17'),(16,'Quản lý chi nhánh','cinemas.manage',NULL,'cinemas','2026-08-08 08:37:17','2026-08-08 08:37:17'),(17,'Xem phân công chi nhánh','cinema_assignments.view',NULL,'cinema_assignments','2026-08-08 08:37:17','2026-08-08 08:37:17'),(18,'Quản lý phân công chi nhánh','cinema_assignments.manage',NULL,'cinema_assignments','2026-08-08 08:37:17','2026-08-08 08:37:17'),(19,'Tra cứu vé','tickets.lookup',NULL,'tickets','2026-08-08 08:37:19','2026-08-08 08:37:19'),(20,'Cho phép in lại vé','tickets.print.override',NULL,'tickets','2026-08-08 08:37:19','2026-08-08 08:37:19'),(21,'Xem lịch sử in vé','ticket_prints.view',NULL,'ticket_prints','2026-08-08 08:37:19','2026-08-08 08:37:19'),(22,'Xem mẫu sơ đồ phòng','layout_templates.view',NULL,'layout_templates','2026-08-08 08:37:22','2026-08-08 08:37:29'),(23,'Quản lý mẫu sơ đồ phòng','layout_templates.manage',NULL,'layout_templates','2026-08-08 08:37:22','2026-08-08 08:37:29'),(24,'Áp dụng mẫu sơ đồ cho phòng','room_layouts.apply_template',NULL,'room_layouts','2026-08-08 08:37:22','2026-08-08 08:37:22'),(25,'Quản lý vòng đời phim','movies.lifecycle',NULL,'movies','2026-08-08 08:37:22','2026-08-08 08:37:22'),(26,'Xem khu vực bán vé tại quầy','counter_sales.view',NULL,'counter_sales','2026-08-08 08:37:23','2026-08-08 08:37:23'),(27,'Tạo đơn bán vé tại quầy','counter_sales.create',NULL,'counter_sales','2026-08-08 08:37:23','2026-08-08 08:37:23'),(28,'Xác nhận thu tiền mặt tại quầy','counter_sales.settle',NULL,'counter_sales','2026-08-08 08:37:23','2026-08-08 08:37:23'),(29,'Hủy đơn giữ chỗ tại quầy','counter_sales.cancel',NULL,'counter_sales','2026-08-08 08:37:23','2026-08-08 08:37:23'),(30,'Xem danh mục loại phòng','room_types.view',NULL,'room_types','2026-08-08 08:37:24','2026-08-08 08:37:29'),(31,'Quản lý danh mục loại phòng','room_types.manage',NULL,'room_types','2026-08-08 08:37:24','2026-08-08 08:37:29'),(32,'Quản lý giờ hoạt động chi nhánh','cinemas.operations.manage',NULL,'cinemas','2026-08-08 08:37:29','2026-08-08 08:37:29'),(33,'Truy cập khu vực quản trị','admin.access',NULL,'admin','2026-08-08 08:37:29','2026-08-08 08:37:29'),(34,'Xem tổng quan','dashboard.view',NULL,'dashboard','2026-08-08 08:37:29','2026-08-08 08:37:29'),(35,'Xem rạp','cinema.view',NULL,'cinema','2026-08-08 08:37:29','2026-08-08 08:37:29'),(36,'Tạo rạp','cinema.create',NULL,'cinema','2026-08-08 08:37:29','2026-08-08 08:37:29'),(37,'Sửa rạp','cinema.update',NULL,'cinema','2026-08-08 08:37:29','2026-08-08 08:37:29'),(38,'Xóa rạp','cinema.delete',NULL,'cinema','2026-08-08 08:37:29','2026-08-08 08:37:29'),(39,'Xem phòng','rooms.view',NULL,'rooms','2026-08-08 08:37:29','2026-08-08 08:37:29'),(40,'Tạo phòng','rooms.create',NULL,'rooms','2026-08-08 08:37:29','2026-08-08 08:37:29'),(41,'Sửa phòng','rooms.update',NULL,'rooms','2026-08-08 08:37:29','2026-08-08 08:37:29'),(42,'Xem ghế','seats.view',NULL,'seats','2026-08-08 08:37:29','2026-08-08 08:37:29'),(43,'Quản lý sơ đồ ghế','seats.manage',NULL,'seats','2026-08-08 08:37:29','2026-08-08 08:37:29'),(44,'Xem phim','movies.view',NULL,'movies','2026-08-08 08:37:29','2026-08-08 08:37:29'),(45,'Tạo phim','movies.create',NULL,'movies','2026-08-08 08:37:29','2026-08-08 08:37:29'),(46,'Sửa phim','movies.update',NULL,'movies','2026-08-08 08:37:29','2026-08-08 08:37:29'),(47,'Xem thể loại','genres.view',NULL,'genres','2026-08-08 08:37:29','2026-08-08 08:37:29'),(48,'Tạo thể loại','genres.create',NULL,'genres','2026-08-08 08:37:29','2026-08-08 08:37:29'),(49,'Sửa thể loại','genres.update',NULL,'genres','2026-08-08 08:37:29','2026-08-08 08:37:29'),(50,'Xóa thể loại','genres.delete',NULL,'genres','2026-08-08 08:37:29','2026-08-08 08:37:29'),(51,'Xem suất chiếu','showtimes.view',NULL,'showtimes','2026-08-08 08:37:29','2026-08-08 08:37:29'),(52,'Tạo suất chiếu','showtimes.create',NULL,'showtimes','2026-08-08 08:37:29','2026-08-08 08:37:29'),(53,'Sửa suất chiếu','showtimes.update',NULL,'showtimes','2026-08-08 08:37:29','2026-08-08 08:37:29'),(54,'Xóa suất chiếu','showtimes.delete',NULL,'showtimes','2026-08-08 08:37:29','2026-08-08 08:37:29'),(55,'Xem bảng giá vé','pricing.view',NULL,'pricing','2026-08-08 08:37:29','2026-08-08 08:37:29'),(56,'Quản lý bảng giá vé','pricing.manage',NULL,'pricing','2026-08-08 08:37:29','2026-08-08 08:37:29'),(57,'Xem danh mục món ăn','foods.view',NULL,'foods','2026-08-08 08:37:29','2026-08-08 08:37:29'),(58,'Tạo món ăn','foods.create',NULL,'foods','2026-08-08 08:37:29','2026-08-08 08:37:29'),(59,'Sửa món ăn','foods.update',NULL,'foods','2026-08-08 08:37:29','2026-08-08 08:37:29'),(60,'Xóa món ăn','foods.delete',NULL,'foods','2026-08-08 08:37:29','2026-08-08 08:37:29'),(61,'Xem đơn đồ ăn','food-orders.view',NULL,'food-orders','2026-08-08 08:37:29','2026-08-08 08:37:29'),(62,'Cập nhật trạng thái đơn đồ ăn','food-orders.update-status',NULL,'food-orders','2026-08-08 08:37:29','2026-08-08 08:37:29'),(63,'Vận hành đơn đặt vé','bookings.operate',NULL,'bookings','2026-08-08 08:37:29','2026-08-08 08:37:29'),(64,'In vé','tickets.print',NULL,'tickets','2026-08-08 08:37:29','2026-08-08 08:37:29'),(65,'Soát vé','tickets.checkin',NULL,'tickets','2026-08-08 08:37:29','2026-08-08 08:37:29'),(66,'Xem người dùng','users.view',NULL,'users','2026-08-08 08:37:29','2026-08-08 08:37:29'),(67,'Thay đổi vai trò người dùng','users.manage-role',NULL,'users','2026-08-08 08:37:29','2026-08-08 08:37:29'),(68,'Thay đổi trạng thái người dùng','users.manage-status',NULL,'users','2026-08-08 08:37:29','2026-08-08 08:37:29'),(69,'Xem vai trò và quyền','roles.view',NULL,'roles','2026-08-08 08:37:29','2026-08-08 08:37:29'),(70,'Thay đổi quyền của vai trò','roles.manage',NULL,'roles','2026-08-08 08:37:29','2026-08-08 08:37:29');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `movie_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  `rating` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `sentiment` enum('positive','neutral','negative') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('visible','hidden') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'visible',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `moderation_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `moderation_flags` json DEFAULT NULL,
  `moderation_reason` text COLLATE utf8mb4_unicode_ci,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `first_published_at` timestamp NULL DEFAULT NULL,
  `reward_awarded_at` timestamp NULL DEFAULT NULL,
  `moderated_by_user_id` bigint unsigned DEFAULT NULL,
  `moderated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_user_id_movie_id_unique` (`user_id`,`movie_id`),
  KEY `reviews_movie_id_foreign` (`movie_id`),
  KEY `reviews_booking_id_foreign` (`booking_id`),
  KEY `reviews_moderated_by_user_id_foreign` (`moderated_by_user_id`),
  KEY `reviews_moderation_status_index` (`moderation_status`),
  KEY `reviews_is_verified_index` (`is_verified`),
  CONSTRAINT `reviews_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviews_moderated_by_user_id_foreign` FOREIGN KEY (`moderated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviews_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','admin','Quản trị toàn hệ thống',1,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(2,'Manager','manager','Quản lý vận hành',1,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(3,'Staff','staff','Nhân viên vận hành',1,'2026-08-08 08:37:29','2026-08-08 08:37:29'),(4,'User','user','Khách hàng',1,'2026-08-08 08:37:29','2026-08-08 08:37:29');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `room_layout_cells`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_layout_cells` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_layout_id` bigint unsigned NOT NULL,
  `x_position` tinyint unsigned NOT NULL,
  `y_position` tinyint unsigned NOT NULL,
  `cell_type` enum('seat','aisle') COLLATE utf8mb4_unicode_ci NOT NULL,
  `seat_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_layout_cells_coordinate_unique` (`room_layout_id`,`x_position`,`y_position`),
  UNIQUE KEY `room_layout_cells_seat_unique` (`room_layout_id`,`seat_id`),
  KEY `room_layout_cells_seat_id_foreign` (`seat_id`),
  CONSTRAINT `room_layout_cells_room_layout_id_foreign` FOREIGN KEY (`room_layout_id`) REFERENCES `room_layouts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `room_layout_cells_seat_id_foreign` FOREIGN KEY (`seat_id`) REFERENCES `seats` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `room_layout_cells` WRITE;
/*!40000 ALTER TABLE `room_layout_cells` DISABLE KEYS */;
INSERT INTO `room_layout_cells` VALUES (1,1,1,1,'seat',1,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(2,1,2,1,'seat',2,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(3,1,3,1,'seat',3,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(4,1,4,1,'seat',4,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(5,1,1,2,'seat',5,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(6,1,2,2,'seat',6,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(7,1,3,2,'seat',7,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(8,1,4,2,'seat',8,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(9,1,1,3,'seat',9,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(10,1,2,3,'seat',10,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(11,1,3,3,'seat',11,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(12,1,4,3,'seat',12,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(13,2,1,1,'seat',13,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(14,2,2,1,'seat',14,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(15,2,3,1,'seat',15,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(16,2,4,1,'seat',16,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(17,2,1,2,'seat',17,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(18,2,2,2,'seat',18,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(19,2,3,2,'seat',19,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(20,2,4,2,'seat',20,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(21,2,1,3,'seat',21,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(22,2,2,3,'seat',22,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(23,2,3,3,'seat',23,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(24,2,4,3,'seat',24,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(25,3,1,1,'seat',25,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(26,3,2,1,'seat',26,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(27,3,3,1,'seat',27,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(28,3,4,1,'seat',28,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(29,3,1,2,'seat',29,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(30,3,2,2,'seat',30,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(31,3,3,2,'seat',31,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(32,3,4,2,'seat',32,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(33,3,1,3,'seat',33,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(34,3,2,3,'seat',34,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(35,3,3,3,'seat',35,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(36,3,4,3,'seat',36,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(37,4,1,1,'seat',37,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(38,4,2,1,'seat',38,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(39,4,3,1,'seat',39,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(40,4,4,1,'seat',40,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(41,4,5,1,'aisle',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(42,4,6,1,'seat',41,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(43,4,7,1,'seat',42,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(44,4,8,1,'seat',43,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(45,4,1,2,'seat',44,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(46,4,2,2,'seat',45,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(47,4,3,2,'seat',46,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(48,4,4,2,'seat',47,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(49,4,5,2,'aisle',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(50,4,6,2,'seat',48,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(51,4,7,2,'seat',49,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(52,4,8,2,'seat',50,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(53,4,1,3,'seat',51,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(54,4,2,3,'seat',52,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(55,4,3,3,'seat',53,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(56,4,4,3,'seat',54,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(57,4,5,3,'aisle',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(58,4,6,3,'seat',55,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(59,4,7,3,'seat',56,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(60,4,8,3,'seat',57,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(61,4,1,4,'seat',58,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(62,4,2,4,'seat',59,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(63,4,3,4,'seat',60,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(64,4,4,4,'seat',61,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(65,4,5,4,'aisle',NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(66,4,6,4,'seat',62,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(67,4,7,4,'seat',63,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(68,4,8,4,'seat',64,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(69,5,1,1,'seat',65,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(70,5,2,1,'seat',66,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(71,5,3,1,'seat',67,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(72,5,4,1,'seat',68,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(73,5,1,2,'seat',69,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(74,5,2,2,'seat',70,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(75,5,3,2,'seat',71,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(76,5,4,2,'seat',72,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(77,5,1,3,'seat',73,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(78,5,2,3,'seat',74,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(79,5,3,3,'seat',75,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(80,5,4,3,'seat',76,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(81,6,1,1,'seat',77,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(82,6,2,1,'seat',78,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(83,6,3,1,'seat',79,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(84,6,4,1,'seat',80,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(85,6,1,2,'seat',81,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(86,6,2,2,'seat',82,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(87,6,3,2,'seat',83,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(88,6,4,2,'seat',84,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(89,6,1,3,'seat',85,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(90,6,2,3,'seat',86,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(91,6,3,3,'seat',87,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(92,6,4,3,'seat',88,'2026-08-08 08:37:33','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `room_layout_cells` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `room_layout_template_cells`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_layout_template_cells` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_layout_template_id` bigint unsigned NOT NULL,
  `x_position` tinyint unsigned NOT NULL,
  `y_position` tinyint unsigned NOT NULL,
  `cell_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seat_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seat_label` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seat_unit_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pair_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_layout_template_cells_coordinate_unique` (`room_layout_template_id`,`x_position`,`y_position`),
  UNIQUE KEY `room_layout_template_cells_label_unique` (`room_layout_template_id`,`seat_label`),
  CONSTRAINT `room_layout_template_cells_room_layout_template_id_foreign` FOREIGN KEY (`room_layout_template_id`) REFERENCES `room_layout_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=271 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `room_layout_template_cells` WRITE;
/*!40000 ALTER TABLE `room_layout_template_cells` DISABLE KEYS */;
INSERT INTO `room_layout_template_cells` VALUES (1,1,1,1,'seat','normal','A1','A1',NULL,'{\"row\": \"A\", \"number\": 1}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,1,2,1,'seat','normal','A2','A2',NULL,'{\"row\": \"A\", \"number\": 2}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(3,1,3,1,'seat','normal','A3','A3',NULL,'{\"row\": \"A\", \"number\": 3}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(4,1,4,1,'seat','normal','A4','A4',NULL,'{\"row\": \"A\", \"number\": 4}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(5,1,5,1,'seat','normal','A5','A5',NULL,'{\"row\": \"A\", \"number\": 5}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(6,1,6,1,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(7,1,7,1,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(8,1,8,1,'seat','normal','A8','A8',NULL,'{\"row\": \"A\", \"number\": 8}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(9,1,9,1,'seat','normal','A9','A9',NULL,'{\"row\": \"A\", \"number\": 9}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(10,1,10,1,'seat','normal','A10','A10',NULL,'{\"row\": \"A\", \"number\": 10}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(11,1,11,1,'seat','normal','A11','A11',NULL,'{\"row\": \"A\", \"number\": 11}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(12,1,12,1,'seat','normal','A12','A12',NULL,'{\"row\": \"A\", \"number\": 12}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(13,1,1,2,'seat','normal','B1','B1',NULL,'{\"row\": \"B\", \"number\": 1}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(14,1,2,2,'seat','normal','B2','B2',NULL,'{\"row\": \"B\", \"number\": 2}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(15,1,3,2,'seat','normal','B3','B3',NULL,'{\"row\": \"B\", \"number\": 3}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(16,1,4,2,'seat','normal','B4','B4',NULL,'{\"row\": \"B\", \"number\": 4}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(17,1,5,2,'seat','normal','B5','B5',NULL,'{\"row\": \"B\", \"number\": 5}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(18,1,6,2,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(19,1,7,2,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(20,1,8,2,'seat','normal','B8','B8',NULL,'{\"row\": \"B\", \"number\": 8}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(21,1,9,2,'seat','normal','B9','B9',NULL,'{\"row\": \"B\", \"number\": 9}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(22,1,10,2,'seat','normal','B10','B10',NULL,'{\"row\": \"B\", \"number\": 10}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(23,1,11,2,'seat','normal','B11','B11',NULL,'{\"row\": \"B\", \"number\": 11}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(24,1,12,2,'seat','normal','B12','B12',NULL,'{\"row\": \"B\", \"number\": 12}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(25,1,1,3,'seat','normal','C1','C1',NULL,'{\"row\": \"C\", \"number\": 1}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(26,1,2,3,'seat','normal','C2','C2',NULL,'{\"row\": \"C\", \"number\": 2}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(27,1,3,3,'seat','normal','C3','C3',NULL,'{\"row\": \"C\", \"number\": 3}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(28,1,4,3,'seat','normal','C4','C4',NULL,'{\"row\": \"C\", \"number\": 4}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(29,1,5,3,'seat','normal','C5','C5',NULL,'{\"row\": \"C\", \"number\": 5}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(30,1,6,3,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(31,1,7,3,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(32,1,8,3,'seat','normal','C8','C8',NULL,'{\"row\": \"C\", \"number\": 8}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(33,1,9,3,'seat','normal','C9','C9',NULL,'{\"row\": \"C\", \"number\": 9}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(34,1,10,3,'seat','normal','C10','C10',NULL,'{\"row\": \"C\", \"number\": 10}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(35,1,11,3,'seat','normal','C11','C11',NULL,'{\"row\": \"C\", \"number\": 11}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(36,1,12,3,'seat','normal','C12','C12',NULL,'{\"row\": \"C\", \"number\": 12}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(37,1,1,4,'seat','normal','D1','D1',NULL,'{\"row\": \"D\", \"number\": 1}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(38,1,2,4,'seat','normal','D2','D2',NULL,'{\"row\": \"D\", \"number\": 2}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(39,1,3,4,'seat','normal','D3','D3',NULL,'{\"row\": \"D\", \"number\": 3}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(40,1,4,4,'seat','normal','D4','D4',NULL,'{\"row\": \"D\", \"number\": 4}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(41,1,5,4,'seat','normal','D5','D5',NULL,'{\"row\": \"D\", \"number\": 5}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(42,1,6,4,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(43,1,7,4,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(44,1,8,4,'seat','normal','D8','D8',NULL,'{\"row\": \"D\", \"number\": 8}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(45,1,9,4,'seat','normal','D9','D9',NULL,'{\"row\": \"D\", \"number\": 9}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(46,1,10,4,'seat','normal','D10','D10',NULL,'{\"row\": \"D\", \"number\": 10}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(47,1,11,4,'seat','normal','D11','D11',NULL,'{\"row\": \"D\", \"number\": 11}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(48,1,12,4,'seat','normal','D12','D12',NULL,'{\"row\": \"D\", \"number\": 12}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(49,1,1,5,'seat','normal','E1','E1',NULL,'{\"row\": \"E\", \"number\": 1}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(50,1,2,5,'seat','normal','E2','E2',NULL,'{\"row\": \"E\", \"number\": 2}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(51,1,3,5,'seat','normal','E3','E3',NULL,'{\"row\": \"E\", \"number\": 3}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(52,1,4,5,'seat','normal','E4','E4',NULL,'{\"row\": \"E\", \"number\": 4}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(53,1,5,5,'seat','normal','E5','E5',NULL,'{\"row\": \"E\", \"number\": 5}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(54,1,6,5,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(55,1,7,5,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(56,1,8,5,'seat','normal','E8','E8',NULL,'{\"row\": \"E\", \"number\": 8}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(57,1,9,5,'seat','normal','E9','E9',NULL,'{\"row\": \"E\", \"number\": 9}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(58,1,10,5,'seat','normal','E10','E10',NULL,'{\"row\": \"E\", \"number\": 10}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(59,1,11,5,'seat','normal','E11','E11',NULL,'{\"row\": \"E\", \"number\": 11}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(60,1,12,5,'seat','normal','E12','E12',NULL,'{\"row\": \"E\", \"number\": 12}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(61,1,1,6,'seat','normal','F1','F1',NULL,'{\"row\": \"F\", \"number\": 1}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(62,1,2,6,'seat','normal','F2','F2',NULL,'{\"row\": \"F\", \"number\": 2}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(63,1,3,6,'seat','normal','F3','F3',NULL,'{\"row\": \"F\", \"number\": 3}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(64,1,4,6,'seat','normal','F4','F4',NULL,'{\"row\": \"F\", \"number\": 4}','2026-08-08 08:37:30','2026-08-08 08:37:30'),(65,1,5,6,'seat','normal','F5','F5',NULL,'{\"row\": \"F\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(66,1,6,6,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(67,1,7,6,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(68,1,8,6,'seat','normal','F8','F8',NULL,'{\"row\": \"F\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(69,1,9,6,'seat','normal','F9','F9',NULL,'{\"row\": \"F\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(70,1,10,6,'seat','normal','F10','F10',NULL,'{\"row\": \"F\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(71,1,11,6,'seat','normal','F11','F11',NULL,'{\"row\": \"F\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(72,1,12,6,'seat','normal','F12','F12',NULL,'{\"row\": \"F\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(73,1,1,7,'seat','normal','G1','G1',NULL,'{\"row\": \"G\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(74,1,2,7,'seat','normal','G2','G2',NULL,'{\"row\": \"G\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(75,1,3,7,'seat','normal','G3','G3',NULL,'{\"row\": \"G\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(76,1,4,7,'seat','normal','G4','G4',NULL,'{\"row\": \"G\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(77,1,5,7,'seat','normal','G5','G5',NULL,'{\"row\": \"G\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(78,1,6,7,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(79,1,7,7,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(80,1,8,7,'seat','normal','G8','G8',NULL,'{\"row\": \"G\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(81,1,9,7,'seat','normal','G9','G9',NULL,'{\"row\": \"G\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(82,1,10,7,'seat','normal','G10','G10',NULL,'{\"row\": \"G\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(83,1,11,7,'seat','normal','G11','G11',NULL,'{\"row\": \"G\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(84,1,12,7,'seat','normal','G12','G12',NULL,'{\"row\": \"G\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(85,1,1,8,'seat','normal','H1','H1',NULL,'{\"row\": \"H\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(86,1,2,8,'seat','normal','H2','H2',NULL,'{\"row\": \"H\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(87,1,3,8,'seat','normal','H3','H3',NULL,'{\"row\": \"H\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(88,1,4,8,'seat','normal','H4','H4',NULL,'{\"row\": \"H\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(89,1,5,8,'seat','normal','H5','H5',NULL,'{\"row\": \"H\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(90,1,6,8,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(91,1,7,8,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(92,1,8,8,'seat','normal','H8','H8',NULL,'{\"row\": \"H\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(93,1,9,8,'seat','normal','H9','H9',NULL,'{\"row\": \"H\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(94,1,10,8,'seat','normal','H10','H10',NULL,'{\"row\": \"H\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(95,1,11,8,'seat','normal','H11','H11',NULL,'{\"row\": \"H\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(96,1,12,8,'seat','normal','H12','H12',NULL,'{\"row\": \"H\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(97,1,1,9,'seat','normal','I1','I1',NULL,'{\"row\": \"I\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(98,1,2,9,'seat','normal','I2','I2',NULL,'{\"row\": \"I\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(99,1,3,9,'seat','normal','I3','I3',NULL,'{\"row\": \"I\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(100,1,4,9,'seat','normal','I4','I4',NULL,'{\"row\": \"I\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(101,1,5,9,'seat','normal','I5','I5',NULL,'{\"row\": \"I\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(102,1,6,9,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(103,1,7,9,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(104,1,8,9,'seat','normal','I8','I8',NULL,'{\"row\": \"I\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(105,1,9,9,'seat','normal','I9','I9',NULL,'{\"row\": \"I\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(106,1,10,9,'seat','normal','I10','I10',NULL,'{\"row\": \"I\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(107,1,11,9,'seat','normal','I11','I11',NULL,'{\"row\": \"I\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(108,1,12,9,'seat','normal','I12','I12',NULL,'{\"row\": \"I\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(109,1,1,10,'seat','couple','J1','STANDARD_100-PAIR-1','STANDARD_100-PAIR-1','{\"row\": \"J\", \"number\": 1, \"pair_position\": \"left\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(110,1,2,10,'seat','couple','J2','STANDARD_100-PAIR-1','STANDARD_100-PAIR-1','{\"row\": \"J\", \"number\": 2, \"pair_position\": \"right\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(111,1,3,10,'seat','couple','J3','STANDARD_100-PAIR-2','STANDARD_100-PAIR-2','{\"row\": \"J\", \"number\": 3, \"pair_position\": \"left\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(112,1,4,10,'seat','couple','J4','STANDARD_100-PAIR-2','STANDARD_100-PAIR-2','{\"row\": \"J\", \"number\": 4, \"pair_position\": \"right\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(113,1,5,10,'seat','normal','J5','J5',NULL,'{\"row\": \"J\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(114,1,6,10,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(115,1,7,10,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(116,1,8,10,'seat','couple','J8','STANDARD_100-PAIR-3','STANDARD_100-PAIR-3','{\"row\": \"J\", \"number\": 8, \"pair_position\": \"left\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(117,1,9,10,'seat','couple','J9','STANDARD_100-PAIR-3','STANDARD_100-PAIR-3','{\"row\": \"J\", \"number\": 9, \"pair_position\": \"right\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(118,1,10,10,'seat','couple','J10','STANDARD_100-PAIR-4','STANDARD_100-PAIR-4','{\"row\": \"J\", \"number\": 10, \"pair_position\": \"left\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(119,1,11,10,'seat','couple','J11','STANDARD_100-PAIR-4','STANDARD_100-PAIR-4','{\"row\": \"J\", \"number\": 11, \"pair_position\": \"right\"}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(120,1,12,10,'seat','normal','J12','J12',NULL,'{\"row\": \"J\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(121,2,1,1,'seat','vip','A1','A1',NULL,'{\"row\": \"A\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(122,2,2,1,'seat','vip','A2','A2',NULL,'{\"row\": \"A\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(123,2,3,1,'seat','vip','A3','A3',NULL,'{\"row\": \"A\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(124,2,4,1,'seat','vip','A4','A4',NULL,'{\"row\": \"A\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(125,2,5,1,'seat','vip','A5','A5',NULL,'{\"row\": \"A\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(126,2,6,1,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(127,2,7,1,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(128,2,8,1,'seat','vip','A8','A8',NULL,'{\"row\": \"A\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(129,2,9,1,'seat','vip','A9','A9',NULL,'{\"row\": \"A\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(130,2,10,1,'seat','vip','A10','A10',NULL,'{\"row\": \"A\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(131,2,11,1,'seat','vip','A11','A11',NULL,'{\"row\": \"A\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(132,2,12,1,'seat','vip','A12','A12',NULL,'{\"row\": \"A\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(133,2,1,2,'seat','vip','B1','B1',NULL,'{\"row\": \"B\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(134,2,2,2,'seat','vip','B2','B2',NULL,'{\"row\": \"B\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(135,2,3,2,'seat','vip','B3','B3',NULL,'{\"row\": \"B\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(136,2,4,2,'seat','vip','B4','B4',NULL,'{\"row\": \"B\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(137,2,5,2,'seat','vip','B5','B5',NULL,'{\"row\": \"B\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(138,2,6,2,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(139,2,7,2,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(140,2,8,2,'seat','vip','B8','B8',NULL,'{\"row\": \"B\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(141,2,9,2,'seat','vip','B9','B9',NULL,'{\"row\": \"B\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(142,2,10,2,'seat','vip','B10','B10',NULL,'{\"row\": \"B\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(143,2,11,2,'seat','vip','B11','B11',NULL,'{\"row\": \"B\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(144,2,12,2,'seat','vip','B12','B12',NULL,'{\"row\": \"B\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(145,2,1,3,'seat','vip','C1','C1',NULL,'{\"row\": \"C\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(146,2,2,3,'seat','vip','C2','C2',NULL,'{\"row\": \"C\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(147,2,3,3,'seat','vip','C3','C3',NULL,'{\"row\": \"C\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(148,2,4,3,'seat','vip','C4','C4',NULL,'{\"row\": \"C\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(149,2,5,3,'seat','vip','C5','C5',NULL,'{\"row\": \"C\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(150,2,6,3,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(151,2,7,3,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(152,2,8,3,'seat','vip','C8','C8',NULL,'{\"row\": \"C\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(153,2,9,3,'seat','vip','C9','C9',NULL,'{\"row\": \"C\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(154,2,10,3,'seat','vip','C10','C10',NULL,'{\"row\": \"C\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(155,2,11,3,'seat','vip','C11','C11',NULL,'{\"row\": \"C\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(156,2,12,3,'seat','vip','C12','C12',NULL,'{\"row\": \"C\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(157,2,1,4,'seat','vip','D1','D1',NULL,'{\"row\": \"D\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(158,2,2,4,'seat','vip','D2','D2',NULL,'{\"row\": \"D\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(159,2,3,4,'seat','vip','D3','D3',NULL,'{\"row\": \"D\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(160,2,4,4,'seat','vip','D4','D4',NULL,'{\"row\": \"D\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(161,2,5,4,'seat','vip','D5','D5',NULL,'{\"row\": \"D\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(162,2,6,4,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(163,2,7,4,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(164,2,8,4,'seat','vip','D8','D8',NULL,'{\"row\": \"D\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(165,2,9,4,'seat','vip','D9','D9',NULL,'{\"row\": \"D\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(166,2,10,4,'seat','vip','D10','D10',NULL,'{\"row\": \"D\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(167,2,11,4,'seat','vip','D11','D11',NULL,'{\"row\": \"D\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(168,2,12,4,'seat','vip','D12','D12',NULL,'{\"row\": \"D\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(169,2,1,5,'seat','vip','E1','E1',NULL,'{\"row\": \"E\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(170,2,2,5,'seat','vip','E2','E2',NULL,'{\"row\": \"E\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(171,2,3,5,'seat','vip','E3','E3',NULL,'{\"row\": \"E\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(172,2,4,5,'seat','vip','E4','E4',NULL,'{\"row\": \"E\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(173,2,5,5,'seat','vip','E5','E5',NULL,'{\"row\": \"E\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(174,2,6,5,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(175,2,7,5,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(176,2,8,5,'seat','vip','E8','E8',NULL,'{\"row\": \"E\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(177,2,9,5,'seat','vip','E9','E9',NULL,'{\"row\": \"E\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(178,2,10,5,'seat','vip','E10','E10',NULL,'{\"row\": \"E\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(179,2,11,5,'seat','vip','E11','E11',NULL,'{\"row\": \"E\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(180,2,12,5,'seat','vip','E12','E12',NULL,'{\"row\": \"E\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(181,2,1,6,'seat','vip','F1','F1',NULL,'{\"row\": \"F\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(182,2,2,6,'seat','vip','F2','F2',NULL,'{\"row\": \"F\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(183,2,3,6,'seat','vip','F3','F3',NULL,'{\"row\": \"F\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(184,2,4,6,'seat','vip','F4','F4',NULL,'{\"row\": \"F\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(185,2,5,6,'seat','vip','F5','F5',NULL,'{\"row\": \"F\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(186,2,6,6,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(187,2,7,6,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(188,2,8,6,'seat','vip','F8','F8',NULL,'{\"row\": \"F\", \"number\": 8}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(189,2,9,6,'seat','vip','F9','F9',NULL,'{\"row\": \"F\", \"number\": 9}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(190,2,10,6,'seat','vip','F10','F10',NULL,'{\"row\": \"F\", \"number\": 10}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(191,2,11,6,'seat','vip','F11','F11',NULL,'{\"row\": \"F\", \"number\": 11}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(192,2,12,6,'seat','vip','F12','F12',NULL,'{\"row\": \"F\", \"number\": 12}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(193,2,1,7,'seat','vip','G1','G1',NULL,'{\"row\": \"G\", \"number\": 1}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(194,2,2,7,'seat','vip','G2','G2',NULL,'{\"row\": \"G\", \"number\": 2}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(195,2,3,7,'seat','vip','G3','G3',NULL,'{\"row\": \"G\", \"number\": 3}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(196,2,4,7,'seat','vip','G4','G4',NULL,'{\"row\": \"G\", \"number\": 4}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(197,2,5,7,'seat','vip','G5','G5',NULL,'{\"row\": \"G\", \"number\": 5}','2026-08-08 08:37:31','2026-08-08 08:37:31'),(198,2,6,7,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(199,2,7,7,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(200,2,8,7,'seat','vip','G8','G8',NULL,'{\"row\": \"G\", \"number\": 8}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(201,2,9,7,'seat','vip','G9','G9',NULL,'{\"row\": \"G\", \"number\": 9}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(202,2,10,7,'seat','vip','G10','G10',NULL,'{\"row\": \"G\", \"number\": 10}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(203,2,11,7,'seat','vip','G11','G11',NULL,'{\"row\": \"G\", \"number\": 11}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(204,2,12,7,'seat','vip','G12','G12',NULL,'{\"row\": \"G\", \"number\": 12}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(205,2,1,8,'seat','couple','H1','VIP_80-PAIR-1','VIP_80-PAIR-1','{\"row\": \"H\", \"number\": 1, \"pair_position\": \"left\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(206,2,2,8,'seat','couple','H2','VIP_80-PAIR-1','VIP_80-PAIR-1','{\"row\": \"H\", \"number\": 2, \"pair_position\": \"right\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(207,2,3,8,'seat','couple','H3','VIP_80-PAIR-2','VIP_80-PAIR-2','{\"row\": \"H\", \"number\": 3, \"pair_position\": \"left\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(208,2,4,8,'seat','couple','H4','VIP_80-PAIR-2','VIP_80-PAIR-2','{\"row\": \"H\", \"number\": 4, \"pair_position\": \"right\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(209,2,5,8,'seat','vip','H5','H5',NULL,'{\"row\": \"H\", \"number\": 5}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(210,2,6,8,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(211,2,7,8,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(212,2,8,8,'seat','couple','H8','VIP_80-PAIR-3','VIP_80-PAIR-3','{\"row\": \"H\", \"number\": 8, \"pair_position\": \"left\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(213,2,9,8,'seat','couple','H9','VIP_80-PAIR-3','VIP_80-PAIR-3','{\"row\": \"H\", \"number\": 9, \"pair_position\": \"right\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(214,2,10,8,'seat','couple','H10','VIP_80-PAIR-4','VIP_80-PAIR-4','{\"row\": \"H\", \"number\": 10, \"pair_position\": \"left\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(215,2,11,8,'seat','couple','H11','VIP_80-PAIR-4','VIP_80-PAIR-4','{\"row\": \"H\", \"number\": 11, \"pair_position\": \"right\"}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(216,2,12,8,'seat','vip','H12','H12',NULL,'{\"row\": \"H\", \"number\": 12}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(217,3,1,1,'seat','normal','A1','A1',NULL,'{\"row\": \"A\", \"number\": 1}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(218,3,2,1,'seat','normal','A2','A2',NULL,'{\"row\": \"A\", \"number\": 2}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(219,3,3,1,'seat','normal','A3','A3',NULL,'{\"row\": \"A\", \"number\": 3}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(220,3,4,1,'seat','normal','A4','A4',NULL,'{\"row\": \"A\", \"number\": 4}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(221,3,5,1,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(222,3,6,1,'seat','normal','A6','A6',NULL,'{\"row\": \"A\", \"number\": 6}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(223,3,7,1,'seat','normal','A7','A7',NULL,'{\"row\": \"A\", \"number\": 7}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(224,3,8,1,'seat','normal','A8','A8',NULL,'{\"row\": \"A\", \"number\": 8}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(225,3,9,1,'seat','normal','A9','A9',NULL,'{\"row\": \"A\", \"number\": 9}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(226,3,1,2,'seat','normal','B1','B1',NULL,'{\"row\": \"B\", \"number\": 1}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(227,3,2,2,'seat','normal','B2','B2',NULL,'{\"row\": \"B\", \"number\": 2}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(228,3,3,2,'seat','normal','B3','B3',NULL,'{\"row\": \"B\", \"number\": 3}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(229,3,4,2,'seat','normal','B4','B4',NULL,'{\"row\": \"B\", \"number\": 4}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(230,3,5,2,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(231,3,6,2,'seat','normal','B6','B6',NULL,'{\"row\": \"B\", \"number\": 6}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(232,3,7,2,'seat','normal','B7','B7',NULL,'{\"row\": \"B\", \"number\": 7}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(233,3,8,2,'seat','normal','B8','B8',NULL,'{\"row\": \"B\", \"number\": 8}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(234,3,9,2,'seat','normal','B9','B9',NULL,'{\"row\": \"B\", \"number\": 9}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(235,3,1,3,'seat','normal','C1','C1',NULL,'{\"row\": \"C\", \"number\": 1}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(236,3,2,3,'seat','normal','C2','C2',NULL,'{\"row\": \"C\", \"number\": 2}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(237,3,3,3,'seat','normal','C3','C3',NULL,'{\"row\": \"C\", \"number\": 3}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(238,3,4,3,'seat','normal','C4','C4',NULL,'{\"row\": \"C\", \"number\": 4}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(239,3,5,3,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(240,3,6,3,'seat','normal','C6','C6',NULL,'{\"row\": \"C\", \"number\": 6}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(241,3,7,3,'seat','normal','C7','C7',NULL,'{\"row\": \"C\", \"number\": 7}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(242,3,8,3,'seat','normal','C8','C8',NULL,'{\"row\": \"C\", \"number\": 8}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(243,3,9,3,'seat','normal','C9','C9',NULL,'{\"row\": \"C\", \"number\": 9}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(244,3,1,4,'seat','normal','D1','D1',NULL,'{\"row\": \"D\", \"number\": 1}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(245,3,2,4,'seat','normal','D2','D2',NULL,'{\"row\": \"D\", \"number\": 2}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(246,3,3,4,'seat','normal','D3','D3',NULL,'{\"row\": \"D\", \"number\": 3}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(247,3,4,4,'seat','normal','D4','D4',NULL,'{\"row\": \"D\", \"number\": 4}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(248,3,5,4,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(249,3,6,4,'seat','normal','D6','D6',NULL,'{\"row\": \"D\", \"number\": 6}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(250,3,7,4,'seat','normal','D7','D7',NULL,'{\"row\": \"D\", \"number\": 7}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(251,3,8,4,'seat','normal','D8','D8',NULL,'{\"row\": \"D\", \"number\": 8}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(252,3,9,4,'seat','normal','D9','D9',NULL,'{\"row\": \"D\", \"number\": 9}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(253,3,1,5,'seat','normal','E1','E1',NULL,'{\"row\": \"E\", \"number\": 1}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(254,3,2,5,'seat','normal','E2','E2',NULL,'{\"row\": \"E\", \"number\": 2}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(255,3,3,5,'seat','normal','E3','E3',NULL,'{\"row\": \"E\", \"number\": 3}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(256,3,4,5,'seat','normal','E4','E4',NULL,'{\"row\": \"E\", \"number\": 4}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(257,3,5,5,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(258,3,6,5,'seat','normal','E6','E6',NULL,'{\"row\": \"E\", \"number\": 6}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(259,3,7,5,'seat','normal','E7','E7',NULL,'{\"row\": \"E\", \"number\": 7}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(260,3,8,5,'seat','normal','E8','E8',NULL,'{\"row\": \"E\", \"number\": 8}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(261,3,9,5,'seat','normal','E9','E9',NULL,'{\"row\": \"E\", \"number\": 9}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(262,3,1,6,'seat','normal','F1','F1',NULL,'{\"row\": \"F\", \"number\": 1}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(263,3,2,6,'seat','normal','F2','F2',NULL,'{\"row\": \"F\", \"number\": 2}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(264,3,3,6,'seat','normal','F3','F3',NULL,'{\"row\": \"F\", \"number\": 3}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(265,3,4,6,'seat','normal','F4','F4',NULL,'{\"row\": \"F\", \"number\": 4}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(266,3,5,6,'aisle',NULL,NULL,NULL,NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32'),(267,3,6,6,'seat','normal','F6','F6',NULL,'{\"row\": \"F\", \"number\": 6}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(268,3,7,6,'seat','normal','F7','F7',NULL,'{\"row\": \"F\", \"number\": 7}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(269,3,8,6,'seat','normal','F8','F8',NULL,'{\"row\": \"F\", \"number\": 8}','2026-08-08 08:37:32','2026-08-08 08:37:32'),(270,3,9,6,'seat','normal','F9','F9',NULL,'{\"row\": \"F\", \"number\": 9}','2026-08-08 08:37:32','2026-08-08 08:37:32');
/*!40000 ALTER TABLE `room_layout_template_cells` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `room_layout_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_layout_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `room_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rows` tinyint unsigned NOT NULL,
  `columns` tinyint unsigned NOT NULL,
  `screen_position` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'top',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_layout_templates_code_unique` (`code`),
  KEY `room_layout_templates_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `room_layout_templates_updated_by_user_id_foreign` (`updated_by_user_id`),
  KEY `room_layout_templates_status_name_index` (`status`,`name`),
  CONSTRAINT `room_layout_templates_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_layout_templates_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `room_layout_templates` WRITE;
/*!40000 ALTER TABLE `room_layout_templates` DISABLE KEYS */;
INSERT INTO `room_layout_templates` VALUES (1,'STANDARD_100','Tiêu chuẩn 100 ghế','Mẫu khởi tạo R7; có thể chỉnh sửa trước khi áp dụng.','2D',10,12,'top','active',NULL,NULL,'2026-08-08 08:37:30','2026-08-08 08:37:30'),(2,'VIP_80','VIP 80 ghế','Mẫu khởi tạo R7; có thể chỉnh sửa trước khi áp dụng.',NULL,8,12,'top','active',NULL,NULL,'2026-08-08 08:37:31','2026-08-08 08:37:31'),(3,'COMPACT_48','Phòng nhỏ 48 ghế','Mẫu khởi tạo R7; có thể chỉnh sửa trước khi áp dụng.',NULL,6,9,'top','active',NULL,NULL,'2026-08-08 08:37:32','2026-08-08 08:37:32');
/*!40000 ALTER TABLE `room_layout_templates` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `room_layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_layouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_note` text COLLATE utf8mb4_unicode_ci,
  `source_template_id` bigint unsigned DEFAULT NULL,
  `source_template_name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rows` tinyint unsigned NOT NULL,
  `columns` tinyint unsigned NOT NULL,
  `screen_position` enum('top','bottom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'top',
  `status` enum('draft','published','retired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_layouts_room_id_version_unique` (`room_id`,`version`),
  KEY `room_layouts_created_by_foreign` (`created_by`),
  KEY `room_layouts_updated_by_foreign` (`updated_by`),
  KEY `room_layouts_room_id_status_index` (`room_id`,`status`),
  KEY `room_layouts_source_template_id_foreign` (`source_template_id`),
  CONSTRAINT `room_layouts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_layouts_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `room_layouts_source_template_id_foreign` FOREIGN KEY (`source_template_id`) REFERENCES `room_layout_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_layouts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `room_layouts` WRITE;
/*!40000 ALTER TABLE `room_layouts` DISABLE KEYS */;
INSERT INTO `room_layouts` VALUES (1,1,1,'Sơ đồ demo P01',NULL,NULL,NULL,3,4,'top','published','2026-08-08 08:37:33',NULL,NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(2,2,1,'Sơ đồ demo P02',NULL,NULL,NULL,3,4,'top','published','2026-08-08 08:37:33',NULL,NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(3,3,1,'Sơ đồ demo P03',NULL,NULL,NULL,3,4,'top','published','2026-08-08 08:37:33',NULL,NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(4,4,1,'Sơ đồ demo DEMO',NULL,NULL,NULL,4,8,'top','published','2026-08-08 08:37:33',NULL,NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(5,5,1,'Sơ đồ demo HD01',NULL,NULL,NULL,3,4,'top','published','2026-08-08 08:37:33',NULL,NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(6,6,1,'Sơ đồ demo NTL01',NULL,NULL,NULL,3,4,'top','published','2026-08-08 08:37:33',NULL,NULL,'2026-08-08 08:37:33','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `room_layouts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `room_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_types_name_unique` (`name`),
  UNIQUE KEY `room_types_slug_unique` (`slug`),
  UNIQUE KEY `room_types_code_unique` (`code`),
  KEY `room_types_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `room_types_updated_by_user_id_foreign` (`updated_by_user_id`),
  KEY `room_types_is_active_index` (`is_active`),
  CONSTRAINT `room_types_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_types_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `room_types` WRITE;
/*!40000 ALTER TABLE `room_types` DISABLE KEYS */;
INSERT INTO `room_types` VALUES (1,'2D','2D','2D',NULL,1,1,10,NULL,NULL,'2026-08-08 08:37:24','2026-08-08 08:37:24'),(2,'3D','3D','3D',NULL,1,1,20,NULL,NULL,'2026-08-08 08:37:24','2026-08-08 08:37:24'),(3,'IMAX','IMAX','IMAX',NULL,1,1,30,NULL,NULL,'2026-08-08 08:37:24','2026-08-08 08:37:24');
/*!40000 ALTER TABLE `room_types` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cinema_id` bigint unsigned NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2D',
  `room_type_id` bigint unsigned DEFAULT NULL,
  `total_seats` int NOT NULL DEFAULT '0',
  `cleaning_buffer_minutes` smallint unsigned DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rooms_cinema_id_code_unique` (`cinema_id`,`code`),
  KEY `rooms_room_type_id_foreign` (`room_type_id`),
  CONSTRAINT `rooms_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rooms_room_type_id_foreign` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,1,'P01','Phòng 1','2D',1,12,NULL,'active','2026-08-08 08:37:30','2026-08-08 08:37:33'),(2,1,'P02','Phòng 2','3D',2,12,NULL,'active','2026-08-08 08:37:30','2026-08-08 08:37:33'),(3,1,'P03','Phòng 3','IMAX',3,12,NULL,'active','2026-08-08 08:37:30','2026-08-08 08:37:33'),(4,1,'DEMO','Phòng demo bảo vệ','3D',2,28,NULL,'active','2026-08-08 08:37:30','2026-08-08 08:37:33'),(5,2,'HD01','Phòng 1','2D',1,12,NULL,'active','2026-08-08 08:37:30','2026-08-08 08:37:33'),(6,3,'NTL01','Phòng 1','2D',1,12,NULL,'active','2026-08-08 08:37:30','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `seat_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seat_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_modifier` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_pair` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seat_types_code_unique` (`code`),
  UNIQUE KEY `seat_types_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `seat_types` WRITE;
/*!40000 ALTER TABLE `seat_types` DISABLE KEYS */;
INSERT INTO `seat_types` VALUES (1,'Normal','normal','normal',NULL,NULL,NULL,NULL,0.00,0,1,0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(2,'VIP','vip','vip',NULL,NULL,NULL,NULL,20000.00,0,1,0,'2026-08-08 08:37:33','2026-08-08 08:37:33'),(3,'Couple','couple','couple',NULL,NULL,NULL,NULL,40000.00,1,1,0,'2026-08-08 08:37:33','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `seat_types` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `seats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint unsigned NOT NULL,
  `row` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int NOT NULL,
  `seat_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('normal','vip','couple') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `seat_type_id` bigint unsigned DEFAULT NULL,
  `pair_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pair_position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `row_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seat_number` int DEFAULT NULL,
  `x_position` int DEFAULT NULL,
  `y_position` int DEFAULT NULL,
  `is_center` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seats_room_id_seat_code_unique` (`room_id`,`seat_code`),
  KEY `seats_seat_type_id_foreign` (`seat_type_id`),
  CONSTRAINT `seats_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seats_seat_type_id_foreign` FOREIGN KEY (`seat_type_id`) REFERENCES `seat_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `seats` WRITE;
/*!40000 ALTER TABLE `seats` DISABLE KEYS */;
INSERT INTO `seats` VALUES (1,1,'A',1,'A1','normal',1,NULL,NULL,'A',1,1,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(2,1,'A',2,'A2','normal',1,NULL,NULL,'A',2,2,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(3,1,'A',3,'A3','normal',1,NULL,NULL,'A',3,3,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(4,1,'A',4,'A4','normal',1,NULL,NULL,'A',4,4,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(5,1,'B',1,'B1','normal',1,NULL,NULL,'B',1,1,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(6,1,'B',2,'B2','normal',1,NULL,NULL,'B',2,2,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(7,1,'B',3,'B3','normal',1,NULL,NULL,'B',3,3,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(8,1,'B',4,'B4','normal',1,NULL,NULL,'B',4,4,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(9,1,'C',1,'C1','normal',1,NULL,NULL,'C',1,1,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(10,1,'C',2,'C2','normal',1,NULL,NULL,'C',2,2,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(11,1,'C',3,'C3','normal',1,NULL,NULL,'C',3,3,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(12,1,'C',4,'C4','normal',1,NULL,NULL,'C',4,4,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(13,2,'A',1,'A1','normal',1,NULL,NULL,'A',1,1,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(14,2,'A',2,'A2','normal',1,NULL,NULL,'A',2,2,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(15,2,'A',3,'A3','normal',1,NULL,NULL,'A',3,3,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(16,2,'A',4,'A4','normal',1,NULL,NULL,'A',4,4,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(17,2,'B',1,'B1','normal',1,NULL,NULL,'B',1,1,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(18,2,'B',2,'B2','normal',1,NULL,NULL,'B',2,2,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(19,2,'B',3,'B3','normal',1,NULL,NULL,'B',3,3,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(20,2,'B',4,'B4','normal',1,NULL,NULL,'B',4,4,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(21,2,'C',1,'C1','normal',1,NULL,NULL,'C',1,1,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(22,2,'C',2,'C2','normal',1,NULL,NULL,'C',2,2,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(23,2,'C',3,'C3','normal',1,NULL,NULL,'C',3,3,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(24,2,'C',4,'C4','normal',1,NULL,NULL,'C',4,4,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(25,3,'A',1,'A1','normal',1,NULL,NULL,'A',1,1,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(26,3,'A',2,'A2','normal',1,NULL,NULL,'A',2,2,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(27,3,'A',3,'A3','normal',1,NULL,NULL,'A',3,3,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(28,3,'A',4,'A4','normal',1,NULL,NULL,'A',4,4,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(29,3,'B',1,'B1','normal',1,NULL,NULL,'B',1,1,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(30,3,'B',2,'B2','normal',1,NULL,NULL,'B',2,2,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(31,3,'B',3,'B3','normal',1,NULL,NULL,'B',3,3,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(32,3,'B',4,'B4','normal',1,NULL,NULL,'B',4,4,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(33,3,'C',1,'C1','normal',1,NULL,NULL,'C',1,1,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(34,3,'C',2,'C2','normal',1,NULL,NULL,'C',2,2,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(35,3,'C',3,'C3','normal',1,NULL,NULL,'C',3,3,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(36,3,'C',4,'C4','normal',1,NULL,NULL,'C',4,4,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(37,4,'A',1,'A1','normal',1,NULL,NULL,'A',1,1,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(38,4,'A',2,'A2','normal',1,NULL,NULL,'A',2,2,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(39,4,'A',3,'A3','normal',1,NULL,NULL,'A',3,3,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(40,4,'A',4,'A4','normal',1,NULL,NULL,'A',4,4,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(41,4,'A',6,'A6','normal',1,NULL,NULL,'A',6,6,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(42,4,'A',7,'A7','normal',1,NULL,NULL,'A',7,7,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(43,4,'A',8,'A8','normal',1,NULL,NULL,'A',8,8,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(44,4,'B',1,'B1','normal',1,NULL,NULL,'B',1,1,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(45,4,'B',2,'B2','normal',1,NULL,NULL,'B',2,2,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(46,4,'B',3,'B3','normal',1,NULL,NULL,'B',3,3,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(47,4,'B',4,'B4','normal',1,NULL,NULL,'B',4,4,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(48,4,'B',6,'B6','normal',1,NULL,NULL,'B',6,6,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(49,4,'B',7,'B7','normal',1,NULL,NULL,'B',7,7,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(50,4,'B',8,'B8','normal',1,NULL,NULL,'B',8,8,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(51,4,'C',1,'C1','couple',3,'DEMO-C-PAIR-1','left','C',1,1,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(52,4,'C',2,'C2','couple',3,'DEMO-C-PAIR-1','right','C',2,2,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(53,4,'C',3,'C3','normal',1,NULL,NULL,'C',3,3,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(54,4,'C',4,'C4','normal',1,NULL,NULL,'C',4,4,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(55,4,'C',6,'C6','normal',1,NULL,NULL,'C',6,6,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(56,4,'C',7,'C7','normal',1,NULL,NULL,'C',7,7,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(57,4,'C',8,'C8','normal',1,NULL,NULL,'C',8,8,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(58,4,'D',1,'D1','normal',1,NULL,NULL,'D',1,1,4,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(59,4,'D',2,'D2','normal',1,NULL,NULL,'D',2,2,4,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(60,4,'D',3,'D3','normal',1,NULL,NULL,'D',3,3,4,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(61,4,'D',4,'D4','normal',1,NULL,NULL,'D',4,4,4,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(62,4,'D',6,'D6','normal',1,NULL,NULL,'D',6,6,4,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(63,4,'D',7,'D7','normal',1,NULL,NULL,'D',7,7,4,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(64,4,'D',8,'D8','normal',1,NULL,NULL,'D',8,8,4,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(65,5,'A',1,'A1','normal',1,NULL,NULL,'A',1,1,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(66,5,'A',2,'A2','normal',1,NULL,NULL,'A',2,2,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(67,5,'A',3,'A3','normal',1,NULL,NULL,'A',3,3,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(68,5,'A',4,'A4','normal',1,NULL,NULL,'A',4,4,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(69,5,'B',1,'B1','normal',1,NULL,NULL,'B',1,1,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(70,5,'B',2,'B2','normal',1,NULL,NULL,'B',2,2,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(71,5,'B',3,'B3','normal',1,NULL,NULL,'B',3,3,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(72,5,'B',4,'B4','normal',1,NULL,NULL,'B',4,4,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(73,5,'C',1,'C1','normal',1,NULL,NULL,'C',1,1,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(74,5,'C',2,'C2','normal',1,NULL,NULL,'C',2,2,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(75,5,'C',3,'C3','normal',1,NULL,NULL,'C',3,3,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(76,5,'C',4,'C4','normal',1,NULL,NULL,'C',4,4,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(77,6,'A',1,'A1','normal',1,NULL,NULL,'A',1,1,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(78,6,'A',2,'A2','normal',1,NULL,NULL,'A',2,2,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(79,6,'A',3,'A3','normal',1,NULL,NULL,'A',3,3,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(80,6,'A',4,'A4','normal',1,NULL,NULL,'A',4,4,1,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(81,6,'B',1,'B1','normal',1,NULL,NULL,'B',1,1,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(82,6,'B',2,'B2','normal',1,NULL,NULL,'B',2,2,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(83,6,'B',3,'B3','normal',1,NULL,NULL,'B',3,3,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(84,6,'B',4,'B4','normal',1,NULL,NULL,'B',4,4,2,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(85,6,'C',1,'C1','normal',1,NULL,NULL,'C',1,1,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(86,6,'C',2,'C2','normal',1,NULL,NULL,'C',2,2,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(87,6,'C',3,'C3','normal',1,NULL,NULL,'C',3,3,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33'),(88,6,'C',4,'C4','normal',1,NULL,NULL,'C',4,4,3,0,'active','2026-08-08 08:37:33','2026-08-08 08:37:33');
/*!40000 ALTER TABLE `seats` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `showtimes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `showtimes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `movie_id` bigint unsigned NOT NULL,
  `cinema_id` bigint unsigned NOT NULL,
  `room_id` bigint unsigned NOT NULL,
  `room_layout_id` bigint unsigned DEFAULT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `vip_price` decimal(10,2) DEFAULT NULL,
  `pricing_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `showtimes_movie_id_foreign` (`movie_id`),
  KEY `showtimes_cinema_id_foreign` (`cinema_id`),
  KEY `showtimes_room_layout_id_foreign` (`room_layout_id`),
  KEY `showtimes_room_id_room_layout_id_index` (`room_id`,`room_layout_id`),
  KEY `showtimes_room_schedule_lookup_index` (`room_id`,`show_date`,`show_time`,`status`),
  CONSTRAINT `showtimes_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `showtimes_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `showtimes_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `showtimes_room_layout_id_foreign` FOREIGN KEY (`room_layout_id`) REFERENCES `room_layouts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=241 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `showtimes` WRITE;
/*!40000 ALTER TABLE `showtimes` DISABLE KEYS */;
INSERT INTO `showtimes` VALUES (1,10,1,1,1,'2026-08-09','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(2,11,1,1,1,'2026-08-09','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(3,12,1,1,1,'2026-08-09','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(4,13,1,1,1,'2026-08-09','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(5,11,1,2,2,'2026-08-09','09:00:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(6,12,1,2,2,'2026-08-09','12:45:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(7,13,1,2,2,'2026-08-09','16:30:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(8,14,1,2,2,'2026-08-09','20:15:00',130000.00,160000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(9,12,1,3,3,'2026-08-09','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(10,13,1,3,3,'2026-08-09','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(11,14,1,3,3,'2026-08-09','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(12,15,1,3,3,'2026-08-09','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(13,13,1,4,4,'2026-08-09','09:00:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(14,14,1,4,4,'2026-08-09','12:45:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(15,15,1,4,4,'2026-08-09','16:30:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(16,16,1,4,4,'2026-08-09','20:15:00',130000.00,160000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(17,11,1,1,1,'2026-08-10','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(18,12,1,1,1,'2026-08-10','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(19,13,1,1,1,'2026-08-10','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(20,14,1,1,1,'2026-08-10','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(21,12,1,2,2,'2026-08-10','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(22,13,1,2,2,'2026-08-10','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(23,14,1,2,2,'2026-08-10','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(24,15,1,2,2,'2026-08-10','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(25,13,1,3,3,'2026-08-10','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(26,14,1,3,3,'2026-08-10','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(27,15,1,3,3,'2026-08-10','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(28,16,1,3,3,'2026-08-10','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(29,14,1,4,4,'2026-08-10','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(30,15,1,4,4,'2026-08-10','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(31,16,1,4,4,'2026-08-10','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(32,17,1,4,4,'2026-08-10','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(33,12,1,1,1,'2026-08-11','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(34,13,1,1,1,'2026-08-11','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(35,14,1,1,1,'2026-08-11','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(36,15,1,1,1,'2026-08-11','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(37,13,1,2,2,'2026-08-11','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(38,14,1,2,2,'2026-08-11','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(39,15,1,2,2,'2026-08-11','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(40,16,1,2,2,'2026-08-11','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(41,14,1,3,3,'2026-08-11','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(42,15,1,3,3,'2026-08-11','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(43,16,1,3,3,'2026-08-11','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(44,17,1,3,3,'2026-08-11','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(45,15,1,4,4,'2026-08-11','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(46,16,1,4,4,'2026-08-11','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(47,17,1,4,4,'2026-08-11','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(48,18,1,4,4,'2026-08-11','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(49,13,1,1,1,'2026-08-12','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:34','2026-08-08 08:37:34'),(50,14,1,1,1,'2026-08-12','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(51,15,1,1,1,'2026-08-12','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(52,16,1,1,1,'2026-08-12','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(53,14,1,2,2,'2026-08-12','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(54,15,1,2,2,'2026-08-12','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(55,16,1,2,2,'2026-08-12','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(56,17,1,2,2,'2026-08-12','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(57,15,1,3,3,'2026-08-12','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(58,16,1,3,3,'2026-08-12','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(59,17,1,3,3,'2026-08-12','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(60,18,1,3,3,'2026-08-12','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(61,16,1,4,4,'2026-08-12','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(62,17,1,4,4,'2026-08-12','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(63,18,1,4,4,'2026-08-12','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(64,19,1,4,4,'2026-08-12','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(65,14,1,1,1,'2026-08-13','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(66,15,1,1,1,'2026-08-13','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(67,16,1,1,1,'2026-08-13','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(68,17,1,1,1,'2026-08-13','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(69,15,1,2,2,'2026-08-13','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(70,16,1,2,2,'2026-08-13','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(71,17,1,2,2,'2026-08-13','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(72,18,1,2,2,'2026-08-13','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(73,16,1,3,3,'2026-08-13','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(74,17,1,3,3,'2026-08-13','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(75,18,1,3,3,'2026-08-13','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(76,19,1,3,3,'2026-08-13','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(77,17,1,4,4,'2026-08-13','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(78,18,1,4,4,'2026-08-13','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(79,19,1,4,4,'2026-08-13','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(80,20,1,4,4,'2026-08-13','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(81,15,1,1,1,'2026-08-14','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(82,16,1,1,1,'2026-08-14','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(83,17,1,1,1,'2026-08-14','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(84,18,1,1,1,'2026-08-14','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(85,16,1,2,2,'2026-08-14','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(86,17,1,2,2,'2026-08-14','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(87,18,1,2,2,'2026-08-14','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(88,19,1,2,2,'2026-08-14','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(89,17,1,3,3,'2026-08-14','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(90,18,1,3,3,'2026-08-14','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(91,19,1,3,3,'2026-08-14','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(92,20,1,3,3,'2026-08-14','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(93,18,1,4,4,'2026-08-14','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(94,19,1,4,4,'2026-08-14','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:35','2026-08-08 08:37:35'),(95,20,1,4,4,'2026-08-14','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(96,21,1,4,4,'2026-08-14','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(97,16,1,1,1,'2026-08-15','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(98,17,1,1,1,'2026-08-15','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(99,18,1,1,1,'2026-08-15','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(100,19,1,1,1,'2026-08-15','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(101,17,1,2,2,'2026-08-15','09:00:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(102,18,1,2,2,'2026-08-15','12:45:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(103,19,1,2,2,'2026-08-15','16:30:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(104,20,1,2,2,'2026-08-15','20:15:00',130000.00,160000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(105,18,1,3,3,'2026-08-15','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(106,19,1,3,3,'2026-08-15','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(107,20,1,3,3,'2026-08-15','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(108,21,1,3,3,'2026-08-15','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(109,19,1,4,4,'2026-08-15','09:00:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(110,20,1,4,4,'2026-08-15','12:45:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(111,21,1,4,4,'2026-08-15','16:30:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(112,22,1,4,4,'2026-08-15','20:15:00',130000.00,160000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(113,17,1,1,1,'2026-08-16','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(114,18,1,1,1,'2026-08-16','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(115,19,1,1,1,'2026-08-16','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(116,20,1,1,1,'2026-08-16','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(117,18,1,2,2,'2026-08-16','09:00:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(118,19,1,2,2,'2026-08-16','12:45:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(119,20,1,2,2,'2026-08-16','16:30:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(120,21,1,2,2,'2026-08-16','20:15:00',130000.00,160000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(121,19,1,3,3,'2026-08-16','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(122,20,1,3,3,'2026-08-16','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(123,21,1,3,3,'2026-08-16','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(124,22,1,3,3,'2026-08-16','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(125,20,1,4,4,'2026-08-16','09:00:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(126,21,1,4,4,'2026-08-16','12:45:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(127,22,1,4,4,'2026-08-16','16:30:00',115000.00,145000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(128,23,1,4,4,'2026-08-16','20:15:00',130000.00,160000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(129,18,1,1,1,'2026-08-17','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(130,19,1,1,1,'2026-08-17','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(131,20,1,1,1,'2026-08-17','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(132,21,1,1,1,'2026-08-17','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:36','2026-08-08 08:37:36'),(133,19,1,2,2,'2026-08-17','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(134,20,1,2,2,'2026-08-17','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(135,21,1,2,2,'2026-08-17','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(136,22,1,2,2,'2026-08-17','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(137,20,1,3,3,'2026-08-17','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(138,21,1,3,3,'2026-08-17','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(139,22,1,3,3,'2026-08-17','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(140,23,1,3,3,'2026-08-17','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(141,21,1,4,4,'2026-08-17','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(142,22,1,4,4,'2026-08-17','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(143,23,1,4,4,'2026-08-17','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(144,24,1,4,4,'2026-08-17','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(145,19,1,1,1,'2026-08-18','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(146,20,1,1,1,'2026-08-18','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(147,21,1,1,1,'2026-08-18','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(148,22,1,1,1,'2026-08-18','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(149,20,1,2,2,'2026-08-18','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(150,21,1,2,2,'2026-08-18','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(151,22,1,2,2,'2026-08-18','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(152,23,1,2,2,'2026-08-18','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(153,21,1,3,3,'2026-08-18','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(154,22,1,3,3,'2026-08-18','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(155,23,1,3,3,'2026-08-18','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(156,24,1,3,3,'2026-08-18','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(157,22,1,4,4,'2026-08-18','09:00:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(158,23,1,4,4,'2026-08-18','12:45:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(159,24,1,4,4,'2026-08-18','16:30:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(160,9,1,4,4,'2026-08-18','20:15:00',120000.00,150000.00,'cinema-pricing-v1','active','2026-08-08 08:37:37','2026-08-08 08:37:37'),(161,11,2,5,5,'2026-08-09','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(162,12,2,5,5,'2026-08-09','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(163,13,2,5,5,'2026-08-09','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(164,14,2,5,5,'2026-08-09','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(165,12,2,5,5,'2026-08-10','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(166,13,2,5,5,'2026-08-10','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(167,14,2,5,5,'2026-08-10','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(168,15,2,5,5,'2026-08-10','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(169,13,2,5,5,'2026-08-11','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(170,14,2,5,5,'2026-08-11','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(171,15,2,5,5,'2026-08-11','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(172,16,2,5,5,'2026-08-11','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(173,14,2,5,5,'2026-08-12','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(174,15,2,5,5,'2026-08-12','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(175,16,2,5,5,'2026-08-12','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(176,17,2,5,5,'2026-08-12','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(177,15,2,5,5,'2026-08-13','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(178,16,2,5,5,'2026-08-13','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(179,17,2,5,5,'2026-08-13','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(180,18,2,5,5,'2026-08-13','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(181,16,2,5,5,'2026-08-14','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(182,17,2,5,5,'2026-08-14','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(183,18,2,5,5,'2026-08-14','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(184,19,2,5,5,'2026-08-14','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(185,17,2,5,5,'2026-08-15','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(186,18,2,5,5,'2026-08-15','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(187,19,2,5,5,'2026-08-15','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(188,20,2,5,5,'2026-08-15','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(189,18,2,5,5,'2026-08-16','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(190,19,2,5,5,'2026-08-16','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(191,20,2,5,5,'2026-08-16','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(192,21,2,5,5,'2026-08-16','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:38','2026-08-08 08:37:38'),(193,19,2,5,5,'2026-08-17','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(194,20,2,5,5,'2026-08-17','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(195,21,2,5,5,'2026-08-17','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(196,22,2,5,5,'2026-08-17','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(197,20,2,5,5,'2026-08-18','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(198,21,2,5,5,'2026-08-18','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(199,22,2,5,5,'2026-08-18','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(200,23,2,5,5,'2026-08-18','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(201,12,3,6,6,'2026-08-09','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(202,13,3,6,6,'2026-08-09','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(203,14,3,6,6,'2026-08-09','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(204,15,3,6,6,'2026-08-09','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(205,13,3,6,6,'2026-08-10','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(206,14,3,6,6,'2026-08-10','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(207,15,3,6,6,'2026-08-10','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(208,16,3,6,6,'2026-08-10','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(209,14,3,6,6,'2026-08-11','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(210,15,3,6,6,'2026-08-11','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(211,16,3,6,6,'2026-08-11','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(212,17,3,6,6,'2026-08-11','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(213,15,3,6,6,'2026-08-12','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(214,16,3,6,6,'2026-08-12','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(215,17,3,6,6,'2026-08-12','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(216,18,3,6,6,'2026-08-12','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(217,16,3,6,6,'2026-08-13','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(218,17,3,6,6,'2026-08-13','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(219,18,3,6,6,'2026-08-13','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(220,19,3,6,6,'2026-08-13','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(221,17,3,6,6,'2026-08-14','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(222,18,3,6,6,'2026-08-14','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(223,19,3,6,6,'2026-08-14','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(224,20,3,6,6,'2026-08-14','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(225,18,3,6,6,'2026-08-15','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(226,19,3,6,6,'2026-08-15','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:39','2026-08-08 08:37:39'),(227,20,3,6,6,'2026-08-15','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(228,21,3,6,6,'2026-08-15','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(229,19,3,6,6,'2026-08-16','09:00:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(230,20,3,6,6,'2026-08-16','12:45:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(231,21,3,6,6,'2026-08-16','16:30:00',90000.00,120000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(232,22,3,6,6,'2026-08-16','20:15:00',105000.00,135000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(233,20,3,6,6,'2026-08-17','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(234,21,3,6,6,'2026-08-17','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(235,22,3,6,6,'2026-08-17','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(236,23,3,6,6,'2026-08-17','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(237,21,3,6,6,'2026-08-18','09:00:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(238,22,3,6,6,'2026-08-18','12:45:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(239,23,3,6,6,'2026-08-18','16:30:00',80000.00,110000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40'),(240,24,3,6,6,'2026-08-18','20:15:00',95000.00,125000.00,'cinema-pricing-v1','active','2026-08-08 08:37:40','2026-08-08 08:37:40');
/*!40000 ALTER TABLE `showtimes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `ticket_checkin_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_checkin_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `showtime_id` bigint unsigned DEFAULT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `actor_role_snapshot` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scanned_at` timestamp NOT NULL,
  `request_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safe_ip_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent_summary` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_checkins_booking_result_index` (`booking_id`,`result`),
  KEY `ticket_checkins_booking_index` (`booking_id`),
  KEY `ticket_checkins_showtime_index` (`showtime_id`),
  KEY `ticket_checkins_actor_index` (`actor_user_id`),
  KEY `ticket_checkin_events_result_index` (`result`),
  KEY `ticket_checkin_events_scanned_at_index` (`scanned_at`),
  KEY `ticket_checkin_events_request_id_index` (`request_id`),
  CONSTRAINT `ticket_checkin_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_checkin_events_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ticket_checkin_events_showtime_id_foreign` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ticket_checkin_events` WRITE;
/*!40000 ALTER TABLE `ticket_checkin_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_checkin_events` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `ticket_checkin_events_prevent_update` BEFORE UPDATE ON `ticket_checkin_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ticket_checkin_events are append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `ticket_checkin_events_prevent_delete` BEFORE DELETE ON `ticket_checkin_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ticket_checkin_events are append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `user_cinema_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_cinema_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cinema_id` bigint unsigned NOT NULL,
  `assigned_by_user_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_cinema_assignment_unique` (`user_id`,`cinema_id`),
  KEY `user_cinema_assignments_assigned_by_user_id_foreign` (`assigned_by_user_id`),
  KEY `cinema_assignment_status_index` (`cinema_id`,`status`),
  KEY `user_cinema_assignments_status_index` (`status`),
  CONSTRAINT `user_cinema_assignments_assigned_by_user_id_foreign` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_cinema_assignments_cinema_id_foreign` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `user_cinema_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_cinema_assignments` WRITE;
/*!40000 ALTER TABLE `user_cinema_assignments` DISABLE KEYS */;
INSERT INTO `user_cinema_assignments` VALUES (1,3,1,NULL,'active','2026-08-08 08:37:40','2026-08-08 08:37:40','2026-08-08 08:37:40'),(2,4,1,NULL,'active','2026-08-08 08:37:40','2026-08-08 08:37:40','2026-08-08 08:37:40'),(3,5,2,NULL,'active','2026-08-08 08:37:40','2026-08-08 08:37:40','2026-08-08 08:37:40'),(4,6,2,NULL,'active','2026-08-08 08:37:40','2026-08-08 08:37:40','2026-08-08 08:37:40'),(5,7,3,NULL,'active','2026-08-08 08:37:40','2026-08-08 08:37:40','2026-08-08 08:37:40'),(6,8,3,NULL,'active','2026-08-08 08:37:40','2026-08-08 08:37:40','2026-08-08 08:37:40');
/*!40000 ALTER TABLE `user_cinema_assignments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'MovieMate Global Admin','admin@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',1,NULL,'active'),(2,'MovieMate Demo Customer','customer@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',4,NULL,'active'),(3,'Manager CG','manager.cg@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',2,NULL,'active'),(4,'Staff CG','staff.cg@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',3,NULL,'active'),(5,'Manager HD','manager.hd@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',2,NULL,'active'),(6,'Staff HD','staff.hd@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',3,NULL,'active'),(7,'Manager NTL','manager.ntl@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',2,NULL,'active'),(8,'Staff NTL','staff.ntl@moviemate.test',NULL,'2026-08-08 08:37:40','$2y$12$I9AMiMtb9BuzSpXxNF03LOGLBLeKR5dHzn.YbJ2JJwqg4w35sKCU2',NULL,'2026-08-08 08:37:40','2026-08-08 08:37:40',3,NULL,'active');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
