-- Run this SQL on your MySQL database (e.g. admin_acecare) to create the missing table.
-- Then WhatsApp logging will work without running the full Laravel migration.

CREATE TABLE IF NOT EXISTS `whatsapp_message_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` varchar(255) DEFAULT NULL,
  `to` varchar(255) DEFAULT NULL,
  `template_name` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'sent',
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `request_payload` json DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `meta_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whatsapp_message_logs_message_id_index` (`message_id`),
  KEY `whatsapp_message_logs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: record this migration so "php artisan migrate" does not run it again.
-- INSERT INTO `migrations` (`migration`, `batch`) SELECT '2026_02_09_000001_create_whatsapp_message_logs_table', IFNULL((SELECT MAX(batch) FROM migrations m), 0) + 1;
