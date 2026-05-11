<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirAdministrador();

// ─── Parámetros del informe ────────────────────────────────
$mesActual  = date('m');
$anioActual = date('Y');

$mes  = isset($_GET['mes'])  ? intval($_GET['mes'])  : intval($mesActual);
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : intval($anioActual);

// Rango de fechas del mes seleccionado
$fechaInicio = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
$fechaFin    = date('Y-m-t 23:59:59', mktime(0, 0, 0, $mes, 1, $anio));

$nombresMeses = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];
$labelMes = $nombresMeses[$mes] . ' ' . $anio;

// ─── Estadísticas generales del mes ────────────────────────
$stmtTotalDocs = $conn->prepare("
    SELECT COUNT(*) as total FROM documentos 
    WHERE fecha_creacion BETWEEN ? AND ?
");
$stmtTotalDocs->execute([$fechaInicio, $fechaFin]);
$totalDocsMes = $stmtTotalDocs->fetch(PDO::FETCH_ASSOC)['total'];

$stmtUsuariosActivos = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1 AND rol != 'administrador'");
$totalProfesores = $stmtUsuariosActivos->fetch(PDO::FETCH_ASSOC)['total'];

$stmtUsuariosConDocs = $conn->prepare("
    SELECT COUNT(DISTINCT usuario_id) as total FROM documentos 
    WHERE fecha_creacion BETWEEN ? AND ? AND usuario_id IS NOT NULL
");
$stmtUsuariosConDocs->execute([$fechaInicio, $fechaFin]);
$profesoresQueEnviaron = $stmtUsuariosConDocs->fetch(PDO::FETCH_ASSOC)['total'];
$profesoresSinDocs     = $totalProfesores - $profesoresQueEnviaron;
$porcentajeCumplimiento = $totalProfesores > 0 ? round(($profesoresQueEnviaron / $totalProfesores) * 100) : 0;

// ─── Reporte por usuario (todos los profesores activos) ────
$stmtUsuarios = $conn->prepare("
    SELECT 
        u.id,
        u.nombre,
        u.email,
        u.rol,
        u.activo,
        COUNT(d.id)                                                         AS total_docs,
        GROUP_CONCAT(DISTINCT d.tipo ORDER BY d.tipo SEPARATOR ', ')        AS tipos_docs,
        MIN(d.fecha_creacion)                                               AS primer_envio,
        MAX(d.fecha_creacion)                                               AS ultimo_envio
    FROM usuarios u
    LEFT JOIN documentos d 
           ON d.usuario_id = u.id 
          AND d.fecha_creacion BETWEEN ? AND ?
    WHERE u.activo = 1
    ORDER BY u.rol DESC, total_docs DESC, u.nombre ASC
");
$stmtUsuarios->execute([$fechaInicio, $fechaFin]);
$usuariosReporte = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

// ─── Detalle de todos los documentos del mes ───────────────
$stmtDocsMes = $conn->prepare("
    SELECT 
        d.id,
        d.titulo,
        d.tipo,
        d.fecha_creacion,
        d.tamanio_archivo,
        d.nombre_archivo,
        u.nombre  AS usuario_nombre,
        u.email   AS usuario_email,
        u.rol     AS usuario_rol
    FROM documentos d
    LEFT JOIN usuarios u ON d.usuario_id = u.id
    WHERE d.fecha_creacion BETWEEN ? AND ?
    ORDER BY d.fecha_creacion DESC
");
$stmtDocsMes->execute([$fechaInicio, $fechaFin]);
$documentosMes = $stmtDocsMes->fetchAll(PDO::FETCH_ASSOC);

// ─── Helper: Etiqueta de tipo de documento ─────────────────
function badgeTipo(string $tipo): string {
    $map = [
        'archivo_subido'      => '<span class="badge bg-success"><i class="fas fa-file-upload"></i> Archivo Subido</span>',
        'documento_convertido'=> '<span class="badge bg-secondary"><i class="fas fa-file-import"></i> Convertido</span>',
        'reporte'             => '<span class="badge bg-info"><i class="fas fa-file-alt"></i> Reporte</span>',
        'carta'               => '<span class="badge bg-primary"><i class="fas fa-envelope"></i> Carta</span>',
        'factura'             => '<span class="badge bg-warning text-dark"><i class="fas fa-file-invoice"></i> Factura</span>',
    ];
    return $map[$tipo] ?? '<span class="badge bg-dark">' . htmlspecialchars($tipo) . '</span>';
}

function formatearBytes(int $bytes): string {
    if ($bytes <= 0) return '—';
    $sizes = ['B','KB','MB','GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}

// ─── Obtener configuración de correo ─────────────────────────
$stmtMail = $conn->query("SELECT * FROM config_envio_informes LIMIT 1");
$configMail = $stmtMail->fetch(PDO::FETCH_ASSOC) ?: [
    'correos_destino' => '',
    'envio_automatico' => 0,
    'intervalo_dias' => 2,
    'hora_envio' => '08:00:00',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_secure' => 'tls'
];
?>
<?php 
$page_title = "Informe Mensual - Universitaria de Colombia";
include 'includes/header.php'; 
?>
<style>
    /* Sobrescribimos algunas variables locales para que coincidan con el diseño global */
    :root {
        --primary: var(--primary-blue);
        --success: #059669;
        --danger: #dc2626;
        --warning: var(--accent-yellow);
    }
    .report-header { background: var(--primary-blue); color: white; padding: 40px 0; border-bottom: 4px solid var(--accent-red); margin-bottom: 30px; }
    .section-title { border-bottom: 3px solid var(--primary-blue); color: var(--primary-blue); }
    .stat-icon.purple { background: #e0f2fe; color: var(--primary-blue); }
</style>

<div class="report-header shadow-sm text-center">
    <div class="container">
        <h1 class="fw-bold mb-0"><i class="fas fa-chart-pie me-2"></i>Informe de Gestión Académica</h1>
        <p class="lead opacity-75">Periodo: <?php echo $labelMes; ?></p>
    </div>
</div>

<div class="container pb-5">

    <!-- ══ FILTRO DE MES ════════════════════════════════════ -->
    <div class="filter-card no-print mb-4">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold small text-muted text-uppercase">Mes</label>
                <select name="mes" id="mes" class="form-select">
                    <?php foreach ($nombresMeses as $num => $nombre): ?>
                        <option value="<?php echo $num; ?>" <?php echo $num == $mes ? 'selected' : ''; ?>>
                            <?php echo $nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted text-uppercase">Año</label>
                <select name="anio" id="anio" class="form-select">
                    <?php for ($y = intval(date('Y')); $y >= intval(date('Y')) - 4; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $anio ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Actualizar
                </button>
            </div>
            <?php if ($mes != intval($mesActual) || $anio != intval($anioActual)): ?>
            <div class="col-md-2">
                <a href="informe_mensual.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-undo me-1"></i> Hoy
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ══ TARJETAS DE ESTADÍSTICAS ═════════════════════════ -->

    <!-- ══ CONFIGURACIÓN DE ENVÍO POR CORREO ══════════════════ -->
    <div class="card no-print mb-5 shadow-sm border-0" style="border-radius: 16px; overflow: hidden;">
        <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0"><i class="fas fa-envelope-open-text me-2"></i>Configuración de Notificaciones por Correo</h5>
            <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConfig">
                <i class="fas fa-cog"></i> Configurar
            </button>
        </div>
        <div class="collapse" id="collapseConfig">
            <div class="card-body p-4">
                <form id="formEmailConfig">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Correos Destinatarios</label>
                            <input type="text" name="correos_destino" class="form-control" 
                                   placeholder="ejemplo1@mail.com, ejemplo2@mail.com"
                                   value="<?php echo htmlspecialchars($configMail['correos_destino']); ?>">
                            <div class="form-text">Separa múltiples correos con una coma.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">¿Envío automático?</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="envio_automatico" id="envio_auto" 
                                       <?php echo $configMail['envio_automatico'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="envio_auto">Activar automatización</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Frecuencia y Hora</label>
                            <div class="input-group">
                                <span class="input-group-text">Cada</span>
                                <input type="number" name="intervalo_dias" class="form-control" min="1" max="30"
                                       value="<?php echo $configMail['intervalo_dias']; ?>">
                                <span class="input-group-text">días</span>
                            </div>
                            <input type="time" name="hora_envio" class="form-control mt-2" 
                                   value="<?php echo substr($configMail['hora_envio'], 0, 5); ?>">
                        </div>
                        
                        <div class="col-12 mt-4 pt-3 border-top">
                            <h6 class="text-muted text-uppercase small ls-1 fw-bold mb-3">Servidor SMTP (Avanzado)</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" name="smtp_host" class="form-control form-control-sm" placeholder="Host (ej: smtp.gmail.com)" value="<?php echo htmlspecialchars($configMail['smtp_host']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="smtp_port" class="form-control form-control-sm" placeholder="Puerto" value="<?php echo $configMail['smtp_port']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="smtp_user" class="form-control form-control-sm" placeholder="Usuario/Email" value="<?php echo htmlspecialchars($configMail['smtp_user']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <input type="password" name="smtp_pass" class="form-control form-control-sm" placeholder="Contraseña SMTP" value="<?php echo htmlspecialchars($configMail['smtp_pass']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 mt-4 d-flex justify-content-between">
                            <button type="button" id="btnGuardarConfig" class="btn btn-primary d-flex align-items-center gap-2">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                            
                            <button type="button" id="btnEnviarAhora" class="btn btn-outline-success d-flex align-items-center gap-2 shadow-sm">
                                <i class="fas fa-paper-plane"></i> ENVIAR INFORME AHORA POR CORREO
                            </button>
                        </div>
                    </div>
                </form>
                
                <div id="emailAlert" class="alert d-none mt-3 mb-0"></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLA POR USUARIO/PROFESOR ═══════════════════════ -->
    <div class="mb-5">
        <span class="section-title">
            <i class="fas fa-users me-2"></i>Estado de Envíos por Profesor
        </span>

        <!-- Filtro de búsqueda para tabla de profesores -->
        <div class="card border-0 shadow-sm mb-3 p-3 no-print" style="background:#f8faff;border-radius:12px;">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-search me-1"></i>Buscar profesor o email</label>
                    <input type="text" id="filtroProfesor" class="form-control form-control-sm" placeholder="Escribe para buscar..." oninput="filtrarProfesores()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-check-circle me-1"></i>Estado de Envío</label>
                    <select id="filtroEnvioEstado" class="form-select form-select-sm" onchange="filtrarProfesores()">
                        <option value="">Todos</option>
                        <option value="envio">Envió</option>
                        <option value="no-envio">No envió</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="limpiarFiltroProf()"><i class="fas fa-times"></i> Limpiar</button>
                </div>
                <div class="col-12"><small id="contadorProf" class="text-muted"></small></div>
            </div>
        </div>

        <?php if (empty($usuariosReporte)): ?>
            <div class="empty-state">
                <i class="fas fa-users d-block"></i>
                <h5 class="text-muted">No hay usuarios registrados</h5>
            </div>
        <?php else: ?>
        <div class="table-responsive user-table">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th class="text-center">N° Docs</th>
                        <th>Tipos de Documento</th>
                        <th>Primer Envío</th>
                        <th>Último Envío</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                    <tbody id="tbodyProfesores">
                    <?php foreach ($usuariosReporte as $u): 
                        $envio      = intval($u['total_docs']) > 0;
                        $esAdmin    = $u['rol'] === 'administrador';
                        $iniciales  = strtoupper(mb_substr($u['nombre'], 0, 1));
                    ?>
                    <tr data-nombre="<?php echo strtolower(htmlspecialchars($u['nombre'])); ?>"
                        data-email="<?php echo strtolower(htmlspecialchars($u['email'])); ?>"
                        data-envio="<?php echo $envio ? 'envio' : 'no-envio'; ?>">
                        <td>
                            <div class="user-name-cell">
                                <div class="avatar <?php echo $esAdmin ? 'admin' : 'prof'; ?>">
                                    <?php echo $iniciales; ?>
                                </div>
                                <span class="fw-semibold"><?php echo htmlspecialchars($u['nombre']); ?></span>
                            </div>
                        </td>
                        <td class="text-muted small"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <?php if ($esAdmin): ?>
                                <span class="badge bg-danger">Administrador</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Profesor</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold fs-5 <?php echo $envio ? 'text-success' : 'text-muted'; ?>">
                                <?php echo $u['total_docs']; ?>
                            </span>
                        </td>
                        <td class="small">
                            <?php
                            if (!empty($u['tipos_docs'])) {
                                $tipos = explode(', ', $u['tipos_docs']);
                                foreach ($tipos as $t) {
                                    echo badgeTipo(trim($t)) . ' ';
                                }
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                            ?>
                        </td>
                        <td class="small">
                            <?php echo $u['primer_envio'] ? '<i class="fas fa-calendar-check text-muted me-1"></i>' . date('d/m/Y H:i', strtotime($u['primer_envio'])) : '<span class="text-muted">—</span>'; ?>
                        </td>
                        <td class="small">
                            <?php echo $u['ultimo_envio'] ? '<i class="fas fa-clock text-muted me-1"></i>' . date('d/m/Y H:i', strtotime($u['ultimo_envio'])) : '<span class="text-muted">—</span>'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($envio): ?>
                                <span class="badge-sent"><i class="fas fa-check-circle me-1"></i>Envió</span>
                            <?php else: ?>
                                <span class="badge-not-sent"><i class="fas fa-times-circle me-1"></i>No envió</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ TABLA DETALLADA DE DOCUMENTOS DEL MES ════════════ -->
    <div class="mb-5">
        <span class="section-title">
            <i class="fas fa-file-alt me-2"></i>Detalle de Documentos Recibidos — <?php echo $labelMes; ?>
        </span>

        <!-- Filtro de búsqueda para detalle de documentos -->
        <div class="card border-0 shadow-sm mb-3 p-3 no-print" style="background:#f8faff;border-radius:12px;">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-search me-1"></i>Buscar por título o autor</label>
                    <input type="text" id="filtroDocTitulo" class="form-control form-control-sm" placeholder="Escribe para buscar..." oninput="filtrarDocs()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-tag me-1"></i>Tipo de Documento</label>
                    <select id="filtroDocTipo" class="form-select form-select-sm" onchange="filtrarDocs()">
                        <option value="">Todos los tipos</option>
                        <option value="archivo_subido">Archivo Subido</option>
                        <option value="reporte">Reporte</option>
                        <option value="carta">Carta</option>
                        <option value="factura">Factura</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="limpiarFiltroDoc()"><i class="fas fa-times"></i> Limpiar</button>
                </div>
                <div class="col-12"><small id="contadorDoc" class="text-muted"></small></div>
            </div>
        </div>

        <?php if (empty($documentosMes)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open d-block"></i>
                <h5 class="mt-3 text-muted">No se recibieron documentos en <?php echo $labelMes; ?></h5>
                <p class="text-muted small">Selecciona otro mes o espera a que los profesores suban sus documentos.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive docs-table">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Título del Documento</th>
                        <th>Tipo</th>
                        <th>Profesor / Usuario</th>
                        <th>Email</th>
                        <th>Fecha y Hora de Envío</th>
                        <th>Tamaño</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentosMes as $i => $doc): ?>
                    <tr data-titulo="<?php echo strtolower(htmlspecialchars($doc['titulo'])); ?>"
                        data-autor="<?php echo strtolower(htmlspecialchars($doc['usuario_nombre'] ?? '')); ?>"
                        data-tipo="<?php echo htmlspecialchars($doc['tipo']); ?>">
                        <td class="text-muted small"><?php echo $i + 1; ?></td>
                        <td class="fw-semibold">
                            <?php echo htmlspecialchars($doc['titulo']); ?>
                            <?php if (!empty($doc['nombre_archivo'])): ?>
                                <br><small class="text-muted fw-normal">
                                    <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($doc['nombre_archivo']); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo badgeTipo($doc['tipo']); ?></td>
                        <td>
                            <?php if ($doc['usuario_nombre']): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar <?php echo $doc['usuario_rol'] === 'administrador' ? 'admin' : 'prof'; ?>" style="width:28px;height:28px;font-size:.75rem;">
                                        <?php echo strtoupper(mb_substr($doc['usuario_nombre'], 0, 1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($doc['usuario_nombre']); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-question-circle"></i> Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?php echo htmlspecialchars($doc['usuario_email'] ?? '—'); ?></td>
                        <td class="small">
                            <strong><?php echo date('d/m/Y', strtotime($doc['fecha_creacion'])); ?></strong>
                            <br><span class="text-muted"><?php echo date('H:i:s', strtotime($doc['fecha_creacion'])); ?></span>
                        </td>
                        <td class="small text-muted">
                            <?php echo $doc['tamanio_archivo'] ? formatearBytes(intval($doc['tamanio_archivo'])) : '—'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla con resumen -->
        <div class="mt-3 d-flex justify-content-between align-items-center text-muted small px-1">
            <span>
                <i class="fas fa-info-circle me-1"></i>
                Total: <strong class="text-dark"><?php echo count($documentosMes); ?></strong>
                documento(s) recibido(s) en <?php echo $labelMes; ?>
            </span>
            <span class="no-print">
                <i class="fas fa-print me-1"></i>
                <a href="#" onclick="window.print()" class="text-decoration-none text-muted">Imprimir informe</a>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ FOOTER DEL INFORME ════════════════════════════════ -->
    <div class="text-center text-muted small py-3 border-top">
        <p class="mb-1">
            <i class="fas fa-university me-1"></i>
            Sistema de Gestión de Documentos · Generado el <?php echo date('d/m/Y \a \l\a\s H:i'); ?>
        </p>
        <p class="mb-0">Informe confidencial · Solo para uso de Dirección Académica</p>
    </div>

</div><!-- /container -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // filtros informe previos
    const btnGuardar = document.getElementById('btnGuardarConfig');
    const btnEnviar = document.getElementById('btnEnviarAhora');
    const alertBox = document.getElementById('emailAlert');

    function showAlert(msg, isSuccess) {
        alertBox.className = `alert mt-3 mb-0 alert-${isSuccess ? 'success' : 'danger'}`;
        alertBox.innerHTML = msg;
        alertBox.classList.remove('d-none');
    }

    if (btnGuardar) {
        btnGuardar.addEventListener('click', function() {
            const formData = new FormData(document.getElementById('formEmailConfig'));
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            fetch('api/api_guardar_config_email.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => { showAlert(data.message || 'Configuración guardada.', data.success); })
            .catch(() => showAlert('Error al guardar la configuración.', false))
            .finally(() => { btnGuardar.disabled = false; btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios'; });
        });
    }
    if (btnEnviar) {
        btnEnviar.addEventListener('click', function() {
            btnEnviar.disabled = true;
            btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando informe...';
            alertBox.classList.add('d-none');
            const params = new FormData();
            params.append('mes', '<?php echo $mes; ?>');
            params.append('anio', '<?php echo $anio; ?>');
            fetch('api/api_enviar_informe_manual.php', { method: 'POST', body: params })
            .then(r => r.json())
            .then(data => { showAlert(data.message, data.success); })
            .catch(() => { showAlert('Error crítico de red.', false); })
            .finally(() => { btnEnviar.disabled = false; btnEnviar.innerHTML = '<i class="fas fa-paper-plane"></i> ENVIAR INFORME AHORA POR CORREO'; });
        });
    }
});

// Filtro tabla profesores
function filtrarProfesores() {
    const texto  = document.getElementById('filtroProfesor').value.toLowerCase();
    const estado = document.getElementById('filtroEnvioEstado').value;
    const filas  = document.querySelectorAll('#tbodyProfesores tr[data-nombre]');
    let vis = 0;
    filas.forEach(f => {
        const txt = (f.dataset.nombre||'') + ' ' + (f.dataset.email||'');
        const ok  = (!texto || txt.includes(texto)) && (!estado || f.dataset.envio === estado);
        f.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    document.getElementById('contadorProf').textContent = `Mostrando ${vis} de ${filas.length} usuario(s)`;
}
function limpiarFiltroProf() {
    ['filtroProfesor','filtroEnvioEstado'].forEach(id => { document.getElementById(id).value = ''; });
    filtrarProfesores();
}

// Filtro tabla documentos del mes
function filtrarDocs() {
    const texto = document.getElementById('filtroDocTitulo').value.toLowerCase();
    const tipo  = document.getElementById('filtroDocTipo').value;
    const filas = document.querySelectorAll('tbody tr[data-titulo]');
    let vis = 0;
    filas.forEach(f => {
        const txt = (f.dataset.titulo||'') + ' ' + (f.dataset.autor||'');
        const ok  = (!texto || txt.includes(texto)) && (!tipo || f.dataset.tipo === tipo);
        f.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    document.getElementById('contadorDoc').textContent = `Mostrando ${vis} de ${filas.length} documento(s)`;
}
function limpiarFiltroDoc() {
    ['filtroDocTitulo','filtroDocTipo'].forEach(id => { document.getElementById(id).value = ''; });
    filtrarDocs();
}

document.addEventListener('DOMContentLoaded', () => { filtrarProfesores(); filtrarDocs(); });
</script>

<?php include 'includes/footer.php'; ?>
