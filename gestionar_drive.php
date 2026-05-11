<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirAdministrador();

// ─── Procesar acciones POST ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $titulo         = trim($_POST['titulo']);
        $descripcion    = trim($_POST['descripcion'] ?? '');
        $url_drive      = trim($_POST['url_drive']);
        $tipo_documento = $_POST['tipo_documento'] ?? 'general';
        $tipo_archivo   = $_POST['tipo_archivo'] ?? 'docs';
        $rol_asignado   = $_POST['rol_asignado'];
        $fecha_limite   = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;

        try {
            $stmt = $conn->prepare("INSERT INTO drive_documentos (titulo, descripcion, url_drive, tipo_documento, tipo_archivo, rol_asignado, fecha_limite, asignado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titulo, $descripcion, $url_drive, $tipo_documento, $tipo_archivo, $rol_asignado, $fecha_limite, $_SESSION['usuario_id']]);
            $success = "Documento de Drive agregado y asignado al rol: " . obtenerRolLabel($rol_asignado);
        } catch(Exception $e) {
            $error = "Error al agregar documento: " . $e->getMessage();
        }

    } elseif ($accion === 'eliminar') {
        $id = intval($_POST['id']);
        try {
            $conn->prepare("DELETE FROM usuario_drive_estado WHERE drive_doc_id = ?")->execute([$id]);
            $conn->prepare("DELETE FROM drive_documentos WHERE id = ?")->execute([$id]);
            $success = "Documento eliminado correctamente";
        } catch(Exception $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }

    } elseif ($accion === 'toggle_activo') {
        $id = intval($_POST['id']);
        try {
            $conn->prepare("UPDATE drive_documentos SET activo = NOT activo WHERE id = ?")->execute([$id]);
            $success = "Estado actualizado";
        } catch(Exception $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// ─── Filtro por rol ────────────────────────────────────────
$filtroRol = $_GET['rol'] ?? '';

// ─── Obtener documentos de Drive ───────────────────────────
$sql = "SELECT d.*, u.nombre AS admin_nombre FROM drive_documentos d LEFT JOIN usuarios u ON d.asignado_por = u.id";
$params = [];
if (!empty($filtroRol)) {
    $sql .= " WHERE d.rol_asignado = ?";
    $params[] = $filtroRol;
}
$sql .= " ORDER BY d.fecha_creacion DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$documentosDrive = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Estadísticas por rol ──────────────────────────────────
$stmtStats = $conn->query("SELECT rol_asignado, COUNT(*) as total FROM drive_documentos WHERE activo = 1 GROUP BY rol_asignado");
$statsPorRol = [];
while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
    $statsPorRol[$row['rol_asignado']] = $row['total'];
}

// ─── Conteo de estado por documento ────────────────────────
$stmtEstados = $conn->query("
    SELECT dd.id AS doc_id,
           SUM(CASE WHEN ude.estado = 'completado' THEN 1 ELSE 0 END) AS completados,
           COUNT(ude.id) AS total_estados
    FROM drive_documentos dd
    LEFT JOIN usuario_drive_estado ude ON ude.drive_doc_id = dd.id
    GROUP BY dd.id
");
$estadosPorDoc = [];
while ($row = $stmtEstados->fetch(PDO::FETCH_ASSOC)) {
    $estadosPorDoc[$row['doc_id']] = $row;
}

$roles = [
    'coordinador_sede'  => 'Coordinador de Sede',
    'decano'            => 'Decano',
    'docente_tc'        => 'Docente Tiempo Completo',
    'docente_catedra'   => 'Docente de Cátedra',
];

$tiposDocumento = ['Syllabus', 'Plan de Clase', 'Informe', 'Acta', 'Formato', 'Plantilla', 'Otro'];
?>
<?php 
$page_title = "Gestionar Drive - Universitaria de Colombia";
include 'includes/header.php'; 
?>
<style>
    :root { --primary: var(--primary-blue); --bg: var(--bg-light); }
    .hero-banner { background: var(--primary-blue); color: white; padding: 40px 0; border-bottom: 4px solid var(--accent-red); margin-bottom: 30px; }
</style>
<div class="hero-banner shadow-sm text-center">
    <div class="container">
        <h1 class="fw-bold"><i class="fab fa-google-drive me-2"></i>Gestión de Documentos Drive</h1>
        <p class="lead opacity-75">Panel de asignación y seguimiento de archivos en la nube</p>
        
        <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
            <?php foreach ($statsPorRol as $rol => $total): ?>
                <span class="badge bg-white text-dark p-2 border">
                    <strong><?php echo $total; ?></strong> <?php echo obtenerRolLabel($rol); ?>
                </span>
            <?php endforeach; ?>
        </div>
        
        <button class="btn btn-light fw-bold mt-4" data-bs-toggle="modal" data-bs-target="#modalAgregar">
            <i class="fas fa-plus me-2"></i>Nuevo Documento
        </button>
    </div>
</div>

<div class="container py-4">

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Guía de Integración -->
    <div class="card card-custom mb-4" style="border-left: 5px solid #10b981;">
        <div class="card-body">
            <h5 class="card-title text-success"><i class="fas fa-lightbulb me-2"></i>Guía de Integración (Opción A)</h5>
            <p class="small text-muted mb-3">Sigue estos pasos para añadir tus documentos de Google Drive correctamente:</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <div class="badge bg-success rounded-circle" style="width:24px; height:24px;">1</div>
                        <p class="small">En Google Drive: clic derecho en el archivo > <strong>Compartir</strong>.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <div class="badge bg-success rounded-circle" style="width:24px; height:24px;">2</div>
                        <p class="small">Cambia acceso a: <strong>"Cualquier persona con el enlace puede editar"</strong>.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <div class="badge bg-success rounded-circle" style="width:24px; height:24px;">3</div>
                        <p class="small">Copia el enlace y pégalo aquí abajo en <strong>"Agregar Documento Drive"</strong>.</p>
                    </div>
                </div>
            </div>
            <div class="alert alert-info py-2 px-3 mt-2 mb-0" style="font-size: 0.85rem;">
                <i class="fas fa-info-circle me-1"></i> <strong>Tip:</strong> He precargado algunos formatos reales de tu carpeta <strong>"HORA CATEDRA"</strong> para que veas cómo queda.
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?rol=" class="btn btn-outline-secondary filter-btn <?php echo empty($filtroRol) ? 'active' : ''; ?>">Todos</a>
            <?php foreach ($roles as $key => $label): ?>
                <a href="?rol=<?php echo $key; ?>" class="btn btn-outline-secondary filter-btn <?php echo $filtroRol === $key ? 'active' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#agregarModal">
            <i class="fas fa-plus me-1"></i> Agregar Documento Drive
        </button>
    </div>

    <!-- Barra de búsqueda -->
    <div class="card border-0 shadow-sm mb-3 p-3" style="background:#f8faff;border-radius:12px;">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted"><i class="fas fa-search me-1"></i>Buscar documento</label>
                <input type="text" id="filtroDriveTexto" class="form-control form-control-sm" placeholder="Título o descripción..." oninput="filtrarDrive()">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted"><i class="fas fa-tag me-1"></i>Tipo de Documento</label>
                <select id="filtroDriveTipo" class="form-select form-select-sm" onchange="filtrarDrive()">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tiposDocumento as $tipo): ?>
                        <option value="<?php echo strtolower($tipo); ?>"><?php echo $tipo; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted"><i class="fas fa-toggle-on me-1"></i>Estado</label>
                <select id="filtroDriveEstado" class="form-select form-select-sm" onchange="filtrarDrive()">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-outline-secondary w-100" onclick="limpiarDrive()" title="Limpiar"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="mt-2"><small id="contadorDrive" class="text-muted"></small></div>
    </div>

    <!-- Tabla de documentos Drive -->
    <div class="card-custom overflow-hidden">
        <?php if (empty($documentosDrive)): ?>
            <div class="text-center py-5">
                <i class="fab fa-google-drive fa-3x text-muted mb-3 d-block"></i>
                <h5 class="text-muted">No hay documentos de Drive registrados</h5>
                <p class="text-muted small">Haz clic en "Agregar Documento Drive" para comenzar</p>
            </div>
        <?php else: ?>
        <table class="table table-drive mb-0">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Tipo</th>
                    <th>Rol Asignado</th>
                    <th>Fecha Límite</th>
                    <th>Progreso</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documentosDrive as $doc):
                    $iconClass = 'other'; $iconIcon = 'fa-file';
                    switch ($doc['tipo_archivo']) {
                        case 'docs':   $iconClass = 'docs';   $iconIcon = 'fa-file-word'; break;
                        case 'sheets': $iconClass = 'sheets'; $iconIcon = 'fa-file-excel'; break;
                        case 'slides': $iconClass = 'slides'; $iconIcon = 'fa-file-powerpoint'; break;
                        case 'pdf':    $iconClass = 'pdf';    $iconIcon = 'fa-file-pdf'; break;
                    }
                    $est = $estadosPorDoc[$doc['id']] ?? ['completados' => 0, 'total_estados' => 0];
                ?>
                <tr data-titulo="<?php echo strtolower(htmlspecialchars($doc['titulo'])); ?>"
                    data-tipo="<?php echo strtolower(htmlspecialchars($doc['tipo_documento'])); ?>"
                    data-estado="<?php echo $doc['activo'] ? 'activo' : 'inactivo'; ?>">
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="drive-icon <?php echo $iconClass; ?>"><i class="fas <?php echo $iconIcon; ?>"></i></div>
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($doc['titulo']); ?></div>
                                <?php if (!empty($doc['descripcion'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars(mb_substr($doc['descripcion'], 0, 60)); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($doc['tipo_documento']); ?></span></td>
                    <td><span class="badge <?php echo obtenerRolBadgeClass($doc['rol_asignado']); ?>"><?php echo obtenerRolLabel($doc['rol_asignado']); ?></span></td>
                    <td class="small">
                        <?php if ($doc['fecha_limite']): ?>
                            <?php
                            $hoy = new DateTime();
                            $limite = new DateTime($doc['fecha_limite']);
                            $vencido = $limite < $hoy;
                            ?>
                            <span class="<?php echo $vencido ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                <i class="fas fa-calendar-alt me-1"></i><?php echo date('d/m/Y', strtotime($doc['fecha_limite'])); ?>
                                <?php if ($vencido): ?> <small>(Vencido)</small><?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <span class="text-success fw-bold"><?php echo $est['completados']; ?></span>
                        <span class="text-muted">/ <?php echo $est['total_estados']; ?> completados</span>
                    </td>
                    <td>
                        <?php if ($doc['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?php echo htmlspecialchars($doc['url_drive']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Abrir en Drive">
                                <i class="fab fa-google-drive"></i>
                            </a>
                            <a href="ver_entregas_drive.php?doc_id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-success" title="Ver entregas recibidas">
                                <i class="fas fa-inbox"></i>
                            </a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="<?php echo $doc['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                    <i class="fas <?php echo $doc['activo'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este documento?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Agregar Documento -->
<div class="modal fade" id="agregarModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;">
                <h5 class="modal-title"><i class="fab fa-google-drive me-2"></i> Agregar Documento de Google Drive</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Título del Documento *</label>
                        <input type="text" class="form-control" name="titulo" required placeholder="Ej: Syllabus Matemáticas I">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enlace de Google Drive *</label>
                        <input type="url" class="form-control" name="url_drive" required placeholder="https://docs.google.com/document/d/...">
                        <div class="form-text"><i class="fas fa-info-circle"></i> Pega aquí el enlace completo del documento de Google Drive</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="2" placeholder="Descripción opcional del documento"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tipo de Documento</label>
                            <select class="form-select" name="tipo_documento">
                                <?php foreach ($tiposDocumento as $tipo): ?>
                                    <option value="<?php echo $tipo; ?>"><?php echo $tipo; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tipo de Archivo</label>
                            <select class="form-select" name="tipo_archivo">
                                <option value="docs">📄 Google Docs / Word</option>
                                <option value="sheets">📊 Google Sheets / Excel</option>
                                <option value="slides">📽️ Google Slides / PowerPoint</option>
                                <option value="pdf">📕 PDF</option>
                                <option value="other">📁 Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Fecha Límite</label>
                            <input type="date" class="form-control" name="fecha_limite">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Asignar al Rol *</label>
                        <select class="form-select" name="rol_asignado" required>
                            <option value="">— Seleccionar rol —</option>
                            <?php foreach ($roles as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><i class="fas fa-info-circle"></i> Todos los usuarios con este rol verán este documento en su panel</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Agregar Documento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
function filtrarDrive() {
    const texto  = document.getElementById('filtroDriveTexto').value.toLowerCase();
    const tipo   = document.getElementById('filtroDriveTipo').value.toLowerCase();
    const estado = document.getElementById('filtroDriveEstado').value;
    const filas  = document.querySelectorAll('tbody tr[data-titulo]');
    let vis = 0;
    filas.forEach(f => {
        const ok = (!texto  || (f.dataset.titulo||'').includes(texto))
                && (!tipo   || (f.dataset.tipo||'').includes(tipo))
                && (!estado || f.dataset.estado === estado);
        f.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    document.getElementById('contadorDrive').textContent = `Mostrando ${vis} de ${filas.length} documento(s)`;
}
function limpiarDrive() {
    ['filtroDriveTexto','filtroDriveTipo','filtroDriveEstado'].forEach(id => { document.getElementById(id).value=''; });
    filtrarDrive();
}
document.addEventListener('DOMContentLoaded', filtrarDrive);
</script>
