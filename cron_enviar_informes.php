<?php
/**
 * Script para envío automático de informes.
 * Debe ser ejecutado mediante el Programador de Tareas (Windows) o Cron (Linux).
 * Ejemplo: php cron_enviar_informes.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/MailService.php';

echo "[LOG] Iniciando proceso de cron para envíos de informes...\n";

try {
    // 1. Obtener configuración
    $stmt = $conn->query("SELECT * FROM config_envio_informes WHERE envio_automatico = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo "[LOG] Envío automático desactivado o no configurado.\n";
        exit;
    }

    // 2. Verificar frecuencia
    $ahora = new DateTime();
    $horaConfig = new DateTime($config['hora_envio']);
    
    // Solo enviar si es después de la hora configurada (ej: 08:00 AM)
    if ($ahora->format('H:i') < $horaConfig->format('H:i')) {
        echo "[LOG] Aún no es la hora programada ({$config['hora_envio']}).\n";
        exit;
    }

    $enviar = false;
    if (!$config['ultimo_envio']) {
        $enviar = true;
    } else {
        $ultimo = new DateTime($config['ultimo_envio']);
        $diff = $ahora->diff($ultimo);
        
        if ($diff->days >= $config['intervalo_dias']) {
            $enviar = true;
        }
    }

    if ($enviar) {
        echo "[LOG] Intervalo cumplido. Enviando informe...\n";
        $mailService = new MailService($conn);
        $res = $mailService->enviarInformeMensual(null, null, false);
        
        if ($res['success']) {
            echo "[SUCCESS] " . $res['message'] . "\n";
        } else {
            echo "[ERROR] " . $res['message'] . "\n";
        }
    } else {
        echo "[LOG] Ya se envió un informe recientemente. Esperando siguiente intervalo.\n";
    }

} catch (Exception $e) {
    echo "[CRITICAL ERROR] " . $e->getMessage() . "\n";
}
