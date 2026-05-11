<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirLogin();

// Obtener estadísticas
if (esAdministrador()) {
    $stmtTotal = $conn->query("SELECT COUNT(*) as total FROM documentos");
    $stmtUsuarios = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
    // Cuenta todos los documentos drive activos globalmente
    $stmtDrive = $conn->query("SELECT COUNT(*) as total FROM drive_documentos WHERE activo = 1");
} else {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) as total FROM documentos WHERE usuario_id = ?");
    $stmtTotal->execute([$_SESSION['usuario_id']]);
    $stmtUsuarios = null;
    // Cuenta los documentos drive exclusivos del rol del profesor/coordinador
    $stmtDrive = $conn->prepare("SELECT COUNT(*) as total FROM drive_documentos WHERE activo = 1 AND rol_asignado = ?");
    $stmtDrive->execute([$_SESSION['rol']]);
}

$totalDocumentos = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
$totalUsuarios = $stmtUsuarios ? $stmtUsuarios->fetch(PDO::FETCH_ASSOC)['total'] : 0;
$totalDrive = $stmtDrive->fetch(PDO::FETCH_ASSOC)['total'];

$page_title = "Inicio - Universitaria de Colombia";
include 'includes/header.php';
?>

<div class="hero-banner text-center">
    <div class="container">
        <h1 class="display-4 fw-bold mb-3">
             Bienvenido, <?php echo explode(' ', $_SESSION['usuario_nombre'])[0]; ?>
        </h1>
        <p class="lead mb-4">Sistema de Gestión de Documentos Institucionales</p>
        <div class="row justify-content-center gap-3 gap-md-0">
            <div class="col-md-3">
                <div class="card bg-white text-dark border-0 p-3 shadow-sm h-100">
                    <h2 class="fw-bold mb-0"><?php echo $totalDocumentos; ?></h2>
                    <small class="text-muted fw-semibold">Mis Cargas</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-white text-dark border-0 p-3 shadow-sm h-100">
                    <h2 class="fw-bold mb-0 text-success"><?php echo $totalDrive; ?></h2>
                    <small class="text-muted fw-semibold">Plantillas en Drive</small>
                </div>
            </div>
            <?php if (esAdministrador()): ?>
            <div class="col-md-3">
                <div class="card bg-white text-dark border-0 p-3 shadow-sm h-100">
                    <h2 class="fw-bold mb-0 text-primary"><?php echo $totalUsuarios; ?></h2>
                    <small class="text-muted fw-semibold">Usuarios Activos</small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4">
        <!-- Tarjeta: Crear -->
        <div class="col-md-4">
            <a href="crear_documento.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body text-center">
                        <div class="mb-3 text-primary"><i class="fas fa-plus-circle fa-3x"></i></div>
                        <h5 class="fw-bold text-dark">Crear Documento</h5>
                        <p class="text-muted small">Reportes, cartas o facturas desde cero</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Tarjeta: Subir -->
        <div class="col-md-4">
            <a href="subir_documento.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body text-center">
                        <div class="mb-3 text-success"><i class="fas fa-upload fa-3x"></i></div>
                        <h5 class="fw-bold text-dark">Subir Archivo</h5>
                        <p class="text-muted small">Carga documentos existentes al repositorio</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Tarjeta: Listar -->
        <div class="col-md-4">
            <a href="listar_documentos.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body text-center">
                        <div class="mb-3 text-warning"><i class="fas fa-search fa-3x"></i></div>
                        <h5 class="fw-bold text-dark">Ver Repositorio</h5>
                        <p class="text-muted small">Consulta y descarga documentos guardados</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Tarjeta: Drive (Común) -->
        <div class="col-md-4">
            <a href="mis_documentos_drive.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body text-center">
                        <div class="mb-3" style="color:#14b8a6"><i class="fab fa-google-drive fa-3x"></i></div>
                        <h5 class="fw-bold text-dark">Mis Documentos Drive</h5>
                        <p class="text-muted small">Accede a tus archivos institucionales asignados</p>
                    </div>
                </div>
            </a>
        </div>

        <?php if (esAdministrador()): ?>
        <!-- Tarjeta: Informe Mensual -->
        <div class="col-md-4">
            <a href="informe_mensual.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm p-3" style="border-left: 4px solid var(--accent-red) !important;">
                    <div class="card-body text-center">
                        <div class="mb-3 text-u"><i class="fas fa-chart-line fa-3x"></i></div>
                        <h5 class="fw-bold text-dark">Informe de Gestión</h5>
                        <p class="text-muted small">Reporte detallado de cumplimiento por profesor</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Tarjeta: Usuarios -->
        <div class="col-md-4">
            <a href="gestionar_usuarios.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body text-center">
                        <div class="mb-3 text-secondary"><i class="fas fa-users-cog fa-3x"></i></div>
                        <h5 class="fw-bold text-dark">Gestionar Usuarios</h5>
                        <p class="text-muted small">Administración de cuentas y permisos</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
