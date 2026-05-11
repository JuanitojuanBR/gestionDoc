<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirAdministrador();

$drive_doc_id = intval($_GET['doc_id'] ?? 0);
$docDrive = null;

if ($drive_doc_id > 0) {
    // ─── Info del documento Drive específico ──────────────────────────────
    $stmtDoc = $conn->prepare("SELECT * FROM drive_documentos WHERE id = ?");
    $stmtDoc->execute([$drive_doc_id]);
    $docDrive = $stmtDoc->fetch(PDO::FETCH_ASSOC);
    
    if (!$docDrive) {
        header('Location: ver_entregas_drive.php');
        exit;
    }
} else {
    // ─── Lista global de documentos con entregas ─────────────────────────
    $stmtDocsList = $conn->query("
        SELECT 
            d.*,
            (SELECT COUNT(DISTINCT usuario_id) FROM drive_entregas WHERE drive_doc_id = d.id) AS cant_entregas,
            (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND rol = d.rol_asignado) AS total_esperados
        FROM drive_documentos d
        WHERE d.activo = 1
        ORDER BY d.fecha_limite DESC, d.titulo ASC
    ");
    $docsResumen = $stmtDocsList->fetchAll(PDO::FETCH_ASSOC);
}

// ─── LÓGICA ESPECÍFICA (Solo si seleccionamos un documento) ───────
if ($docDrive) {
    // ─── Total de usuarios activos con ese rol ─────────────────
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1 AND rol = ?");
    $stmtTotal->execute([$docDrive['rol_asignado']]);
    $totalUsuariosRol = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

    // ─── Todas las entregas de este documento (historial completo) ──
    $stmtEntregas = $conn->prepare("
        SELECT 
            de.*,
            u.nombre  AS usuario_nombre,
            u.email   AS usuario_email,
            u.rol     AS usuario_rol
        FROM drive_entregas de
        JOIN usuarios u ON de.usuario_id = u.id
        WHERE de.drive_doc_id = ?
        ORDER BY de.fecha_entrega DESC
    ");
    $stmtEntregas->execute([$drive_doc_id]);
    $entregas = $stmtEntregas->fetchAll(PDO::FETCH_ASSOC);

    // ─── Usuarios que ya entregaron (al menos una vez) ─────────
    $stmtEntregaron = $conn->prepare("
        SELECT DISTINCT usuario_id FROM drive_entregas WHERE drive_doc_id = ?
    ");
    $stmtEntregaron->execute([$drive_doc_id]);
    $idsEntregaron = array_column($stmtEntregaron->fetchAll(PDO::FETCH_ASSOC), 'usuario_id');
    $cantEntregaron = count($idsEntregaron);
    $pctEntrega = $totalUsuariosRol > 0 ? round(($cantEntregaron / $totalUsuariosRol) * 100) : 0;

    // ─── Usuarios del rol que AÚN NO han entregado ─────────────
    $stmtFaltantes = $conn->prepare("
        SELECT id, nombre, email FROM usuarios
        WHERE activo = 1 AND rol = ?
        AND id NOT IN (SELECT DISTINCT usuario_id FROM drive_entregas WHERE drive_doc_id = ?)
        ORDER BY nombre ASC
    ");
    $stmtFaltantes->execute([$docDrive['rol_asignado'], $drive_doc_id]);
    $faltantes = $stmtFaltantes->fetchAll(PDO::FETCH_ASSOC);
}

function formatBytes(int $b): string {
    if ($b <= 0) return '—';
    $u = ['B','KB','MB','GB'];
    $i = floor(log($b, 1024));
    return round($b / pow(1024, $i), 1) . ' ' . $u[$i];
}
?>
<?php
$page_title = $docDrive ? "Entregas: " . htmlspecialchars($docDrive['titulo']) : "Bandeja de Entregas Drive";
include 'includes/header.php';
?>
<style>
    .hero-banner { background: var(--primary-blue); color: white; padding: 35px 0; border-bottom: 4px solid var(--accent-red); margin-bottom: 30px; }
    .stat-pill { background: rgba(255,255,255,.15); border-radius: 50px; padding: 8px 20px; display: inline-flex; align-items: center; gap: 8px; font-size: .9rem; }
    .entrega-row { background: #fff; border-radius: 10px; margin-bottom: 10px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,.05); transition: box-shadow .15s; }
    .entrega-row:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
    .avatar-sm { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size:.85rem; }
    .faltante-row { background: #fff7ed; border-left: 3px solid #f97316; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; }
    .version-badge { font-size:.68rem; background:#e0f2fe; color:#0369a1; border-radius:4px; padding:2px 7px; font-weight:600; }
    .card-doc { border-radius: 16px; transition: transform .2s, box-shadow .2s; cursor: pointer; }
    .card-doc:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,.1) !important; }
</style>

<div class="hero-banner shadow-sm">
    <div class="container">
        <?php if ($docDrive): ?>
            <a href="ver_entregas_drive.php" class="btn btn-sm btn-outline-light mb-3">
                <i class="fas fa-arrow-left me-1"></i>Volver al Resumen
            </a>
            <h1 class="fw-bold mb-1"><i class="fas fa-inbox me-2"></i>Entregas Recibidas</h1>
            <p class="lead opacity-75 mb-0"><?php echo htmlspecialchars($docDrive['titulo']); ?></p>
        <?php else: ?>
            <h1 class="fw-bold mb-1"><i class="fas fa-inbox me-2"></i>Bandeja de Entregas</h1>
            <p class="lead opacity-75 mb-0">Resumen global de cumplimiento por documento Drive</p>
        <?php endif; ?>
    </div>
</div>

<div class="container pb-5">
    <?php if ($docDrive): ?>
    <div class="row g-4">
        <!-- ══ VISTA ESPECÍFICA ══════════════════════════════════════ -->
        <div class="col-lg-8">
            <h5 class="fw-bold mb-3"><i class="fas fa-history me-2 text-primary"></i>Historial de Entregas</h5>

        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-3 p-3" style="background:#f8faff;border-radius:12px;">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-search me-1"></i>Buscar por nombre de usuario</label>
                    <input type="text" id="filtroEntregaUser" class="form-control form-control-sm" placeholder="Escribe el nombre..." oninput="filtrarEntregas()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-calendar me-1"></i>Desde</label>
                    <input type="date" id="filtroEntregaDesde" class="form-control form-control-sm" onchange="filtrarEntregas()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Hasta</label>
                    <input type="date" id="filtroEntregaHasta" class="form-control form-control-sm" onchange="filtrarEntregas()">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="limpiarEntregas()" title="Limpiar"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="mt-2"><small id="contadorEntregas" class="text-muted"></small></div>
        </div>

            <?php if (empty($entregas)): ?>
                <div class="text-center py-5 bg-white rounded-3 shadow-sm border">
                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                    <h6 class="text-muted">Aún no hay entregas para este documento.</h6>
                </div>
            <?php else:
                $conteoVersiones = [];
                $entregasInvertidas = array_reverse($entregas);
                foreach ($entregasInvertidas as $e) { $uid = $e['usuario_id']; $conteoVersiones[$uid] = ($conteoVersiones[$uid] ?? 0) + 1; }
                $conteoActual = [];
                foreach ($entregas as $e):
                    $uid = $e['usuario_id'];
                    $vNum = $conteoVersiones[$uid];
                    $conteoActual[$uid] = ($conteoActual[$uid] ?? $vNum + 1) - 1;
                    $iniciales = strtoupper(mb_substr($e['usuario_nombre'], 0, 1));
            ?>
            <div class="entrega-row border" data-usuario="<?php echo strtolower(htmlspecialchars($e['usuario_nombre'])); ?>"
                data-fecha="<?php echo date('Y-m-d', strtotime($e['fecha_entrega'])); ?>">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm bg-primary text-white flex-shrink-0"><?php echo $iniciales; ?></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold"><?php echo htmlspecialchars($e['usuario_nombre']); ?></span>
                            <span class="version-badge">v<?php echo $conteoActual[$uid]; ?></span>
                            <?php if ($conteoActual[$uid] == $conteoVersiones[$uid]): ?>
                                <span class="badge bg-success" style="font-size:.65rem;">Actual</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small mt-1">
                            <i class="fas fa-file-download me-1"></i><?php echo htmlspecialchars($e['nombre_original']); ?> · <?php echo formatBytes((int)$e['tamanio_archivo']); ?>
                        </div>
                        <?php if (!empty($e['comentario'])): ?>
                            <div class="small mt-1 fst-italic text-secondary"><i class="fas fa-comment me-1"></i>"<?php echo htmlspecialchars($e['comentario']); ?>"</div>
                        <?php endif; ?>
                        
                        <?php if (!empty($e['admin_feedback'])): ?>
                            <div class="small mt-2 p-2 bg-light border-start border-3 border-danger rounded text-dark">
                                <strong class="text-danger"><i class="fas fa-user-shield me-1"></i> Feedback Admin:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($e['admin_feedback'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="abrirModalFeedback(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars(addslashes($e['admin_feedback'] ?? '')); ?>')">
                                <i class="fas fa-reply"></i> Responder / Feedback
                            </button>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($e['fecha_entrega'])); ?></div>
                        <a href="<?php echo htmlspecialchars($e['ruta_archivo']); ?>" download class="btn btn-sm btn-outline-primary mt-1"><i class="fas fa-download"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
                <div class="card-body text-center p-4">
                    <h6 class="fw-bold text-muted text-uppercase small ls-1 mb-3">Progreso de Entrega</h6>
                    <div style="font-size:3rem;font-weight:800;color:<?php echo $pctEntrega >= 80 ? '#059669' : ($pctEntrega >= 50 ? '#d97706' : '#dc2626'); ?>">
                        <?php echo $pctEntrega; ?>%
                    </div>
                    <div class="progress mt-2" style="height:8px;border-radius:99px;"><div class="progress-bar <?php echo $pctEntrega >= 80 ? 'bg-success' : ($pctEntrega >= 50 ? 'bg-warning' : 'bg-danger'); ?>" style="width:<?php echo $pctEntrega; ?>%"></div></div>
                    <p class="small text-muted mt-2 mb-0"><?php echo $cantEntregaron; ?> de <?php echo $totalUsuariosRol; ?> entregaron</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm" style="border-radius:14px;">
                <div class="card-header bg-warning bg-opacity-10 border-0 fw-bold">Pendientes (<?php echo count($faltantes); ?>)</div>
                <div class="card-body p-3">
                    <?php if (empty($faltantes)): ?>
                        <div class="text-center py-2"><span class="small text-success fw-semibold">¡Completado!</span></div>
                    <?php else: ?>
                        <?php foreach ($faltantes as $f): ?>
                            <div class="faltante-row">
                                <div class="fw-semibold small"><?php echo htmlspecialchars($f['nombre']); ?></div>
                                <div class="text-muted" style="font-size:.7rem;"><?php echo htmlspecialchars($f['email']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- ══ VISTA GLOBAL ══════════════════════════════════════════ -->
    <div class="row g-4">
        <?php if (empty($docsResumen)): ?>
            <div class="col-12 text-center py-5 bg-white rounded-3 shadow-sm border">
                <i class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></i>
                <h6 class="text-muted">No hay documentos Drive activos asignados en este momento.</h6>
                <a href="gestionar_drive.php" class="btn btn-sm btn-primary mt-3">Gestionar Documentos</a>
            </div>
        <?php else: ?>
            <?php foreach ($docsResumen as $d): 
                $pct = $d['total_esperados'] > 0 ? round(($d['cant_entregas'] / $d['total_esperados']) * 100) : 0;
                $color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm card-doc" onclick="location.href='ver_entregas_drive.php?doc_id=<?php echo $d['id']; ?>'">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="badge <?php echo obtenerRolBadgeClass($d['rol_asignado']); ?>"><?php echo obtenerRolLabel($d['rol_asignado']); ?></span>
                            <span class="small text-muted"><i class="fas fa-layer-group me-1"></i><?php echo $d['cant_entregas']; ?> / <?php echo $d['total_esperados']; ?></span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1 text-truncate"><?php echo htmlspecialchars($d['titulo']); ?></h5>
                        <p class="small text-muted mb-4 text-truncate"><?php echo htmlspecialchars($d['tipo_documento']); ?></p>
                        
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-semibold">Cumplimiento</span>
                            <span class="small fw-bold text-<?php echo $color; ?>"><?php echo $pct; ?>%</span>
                        </div>
                        <div class="progress" style="height:6px; border-radius:10px;">
                            <div class="progress-bar bg-<?php echo $color; ?>" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Feedback -->
<?php if ($docDrive): ?>
<div class="modal fade" id="feedbackModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="api/guardar_feedback_entrega.php" method="POST">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-comment-dots text-primary me-2"></i>Retroalimentación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="entrega_id" id="feedback_entrega_id">
          <input type="hidden" name="doc_id" value="<?php echo $drive_doc_id; ?>">
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Comentario del Administrador:</label>
            <textarea class="form-control" name="admin_feedback" id="feedback_textarea" rows="4" placeholder="Escribe aquí observaciones, correcciones o aprobación..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function abrirModalFeedback(id, feedbackActual) {
    document.getElementById('feedback_entrega_id').value = id;
    document.getElementById('feedback_textarea').value = feedbackActual;
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

function filtrarEntregas() {
    const texto = document.getElementById('filtroEntregaUser')?.value.toLowerCase() || '';
    const desde = document.getElementById('filtroEntregaDesde')?.value || '';
    const hasta = document.getElementById('filtroEntregaHasta')?.value || '';
    const filas = document.querySelectorAll('.entrega-row[data-usuario]');
    let vis = 0;
    filas.forEach(f => {
        const ok = (!texto || (f.dataset.usuario||'').includes(texto))
                && (!desde || f.dataset.fecha >= desde)
                && (!hasta || f.dataset.fecha <= hasta);
        f.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    const contador = document.getElementById('contadorEntregas');
    if (contador) contador.textContent = `Mostrando ${vis} de ${filas.length} entrega(s)`;
}
function limpiarEntregas() {
    ['filtroEntregaUser','filtroEntregaDesde','filtroEntregaHasta'].forEach(id => {
        const el = document.getElementById(id); if (el) el.value = '';
    });
    filtrarEntregas();
}
document.addEventListener('DOMContentLoaded', filtrarEntregas);
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
