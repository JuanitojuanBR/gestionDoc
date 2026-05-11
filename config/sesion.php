<?php
session_start();

function estaLogueado() {
    return isset($_SESSION['usuario_id']);
}

function esAdministrador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador';
}



function esCoordinador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'coordinador_sede';
}

function esDecano() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'decano';
}

function esDocenteTC() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'docente_tc';
}

function esDocenteCatedra() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'docente_catedra';
}

// Devuelve true si el usuario es cualquier tipo de docente/profesor
function esDocente() {
    return esDocenteTC() || esDocenteCatedra();
}

function obtenerUsuario() {
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'nombre' => $_SESSION['usuario_nombre'] ?? null,
        'email' => $_SESSION['usuario_email'] ?? null,
        'rol' => $_SESSION['rol'] ?? null
    ];
}

function obtenerRolLabel($rol = null) {
    $rol = $rol ?? ($_SESSION['rol'] ?? '');
    $labels = [
        'administrador'     => 'Administrador',

        'coordinador_sede'  => 'Coordinador de Sede',
        'decano'            => 'Decano',
        'docente_tc'        => 'Docente Tiempo Completo',
        'docente_catedra'   => 'Docente de Cátedra',
    ];
    return $labels[$rol] ?? ucfirst($rol);
}

function obtenerRolBadgeClass($rol = null) {
    $rol = $rol ?? ($_SESSION['rol'] ?? '');
    $classes = [
        'administrador'     => 'bg-danger',

        'coordinador_sede'  => 'bg-info',
        'decano'            => 'bg-warning text-dark',
        'docente_tc'        => 'bg-success',
        'docente_catedra'   => 'bg-secondary',
    ];
    return $classes[$rol] ?? 'bg-dark';
}

function requerirLogin() {
    if (!estaLogueado()) {
        header("Location: login.php");
        exit();
    }
}

function requerirAdministrador() {
    requerirLogin();
    if (!esAdministrador()) {
        header("Location: index.php?error=permisos");
        exit();
    }
}

function cerrarSesion() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>