<?php
require_once '../config/sesion.php';
require_once '../config/database.php';
requerirAdministrador();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$correos      = $_POST['correos_destino'] ?? '';
$auto         = isset($_POST['envio_automatico']) ? 1 : 0;
$intervalo    = intval($_POST['intervalo_dias'] ?? 2);
$hora         = $_POST['hora_envio'] ?? '08:00:00';
$smtp_host    = $_POST['smtp_host'] ?? 'smtp.gmail.com';
$smtp_port    = intval($_POST['smtp_port'] ?? 587);
$smtp_user    = $_POST['smtp_user'] ?? '';
$smtp_pass    = $_POST['smtp_pass'] ?? '';
$smtp_secure  = $_POST['smtp_secure'] ?? 'tls';

try {
    // Verificar si ya existe configuración
    $stmtCheck = $conn->query("SELECT id FROM config_envio_informes LIMIT 1");
    $configExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($configExistente) {
        $sql = "UPDATE config_envio_informes SET 
                correos_destino = ?, envio_automatico = ?, intervalo_dias = ?, 
                hora_envio = ?, smtp_host = ?, smtp_port = ?, 
                smtp_user = ?, smtp_pass = ?, smtp_secure = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $correos, $auto, $intervalo, $hora, 
            $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_secure,
            $configExistente['id']
        ]);
    } else {
        $sql = "INSERT INTO config_envio_informes 
                (correos_destino, envio_automatico, intervalo_dias, hora_envio, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $correos, $auto, $intervalo, $hora, 
            $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_secure
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
