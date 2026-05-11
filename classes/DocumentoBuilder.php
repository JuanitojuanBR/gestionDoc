<?php

interface DocumentoBuilder {
    public function reset();
    public function setTitulo($titulo);
    public function agregarSeccion($titulo, $contenido);
    public function setEncabezado($texto);
    public function setPieDePagina($texto);
    public function setContenido($contenido);
    public function getResultado(): Documento;
}

class DocumentoBuilderConcreto implements DocumentoBuilder {
    private $documento;

    public function __construct() {
        $this->reset();
    }

    public function reset() {
        $this->documento = new Documento();
    }

    public function setTitulo($titulo) {
        $this->documento->setTitulo($titulo);
        return $this;
    }

    public function agregarSeccion($titulo, $contenido) {
        $seccion = [
            'titulo' => $titulo,
            'contenido' => $contenido
        ];
        $this->documento->agregarSeccion($seccion);
        return $this;
    }

    public function setEncabezado($texto) {
        $this->documento->setEncabezado($texto);
        return $this;
    }

    public function setPieDePagina($texto) {
        $this->documento->setPieDePagina($texto);
        return $this;
    }

    public function setContenido($contenido) {
        $this->documento->setContenido($contenido);
        return $this;
    }

    public function getResultado(): Documento {
        return $this->documento;
    }
} 