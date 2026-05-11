<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'] ?? '';
    $fecha = date('Y-m-d H:i:s');
    $usuario_id = $_SESSION['usuario_id'];

    // Validar que se haya subido un archivo
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo'];
        
        // Obtener información del archivo
        $nombreOriginal = $archivo['name'];
        $tamanio = $archivo['size'];
        $tipoArchivo = $archivo['type'];
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        
        // Validar tamaño (máximo 10MB)
        $tamanioMaximo = 10 * 1024 * 1024; // 10MB en bytes
        if ($tamanio > $tamanioMaximo) {
            $error = "El archivo es demasiado grande. Máximo 10MB.";
        } else {
            // Generar nombre único para evitar conflictos
            $nombreUnico = uniqid() . '_' . time() . '.' . $extension;
            $rutaDestino = 'uploads/' . $nombreUnico;
            
            // Validar tipo de archivo
            $tiposPermitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'ppt', 'pptx'];
            
            if (!in_array($extension, $tiposPermitidos)) {
                $error = "Tipo de archivo no permitido. Solo se permiten: " . implode(', ', $tiposPermitidos);
            } else {
                // Mover el archivo a la carpeta uploads
                if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                    try {
                        // Guardar en la base de datos
                        $stmt = $conn->prepare("INSERT INTO documentos (titulo, tipo, contenido, nombre_archivo, ruta_archivo, tipo_archivo, tamanio_archivo, fecha_creacion, usuario_id, es_archivo_subido) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $titulo, 
                            'archivo_subido', 
                            $descripcion, 
                            $nombreOriginal, 
                            $rutaDestino, 
                            $tipoArchivo, 
                            $tamanio, 
                            $fecha,
                            $usuario_id,
                            1
                        ]);
                        
                        header("Location: listar_documentos.php?success=subido");
                        exit();
                    } catch(Exception $e) {
                        $error = "Error al guardar en la base de datos: " . $e->getMessage();
                        // Eliminar el archivo si falla la base de datos
                        if (file_exists($rutaDestino)) {
                            unlink($rutaDestino);
                        }
                    }
                } else {
                    $error = "Error al mover el archivo al servidor.";
                }
            }
        }
    } else {
        $errorCodes = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
        ];
        $error = $errorCodes[$_FILES['archivo']['error']] ?? "Error desconocido al subir el archivo";
    }
}
?>
<?php 
$page_title = "Subir Documento - Universitaria de Colombia";
include 'includes/header.php'; 
?>
<div class="hero-banner text-center py-4">
    <div class="container">
        <h1 class="fw-bold"><i class="fas fa-upload me-2"></i>Subir Documento Externo</h1>
        <p class="lead opacity-75">Carga archivos existentes al repositorio oficial de la institución</p>
    </div>
</div>


    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-upload"></i> Subir Documento</h2>
                    <div>
                        <a href="convertir_documentos.php" class="btn btn-info btn-sm">
                            <i class="fas fa-file-import"></i> Convertir a Texto
                        </a>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Información:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Esta opción mantiene el archivo original para descarga posterior</li>
                        <li>Tamaño máximo: 10MB</li>
                        <li>Formatos permitidos: PDF, Word, Excel, PowerPoint, imágenes, TXT</li>
                    </ul>
                </div>
                
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-3">
                                <label for="titulo" class="form-label">
                                    <i class="fas fa-heading"></i> Título del Documento *
                                </label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">
                                    <i class="fas fa-align-left"></i> Descripción (Opcional)
                                </label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Agrega una descripción o notas sobre el documento"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="archivo" class="form-label">
                                    <i class="fas fa-file"></i> Seleccionar Archivo *
                                </label>
                                <input type="file" class="form-control" id="archivo" name="archivo" required onchange="mostrarInfoArchivo()">
                                <div class="form-text">
                                    Tipos permitidos: PDF, Word (doc, docx), Excel (xls, xlsx), PowerPoint (ppt, pptx), imágenes (jpg, png), TXT
                                </div>
                                <div id="archivoInfo" class="mt-2" style="display:none;">
                                    <div class="alert alert-secondary">
                                        <strong>Archivo seleccionado:</strong>
                                        <div id="archivoNombre"></div>
                                        <div id="archivoTamanio"></div>
                                        <div id="archivoTipo"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload"></i> Subir Documento
                                </button>
                                <a href="listar_documentos.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarInfoArchivo() {
            const input = document.getElementById('archivo');
            const file = input.files[0];
            
            if (file) {
                document.getElementById('archivoInfo').style.display = 'block';
                document.getElementById('archivoNombre').innerHTML = '<i class="fas fa-file"></i> ' + file.name;
                document.getElementById('archivoTamanio').innerHTML = '<i class="fas fa-weight"></i> Tamaño: ' + formatBytes(file.size);
                document.getElementById('archivoTipo').innerHTML = '<i class="fas fa-tag"></i> Tipo: ' + file.type;
            }
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }
    </script>
</body>
</html>
