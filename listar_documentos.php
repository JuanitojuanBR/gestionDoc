<?php
require_once 'config/sesion.php';
requerirLogin();
require_once 'config/database.php';

// Eliminar documento si se solicita
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    try {
        $stmt = $conn->prepare("DELETE FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: listar_documentos.php");
        exit();
    } catch(PDOException $e) {
        $error = "Error al eliminar el documento: " . $e->getMessage();
    }
}

// Obtener los documentos del usuario
try {
    $stmt = $conn->prepare("SELECT * FROM documentos WHERE usuario_id = ? ORDER BY fecha_creacion DESC");
    $stmt->execute([$_SESSION['usuario_id']]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error al obtener los documentos: " . $e->getMessage();
}
?>
<?php 
$page_title = "Mis Documentos - Universitaria de Colombia";
include 'includes/header.php'; 
?>

<div class="hero-banner text-center py-4">
    <div class="container">
        <h1 class="fw-bold"><i class="fas fa-folder-open me-2"></i>Repositorio de Documentos</h1>
        <p class="lead opacity-75">Consulta y administración de archivos digitales institucionales</p>
    </div>
</div>


    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Lista de Documentos</h2>
                    <a href="crear_documento.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo Documento
                    </a>
                </div>

                <!-- Barra de Filtros -->
                <div class="card border-0 shadow-sm mb-4 p-3" style="background:#f8faff;border-radius:12px;">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted"><i class="fas fa-search me-1"></i>Buscar por título</label>
                            <input type="text" id="filtroTexto" class="form-control form-control-sm" placeholder="Escribe para buscar..." oninput="aplicarFiltros()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted"><i class="fas fa-tag me-1"></i>Tipo de Documento</label>
                            <select id="filtroTipo" class="form-select form-select-sm" onchange="aplicarFiltros()">
                                <option value="">Todos los tipos</option>
                                <option value="reporte">Reporte</option>
                                <option value="carta">Carta</option>
                                <option value="factura">Factura</option>
                                <option value="archivo_subido">Archivo Subido</option>
                                <option value="documento_convertido">Doc. Convertido</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted"><i class="fas fa-calendar me-1"></i>Desde</label>
                            <input type="date" id="filtroDesde" class="form-control form-control-sm" onchange="aplicarFiltros()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Hasta</label>
                            <input type="date" id="filtroHasta" class="form-control form-control-sm" onchange="aplicarFiltros()">
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-sm btn-outline-secondary w-100" onclick="limpiarFiltros()" title="Limpiar filtros"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small id="contadorResultados" class="text-muted"></small>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (empty($documentos)): ?>
                    <div class="alert alert-info">No hay documentos disponibles.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Fecha de Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documentos as $documento): ?>
                                    <tr data-titulo="<?php echo htmlspecialchars(strtolower($documento['titulo'])); ?>"
                                        data-tipo="<?php echo htmlspecialchars($documento['tipo']); ?>"
                                        data-fecha="<?php echo date('Y-m-d', strtotime($documento['fecha_creacion'])); ?>">
                                        <td><?php echo htmlspecialchars($documento['titulo']); ?></td>
                                        <td>
                                            <?php 
                                            // Mostrar etiqueta visual según el tipo
                                            if ($documento['tipo'] === 'archivo_subido' || (isset($documento['es_archivo_subido']) && $documento['es_archivo_subido'] == 1)) {
                                                echo '<span class="badge bg-success"><i class="fas fa-file-upload"></i> Archivo Subido</span>';
                                            } elseif ($documento['tipo'] === 'documento_convertido') {
                                                echo '<span class="badge bg-secondary"><i class="fas fa-file-import"></i> Documento Convertido</span>';
                                            } else {
                                                $tipos = [
                                                    'reporte' => '<span class="badge bg-info"><i class="fas fa-file-alt"></i> Reporte</span>',
                                                    'carta' => '<span class="badge bg-primary"><i class="fas fa-envelope"></i> Carta</span>',
                                                    'factura' => '<span class="badge bg-warning"><i class="fas fa-file-invoice"></i> Factura</span>'
                                                ];
                                                echo $tipos[$documento['tipo']] ?? '<span class="badge bg-dark">' . htmlspecialchars($documento['tipo']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($documento['fecha_creacion'])); ?></td>
                                        <td>
                                            <?php if ($documento['tipo'] === 'archivo_subido' || (isset($documento['es_archivo_subido']) && $documento['es_archivo_subido'] == 1)): ?>
                                            <!-- Botones para archivos subidos -->
                                            
                                                <a href="generar_pdf.php?id=<?php echo $documento['id']; ?>" class="btn btn-primary btn-sm" target="_blank" title="Descargar archivo original">
                                                    <i class="fas fa-download"></i> Descargar
                                                </a>
                                                <a href="listar_documentos.php?eliminar=<?php echo $documento['id']; ?>" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                title="Eliminar archivo">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php else: ?>
                                                <!-- Botones para documentos creados manualmente, convertidos, reportes, cartas y facturas -->
                                                <a href="generar_pdf.php?id=<?php echo $documento['id']; ?>" class="btn btn-primary btn-sm" target="_blank" title="Generar y ver PDF">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </a>
                                                <a href="editar_documento.php?id=<?php echo $documento['id']; ?>" class="btn btn-warning btn-sm" title="Editar documento">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="clonar_documento.php?id=<?php echo $documento['id']; ?>" class="btn btn-info btn-sm" title="Clonar documento">
                                                    <i class="fas fa-copy"></i> Clonar
                                                </a>
                                                <a href="listar_documentos.php?eliminar=<?php echo $documento['id']; ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('¿Está seguro de eliminar este documento?')"
                                                   title="Eliminar documento">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
function aplicarFiltros() {
    const texto = document.getElementById('filtroTexto').value.toLowerCase();
    const tipo  = document.getElementById('filtroTipo').value.toLowerCase();
    const desde = document.getElementById('filtroDesde').value;
    const hasta = document.getElementById('filtroHasta').value;
    const filas = document.querySelectorAll('tbody tr[data-tipo]');
    let visibles = 0;

    filas.forEach(fila => {
        const titulo   = (fila.dataset.titulo || '').toLowerCase();
        const tipoDoc  = (fila.dataset.tipo   || '').toLowerCase();
        const fechaStr = fila.dataset.fecha || '';

        const coincideTexto = !texto || titulo.includes(texto);
        const coincideTipo  = !tipo  || tipoDoc.includes(tipo);

        let coincideFecha = true;
        if (desde && fechaStr < desde) coincideFecha = false;
        if (hasta && fechaStr > hasta) coincideFecha = false;

        const visible = coincideTexto && coincideTipo && coincideFecha;
        fila.style.display = visible ? '' : 'none';
        if (visible) visibles++;
    });
    document.getElementById('contadorResultados').textContent =
        `Mostrando ${visibles} de ${filas.length} documento(s)`;
}

function limpiarFiltros() {
    ['filtroTexto','filtroTipo','filtroDesde','filtroHasta'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    aplicarFiltros();
}

document.addEventListener('DOMContentLoaded', aplicarFiltros);
</script>
<?php include 'includes/footer.php'; ?>
