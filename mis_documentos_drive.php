<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirLogin();

$usuario_id  = $_SESSION['usuario_id'];
$usuario_rol = $_SESSION['rol'];

// ─── Obtener documentos de Drive asignados al rol del usuario ──
$stmt = $conn->prepare("
    SELECT 
        dd.*,
        ude.estado AS mi_estado,
        ude.fecha_apertura,
        ude.fecha_completado,
        ude.notas AS mi_nota,
        u.nombre AS admin_nombre
    FROM drive_documentos dd
    LEFT JOIN usuario_drive_estado ude 
           ON ude.drive_doc_id = dd.id AND ude.usuario_id = ?
    LEFT JOIN usuarios u ON dd.asignado_por = u.id
    WHERE dd.rol_asignado = ? AND dd.activo = 1
    ORDER BY dd.fecha_creacion DESC
");
$stmt->execute([$usuario_id, $usuario_rol]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Obtener última entrega por documento para este usuario ────
$stmtEntregas = $conn->prepare("
    SELECT drive_doc_id, nombre_original, fecha_entrega, tamanio_archivo, admin_feedback
    FROM drive_entregas
    WHERE usuario_id = ?
    ORDER BY fecha_entrega DESC
");
$stmtEntregas->execute([$usuario_id]);
$entregasPorDoc = [];
while ($e = $stmtEntregas->fetch(PDO::FETCH_ASSOC)) {
    // Solo guardamos la más reciente por documento
    if (!isset($entregasPorDoc[$e['drive_doc_id']])) {
        $entregasPorDoc[$e['drive_doc_id']] = $e;
    }
}

// ─── Estadísticas rápidas ──────────────────────────────────
$totalDocs    = count($documentos);
$pendientes   = 0;
$enProgreso   = 0;
$completados  = 0;

foreach ($documentos as $doc) {
    $estado = $doc['mi_estado'] ?? 'pendiente';
    if ($estado === 'completado')  $completados++;
    elseif ($estado === 'en_progreso') $enProgreso++;
    else $pendientes++;
}

// ─── Filtro de estado ──────────────────────────────────────
$filtroEstado = $_GET['estado'] ?? '';

// ─── Helper para icono ─────────────────────────────────────
function driveIconData(string $tipo): array {
    $map = [
        'docs'   => ['class' => 'docs',   'icon' => 'fa-file-word',       'color' => '#2563eb', 'bg' => '#dbeafe'],
        'sheets' => ['class' => 'sheets', 'icon' => 'fa-file-excel',      'color' => '#059669', 'bg' => '#d1fae5'],
        'slides' => ['class' => 'slides', 'icon' => 'fa-file-powerpoint', 'color' => '#d97706', 'bg' => '#fef3c7'],
        'pdf'    => ['class' => 'pdf',    'icon' => 'fa-file-pdf',        'color' => '#dc2626', 'bg' => '#fee2e2'],
    ];
    return $map[$tipo] ?? ['class' => 'other', 'icon' => 'fa-file', 'color' => '#64748b', 'bg' => '#e2e8f0'];
}

function formatBytes(int $bytes): string {
    if ($bytes <= 0) return '—';
    $units = ['B','KB','MB','GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}
?>
<?php 
$page_title = "Mis Documentos Drive - Universitaria de Colombia";
include 'includes/header.php'; 
?>
<style>
    :root { --primary: var(--primary-blue); --bg: var(--bg-light); }
    .hero-banner { background: var(--primary-blue); color: white; padding: 40px 0; border-bottom: 4px solid var(--accent-red); margin-bottom: 30px; }
    .filter-pill {
        border-radius: 999px; font-size: .82rem; padding: 6px 18px;
        border: 1.5px solid #cbd5e1; background: #fff; color: #475569;
        transition: all .15s; cursor: pointer; text-decoration: none;
    }
    .filter-pill:hover { border-color: var(--primary-blue); color: var(--primary-blue); }
    .filter-pill.active { background: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }
    .doc-card {
        background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        padding: 24px; transition: transform .2s, box-shadow .2s;
        display: flex; flex-direction: column; height: 100%;
    }
    .doc-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
    .status-badge { border-radius: 8px; padding: 4px 12px; font-size: .78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .status-pendiente   { background: #fef3c7; color: #92400e; }
    .status-en_progreso { background: #dbeafe; color: #1e40af; }
    .status-completado  { background: #d1fae5; color: #065f46; }
    .btn-upload-entrega {
        font-size: .78rem; padding: 6px 10px; border-radius: 8px;
        border: 1.5px solid #10b981; color: #065f46; background: #ecfdf5;
        transition: all .15s; cursor: pointer; white-space: nowrap;
    }
    .btn-upload-entrega:hover { background: #10b981; color: #fff; border-color: #10b981; }
    .btn-upload-entrega.ya-entregado { background: #d1fae5; border-color: #059669; color: #065f46; }
    .entrega-info { font-size: .72rem; color: #64748b; margin-top: 6px; padding: 6px 10px;
        background: #f0fdf4; border-radius: 6px; border-left: 3px solid #10b981; }
</style>

<div class="hero-banner shadow-sm text-center">
    <div class="container">
        <h1 class="fw-bold"><i class="fab fa-google-drive me-2"></i>Mis Documentos Drive</h1>
        <p class="lead opacity-75">Archivos institucionales asignados a tu rol: <?php echo obtenerRolLabel(); ?></p>
        
        <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
            <div class="badge bg-white text-dark p-2 border"><strong><?php echo $totalDocs; ?></strong> Total</div>
            <div class="badge bg-white text-dark p-2 border"><strong><?php echo $pendientes; ?></strong> Pendientes</div>
            <div class="badge bg-white text-dark p-2 border"><strong><?php echo $enProgreso; ?></strong> En Progreso</div>
            <div class="badge bg-white text-dark p-2 border"><strong><?php echo $completados; ?></strong> Completados</div>
        </div>
    </div>
</div>


<div class="container py-4">

    <!-- Filtros -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="?" class="filter-pill <?php echo empty($filtroEstado) ? 'active' : ''; ?>">Todos (<?php echo $totalDocs; ?>)</a>
        <a href="?estado=pendiente" class="filter-pill <?php echo $filtroEstado === 'pendiente' ? 'active' : ''; ?>">🟡 Pendientes (<?php echo $pendientes; ?>)</a>
        <a href="?estado=en_progreso" class="filter-pill <?php echo $filtroEstado === 'en_progreso' ? 'active' : ''; ?>">🔵 En Progreso (<?php echo $enProgreso; ?>)</a>
        <a href="?estado=completado" class="filter-pill <?php echo $filtroEstado === 'completado' ? 'active' : ''; ?>">🟢 Completados (<?php echo $completados; ?>)</a>
    </div>

    <!-- Barra de búsqueda adicional -->
    <div class="card border-0 shadow-sm mb-4 p-3" style="background:#f8faff;border-radius:12px;">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted"><i class="fas fa-search me-1"></i>Buscar por título</label>
                <input type="text" id="filtroMisDrive" class="form-control form-control-sm" placeholder="Escribe para buscar..." oninput="filtrarMisDrive()">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted"><i class="fas fa-tag me-1"></i>Tipo de Archivo</label>
                <select id="filtroMisDriveTipo" class="form-select form-select-sm" onchange="filtrarMisDrive()">
                    <option value="">Todos</option>
                    <option value="docs">📄 Docs / Word</option>
                    <option value="sheets">📊 Sheets / Excel</option>
                    <option value="slides">📽 Slides / PPT</option>
                    <option value="pdf">📕 PDF</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-outline-secondary w-100" onclick="limpiarMisDrive()"><i class="fas fa-times"></i> Limpiar</button>
            </div>
        </div>
        <div class="mt-2"><small id="contadorMisDrive" class="text-muted"></small></div>
    </div>

    <?php if (empty($documentos)): ?>
        <div class="empty-state">
            <i class="fab fa-google-drive d-block mb-3"></i>
            <h5 class="text-muted">No tienes documentos de Drive asignados</h5>
            <p class="text-muted small">El administrador asignará documentos a tu rol. Regresa más tarde.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($documentos as $doc):
                $estado = $doc['mi_estado'] ?? 'pendiente';
                if (!empty($filtroEstado) && $estado !== $filtroEstado) continue;
                $iconData  = driveIconData($doc['tipo_archivo'] ?? 'other');
                $ultimaEntrega = $entregasPorDoc[$doc['id']] ?? null;
                $hoy    = new DateTime();
                $vencido = false;
                if ($doc['fecha_limite']) {
                    $limite  = new DateTime($doc['fecha_limite']);
                    $vencido = $limite < $hoy && $estado !== 'completado';
                }
            ?>
            <div class="col-md-6 col-xl-4 doc-card-wrapper"
                 data-titulo="<?php echo strtolower(htmlspecialchars($doc['titulo'])); ?>"
                 data-tipo="<?php echo htmlspecialchars($doc['tipo_archivo'] ?? 'other'); ?>">
                <div class="doc-card" id="card-<?php echo $doc['id']; ?>">
                    <!-- Header del card -->
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="doc-icon" style="background:<?php echo $iconData['bg']; ?>;color:<?php echo $iconData['color']; ?>">
                            <i class="fas <?php echo $iconData['icon']; ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($doc['titulo']); ?></h6>
                            <span class="badge bg-light text-dark border" style="font-size:.72rem;">
                                <?php echo htmlspecialchars($doc['tipo_documento']); ?>
                            </span>
                        </div>
                        <span class="status-badge status-<?php echo $estado; ?>">
                            <?php
                            $statusIcons = ['pendiente' => 'fa-clock', 'en_progreso' => 'fa-spinner', 'completado' => 'fa-check-circle'];
                            $statusLabels = ['pendiente' => 'Pendiente', 'en_progreso' => 'En Progreso', 'completado' => 'Completado'];
                            ?>
                            <i class="fas <?php echo $statusIcons[$estado]; ?>"></i>
                            <?php echo $statusLabels[$estado]; ?>
                        </span>
                    </div>

                    <!-- Descripción -->
                    <?php if (!empty($doc['descripcion'])): ?>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($doc['descripcion']); ?></p>
                    <?php endif; ?>

                    <!-- Fecha límite -->
                    <?php if ($doc['fecha_limite']): ?>
                        <div class="mb-3 small <?php echo $vencido ? 'deadline-warning' : 'deadline-ok'; ?>">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Fecha límite: <?php echo date('d/m/Y', strtotime($doc['fecha_limite'])); ?>
                            <?php if ($vencido): ?> — <strong>¡Vencido!</strong><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Última apertura -->
                    <?php if ($doc['fecha_apertura']): ?>
                        <div class="text-muted small mb-3">
                            <i class="fas fa-eye me-1"></i>
                            Último acceso: <?php echo date('d/m/Y H:i', strtotime($doc['fecha_apertura'])); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Info de última entrega -->
                    <?php if ($ultimaEntrega): ?>
                        <div class="entrega-info mb-2">
                            <i class="fas fa-check-circle text-success me-1"></i>
                            <strong>Entregado:</strong> <?php echo date('d/m/Y H:i', strtotime($ultimaEntrega['fecha_entrega'])); ?>
                            · <span class="text-muted"><?php echo htmlspecialchars(mb_substr($ultimaEntrega['nombre_original'], 0, 30)); ?></span>
                            · <?php echo formatBytes((int)$ultimaEntrega['tamanio_archivo']); ?>
                        </div>
                        <?php if (!empty($ultimaEntrega['admin_feedback'])): ?>
                            <div class="small mt-2 p-2 bg-light border-start border-3 border-danger rounded text-dark">
                                <strong class="text-danger"><i class="fas fa-user-shield me-1"></i> Feedback Admin:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($ultimaEntrega['admin_feedback'])); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Spacer -->
                    <div class="flex-grow-1"></div>

                    <!-- Acciones -->
                    <div class="d-flex gap-2 mt-3 align-items-center flex-wrap">
                        <?php 
                        $urlDrive = $doc['url_drive'];
                        if (strpos($urlDrive, 'docs.google.com') !== false && strpos($urlDrive, '/edit') !== false) {
                            $urlDrive = preg_replace('/\/edit.*?$/', '/copy', $urlDrive);
                        }
                        ?>
                        <a href="<?php echo htmlspecialchars($urlDrive); ?>" target="_blank"
                           class="btn btn-drive flex-grow-1"
                           onclick="registrarApertura(<?php echo $doc['id']; ?>)"
                           title="Abre una copia privada que no afectará el archivo original"
                           data-bs-toggle="tooltip">
                            <i class="fab fa-google-drive me-2"></i>Crear Copia Privada
                        </a>

                        <!-- NUEVO: Subir Archivo Editado -->
                        <button type="button"
                                class="btn-upload-entrega <?php echo $ultimaEntrega ? 'ya-entregado' : ''; ?>"
                                onclick="abrirModalEntrega(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['titulo'])); ?>')"
                                title="<?php echo $ultimaEntrega ? 'Ya entregaste este archivo. Haz clic para reemplazar la entrega' : 'Subir tu versión editada de este documento'; ?>">
                            <i class="fas <?php echo $ultimaEntrega ? 'fa-redo-alt' : 'fa-upload'; ?> me-1"></i>
                            <?php echo $ultimaEntrega ? 'Reemplazar' : 'Subir Editado'; ?>
                        </button>

                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Cambiar estado">
                                <i class="fas fa-tasks"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?php echo $doc['id']; ?>,'pendiente')"><i class="fas fa-clock text-warning me-2"></i>Pendiente</a></li>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?php echo $doc['id']; ?>,'en_progreso')"><i class="fas fa-spinner text-primary me-2"></i>En Progreso</a></li>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?php echo $doc['id']; ?>,'completado')"><i class="fas fa-check-circle text-success me-2"></i>Completado</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ══ MODAL: Subir Archivo Editado ══════════════════════════════════ -->
<div class="modal fade" id="modalEntrega" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#059669,#10b981);">
                <h5 class="modal-title fw-bold"><i class="fas fa-upload me-2"></i>Subir Archivo Editado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-1">Documento:</p>
                <p class="fw-bold mb-3" id="modalEntregaTitulo"></p>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Seleccionar archivo editado <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="entregaArchivo"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                    <div class="form-text"><i class="fas fa-info-circle me-1"></i>Formatos: PDF, Word, Excel, PowerPoint, TXT · Máximo 20MB</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Comentario <span class="text-muted fw-normal">(opcional)</span></label>
                    <textarea class="form-control" id="entregaComentario" rows="2"
                              placeholder="Ej: Versión final con observaciones incorporadas..."></textarea>
                </div>

                <div id="entregaAlert" class="d-none"></div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success fw-bold" id="btnConfirmarEntrega">
                    <i class="fas fa-paper-plane me-2"></i>Entregar Archivo
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
// ── Modal entrega ────────────────────────────────────────────────────
const modalEntrega = new bootstrap.Modal(document.getElementById('modalEntrega'));
let docIdEntrega   = null;

function abrirModalEntrega(docId, titulo) {
    docIdEntrega = docId;
    document.getElementById('modalEntregaTitulo').textContent = titulo;
    document.getElementById('entregaArchivo').value = '';
    document.getElementById('entregaComentario').value = '';
    document.getElementById('entregaAlert').className = 'd-none';
    document.getElementById('entregaAlert').innerHTML = '';
    modalEntrega.show();
}

// ── Filtros de Mis Documentos Drive ──────────────────────────────────
function filtrarMisDrive() {
    const texto = document.getElementById('filtroMisDrive').value.toLowerCase();
    const tipo  = document.getElementById('filtroMisDriveTipo').value;
    const cards = document.querySelectorAll('.doc-card-wrapper[data-titulo]');
    let vis = 0;
    cards.forEach(c => {
        const ok = (!texto || (c.dataset.titulo||'').includes(texto))
                && (!tipo  || c.dataset.tipo === tipo);
        c.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    const el = document.getElementById('contadorMisDrive');
    if (el) el.textContent = `Mostrando ${vis} de ${cards.length} documento(s)`;
}
function limpiarMisDrive() {
    ['filtroMisDrive','filtroMisDriveTipo'].forEach(id => { document.getElementById(id).value = ''; });
    filtrarMisDrive();
}
document.addEventListener('DOMContentLoaded', filtrarMisDrive);

document.getElementById('btnConfirmarEntrega').addEventListener('click', function () {
    const archivoInput = document.getElementById('entregaArchivo');
    const comentario   = document.getElementById('entregaComentario').value;
    const alertBox     = document.getElementById('entregaAlert');
    const btn          = this;

    if (!archivoInput.files[0]) {
        alertBox.className = 'alert alert-warning';
        alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Debes seleccionar un archivo.';
        return;
    }

    const formData = new FormData();
    formData.append('drive_doc_id', docIdEntrega);
    formData.append('archivo', archivoInput.files[0]);
    formData.append('comentario', comentario);

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Subiendo...';
    alertBox.className = 'd-none';

    fetch('api/api_subir_entrega_drive.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alertBox.className = 'alert alert-success';
            alertBox.innerHTML = `<i class="fas fa-check-circle me-1"></i>${data.message} · ${data.fecha_entrega}`;
            setTimeout(() => { modalEntrega.hide(); location.reload(); }, 1800);
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.innerHTML = `<i class="fas fa-times-circle me-1"></i>${data.error}`;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Entregar Archivo';
        }
    })
    .catch(() => {
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="fas fa-times-circle me-1"></i>Error de conexión con el servidor.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Entregar Archivo';
    });
});

// ── Apertura y estado ────────────────────────────────────────────────
function registrarApertura(docId) {
    fetch('api/api_drive_estado.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=abrir&doc_id=' + docId
    });
}

function cambiarEstado(docId, nuevoEstado) {
    fetch('api/api_drive_estado.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=estado&doc_id=' + docId + '&estado=' + nuevoEstado
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) { location.reload(); }
        else { alert('Error al actualizar estado: ' + (data.error || 'desconocido')); }
    })
    .catch(() => alert('Error de conexión'));
}

// ── Tooltips ─────────────────────────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});
</script>
</body>
</html>
