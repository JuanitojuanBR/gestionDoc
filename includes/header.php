<?php
if (!isset($page_title)) $page_title = "Sistema de Gestión de Documentos";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/jpeg" href="assets/img/images.jpg">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Design System -->
    <link href="assets/css/universitaria.css" rel="stylesheet">
    
    <style>
        /* Estilos específicos de layout */
        .page-content { padding-top: 20px; min-height: calc(100vh - 160px); }
    </style>
</head>
<body>

    <!-- ══ NAVBAR INSTITUCIONAL ════════════════════════════════════ -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow no-print">
        <div class="container">
            <a class="navbar-brand bg-white rounded px-3 py-1 shadow-sm d-flex align-items-center" href="index.php" style="margin-right: 1.5rem;">
                <img src="assets/img/5988c532-cd64-415e-973e-df53f33cdb56.png" alt="Logo Universitaria de Colombia" style="max-height: 40px; width: auto; object-fit: contain;">
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'crear_documento.php' ? 'active' : ''; ?>" href="crear_documento.php">Crear</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'subir_documento.php' ? 'active' : ''; ?>" href="subir_documento.php">Subir</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'listar_documentos.php' ? 'active' : ''; ?>" href="listar_documentos.php">Mis Documentos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'mis_documentos_drive.php' ? 'active' : ''; ?>" href="mis_documentos_drive.php"><i class="fab fa-google-drive me-1"></i>Drive</a>
                    </li>
                    
                    <?php if (esAdministrador()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['gestionar_usuarios.php', 'gestionar_drive.php', 'informe_mensual.php', 'ver_entregas_drive.php']) ? 'active' : ''; ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">Panel Admin</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="gestionar_usuarios.php"><i class="fas fa-users-cog me-2"></i> Usuarios</a></li>
                            <li><a class="dropdown-item" href="gestionar_drive.php"><i class="fab fa-google-drive me-2"></i> Gestión Drive</a></li>
                            <li><a class="dropdown-item <?php echo $current_page === 'ver_entregas_drive.php' ? 'active' : ''; ?>" href="ver_entregas_drive.php"><i class="fas fa-inbox me-2 text-success"></i> Entregas Drive</a></li>
                            <li><a class="dropdown-item" href="informe_mensual.php"><i class="fas fa-chart-line me-2"></i> Informe Mensual</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle bg-white text-dark px-3 py-1 rounded-pill" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo explode(' ', $_SESSION['usuario_nombre'])[0]; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="px-3 py-2 small fw-bold text-muted">ROL: <?php echo str_replace('_', ' ', strtoupper($_SESSION['rol'])); ?></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cambiar_password.php"><i class="fas fa-key me-2 text-primary"></i>Cambiar Contraseña</a></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="page-content">
