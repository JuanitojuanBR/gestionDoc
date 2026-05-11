<?php
session_start();
require_once 'config/database.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Por favor completa todos los campos";
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($password, $usuario['password'])) {
                // Login exitoso
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['rol'] = $usuario['rol'];
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Credenciales incorrectas";
            }
        } catch(Exception $e) {
            $error = "Error en el sistema: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Universitaria de Colombia</title>
    <link rel="icon" type="image/jpeg" href="assets/img/images.jpg">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #003366;
            --accent-red: #A51C30;
            --accent-yellow: #F1C400;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            position: relative;
            background-color: rgba(0, 51, 102, 0.4);
        }
        body::before {
            content: "";
            position: fixed;
            top: -10px; left: -10px; right: -10px; bottom: -10px; /* Slight overflow to hide edge blur artifacts */
            background-image: url('assets/img/Sede+Verde+Actualizar.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(8px) brightness(0.6);
            z-index: -1;
        }
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background-color: var(--primary-blue);
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 5px solid var(--accent-yellow);
        }
        .login-body {
            padding: 40px;
            background: white;
        }
        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            padding: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 10px;
        }
        .btn-primary:hover {
            background-color: #002244;
            border-color: #002244;
        }
        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.15);
        }
        .inst-logo {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--accent-yellow);
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center">
        <div class="login-card">
            <div class="login-header">
                <div class="inst-logo">
                    <i class="fas fa-university"></i>
                </div>
                <h4 class="fw-bold mb-1">GESTIÓN DOCUMENTAL</h4>
                <p class="mb-0 opacity-75 small">Universitaria de Colombia</p>
            </div>
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold text-muted small">CORREO ELECTRÓNICO</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="ejemplo@universitariadecolombia.edu.co" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-bold text-muted small">CONTRASEÑA</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3 shadow">
                        INGRESAR AL SISTEMA
                    </button>
                    
                    <div class="text-center">
                        <small class="text-muted">¿Problemas para ingresar? Contacte a soporte técnico.</small>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
