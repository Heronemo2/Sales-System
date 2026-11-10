CREATE TABLE `user_notifications` (
  `notification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `event_key` varchar(160) NOT NULL,
  `title` varchar(120) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  UNIQUE KEY `uq_user_notifications_event` (`user_id`,`event_key`),
  KEY `idx_user_notifications_unread` (`user_id`,`read_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
