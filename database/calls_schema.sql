-- WhatsApp Calling API Database Schema
-- This file contains all database tables needed for WhatsApp Calling features

-- Table for storing call permissions
CREATE TABLE IF NOT EXISTS `whatsapp_call_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) NOT NULL,
  `user_wa_id` varchar(20) NOT NULL,
  `business_phone_number_id` varchar(50) NOT NULL,
  `permission_status` enum('no_permission','temporary','permanent') DEFAULT 'no_permission',
  `expiration_time` int(11) DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_phone_user_business` (`phone_number`, `user_wa_id`, `business_phone_number_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_user_wa_id` (`user_wa_id`),
  KEY `idx_permission_status` (`permission_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing call records
CREATE TABLE IF NOT EXISTS `whatsapp_calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` varchar(255) NOT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `user_wa_id` varchar(20) DEFAULT NULL,
  `business_phone_number_id` varchar(50) NOT NULL,
  `direction` enum('BUSINESS_INITIATED','USER_INITIATED') NOT NULL,
  `status` enum('RINGING','ACCEPTED','REJECTED','TERMINATED','FAILED','COMPLETED') DEFAULT 'RINGING',
  `assigned_agent_id` int(11) DEFAULT NULL,
  `sdp_offer` longtext DEFAULT NULL,
  `sdp_answer` longtext DEFAULT NULL,
  `start_time` int(11) DEFAULT NULL,
  `end_time` int(11) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `biz_opaque_callback_data` varchar(512) DEFAULT NULL,
  `deeplink_payload` varchar(512) DEFAULT NULL,
  `cta_payload` varchar(512) DEFAULT NULL,
  `error_code` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_call_id` (`call_id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_assigned_agent_id` (`assigned_agent_id`),
  KEY `idx_direction` (`direction`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `wa_calls_conv_fk` FOREIGN KEY (`conversation_id`) REFERENCES `whatsapp_conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wa_calls_agent_fk` FOREIGN KEY (`assigned_agent_id`) REFERENCES `agents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing call settings
CREATE TABLE IF NOT EXISTS `whatsapp_call_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number_id` varchar(50) NOT NULL,
  `calling_status` enum('ENABLED','DISABLED') DEFAULT 'DISABLED',
  `call_icon_visibility` enum('DEFAULT','DISABLE_ALL') DEFAULT 'DEFAULT',
  `callback_permission_status` enum('ENABLED','DISABLED') DEFAULT 'DISABLED',
  `call_hours_status` enum('ENABLED','DISABLED') DEFAULT 'DISABLED',
  `call_hours_timezone` varchar(100) DEFAULT NULL,
  `call_hours_weekly_schedule` longtext DEFAULT NULL COMMENT 'JSON array of weekly operating hours',
  `call_hours_holiday_schedule` longtext DEFAULT NULL COMMENT 'JSON array of holiday schedules',
  `sip_status` enum('ENABLED','DISABLED') DEFAULT 'DISABLED',
  `sip_servers` longtext DEFAULT NULL COMMENT 'JSON array of SIP server configurations',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_phone_number_id` (`phone_number_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing analytics data
CREATE TABLE IF NOT EXISTS `whatsapp_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `waba_id` varchar(100) NOT NULL,
  `phone_number_id` varchar(50) DEFAULT NULL,
  `analytics_type` enum('messaging','conversation','pricing','template','template_group') NOT NULL,
  `start_time` int(11) NOT NULL,
  `end_time` int(11) NOT NULL,
  `granularity` enum('HALF_HOUR','DAY','DAILY','MONTH','MONTHLY') NOT NULL,
  `data_points` longtext NOT NULL COMMENT 'JSON array of data points',
  `filters_json` longtext DEFAULT NULL COMMENT 'JSON object of applied filters',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_analytics` (`waba_id`, `analytics_type`, `start_time`, `end_time`, `granularity`, `phone_number_id`),
  KEY `idx_waba_id` (`waba_id`),
  KEY `idx_phone_number_id` (`phone_number_id`),
  KEY `idx_analytics_type` (`analytics_type`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_end_time` (`end_time`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing payment orders (Cashfree integration)
CREATE TABLE IF NOT EXISTS `whatsapp_payment_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(100) NOT NULL,
  `cf_order_id` varchar(100) DEFAULT NULL,
  `cf_payment_id` varchar(100) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `order_amount` decimal(10,2) NOT NULL,
  `order_currency` varchar(10) DEFAULT 'INR',
  `payment_status` enum('PENDING','SUCCESS','FAILED','USER_DROPPED','CANCELLED') DEFAULT 'PENDING',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_configuration` varchar(100) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `merchant_vpa` varchar(100) DEFAULT NULL,
  `order_expiry_time` datetime DEFAULT NULL,
  `payment_time` datetime DEFAULT NULL,
  `payment_completion_time` datetime DEFAULT NULL,
  `webhook_data` longtext DEFAULT NULL COMMENT 'JSON object of webhook payload',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_id` (`order_id`),
  KEY `idx_cf_order_id` (`cf_order_id`),
  KEY `idx_cf_payment_id` (`cf_payment_id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `wa_payment_conv_fk` FOREIGN KEY (`conversation_id`) REFERENCES `whatsapp_conversations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing welcome message sequences
CREATE TABLE IF NOT EXISTS `whatsapp_welcome_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` varchar(100) NOT NULL,
  `waba_id` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `text` text DEFAULT NULL,
  `autofill_message` text DEFAULT NULL,
  `ice_breakers_json` longtext DEFAULT NULL COMMENT 'JSON array of ice breakers',
  `is_used_in_ad` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sequence_id` (`sequence_id`),
  KEY `idx_waba_id` (`waba_id`),
  KEY `idx_is_used_in_ad` (`is_used_in_ad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for throughput metrics
CREATE TABLE IF NOT EXISTS `whatsapp_throughput_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number_id` varchar(50) NOT NULL,
  `current_rate` int(11) NOT NULL COMMENT 'Messages per second',
  `max_throughput` int(11) NOT NULL COMMENT 'Maximum throughput allowed',
  `utilization_percent` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone_number_id` (`phone_number_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for throughput upgrades
CREATE TABLE IF NOT EXISTS `whatsapp_throughput_upgrades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number_id` varchar(50) NOT NULL,
  `event` varchar(50) NOT NULL,
  `max_daily_conversations` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone_number_id` (`phone_number_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for message queue (for throughput management)
CREATE TABLE IF NOT EXISTS `whatsapp_message_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number_id` varchar(50) NOT NULL,
  `to_phone` varchar(20) NOT NULL,
  `message_type` varchar(50) NOT NULL,
  `message_data` longtext NOT NULL COMMENT 'JSON object of message data',
  `priority` int(11) DEFAULT 0,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone_number_id` (`phone_number_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add customer fields to payment orders
ALTER TABLE `whatsapp_payment_orders`
  ADD COLUMN IF NOT EXISTS `customer_id` varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `customer_phone` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `customer_email` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `customer_name` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `payment_session_id` varchar(255) DEFAULT NULL;

-- Add call-related columns to whatsapp_conversations if they don't exist
ALTER TABLE `whatsapp_conversations` 
  ADD COLUMN IF NOT EXISTS `call_enabled` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `call_permission_status` enum('no_permission','temporary','permanent') DEFAULT 'no_permission',
  ADD COLUMN IF NOT EXISTS `last_call_at` timestamp NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `total_calls` int(11) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `missed_calls` int(11) DEFAULT 0;

-- Add call-related columns to whatsapp_messages if they don't exist
ALTER TABLE `whatsapp_messages`
  ADD COLUMN IF NOT EXISTS `context_message_id` varchar(100) DEFAULT NULL COMMENT 'For contextual replies',
  ADD COLUMN IF NOT EXISTS `typing_indicator_sent` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `link_preview_data` longtext DEFAULT NULL COMMENT 'JSON object of link preview data';

-- Index for context_message_id
ALTER TABLE `whatsapp_messages`
  ADD KEY IF NOT EXISTS `idx_context_message_id` (`context_message_id`);

-- Table for link preview cache
CREATE TABLE IF NOT EXISTS `link_preview_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url_hash` varchar(64) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `preview_data` longtext DEFAULT NULL COMMENT 'JSON object with og:title, og:description, og:image, og:url',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_url_hash` (`url_hash`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update whatsapp_templates table to support enhanced template management
ALTER TABLE `whatsapp_templates`
  ADD COLUMN IF NOT EXISTS `meta_template_id` varchar(100) DEFAULT NULL COMMENT 'Meta API template ID',
  ADD COLUMN IF NOT EXISTS `parameter_format` enum('named','positional') DEFAULT 'positional',
  ADD COLUMN IF NOT EXISTS `components_json` longtext DEFAULT NULL COMMENT 'Full template components JSON',
  ADD COLUMN IF NOT EXISTS `quality_rating` enum('GREEN','YELLOW','RED') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `rejected_reason` text DEFAULT NULL,
  ADD KEY IF NOT EXISTS `idx_meta_template_id` (`meta_template_id`),
  ADD KEY IF NOT EXISTS `idx_status` (`status`);

