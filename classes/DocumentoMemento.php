<?php

// Clase Memento que almacena el estado del documento
class DocumentoMemento {
    private $estado;
    private $timestamp;

    public function __construct(array $estado) {
        $this->estado = $estado;
        $this->timestamp = time();
    }

    public function getEstado(): array {
        return $this->estado;
    }

    public function getTimestamp(): int {
        return $this->timestamp;
    }
}

// Clase Caretaker que gestiona los mementos
class DocumentoCaretaker {
    private $mementos = [];
    private $indiceActual = -1;
    private $maxMementos = 50; 

    public function guardarMemento(DocumentoMemento $memento) {
        // Eliminar todos los mementos después del índice actual
        $this->mementos = array_slice($this->mementos, 0, $this->indiceActual + 1);
        
        // Agregar el nuevo memento
        $this->mementos[] = $memento;
        $this->indiceActual = count($this->mementos) - 1;

        // Limitar el número de mementos
        if (count($this->mementos) > $this->maxMementos) {
            $this->mementos = array_slice($this->mementos, -$this->maxMementos);
            $this->indiceActual = count($this->mementos) - 1;
        }
    }

    public function deshacer(): ?DocumentoMemento {
        if ($this->indiceActual > 0) {
            $this->indiceActual--;
            return $this->mementos[$this->indiceActual];
        }
        return null;
    }

    public function rehacer(): ?DocumentoMemento {
        if ($this->indiceActual < count($this->mementos) - 1) {
            $this->indiceActual++;
            return $this->mementos[$this->indiceActual];
        }
        return null;
    }

    public function puedeDeshacer(): bool {
        return $this->indiceActual > 0;
    }

    public function puedeRehacer(): bool {
        return $this->indiceActual < count($this->mementos) - 1;
    }

    public function getMementoActual(): ?DocumentoMemento {
        return $this->indiceActual >= 0 ? $this->mementos[$this->indiceActual] : null;
    }
}

// Clase Editor que maneja el documento y sus estados
class DocumentoEditor {
    private $documento;
    private $caretaker;
    private $ultimoGuardado;

    public function __construct(IDocumento $documento) {
        $this->documento = $documento;
        $this->caretaker = new DocumentoCaretaker();
        $this->guardarEstado();
    }

    public function guardarEstado() {
        $estado = [
            'tipo' => $this->documento->getTipo(),
            'titulo' => $this->documento->getTitulo(),
            'contenido' => $this->documento->getContenido(),
            'encabezado' => $this->documento->getEncabezado(),
            'pieDePagina' => $this->documento->getPieDePagina()
        ];

        // Agregar datos específicos según el tipo de documento
        if ($this->documento instanceof Carta) {
            $estado['destinatario'] = $this->documento->getDestinatario();
        } elseif ($this->documento instanceof Factura) {
            $estado['numeroFactura'] = $this->documento->getNumeroFactura();
            $estado['monto'] = $this->documento->getMonto();
        }

        $memento = new DocumentoMemento($estado);
        $this->caretaker->guardarMemento($memento);
        $this->ultimoGuardado = time();
    }

    public function deshacer(): bool {
        $memento = $this->caretaker->deshacer();
        if ($memento) {
            $this->restaurarEstado($memento);
            return true;
        }
        return false;
    }

    public function rehacer(): bool {
        $memento = $this->caretaker->rehacer();
        if ($memento) {
            $this->restaurarEstado($memento);
            return true;
        }
        return false;
    }

    private function restaurarEstado(DocumentoMemento $memento) {
        $estado = $memento->getEstado();
        
        // Crear una nueva instancia del documento según el tipo
        $factory = DocumentoFactoryCreator::getFactory($estado['tipo']);
        
        $datosAdicionales = [];
        if ($estado['tipo'] === 'carta') {
            $datosAdicionales['destinatario'] = $estado['destinatario'];
        } elseif ($estado['tipo'] === 'factura') {
            $datosAdicionales['numero_factura'] = $estado['numeroFactura'];
            $datosAdicionales['monto'] = $estado['monto'];
        }

        $this->documento = $factory->createDocumento(
            $estado['titulo'],
            $estado['contenido'],
            $estado['encabezado'],
            $estado['pieDePagina'],
            $datosAdicionales
        );
    }

    public function getDocumento(): IDocumento {
        return $this->documento;
    }

    public function puedeDeshacer(): bool {
        return $this->caretaker->puedeDeshacer();
    }

    public function puedeRehacer(): bool {
        return $this->caretaker->puedeRehacer();
    }

    public function getUltimoGuardado(): int {
        return $this->ultimoGuardado;
    }
} 