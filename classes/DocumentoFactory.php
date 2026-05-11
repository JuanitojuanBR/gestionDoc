<?php

// Interfaz base para todos los documentos
interface IDocumento {
    public function getTipo(): string;
    public function getTitulo(): string;
    public function getContenido(): string;
    public function getEncabezado(): string;
    public function getPieDePagina(): string;
    public function clonar(): IDocumento;
}

// Clase base abstracta para documentos
abstract class DocumentoBase implements IDocumento {
    protected $titulo;
    protected $contenido;
    protected $encabezado;
    protected $pieDePagina;

    public function __construct($titulo, $contenido, $encabezado = '', $pieDePagina = '') {
        $this->titulo = $titulo;
        $this->contenido = $contenido;
        $this->encabezado = $encabezado;
        $this->pieDePagina = $pieDePagina;
    }

    public function getTitulo(): string {
        return $this->titulo;
    }

    public function getContenido(): string {
        return $this->contenido;
    }

    public function getEncabezado(): string {
        return $this->encabezado;
    }

    public function getPieDePagina(): string {
        return $this->pieDePagina;
    }

    public function setTitulo($titulo) {
        $this->titulo = $titulo;
    }

    public function setContenido($contenido) {
        $this->contenido = $contenido;
    }

    public function setEncabezado($encabezado) {
        $this->encabezado = $encabezado;
    }

    public function setPieDePagina($pieDePagina) {
        $this->pieDePagina = $pieDePagina;
    }
}

// Clases concretas para diferentes tipos de documentos
class Reporte extends DocumentoBase {
    public function getTipo(): string {
        return 'reporte';
    }

    public function clonar(): IDocumento {
        return new Reporte(
            $this->titulo,
            $this->contenido,
            $this->encabezado,
            $this->pieDePagina
        );
    }
}


class Carta extends DocumentoBase {
    private $destinatario;

    public function __construct($titulo, $contenido, $destinatario, $encabezado = '', $pieDePagina = '') {
        parent::__construct($titulo, $contenido, $encabezado, $pieDePagina);
        $this->destinatario = $destinatario;
    }

    public function getTipo(): string {
        return 'carta';
    }

    public function getDestinatario(): string {
        return $this->destinatario;
    }

    public function setDestinatario($destinatario) {
        $this->destinatario = $destinatario;
    }

    public function clonar(): IDocumento {
        return new Carta(
            $this->titulo,
            $this->contenido,
            $this->destinatario,
            $this->encabezado,
            $this->pieDePagina
        );
    }
}

class Factura extends DocumentoBase {
    private $numeroFactura;
    private $monto;

    public function __construct($titulo, $contenido, $numeroFactura, $monto, $encabezado = '', $pieDePagina = '') {
        parent::__construct($titulo, $contenido, $encabezado, $pieDePagina);
        $this->numeroFactura = $numeroFactura;
        $this->monto = $monto;
    }

    public function getTipo(): string {
        return 'factura';
    }

    public function getNumeroFactura(): string {
        return $this->numeroFactura;
    }

    public function getMonto(): float {
        return $this->monto;
    }

    public function setNumeroFactura($numeroFactura) {
        $this->numeroFactura = $numeroFactura;
    }

    public function setMonto($monto) {
        $this->monto = $monto;
    }

    public function clonar(): IDocumento {
        return new Factura(
            $this->titulo,
            $this->contenido,
            $this->numeroFactura,
            $this->monto,
            $this->encabezado,
            $this->pieDePagina
        );
    }
}

class ArchivoSubido extends DocumentoBase {
    private $nombreArchivo;
    private $rutaOriginal;

    public function __construct($titulo, $contenido, $nombreArchivo = '', $rutaOriginal = '', $encabezado = '', $pieDePagina = '') {
        parent::__construct($titulo, $contenido, $encabezado, $pieDePagina);
        $this->nombreArchivo = $nombreArchivo;
        $this->rutaOriginal = $rutaOriginal;
    }

    public function getTipo(): string {
        return 'archivo_subido';
    }

    public function getNombreArchivo(): string {
        return $this->nombreArchivo;
    }

    public function getRutaOriginal(): string {
        return $this->rutaOriginal;
    }

    public function setNombreArchivo($nombreArchivo) {
        $this->nombreArchivo = $nombreArchivo;
    }

    public function setRutaOriginal($rutaOriginal) {
        $this->rutaOriginal = $rutaOriginal;
    }

    public function clonar(): IDocumento {
        return new ArchivoSubido(
            $this->titulo,
            $this->contenido,
            $this->nombreArchivo,
            $this->rutaOriginal,
            $this->encabezado,
            $this->pieDePagina
        );
    }
}

class DocumentoConvertido extends DocumentoBase {
    private $archivoOriginal;
    private $tipoOriginal;

    public function __construct($titulo, $contenido, $archivoOriginal = '', $tipoOriginal = '', $encabezado = '', $pieDePagina = '') {
        parent::__construct($titulo, $contenido, $encabezado, $pieDePagina);
        $this->archivoOriginal = $archivoOriginal;
        $this->tipoOriginal = $tipoOriginal;
    }

    public function getTipo(): string {
        return 'documento_convertido';
    }

    public function getArchivoOriginal(): string {
        return $this->archivoOriginal;
    }

    public function getTipoOriginal(): string {
        return $this->tipoOriginal;
    }

    public function clonar(): IDocumento {
        return new DocumentoConvertido(
            $this->titulo,
            $this->contenido,
            $this->archivoOriginal,
            $this->tipoOriginal,
            $this->encabezado,
            $this->pieDePagina
        );
    }
}


// Fábrica abstracta de documentos
abstract class DocumentoFactory {
    abstract public function createDocumento($titulo, $contenido, $encabezado = '', $pieDePagina = '', $datosAdicionales = []): IDocumento;
}

// Fábricas concretas para cada tipo de documento
class ReporteFactory extends DocumentoFactory {
    public function createDocumento($titulo, $contenido, $encabezado = '', $pieDePagina = '', $datosAdicionales = []): IDocumento {
        return new Reporte($titulo, $contenido, $encabezado, $pieDePagina);
    }
}



class CartaFactory extends DocumentoFactory {
    public function createDocumento($titulo, $contenido, $encabezado = '', $pieDePagina = '', $datosAdicionales = []): IDocumento {
        $destinatario = $datosAdicionales['destinatario'] ?? '';
        return new Carta($titulo, $contenido, $destinatario, $encabezado, $pieDePagina);
    }
}

class FacturaFactory extends DocumentoFactory {
    public function createDocumento($titulo, $contenido, $encabezado = '', $pieDePagina = '', $datosAdicionales = []): IDocumento {
        $numeroFactura = $datosAdicionales['numero_factura'] ?? '';
        $monto = $datosAdicionales['monto'] ?? 0.0;
        return new Factura($titulo, $contenido, $numeroFactura, $monto, $encabezado, $pieDePagina);
    }
}

class ArchivoSubidoFactory extends DocumentoFactory {
    public function createDocumento($titulo, $contenido, $encabezado = '', $pieDePagina = '', $datosAdicionales = []): IDocumento {
        $nombreArchivo = $datosAdicionales['nombre_archivo'] ?? '';
        $rutaOriginal = $datosAdicionales['ruta_original'] ?? '';
        return new ArchivoSubido($titulo, $contenido, $nombreArchivo, $rutaOriginal, $encabezado, $pieDePagina);
    }
}

class DocumentoConvertidoFactory extends DocumentoFactory {
    public function createDocumento($titulo, $contenido, $encabezado = '', $pieDePagina = '', $datosAdicionales = []): IDocumento {
        $archivoOriginal = $datosAdicionales['archivo_original'] ?? '';
        $tipoOriginal = $datosAdicionales['tipo_original'] ?? '';
        return new DocumentoConvertido($titulo, $contenido, $archivoOriginal, $tipoOriginal, $encabezado, $pieDePagina);
    }
}



// Fábrica principal que selecciona la fábrica adecuada según el tipo
class DocumentoFactoryCreator {
        public static function getFactory($tipo): DocumentoFactory {
        return match($tipo) {
            'reporte' => new ReporteFactory(),
            'carta' => new CartaFactory(),
            'factura' => new FacturaFactory(),
            'archivo_subido' => new ArchivoSubidoFactory(),
            'documento_convertido' => new DocumentoConvertidoFactory(), // ← AGREGAR ESTA LÍNEA
            default => throw new Exception("Tipo de documento no soportado: $tipo")
        };
    }

}
