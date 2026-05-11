<?php
require_once 'config/sesion.php';
requerirLogin();
require_once 'config/database.php';
require_once 'classes/DocumentoFactory.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo      = $_POST['titulo'];
    $tipo        = $_POST['tipo'];
    $contenido   = $_POST['contenido'];
    $encabezado  = $_POST['encabezado'] ?? '';
    $pieDePagina = $_POST['pie_pagina'] ?? '';
    $fecha       = date('Y-m-d H:i:s');

    $datosAdicionales = [];
    if ($tipo === 'carta') {
        $datosAdicionales['destinatario'] = $_POST['destinatario'] ?? '';
    } elseif ($tipo === 'factura') {
        $datosAdicionales['numero_factura'] = $_POST['numero_factura'] ?? '';
        $datosAdicionales['monto']          = $_POST['monto'] ?? 0.0;
    }

    try {
        $factory   = DocumentoFactoryCreator::getFactory($tipo);
        $documento = $factory->createDocumento($titulo, $contenido, $encabezado, $pieDePagina, $datosAdicionales);

        $contenidoCompleto = json_encode([
            'tipo'            => $documento->getTipo(),
            'titulo'          => $documento->getTitulo(),
            'encabezado'      => $documento->getEncabezado(),
            'contenido'       => $documento->getContenido(),
            'pieDePagina'     => $documento->getPieDePagina(),
            'datosAdicionales'=> $datosAdicionales
        ]);

        $stmt = $conn->prepare("INSERT INTO documentos (titulo, tipo, contenido, fecha_creacion, usuario_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $tipo, $contenidoCompleto, $fecha, $_SESSION['usuario_id']]);

        header("Location: listar_documentos.php");
        exit();
    } catch(Exception $e) {
        $error = "Error al crear el documento: " . $e->getMessage();
    }
}
?>
<?php 
$page_title = "Crear Documento - Universitaria de Colombia";
include 'includes/header.php'; 
?>

<div class="hero-banner text-center py-4">
    <div class="container">
        <h1 class="fw-bold"><i class="fas fa-plus-circle me-2"></i>Crear Nuevo Documento</h1>
        <p class="lead opacity-75">Generador de reportes, cartas y facturas institucionales</p>
    </div>
</div>


<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Crear Nuevo Documento</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="documentoForm">
                <div class="mb-3">
                    <label for="titulo" class="form-label">Título</label>
                    <input type="text" class="form-control" id="titulo" name="titulo" required>
                </div>

                <div class="mb-3">
                    <label for="tipo" class="form-label">Tipo de Documento</label>
                    <select class="form-select" id="tipo" name="tipo" required onchange="mostrarCamposAdicionales()">
                        <option value="">Seleccione un tipo</option>
                        <option value="reporte">Reporte</option>
                        <option value="carta">Carta</option>
                        <option value="factura">Factura</option>
                        <option value="archivo_subido">Archivo Subido</option>
                    </select>
                </div>

                <div class="mb-3 campos-carta" style="display: none;">
                    <label for="destinatario" class="form-label">Destinatario</label>
                    <input type="text" class="form-control" id="destinatario" name="destinatario">
                </div>

                <div class="mb-3 campos-factura" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="numero_factura" class="form-label">Número de Factura</label>
                            <input type="text" class="form-control" id="numero_factura" name="numero_factura">
                        </div>
                        <div class="col-md-6">
                            <label for="monto" class="form-label">Monto</label>
                            <input type="number" step="0.01" class="form-control" id="monto" name="monto">
                        </div>
                    </div>
                </div>

                <div class="mb-3 campos-archivo" style="display: none;">
                    <div class="row">
                        <div class="col-md-12">
                            <label for="nombre_archivo" class="form-label">Nombre del Archivo Original</label>
                            <input type="text" class="form-control" id="nombre_archivo" name="nombre_archivo">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="encabezado" class="form-label">Encabezado</label>
                    <input type="text" class="form-control" id="encabezado" name="encabezado">
                </div>

                <div class="mb-3">
                    <label for="contenido" class="form-label">Contenido</label>
                    <textarea class="form-control" id="contenido" name="contenido" rows="10"></textarea>
                </div>

                <div class="mb-3">
                    <label for="pie_pagina" class="form-label">Pie de Página</label>
                    <input type="text" class="form-control" id="pie_pagina" name="pie_pagina">
                </div>

                <button type="submit" class="btn btn-primary">Crear Documento</button>
                <a href="listar_documentos.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function mostrarCamposAdicionales() {
    const tipo = document.getElementById('tipo').value;
    document.querySelector('.campos-carta').style.display   = 'none';
    document.querySelector('.campos-factura').style.display = 'none';
    document.querySelector('.campos-archivo').style.display = 'none';

    if (tipo === 'carta') {
        document.querySelector('.campos-carta').style.display = 'block';
    } else if (tipo === 'factura') {
        document.querySelector('.campos-factura').style.display = 'block';
    } else if (tipo === 'archivo_subido') {
        document.querySelector('.campos-archivo').style.display = 'block';
    }
}
</script>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
ClassicEditor
    .create( document.querySelector( '#contenido' ), {
        toolbar: [
            'undo', 'redo', '|',
            'heading', '|',
            'bold', 'italic', 'underline', 'strikethrough', '|',
            'bulletedList', 'numberedList', '|',
            'alignment:left', 'alignment:center', 'alignment:right', '|',
            'link', 'insertTable'
        ],
        language: 'es'
    } )
    .catch( error => { console.error( error ); } );
</script>
<?php include 'includes/footer.php'; ?>
