# Sistema de Gestión Documental Universitaria

Este es un sistema web diseñado para la gestión y seguimiento de documentos académicos y administrativos en una institución universitaria. Permite la creación, edición, clonación y carga de documentos, así como un sistema de entregas estilo "Drive" con retroalimentación entre docentes y administración.

## 🚀 Características Principaless

- **Gestión de Documentos:** Creación de reportes, cartas y facturas con editor enriquecido.
- **Módulo de Entregas Drive:** Los docentes pueden subir documentos requeridos por la administración.
- **Sistema de Feedback:** Comunicación directa entre administradores y docentes sobre documentos entregados.
- **Filtros Avanzados:** Búsqueda en tiempo real por metadatos, fechas, tipos y estados en todos los módulos.
- **Informes Académicos:** Generación de informes de gestión mensual con estadísticas de cumplimiento.
- **Roles y Permisos:** Control de acceso basado en roles (Administrador, Decano, Docente TC, Cátedra).

## 🛠️ Instalación y Configuración

### Requisitos
- Servidor local (XAMPP, WAMP, Laragon, etc.)
- PHP 7.4 o superior
- MySQL / MariaDB

### Pasos para instalar
1. **Clonar o descargar** el repositorio en la carpeta `htdocs` de tu servidor local.
2. **Importar la Base de Datos**:
   - Abre `phpMyAdmin`.
   - Crea una base de datos llamada `gestion_documentos`.
   - Selecciona la base de datos e importa el archivo `database_schema.sql` que se encuentra en la raíz del proyecto.
3. **Configuración de conexión**:
   - Si tu usuario de MySQL no es `root` o tienes contraseña, edita el archivo `config/database.php`.
4. **Acceder al sistema**:
   - Abre tu navegador y ve a `http://localhost/gestionDoc-main/`.

### 🔑 Credenciales de Prueba (Por defecto)
- **Administrador:** `admin@universidad.edu.co` / `password` (Si se usó el hash por defecto)
- **Decano:** `decano@universidad.edu.co` / `password`

## 📂 Estructura del Proyecto
- `api/`: Endpoints para procesamiento de datos y feedback.
- `config/`: Archivos de configuración de base de datos y sesiones.
- `includes/`: Componentes reutilizables (Header, Footer, Nav).
- `uploads/`: Carpeta donde se almacenan los archivos subidos (Asegúrate de que tenga permisos de escritura).

---
*Desarrollado para la mejora de procesos académicos institucionales.*
