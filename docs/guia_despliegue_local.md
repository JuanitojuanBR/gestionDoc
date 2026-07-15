# 💻 Guía de Despliegue Local (En otra Computadora)

Para ejecutar este sistema en otra computadora de forma local, utilizaremos **XAMPP** para simular el servidor Apache y la base de datos MySQL.

---

## 📋 Requisitos Previos

1. Descargar **XAMPP** para Windows (versión con PHP 7.4, 8.1 u 8.2):
   * Enlace oficial: [https://www.apachefriends.org/es/index.html](https://www.apachefriends.org/es/index.html)
2. El archivo comprimido **`proyecto.zip`** (que generamos anteriormente) o acceso al repositorio Git para descargar los archivos.

---

## 🚀 Pasos para la Instalación

1. **Instalar XAMPP**: Ejecuta el instalador descargado asegurándote de activar los componentes Apache y MySQL.
2. **Copiar el Proyecto**: Ve a `C:\xampp\htdocs\` en la nueva PC y extrae el contenido de `proyecto.zip` de manera que quede en `C:\xampp\htdocs\gestionDoc-main\`.
3. **Iniciar los Servidores**: Abre el *XAMPP Control Panel* e inicia (Start) **Apache** y **MySQL**.
4. **Importar la Base de Datos**:
   * Entra a [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).
   * Crea una base de datos llamada `gestion_documentos`.
   * Selecciónala, ve a **Importar**, elige el archivo `database/database_export.sql` dentro del proyecto y ejecútalo.
5. **Acceder al Sistema**: Entra desde el navegador a **[http://localhost/gestionDoc-main/](http://localhost/gestionDoc-main/)**.

---

## 🔑 Credenciales de Acceso por Defecto
* **Administrador:** `admin@universidad.edu.co` / contraseña: `password`
* **Decano:** `decano@universidad.edu.co` / contraseña: `password`
