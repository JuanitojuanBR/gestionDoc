<?php
// IMPORTANTE: Cargar las clases ANTES de iniciar la sesión
require_once 'classes/DocumentoFactory.php';
require_once 'classes/DocumentoMemento.php';
require_once 'config/sesion.php';
requerirLogin();
require_once 'config/database.php';

// Limpiar la sesión si se está editando un nuevo documento
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    if (!isset($_SESSION['documento_id']) || $_SESSION['documento_id'] !== $id) {
        unset($_SESSION['documento_editor']);
        $_SESSION['documento_id'] = $id;
    }
}

// Inicializar el editor si no existe
if (!isset($_SESSION['documento_editor'])) {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($documento) {
            // Verificar si es un archivo subido
            if ($documento['tipo'] === 'archivo_subido' || (isset($documento['es_archivo_subido']) && $documento['es_archivo_subido'] == 1)) {
                $_SESSION['error'] = "No se pueden editar archivos subidos. Solo puedes descargarlos o eliminarlos.";
                header("Location: listar_documentos.php");
                exit();
            }

            $contenido = json_decode($documento['contenido'], true);

            if (!$contenido) {
                $_SESSION['error'] = "Error: El contenido del documento está corrupto.";
                header("Location: listar_documentos.php");
                exit();
            }

            $factory = DocumentoFactoryCreator::getFactory($documento['tipo']);
            $documentoObj = $factory->createDocumento(
                $contenido['titulo'],
                $contenido['contenido'],
                $contenido['encabezado'] ?? '',
                $contenido['pieDePagina'] ?? '',
                $contenido['datosAdicionales'] ?? []
            );
            $_SESSION['documento_editor'] = new DocumentoEditor($documentoObj);
        }
    }
}

// Manejar acciones de deshacer/rehacer/guardar
if (isset($_POST['accion']) && isset($_SESSION['documento_editor'])) {
    $editor = $_SESSION['documento_editor'];

    switch ($_POST['accion']) {
        case 'deshacer':
            if ($editor->deshacer()) {
                $_SESSION['documento_editor'] = $editor;
            }
            break;
        case 'rehacer':
            if ($editor->rehacer()) {
                $_SESSION['documento_editor'] = $editor;
            }
            break;
        case 'guardar':
            $documento = $editor->getDocumento();

            // Datos adicionales según el tipo
            $datosAdicionales = [];
            if ($documento instanceof Carta) {
                $datosAdicionales['destinatario'] = $_POST['destinatario'] ?? $documento->getDestinatario();
            } elseif ($documento instanceof Factura) {
                $datosAdicionales['numero_factura'] = $_POST['numero_factura'] ?? $documento->getNumeroFactura();
                $datosAdicionales['monto'] = $_POST['monto'] ?? $documento->getMonto();
            }

            $contenido = json_encode([
                'tipo'          => $documento->getTipo(),
                'titulo'        => $_POST['titulo']      ?? $documento->getTitulo(),
                'contenido'     => $_POST['contenido']   ?? $documento->getContenido(),
                'encabezado'    => $_POST['encabezado']  ?? $documento->getEncabezado(),
                'pieDePagina'   => $_POST['pie_pagina']  ?? $documento->getPieDePagina(),
                'datosAdicionales' => $datosAdicionales
            ]);

            $stmt = $conn->prepare("UPDATE documentos SET contenido = ?, titulo = ? WHERE id = ?");
            $stmt->execute([
                $contenido,
                $_POST['titulo'] ?? $documento->getTitulo(),
                $_SESSION['documento_id']
            ]);

            unset($_SESSION['documento_editor'], $_SESSION['documento_id']);

            header("Location: listar_documentos.php");
            exit();
    }
}

// Obtener el documento actual
$editor = $_SESSION['documento_editor'] ?? null;
$documento = $editor ? $editor->getDocumento() : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Documento - Sistema de Gestión de Documentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Gestión de Documentos</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="crear_documento.php">Crear Documento</a></li>
                <li class="nav-item"><a class="nav-link" href="listar_documentos.php">Listar Documentos</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['usuario_nombre']; ?>
                        <span class="badge bg-secondary"><?php echo ucfirst($_SESSION['rol']); ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (esAdministrador()): ?>
                            <li><a class="dropdown-item" href="gestionar_usuarios.php"><i class="fas fa-users"></i> Gestionar Usuarios</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Editar Documento</h2>

            <?php if ($documento): ?>
                <form method="POST" action="" id="documentoForm">
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título</label>
                        <input type="text" class="form-control" id="titulo" name="titulo"
                               value="<?php echo htmlspecialchars($documento->getTitulo()); ?>" required>
                    </div>

                    <?php if ($documento instanceof Carta): ?>
                        <div class="mb-3">
                            <label for="destinatario" class="form-label">Destinatario</label>
                            <input type="text" class="form-control" id="destinatario" name="destinatario"
                                   value="<?php echo htmlspecialchars($documento->getDestinatario()); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($documento instanceof Factura): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="numero_factura" class="form-label">Número de Factura</label>
                                <input type="text" class="form-control" id="numero_factura" name="numero_factura"
                                       value="<?php echo htmlspecialchars($documento->getNumeroFactura()); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="monto" class="form-label">Monto</label>
                                <input type="number" step="0.01" class="form-control" id="monto" name="monto"
                                       value="<?php echo $documento->getMonto(); ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="encabezado" class="form-label">Encabezado</label>
                        <input type="text" class="form-control" id="encabezado" name="encabezado"
                               value="<?php echo htmlspecialchars($documento->getEncabezado()); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="contenido" class="form-label">Contenido</label>
                        <textarea class="form-control" id="contenido" name="contenido" rows="10"
                                  onchange="guardarEstado()"><?php
                            echo htmlspecialchars($documento->getContenido());
                        ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="pie_pagina" class="form-label">Pie de Página</label>
                        <input type="text" class="form-control" id="pie_pagina" name="pie_pagina"
                               value="<?php echo htmlspecialchars($documento->getPieDePagina()); ?>">
                    </div>

                    <div class="btn-group mb-3">
                        <button type="button" class="btn btn-secondary" onclick="deshacer()"
                            <?php echo !$editor->puedeDeshacer() ? 'disabled' : ''; ?>>
                            <i class="fas fa-undo"></i> Deshacer
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="rehacer()"
                            <?php echo !$editor->puedeRehacer() ? 'disabled' : ''; ?>>
                            <i class="fas fa-redo"></i> Rehacer
                        </button>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" name="accion" value="guardar" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <a href="listar_documentos.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">Documento no encontrado</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Guardado de estado (si usas guardar_estado.php)
function guardarEstado() {
    fetch('api/guardar_estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            titulo: document.getElementById('titulo').value,
            contenido: document.getElementById('contenido').value,
            encabezado: document.getElementById('encabezado').value,
            pie_pagina: document.getElementById('pie_pagina').value,
            destinatario: document.getElementById('destinatario')?.value,
            numero_factura: document.getElementById('numero_factura')?.value,
            monto: document.getElementById('monto')?.value
        })
    });
}

function deshacer() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="accion" value="deshacer">';
    document.body.appendChild(form);
    form.submit();
}

function rehacer() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="accion" value="rehacer">';
    document.body.appendChild(form);
    form.submit();
}

// Guardar estado cada 30 segundos (opcional)
setInterval(guardarEstado, 30000);
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
</body>
</html>
