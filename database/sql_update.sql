-- ============================================================
-- Script de actualización — Módulo de Informe Mensual
-- Ejecutar este script en phpMyAdmin o consola MySQL
-- ============================================================

USE gestion_documentos;

-- 1. Asegurar que la columna usuario_id exista en documentos
-- (subir_documento.php ya la usa, pero por si acaso no fue creada)
ALTER TABLE documentos 
  MODIFY COLUMN usuario_id INT NULL;

-- 2. Agregar columna departamento a usuarios (opcional, para el informe)
ALTER TABLE usuarios 
  ADD COLUMN IF NOT EXISTS departamento VARCHAR(100) DEFAULT NULL AFTER rol;

-- ============================================================
-- Verificación: Comprobar que todo quedó bien
-- ============================================================
-- SELECT id, titulo, tipo, usuario_id, fecha_creacion FROM documentos LIMIT 10;
-- SELECT id, nombre, email, rol, departamento FROM usuarios LIMIT 10;
