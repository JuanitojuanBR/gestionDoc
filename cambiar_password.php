<?php
require_once 'config/sesion.php';
requerirLogin();
require_once 'config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $user_id = $_SESSION['usuario_id'];
    
    // Obtener la contraseña actual del usuario desde la base de datos
    $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $error = "Error al encontrar el usuario.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "La contraseña actual es incorrecta.";
    } elseif ($new_password !== $confirm_password) {
        $error = "La nueva contraseña y la confirmación no coinciden.";
    } elseif (strlen($new_password) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } else {
        // Encriptar y actualizar la nueva contraseña
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            $update_stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $update_stmt->execute([$new_hash, $user_id]);
            $success = "Contraseña actualizada correctamente.";
        } catch(Exception $e) {
            $error = "Error en la base de datos al actualizar la contraseña.";
        }
    }
}
?>
<?php 
$page_title = "Cambiar Contraseña - Universitaria de Colombia";
include 'includes/header.php'; 
?>

<div class="hero-banner text-center py-4">
    <div class="container">
        <h1 class="fw-bold"><i class="fas fa-lock me-2"></i>Cambiar Contraseña</h1>
        <p class="lead opacity-75">Configuración de seguridad para tu cuenta</p>
    </div>
</div>

<div class="container mt-5 pb-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="mb-0 text-center"><i class="fas fa-shield-alt me-2 text-warning"></i>Actualizar Credenciales</h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-1"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label fw-bold text-muted small text-uppercase">Contraseña Actual</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-bold text-muted small text-uppercase">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text small">Mínimo 6 caracteres.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-bold text-muted small text-uppercase">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid mt-2">
                            <button type="submit" class="btn btn-primary d-flex justify-content-center align-items-center py-2">
                                <i class="fas fa-save me-2"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
