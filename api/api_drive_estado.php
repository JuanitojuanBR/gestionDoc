<?php
require_once '../config/sesion.php';
require_once '../config/database.php';
requerirLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion  = $_POST['accion'] ?? '';
    $docId   = intval($_POST['doc_id'] ?? 0);
    $usuarioId = $_SESSION['usuario_id'];

    if ($docId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID de documento inválido']);
        exit;
    }

    try {
        if ($accion === 'abrir') {
            // Registrar fecha de apertura
            $stmt = $conn->prepare("
                INSERT INTO usuario_drive_estado (usuario_id, drive_doc_id, fecha_apertura) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE fecha_apertura = NOW()
            ");
            $stmt->execute([$usuarioId, $docId]);
            echo json_encode(['ok' => true]);

        } elseif ($accion === 'estado') {
            $nuevoEstado = $_POST['estado'] ?? 'pendiente';
            $validos = ['pendiente', 'en_progreso', 'completado'];
            
            if (!in_array($nuevoEstado, $validos)) {
                echo json_encode(['ok' => false, 'error' => 'Estado inválido']);
                exit;
            }

            $fechaCompletado = ($nuevoEstado === 'completado') ? 'NOW()' : 'NULL';
            
            $sql = "
                INSERT INTO usuario_drive_estado (usuario_id, drive_doc_id, estado, fecha_completado) 
                VALUES (?, ?, ?, " . ($nuevoEstado === 'completado' ? 'NOW()' : 'NULL') . ") 
                ON DUPLICATE KEY UPDATE estado = ?, fecha_completado = " . ($nuevoEstado === 'completado' ? 'NOW()' : 'NULL');
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$usuarioId, $docId, $nuevoEstado, $nuevoEstado]);
            
            echo json_encode(['ok' => true]);

        } else {
            echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
