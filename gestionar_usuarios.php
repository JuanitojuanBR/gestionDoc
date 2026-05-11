<?php
require_once 'config/sesion.php';
require_once 'config/database.php';
requerirAdministrador();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = $_POST['nombre'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $rol = $_POST['rol'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $email, $password, $rol]);
            $success = "Usuario creado exitosamente";
        } catch(Exception $e) {
            $error = "Error al crear usuario: " . $e->getMessage();
        }
    } elseif ($accion === 'desactivar') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Usuario ID $id desactivado correctamente";
            } catch(Exception $e) {
                $error = "Error DB: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'activar') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Usuario ID $id activado correctamente";
            } catch(Exception $e) {
                $error = "Error DB: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'borrar_fisico') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && $id != $_SESSION['usuario_id']) {
            try {
                $conn->beginTransaction();
                $conn->prepare("DELETE FROM usuario_drive_estado WHERE usuario_id = ?")->execute([$id]);
                $conn->prepare("DELETE FROM documentos WHERE usuario_id = ?")->execute([$id]);
                $conn->prepare("UPDATE drive_documentos SET asignado_por = NULL WHERE asignado_por = ?")->execute([$id]);
                $conn->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
                $conn->commit();
                $success = "Usuario ID $id eliminado permanentemente";
            } catch(Exception $e) {
                $conn->rollBack();
                $error = "Error DB: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'restablecer_clave') {
        $id = intval($_POST['id'] ?? 0);
        $nueva_clave = $_POST['nueva_password'] ?? '';
        if ($id > 0 && !empty($nueva_clave)) {
            try {
                $password_hash = password_hash($nueva_clave, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$password_hash, $id]);
                $success = "Contraseña restablecida correctamente";
            } catch(Exception $e) {
                $error = "Error DB al cambiar contraseña: " . $e->getMessage();
            }
        } else {
            $error = "Debes proveer una nueva contraseña.";
        }
    } elseif ($accion === 'cambiar_rol') {
        $id = intval($_POST['id'] ?? 0);
        $nuevo_rol = $_POST['nuevo_rol'] ?? '';
        if ($id > 0 && !empty($nuevo_rol)) {
            try {
                $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
                $stmt->execute([$nuevo_rol, $id]);
                $success = "Rol de usuario actualizado exitosamente";
            } catch(Exception $e) {
                $error = "Error DB al cambiar rol: " . $e->getMessage();
            }
        }
    }
}

// Obtener usuarios
$stmt = $conn->query("SELECT * FROM usuarios ORDER BY fecha_creacion DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php 
$page_title = "Gestionar Usuarios - Universitaria de Colombia";
include 'includes/header.php'; 
?>

<div class="hero-banner text-center py-4">
    <div class="container">
        <h1 class="fw-bold"><i class="fas fa-users-cog me-2"></i>Gestión de Usuarios</h1>
        <p class="lead opacity-75">Administración de acceso y roles del personal académico</p>
    </div>
</div>


    <div class="container mt-4">
        <h2><i class="fas fa-users"></i> Gestionar Usuarios</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#crearUsuarioModal">
            <i class="fas fa-user-plus"></i> Crear Nuevo Usuario
        </button>

        <!-- Barra de Filtros -->
        <div class="card border-0 shadow-sm mb-3 p-3" style="background:#f8faff;border-radius:12px;">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-search me-1"></i>Buscar por nombre o email</label>
                    <input type="text" id="filtroNombre" class="form-control form-control-sm" placeholder="Escribe para buscar..." oninput="aplicarFiltrosUsuarios()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-user-tag me-1"></i>Filtrar por Rol</label>
                    <select id="filtroRolU" class="form-select form-select-sm" onchange="aplicarFiltrosUsuarios()">
                        <option value="">Todos los roles</option>
                        <option value="administrador">Administrador</option>
                        <option value="decano">Decano</option>
                        <option value="coordinador_sede">Coordinador de Sede</option>
                        <option value="docente_tc">Docente TC</option>
                        <option value="docente_catedra">Docente Cátedra</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted"><i class="fas fa-toggle-on me-1"></i>Estado</label>
                    <select id="filtroEstadoU" class="form-select form-select-sm" onchange="aplicarFiltrosUsuarios()">
                        <option value="">Todos</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="limpiarFiltrosU()" title="Limpiar">
                        <i class="fas fa-times"></i> Limpiar
                    </button>
                </div>
            </div>
            <div class="mt-2"><small id="contadorU" class="text-muted"></small></div>
        </div>

        <!-- Tabla de usuarios -->
        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr data-nombre="<?php echo strtolower(htmlspecialchars($usuario['nombre'])); ?>"
                            data-email="<?php echo strtolower(htmlspecialchars($usuario['email'])); ?>"
                            data-rol="<?php echo htmlspecialchars($usuario['rol']); ?>"
                            data-activo="<?php echo $usuario['activo'] ? 'activo' : 'inactivo'; ?>">
                            <td><?php echo $usuario['id']; ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td>
                                <span class="badge <?php echo obtenerRolBadgeClass($usuario['rol']); ?>">
                                    <?php echo obtenerRolLabel($usuario['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($usuario['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></td>
                            <td>
                                <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                    <div class="d-flex gap-2">
                                        <?php if ($usuario['activo']): ?>
                                            <button type="button" class="btn btn-sm btn-warning text-white" 
                                                    onclick="confirmarAccion('desactivar', <?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')" 
                                                    title="Desactivar">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="accion" value="activar">
                                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Activar">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-info text-white"
                                                onclick="abrirModalRestablecer(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars(addslashes($usuario['nombre'])); ?>')"
                                                title="Cambiar Contraseña">
                                            <i class="fas fa-key"></i>
                                        </button>

                                        <button type="button" class="btn btn-sm btn-dark" 
                                                onclick="abrirModalCambiarRol(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars(addslashes($usuario['nombre'])); ?>', '<?php echo $usuario['rol']; ?>')" 
                                                title="Cambiar Rol">
                                            <i class="fas fa-user-tag"></i>
                                        </button>

                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmarAccion('borrar_fisico', <?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')" 
                                                title="Eliminar Permanentemente">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small"><em>(Tú)</em></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Crear Usuario -->
    <div class="modal fade" id="crearUsuarioModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Crear Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="coordinador_sede">Coordinador de Sede</option>
                                <option value="decano">Decano</option>
                                <option value="docente_tc">Docente Tiempo Completo</option>
                                <option value="docente_catedra">Docente de Cátedra</option>
                                <option value="administrador">Administrador</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cambiar Rol -->
    <div class="modal fade" id="cambiarRolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-user-tag"></i> Cambiar Rol de Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="rol_id">
                        <input type="hidden" name="accion" value="cambiar_rol">
                        
                        <p>Cambiar el rol de acceso para: <strong><span id="rol_nombre"></span></strong></p>
                        
                        <div class="mb-3">
                            <label for="nuevo_rol" class="form-label fw-bold">Nuevo Rol Asignado</label>
                            <select class="form-select" id="nuevo_rol" name="nuevo_rol" required>
                                <option value="coordinador_sede">Coordinador de Sede</option>
                                <option value="decano">Decano</option>
                                <option value="docente_tc">Docente Tiempo Completo</option>
                                <option value="docente_catedra">Docente de Cátedra</option>
                                <option value="administrador">Administrador</option>
                            </select>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle"></i> Al cambiar el rol, el usuario verá diferentes documentos en su panel de Google Drive, pero conservará todos sus archivos creados.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark">Actualizar Rol</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Restablecer Contraseña -->
    <div class="modal fade" id="restablecerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Restablecer Contraseña</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="restablecer_clave">
                        <input type="hidden" name="id" id="restablecer_id">
                        
                        <p>Ingresa la nueva contraseña para <strong><span id="restablecer_nombre"></span></strong>:</p>
                        
                        <div class="mb-3">
                            <label for="nueva_password" class="form-label text-muted small fw-bold">Nueva Contraseña Temporal</label>
                            <input type="text" class="form-control" id="nueva_password" name="nueva_password" required placeholder="Ejemplo: nueva_clave123">
                            <div class="form-text">Si cambias la contraseña, asegúrate de comunicarle esta nueva clave temporal al profesor al instante.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info text-white">Guardar y Cambiar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Confirmación Acciones -->
    <div class="modal fade" id="confirmarAccionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" id="modalConfirmHeader">
                    <h5 class="modal-title text-white" id="modalConfirmTitle">Confirmar Acción</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formConfirmarAccion">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="hiddenAccion">
                        <input type="hidden" name="id" id="hiddenId">
                        <p id="modalConfirmMessage">¿Estás seguro de realizar esta acción?</p>
                        <div id="deleteWarning" class="alert alert-danger mt-2 d-none">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Atención:</strong> Esta acción es irreversible y eliminará todos los documentos y registros asociados al usuario.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn" id="btnConfirmarSubmit">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ─── Filtros de Usuarios ─────────────────────────────────
    function aplicarFiltrosUsuarios() {
        const nombre = document.getElementById('filtroNombre').value.toLowerCase();
        const rol    = document.getElementById('filtroRolU').value;
        const estado = document.getElementById('filtroEstadoU').value;
        const filas  = document.querySelectorAll('tbody tr[data-nombre]');
        let visibles = 0;
        filas.forEach(f => {
            const n = (f.dataset.nombre || '') + ' ' + (f.dataset.email || '');
            const coincide = (!nombre || n.includes(nombre))
                          && (!rol    || f.dataset.rol    === rol)
                          && (!estado || f.dataset.activo === estado);
            f.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });
        document.getElementById('contadorU').textContent =
            `Mostrando ${visibles} de ${filas.length} usuario(s)`;
    }
    function limpiarFiltrosU() {
        ['filtroNombre','filtroRolU','filtroEstadoU'].forEach(id => { document.getElementById(id).value = ''; });
        aplicarFiltrosUsuarios();
    }
    document.addEventListener('DOMContentLoaded', aplicarFiltrosUsuarios);
        // Restablecer Clave Logic
        const modalRestablecer = new bootstrap.Modal(document.getElementById('restablecerModal'));
        
        function abrirModalRestablecer(id, nombre) {
            document.getElementById('restablecer_id').value = id;
            document.getElementById('restablecer_nombre').innerText = nombre;
            document.getElementById('nueva_password').value = '';
            modalRestablecer.show();
        }

        // Cambiar Rol Logic
        const modalCambiarRol = new bootstrap.Modal(document.getElementById('cambiarRolModal'));
        
        function abrirModalCambiarRol(id, nombre, rolActual) {
            document.getElementById('rol_id').value = id;
            document.getElementById('rol_nombre').innerText = nombre;
            document.getElementById('nuevo_rol').value = rolActual;
            modalCambiarRol.show();
        }

        // Acciones Genéricas Logic
        const modalAccion = new bootstrap.Modal(document.getElementById('confirmarAccionModal'));
        
        function confirmarAccion(accion, id, nombre) {
            const title = document.getElementById('modalConfirmTitle');
            const message = document.getElementById('modalConfirmMessage');
            const header = document.getElementById('modalConfirmHeader');
            const btn = document.getElementById('btnConfirmarSubmit');
            const warning = document.getElementById('deleteWarning');
            
            document.getElementById('hiddenAccion').value = accion;
            document.getElementById('hiddenId').value = id;
            
            warning.classList.add('d-none');
            
            if (accion === 'desactivar') {
                header.className = 'modal-header bg-warning';
                title.innerText = 'Desactivar Usuario';
                message.innerHTML = `¿Estás seguro que deseas desactivar al usuario <strong>${nombre}</strong>?`;
                btn.className = 'btn btn-warning text-white';
            } else if (accion === 'borrar_fisico') {
                header.className = 'modal-header bg-danger';
                title.innerText = 'ELIMINAR PERMANENTEMENTE';
                message.innerHTML = `¿Estás seguro que deseas eliminar físicamente al usuario <strong>${nombre}</strong> de la base de datos?`;
                btn.className = 'btn btn-danger';
                warning.classList.remove('d-none');
            }
            
            modalAccion.show();
        }
    </script>
</body>
</html>
