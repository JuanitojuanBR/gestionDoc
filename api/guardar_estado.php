<?php
require_once '../config/sesion.php';  // ← Ya hace session_start() internamente
requerirLogin();
require_once '../config/database.php';
require_once '../classes/DocumentoFactory.php';
require_once '../classes/DocumentoMemento.php';

// ❌ ELIMINAR ESTA LÍNEA: session_start();  
// Ya se ejecutó en config/sesion.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($_SESSION['documento_editor']) && isset($_SESSION['documento_id'])) {
        $editor = $_SESSION['documento_editor'];
        $documento = $editor->getDocumento();
        
        // Crear una nueva instancia del documento con los datos actualizados
        $factory = DocumentoFactoryCreator::getFactory($documento->getTipo());
        
        $datosAdicionales = [];
        if ($documento instanceof Carta) {
            $datosAdicionales['destinatario'] = $data['destinatario'] ?? '';
        } elseif ($documento instanceof Factura) {
            $datosAdicionales['numero_factura'] = $data['numero_factura'] ?? '';
            $datosAdicionales['monto'] = $data['monto'] ?? 0.0;
        }

        $nuevoDocumento = $factory->createDocumento(
            $data['titulo'],
            $data['contenido'],
            $data['encabezado'],
            $data['pie_pagina'],
            $datosAdicionales
        );

        // Actualizar el editor con el nuevo documento
        $editor = new DocumentoEditor($nuevoDocumento);
        $_SESSION['documento_editor'] = $editor;
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Editor no encontrado']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?>
