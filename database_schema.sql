-- Script de base de datos para Gestión Documental Universitaria
-- Base de datos: gestion_documentos

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

-- Estructura de tabla para la tabla `usuarios`
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `rol` enum('administrador','decano','coordinador_sede','docente_tc','docente_catedra') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estructura de tabla para la tabla `documentos`
CREATE TABLE IF NOT EXISTS `documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11),
  `titulo` varchar(255) NOT NULL,
  `contenido` longtext,
  `tipo` enum('reporte','carta','factura','archivo_subido','documento_convertido') NOT NULL,
  `nombre_archivo` varchar(255) DEFAULT NULL,
  `ruta_archivo` varchar(255) DEFAULT NULL,
  `tamanio_archivo` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estructura de tabla para la tabla `drive_documentos`
CREATE TABLE IF NOT EXISTS `drive_documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text,
  `tipo_archivo` enum('docs','sheets','slides','pdf','other') DEFAULT 'other',
  `tipo_documento` varchar(100) DEFAULT NULL,
  `rol_asignado` varchar(50) DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estructura de tabla para la tabla `drive_entregas`
CREATE TABLE IF NOT EXISTS `drive_entregas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_drive_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `comentario` text,
  `admin_feedback` text,
  `estado` enum('pendiente','en_progreso','completado') DEFAULT 'completado',
  `fecha_entrega` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`documento_drive_id`) REFERENCES `drive_documentos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales para pruebas
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`) VALUES
('Administrador Sistema', 'admin@universidad.edu.co', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador'),
('Decano Facultad', 'decano@universidad.edu.co', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'decano');

COMMIT;
