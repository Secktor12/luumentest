-- Luumen Database Schema - Complete (Updated for Production)
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Estructura de tabla para la tabla `admninistrador`
--
CREATE TABLE IF NOT EXISTS `admninistrador` (
  `id_administrador` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario` varchar(200) NOT NULL,
  `contraseña` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Insertar admin por defecto si no existe
INSERT IGNORE INTO `admninistrador` (`id_administrador`, `usuario`, `contraseña`) VALUES (1, 'admin', 'luumen2026');

--
-- Estructura de tabla para la tabla `audio_library` (Biblioteca musical)
--
CREATE TABLE IF NOT EXISTS `audio_library` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Estructura de tabla para la tabla `cards`
--
CREATE TABLE IF NOT EXISTS `cards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `video_url` VARCHAR(255) NOT NULL,
    `audio_url` VARCHAR(255) DEFAULT NULL,
    `audio_start` DECIMAL(10,2) DEFAULT 0,
    `audio_duration` DECIMAL(10,2) DEFAULT 6,
    `video_volume` DECIMAL(3,2) DEFAULT 1.0,
    `music_volume` DECIMAL(3,2) DEFAULT 1.0,
    `serial_number` VARCHAR(50) DEFAULT NULL,
    `rarity` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Estructura de tabla para la tabla `user_interactions`
--
CREATE TABLE IF NOT EXISTS `user_interactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_uuid` VARCHAR(100) NOT NULL,
    `card_id` INT NOT NULL,
    `interaction_type` ENUM('fuego', 'muy_fuego', 'me_encanta') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_interaction` (`user_uuid`, `card_id`, `interaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Estructura de tabla para la tabla `view_analytics`
--
CREATE TABLE IF NOT EXISTS `view_analytics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_uuid` VARCHAR(100) NOT NULL,
    `card_id` INT NOT NULL,
    `view_duration` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Estructura de tabla para la tabla `comments`
--
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `card_id` INT NOT NULL,
    `user_uuid` VARCHAR(100) NOT NULL,
    `comment_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Estructura de tabla para la tabla `comment_likes`
--
CREATE TABLE IF NOT EXISTS `comment_likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `comment_id` INT NOT NULL,
    `user_uuid` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_comment_like` (`comment_id`, `user_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Estructura de tabla para la tabla `login_attempts`
--
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `ip` VARCHAR(45) PRIMARY KEY,
  `attempts` INT DEFAULT 1,
  `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indices para optimización
CREATE INDEX `idx_user_uuid` ON `user_interactions` (`user_uuid`);
CREATE INDEX `idx_card_id` ON `user_interactions` (`card_id`);
CREATE INDEX `idx_view_card` ON `view_analytics` (`card_id`);
CREATE INDEX `idx_comment_card` ON `comments` (`card_id`);

COMMIT;
