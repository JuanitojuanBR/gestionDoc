<?php
require_once '../config/sesion.php';
require_once '../config/database.php';
require_once '../config/MailService.php';
requerirAdministrador();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $mailService = new MailService($conn);
    
    // Podemos recibir mes y año opcionalmente, de lo contrario usa el actual
    $mes = isset($_POST['mes']) ? intval($_POST['mes']) : null;
    $anio = isset($_POST['anio']) ? intval($_POST['anio']) : null;

    $resultado = $mailService->enviarInformeMensual($mes, $anio, true);
    
    echo json_encode($resultado);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error crítico: ' . $e->getMessage()]);
}
