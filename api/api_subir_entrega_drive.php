<?php
require_once '../config/sesion.php';
require_once '../config/database.php';
requerirLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$usuario_id  = $_SESSION['usuario_id'];
$usuario_rol = $_SESSION['rol'];
$drive_doc_id = intval($_POST['drive_doc_id'] ?? 0);
$comentario   = trim($_POST['comentario'] ?? '');

if ($drive_doc_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de documento inválido']);
    exit;
}

// ─── Verificar que el documento pertenece al rol del usuario ──────────
$stmtCheck = $conn->prepare("SELECT id FROM drive_documentos WHERE id = ? AND rol_asignado = ? AND activo = 1");
$stmtCheck->execute([$drive_doc_id, $usuario_rol]);
if (!$stmtCheck->fetch()) {
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para entregar este documento']);
    exit;
}

// ─── Validar archivo ─────────────────────────────────────────────────
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la subida']);
    exit;
}

$archivo       = $_FILES['archivo'];
$extension     = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$extensiones   = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
$maxTamanio    = 20 * 1024 * 1024; // 20MB

if (!in_array($extension, $extensiones)) {
    echo json_encode(['success' => false, 'error' => 'Formato no permitido. Use: PDF, Word, Excel, PowerPoint o TXT']);
    exit;
}

if ($archivo['size'] > $maxTamanio) {
    echo json_encode(['success' => false, 'error' => 'El archivo supera el límite de 20MB']);
    exit;
}

// ─── Guardar archivo ─────────────────────────────────────────────────
$dirEntregas = __DIR__ . '/../uploads/drive_entregas/';
if (!is_dir($dirEntregas)) {
    mkdir($dirEntregas, 0755, true);
}

$nombreUnico  = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
$rutaAbsoluta = $dirEntregas . $nombreUnico;
$rutaRelativa = 'uploads/drive_entregas/' . $nombreUnico;

if (!move_uploaded_file($archivo['tmp_name'], $rutaAbsoluta)) {
    echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo en el servidor']);
    exit;
}

// ─── Insertar en drive_entregas ──────────────────────────────────────
try {
    $conn->beginTransaction();

    $stmtInsert = $conn->prepare("
        INSERT INTO drive_entregas (usuario_id, drive_doc_id, nombre_original, ruta_archivo, tamanio_archivo, comentario)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $usuario_id,
        $drive_doc_id,
        $archivo['name'],
        $rutaRelativa,
        $archivo['size'],
        $comentario
    ]);

    // ─── Marcar estado como completado automáticamente ────────────────
    $stmtUpsert = $conn->prepare("
        INSERT INTO usuario_drive_estado (usuario_id, drive_doc_id, estado, fecha_completado)
        VALUES (?, ?, 'completado', NOW())
        ON DUPLICATE KEY UPDATE estado = 'completado', fecha_completado = NOW()
    ");
    $stmtUpsert->execute([$usuario_id, $drive_doc_id]);

    $conn->commit();

    echo json_encode([
        'success'       => true,
        'message'       => 'Archivo entregado correctamente',
        'fecha_entrega' => date('d/m/Y H:i'),
        'nombre'        => htmlspecialchars($archivo['name'])
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    // Eliminar archivo si falló la BD
    if (file_exists($rutaAbsoluta)) unlink($rutaAbsoluta);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
