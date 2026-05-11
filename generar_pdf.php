<?php

ob_start();

require_once 'config/database.php';
require_once 'classes/DocumentoFactory.php';
require_once 'vendor/autoload.php';

// Usar el namespace correcto de TCPDF
use TCPDF as TCPDF;

class DocumentoPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'Sistema de Gestión de Documentos', 0, 1, 'C');
        $this->Ln(10);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
    }
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Obtener el documento
    $stmt = $conn->prepare("SELECT * FROM documentos WHERE id = ?");
    $stmt->execute([$id]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

if ($documento) {
    // Verificar si es un archivo subido
    if ($documento['tipo'] === 'archivo_subido' || $documento['es_archivo_subido'] == 1) {
        // Para archivos subidos, redirigir a la descarga del archivo original
        if (file_exists($documento['ruta_archivo'])) {
            header('Content-Type: ' . $documento['tipo_archivo']);
            header('Content-Disposition: inline; filename="' . $documento['nombre_archivo'] . '"');
            header('Content-Length: ' . filesize($documento['ruta_archivo']));
            readfile($documento['ruta_archivo']);
            exit();
        } else {
            die("Error: Archivo no encontrado");
        }
    }
    
    // Para documentos creados manualmente
    $contenido = json_decode($documento['contenido'], true);
    
    // Validar que el contenido sea un JSON válido
    if (!$contenido) {
        die("Error: Contenido del documento inválido");
    }
    
    // Crear una instancia del documento usando la fábrica
    $factory = DocumentoFactoryCreator::getFactory($documento['tipo']);
    $documentoObj = $factory->createDocumento(
        $contenido['titulo'],
        $contenido['contenido'],
        $contenido['encabezado'] ?? '',
        $contenido['pieDePagina'] ?? '',
        $contenido['datosAdicionales'] ?? []
    );

    ob_clean();

        // Crear el PDF
        $pdf = new DocumentoPDF();
        
        // Configurar el documento
        $pdf->SetCreator('Sistema de Gestión de Documentos');
        $pdf->SetAuthor('Sistema de Gestión de Documentos');
        $pdf->SetTitle($documentoObj->getTitulo());
        
        // Agregar una página
        $pdf->AddPage();
        
        // Configurar fuentes
        $pdf->SetFont('helvetica', 'B', 14);
        
        // Título del documento
        $pdf->Cell(0, 10, $documentoObj->getTitulo(), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Encabezado
        if ($documentoObj->getEncabezado()) {
            $pdf->SetFont('helvetica', 'I', 12);
            $pdf->Cell(0, 10, $documentoObj->getEncabezado(), 0, 1, 'L');
            $pdf->Ln(5);
        }
        
        // Datos específicos según el tipo de documento
        $pdf->SetFont('helvetica', '', 12);
        if ($documentoObj instanceof Carta) {
            $pdf->Cell(0, 10, 'Destinatario: ' . $documentoObj->getDestinatario(), 0, 1, 'L');
            $pdf->Ln(5);
        } elseif ($documentoObj instanceof Factura) {
            $pdf->Cell(0, 10, 'Número de Factura: ' . $documentoObj->getNumeroFactura(), 0, 1, 'L');
            $pdf->Cell(0, 10, 'Monto: $' . number_format($documentoObj->getMonto(), 2), 0, 1, 'L');
            $pdf->Ln(5);
        }
        
        // Contenido
        $pdf->MultiCell(0, 10, $documentoObj->getContenido(), 0, 'J');
        $pdf->Ln(10);
        
        // Pie de página
        if ($documentoObj->getPieDePagina()) {
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->Cell(0, 10, $documentoObj->getPieDePagina(), 0, 1, 'C');
        }
        
        // Generar el PDF
        $pdf->Output($documentoObj->getTitulo() . '.pdf', 'I');
        exit();
    }
}

// Si no se proporcionó un ID o el documento no existe, redirigir a la lista
header("Location: listar_documentos.php");
exit(); 
