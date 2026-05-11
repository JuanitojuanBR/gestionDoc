<?php

class Documento {
    private $titulo;
    private $secciones = [];
    private $encabezado;
    private $pieDePagina;
    private $contenido;

    public function setTitulo($titulo) {
        $this->titulo = $titulo;
    }

    public function getTitulo() {
        return $this->titulo;
    }

    public function agregarSeccion($seccion) {
        $this->secciones[] = $seccion;
    }

    public function getSecciones() {
        return $this->secciones;
    }

    public function setEncabezado($encabezado) {
        $this->encabezado = $encabezado;
    }

    public function getEncabezado() {
        return $this->encabezado;
    }

    public function setPieDePagina($pieDePagina) {
        $this->pieDePagina = $pieDePagina;
    }

    public function getPieDePagina() {
        return $this->pieDePagina;
    }

    public function setContenido($contenido) {
        $this->contenido = $contenido;
    }

    public function getContenido() {
        return $this->contenido;
    }
} 