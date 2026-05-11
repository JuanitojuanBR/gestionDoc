<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class MailService {
    private $db;
    private $config;

    public function __construct($db) {
        $this->db = $db;
        $this->loadConfig();
    }

    private function loadConfig() {
        $stmt = $this->db->query("SELECT * FROM config_envio_informes LIMIT 1");
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function enviarInformeMensual($mes = null, $anio = null, $manual = true) {
        if (!$this->config) return ["success" => false, "message" => "Configuración de correo no encontrada"];
        if (empty($this->config['correos_destino'])) return ["success" => false, "message" => "No hay correos destinatarios configurados"];

        $mes = $mes ?? date('m');
        $anio = $anio ?? date('Y');
        
        $cuerpoHtml = $this->generarCuerpoInforme($mes, $anio);
        
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host       = $this->config['smtp_host'];
            $mail->SMTPAuth   = !empty($this->config['smtp_user']);
            $mail->Username   = $this->config['smtp_user'];
            $mail->Password   = $this->config['smtp_pass'];
            $mail->SMTPSecure = $this->config['smtp_secure'];
            $mail->Port       = $this->config['smtp_port'];
            $mail->CharSet    = 'UTF-8';

            // Destinatarios
            $mail->setFrom($this->config['smtp_user'] ?: 'admin@sistema.com', 'Sistema de Gestión Documental');
            $correos = explode(',', $this->config['correos_destino']);
            foreach ($correos as $email) {
                $mail->addAddress(trim($email));
            }

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = "Informe de Gestión de Documentos - " . date('M Y', mktime(0, 0, 0, $mes, 1, $anio));
            $mail->Body    = $cuerpoHtml;

            $mail->send();
            
            // Actualizar último envío si es automático
            if (!$manual) {
                $stmt = $this->db->prepare("UPDATE config_envio_informes SET ultimo_envio = NOW() WHERE id = ?");
                $stmt->execute([$this->config['id']]);
            }

            return ["success" => true, "message" => "Informe enviado correctamente"];
        } catch (Exception $e) {
            return ["success" => false, "message" => "Error al enviar correo: {$mail->ErrorInfo}"];
        }
    }

    private function generarCuerpoInforme($mes, $anio) {
        $fechaInicio = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
        $fechaFin    = date('Y-m-t 23:59:59', mktime(0, 0, 0, $mes, 1, $anio));

        $nombresMeses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',
                         6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',
                         10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $labelMes = $nombresMeses[intval($mes)] . ' ' . $anio;

        // ── Estadísticas globales ──────────────────────────────────────
        $stmtTotal = $this->db->prepare("SELECT COUNT(*) AS total FROM documentos WHERE fecha_creacion BETWEEN ? AND ?");
        $stmtTotal->execute([$fechaInicio, $fechaFin]);
        $totalDocs = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtProfs = $this->db->query("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1 AND rol != 'administrador'");
        $totalProfs = (int)$stmtProfs->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtEnviaron = $this->db->prepare("SELECT COUNT(DISTINCT usuario_id) AS total FROM documentos WHERE fecha_creacion BETWEEN ? AND ?");
        $stmtEnviaron->execute([$fechaInicio, $fechaFin]);
        $enviaron = (int)$stmtEnviaron->fetch(PDO::FETCH_ASSOC)['total'];
        $cumplimiento = $totalProfs > 0 ? round(($enviaron / $totalProfs) * 100) : 0;

        // ── Total de documentos Drive activos ─────────────────────────
        $stmtTotalDrive = $this->db->query("SELECT COUNT(*) AS total FROM drive_documentos WHERE activo = 1");
        $totalDriveGlobal = (int)$stmtTotalDrive->fetch(PDO::FETCH_ASSOC)['total'];

        // ── Por cada usuario activo no admin, calcular sus stats ──────
        $stmtUsuarios = $this->db->prepare("
            SELECT u.id, u.nombre, u.email, u.rol,
                   COUNT(d.id) AS docs_mes
            FROM usuarios u
            LEFT JOIN documentos d ON d.usuario_id = u.id
                   AND d.fecha_creacion BETWEEN ? AND ?
            WHERE u.activo = 1 AND u.rol != 'administrador'
            GROUP BY u.id
            ORDER BY u.rol ASC, u.nombre ASC
        ");
        $stmtUsuarios->execute([$fechaInicio, $fechaFin]);
        $usuarios = $stmtUsuarios->fetchAll(\PDO::FETCH_ASSOC);

        // ── Drive: total asignado por rol ─────────────────────────────
        $stmtDrivePorRol = $this->db->query("
            SELECT rol_asignado, COUNT(*) AS total
            FROM drive_documentos WHERE activo = 1
            GROUP BY rol_asignado
        ");
        $drivePorRol = [];
        while ($r = $stmtDrivePorRol->fetch(\PDO::FETCH_ASSOC)) {
            $drivePorRol[$r['rol_asignado']] = (int)$r['total'];
        }

        // ── Drive: entregas por usuario ───────────────────────────────
        $stmtEntregas = $this->db->query("
            SELECT usuario_id, COUNT(DISTINCT drive_doc_id) AS entregados
            FROM drive_entregas
            GROUP BY usuario_id
        ");
        $entregasPorUsuario = [];
        while ($r = $stmtEntregas->fetch(\PDO::FETCH_ASSOC)) {
            $entregasPorUsuario[$r['usuario_id']] = (int)$r['entregados'];
        }

        // ── Colores institucionales ────────────────────────────────────
        $c = [
            'azul'   => '#003366',
            'rojo'   => '#A51C30',
            'dorado' => '#B8962E',
            'verde'  => '#059669',
            'naranja'=> '#d97706',
            'gris'   => '#64748b',
            'fondo'  => '#f8fafc',
            'borde'  => '#e2e8f0',
        ];

        $roleLabels = [
            'coordinador_sede' => 'Coordinador de Sede',
            'decano'           => 'Decano',
            'docente_tc'       => 'Docente Tiempo Completo',
            'docente_catedra'  => 'Docente de Cátedra',
        ];

        // ── Función interna barra de progreso ─────────────────────────
        $barraColor = function(int $pct) use ($c): string {
            if ($pct >= 80) return $c['verde'];
            if ($pct >= 50) return $c['naranja'];
            return $c['rojo'];
        };

        // ── Inicio HTML ───────────────────────────────────────────────
        ob_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body        { margin:0; font-family: Arial, Helvetica, sans-serif; background:#f0f4f8; color:#333; }
  .wrap       { max-width:680px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,.1); }
  .header     { background:<?php echo $c['azul']; ?>; color:#fff; padding:32px 28px; border-bottom:5px solid <?php echo $c['rojo']; ?>; }
  .header h1  { margin:0 0 4px; font-size:22px; }
  .header p   { margin:0; opacity:.8; font-size:14px; }
  .stats-row  { display:flex; gap:12px; padding:24px 28px; background:<?php echo $c['fondo']; ?>; border-bottom:1px solid <?php echo $c['borde']; ?>; }
  .stat-box   { flex:1; background:#fff; border-radius:10px; padding:16px; text-align:center; border:1px solid <?php echo $c['borde']; ?>; }
  .stat-val   { font-size:28px; font-weight:800; color:<?php echo $c['azul']; ?>; line-height:1.1; }
  .stat-lbl   { font-size:11px; color:<?php echo $c['gris']; ?>; text-transform:uppercase; letter-spacing:.5px; margin-top:4px; }
  .section    { padding:24px 28px; }
  .sec-title  { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.6px;
                color:<?php echo $c['azul']; ?>; border-bottom:2px solid <?php echo $c['rojo']; ?>;
                padding-bottom:8px; margin-bottom:16px; }
  .user-row   { border:1px solid <?php echo $c['borde']; ?>; border-radius:10px; padding:14px 16px;
                margin-bottom:10px; background:#fff; }
  .user-name  { font-weight:700; font-size:14px; color:#1e293b; }
  .user-role  { font-size:11px; color:<?php echo $c['gris']; ?>; margin-bottom:10px; }
  .metrics    { display:flex; gap:10px; flex-wrap:wrap; }
  .metric     { flex:1; min-width:140px; }
  .metric-lbl { font-size:11px; color:<?php echo $c['gris']; ?>; margin-bottom:4px; }
  .metric-val { font-size:13px; font-weight:700; }
  .bar-track  { background:<?php echo $c['borde']; ?>; border-radius:99px; height:6px; overflow:hidden; margin-top:4px; }
  .bar-fill   { height:6px; border-radius:99px; }
  .badge-ok   { display:inline-block; background:#d1fae5; color:#065f46; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:600; }
  .badge-warn { display:inline-block; background:#fef3c7; color:#92400e; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:600; }
  .badge-err  { display:inline-block; background:#fee2e2; color:#991b1b; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:600; }
  .footer     { text-align:center; padding:20px 28px; background:<?php echo $c['fondo']; ?>; font-size:11px; color:<?php echo $c['gris']; ?>; border-top:1px solid <?php echo $c['borde']; ?>; }
  .divider    { height:1px; background:<?php echo $c['borde']; ?>; margin:8px 0 16px; }
</style></head>
<body>
<div class="wrap">

  <!-- Encabezado -->
  <div class="header">
    <h1>📋 Informe de Gestión Documental</h1>
    <p>Período: <?php echo $labelMes; ?> &nbsp;·&nbsp; Generado: <?php echo date('d/m/Y H:i'); ?></p>
  </div>

  <!-- Stats globales -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-val"><?php echo $totalDocs; ?></div>
      <div class="stat-lbl">Documentos<br>del mes</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?php echo $cumplimiento; ?>%</div>
      <div class="stat-lbl">Cumplimiento<br>documentos</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?php echo $enviaron; ?> / <?php echo $totalProfs; ?></div>
      <div class="stat-lbl">Docentes que<br>enviaron</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?php echo $totalDriveGlobal; ?></div>
      <div class="stat-lbl">Formatos Drive<br>activos</div>
    </div>
  </div>

  <!-- Tabla por usuario -->
  <div class="section">
    <div class="sec-title">Detalle por Docente / Funcionario</div>
    <?php
    $rolActual = '';
    foreach ($usuarios as $u):
        $rol = $u['rol'];
        $totalDriveRol  = $drivePorRol[$rol] ?? 0;
        $entregadosDrive = $entregasPorUsuario[$u['id']] ?? 0;
        $faltanDrive     = max(0, $totalDriveRol - $entregadosDrive);
        $pctDrive        = $totalDriveRol > 0 ? round(($entregadosDrive / $totalDriveRol) * 100) : 0;
        $pctDocs         = $u['docs_mes'] > 0 ? 100 : 0;
        $colorDrive      = $barraColor($pctDrive);

        if ($rolActual !== $rol):
            $rolActual = $rol;
    ?>
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:<?php echo $c['gris']; ?>;margin:<?php echo $rolActual === $rol && $rol !== array_key_first((array)$usuarios) ? '20px' : '0px'; ?> 0 8px;">
        <?php echo $roleLabels[$rol] ?? $rol; ?>
    </div>
    <?php endif; ?>

    <div class="user-row">
      <div class="user-name"><?php echo htmlspecialchars($u['nombre']); ?></div>
      <div class="user-role"><?php echo htmlspecialchars($u['email']); ?></div>
      <div class="divider"></div>
      <div class="metrics">

        <!-- Documentos del mes -->
        <div class="metric">
          <div class="metric-lbl">📄 Docs. creados este mes</div>
          <div class="metric-val"><?php echo $u['docs_mes']; ?> docs.</div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?php echo $u['docs_mes'] > 0 ? 100 : 0; ?>%;background:<?php echo $u['docs_mes'] > 0 ? $c['verde'] : $c['gris']; ?>;"></div>
          </div>
        </div>

        <!-- Drive: entregados -->
        <div class="metric">
          <div class="metric-lbl">✅ Formatos Drive entregados</div>
          <div class="metric-val" style="color:<?php echo $colorDrive; ?>">
            <?php echo $entregadosDrive; ?> / <?php echo $totalDriveRol; ?>
            &nbsp;
            <?php if ($pctDrive >= 80): ?>
              <span class="badge-ok"><?php echo $pctDrive; ?>%</span>
            <?php elseif ($pctDrive >= 50): ?>
              <span class="badge-warn"><?php echo $pctDrive; ?>%</span>
            <?php else: ?>
              <span class="badge-err"><?php echo $pctDrive; ?>%</span>
            <?php endif; ?>
          </div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?php echo $pctDrive; ?>%;background:<?php echo $colorDrive; ?>;"></div>
          </div>
        </div>

        <!-- Drive: pendientes -->
        <div class="metric">
          <div class="metric-lbl">⏳ Formatos Drive pendientes</div>
          <div class="metric-val" style="color:<?php echo $faltanDrive > 0 ? $c['rojo'] : $c['verde']; ?>">
            <?php if ($faltanDrive > 0): ?>
              <span class="badge-err"><?php echo $faltanDrive; ?> pendiente<?php echo $faltanDrive > 1 ? 's' : ''; ?></span>
            <?php else: ?>
              <span class="badge-ok">Sin pendientes</span>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pie -->
  <div class="footer">
    <strong>Sistema de Gestión Documental</strong> &nbsp;·&nbsp; Universitaria de Colombia<br>
    Este mensaje fue generado automáticamente. &copy; <?php echo date('Y'); ?>
  </div>

</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
