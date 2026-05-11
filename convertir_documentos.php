<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
require_once 'vendor/autoload.php';
requerirLogin();

use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $fecha = date('Y-m-d H:i:s');
    $usuario_id = $_SESSION['usuario_id'];
    
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $contenidoExtraido = '';
        
        try {
            if ($extension === 'pdf') {
                // Extraer texto de PDF
                $parser = new PdfParser();
                $pdf = $parser->parseFile($archivo['tmp_name']);
                $contenidoExtraido = $pdf->getText();
                
            } elseif (in_array($extension, ['doc', 'docx'])) {
                // Extraer texto de Word
                $phpWord = IOFactory::load($archivo['tmp_name']);
                $sections = $phpWord->getSections();
                
                foreach ($sections as $section) {
                    $elements = $section->getElements();
                    foreach ($elements as $element) {
                        if (method_exists($element, 'getText')) {
                            $contenidoExtraido .= $element->getText() . "\n";
                        } elseif (method_exists($element, 'getElements')) {
                            foreach ($element->getElements() as $childElement) {
                                if (method_exists($childElement, 'getText')) {
                                    $contenidoExtraido .= $childElement->getText() . "\n";
                                } elseif (method_exists($childElement, 'getTextObject')) {
                                    $textObject = $childElement->getTextObject();
                                    if (method_exists($textObject, 'getText')) {
                                        $contenidoExtraido .= $textObject->getText() . "\n";
                                    }
                                }
                            }
                        }
                    }
                }
                
            } elseif ($extension === 'txt') {
                // Leer archivo de texto plano
                $contenidoExtraido = file_get_contents($archivo['tmp_name']);
                
            } else {
                $error = "Formato no soportado. Solo PDF, Word (doc, docx) y TXT";
            }
            
            if (!empty($contenidoExtraido)) {
                // Limpiar el contenido
                $contenidoExtraido = trim($contenidoExtraido);
                
                // Guardar en la base de datos
                $contenidoJson = json_encode([
                    'tipo' => 'documento_convertido',
                    'titulo' => $titulo,
                    'contenido' => $contenidoExtraido,
                    'archivo_original' => $archivo['name']
                ]);
                
                $stmt = $conn->prepare("INSERT INTO documentos (titulo, tipo, contenido, fecha_creacion, usuario_id, nombre_archivo, es_archivo_subido) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$titulo, 'documento_convertido', $contenidoJson, $fecha, $usuario_id, $archivo['name'], 0]);
                
                header("Location: listar_documentos.php?success=convertido");
                exit();
            } else {
                $error = "No se pudo extraer texto del archivo. El archivo puede estar vacío o protegido.";
            }
            
        } catch(Exception $e) {
            $error = "Error al procesar el archivo: " . $e->getMessage();
        }
    } else {
        $error = "Por favor selecciona un archivo para convertir.";
    }
}
?>
<?php 
$page_title = "Convertir Documento - Universitaria de Colombia";
include 'includes/header.php'; 
?>

<div class="hero-banner text-center py-4">
    <div class="container">
        <h1 class="fw-bold"><i class="fas fa-magic me-2"></i>Convertidor Inteligente</h1>
        <p class="lead opacity-75">Extrae y digitaliza contenido de archivos externos (PDF, Word, TXT)</p>
    </div>
</div>


    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-file-import"></i> Convertir Documentos</h2>
                    <div>
                        <a href="subir_documento.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-upload"></i> Subir Archivo Original
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
                        <li>Esta opción extrae el texto del archivo y lo guarda en el sistema</li>
                        <li>Permite buscar y editar el contenido posteriormente</li>
                        <li>El archivo original no se conserva</li>
                        <li>Formatos soportados: PDF, Word (doc, docx), TXT</li>
                    </ul>
                </div>
                
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="titulo" class="form-label">
                                    <i class="fas fa-heading"></i> Título del Documento *
                                </label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="archivo" class="form-label">
                                    <i class="fas fa-file"></i> Seleccionar Archivo *
                                </label>
                                <input type="file" class="form-control" id="archivo" name="archivo" accept=".pdf,.doc,.docx,.txt" required>
                                <div class="form-text">
                                    Formatos soportados: PDF, Word (doc, docx), TXT
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Nota:</strong> El proceso de conversión puede tardar unos segundos dependiendo del tamaño del archivo.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-file-import"></i> Convertir y Guardar
                                </button>
                                <a href="listar_documentos.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h5>Diferencias entre las opciones:</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-upload"></i> Subir Archivo
                                </div>
                                <div class="card-body">
                                    <ul>
                                        <li>Mantiene el archivo original</li>
                                        <li>Permite descarga posterior</li>
                                        <li>Soporta más formatos</li>
                                        <li>Ideal para archivar</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <i class="fas fa-file-import"></i> Convertir Documento
                                </div>
                                <div class="card-body">
                                    <ul>
                                        <li>Extrae solo el texto</li>
                                        <li>Permite búsqueda de contenido</li>
                                        <li>Contenido editable</li>
                                        <li>Ideal para procesar información</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
