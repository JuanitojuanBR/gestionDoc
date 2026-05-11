-- ============================================================
-- Tabla: drive_entregas
-- Propósito: Almacenar los archivos editados que los docentes/decanos/
-- coordinadores suben como respuesta a un documento Drive asignado.
-- Ejecutar una vez en phpMyAdmin o desde la consola MySQL.
-- ============================================================

CREATE TABLE IF NOT EXISTS `drive_entregas` (
    `id`               INT          AUTO_INCREMENT PRIMARY KEY,
    `usuario_id`       INT          NOT NULL,
    `drive_doc_id`     INT          NOT NULL,
    `nombre_original`  VARCHAR(255) NOT NULL,
    `ruta_archivo`     VARCHAR(500) NOT NULL,
    `tamanio_archivo`  BIGINT       DEFAULT 0,
    `comentario`       TEXT,
    `fecha_entrega`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`usuario_id`)   REFERENCES `usuarios`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`drive_doc_id`) REFERENCES `drive_documentos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
