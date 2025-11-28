-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 28, 2025 at 09:47 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u675018328_kusum`
--

-- --------------------------------------------------------

--
-- Table structure for table `ad_budgets`
--

CREATE TABLE `ad_budgets` (
  `id` int(11) NOT NULL,
  `campaign_id` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `budget_amount` decimal(10,2) NOT NULL,
  `spend_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `id` int(11) NOT NULL,
  `device_token` varchar(255) DEFAULT NULL,
  `assigned_campaigns` text DEFAULT NULL,
  `last_assignment` timestamp NULL DEFAULT NULL,
  `assigned_sheets` text DEFAULT NULL COMMENT 'JSON array of sheet names',
  `assigned_forms` text DEFAULT NULL,
  `onesignal_player_id` varchar(255) DEFAULT NULL,
  `pusher_device_id` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`id`, `device_token`, `assigned_campaigns`, `last_assignment`, `assigned_sheets`, `assigned_forms`, `onesignal_player_id`, `pusher_device_id`, `last_login`) VALUES
(2, NULL, NULL, '2025-11-27 15:23:12', NULL, '[]', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `agent_responses`
--

CREATE TABLE `agent_responses` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `response_text` text DEFAULT NULL,
  `response_status` enum('contacted','qualified','not_qualified','call_not_picked','payment_completed') NOT NULL,
  `price_offered` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flow_edges`
--

CREATE TABLE `flow_edges` (
  `id` int(11) NOT NULL,
  `flow_id` int(11) NOT NULL,
  `from_node_id` int(11) NOT NULL,
  `to_node_id` int(11) NOT NULL,
  `condition_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flow_nodes`
--

CREATE TABLE `flow_nodes` (
  `id` int(11) NOT NULL,
  `flow_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `config_json` longtext DEFAULT NULL,
  `pos_x` int(11) DEFAULT 0,
  `pos_y` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flow_runs`
--

CREATE TABLE `flow_runs` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `flow_id` int(11) NOT NULL,
  `state_json` longtext DEFAULT NULL,
  `next_node_id` varchar(50) DEFAULT NULL,
  `resume_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flow_variables`
--

CREATE TABLE `flow_variables` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `var_key` varchar(100) NOT NULL,
  `var_value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `followup_reminders`
--

CREATE TABLE `followup_reminders` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `reminder_time` datetime NOT NULL,
  `reminder_note` text DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `external_id` varchar(100) NOT NULL,
  `created_time` timestamp NOT NULL,
  `ad_id` varchar(100) DEFAULT NULL,
  `ad_name` varchar(255) DEFAULT NULL,
  `adset_id` varchar(100) DEFAULT NULL,
  `adset_name` varchar(255) DEFAULT NULL,
  `campaign_id` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `form_id` varchar(100) DEFAULT NULL,
  `form_name` varchar(255) DEFAULT NULL,
  `is_organic` tinyint(1) DEFAULT 0,
  `platform` varchar(50) DEFAULT NULL,
  `question_text` text DEFAULT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `lead_status` varchar(50) DEFAULT NULL,
  `sheet_source` varchar(100) DEFAULT NULL,
  `raw_json` text DEFAULT NULL,
  `lead_score` int(11) DEFAULT 0,
  `score_factors` text DEFAULT NULL,
  `priority_level` enum('low','medium','high','hot') DEFAULT 'medium',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `external_id`, `created_time`, `ad_id`, `ad_name`, `adset_id`, `adset_name`, `campaign_id`, `campaign_name`, `form_id`, `form_name`, `is_organic`, `platform`, `question_text`, `full_name`, `phone_number`, `city`, `lead_status`, `sheet_source`, `raw_json`, `lead_score`, `score_factors`, `priority_level`, `created_at`, `updated_at`) VALUES
(1, 'l:4229609260619426', '2025-11-27 12:12:59', 'ag:120225411063300521', 'Tailored leads campaign 02/01/2025 Ad', 'as:120225411063310521', 'Mayara ads Ad Set', 'c:120225411063320521', 'Mayara ads Campaign', 'f:836971255412599', 'Mayara', 0, 'fb', 'हाँ', 'Raj Kumar', '+919459462009', 'hamirpur,h,p', 'CREATED', 'Sheet1', '{\"id\":\"l:4229609260619426\",\"created_time\":\"2025-11-27T12:12:59.000Z\",\"ad_id\":\"ag:120225411063300521\",\"ad_name\":\"Tailored leads campaign 02\\/01\\/2025 Ad\",\"adset_id\":\"as:120225411063310521\",\"adset_name\":\"Mayara ads Ad Set\",\"campaign_id\":\"c:120225411063320521\",\"campaign_name\":\"Mayara ads Campaign\",\"form_id\":\"f:836971255412599\",\"form_name\":\"Mayara\",\"is_organic\":\"false\",\"platform\":\"fb\",\"\\u0915\\u094d\\u092f\\u093e_\\u0906\\u092a_\\u092e\\u0947\\u0921\\u093f\\u0915\\u0932_\\u0915\\u094d\\u0937\\u0947\\u0924\\u094d\\u0930_\\u092e\\u0947\\u0902_\\u0905\\u092a\\u0928\\u093e_\\u092d\\u0935\\u093f\\u0937\\u094d\\u092f_\\u092c\\u0928\\u093e\\u0928\\u093e_\\u091a\\u093e\\u0939\\u0924\\u0947_\\u0939\\u0948_?\":\"\\u0939\\u093e\\u0901\",\"full_name\":\"Raj Kumar\",\"phone_number\":\"+919459462009\",\"city\":\"hamirpur,h,p\",\"lead_status\":\"CREATED\",\"synced\":\"\",\"external_id\":\"l:4229609260619426\",\"sheet_source\":\"Sheet1\",\"hindi_question\":\"\\u0939\\u093e\\u0901\"}', 75, '[\"Very fresh lead\"]', 'high', '2025-11-27 12:13:37', '2025-11-27 12:13:39'),
(2, 'l:1972743739967707', '2025-11-27 13:11:26', 'ag:120225411063300521', 'Tailored leads campaign 02/01/2025 Ad', 'as:120225411063310521', 'Mayara ads Ad Set', 'c:120225411063320521', 'Mayara ads Campaign', 'f:836971255412599', 'Mayara', 0, 'ig', 'हाँ', 'Malti  mahant', '+918103186281', 'Bilaspur', 'CREATED', 'Sheet1', '{\"id\":\"l:1972743739967707\",\"created_time\":\"2025-11-27T13:11:26.000Z\",\"ad_id\":\"ag:120225411063300521\",\"ad_name\":\"Tailored leads campaign 02\\/01\\/2025 Ad\",\"adset_id\":\"as:120225411063310521\",\"adset_name\":\"Mayara ads Ad Set\",\"campaign_id\":\"c:120225411063320521\",\"campaign_name\":\"Mayara ads Campaign\",\"form_id\":\"f:836971255412599\",\"form_name\":\"Mayara\",\"is_organic\":\"false\",\"platform\":\"ig\",\"\\u0915\\u094d\\u092f\\u093e_\\u0906\\u092a_\\u092e\\u0947\\u0921\\u093f\\u0915\\u0932_\\u0915\\u094d\\u0937\\u0947\\u0924\\u094d\\u0930_\\u092e\\u0947\\u0902_\\u0905\\u092a\\u0928\\u093e_\\u092d\\u0935\\u093f\\u0937\\u094d\\u092f_\\u092c\\u0928\\u093e\\u0928\\u093e_\\u091a\\u093e\\u0939\\u0924\\u0947_\\u0939\\u0948_?\":\"\\u0939\\u093e\\u0901\",\"full_name\":\"Malti  mahant\",\"phone_number\":\"+918103186281\",\"city\":\"Bilaspur\",\"lead_status\":\"CREATED\",\"synced\":\"\",\"external_id\":\"l:1972743739967707\",\"sheet_source\":\"Sheet1\",\"hindi_question\":\"\\u0939\\u093e\\u0901\"}', 75, '[\"Very fresh lead\"]', 'high', '2025-11-27 13:11:36', '2025-11-27 13:11:38'),
(3, 'l:2254034315077429', '2025-11-27 13:24:50', 'ag:120236089633530521', 'Tailored leads campaign 02/01/2025 Ad – Copy', 'as:120236089633540521', 'sushma ads Ad Set – Copy', 'c:120236089633550521', 'Neha ads Campaign', 'f:1338570067270657', 'neha', 0, 'ig', 'हाँ', 'Siddharth Kumar', '+919570078996', 'Madhepura', 'CREATED', 'Sheet1', '{\"\":\"l:2254034315077429\",\"created_time\":\"2025-11-27T13:24:50.000Z\",\"ad_id\":\"ag:120236089633530521\",\"ad_name\":\"Tailored leads campaign 02\\/01\\/2025 Ad \\u2013 Copy\",\"adset_id\":\"as:120236089633540521\",\"adset_name\":\"sushma ads Ad Set \\u2013 Copy\",\"campaign_id\":\"c:120236089633550521\",\"campaign_name\":\"Neha ads Campaign\",\"form_id\":\"f:1338570067270657\",\"form_name\":\"neha\",\"is_organic\":\"false\",\"platform\":\"ig\",\"\\u0915\\u094d\\u092f\\u093e_\\u0906\\u092a_\\u092e\\u0947\\u0921\\u093f\\u0915\\u0932_\\u0915\\u094d\\u0937\\u0947\\u0924\\u094d\\u0930_\\u092e\\u0947\\u0902_\\u0905\\u092a\\u0928\\u093e_\\u092d\\u0935\\u093f\\u0937\\u094d\\u092f_\\u092c\\u0928\\u093e\\u0928\\u093e_\\u091a\\u093e\\u0939\\u0924\\u0947_\\u0939\\u0948_?\":\"\\u0939\\u093e\\u0901\",\"full_name\":\"Siddharth Kumar\",\"phone_number\":\"+919570078996\",\"city\":\"Madhepura\",\"lead_status\":\"CREATED\",\"synced\":\"\",\"external_id\":\"l:2254034315077429\",\"sheet_source\":\"Sheet1\",\"hindi_question\":\"\\u0939\\u093e\\u0901\"}', 75, '[\"Very fresh lead\"]', 'high', '2025-11-27 13:25:22', '2025-11-27 13:25:24'),
(4, 'l:1960376681213015', '2025-11-27 13:53:55', 'ag:120236089633530521', 'Tailored leads campaign 02/01/2025 Ad – Copy', 'as:120236089633540521', 'sushma ads Ad Set – Copy', 'c:120236089633550521', 'Neha ads Campaign', 'f:1338570067270657', 'neha', 0, 'ig', 'हाँ', 'Ishwar Ahirwar', '+919301809713', 'Mandla', 'CREATED', 'Sheet1', '{\"\":\"l:1960376681213015\",\"created_time\":\"2025-11-27T13:53:55.000Z\",\"ad_id\":\"ag:120236089633530521\",\"ad_name\":\"Tailored leads campaign 02\\/01\\/2025 Ad \\u2013 Copy\",\"adset_id\":\"as:120236089633540521\",\"adset_name\":\"sushma ads Ad Set \\u2013 Copy\",\"campaign_id\":\"c:120236089633550521\",\"campaign_name\":\"Neha ads Campaign\",\"form_id\":\"f:1338570067270657\",\"form_name\":\"neha\",\"is_organic\":\"false\",\"platform\":\"ig\",\"\\u0915\\u094d\\u092f\\u093e_\\u0906\\u092a_\\u092e\\u0947\\u0921\\u093f\\u0915\\u0932_\\u0915\\u094d\\u0937\\u0947\\u0924\\u094d\\u0930_\\u092e\\u0947\\u0902_\\u0905\\u092a\\u0928\\u093e_\\u092d\\u0935\\u093f\\u0937\\u094d\\u092f_\\u092c\\u0928\\u093e\\u0928\\u093e_\\u091a\\u093e\\u0939\\u0924\\u0947_\\u0939\\u0948_?\":\"\\u0939\\u093e\\u0901\",\"full_name\":\"Ishwar Ahirwar\",\"phone_number\":\"+919301809713\",\"city\":\"Mandla\",\"lead_status\":\"CREATED\",\"synced\":\"\",\"external_id\":\"l:1960376681213015\",\"sheet_source\":\"Sheet1\",\"hindi_question\":\"\\u0939\\u093e\\u0901\"}', 75, '[\"Very fresh lead\"]', 'high', '2025-11-27 13:54:21', '2025-11-27 13:54:23'),
(5, 'wa:919138386908:1764251865', '2025-11-27 13:57:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'whatsapp', NULL, 'Kumar Development', '919138386908', NULL, NULL, NULL, NULL, 0, NULL, 'medium', '2025-11-27 13:57:45', '2025-11-27 13:57:45'),
(6, 'l:1217638666896638', '2025-11-27 14:06:42', 'ag:120225411063300521', 'Tailored leads campaign 02/01/2025 Ad', 'as:120225411063310521', 'Mayara ads Ad Set', 'c:120225411063320521', 'Mayara ads Campaign', 'f:836971255412599', 'Mayara', 0, 'fb', 'हाँ', 'Vaishali Ghichariya', '+918530278519', 'katol maharashtra', 'CREATED', 'Sheet1', '{\"id\":\"l:1217638666896638\",\"created_time\":\"2025-11-27T14:06:42.000Z\",\"ad_id\":\"ag:120225411063300521\",\"ad_name\":\"Tailored leads campaign 02\\/01\\/2025 Ad\",\"adset_id\":\"as:120225411063310521\",\"adset_name\":\"Mayara ads Ad Set\",\"campaign_id\":\"c:120225411063320521\",\"campaign_name\":\"Mayara ads Campaign\",\"form_id\":\"f:836971255412599\",\"form_name\":\"Mayara\",\"is_organic\":\"false\",\"platform\":\"fb\",\"\\u0915\\u094d\\u092f\\u093e_\\u0906\\u092a_\\u092e\\u0947\\u0921\\u093f\\u0915\\u0932_\\u0915\\u094d\\u0937\\u0947\\u0924\\u094d\\u0930_\\u092e\\u0947\\u0902_\\u0905\\u092a\\u0928\\u093e_\\u092d\\u0935\\u093f\\u0937\\u094d\\u092f_\\u092c\\u0928\\u093e\\u0928\\u093e_\\u091a\\u093e\\u0939\\u0924\\u0947_\\u0939\\u0948_?\":\"\\u0939\\u093e\\u0901\",\"full_name\":\"Vaishali Ghichariya\",\"phone_number\":\"+918530278519\",\"city\":\"katol maharashtra\",\"lead_status\":\"CREATED\",\"synced\":\"\",\"external_id\":\"l:1217638666896638\",\"sheet_source\":\"Sheet1\",\"hindi_question\":\"\\u0939\\u093e\\u0901\"}', 75, '[\"Very fresh lead\"]', 'high', '2025-11-27 14:07:37', '2025-11-27 14:07:39'),
(7, 'l:844179448309457', '2025-11-27 15:08:58', 'ag:120225411063300521', 'Tailored leads campaign 02/01/2025 Ad', 'as:120225411063310521', 'Mayara ads Ad Set', 'c:120225411063320521', 'Mayara ads Campaign', 'f:836971255412599', 'Mayara', 0, 'ig', 'हाँ', 'Sanjeev Kumar', '+916206407450', 'Bagusa', 'CREATED', 'Sheet1', '{\"id\":\"l:844179448309457\",\"created_time\":\"2025-11-27T15:08:58.000Z\",\"ad_id\":\"ag:120225411063300521\",\"ad_name\":\"Tailored leads campaign 02\\/01\\/2025 Ad\",\"adset_id\":\"as:120225411063310521\",\"adset_name\":\"Mayara ads Ad Set\",\"campaign_id\":\"c:120225411063320521\",\"campaign_name\":\"Mayara ads Campaign\",\"form_id\":\"f:836971255412599\",\"form_name\":\"Mayara\",\"is_organic\":\"false\",\"platform\":\"ig\",\"\\u0915\\u094d\\u092f\\u093e_\\u0906\\u092a_\\u092e\\u0947\\u0921\\u093f\\u0915\\u0932_\\u0915\\u094d\\u0937\\u0947\\u0924\\u094d\\u0930_\\u092e\\u0947\\u0902_\\u0905\\u092a\\u0928\\u093e_\\u092d\\u0935\\u093f\\u0937\\u094d\\u092f_\\u092c\\u0928\\u093e\\u0928\\u093e_\\u091a\\u093e\\u0939\\u0924\\u0947_\\u0939\\u0948_?\":\"\\u0939\\u093e\\u0901\",\"full_name\":\"Sanjeev Kumar\",\"phone_number\":\"+916206407450\",\"city\":\"Bagusa\",\"lead_status\":\"CREATED\",\"synced\":\"\",\"external_id\":\"l:844179448309457\",\"sheet_source\":\"Sheet1\",\"hindi_question\":\"\\u0939\\u093e\\u0901\"}', 75, '[\"Very fresh lead\"]', 'high', '2025-11-27 15:09:37', '2025-11-27 15:09:39'),
(8, 'wa:916395755571:1764256992', '2025-11-27 15:23:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'whatsapp', NULL, 'Shantnu Sharma', '916395755571', NULL, NULL, NULL, NULL, 0, NULL, 'medium', '2025-11-27 15:23:12', '2025-11-27 15:23:12');

-- --------------------------------------------------------

--
-- Table structure for table `lead_assignments`
--

CREATE TABLE `lead_assignments` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `status` enum('assigned','contacted','qualified','not_qualified','call_not_picked','payment_completed') DEFAULT 'assigned',
  `assigned_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lead_assignments`
--

INSERT INTO `lead_assignments` (`id`, `lead_id`, `agent_id`, `assigned_by`, `status`, `assigned_at`) VALUES
(1, 1, 2, NULL, 'assigned', '2025-11-27 12:13:37'),
(2, 2, 2, NULL, 'assigned', '2025-11-27 13:11:36'),
(3, 3, 2, NULL, 'assigned', '2025-11-27 13:25:22'),
(4, 4, 2, NULL, 'assigned', '2025-11-27 13:54:21'),
(5, 5, 2, NULL, 'assigned', '2025-11-27 13:57:45'),
(6, 6, 2, NULL, 'assigned', '2025-11-27 14:07:37'),
(7, 7, 2, NULL, 'assigned', '2025-11-27 15:09:37'),
(8, 8, 2, NULL, 'assigned', '2025-11-27 15:23:12');

--
-- Triggers `lead_assignments`
--
DELIMITER $$
CREATE TRIGGER `after_lead_assignment_insert` AFTER INSERT ON `lead_assignments` FOR EACH ROW BEGIN
    DECLARE lead_name VARCHAR(255) DEFAULT NULL;
    DECLARE lead_phone VARCHAR(50) DEFAULT NULL;
    DECLARE lead_exists INT DEFAULT 0;

    -- Check if lead exists
    SELECT COUNT(*) INTO lead_exists
    FROM leads
    WHERE id = NEW.lead_id;

    -- Only proceed if lead exists
    IF lead_exists > 0 THEN
        -- Get lead details
        SELECT full_name, phone_number
        INTO lead_name, lead_phone
        FROM leads
        WHERE id = NEW.lead_id
        LIMIT 1;

        -- Insert into queue for background processing
        INSERT INTO notification_queue (
            agent_id,
            lead_id,
            lead_name,
            lead_phone,
            notification_type,
            status,
            created_at
        ) VALUES (
            NEW.agent_id,
            NEW.lead_id,
            COALESCE(lead_name, 'Unknown'),
            COALESCE(lead_phone, 'N/A'),
            'lead_assigned',
            'pending',
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `lead_name` varchar(255) DEFAULT NULL,
  `lead_phone` varchar(50) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT 'lead_assigned',
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_queue`
--

INSERT INTO `notification_queue` (`id`, `agent_id`, `lead_id`, `lead_name`, `lead_phone`, `notification_type`, `status`, `created_at`, `sent_at`) VALUES
(1, 2, 1, 'Raj Kumar', '+919459462009', 'lead_assigned', 'pending', '2025-11-27 12:13:37', NULL),
(2, 2, 2, 'Malti  mahant', '+918103186281', 'lead_assigned', 'pending', '2025-11-27 13:11:36', NULL),
(3, 2, 3, 'Siddharth Kumar', '+919570078996', 'lead_assigned', 'pending', '2025-11-27 13:25:22', NULL),
(4, 2, 4, 'Ishwar Ahirwar', '+919301809713', 'lead_assigned', 'pending', '2025-11-27 13:54:21', NULL),
(5, 2, 5, 'Kumar Development', '919138386908', 'lead_assigned', 'pending', '2025-11-27 13:57:45', NULL),
(6, 2, 6, 'Vaishali Ghichariya', '+918530278519', 'lead_assigned', 'pending', '2025-11-27 14:07:37', NULL),
(7, 2, 7, 'Sanjeev Kumar', '+916206407450', 'lead_assigned', 'pending', '2025-11-27 15:09:37', NULL),
(8, 2, 8, 'Shantnu Sharma', '916395755571', 'lead_assigned', 'pending', '2025-11-27 15:23:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `reminder_time` datetime NOT NULL,
  `reminder_note` text DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `notification_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'auto_assign_leads', '1', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(2, 'lead_scoring_enabled', '1', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(3, 'default_lead_priority', 'medium', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(4, 'max_leads_per_agent', '50', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(5, 'response_time_threshold', '24', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(6, 'qualification_threshold', '70', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(7, 'notification_enabled', '1', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(8, 'email_notifications', '0', '2025-11-27 07:40:21', '2025-11-27 16:01:39'),
(9, 'sms_notifications', '0', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(10, 'whatsapp_enabled', '1', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(11, 'reminder_enabled', '1', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(12, 'meta_graph_version', 'v23.0', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(13, 'meta_verify_token', 'proschool360', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(14, 'meta_app_secret', '9b554e03d11c0bdc9e086681b92dbb7e', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(15, 'whatsapp_token', 'EAAdPsmNwMGYBQD8HqjfxKXHZBg665k85zQWs0C2pxlNSy5ssVOryLOTLk2PgnHty68R79OR0y3RbmOLN0vkRpF0Bd14fBASNd2VK3J5c9ZC6cTZAj197zugZCrbS82eAGQkZBxK6SXF7MUCLNpBO7L0MK6vtbhQ7iUoeQQqo7syqUzGtrEsDgOWSc1iqc1wZDZD', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(16, 'whatsapp_phone_number_id', '715808554957137', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(17, 'pusher_app_id', '2081144', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(18, 'pusher_key', 'f49cb5d7d6fee892229b', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(19, 'pusher_secret', '324ff997b2c454662b47', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(20, 'pusher_cluster', 'ap2', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(21, 'system_timezone', 'Asia/Kolkata', '2025-11-27 07:40:22', '2025-11-27 16:01:39'),
(53, 'admin_whatsapp_alerts', '0', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(54, 'admin_alert_numbers', '', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(56, 'score_very_fresh_bonus', '25', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(57, 'score_fresh_bonus', '15', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(58, 'score_old_penalty', '10', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(59, 'score_city_bonus', '10', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(65, 'whatsapp_business_account_id', '1508918753758784', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(70, 'r2_access_key', '', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(71, 'r2_secret_key', '', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(72, 'r2_account_id', '', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(73, 'r2_bucket', '', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(74, 'r2_endpoint', '', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(75, 'r2_region', 'auto', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(76, 'r2_custom_domain', '', '2025-11-27 16:00:30', '2025-11-27 16:01:39'),
(99, 'cache_last_cleared', '2025-11-28 15:05:19', '2025-11-28 09:35:19', '2025-11-28 09:35:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','agent') NOT NULL DEFAULT 'agent',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `onesignal_player_id` varchar(255) DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `active`, `created_at`, `updated_at`, `onesignal_player_id`, `is_online`) VALUES
(1, 'Admin User', 'admin@crm.com', NULL, '$2y$10$M1kgEmyLoA8AKLfz.WBvBepLSsP8rC9yAZKX8rWybvhEZrOhMQ9N.', 'admin', 1, '2025-11-26 15:44:56', '2025-11-26 15:44:56', NULL, 1),
(2, 'kusum', 'lohankusum@gmail.com', '8222082076', '$2y$10$bp7UyvAdWFBA9o55ZsFGfezqOPVChG/Gkd0dL4cOD6STH6G5DlIpO', 'agent', 1, '2025-11-27 07:25:27', '2025-11-27 07:25:27', '76d65286-0211-4217-9f82-c41424492952', 1);

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_assignment_history`
--

CREATE TABLE `whatsapp_assignment_history` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `previous_agent_id` int(11) DEFAULT NULL,
  `new_agent_id` int(11) NOT NULL,
  `changed_by_user_id` int(11) NOT NULL,
  `changed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_campaigns`
--

CREATE TABLE `whatsapp_campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `language_code` varchar(20) DEFAULT 'en_US',
  `filters_json` longtext DEFAULT NULL,
  `status` enum('draft','scheduled','running','completed','failed') DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `warmup_enabled` tinyint(1) DEFAULT 0,
  `daily_quota` int(11) DEFAULT NULL,
  `current_quota` int(11) DEFAULT NULL,
  `quality_score_snapshot` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_campaign_recipients`
--

CREATE TABLE `whatsapp_campaign_recipients` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `status` enum('queued','sent','delivered','read','failed') DEFAULT 'queued',
  `wa_message_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `attempts` int(11) DEFAULT 0,
  `last_attempt_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_conversations`
--

CREATE TABLE `whatsapp_conversations` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `assigned_agent_id` int(11) DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `contact_name` varchar(150) DEFAULT NULL,
  `last_incoming_at` timestamp NULL DEFAULT NULL,
  `unread_count` int(11) DEFAULT 0,
  `first_message_at` timestamp NULL DEFAULT NULL,
  `phone_number_id` varchar(50) DEFAULT NULL,
  `quality_score` int(11) DEFAULT NULL,
  `meta_contact_id` varchar(100) DEFAULT NULL,
  `intervened` tinyint(1) DEFAULT 0,
  `intervened_by` int(11) DEFAULT NULL,
  `intervened_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `whatsapp_conversations`
--

INSERT INTO `whatsapp_conversations` (`id`, `phone_number`, `lead_id`, `assigned_agent_id`, `status`, `last_message_at`, `created_at`, `updated_at`, `contact_name`, `last_incoming_at`, `unread_count`, `first_message_at`, `phone_number_id`, `quality_score`, `meta_contact_id`, `intervened`, `intervened_by`, `intervened_at`) VALUES
(2, '919138386908', 5, 2, 'open', '2025-11-27 16:24:55', '2025-11-27 13:57:45', '2025-11-27 16:24:55', 'Kumar Development', '2025-11-27 14:52:10', 3, '2025-11-27 13:57:45', '715808554957137', NULL, '919138386908', 1, 1, '2025-11-27 16:24:46'),
(3, '916395755571', 8, 2, 'open', '2025-11-27 15:23:34', '2025-11-27 15:23:12', '2025-11-27 15:23:34', 'Shantnu Sharma', '2025-11-27 15:23:34', 2, '2025-11-27 15:23:12', '715808554957137', NULL, '916395755571', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_conversation_tags`
--

CREATE TABLE `whatsapp_conversation_tags` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `tag` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `whatsapp_conversation_tags`
--

INSERT INTO `whatsapp_conversation_tags` (`id`, `conversation_id`, `tag`, `created_at`) VALUES
(1, 2, 'Newone', '2025-11-27 14:03:21');

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_flows`
--

CREATE TABLE `whatsapp_flows` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `definition_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_messages`
--

CREATE TABLE `whatsapp_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `direction` enum('incoming','outgoing') NOT NULL,
  `wa_message_id` varchar(100) DEFAULT NULL,
  `type` enum('text','image','video','audio','document','sticker','location','interactive','template','unknown') DEFAULT 'text',
  `body` longtext DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `sender_phone` varchar(20) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `status` enum('queued','sent','delivered','read','failed') DEFAULT 'queued',
  `timestamp` timestamp NULL DEFAULT current_timestamp(),
  `meta_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `whatsapp_messages`
--

INSERT INTO `whatsapp_messages` (`id`, `conversation_id`, `direction`, `wa_message_id`, `type`, `body`, `media_url`, `template_name`, `sender_phone`, `recipient_phone`, `status`, `timestamp`, `meta_json`) VALUES
(6, 2, 'incoming', 'wamid.HBgMOTE5MTM4Mzg2OTA4FQIAEhgUMkFGOUVGNzg0RjJEMzlEMjc1OTYA', 'text', 'Hlo', NULL, NULL, '919138386908', NULL, 'delivered', '2025-11-27 13:57:45', '{\"from\":\"919138386908\",\"id\":\"wamid.HBgMOTE5MTM4Mzg2OTA4FQIAEhgUMkFGOUVGNzg0RjJEMzlEMjc1OTYA\",\"timestamp\":\"1764251863\",\"text\":{\"body\":\"Hlo\"},\"type\":\"text\"}'),
(7, 2, 'incoming', 'wamid.HBgMOTE5MTM4Mzg2OTA4FQIAEhgUMkE1RUY3QTI2NTIzRjE5NUNFQUEA', 'text', 'Hlo', NULL, NULL, '919138386908', NULL, 'delivered', '2025-11-27 13:57:53', '{\"from\":\"919138386908\",\"id\":\"wamid.HBgMOTE5MTM4Mzg2OTA4FQIAEhgUMkE1RUY3QTI2NTIzRjE5NUNFQUEA\",\"timestamp\":\"1764251872\",\"text\":{\"body\":\"Hlo\"},\"type\":\"text\"}'),
(8, 2, 'outgoing', 'wamid.HBgMOTE5MTM4Mzg2OTA4FQIAERgSOTVCQTAzRUU4QjVFQzhGNDg2AA==', 'text', '🤣', NULL, NULL, NULL, '919138386908', 'read', '2025-11-27 14:00:52', '{\"messaging_product\":\"whatsapp\",\"contacts\":[{\"input\":\"919138386908\",\"wa_id\":\"919138386908\"}],\"messages\":[{\"id\":\"wamid.HBgMOTE5MTM4Mzg2OTA4FQIAERgSOTVCQTAzRUU4QjVFQzhGNDg2AA==\"}]}'),
(9, 2, 'incoming', 'wamid.HBgMOTE5MTM4Mzg2OTA4FQIAEhgUMkFFNzAyOUZBMjM5N0U2MUY0MTEA', 'image', NULL, NULL, NULL, '919138386908', NULL, 'delivered', '2025-11-27 14:52:10', '{\"from\":\"919138386908\",\"id\":\"wamid.HBgMOTE5MTM4Mzg2OTA4FQIAEhgUMkFFNzAyOUZBMjM5N0U2MUY0MTEA\",\"timestamp\":\"1764255127\",\"type\":\"image\",\"image\":{\"mime_type\":\"image\\/jpeg\",\"sha256\":\"LZ+bJc6CyHCp61x8qWC6ljHbHS\\/3cs+AKEEOyDIR4Q0=\",\"id\":\"809969708706574\"}}'),
(10, 3, 'incoming', 'wamid.HBgMOTE2Mzk1NzU1NTcxFQIAEhgUM0EwRTY3MEIxNUNGNkYzOTRCQkEA', 'text', 'Hllo sexy', NULL, NULL, '916395755571', NULL, 'delivered', '2025-11-27 15:23:12', '{\"from\":\"916395755571\",\"id\":\"wamid.HBgMOTE2Mzk1NzU1NTcxFQIAEhgUM0EwRTY3MEIxNUNGNkYzOTRCQkEA\",\"timestamp\":\"1764256989\",\"text\":{\"body\":\"Hllo sexy\"},\"type\":\"text\"}'),
(11, 3, 'outgoing', 'wamid.HBgMOTE2Mzk1NzU1NTcxFQIAERgSRDkzMzFBMTc4MEM4NkZBOUQ5AA==', 'text', 'kjhkjh', NULL, NULL, NULL, '916395755571', 'read', '2025-11-27 15:23:25', '{\"messaging_product\":\"whatsapp\",\"contacts\":[{\"input\":\"916395755571\",\"wa_id\":\"916395755571\"}],\"messages\":[{\"id\":\"wamid.HBgMOTE2Mzk1NzU1NTcxFQIAERgSRDkzMzFBMTc4MEM4NkZBOUQ5AA==\"}]}'),
(12, 3, 'incoming', 'wamid.HBgMOTE2Mzk1NzU1NTcxFQIAEhgUM0FFNjU2QkMxQjIyN0IyMUFBMDUA', 'text', 'Ok', NULL, NULL, '916395755571', NULL, 'delivered', '2025-11-27 15:23:34', '{\"from\":\"916395755571\",\"id\":\"wamid.HBgMOTE2Mzk1NzU1NTcxFQIAEhgUM0FFNjU2QkMxQjIyN0IyMUFBMDUA\",\"timestamp\":\"1764257012\",\"text\":{\"body\":\"Ok\"},\"type\":\"text\"}'),
(13, 2, 'outgoing', 'wamid.HBgMOTE5MTM4Mzg2OTA4FQIAERgSNjQxRDAyNUVDQzJFQjFDQzU5AA==', 'text', 'hlo', NULL, NULL, NULL, '919138386908', 'delivered', '2025-11-27 16:24:55', '{\"messaging_product\":\"whatsapp\",\"contacts\":[{\"input\":\"919138386908\",\"wa_id\":\"919138386908\"}],\"messages\":[{\"id\":\"wamid.HBgMOTE5MTM4Mzg2OTA4FQIAERgSNjQxRDAyNUVDQzJFQjFDQzU5AA==\"}]}');

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_notes`
--

CREATE TABLE `whatsapp_notes` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `author_user_id` int(11) NOT NULL,
  `note_text` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_private` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_templates`
--

CREATE TABLE `whatsapp_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `media_type` enum('none','image','video','pdf') DEFAULT 'none',
  `media_url` varchar(500) DEFAULT NULL,
  `buttons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`buttons`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` enum('Marketing','Utility','Authentication') DEFAULT 'Utility',
  `language` varchar(20) DEFAULT 'en_US',
  `header_text` text DEFAULT NULL,
  `header_media_type` enum('none','image','video','pdf') DEFAULT 'none',
  `header_media_url` varchar(500) DEFAULT NULL,
  `footer_text` text DEFAULT NULL,
  `placeholders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`placeholders`)),
  `status` enum('approved','pending','rejected') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ad_budgets`
--
ALTER TABLE `ad_budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_campaign_date` (`campaign_id`,`date`),
  ADD KEY `idx_campaign_id` (`campaign_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device_token` (`device_token`),
  ADD KEY `idx_pusher_device_id` (`pusher_device_id`),
  ADD KEY `idx_last_login` (`last_login`);

--
-- Indexes for table `agent_responses`
--
ALTER TABLE `agent_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lead_id` (`lead_id`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_response_status` (`response_status`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `flow_edges`
--
ALTER TABLE `flow_edges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flow_id` (`flow_id`);

--
-- Indexes for table `flow_nodes`
--
ALTER TABLE `flow_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flow_id` (`flow_id`);

--
-- Indexes for table `flow_runs`
--
ALTER TABLE `flow_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_flow_id` (`flow_id`);

--
-- Indexes for table `flow_variables`
--
ALTER TABLE `flow_variables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run_id` (`run_id`),
  ADD KEY `idx_var_key` (`var_key`);

--
-- Indexes for table `followup_reminders`
--
ALTER TABLE `followup_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_lead_id` (`lead_id`),
  ADD KEY `idx_reminder_time` (`reminder_time`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `external_id` (`external_id`),
  ADD KEY `idx_external_id` (`external_id`),
  ADD KEY `idx_campaign_id` (`campaign_id`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_created_time` (`created_time`),
  ADD KEY `idx_sheet_source` (`sheet_source`);

--
-- Indexes for table `lead_assignments`
--
ALTER TABLE `lead_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_lead_id` (`lead_id`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_agent_id` (`agent_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `idx_reminder_time` (`reminder_time`,`is_completed`,`notification_sent`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_users_onesignal_player_id` (`onesignal_player_id`);

--
-- Indexes for table `whatsapp_assignment_history`
--
ALTER TABLE `whatsapp_assignment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`);

--
-- Indexes for table `whatsapp_campaigns`
--
ALTER TABLE `whatsapp_campaigns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `whatsapp_campaign_recipients`
--
ALTER TABLE `whatsapp_campaign_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign_id` (`campaign_id`);

--
-- Indexes for table `whatsapp_conversations`
--
ALTER TABLE `whatsapp_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_phone_number` (`phone_number`),
  ADD KEY `idx_lead_id` (`lead_id`),
  ADD KEY `idx_assigned_agent_id` (`assigned_agent_id`),
  ADD KEY `idx_last_incoming_at` (`last_incoming_at`),
  ADD KEY `idx_phone_number_id` (`phone_number_id`),
  ADD KEY `idx_phone_last` (`phone_number`,`last_message_at`),
  ADD KEY `idx_intervened_by` (`intervened_by`);

--
-- Indexes for table `whatsapp_conversation_tags`
--
ALTER TABLE `whatsapp_conversation_tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`);

--
-- Indexes for table `whatsapp_flows`
--
ALTER TABLE `whatsapp_flows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_direction` (`direction`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_conv_timestamp` (`conversation_id`,`timestamp`);

--
-- Indexes for table `whatsapp_notes`
--
ALTER TABLE `whatsapp_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `wa_notes_user_fk` (`author_user_id`);

--
-- Indexes for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ad_budgets`
--
ALTER TABLE `ad_budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_responses`
--
ALTER TABLE `agent_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flow_edges`
--
ALTER TABLE `flow_edges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flow_nodes`
--
ALTER TABLE `flow_nodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flow_runs`
--
ALTER TABLE `flow_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flow_variables`
--
ALTER TABLE `flow_variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `followup_reminders`
--
ALTER TABLE `followup_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `lead_assignments`
--
ALTER TABLE `lead_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `whatsapp_assignment_history`
--
ALTER TABLE `whatsapp_assignment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_campaigns`
--
ALTER TABLE `whatsapp_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_campaign_recipients`
--
ALTER TABLE `whatsapp_campaign_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_conversations`
--
ALTER TABLE `whatsapp_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `whatsapp_conversation_tags`
--
ALTER TABLE `whatsapp_conversation_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `whatsapp_flows`
--
ALTER TABLE `whatsapp_flows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `whatsapp_notes`
--
ALTER TABLE `whatsapp_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agents`
--
ALTER TABLE `agents`
  ADD CONSTRAINT `agents_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_responses`
--
ALTER TABLE `agent_responses`
  ADD CONSTRAINT `agent_responses_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_responses_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `followup_reminders`
--
ALTER TABLE `followup_reminders`
  ADD CONSTRAINT `followup_reminders_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `followup_reminders_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lead_assignments`
--
ALTER TABLE `lead_assignments`
  ADD CONSTRAINT `lead_assignments_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_assignments_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `reminders_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`);

--
-- Constraints for table `whatsapp_assignment_history`
--
ALTER TABLE `whatsapp_assignment_history`
  ADD CONSTRAINT `wa_assign_conv_fk` FOREIGN KEY (`conversation_id`) REFERENCES `whatsapp_conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_campaign_recipients`
--
ALTER TABLE `whatsapp_campaign_recipients`
  ADD CONSTRAINT `wa_campaign_fk` FOREIGN KEY (`campaign_id`) REFERENCES `whatsapp_campaigns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_conversations`
--
ALTER TABLE `whatsapp_conversations`
  ADD CONSTRAINT `wa_conv_agent_fk` FOREIGN KEY (`assigned_agent_id`) REFERENCES `agents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `wa_conv_lead_fk` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `whatsapp_conversation_tags`
--
ALTER TABLE `whatsapp_conversation_tags`
  ADD CONSTRAINT `wa_tags_conv_fk` FOREIGN KEY (`conversation_id`) REFERENCES `whatsapp_conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  ADD CONSTRAINT `wa_msg_conv_fk` FOREIGN KEY (`conversation_id`) REFERENCES `whatsapp_conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_notes`
--
ALTER TABLE `whatsapp_notes`
  ADD CONSTRAINT `wa_notes_conv_fk` FOREIGN KEY (`conversation_id`) REFERENCES `whatsapp_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wa_notes_user_fk` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
