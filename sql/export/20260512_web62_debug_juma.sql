-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 12. Mai 2026 um 09:23
-- Server-Version: 10.11.16-MariaDB
-- PHP-Version: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `web62_debug_juma`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(100) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `competitions`
--

CREATE TABLE `competitions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('active','finished','archived') NOT NULL DEFAULT 'active',
  `hash` char(32) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `competitions`
--

INSERT INTO `competitions` (`id`, `name`, `location`, `date`, `status`, `hash`, `lat`, `lng`, `created_at`, `updated_at`) VALUES
(1, 'JuMa 2026', 'Krummennaab', '2026-05-31', 'active', 'e70df3b6bc0aa81a7601318ec76a165c', NULL, NULL, '2026-05-10 22:32:42', '2026-05-10 22:32:42');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `feuerwehren`
--

CREATE TABLE `feuerwehren` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `bereich` varchar(60) NOT NULL,
  `kbi_bereich` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `feuerwehren`
--

INSERT INTO `feuerwehren` (`id`, `name`, `bereich`, `kbi_bereich`) VALUES
(1, 'FF Ahornberg', 'TIR Land 2/1', 'TIR Land 2'),
(2, 'FF Altglashütte', 'TIR Land 4/3', 'TIR Land 4'),
(3, 'FF Atzmannsberg - Köglitz', 'TIR Land 2/3', 'TIR Land 2'),
(4, 'FF Bad Neualbenreuth', 'TIR Land 4/1', 'TIR Land 4'),
(5, 'FF Bärnau', 'TIR Land 4/3', 'TIR Land 4'),
(6, 'FF Beidl', 'TIR Land 4/3', 'TIR Land 4'),
(7, 'FF Brand', 'TIR Land 2/2', 'TIR Land 2'),
(8, 'FF Dippersreuth', 'TIR Land 4/2', 'TIR Land 4'),
(9, 'FF Ebnath', 'TIR Land 2/2', 'TIR Land 2'),
(10, 'FF Ellenfeld', 'TIR Land 4/3', 'TIR Land 4'),
(11, 'FF Erbendorf', 'TIR Land 3/4', 'TIR Land 3'),
(12, 'FF Falkenberg', 'TIR Land 3/1', 'TIR Land 3'),
(13, 'FF Fortschau - Kuchenreuth', 'TIR Land 2/1', 'TIR Land 2'),
(14, 'FF Friedenfels', 'TIR Land 3/1', 'TIR Land 3'),
(15, 'FF Fuchsmühl', 'TIR Land 3/1', 'TIR Land 3'),
(16, 'FF Fuhrmannsreuth', 'TIR Land 2/2', 'TIR Land 2'),
(17, 'FF Griesbach', 'TIR Land 4/2', 'TIR Land 4'),
(18, 'FF Groschlattengrün', 'TIR Land 3/1', 'TIR Land 3'),
(19, 'FF Großensees', 'TIR Land 3/3', 'TIR Land 3'),
(20, 'FF Großensterz', 'TIR Land 3/3', 'TIR Land 3'),
(21, 'FF Großkonreuth', 'TIR Land 4/2', 'TIR Land 4'),
(22, 'FF Grötschenreuth', 'TIR Land 3/4', 'TIR Land 3'),
(23, 'FF Gumpen', 'TIR Land 3/1', 'TIR Land 3'),
(24, 'FF Guttenberg', 'TIR Land 2/3', 'TIR Land 2'),
(25, 'FF Hardeck', 'TIR Land 4/1', 'TIR Land 4'),
(26, 'FF Helmbrechts', 'TIR Land 3/2', 'TIR Land 3'),
(27, 'FF Hermannsreuth', 'TIR Land 4/3', 'TIR Land 4'),
(28, 'FF Höflas bei Kemnath', 'TIR Land 2/1', 'TIR Land 2'),
(29, 'FF Höflas bei Konnersreuth', 'TIR Land 3/3', 'TIR Land 3'),
(30, 'FF Hohenhard', 'TIR Land 3/2', 'TIR Land 3'),
(31, 'FF Hohenthan', 'TIR Land 4/3', 'TIR Land 4'),
(32, 'FF Immenreuth', 'TIR Land 2/1', 'TIR Land 2'),
(33, 'FF Kastl b. Kemnath', 'TIR Land 2/3', 'TIR Land 2'),
(34, 'FF Kemnath', 'TIR Land 2/1', 'TIR Land 2'),
(35, 'FF Kondrau', 'TIR Land 4/1', 'TIR Land 4'),
(36, 'FF Königshütte', 'TIR Land 3/3', 'TIR Land 3'),
(37, 'FF Konnersreuth', 'TIR Land 3/3', 'TIR Land 3'),
(38, 'FF Kötzersdorf', 'TIR Land 2/1', 'TIR Land 2'),
(39, 'FF Krummennaab', 'TIR Land 3/4', 'TIR Land 3'),
(40, 'FF Kulmain', 'TIR Land 2/1', 'TIR Land 2'),
(41, 'FF Lenau', 'TIR Land 2/1', 'TIR Land 2'),
(42, 'FF Lengenfeld bei Tirschenreuth', 'TIR Land 4/2', 'TIR Land 4'),
(43, 'FF Lengenfeld bei Waldershof', 'TIR Land 3/2', 'TIR Land 3'),
(44, 'FF Leonberg', 'TIR Land 3/3', 'TIR Land 3'),
(45, 'FF Liebenstein', 'TIR Land 4/3', 'TIR Land 4'),
(46, 'FF Lochau', 'TIR Land 2/2', 'TIR Land 2'),
(47, 'FF Löschwitz - Kaibitz', 'TIR Land 2/3', 'TIR Land 2'),
(48, 'FF Mähring', 'TIR Land 4/2', 'TIR Land 4'),
(49, 'FF Matzersreuth', 'TIR Land 4/2', 'TIR Land 4'),
(50, 'FF Mitterteich', 'TIR Land 3/3', 'TIR Land 3'),
(51, 'FF Münchenreuth', 'TIR Land 4/1', 'TIR Land 4'),
(52, 'FF Naab', 'TIR Land 4/3', 'TIR Land 4'),
(53, 'FF Neudorf - Rosenbühl', 'TIR Land 3/3', 'TIR Land 3'),
(54, 'FF Neusorg', 'TIR Land 2/2', 'TIR Land 2'),
(55, 'FF Oberwappenöst', 'TIR Land 2/1', 'TIR Land 2'),
(56, 'FF Ottengrün', 'TIR Land 4/1', 'TIR Land 4'),
(57, 'FF Pechbrunn', 'TIR Land 3/1', 'TIR Land 3'),
(58, 'FF Pechofen', 'TIR Land 3/3', 'TIR Land 3'),
(59, 'FF Pfaffenreuth', 'TIR Land 4/1', 'TIR Land 4'),
(60, 'FF Pilgramsreuth', 'TIR Land 2/2', 'TIR Land 2'),
(61, 'FF Pilmersreuth an der Straße', 'TIR Land 4/2', 'TIR Land 4'),
(62, 'FF Pleußen', 'TIR Land 3/3', 'TIR Land 3'),
(63, 'FF Plößberg', 'TIR Land 4/3', 'TIR Land 4'),
(64, 'FF Poppenreuth bei Tirschenreuth', 'TIR Land 4/2', 'TIR Land 4'),
(65, 'FF Poppenreuth bei Waldershof', 'TIR Land 3/2', 'TIR Land 3'),
(66, 'FF Premenreuth', 'TIR Land 3/4', 'TIR Land 3'),
(67, 'FF Pullenreuth', 'TIR Land 2/2', 'TIR Land 2'),
(68, 'FF Punreuth', 'TIR Land 2/1', 'TIR Land 2'),
(69, 'FF Querenbach', 'TIR Land 4/1', 'TIR Land 4'),
(70, 'FF Redenbach', 'TIR Land 4/2', 'TIR Land 4'),
(71, 'FF Reuth bei Erbendorf', 'TIR Land 3/4', 'TIR Land 3'),
(72, 'FF Reuth bei Kastl', 'TIR Land 2/3', 'TIR Land 2'),
(73, 'FF Riglasreuth', 'TIR Land 2/2', 'TIR Land 2'),
(74, 'FF Rodenzenreuth', 'TIR Land 3/2', 'TIR Land 3'),
(75, 'FF Rosall', 'TIR Land 4/2', 'TIR Land 4'),
(76, 'FF Röthenbach am Steinwald', 'TIR Land 3/4', 'TIR Land 3'),
(77, 'FF Schönficht', 'TIR Land 4/3', 'TIR Land 4'),
(78, 'FF Schönhaid - Leugas', 'TIR Land 3/1', 'TIR Land 3'),
(79, 'FF Schönkirch', 'TIR Land 4/3', 'TIR Land 4'),
(80, 'FF Schönreuth', 'TIR Land 2/3', 'TIR Land 2'),
(81, 'FF Schurbach', 'TIR Land 3/2', 'TIR Land 3'),
(82, 'FF Schwarzenbach bei Bärnau', 'TIR Land 4/3', 'TIR Land 4'),
(83, 'FF Schwarzenreuth', 'TIR Land 2/2', 'TIR Land 2'),
(84, 'FF Siegritz', 'TIR Land 3/4', 'TIR Land 3'),
(85, 'FF Thanhausen', 'TIR Land 4/3', 'TIR Land 4'),
(86, 'FF Thumsenreuth', 'TIR Land 3/4', 'TIR Land 3'),
(87, 'FF Tirschenreuth', 'TIR Land 4/2', 'TIR Land 4'),
(88, 'FF Trevesen', 'TIR Land 2/2', 'TIR Land 2'),
(89, 'FF Unterbruck', 'TIR Land 2/3', 'TIR Land 2'),
(90, 'FF Voitenthan', 'TIR Land 3/1', 'TIR Land 3'),
(91, 'FF Walbenreuth', 'TIR Land 3/2', 'TIR Land 3'),
(92, 'FF Waldeck', 'TIR Land 2/3', 'TIR Land 2'),
(93, 'FF Waldershof', 'TIR Land 3/2', 'TIR Land 3'),
(94, 'FF Waldsassen', 'TIR Land 4/1', 'TIR Land 4'),
(95, 'FF Wernersreuth', 'TIR Land 4/1', 'TIR Land 4'),
(96, 'FF Wetzldorf', 'TIR Land 3/4', 'TIR Land 3'),
(97, 'FF Wiesau', 'TIR Land 3/1', 'TIR Land 3'),
(98, 'FF Wildenau', 'TIR Land 4/3', 'TIR Land 4'),
(99, 'FF Wildenreuth', 'TIR Land 3/4', 'TIR Land 3'),
(100, 'FF Wondreb', 'TIR Land 4/2', 'TIR Land 4'),
(101, 'FF Zinst', 'TIR Land 2/1', 'TIR Land 2'),
(102, 'FF Zwergau', 'TIR Land 2/3', 'TIR Land 2'),
(103, 'WF Schott AG', 'TIR Land 3/3', 'TIR Land 3'),
(104, 'WF Siemens Kemnath', 'TIR Land 2/1', 'TIR Land 2');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `competition_id` int(11) NOT NULL,
  `registration_date` date DEFAULT NULL,
  `num` smallint(6) NOT NULL,
  `name` varchar(255) NOT NULL,
  `kreis` varchar(255) DEFAULT NULL,
  `altersgruppe` varchar(50) DEFAULT NULL,
  `startnr` varchar(20) DEFAULT NULL,
  `qr_token` char(32) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `kbm_area` varchar(100) DEFAULT NULL,
  `feuerwehr_id` int(11) DEFAULT NULL,
  `last_station_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `groups`
--

INSERT INTO `groups` (`id`, `competition_id`, `registration_date`, `num`, `name`, `kreis`, `altersgruppe`, `startnr`, `qr_token`, `active`, `kbm_area`, `feuerwehr_id`, `last_station_id`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-05-10', 1, 'JF Thumsenreuth', NULL, NULL, NULL, '2014442166df096242b87ea076456939', 1, 'TIR Land 3/4', 86, NULL, '2026-05-10 23:25:45', '2026-05-12 08:59:03');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `vorname` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `geburtsdatum` date DEFAULT NULL,
  `geschlecht` enum('m','w','d') DEFAULT NULL,
  `funktion` varchar(100) DEFAULT NULL,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `group_members`
--

INSERT INTO `group_members` (`id`, `group_id`, `vorname`, `name`, `geburtsdatum`, `geschlecht`, `funktion`, `sort_order`) VALUES
(1, 1, 'Maximilan', 'Sirtl', '1996-02-05', 'm', NULL, 0),
(2, 1, 'Christian', 'Sirtl', '1991-07-01', 'm', NULL, 0),
(3, 1, 'Markus', 'Panzer', '1985-01-01', 'm', NULL, 0),
(4, 1, 'Hans', 'Huber', '2002-06-01', 'm', NULL, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `group_station_log`
--

CREATE TABLE `group_station_log` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `checked_in` datetime NOT NULL DEFAULT current_timestamp(),
  `checked_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `group_station_log`
--

INSERT INTO `group_station_log` (`id`, `group_id`, `station_id`, `checked_in`, `checked_out`) VALUES
(1, 1, 2, '2026-05-12 08:43:08', '2026-05-12 08:43:41');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `judges`
--

CREATE TABLE `judges` (
  `id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `initials` varchar(5) DEFAULT NULL,
  `role` varchar(100) NOT NULL,
  `qr_token` char(32) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `judges`
--

INSERT INTO `judges` (`id`, `station_id`, `name`, `initials`, `role`, `qr_token`, `created_at`, `updated_at`) VALUES
(11, 3, 'Hans', NULL, 'Schiedsrichter A', 'dfa39b5b6f4ba03a91f842c2bfd96a80', '2026-05-11 15:25:19', '2026-05-11 15:25:19'),
(12, 2, 'Egon', NULL, 'Schiedsrichter A', '03cbc4083f7a9c27ccf740bcd72b1969', '2026-05-11 15:30:21', '2026-05-11 15:30:21'),
(13, 3, 'Christian Sirtl', NULL, 'Schiedsrichter A', 'ca627cbf67371d7bd6231df542b8a2fa', '2026-05-11 15:50:02', '2026-05-11 15:50:02'),
(14, 3, 'Test', NULL, 'Schiedsrichter A', 'ee6da604b47b06710a2a86ec31b7768e', '2026-05-12 08:24:21', '2026-05-12 08:24:21'),
(15, 3, 'TestNeu', NULL, 'Schiedsrichter A', '2319cf44751877e1e99ae34a54552a03', '2026-05-12 08:31:46', '2026-05-12 08:31:46'),
(16, 2, 'Huber', NULL, 'Schiedsrichter A', '2be8b55f528e3408d60333a740f43d50', '2026-05-12 08:34:04', '2026-05-12 08:34:04'),
(17, 2, 'Hallo', NULL, 'Schiedsrichter A', '429c5c6b60b7289ea9b31223647706f1', '2026-05-12 08:42:58', '2026-05-12 08:42:58');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `judge_id` int(11) DEFAULT NULL,
  `sender` enum('zentrale','judge') NOT NULL DEFAULT 'zentrale',
  `body` text NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `messages`
--

INSERT INTO `messages` (`id`, `station_id`, `judge_id`, `sender`, `body`, `read_at`, `created_at`) VALUES
(1, 2, NULL, 'judge', 'Testnachricht', '2026-05-11 13:55:38', '2026-05-11 11:19:41'),
(3, 2, NULL, 'zentrale', 'Antwort 1', '2026-05-11 11:23:09', '2026-05-11 11:22:56'),
(4, 2, NULL, 'zentrale', 'Antwort aus der Zentrale', '2026-05-11 13:56:36', '2026-05-11 13:56:14'),
(5, 2, NULL, 'judge', 'Antwort Schiedsrichter', '2026-05-11 13:56:54', '2026-05-11 13:56:51'),
(6, 2, NULL, 'zentrale', 'Neue Nachricht an &quot;Testfragen&quot;', '2026-05-11 14:02:28', '2026-05-11 14:02:24'),
(7, 2, NULL, 'judge', 'Antwort', '2026-05-11 14:03:44', '2026-05-11 14:03:36'),
(8, 3, NULL, 'judge', 'Test', '2026-05-11 14:55:48', '2026-05-11 14:55:19'),
(9, 3, NULL, 'zentrale', 'Test zurück', '2026-05-11 14:56:01', '2026-05-11 14:55:55');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `offline_queue`
--

CREATE TABLE `offline_queue` (
  `id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `synced_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `time_ms` int(10) UNSIGNED DEFAULT NULL,
  `impression` enum('sehr_gut','gut','befriedigend') NOT NULL DEFAULT 'gut',
  `total_fp` smallint(6) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `task_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`task_results`)),
  `synced_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `scores`
--

INSERT INTO `scores` (`id`, `judge_id`, `group_id`, `station_id`, `time_ms`, `impression`, `total_fp`, `notes`, `task_results`, `synced_at`, `created_at`, `updated_at`) VALUES
(9, 15, 1, 3, NULL, 'sehr_gut', 14, NULL, '[{\"task_id\":20,\"type\":\"boolean\",\"value\":\"ok\"},{\"task_id\":21,\"type\":\"count\",\"value\":0},{\"task_id\":22,\"type\":\"count\",\"value\":0},{\"task_id\":23,\"type\":\"count\",\"value\":1},{\"task_id\":24,\"type\":\"count\",\"value\":0},{\"task_id\":25,\"type\":\"boolean\",\"value\":\"fail\"},{\"task_id\":26,\"type\":\"count\",\"value\":0},{\"task_id\":27,\"type\":\"count\",\"value\":1},{\"task_id\":28,\"type\":\"count\",\"value\":0},{\"task_id\":29,\"type\":\"time\",\"value\":null,\"times\":[1031,2268,5333,17419]},{\"task_id\":30,\"type\":\"boolean\",\"value\":\"ok\"}]', '2026-05-12 08:33:44', '2026-05-12 08:33:44', '2026-05-12 08:33:44'),
(11, 17, 1, 2, 4596, 'sehr_gut', 6, NULL, '[{\"task_id\":1,\"type\":\"boolean\",\"value\":\"fail\"},{\"task_id\":2,\"type\":\"count\",\"value\":0},{\"task_id\":3,\"type\":\"count\",\"value\":1},{\"task_id\":4,\"type\":\"count\",\"value\":0},{\"task_id\":5,\"type\":\"count\",\"value\":0},{\"task_id\":6,\"type\":\"boolean\",\"value\":\"ok\"},{\"task_id\":7,\"type\":\"count\",\"value\":0},{\"task_id\":8,\"type\":\"time\",\"value\":null},{\"task_id\":9,\"type\":\"boolean\",\"value\":\"ok\"}]', '2026-05-12 08:43:41', '2026-05-12 08:43:41', '2026-05-12 08:43:41');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `score_criteria`
--

CREATE TABLE `score_criteria` (
  `id` int(11) NOT NULL,
  `score_id` int(11) NOT NULL,
  `criterion_id` int(11) NOT NULL,
  `result` enum('ok','fail') NOT NULL DEFAULT 'ok'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `score_penalties`
--

CREATE TABLE `score_penalties` (
  `id` int(11) NOT NULL,
  `score_id` int(11) NOT NULL,
  `penalty_id` int(11) NOT NULL,
  `count` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `stations`
--

CREATE TABLE `stations` (
  `id` int(11) NOT NULL,
  `competition_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `task` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `has_time` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `hash` char(32) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `version` varchar(20) NOT NULL DEFAULT '2026.1',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `stations`
--

INSERT INTO `stations` (`id`, `competition_id`, `code`, `name`, `task`, `location`, `has_time`, `active`, `hash`, `lat`, `lng`, `version`, `created_at`, `updated_at`) VALUES
(1, 1, '1', 'Startkontrolle', NULL, NULL, 0, 1, '1bad715d082849e1ada81c68dbea3644', NULL, NULL, '2026.1', '2026-05-10 22:46:15', '2026-05-10 22:46:15'),
(2, 1, '2', 'Testfragen', NULL, NULL, 0, 1, '6205df86b6e294fa2bb0c14c2bbca07e', NULL, NULL, '2026.1', '2026-05-10 23:00:06', '2026-05-10 23:00:06'),
(3, 1, '3', 'Zielwurf Feuerwehrleine', NULL, NULL, 0, 1, 'c77e6bb0a104e5a065cb67cf5d4ddb77', NULL, NULL, '2026.1', '2026-05-10 23:39:10', '2026-05-10 23:39:10'),
(4, 1, '4', 'Kuppeln Saugschlauch', NULL, NULL, 0, 1, 'e3f2ce6afe472bc683464ff0e5d76477', NULL, NULL, '2026.1', '2026-05-10 23:46:27', '2026-05-10 23:46:27');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `station_criteria`
--

CREATE TABLE `station_criteria` (
  `id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `label` varchar(255) NOT NULL,
  `hint` varchar(255) DEFAULT NULL,
  `weight` smallint(6) NOT NULL DEFAULT 5,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `station_penalties`
--

CREATE TABLE `station_penalties` (
  `id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `label` varchar(255) NOT NULL,
  `weight` smallint(6) NOT NULL DEFAULT 5,
  `max_count` tinyint(4) NOT NULL DEFAULT 10,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `station_tasks`
--

CREATE TABLE `station_tasks` (
  `id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `type` enum('count','boolean','time') NOT NULL DEFAULT 'boolean',
  `points` smallint(6) NOT NULL DEFAULT 1,
  `max_count` smallint(5) UNSIGNED DEFAULT NULL,
  `sollzeit_sek` smallint(5) UNSIGNED DEFAULT NULL,
  `hoechstzeit_sek` smallint(5) UNSIGNED DEFAULT NULL,
  `zeitstrafe_fp` tinyint(3) UNSIGNED DEFAULT NULL,
  `zeiteinheit_sek` tinyint(3) UNSIGNED DEFAULT NULL,
  `zeit_felder` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `station_tasks`
--

INSERT INTO `station_tasks` (`id`, `station_id`, `label`, `type`, `points`, `max_count`, `sollzeit_sek`, `hoechstzeit_sek`, `zeitstrafe_fp`, `zeiteinheit_sek`, `zeit_felder`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 2, 'Anmeldung der Gruppe vor der Übung', 'boolean', 1, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-05-11 10:43:00', '2026-05-11 10:43:00'),
(2, 2, 'Helm / Kunststoffhelm getragen', 'count', 10, 4, NULL, NULL, NULL, NULL, 1, 2, '2026-05-11 10:43:31', '2026-05-11 10:43:31'),
(3, 2, 'Schutzanzug komplett getragen', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 3, '2026-05-11 10:43:57', '2026-05-11 10:43:57'),
(4, 2, 'Sicherheitsstiefel getragen', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 4, '2026-05-11 10:44:20', '2026-05-11 10:44:20'),
(5, 2, 'Feuerwehrhandschuhe getragen', 'count', 10, 4, NULL, NULL, NULL, NULL, 1, 5, '2026-05-11 10:44:34', '2026-05-11 10:44:34'),
(6, 2, 'Kein unnötiges Reden oder Sprechen', 'boolean', 2, NULL, NULL, NULL, NULL, NULL, 1, 6, '2026-05-11 10:44:55', '2026-05-11 10:44:55'),
(7, 2, 'Falsche Antwort / nicht bearbeitet', 'count', 1, 72, NULL, NULL, NULL, NULL, 1, 7, '2026-05-11 10:45:11', '2026-05-11 10:45:11'),
(8, 2, 'Zeit', 'time', 1, NULL, 180, 300, 1, 1, 1, 8, '2026-05-11 10:46:04', '2026-05-11 10:46:04'),
(9, 2, 'Abmelden der Gruppe nach der Übung', 'boolean', 1, NULL, NULL, NULL, NULL, NULL, 1, 9, '2026-05-11 10:46:39', '2026-05-11 10:46:39'),
(10, 1, 'Anmeldung der Gruppe vor der Übung', 'boolean', 1, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-05-11 14:41:04', '2026-05-11 14:41:04'),
(11, 1, 'Helm / Kunststoffhelm getragen', 'count', 10, 4, NULL, NULL, NULL, NULL, 1, 2, '2026-05-11 14:41:18', '2026-05-11 14:41:18'),
(12, 1, 'Schutzanzug komplett getragen', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 3, '2026-05-11 14:41:36', '2026-05-11 14:41:36'),
(13, 1, 'Sicherheitsstiefel getragen', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 4, '2026-05-11 14:41:49', '2026-05-11 14:41:49'),
(14, 1, 'Feuerwehrhandschuhe getragen', 'count', 10, 4, NULL, NULL, NULL, NULL, 1, 5, '2026-05-11 14:42:02', '2026-05-11 14:42:10'),
(15, 1, 'Kein unnötiges Reden oder Sprechen', 'boolean', 2, NULL, NULL, NULL, NULL, NULL, 1, 6, '2026-05-11 14:42:25', '2026-05-11 14:42:25'),
(16, 1, 'Ausweis/Dienstbuch vorhandem', 'count', 10, 4, NULL, NULL, NULL, NULL, 1, 7, '2026-05-11 14:42:44', '2026-05-11 14:42:44'),
(17, 1, 'Ausweis/Dienstbuch vollständig', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 8, '2026-05-11 14:42:59', '2026-05-11 14:42:59'),
(18, 1, 'Startkarte vorhanden', 'boolean', 5, NULL, NULL, NULL, NULL, NULL, 1, 9, '2026-05-11 14:43:13', '2026-05-11 14:43:13'),
(19, 1, 'Abmelden der Gruppe nach der Übung', 'boolean', 1, NULL, NULL, NULL, NULL, NULL, 1, 10, '2026-05-11 14:43:32', '2026-05-11 14:43:32'),
(20, 3, 'Anmeldung der Gruppe vor der Übung', 'boolean', 1, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-05-11 14:45:05', '2026-05-11 14:45:05'),
(21, 3, 'Helm / Kunststoffhelm getragen', 'count', 10, 4, NULL, NULL, NULL, NULL, 1, 2, '2026-05-11 14:45:22', '2026-05-11 14:45:22'),
(22, 3, 'Schutzanzug komplett getragen', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 3, '2026-05-11 14:45:38', '2026-05-11 14:45:38'),
(23, 3, 'Sicherheitsstiefel getragen', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 4, '2026-05-11 14:45:59', '2026-05-11 14:45:59'),
(24, 3, 'Feuerwehrhandschuhe getragen', 'count', 10, 4, NULL, NULL, NULL, NULL, 1, 5, '2026-05-11 14:46:16', '2026-05-11 14:46:16'),
(25, 3, 'Kein unnötiges Reden oder Sprechen', 'boolean', 2, NULL, NULL, NULL, NULL, NULL, 1, 6, '2026-05-11 14:46:28', '2026-05-11 14:46:28'),
(26, 3, 'Verfehlen des Ziels', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 7, '2026-05-11 14:46:46', '2026-05-11 14:46:46'),
(27, 3, 'Überschreiten der Startlinie', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 8, '2026-05-11 14:47:05', '2026-05-11 14:47:05'),
(28, 3, 'Ende der Leine nicht festgehalten', 'count', 5, 4, NULL, NULL, NULL, NULL, 1, 9, '2026-05-11 14:47:22', '2026-05-11 14:47:22'),
(29, 3, 'Zeit', 'time', 1, NULL, 15, 20, 1, 1, 4, 10, '2026-05-11 14:47:59', '2026-05-11 15:48:45'),
(30, 3, 'Abmelden der Gruppe nach der Übung', 'boolean', 1, NULL, NULL, NULL, NULL, NULL, 1, 11, '2026-05-11 14:48:14', '2026-05-11 14:48:14');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`);

--
-- Indizes für die Tabelle `competitions`
--
ALTER TABLE `competitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_competitions_hash` (`hash`);

--
-- Indizes für die Tabelle `feuerwehren`
--
ALTER TABLE `feuerwehren`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bereich` (`bereich`),
  ADD KEY `idx_kbi_bereich` (`kbi_bereich`);

--
-- Indizes für die Tabelle `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_groups_token` (`qr_token`),
  ADD KEY `fk_groups_competition` (`competition_id`),
  ADD KEY `fk_groups_last_station` (`last_station_id`),
  ADD KEY `fk_groups_feuerwehr` (`feuerwehr_id`);

--
-- Indizes für die Tabelle `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_members_group` (`group_id`);

--
-- Indizes für die Tabelle `group_station_log`
--
ALTER TABLE `group_station_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_group` (`group_id`),
  ADD KEY `fk_log_station` (`station_id`);

--
-- Indizes für die Tabelle `judges`
--
ALTER TABLE `judges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_judges_token` (`qr_token`),
  ADD KEY `fk_judges_station` (`station_id`);

--
-- Indizes für die Tabelle `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_messages_station` (`station_id`),
  ADD KEY `fk_messages_judge` (`judge_id`);

--
-- Indizes für die Tabelle `offline_queue`
--
ALTER TABLE `offline_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_oq_judge` (`judge_id`);

--
-- Indizes für die Tabelle `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_score` (`judge_id`,`group_id`,`station_id`),
  ADD KEY `fk_scores_group` (`group_id`),
  ADD KEY `fk_scores_station` (`station_id`);

--
-- Indizes für die Tabelle `score_criteria`
--
ALTER TABLE `score_criteria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_score_criterion` (`score_id`,`criterion_id`),
  ADD KEY `fk_sc_criterion` (`criterion_id`);

--
-- Indizes für die Tabelle `score_penalties`
--
ALTER TABLE `score_penalties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_score_penalty` (`score_id`,`penalty_id`),
  ADD KEY `fk_sp_penalty` (`penalty_id`);

--
-- Indizes für die Tabelle `stations`
--
ALTER TABLE `stations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`),
  ADD KEY `fk_stations_competition` (`competition_id`);

--
-- Indizes für die Tabelle `station_criteria`
--
ALTER TABLE `station_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_criteria_station` (`station_id`);

--
-- Indizes für die Tabelle `station_penalties`
--
ALTER TABLE `station_penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_penalties_station` (`station_id`);

--
-- Indizes für die Tabelle `station_tasks`
--
ALTER TABLE `station_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_station_tasks_station` (`station_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `competitions`
--
ALTER TABLE `competitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `feuerwehren`
--
ALTER TABLE `feuerwehren`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT für Tabelle `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `group_station_log`
--
ALTER TABLE `group_station_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `judges`
--
ALTER TABLE `judges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT für Tabelle `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT für Tabelle `offline_queue`
--
ALTER TABLE `offline_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT für Tabelle `score_criteria`
--
ALTER TABLE `score_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `score_penalties`
--
ALTER TABLE `score_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `stations`
--
ALTER TABLE `stations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `station_criteria`
--
ALTER TABLE `station_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `station_penalties`
--
ALTER TABLE `station_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `station_tasks`
--
ALTER TABLE `station_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `fk_groups_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_groups_feuerwehr` FOREIGN KEY (`feuerwehr_id`) REFERENCES `feuerwehren` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_groups_last_station` FOREIGN KEY (`last_station_id`) REFERENCES `stations` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `fk_members_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `group_station_log`
--
ALTER TABLE `group_station_log`
  ADD CONSTRAINT `fk_log_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_log_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `judges`
--
ALTER TABLE `judges`
  ADD CONSTRAINT `fk_judges_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_judge` FOREIGN KEY (`judge_id`) REFERENCES `judges` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `offline_queue`
--
ALTER TABLE `offline_queue`
  ADD CONSTRAINT `fk_oq_judge` FOREIGN KEY (`judge_id`) REFERENCES `judges` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `fk_scores_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  ADD CONSTRAINT `fk_scores_judge` FOREIGN KEY (`judge_id`) REFERENCES `judges` (`id`),
  ADD CONSTRAINT `fk_scores_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`);

--
-- Constraints der Tabelle `score_criteria`
--
ALTER TABLE `score_criteria`
  ADD CONSTRAINT `fk_sc_criterion` FOREIGN KEY (`criterion_id`) REFERENCES `station_criteria` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sc_score` FOREIGN KEY (`score_id`) REFERENCES `scores` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `score_penalties`
--
ALTER TABLE `score_penalties`
  ADD CONSTRAINT `fk_sp_penalty` FOREIGN KEY (`penalty_id`) REFERENCES `station_penalties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sp_score` FOREIGN KEY (`score_id`) REFERENCES `scores` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `stations`
--
ALTER TABLE `stations`
  ADD CONSTRAINT `fk_stations_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `station_criteria`
--
ALTER TABLE `station_criteria`
  ADD CONSTRAINT `fk_criteria_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `station_penalties`
--
ALTER TABLE `station_penalties`
  ADD CONSTRAINT `fk_penalties_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `station_tasks`
--
ALTER TABLE `station_tasks`
  ADD CONSTRAINT `fk_station_tasks_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
