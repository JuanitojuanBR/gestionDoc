-- ============================================================
-- ACTUALIZACIÓN: Integración Google Drive + Expansión de Roles
-- Base de datos: gestion_documentos
-- ============================================================

USE gestion_documentos;

-- 1. Expandir roles de usuario (mantener administrador y profesor + 4 nuevos)
ALTER TABLE usuarios MODIFY COLUMN rol VARCHAR(50) NOT NULL DEFAULT 'profesor';

-- 2. Tabla de documentos de Google Drive
CREATE TABLE IF NOT EXISTS drive_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    url_drive VARCHAR(500) NOT NULL,
    tipo_documento VARCHAR(100) DEFAULT 'general',
    tipo_archivo VARCHAR(20) DEFAULT 'docs',
    rol_asignado VARCHAR(50) NOT NULL,
    fecha_limite DATE DEFAULT NULL,
    asignado_por INT DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asignado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla de estado individual por usuario-documento
CREATE TABLE IF NOT EXISTS usuario_drive_estado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    drive_doc_id INT NOT NULL,
    estado ENUM('pendiente','en_progreso','completado') DEFAULT 'pendiente',
    fecha_apertura DATETIME DEFAULT NULL,
    fecha_completado DATETIME DEFAULT NULL,
    notas TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (drive_doc_id) REFERENCES drive_documentos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_doc (usuario_id, drive_doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Verificación
-- ============================================================
SELECT 'OK: Tablas creadas y roles actualizados' AS resultado;
