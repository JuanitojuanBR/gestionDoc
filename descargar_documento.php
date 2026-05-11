<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirLogin();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $usuario_id = $_SESSION['usuario_id'];
    
    try {
        // Verificar que el documento existe y el usuario tiene acceso
        // Los administradores pueden descargar cualquier archivo
        if (esAdministrador()) {
            $stmt = $conn->prepare("SELECT * FROM documentos WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            // Los profesores solo sus propios archivos
            $stmt = $conn->prepare("SELECT * FROM documentos WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
        }
        
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($documento && !empty($documento['ruta_archivo']) && file_exists($documento['ruta_archivo'])) {
            // Configurar headers para descarga
            header('Content-Type: ' . ($documento['tipo_archivo'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . $documento['nombre_archivo'] . '"');
            header('Content-Length: ' . filesize($documento['ruta_archivo']));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Limpiar el buffer de salida
            ob_clean();
            flush();
            
            // Enviar el archivo
            readfile($documento['ruta_archivo']);
            exit();
        } else {
            echo "<script>alert('Archivo no encontrado.'); window.location.href='listar_documentos.php';</script>";
        }
    } catch(Exception $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='listar_documentos.php';</script>";
    }
} else {
    header("Location: listar_documentos.php");
    exit();
}
?>
