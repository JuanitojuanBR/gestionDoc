<?php
require_once '../config/sesion.php';
require_once '../config/database.php';
requerirAdministrador();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entrega_id = intval($_POST['entrega_id']);
    $doc_id = intval($_POST['doc_id']);
    $admin_feedback = trim($_POST['admin_feedback']);

    if ($entrega_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE drive_entregas SET admin_feedback = ? WHERE id = ?");
            $stmt->execute([$admin_feedback, $entrega_id]);
        } catch(PDOException $e) {
            // Redirigir con error si falla (opcional)
        }
    }

    header('Location: ../ver_entregas_drive.php?doc_id=' . $doc_id);
    exit;
}
