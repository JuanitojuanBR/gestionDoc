<?php
require_once 'config/database.php';
require_once 'classes/DocumentoFactory.php';

session_start();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Obtener el documento original
    $stmt = $conn->prepare("SELECT * FROM documentos WHERE id = ?");
    $stmt->execute([$id]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($documento) {
        $contenido = json_decode($documento['contenido'], true);
        
        // Crear una instancia del documento usando la fábrica
        $factory = DocumentoFactoryCreator::getFactory($documento['tipo']);
        $documentoObj = $factory->createDocumento(
            $contenido['titulo'],
            $contenido['contenido'],
            $contenido['encabezado'] ?? '',
            $contenido['pieDePagina'] ?? '',
            $contenido['datosAdicionales'] ?? []
        );

        // Clonar el documento
        $documentoClonado = $documentoObj->clonar();
        
        // Modificar el título para indicar que es una copia
        $documentoClonado->setTitulo($documentoClonado->getTitulo() . ' (Copia)');

        // Preparar los datos para guardar
        $contenidoClonado = [
            'tipo' => $documentoClonado->getTipo(),
            'titulo' => $documentoClonado->getTitulo(),
            'contenido' => $documentoClonado->getContenido(),
            'encabezado' => $documentoClonado->getEncabezado(),
            'pieDePagina' => $documentoClonado->getPieDePagina()
        ];

        // Agregar datos específicos según el tipo de documento
        if ($documentoClonado instanceof Carta) {
            $contenidoClonado['datosAdicionales'] = [
                'destinatario' => $documentoClonado->getDestinatario()
            ];
        } elseif ($documentoClonado instanceof Factura) {
            $contenidoClonado['datosAdicionales'] = [
                'numero_factura' => $documentoClonado->getNumeroFactura(),
                'monto' => $documentoClonado->getMonto()
            ];
        }

        // Guardar el documento clonado en la base de datos
        $stmt = $conn->prepare("INSERT INTO documentos (tipo, contenido, fecha_creacion) VALUES (?, ?, NOW())");
        $stmt->execute([
            $documentoClonado->getTipo(),
            json_encode($contenidoClonado)
        ]);

        // Redirigir a la lista de documentos
        header("Location: listar_documentos.php");
        exit();
    }
}

// Si no se proporcionó un ID o el documento no existe, redirigir a la lista
header("Location: listar_documentos.php");
exit(); 
